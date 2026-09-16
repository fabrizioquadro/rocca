<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\TipoAtendimento;
use App\Models\Clinica;
use App\Models\Combo;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoAnexo;
use App\Services\FeegowService;
use App\Services\PrescricaoSemanaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrescricaoController extends Controller
{
    public function __construct(
        private FeegowService $feegow,
        private PrescricaoSemanaService $semanas,
    ) {
    }

    /**
     * Lista as prescrições.
     */
    public function index()
    {
        $prescricoes = Prescricao::with(['paciente', 'clinica', 'semanas', 'financeiro.parcelas'])
            ->latest('id')
            ->get();

        return view('prescricoes.index', compact('prescricoes'));
    }

    /**
     * Formulário de cadastro de prescrição.
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();
        $medicamentos = Medicamento::orderBy('nome')->get();
        $combos = Combo::with('itens.medicamento')->orderBy('nome')->get();
        $tipos = TipoAtendimento::cases();
        $clinicaUsuario = auth()->user()?->clinica_id;
        $semanasIniciais = old('semanas', []);
        $pacienteSelecionado = old('paciente_id') ? Paciente::find(old('paciente_id')) : null;

        // Os médicos são poucos: carregamos todos de uma vez para o select.
        $medicos = [];
        $erroMedicos = null;

        try {
            $medicos = $this->feegow->listarMedicos();
        } catch (\Throwable $e) {
            $erroMedicos = $e->getMessage();
        }

        return view('prescricoes.create', compact(
            'clinicas',
            'medicamentos',
            'combos',
            'tipos',
            'clinicaUsuario',
            'semanasIniciais',
            'pacienteSelecionado',
            'medicos',
            'erroMedicos'
        ));
    }

    /**
     * Grava a prescrição (semanas, itens e o financeiro/parcelas).
     */
    public function store(Request $request)
    {
        $dados = $request->validate($this->regrasValidacao());

        $prescricao = DB::transaction(function () use ($dados, $request) {
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

            $totalItens = 0;
            $exigeAnexo = false;

            foreach (array_values($dados['semanas']) as $indice => $semana) {
                $resultado = $this->salvarSemana($prescricao, $indice + 1, $semana);

                $totalItens += $resultado['quantidade'];
                $exigeAnexo = $exigeAnexo || $resultado['exige_anexo'];
            }

            if ($totalItens === 0) {
                throw ValidationException::withMessages([
                    'semanas' => 'Informe pelo menos um medicamento ou combo em alguma semana.',
                ]);
            }

            // Ampola/miligrama com aplicação exige a prescrição médica anexada
            if ($exigeAnexo && ! $request->hasFile('anexos')) {
                throw ValidationException::withMessages([
                    'anexos' => 'É obrigatório anexar a prescrição médica: esta prescrição tem medicamento do tipo ampola ou miligrama com aplicação.',
                ]);
            }

            $this->semanas->sincronizarFinanceiro($prescricao, [
                'desconto_tipo' => $dados['desconto_tipo'] ?? null,
                'desconto_valor' => $this->semanas->normalizarNumero($dados['desconto_valor'] ?? null),
                'adicional_valor' => $this->semanas->normalizarNumero($dados['adicional_valor'] ?? null),
            ]);
            $this->salvarAnexos($prescricao, $request->file('anexos', []));

            return $prescricao;
        });

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Prescrição cadastrada com sucesso.');
    }

    /**
     * Detalhes da prescrição.
     */
    public function show(Prescricao $prescricao)
    {
        $prescricao->load([
            'paciente',
            'clinica',
            'user',
            'anexos.user',
            'semanas.itens.medicamento',
            'semanas.itens.combo',
            'semanas.parcelas',
            'financeiro.parcelas.semana',
            'financeiro.pagamentos.user',
        ]);

        return view('prescricoes.show', [
            'prescricao' => $prescricao,
            'formasPagamento' => FormaPagamento::opcoes(),
            'formasComParcelas' => FormaPagamento::comParcelas(),
            'parcelasDisponiveis' => FormaPagamento::parcelasDisponiveis(),
        ]);
    }

    /**
     * Anexa arquivos (exames, receitas, documentos e etc.) a uma prescrição.
     */
    public function storeAnexo(Request $request, Prescricao $prescricao)
    {
        $request->validate(
            [
                'anexos' => ['required', 'array'],
                'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xml,csv', 'max:10240'],
            ],
            [
                'anexos.required' => 'Selecione pelo menos um arquivo.',
                'anexos.*.mimes' => 'Formato não permitido (use PDF, imagem, XML ou CSV).',
                'anexos.*.max' => 'Cada arquivo deve ter no máximo 10MB.',
            ]
        );

        $this->salvarAnexos($prescricao, $request->file('anexos', []));

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    /**
     * Remove um anexo da prescrição.
     */
    public function destroyAnexo(Prescricao $prescricao, PrescricaoAnexo $anexo)
    {
        abort_if($anexo->prescricao_id !== $prescricao->id, 404);

        if ($anexo->arquivo && file_exists(public_path($anexo->arquivo))) {
            @unlink(public_path($anexo->arquivo));
        }

        $anexo->delete();

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Anexo removido.');
    }

    /**
     * Exclui a prescrição (e o financeiro).
     */
    public function destroy(Prescricao $prescricao)
    {
        DB::transaction(function () use ($prescricao) {
            $prescricao->financeiro?->delete();
            $prescricao->delete();
        });

        return redirect()
            ->route('prescricoes.index')
            ->with('success', 'Prescrição excluída com sucesso.');
    }

    /**
     * Busca pacientes na base local (o volume é grande, então o select
     * começa vazio e vai filtrando conforme o usuário digita).
     */
    public function buscarPacientes(Request $request)
    {
        $termo = trim((string) $request->input('busca'));
        $digitos = preg_replace('/\D+/', '', $termo);

        $pacientes = Paciente::query()
            ->when($termo !== '', function ($query) use ($termo, $digitos) {
                $query->where(function ($sub) use ($termo, $digitos) {
                    $sub->where('nome', 'like', '%'.$termo.'%');

                    if (strlen($digitos) >= 3) {
                        $sub->orWhere('cpf', 'like', '%'.$digitos.'%');
                    }
                });
            })
            ->orderBy('nome')
            ->limit(30)
            ->get();

        $resultados = $pacientes
            ->map(fn (Paciente $paciente) => [
                'id' => $paciente->id,
                'text' => $paciente->cpf_formatado
                    ? $paciente->nome.' - '.$paciente->cpf_formatado
                    : $paciente->nome,
                'nome' => $paciente->nome,
            ])
            ->values();

        return response()->json(['results' => $resultados]);
    }

    /**
     * Regras de validação do formulário.
     *
     * @return array<string, mixed>
     */
    private function regrasValidacao(): array
    {
        return [
            'paciente_id' => ['required', 'integer', 'exists:pacientes,id'],
            'medico_id' => ['nullable', 'integer'],
            'medico_nome' => ['nullable', 'string', 'max:150'],
            'clinica_id' => ['required', 'integer', 'exists:clinicas,id'],
            'tipo_atendimento' => ['required', Rule::enum(TipoAtendimento::class)],
            'agendamento' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],

            'semanas' => ['required', 'array', 'min:1'],
            'semanas.*.data_prevista' => ['nullable', 'date'],
            'semanas.*.sem_aplicacao' => ['nullable', 'boolean'],

            'semanas.*.itens' => ['nullable', 'array'],
            'semanas.*.itens.*.tipo' => ['nullable', Rule::in(['medicamento', 'combo'])],
            'semanas.*.itens.*.medicamento_id' => ['nullable', 'integer', 'exists:medicamentos,id'],
            'semanas.*.itens.*.combo_id' => ['nullable', 'integer', 'exists:combos,id'],
            'semanas.*.itens.*.quantidade' => ['nullable'],
            'semanas.*.itens.*.valor' => ['nullable'],

            // Desconto (percentual ou valor fixo) e adicional em R$.
            // O valor é normalizado depois (aceita "10,5").
            'desconto_tipo' => ['nullable', Rule::in(['porcentagem', 'valor'])],
            'desconto_valor' => ['nullable'],
            'adicional_valor' => ['nullable'],

            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xml,csv', 'max:10240'],
        ];
    }

    /**
     * Grava uma semana e seus itens. Devolve a quantidade de itens gravados
     * e se algum deles exige anexo (prescrição médica).
     *
     * @param  array<string, mixed>  $dados
     * @return array{quantidade: int, exige_anexo: bool}
     */
    private function salvarSemana(Prescricao $prescricao, int $numero, array $dados): array
    {
        $semAplicacao = (bool) ($dados['sem_aplicacao'] ?? false);

        // Semana sem aplicação não leva medicamentos
        $preparados = $semAplicacao
            ? ['itens' => [], 'exige_anexo' => false]
            : $this->semanas->prepararItens($dados['itens'] ?? []);

        $itens = $preparados['itens'];

        $semana = $prescricao->semanas()->create([
            'numero' => $numero,
            'data_prevista' => $dados['data_prevista'] ?? null,
            'sem_aplicacao' => $semAplicacao,
            'status' => $this->semanas->statusDaSemana($itens),
        ]);

        foreach ($itens as $item) {
            $semana->itens()->create($item);
        }

        return [
            'quantidade' => count($itens),
            'exige_anexo' => $preparados['exige_anexo'],
        ];
    }

    /**
     * Salva os anexos enviados (arquivos ficam em public/uploads/prescricoes).
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $arquivos
     */
    private function salvarAnexos(Prescricao $prescricao, array $arquivos): void
    {
        if (empty($arquivos)) {
            return;
        }

        $destino = public_path('uploads/prescricoes');

        File::ensureDirectoryExists($destino);

        foreach ($arquivos as $arquivo) {
            if (! $arquivo || ! $arquivo->isValid()) {
                continue;
            }

            // Os dados do arquivo precisam ser lidos ANTES do move():
            // depois de mover, o arquivo temporário não existe mais.
            $nomeOriginal = $arquivo->getClientOriginalName();
            $mime = $arquivo->getClientMimeType();
            $tamanho = $arquivo->getSize();
            $extensao = $arquivo->extension();

            $nomeArquivo = 'prescricao_'.$prescricao->id.'_'.time().'_'.uniqid().'.'.$extensao;

            $arquivo->move($destino, $nomeArquivo);

            $prescricao->anexos()->create([
                'nome' => $nomeOriginal,
                'arquivo' => 'uploads/prescricoes/'.$nomeArquivo,
                'mime' => $mime,
                'tamanho' => $tamanho,
                'user_id' => auth()->id(),
            ]);
        }
    }
}
