<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use Illuminate\Http\Request;

/**
 * Área da Secretária: busca por paciente (todas as prescrições dele), quem
 * tem aplicação marcada para hoje e quem está com semana em atraso.
 */
class SecretariaController extends Controller
{
    /**
     * Busca por paciente (1º card), semanas agendadas para hoje e semanas em atraso.
     */
    public function index(Request $request)
    {
        $dia = now();

        // Busca por paciente: lista TODAS as prescrições do paciente escolhido
        $paciente = Paciente::find((int) $request->input('paciente_id'));

        $prescricoes = $paciente
            ? Prescricao::with(['clinica', 'financeiro', 'semanas'])
                ->where('paciente_id', $paciente->id)
                ->orderByDesc('id')
                ->get()
            : collect();

        $agendadas = PrescricaoSemana::with([
            'prescricao.paciente',
            'prescricao.clinica',
            'prescricao.semanas',
            'itens.medicamento',
            'itens.combo',
            'parcelas',
        ])
            ->where('status', StatusSemana::Agendada)
            ->whereDate('data_prevista', now()->toDateString())
            ->get()
            ->sortBy(fn (PrescricaoSemana $semana) => ($semana->prescricao->paciente?->nome ?? '').$semana->prescricao_id)
            ->values();

        // Prescrições com semana ainda agendada para um dia que já passou —
        // ordenadas pelas mais atrasadas primeiro.
        $atrasadas = Prescricao::with(['paciente', 'clinica', 'semanas'])
            ->whereHas('semanas', fn ($query) => $query
                ->where('status', StatusSemana::Agendada)
                ->whereDate('data_prevista', '<', now()->toDateString()))
            ->get()
            ->sortByDesc('dias_de_atraso')
            ->values();

        // Medicamentos abaixo do nível médio/mínimo cadastrado (os abaixo do
        // mínimo primeiro, para o que é mais crítico ficar no topo).
        $abaixoDoNivel = Medicamento::with('grupo')
            ->withSum('movimentacoes as saldo', 'quantidade')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Medicamento $medicamento) => $medicamento->nivel_estoque !== null)
            ->sortByDesc(fn (Medicamento $medicamento) => $medicamento->nivel_estoque === 'minimo')
            ->values();

        return view('secretaria.index', compact(
            'agendadas',
            'atrasadas',
            'abaixoDoNivel',
            'dia',
            'paciente',
            'prescricoes'
        ));
    }
}
