<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAtendimento;

class DashboardController extends Controller
{
    /**
     * Dashboard com as áreas operacionais. Hoje só a Enfermagem, dividida em
     * duas listas: quem está esperando na fila de aplicação e quem já está
     * em atendimento.
     */
    public function index()
    {
        // Fila de trabalho: o paciente chegou e o atendimento ainda não começou
        // (inclui a volta do paciente depois de uma aplicação parcial)
        $fila = PrescricaoSemana::with([
            'prescricao.paciente',
            'prescricao.clinica',
            'prescricao.semanas',
            'itens.medicamento',
            'itens.combo',
        ])
            ->whereIn('status', [StatusSemana::FilaAplicacao, StatusSemana::AplicacaoParcial])
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

        return view('home', compact('fila', 'atendimentos'));
    }
}
