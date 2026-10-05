<?php

namespace App\Http\Controllers;

use App\Enums\StatusSemana;
use App\Enums\TipoAtendimento;
use App\Enums\TipoLogPrescricao;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Services\FeegowService;
use App\Services\PrescricaoSemanaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Área da Secretária: busca por paciente (todas as prescrições dele), quem
 * tem aplicação marcada para hoje e quem está com semana em atraso.
 */
class SecretariaController extends Controller
{
    /**
     * Procedimentos permitidos no cadastro rápido: Bioimpedância e Coleta.
     *
     * @var array<int, int>
     */
    private const PROCEDIMENTOS_RAPIDOS = [10000, 10001];

    public function __construct(
        private FeegowService $feegow,
        private PrescricaoSemanaService $semanas,
    ) {
    }

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

        // Cadastro rápido: 1 semana, apenas Bioimpedância e/ou Coleta.
        $clinicaUsuario = auth()->user()?->clinica_id;
        $clinicas = Clinica::orderBy('nome')->get();
        $tipos = TipoAtendimento::cases();
        $procedimentos = Medicamento::whereIn('id', self::PROCEDIMENTOS_RAPIDOS)->orderBy('id')->get();
        $pacienteRapido = old('paciente_id') ? Paciente::find(old('paciente_id')) : null;

        return view('secretaria.index', compact(
            'agendadas',
            'atrasadas',
            'abaixoDoNivel',
            'dia',
            'paciente',
            'prescricoes',
            'clinicaUsuario',
            'clinicas',
            'tipos',
            'procedimentos',
            'pacienteRapido'
        ));
    }

    /**
     * Médicos da Feegow para o cadastro rápido (carregados quando o modal abre,
     * para não pesar o carregamento da página).
     */
    public function medicos()
    {
        try {
            return response()->json(['medicos' => $this->feegow->listarMedicos()]);
        } catch (\Throwable $e) {
            return response()->json(['medicos' => [], 'erro' => $e->getMessage()]);
        }
    }

    /**
     * Cadastro rápido: prescrição com uma única semana e apenas os
     * procedimentos Bioimpedância e/ou Coleta.
     */
    public function storePrescricaoRapida(Request $request)
    {
        $dados = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'medico_id' => ['nullable', 'integer'],
            'medico_nome' => ['nullable', 'string', 'max:150'],
            'clinica_id' => ['required', 'integer', 'exists:clinicas,id'],
            'tipo_atendimento' => ['required', Rule::enum(TipoAtendimento::class)],
            'agendamento' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'data_prevista' => ['required', 'date'],
            'procedimentos' => ['required', 'array', 'min:1'],
            'procedimentos.*' => ['integer', Rule::in(self::PROCEDIMENTOS_RAPIDOS)],
        ], [
            'paciente_id.required' => 'Escolha o paciente.',
            'clinica_id.required' => 'Escolha a clínica.',
            'tipo_atendimento.required' => 'Escolha o tipo de atendimento.',
            'data_prevista.required' => 'Informe a data da semana.',
            'procedimentos.required' => 'Escolha Bioimpedância, Coleta ou as duas.',
            'procedimentos.*.in' => 'Só é possível escolher Bioimpedância e/ou Coleta.',
        ]);

        $idsProcedimentos = array_values(array_intersect(
            self::PROCEDIMENTOS_RAPIDOS,
            array_map('intval', $dados['procedimentos'])
        ));

        $prescricao = DB::transaction(function () use ($dados, $idsProcedimentos) {
            $prescricao = Prescricao::create([
                'paciente_id' => $dados['paciente_id'],
                'medico_id' => $dados['medico_id'] ?? null,
                'medico_nome' => $dados['medico_nome'] ?? null,
                'clinica_id' => $dados['clinica_id'],
                'tipo_atendimento' => $dados['tipo_atendimento'],
                'agendamento' => $dados['agendamento'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $preparados = $this->semanas->prepararItens(
                collect($idsProcedimentos)->map(fn (int $id) => [
                    'tipo' => 'medicamento',
                    'medicamento_id' => $id,
                    'quantidade' => 1,
                ])->all()
            );

            $itens = $preparados['itens'];

            if ($itens === []) {
                throw ValidationException::withMessages([
                    'procedimentos' => 'Não foi possível registrar os procedimentos escolhidos.',
                ]);
            }

            $semana = $prescricao->semanas()->create([
                'numero' => 1,
                'data_prevista' => $dados['data_prevista'],
                'sem_aplicacao' => false,
                'status' => $this->semanas->statusDaSemana($itens),
            ]);

            foreach ($itens as $item) {
                $semana->itens()->create($item);
            }

            $this->semanas->sincronizarFinanceiro($prescricao);

            $prescricao->load(['paciente', 'clinica', 'semanas.itens.medicamento', 'financeiro']);

            PrescricaoLog::registrar(
                $prescricao,
                TipoLogPrescricao::Criacao,
                'Prescrição cadastrada pela Secretaria (cadastro rápido: 1 semana).',
                [
                    'detalhes' => array_filter([
                        'Paciente' => $prescricao->paciente?->nome,
                        'Clínica' => $prescricao->clinica?->nome,
                        'Médico' => $prescricao->medico_nome,
                        'Tipo de atendimento' => $prescricao->tipo_atendimento?->label(),
                        'Agendamento' => $prescricao->agendamento,
                        'Procedimentos' => $prescricao->semanas->flatMap->itens->pluck('medicamento.nome')->filter()->implode(' · '),
                        'Total' => $prescricao->financeiro?->valor_total_formatado,
                    ]),
                ]
            );

            foreach ($prescricao->semanas as $semanaCriada) {
                PrescricaoLog::registrar(
                    $prescricao,
                    TipoLogPrescricao::SemanaCriada,
                    'Semana '.$semanaCriada->numero.' criada.',
                    ['detalhes' => $semanaCriada->resumoParaLog()],
                    $semanaCriada->id
                );
            }

            return $prescricao;
        });

        return redirect()
            ->route('secretaria.index', ['paciente_id' => $prescricao->paciente_id])
            ->with('success', 'Prescrição #'.$prescricao->id.' cadastrada com 1 semana.');
    }
}
