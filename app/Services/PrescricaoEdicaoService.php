<?php

namespace App\Services;

use App\Enums\StatusParcela;
use App\Enums\StatusSemana;
use App\Enums\TipoLogPrescricao;
use App\Models\Combo;
use App\Models\Financeiro;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaItem;
use Illuminate\Support\Facades\DB;

/**
 * Edição completa dos medicamentos de uma prescrição já cadastrada, a partir da
 * tela "Editar medicamentos e semanas": o usuário monta as semanas (igual ao
 * cadastro), o sistema mostra o que vai mudar e só então aplica.
 *
 * O plano é montado em memória antes de qualquer gravação:
 *  - semana COM aplicação registrada fica travada (não altera nem exclui): é o
 *    que protege o histórico de aplicações e as baixas de estoque;
 *  - semana sem aplicação pode ter os itens trocados, e a exclusão segue a
 *    mesma regra do botão de excluir a semana (pode_ser_excluida);
 *  - o financeiro é recalculado depois (sincronizarFinanceiro), mantendo o
 *    desconto congelado e redistribuindo o que já foi recebido.
 */
class PrescricaoEdicaoService
{
    /**
     * Ação do plano para cada semana.
     */
    public const MANTER = 'manter';

    public const ALTERAR = 'alterar';

    public const CRIAR = 'criar';

    public const EXCLUIR = 'excluir';

    public function __construct(
        private PrescricaoSemanaService $semanas
    ) {
    }

    /** @var array<int, Medicamento|null> */
    private array $medicamentos = [];

    /** @var array<int, Combo|null> */
    private array $combos = [];

    /**
     * Transforma o que veio do formulário num plano (o que criar, alterar e
     * excluir), com as diferenças de cada semana e os problemas que impedem a
     * gravação.
     *
     * @param  array<int|string, array<string, mixed>>  $semanasDoFormulario
     * @return array{plano: array<int, array<string, mixed>>, problemas: array<int, string>, avisos: array<int, string>, resumo: array<string, int>}
     */
    public function montarPlano(Prescricao $prescricao, array $semanasDoFormulario): array
    {
        $prescricao->loadMissing([
            'semanas.itens.medicamento',
            'semanas.itens.combo',
            'anexos',
        ]);

        $existentes = $prescricao->semanas->keyBy('id');
        $plano = [];
        $problemas = [];
        $avisos = [];
        $usadas = [];
        $exigeAnexo = false;

        foreach (array_values($semanasDoFormulario) as $posicao => $linha) {
            $id = (int) ($linha['id'] ?? 0);
            $semana = $id > 0 ? $existentes->get($id) : null;
            $rotulo = $semana ? 'Semana '.$semana->numero : 'Semana '.($posicao + 1);

            if ($id > 0 && ! $semana) {
                $problemas[] = 'Uma das semanas enviadas não faz parte desta prescrição.';

                continue;
            }

            if ($semana && isset($usadas[$semana->id])) {
                $problemas[] = $rotulo.' foi enviada mais de uma vez.';

                continue;
            }

            if ($semana) {
                $usadas[$semana->id] = true;
            }

            if ($semana && (bool) ($linha['excluir'] ?? false)) {
                if (! $semana->pode_ser_excluida) {
                    $problemas[] = $rotulo.': '.$semana->motivo_bloqueio_exclusao;

                    continue;
                }

                $plano[] = [
                    'semana' => $semana,
                    'acao' => self::EXCLUIR,
                    'alteracoes' => [],
                    'itens' => [],
                    'itens_descricao' => implode(' · ', $semana->itensParaLog()) ?: 'Nenhum item',
                    'valor_total' => 0.0,
                    'valor_total_anterior' => $semana->valor_total,
                    'bloqueada' => false,
                    'motivo_bloqueio' => null,
                ];

                continue;
            }

            $semAplicacao = (bool) ($linha['sem_aplicacao'] ?? false);
            $preparados = $semAplicacao
                ? ['itens' => [], 'exige_anexo' => false]
                : $this->semanas->prepararItens($linha['itens'] ?? []);

            $itens = $preparados['itens'];
            $exigeAnexo = $exigeAnexo || $preparados['exige_anexo'];

            $dados = [
                'data_prevista' => $this->normalizarData($linha['data_prevista'] ?? null),
                'sem_aplicacao' => $semAplicacao,
                'observacao' => trim((string) ($linha['observacao'] ?? '')) ?: null,
            ];

            // Semana com aplicação registrada: não altera nada. Se o formulário
            // mandou os dados dela (tentativa de alteração), confere e recusa.
            if ($semana && ! $semana->pode_ser_alterada) {
                $enviouDados = array_intersect_key($linha, array_flip(['itens', 'data_prevista', 'sem_aplicacao', 'observacao'])) !== [];

                if ($enviouDados && $this->diferencas($semana, $dados, $itens) !== []) {
                    $problemas[] = $rotulo.': '.$semana->motivo_bloqueio;
                }

                $plano[] = [
                    'semana' => $semana,
                    'acao' => self::MANTER,
                    'dados' => [
                        'data_prevista' => $semana->data_prevista?->format('Y-m-d'),
                        'sem_aplicacao' => (bool) $semana->sem_aplicacao,
                        'observacao' => $semana->observacao,
                    ],
                    'itens' => [],
                    'alteracoes' => [],
                    'itens_descricao' => implode(' · ', $semana->itensParaLog()) ?: 'Nenhum item',
                    'valor_total' => $semana->valor_total,
                    'valor_total_anterior' => $semana->valor_total,
                    'bloqueada' => true,
                    'motivo_bloqueio' => $semana->motivo_bloqueio,
                ];

                continue;
            }

            if (! $semAplicacao && $itens === []) {
                $problemas[] = $rotulo.': informe pelo menos um medicamento ou combo (ou marque a semana como "sem aplicação").';

                continue;
            }

            if (! $semana) {
                $plano[] = [
                    'semana' => null,
                    'acao' => self::CRIAR,
                    'dados' => $dados,
                    'itens' => $itens,
                    'alteracoes' => [],
                    'itens_descricao' => $this->descricaoDosItens($itens),
                    'valor_total' => $this->valorDosItens($itens, $semAplicacao),
                    'valor_total_anterior' => 0.0,
                    'bloqueada' => false,
                    'motivo_bloqueio' => null,
                ];

                continue;
            }

            $alteracoes = $this->diferencas($semana, $dados, $itens);

            $plano[] = [
                'semana' => $semana,
                'acao' => $alteracoes === [] ? self::MANTER : self::ALTERAR,
                'dados' => $dados,
                'itens' => $itens,
                'alteracoes' => $alteracoes,
                'itens_descricao' => $this->descricaoDosItens($itens),
                'valor_total' => $this->valorDosItens($itens, $semAplicacao),
                'valor_total_anterior' => $semana->valor_total,
                'bloqueada' => false,
                'motivo_bloqueio' => null,
            ];
        }

        // Semana que não veio no formulário não é mexida (volta para o fim,
        // na numeração que ela já tinha)
        foreach ($existentes as $semana) {
            if (isset($usadas[$semana->id])) {
                continue;
            }

            $plano[] = [
                'semana' => $semana,
                'acao' => self::MANTER,
                'dados' => [
                    'data_prevista' => $semana->data_prevista?->format('Y-m-d'),
                    'sem_aplicacao' => (bool) $semana->sem_aplicacao,
                    'observacao' => $semana->observacao,
                ],
                'itens' => [],
                'alteracoes' => [],
                'valor_total' => $semana->valor_total,
                'valor_total_anterior' => $semana->valor_total,
                'bloqueada' => false,
                'motivo_bloqueio' => null,
            ];
        }

        if ($plano === []) {
            $problemas[] = 'A prescrição precisa ficar com pelo menos uma semana.';
        }

        $cobraveis = array_filter($plano, fn (array $item) => $item['acao'] !== self::EXCLUIR
            && ! $item['dados']['sem_aplicacao']
            && $item['valor_total'] > 0);

        if ($cobraveis === [] && $this->temRecebimento($prescricao)) {
            $problemas[] = 'Esta prescrição já tem pagamento recebido: não é possível deixá-la sem nenhuma cobrança.';
        }

        if ($exigeAnexo && $prescricao->anexos->isEmpty()) {
            $avisos[] = 'Esta alteração inclui medicamento do tipo ampola/miligrama com aplicação, que exige a prescrição médica anexada — e esta prescrição não tem anexo.';
        }

        return [
            'plano' => $plano,
            'problemas' => array_values(array_unique($problemas)),
            'avisos' => $avisos,
            'resumo' => [
                'criadas' => count(array_filter($plano, fn (array $item) => $item['acao'] === self::CRIAR)),
                'alteradas' => count(array_filter($plano, fn (array $item) => $item['acao'] === self::ALTERAR)),
                'excluidas' => count(array_filter($plano, fn (array $item) => $item['acao'] === self::EXCLUIR)),
                'mantidas' => count(array_filter($plano, fn (array $item) => $item['acao'] === self::MANTER)),
            ],
        ];
    }

    /**
     * Aplica o plano: exclui, altera e cria as semanas na ordem do formulário,
     * renumera e reajusta o financeiro (que também redistribui o que já foi
     * recebido). Tudo numa transação: ou aplica inteiro, ou não aplica nada.
     *
     * @param  array<int, array<string, mixed>>  $plano
     */
    public function aplicarPlano(Prescricao $prescricao, array $plano): void
    {
        DB::transaction(function () use ($prescricao, $plano) {
            $numero = 0;

            foreach ($plano as $item) {
                if ($item['acao'] === self::EXCLUIR) {
                    $this->excluirSemana($prescricao, $item['semana']);

                    continue;
                }

                $numero++;

                if ($item['acao'] === self::CRIAR) {
                    $this->criarSemana($prescricao, $numero, $item);

                    continue;
                }

                $this->atualizarSemana($prescricao, $numero, $item);
            }

            $this->semanas->sincronizarFinanceiro($prescricao->refresh());
        });
    }

    /**
     * Projeção do financeiro: simula a alteração dentro de uma transação e
     * desfaz tudo no fim, para o resumo mostrar exatamente o que vai acontecer
     * com parcelas, valores pagos e saldo.
     *
     * @param  array<int, array<string, mixed>>  $plano
     * @return array{antes: array<string, mixed>, depois: array<string, mixed>}
     */
    public function projetarFinanceiro(Prescricao $prescricao, array $plano): array
    {
        $antes = $this->resumoFinanceiro($prescricao);
        $depois = $antes;

        DB::beginTransaction();

        try {
            $this->aplicarPlano($prescricao, $plano);

            // Lido DENTRO da transação: é o estado como ele ficaria de verdade
            $depois = $this->resumoFinanceiro($prescricao->fresh());
        } finally {
            DB::rollBack();
        }

        return ['antes' => $antes, 'depois' => $depois];
    }

    /**
     * Como o financeiro está agora (ou como ficaria, na projeção).
     *
     * @return array<string, mixed>
     */
    public function resumoFinanceiro(Prescricao $prescricao): array
    {
        $financeiro = Financeiro::with(['parcelas', 'pagamentos'])
            ->where('prescricao_id', $prescricao->id)
            ->first();

        if (! $financeiro) {
            return [
                'existe' => false,
                'bruto' => 0.0,
                'desconto' => 0.0,
                'desconto_descricao' => null,
                'adicional' => 0.0,
                'total' => 0.0,
                'recebido' => 0.0,
                'pago' => 0.0,
                'aberto' => 0.0,
                'nao_alocado' => 0.0,
                'parcelas' => [],
                'parcelas_pagas' => 0,
                'sem_cobranca' => true,
            ];
        }

        return [
            'existe' => true,
            'bruto' => (float) $financeiro->valor_bruto,
            'desconto' => $financeiro->valor_desconto,
            'desconto_descricao' => $financeiro->desconto_descricao,
            'adicional' => (float) $financeiro->adicional_valor,
            'total' => (float) $financeiro->valor_total,
            'recebido' => $financeiro->valor_recebido,
            'pago' => $financeiro->valor_pago,
            'aberto' => $financeiro->valor_aberto,
            'nao_alocado' => $financeiro->valor_nao_alocado,
            'sem_cobranca' => false,
            'parcelas' => $financeiro->parcelas
                ->map(fn ($parcela) => [
                    'numero' => (int) $parcela->numero,
                    'vencimento' => $parcela->vencimento_formatado,
                    'valor' => (float) $parcela->valor,
                    'valor_pago' => (float) $parcela->valor_pago,
                    'status' => $parcela->status instanceof StatusParcela ? $parcela->status : null,
                    'semana' => $parcela->semana?->numero,
                ])
                ->all(),
            'parcelas_pagas' => $financeiro->parcelas
                ->filter(fn ($parcela) => $parcela->status === StatusParcela::Paga)
                ->count(),
        ];
    }

    /**
     * Aplica uma semana que veio do formulário e já existe.
     *
     * @param  array<string, mixed>  $item
     */
    private function atualizarSemana(Prescricao $prescricao, int $numero, array $item): void
    {
        /** @var PrescricaoSemana $semana */
        $semana = $item['semana'];
        $alterar = $item['acao'] === self::ALTERAR;

        if ($alterar) {
            $emFluxo = in_array($semana->status, [
                StatusSemana::FilaAplicacao,
                StatusSemana::Atendimento,
                StatusSemana::AplicacaoParcial,
            ], true);

            $status = $item['dados']['sem_aplicacao']
                ? StatusSemana::Aplicada
                : ($emFluxo ? $semana->status : $this->semanas->statusDaSemana($item['itens']));

            $semana->itens()->delete();

            foreach ($item['itens'] as $novoItem) {
                $semana->itens()->create($novoItem);
            }

            $semana->update([
                'data_prevista' => $item['dados']['data_prevista'],
                'sem_aplicacao' => $item['dados']['sem_aplicacao'],
                'observacao' => $item['dados']['observacao'],
                'status' => $status,
            ]);

            PrescricaoLog::registrar(
                $prescricao,
                TipoLogPrescricao::SemanaEditada,
                'Semana '.$semana->numero.' editada na prescrição.',
                ['alteracoes' => $item['alteracoes']],
                $semana->id
            );
        }

        if ($semana->numero !== $numero) {
            $semana->update(['numero' => $numero]);
        }
    }

    /**
     * Cria uma semana nova na posição em que ela ficou no formulário.
     *
     * @param  array<string, mixed>  $item
     */
    private function criarSemana(Prescricao $prescricao, int $numero, array $item): void
    {
        $semana = $prescricao->semanas()->create([
            'numero' => $numero,
            'data_prevista' => $item['dados']['data_prevista'],
            'sem_aplicacao' => $item['dados']['sem_aplicacao'],
            'observacao' => $item['dados']['observacao'],
            'status' => $item['dados']['sem_aplicacao']
                ? StatusSemana::Aplicada
                : $this->semanas->statusDaSemana($item['itens']),
        ]);

        foreach ($item['itens'] as $novoItem) {
            $semana->itens()->create($novoItem);
        }

        PrescricaoLog::registrar(
            $prescricao,
            TipoLogPrescricao::SemanaCriada,
            'Semana '.$numero.' criada na edição da prescrição.',
            ['detalhes' => $semana->refresh()->resumoParaLog()],
            $semana->id
        );
    }

    /**
     * Exclui uma semana (as aplicações e as baixas dela não existem: a semana
     * só chega aqui quando pode_ser_excluida).
     */
    private function excluirSemana(Prescricao $prescricao, PrescricaoSemana $semana): void
    {
        $semana->load('itens');

        PrescricaoLog::registrar(
            $prescricao,
            TipoLogPrescricao::SemanaExcluida,
            'Semana '.$semana->numero.' excluída na edição da prescrição.',
            ['detalhes' => $semana->resumoParaLog()],
            $semana->id
        );

        $semana->delete();
    }

    /**
     * Diferenças entre o que a semana tem hoje e o que o formulário mandou,
     * no formato do histórico (campo, de, para).
     *
     * @param  array<string, mixed>  $dados
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, array<string, string>>
     */
    private function diferencas(PrescricaoSemana $semana, array $dados, array $itens): array
    {
        $semana->loadMissing('itens.medicamento', 'itens.combo');

        $antes = $semana->itens
            ->map(fn (PrescricaoSemanaItem $item) => [
                'nome' => $item->nome,
                'quantidade' => (float) $item->quantidade,
                'valor' => (float) $item->valor,
            ])
            ->all();

        $depois = $this->modelarItens($itens)
            ->map(fn (PrescricaoSemanaItem $item) => [
                'nome' => $item->nome,
                'quantidade' => (float) $item->quantidade,
                'valor' => (float) $item->valor,
            ])
            ->all();

        $alteracoes = [];

        $comparacoes = [
            'Data prevista' => [
                $semana->data_prevista?->format('Y-m-d'),
                $dados['data_prevista'],
                $semana->data_prevista_formatada ?? '—',
                $this->formatarData($dados['data_prevista']),
            ],
            'Sem aplicação' => [
                (bool) $semana->sem_aplicacao,
                (bool) $dados['sem_aplicacao'],
                $semana->sem_aplicacao ? 'Sim' : 'Não',
                $dados['sem_aplicacao'] ? 'Sim' : 'Não',
            ],
            'Observação' => [
                $semana->observacao,
                $dados['observacao'],
                $semana->observacao ?? '—',
                $dados['observacao'] ?? '—',
            ],
            'Itens' => [
                $this->itensComoTexto($antes),
                $this->itensComoTexto($depois),
                $this->itensLegiveis($antes),
                $this->itensLegiveis($depois),
            ],
        ];

        foreach ($comparacoes as $campo => [$valorAntes, $valorDepois, $de, $para]) {
            if ($valorAntes !== $valorDepois) {
                $alteracoes[] = ['campo' => $campo, 'de' => $de, 'para' => $para];
            }
        }

        return $alteracoes;
    }

    /**
     * Assinatura dos itens, usada só para saber se mudou alguma coisa.
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private function itensComoTexto(array $itens): string
    {
        return implode(' | ', array_map(
            fn (array $item) => $item['nome'].' x'.(float) $item['quantidade'].' @'.(float) $item['valor'],
            $itens
        ));
    }

    /**
     * Itens como o usuário lê (medicamento x quantidade).
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private function itensLegiveis(array $itens): string
    {
        if ($itens === []) {
            return 'Nenhum item';
        }

        return implode(' · ', array_map(
            fn (array $item) => $item['nome'].' x'.rtrim(rtrim(number_format((float) $item['quantidade'], 3, ',', '.'), '0'), ','),
            $itens
        ));
    }

    /**
     * Descrição dos itens de uma semana do plano, com o nome do medicamento.
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private function descricaoDosItens(array $itens): string
    {
        if ($itens === []) {
            return 'Nenhum item';
        }

        return $this->itensLegiveis(
            $this->modelarItens($itens)
                ->map(fn (PrescricaoSemanaItem $item) => [
                    'nome' => $item->nome,
                    'quantidade' => (float) $item->quantidade,
                    'valor' => (float) $item->valor,
                ])
                ->all()
        );
    }

    /**
     * Valor total dos itens de uma semana do plano. Monta os itens em memória
     * (sem gravar) para usar exatamente a mesma conta da tela: ampola cobra
     * inteira, miligrama/procedimento/combo cobram a quantidade exata.
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private function valorDosItens(array $itens, bool $semAplicacao): float
    {
        if ($semAplicacao) {
            return 0.0;
        }

        return round($this->modelarItens($itens)->sum(fn (PrescricaoSemanaItem $item) => $item->valor_total), 2);
    }

    /**
     * Itens do plano como modelos (sem gravar), com o medicamento/combo
     * carregado para as contas do valor.
     *
     * @param  array<int, array<string, mixed>>  $itens
     * @return \Illuminate\Support\Collection<int, PrescricaoSemanaItem>
     */
    private function modelarItens(array $itens)
    {
        return collect($itens)->map(function (array $item) {
            $modelo = new PrescricaoSemanaItem($item);

            if (! empty($item['medicamento_id'])) {
                $modelo->setRelation('medicamento', $this->medicamento((int) $item['medicamento_id']));
            }

            if (! empty($item['combo_id'])) {
                $modelo->setRelation('combo', $this->combo((int) $item['combo_id']));
            }

            return $modelo;
        });
    }

    /**
     * Medicamento pelo id (com cache: uma prescrição repete os mesmos itens).
     */
    private function medicamento(int $id): ?Medicamento
    {
        return $this->medicamentos[$id] ??= Medicamento::find($id);
    }

    /**
     * Combo pelo id (com cache).
     */
    private function combo(int $id): ?Combo
    {
        return $this->combos[$id] ??= Combo::find($id);
    }

    /**
     * Já existe algum pagamento lançado no financeiro da prescrição?
     */
    private function temRecebimento(Prescricao $prescricao): bool
    {
        $financeiro = Financeiro::withTrashed()
            ->where('prescricao_id', $prescricao->id)
            ->first();

        return $financeiro && $financeiro->valor_recebido > 0;
    }

    /**
     * Data no formato do banco (aceita d/m/Y e Y-m-d).
     */
    private function normalizarData($data): ?string
    {
        $data = trim((string) $data);

        if ($data === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $formato) {
            $convertida = \DateTime::createFromFormat($formato, $data);

            if ($convertida && $convertida->format($formato) === $data) {
                return $convertida->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Data no formato da tela (d/m/Y).
     */
    private function formatarData(?string $data): string
    {
        if (! $data) {
            return '—';
        }

        $convertida = \DateTime::createFromFormat('Y-m-d', $data);

        return $convertida ? $convertida->format('d/m/Y') : '—';
    }
}
