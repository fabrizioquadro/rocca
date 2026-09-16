<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Models\Combo;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
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

        $semana->load(['itens.medicamento', 'itens.combo', 'parcelas']);

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
