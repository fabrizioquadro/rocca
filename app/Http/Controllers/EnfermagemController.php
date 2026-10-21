<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\PrescricaoSemanaAtendimento;

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

        // Quantitativo do DIA por usuário: quantas aplicações cada um registrou.
        // Mg (vasilhame aberto) e unidades (ampola/combo) ficam separados, porque
        // são grandezas diferentes.
        $aplicacoesPorUsuario = PrescricaoSemanaAplicacao::with('user')
            ->whereDate('aplicado_em', now()->toDateString())
            ->get()
            ->groupBy('user_id')
            ->map(fn ($aplicacoes) => [
                'usuario' => $aplicacoes->first()->user?->nome ?? 'Usuário removido',
                'aplicacoes' => $aplicacoes->count(),
                'unidades' => round((float) $aplicacoes->whereNull('vasilhame_aberto_id')->sum('quantidade'), 3),
                'mg' => round((float) $aplicacoes->whereNotNull('vasilhame_aberto_id')->sum('quantidade'), 2),
            ])
            ->sortByDesc('aplicacoes')
            ->values();

        return view('enfermagem.index', compact('fila', 'atendimentos', 'aplicacoesPorUsuario'));
    }
}
