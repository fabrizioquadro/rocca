<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Enums\TipoLogPrescricao;
use App\Models\Combo;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAtendimento;
use App\Services\PrescricaoSemanaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ações de uma semana dentro do acesso à prescrição:
 * acessar, editar e excluir.
 */
class PrescricaoSemanaController extends Controller
{
    public function __construct(private PrescricaoSemanaService $semanas)
    {
    }

    /**
     * Detalhes da semana (itens, status de aplicação e pagamento).
     */
    public function show(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        $semana->load(['itens.medicamento', 'itens.combo', 'parcelas', 'atendimentos.iniciadoPor']);

        return view('prescricoes.semanas.show', compact('prescricao', 'semana'));
    }

    /**
     * Formulário de edição da semana.
     */
    public function edit(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        if ($bloqueio = $this->bloqueioDeAlteracao($prescricao, $semana)) {
            return $bloqueio;
        }

        $semana->load(['itens.medicamento', 'itens.combo', 'parcelas']);

        $medicamentos = Medicamento::orderBy('nome')->get();
        $combos = Combo::with('itens.medicamento')->orderBy('nome')->get();
        $itens = old('itens', $this->itensParaFormulario($semana));

        return view('prescricoes.semanas.edit', compact('prescricao', 'semana', 'medicamentos', 'combos', 'itens'));
    }

    /**
     * Grava a semana (itens e dados) e reajusta o financeiro.
     */
    public function update(Request $request, Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        if ($bloqueio = $this->bloqueioDeAlteracao($prescricao, $semana)) {
            return $bloqueio;
        }

        $dados = $request->validate([
            'data_prevista' => ['nullable', 'date'],
            'sem_aplicacao' => ['nullable', 'boolean'],
            'observacao' => ['nullable', 'string', 'max:1000'],

            'itens' => ['nullable', 'array'],
            'itens.*.tipo' => ['nullable', Rule::in(['medicamento', 'combo'])],
            'itens.*.medicamento_id' => ['nullable', 'integer', 'exists:medicamentos,id'],
            'itens.*.combo_id' => ['nullable', 'integer', 'exists:combos,id'],
            'itens.*.quantidade' => ['nullable'],
        ]);

        DB::transaction(function () use ($prescricao, $semana, $dados) {
            $antes = $this->resumoDaSemana($semana);

            $semAplicacao = (bool) ($dados['sem_aplicacao'] ?? false);

            // Semana sem aplicação não leva medicamentos
            $itens = $semAplicacao
                ? []
                : $this->semanas->prepararItens($dados['itens'] ?? [])['itens'];

            if (! $semAplicacao && count($itens) === 0) {
                throw ValidationException::withMessages([
                    'itens' => 'Informe pelo menos um medicamento ou combo (ou marque a semana como "sem aplicação").',
                ]);
            }

            $emFluxo = in_array($semana->status, [
                StatusSemana::FilaAplicacao,
                StatusSemana::Atendimento,
                StatusSemana::AplicacaoParcial,
            ], true);

            if ($semAplicacao) {
                $status = StatusSemana::Aplicada;
            } elseif ($emFluxo) {
                // Semana que já entrou no fluxo de aplicação não volta de status
                $status = $semana->status;
            } else {
                $status = $this->semanas->statusDaSemana($itens);
            }

            $semana->itens()->delete();

            foreach ($itens as $item) {
                $semana->itens()->create($item);
            }

            $semana->update([
                'data_prevista' => $dados['data_prevista'] ?? null,
                'sem_aplicacao' => $semAplicacao,
                'observacao' => $dados['observacao'] ?? null,
                'status' => $status,
            ]);

            $this->semanas->sincronizarFinanceiro($prescricao->refresh());

            // Histórico: registra só quando algo mudou de verdade
            $semana->refresh()->load('itens');

            $alteracoes = $this->compararResumos($antes, $this->resumoDaSemana($semana));

            if ($alteracoes) {
                PrescricaoLog::registrar(
                    $prescricao,
                    TipoLogPrescricao::SemanaEditada,
                    'Semana '.$semana->numero.' editada.',
                    ['alteracoes' => $alteracoes],
                    $semana->id
                );
            }
        });

        return redirect()
            ->route('prescricoes.semanas.show', [$prescricao, $semana])
            ->with('success', 'Semana atualizada com sucesso.');
    }

    /**
     * Exclui a semana, renumera as restantes e reajusta o financeiro.
     */
    public function destroy(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        if ($bloqueio = $this->bloqueioDeAlteracao($prescricao, $semana)) {
            return $bloqueio;
        }

        DB::transaction(function () use ($prescricao, $semana) {
            // Histórico: guarda o que a semana tinha antes de sumir
            $semana->load('itens');

            PrescricaoLog::registrar(
                $prescricao,
                TipoLogPrescricao::SemanaExcluida,
                'Semana '.$semana->numero.' excluída.',
                ['detalhes' => $semana->resumoParaLog()],
                $semana->id
            );

            // As parcelas caem junto (cascade) e as semanas restantes voltam a
            // ficar numeradas de 1 a N.
            $semana->delete();

            $prescricao->refresh();

            $this->semanas->renumerarSemanas($prescricao);
            $this->semanas->sincronizarFinanceiro($prescricao->refresh());
        });

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Semana excluída com sucesso.');
    }

    /**
     * Resumo da semana para comparar antes/depois no histórico.
     *
     * @return array<string, string>
     */
    private function resumoDaSemana(PrescricaoSemana $semana): array
    {
        $semana->loadMissing('itens');

        return [
            'Data prevista' => $semana->data_prevista_formatada ?? '—',
            'Sem aplicação' => $semana->sem_aplicacao ? 'Sim' : 'Não',
            'Status' => $semana->status->label(),
            'Observação' => $semana->observacao ?? '—',
            'Itens' => implode(' · ', $semana->itensParaLog()) ?: 'Nenhum item',
        ];
    }

    /**
     * Compara dois resumos e devolve as mudanças no formato do histórico.
     *
     * @param  array<string, string>  $antes
     * @param  array<string, string>  $depois
     * @return array<int, array<string, string>>
     */
    private function compararResumos(array $antes, array $depois): array
    {
        $alteracoes = [];

        foreach ($antes as $campo => $de) {
            $para = $depois[$campo] ?? '—';

            if ($de !== $para) {
                $alteracoes[] = ['campo' => $campo, 'de' => $de, 'para' => $para];
            }
        }

        return $alteracoes;
    }

    /**
     * A semana precisa pertencer à prescrição informada na URL.
     */
    private function garantirSemanaDaPrescricao(Prescricao $prescricao, PrescricaoSemana $semana): void
    {
        abort_if($semana->prescricao_id !== $prescricao->id, 404);
    }

    /**
     * Envia a semana para a fila de atendimento. Exige a parcela paga; sem
     * pagamento, precisa da autorização (email + senha) de um administrador,
     * que fica registrada na semana.
     */
    public function enviarParaFila(Request $request, Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        $dados = $request->validate([
            'liberacao_email' => ['nullable', 'email'],
            'liberacao_senha' => ['nullable', 'string'],
        ], [
            'liberacao_email.email' => 'Informe um email válido do administrador.',
        ]);

        DB::transaction(function () use ($semana, $dados) {
            $this->semanas->enviarParaFilaDeAtendimento(
                $semana,
                $dados['liberacao_email'] ?? null,
                $dados['liberacao_senha'] ?? null
            );
        });

        return redirect()
            ->route('prescricoes.semanas.show', [$prescricao, $semana])
            ->with('success', 'Semana enviada para a fila de atendimento.');
    }

    /**
     * Formulário de registro da aplicação do atendimento em aberto: cada
     * medicamento é marcado como aplicado (com código de barras, lote e
     * vencimento) ou pendente (não aplicado).
     */
    public function formAplicacao(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        $atendimento = $semana->atendimentoAberto;

        if (! $atendimento) {
            return redirect()
                ->route('prescricoes.semanas.show', [$prescricao, $semana])
                ->with('error', 'Inicie o atendimento na área de Enfermagem antes de registrar a aplicação.');
        }

        // Só quem iniciou o atendimento conduz a aplicação
        if ($bloqueio = $this->bloqueioDeOutroUsuario($prescricao, $semana, $atendimento)) {
            return $bloqueio;
        }

        $semana->load(['itens.medicamento', 'itens.combo.itens.medicamento']);
        $atendimento->load(['aplicacoes.item', 'aplicacoes.entradaItem', 'aplicacoes.medicamento', 'aplicacoes.user']);

        return view('prescricoes.semanas.aplicar', compact('prescricao', 'semana', 'atendimento'));
    }

    /**
     * Grava a aplicação do atendimento em aberto e fecha o atendimento.
     */
    public function aplicar(Request $request, Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        $atendimento = $semana->atendimentoAberto;

        if (! $atendimento) {
            return redirect()
                ->route('prescricoes.semanas.show', [$prescricao, $semana])
                ->with('error', 'Inicie o atendimento na área de Enfermagem antes de registrar a aplicação.');
        }

        // Só quem iniciou o atendimento conduz a aplicação
        if ($bloqueio = $this->bloqueioDeOutroUsuario($prescricao, $semana, $atendimento)) {
            return $bloqueio;
        }

        $dados = $request->validate([
            'observacao' => ['nullable', 'string', 'max:2000'],

            'itens' => ['required', 'array'],
            'itens.*.situacao' => ['nullable', Rule::in(['aplicado', 'pendente'])],
            'itens.*.codigo_barras' => ['nullable', 'string', 'max:100'],
            'itens.*.quantidade' => ['nullable'],
            'itens.*.aplicado_em' => ['nullable', 'date'],
            'itens.*.observacao' => ['nullable', 'string', 'max:1000'],
        ], [
            'itens.required' => 'Informe a situação de cada medicamento.',
            'itens.*.aplicado_em.date' => 'Informe uma data/hora de aplicação válida.',
        ]);

        DB::transaction(function () use ($atendimento, $dados) {
            $this->semanas->finalizarAtendimento($atendimento, $dados);
        });

        return redirect()
            ->route('prescricoes.semanas.show', [$prescricao, $semana])
            ->with('success', 'Aplicação registrada e atendimento finalizado.');
    }

    /**
     * Inicia o atendimento da enfermagem: grava o momento de início e passa a
     * semana para "Em Atendimento". Quem inicia fica como responsável pelo
     * atendimento (só ele poderá registrar a aplicação e finalizar).
     *
     * Já cai direto na tela de registro da aplicação: é o próximo passo de
     * quem iniciou o atendimento.
     */
    public function iniciarAtendimento(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        DB::transaction(function () use ($semana) {
            $this->semanas->iniciarAtendimento($semana);
        });

        return redirect()
            ->route('prescricoes.semanas.aplicar.form', [$prescricao, $semana])
            ->with('success', 'Atendimento iniciado. Registre a aplicação dos medicamentos.');
    }

    /**
     * Bloqueia a ação quando o atendimento em aberto é de outro usuário.
     * Devolve null quando o usuário logado é o responsável.
     */
    private function bloqueioDeOutroUsuario(
        Prescricao $prescricao,
        PrescricaoSemana $semana,
        PrescricaoSemanaAtendimento $atendimento
    ): ?RedirectResponse {
        if ($atendimento->podeSerConduzidoPor(auth()->user())) {
            return null;
        }

        return redirect()
            ->route('prescricoes.semanas.show', [$prescricao, $semana])
            ->with('error', $atendimento->bloqueio_de_outro_usuario);
    }

    /**
     * Devolve a semana para "Agendada" (paciente não compareceu). A liberação
     * sem pagamento é desfeita e uma nova será exigida para voltar à fila.
     */
    public function devolverParaAgendada(Prescricao $prescricao, PrescricaoSemana $semana)
    {
        $this->garantirSemanaDaPrescricao($prescricao, $semana);

        $atendimento = $semana->atendimentoAberto;

        // Ninguém cancela o atendimento que outro usuário está conduzindo
        if ($atendimento && $bloqueio = $this->bloqueioDeOutroUsuario($prescricao, $semana, $atendimento)) {
            return $bloqueio;
        }

        DB::transaction(function () use ($semana) {
            $this->semanas->devolverParaAgendada($semana);
        });

        return back()->with('success', 'Semana devolvida para o agendamento.');
    }

    /**
     * A semana só pode ser alterada enquanto não há aplicação. Devolve a
     * resposta de bloqueio (com o motivo) ou null quando está liberada.
     */
    private function bloqueioDeAlteracao(Prescricao $prescricao, PrescricaoSemana $semana): ?RedirectResponse
    {
        if ($semana->pode_ser_alterada) {
            return null;
        }

        return redirect()
            ->route('prescricoes.semanas.show', [$prescricao, $semana])
            ->with('error', $semana->motivo_bloqueio);
    }

    /**
     * Itens da semana no formato que o formulário entende (usado no old()).
     *
     * @return array<int, array<string, mixed>>
     */
    private function itensParaFormulario(PrescricaoSemana $semana): array
    {
        return $semana->itens
            ->map(fn ($item) => [
                'tipo' => $item->tipo,
                'medicamento_id' => $item->medicamento_id,
                'combo_id' => $item->combo_id,
                'quantidade' => $item->quantidade,
            ])
            ->values()
            ->all();
    }
}
