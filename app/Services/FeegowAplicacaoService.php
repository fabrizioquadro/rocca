<?php

namespace App\Services;

use App\Enums\StatusSemanaItem;
use App\Enums\TipoLogPrescricao;
use App\Models\FeegowFila;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAtendimento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra as aplicações da enfermagem como agendamentos na Feegow.
 *
 * Fluxo (mesmo desenho do sistema do instituto):
 *  1. ao finalizar o atendimento, `registrar()` grava o envio na tabela
 *     `feegow_filas` e tenta enviar na hora (best-effort);
 *  2. se a API falhar, o registro fica pendente e o comando `feegow:fila`
 *     (agendado no cron) reenvia com backoff;
 *  3. a tela da semana mostra a situação e permite reenviar na mão.
 *
 * Cada atendimento finalizado gera um agendamento: numa aplicação parcial o
 * paciente volta e o segundo atendimento gera o segundo agendamento.
 */
class FeegowAplicacaoService
{
    /**
     * Escala de espera entre tentativas (minutos).
     */
    private const BACKOFF = [1, 5, 15, 60, 180, 360, 720, 1440];

    public function __construct(private FeegowService $feegow)
    {
    }

    /**
     * A integração está configurada (token no .env)?
     */
    public function configurado(): bool
    {
        return $this->feegow->configurado();
    }

    /**
     * Registra a aplicação do atendimento na Feegow.
     *
     * Nunca lança exceção: a aplicação do paciente não pode falhar por causa
     * da integração. O que não for enviado agora fica na fila.
     */
    public function registrar(PrescricaoSemanaAtendimento $atendimento): ?FeegowFila
    {
        if (! $this->configurado()) {
            return null;
        }

        try {
            $registro = $this->enfileirar($atendimento);

            if ($registro && ! $registro->enviado) {
                try {
                    $this->enviar($registro);
                } catch (Throwable $e) {
                    // Guarda o erro e agenda a próxima tentativa: o comando
                    // `feegow:fila` reenvia e a tela da semana já mostra o motivo.
                    $this->reprogramar($registro, $e);
                }
            }

            return $registro;
        } catch (Throwable $e) {
            Log::error('Feegow: falha ao registrar a aplicação do atendimento '.$atendimento->id.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Coloca (ou recoloca) o atendimento na fila de envio, sem chamar a API.
     */
    public function enfileirar(PrescricaoSemanaAtendimento $atendimento): ?FeegowFila
    {
        $semana = $atendimento->semana()
            ->with(['prescricao.paciente', 'itens.medicamento', 'itens.combo'])
            ->first();

        $paciente = $semana?->prescricao?->paciente;

        if (! $semana || ! $paciente || ! $paciente->paciente_id) {
            Log::warning('Feegow: atendimento '.$atendimento->id.' sem semana ou paciente com id na Feegow.');

            return null;
        }

        $payload = $this->payload($atendimento, $semana);

        // Já existe um envio deste atendimento? Só recomeça (reenvio manual).
        $registro = FeegowFila::where('prescricao_semana_atendimento_id', $atendimento->id)
            ->where('evento', 'aplicacao')
            ->latest('id')
            ->first();

        if ($registro && ! $registro->enviado) {
            $registro->update([
                'situacao' => FeegowFila::PENDENTE,
                'tentativas' => 0,
                'proxima_tentativa' => null,
                'erro' => null,
                'payload' => $payload,
            ]);

            return $registro;
        }

        if ($registro) {
            return $registro;
        }

        return FeegowFila::create([
            'prescricao_id' => $semana->prescricao_id,
            'prescricao_semana_id' => $semana->id,
            'prescricao_semana_atendimento_id' => $atendimento->id,
            'evento' => 'aplicacao',
            'situacao' => FeegowFila::PENDENTE,
            'tentativas' => 0,
            'payload' => $payload,
        ]);
    }

    /**
     * Processa a fila: envia os pendentes e reprograma os que falharem.
     * Chamado pelo comando `feegow:fila`.
     */
    public function processarFila(int $limite = 20): int
    {
        if (! $this->configurado()) {
            return 0;
        }

        $registros = FeegowFila::pendentes()->orderBy('id')->limit($limite)->get();

        foreach ($registros as $registro) {
            try {
                $this->enviar($registro);
            } catch (Throwable $e) {
                $this->reprogramar($registro, $e);
            }
        }

        return $registros->count();
    }

    /**
     * Envia um registro para a Feegow (new-appoint + status "atendido").
     *
     * @throws Throwable quando a Feegow recusa o agendamento.
     */
    public function enviar(FeegowFila $registro): void
    {
        $payload = $registro->payload ?? [];

        try {
            $retorno = $this->feegow->post('appoints/new-appoint', [
                'local_id' => $payload['local_id'] ?? $this->localId(),
                'paciente_id' => $payload['paciente_id'] ?? 0,
                'profissional_id' => $payload['profissional_id'] ?? $this->profissionalId(),
                'especialidade_id' => $payload['especialidade_id'] ?? $this->especialidadeId(),
                'procedimento_id' => $payload['procedimento_id'] ?? $this->procedimentoId(),
                'data' => $this->dataParaEnvio($payload['data'] ?? null),
                'horario' => $payload['horario'] ?? now()->format('H:i:s'),
                'valor' => 0,
                'plano' => 0,
                'notas' => $payload['notas'] ?? '',
            ]);
        } catch (Throwable $e) {
            throw new \RuntimeException('appoints/new-appoint: '.$e->getMessage(), 0, $e);
        }

        $conteudo = $this->feegow->exigirSucesso($retorno, 'appoints/new-appoint');
        $agendamentoId = $conteudo['agendamento_id'] ?? null;

        // Status 3 = atendido: a aplicação já aconteceu quando o envio sai.
        if ($agendamentoId) {
            try {
                $this->feegow->post('appoints/statusUpdate', [
                    'AgendamentoID' => $agendamentoId,
                    'StatusID' => 3,
                    'Obs' => 'Aplicação registrada pelo sistema Rocca.',
                ]);
            } catch (Throwable $e) {
                // O agendamento já existe: o status é um detalhe, não bloqueia.
                Log::warning('Feegow: statusUpdate do agendamento '.$agendamentoId.' falhou: '.$e->getMessage());
            }
        }

        $registro->update([
            'situacao' => FeegowFila::ENVIADO,
            'tentativas' => (int) $registro->tentativas,
            'agendamento_id' => $agendamentoId,
            'enviado_em' => now(),
            'ultima_tentativa' => now(),
            'erro' => null,
        ]);

        $this->registrarLog($registro, $agendamentoId);
    }

    /**
     * Reenvia um registro na hora (botão da tela da semana).
     *
     * @throws Throwable
     */
    public function reenviar(FeegowFila $registro): void
    {
        $registro->update([
            'situacao' => FeegowFila::PENDENTE,
            'tentativas' => 0,
            'proxima_tentativa' => null,
            'erro' => null,
        ]);

        try {
            $this->enviar($registro);
        } catch (Throwable $e) {
            // Registra o erro/motivo e agenda a próxima tentativa (a tela mostra o motivo).
            $this->reprogramar($registro, $e);

            throw $e;
        }
    }

    /**
     * Cria o registro e envia na hora, a partir da semana (uso manual, para
     * aplicações antigas). Devolve o registro criado.
     */
    public function registrarSemana(PrescricaoSemana $semana): ?FeegowFila
    {
        $atendimento = $semana->atendimentos()->orderByDesc('id')->first();

        if (! $atendimento) {
            return null;
        }

        $registro = $this->enfileirar($atendimento);

        if ($registro && ! $registro->enviado) {
            $this->reenviar($registro);
        }

        return $registro;
    }

    /**
     * Quantos minutos esperar antes da próxima tentativa.
     */
    public function backoff(int $tentativas): int
    {
        $indice = min(max($tentativas, 1), count(self::BACKOFF)) - 1;

        return self::BACKOFF[$indice];
    }

    /**
     * Guarda a falha e agenda a próxima tentativa.
     */
    private function reprogramar(FeegowFila $registro, Throwable $e): void
    {
        $tentativas = (int) $registro->tentativas + 1;

        $registro->update([
            'tentativas' => $tentativas,
            'erro' => mb_substr($e->getMessage(), 0, 500),
            'ultima_tentativa' => now(),
            'proxima_tentativa' => now()->addMinutes($this->backoff($tentativas)),
        ]);

        Log::error('Feegow: fila #'.$registro->id.' falhou (tentativa '.$tentativas.'): '.$e->getMessage());
    }

    /**
     * Corpo do envio: identificação da agenda + notas da aplicação.
     *
     * @return array<string, mixed>
     */
    private function payload(PrescricaoSemanaAtendimento $atendimento, PrescricaoSemana $semana): array
    {
        $quando = $atendimento->finalizado_em ?? now();

        return [
            'prescricao_id' => $semana->prescricao_id,
            'semana' => $semana->numero,
            'atendimento_id' => $atendimento->id,
            'local_id' => $this->localId(),
            'profissional_id' => $this->profissionalId(),
            'especialidade_id' => $this->especialidadeId(),
            'procedimento_id' => $this->procedimentoId(),
            'paciente_id' => (int) $semana->prescricao->paciente->paciente_id,
            'data' => $quando->format('d-m-Y'),
            'horario' => $quando->format('H:i:s'),
            'notas' => $this->montarNotas($atendimento, $semana),
        ];
    }

    /**
     * Notas do agendamento na Feegow: tudo o que aconteceu na aplicação.
     */
    private function montarNotas(PrescricaoSemanaAtendimento $atendimento, PrescricaoSemana $semana): string
    {
        $prescricao = $semana->prescricao;
        $paciente = $prescricao?->paciente;
        $formatar = fn ($data) => $data ? $data->format('d/m/Y H:i') : '-';

        $linhas = [];
        $linhas[] = 'PRESCRIÇÃO #'.$prescricao->id.' - SEMANA '.$semana->numero;
        $linhas[] = 'Paciente: '.($paciente?->nome ?? '-');
        $linhas[] = 'Data prevista: '.($semana->data_prevista?->format('d/m/Y') ?? '-');
        $linhas[] = 'Chegada: '.$formatar($atendimento->chegada_em);
        $linhas[] = 'Início do atendimento: '.$formatar($atendimento->iniciado_em);
        $linhas[] = 'Aplicação: '.$formatar($atendimento->finalizado_em);
        $linhas[] = 'Aplicado por: '.($atendimento->finalizadoPor?->nome ?? $atendimento->iniciadoPor?->nome ?? '-');
        $linhas[] = 'Situação da semana: '.($semana->status?->label() ?? '-');
        $linhas[] = 'Obs: '.($semana->observacao ?: ($atendimento->observacao ?: '-'));

        $aplicacoes = $atendimento->aplicacoes()
            ->with(['medicamento', 'item', 'user'])
            ->orderBy('id')
            ->get();

        $linhas[] = '';
        $linhas[] = 'MEDICAMENTOS APLICADOS ('.$aplicacoes->count().'):';
        $linhas[] = $aplicacoes->isEmpty() ? '  (nenhum)' : '';

        foreach ($aplicacoes as $aplicacao) {
            $nome = $aplicacao->item?->nome ?? $aplicacao->medicamento?->nome ?? 'Medicamento';
            $quantidade = $aplicacao->quantidade_formatada.($aplicacao->vasilhame_aberto_id ? ' mg' : ' unidade(s)');

            $linhas[] = '  - '.$nome
                .' | Qtd: '.$quantidade
                .' | Lote: '.($aplicacao->lote ?: '-')
                .' | Código: '.($aplicacao->codigo_barras ?: '-')
                .' | Vencimento: '.($aplicacao->vencimento_formatado ?: '-')
                .' | Aplicado por: '.($aplicacao->user?->nome ?? '-');
        }

        $pendentes = $semana->itens
            ->filter(fn ($item) => $item->gera_aplicacao && $item->status === StatusSemanaItem::Pendente);

        $linhas[] = '';
        $linhas[] = 'MEDICAMENTOS PENDENTES ('.$pendentes->count().'):';
        $linhas[] = $pendentes->isEmpty() ? '  (nenhum)' : '';

        foreach ($pendentes as $item) {
            $linhas[] = '  - '.$item->nome.' | Qtd: '.$item->quantidade_formatada.($item->eh_miligrama ? ' mg' : '');
        }

        return implode("\n", array_filter($linhas, fn ($linha) => $linha !== ''));
    }

    /**
     * Registra o envio na linha do tempo da prescrição.
     */
    private function registrarLog(FeegowFila $registro, ?int $agendamentoId): void
    {
        if (! $registro->prescricao_id) {
            return;
        }

        try {
            PrescricaoLog::registrar(
                $registro->prescricao_id,
                TipoLogPrescricao::FeegowEnviado,
                $agendamentoId
                    ? 'Aplicação registrada na Feegow (agendamento #'.$agendamentoId.').'
                    : 'Aplicação enviada para a Feegow.',
                ['detalhes' => [
                    'Agendamento na Feegow' => $agendamentoId ? '#'.$agendamentoId : 'sem número',
                    'Procedimento' => $registro->payload['procedimento_id'] ?? '-',
                    'Local (agenda)' => $registro->payload['local_id'] ?? '-',
                    'Enviado em' => now()->format('d/m/Y H:i'),
                ]],
                $registro->prescricao_semana_id
            );
        } catch (Throwable $e) {
            Log::warning('Feegow: não foi possível gravar o log do envio #'.$registro->id.': '.$e->getMessage());
        }
    }

    private function localId(): int
    {
        return (int) config('services.feegow.local_aplicacao_id', 1);
    }

    private function profissionalId(): int
    {
        return (int) config('services.feegow.profissional_aplicacao_id', 0);
    }

    private function especialidadeId(): int
    {
        return (int) config('services.feegow.especialidade_aplicacao_id', 0);
    }

    private function procedimentoId(): int
    {
        return (int) config('services.feegow.procedimento_aplicacao_id', 13);
    }

    /**
     * Data que vai no agendamento (d-m-Y).
     *
     * A Feegow recusa agendamento com data anterior a hoje (HTTP 422): quando o
     * envio atrasa — fila processada depois da meia-noite ou reenvio manual de
     * uma aplicação antiga — o agendamento entra com a data do envio e a data
     * real da aplicação continua registrada nas notas.
     */
    private function dataParaEnvio(?string $data): string
    {
        if ($data === null || trim($data) === '') {
            return now()->format('d-m-Y');
        }

        try {
            $aplicacao = Carbon::createFromFormat('d-m-Y', trim($data))->startOfDay();
        } catch (Throwable $e) {
            return now()->format('d-m-Y');
        }

        return $aplicacao->lt(now()->startOfDay()) ? now()->format('d-m-Y') : $aplicacao->format('d-m-Y');
    }
}
