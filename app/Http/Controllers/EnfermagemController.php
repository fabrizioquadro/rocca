<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\PrescricaoSemanaAtendimento;
use Illuminate\Support\Collection;

/**
 * Área da Enfermagem.
 *
 * Duas listas: quem chegou e está esperando na fila de aplicação e quem já
 * está em atendimento (era o conteúdo do dashboard).
 */
class EnfermagemController extends Controller
{
    /**
     * Fila de aplicação + atendimentos em aberto.
     */
    public function index()
    {
        // Fila de trabalho: o paciente CHEGOU e o atendimento ainda não começou.
        // Só "Fila de Aplicação" entra aqui: a semana com aplicação parcial é o
        // paciente que foi atendido e voltou para casa — ela só reaparece na
        // fila quando voltar e for enviada de novo ("paciente retornou").
        $fila = PrescricaoSemana::with([
            'prescricao.paciente',
            'prescricao.clinica',
            'prescricao.semanas',
            'itens.medicamento',
            'itens.combo',
        ])
            // Prescrição excluída (soft delete) deixa as semanas para trás;
            // elas não podem aparecer na fila nem gerar link para a prescrição.
            ->whereHas('prescricao')
            ->where('status', StatusSemana::FilaAplicacao)
            ->whereDoesntHave('atendimentos', fn ($query) => $query->whereNull('finalizado_em'))
            ->orderByRaw('chegada_em IS NULL')
            ->orderBy('chegada_em')
            ->orderByRaw('data_prevista IS NULL')
            ->orderBy('data_prevista')
            ->orderBy('prescricao_id')
            ->orderBy('numero')
            ->get();

        // Atendimentos abertos: o paciente está sendo atendido agora.
        // Só quem iniciou o atendimento pode registrar a aplicação e finalizar.
        $atendimentos = PrescricaoSemanaAtendimento::with([
            'iniciadoPor',
            'semana.prescricao.paciente',
            'semana.prescricao.clinica',
            'semana.itens.medicamento',
            'semana.itens.combo',
        ])
            ->whereNull('finalizado_em')
            ->orderBy('iniciado_em')
            ->get();

        // Quantitativo do DIA por usuário: quantos PACIENTES cada um atendeu.
        // Cada aplicação leva ao atendimento, que leva à semana e à prescrição
        // (onde está o paciente) — o mesmo paciente pode ter várias aplicações.
        $aplicacoesDoDia = PrescricaoSemanaAplicacao::with([
            'user',
            'atendimento.semana.prescricao:id,paciente_id',
        ])
            ->whereDate('aplicado_em', now()->toDateString())
            ->get();

        $pacientesPorUsuario = $aplicacoesDoDia
            ->groupBy('user_id')
            ->map(fn ($aplicacoes) => [
                'usuario' => $aplicacoes->first()->user?->nome ?? 'Usuário removido',
                'pacientes' => $this->pacientesDistintos($aplicacoes),
            ])
            ->sortByDesc('pacientes')
            ->values();

        // Total do dia é o distinto geral: o mesmo paciente pode ter sido
        // atendido por mais de um usuário.
        $totalPacientes = $this->pacientesDistintos($aplicacoesDoDia);

        return view('enfermagem.index', compact('fila', 'atendimentos', 'pacientesPorUsuario', 'totalPacientes'));
    }

    /**
     * Pacientes distintos de um conjunto de aplicações.
     */
    private function pacientesDistintos(Collection $aplicacoes): int
    {
        return $aplicacoes
            ->map(fn (PrescricaoSemanaAplicacao $aplicacao) => $aplicacao->atendimento?->semana?->prescricao?->paciente_id)
            ->filter()
            ->unique()
            ->count();
    }
}
