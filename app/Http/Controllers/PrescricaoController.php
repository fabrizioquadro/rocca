<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\TipoAtendimento;
use App\Enums\TipoLogPrescricao;
use App\Models\Clinica;
use App\Models\Combo;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoAnexo;
use App\Models\PrescricaoLog;
use App\Services\FeegowService;
use App\Services\PrescricaoSemanaService;
use Barryvdh\DomPDF\Facade\Pdf;
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

            // Histórico: o cadastro e as semanas que nasceram com ele
            $prescricao->load(['semanas.itens', 'anexos', 'financeiro']);

            PrescricaoLog::registrar($prescricao, TipoLogPrescricao::Criacao, 'Prescrição cadastrada.', [
                'detalhes' => $this->detalhesDaPrescricao($prescricao),
            ]);

            foreach ($prescricao->semanas as $semana) {
                PrescricaoLog::registrar(
                    $prescricao,
                    TipoLogPrescricao::SemanaCriada,
                    'Semana '.$semana->numero.' criada.',
                    ['detalhes' => $semana->resumoParaLog()],
                    $semana->id
                );
            }

            return $prescricao;
        });

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Prescrição cadastrada com sucesso.');
    }

    /**
     * Formulário de edição dos dados da prescrição (cabeçalho). As semanas,
     * os itens e o financeiro têm telas próprias.
     */
    public function edit(Prescricao $prescricao)
    {
        $prescricao->load(['paciente', 'clinica']);

        // Lista de clínicas e médicos igual à do cadastro. Se a Feegow cair, o
        // médico é editado em texto livre (não perde o que está gravado).
        $clinicas = Clinica::orderBy('nome')->get();
        $medicos = [];
        $erroMedicos = null;

        try {
            $medicos = $this->feegow->listarMedicos();
        } catch (\Throwable $e) {
            $erroMedicos = $e->getMessage();
        }

        return view('prescricoes.edit', [
            'prescricao' => $prescricao,
            'clinicas' => $clinicas,
            'medicos' => $medicos,
            'erroMedicos' => $erroMedicos,
            'tipos' => TipoAtendimento::cases(),
        ]);
    }

    /**
     * Atualiza os dados do cabeçalho da prescrição. Toda mudança fica no
     * histórico (campo, de, para); o médico é gravado pelo id e pelo nome,
     * porque a Feegow pode mudar/remover o profissional.
     */
    public function update(Request $request, Prescricao $prescricao)
    {
        $dados = $request->validate([
            'medico_id' => ['nullable', 'integer'],
            'medico_nome' => ['nullable', 'string', 'max:150'],
            'clinica_id' => ['required', 'integer', 'exists:clinicas,id'],
            'tipo_atendimento' => ['required', Rule::enum(TipoAtendimento::class)],
            'agendamento' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'clinica_id.required' => 'Escolha a clínica da prescrição.',
            'tipo_atendimento.required' => 'Escolha o tipo de atendimento.',
        ]);

        $prescricao->load(['paciente', 'clinica', 'financeiro']);

        DB::transaction(function () use ($prescricao, $dados) {
            $antes = $this->resumoDaPrescricao($prescricao);

            $prescricao->update([
                'medico_id' => $dados['medico_id'] ?? null,
                'medico_nome' => $dados['medico_nome'] ?? null,
                'clinica_id' => $dados['clinica_id'],
                'tipo_atendimento' => $dados['tipo_atendimento'],
                'agendamento' => $dados['agendamento'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            // A clínica é de onde saem os medicamentos e onde o dinheiro é
            // contabilizado: o financeiro acompanha a troca.
            if ($prescricao->financeiro && (int) $prescricao->financeiro->clinica_id !== (int) $prescricao->clinica_id) {
                $prescricao->financeiro->update(['clinica_id' => $prescricao->clinica_id]);
            }

            $prescricao->refresh()->load(['paciente', 'clinica']);

            $alteracoes = $this->compararResumos($antes, $this->resumoDaPrescricao($prescricao));

            if ($alteracoes) {
                PrescricaoLog::registrar(
                    $prescricao,
                    TipoLogPrescricao::Edicao,
                    'Dados da prescrição alterados.',
                    ['alteracoes' => $alteracoes]
                );
            }
        });

        return $this->voltarParaPrescricao($request, $prescricao, 'Prescrição atualizada com sucesso.');
    }

    /**
     * Carrega tudo o que o "Imprimir cadastro" mostra: semanas com itens,
     * aplicações e atendimentos, financeiro, anotações e anexos.
     */
    private function carregarParaImpressao(Prescricao $prescricao): Prescricao
    {
        $prescricao->load([
            'paciente',
            'clinica',
            'user',
            'anexos.user',
            'observacoesRegistradas.user',
            'logs.user',
            'logs.semana',
            'semanas.itens.medicamento',
            'semanas.itens.combo.itens.medicamento',
            'semanas.atendimentos.iniciadoPor',
            'semanas.atendimentos.finalizadoPor',
            'semanas.atendimentos.aplicacoes.item',
            'semanas.atendimentos.aplicacoes.medicamento',
            'semanas.atendimentos.aplicacoes.entradaItem',
            'semanas.atendimentos.aplicacoes.vasilhameAberto',
            'semanas.atendimentos.aplicacoes.user',
            'semanas.parcelas',
            'financeiro.parcelas.semana',
            'financeiro.pagamentos.user',
        ]);

        $prescricao->semanas->each->setRelation('prescricao', $prescricao);

        return $prescricao;
    }

    /**
     * Prescrição completa em uma página: dados, financeiro, semanas com o
     * histórico das aplicações, anotações e o histórico (logs). As edições
     * acontecem nos modais da própria tela.
     */
    public function imprimir(Prescricao $prescricao)
    {
        return view('prescricoes.imprimir', [
            'prescricao' => $this->carregarParaImpressao($prescricao),
            'clinicas' => Clinica::orderBy('nome')->get(),
            'tipos' => TipoAtendimento::cases(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'formasComParcelas' => FormaPagamento::comParcelas(),
            'parcelasDisponiveis' => FormaPagamento::parcelasDisponiveis(),
        ]);
    }

    /**
     * Médicos da Feegow para o modal de edição (carregado só quando o modal
     * abre: a página não depende da Feegow para abrir).
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
     * A mesma página em PDF (A4 retrato), para baixar/arquivar.
     */
    public function imprimirPdf(Prescricao $prescricao)
    {
        $prescricao = $this->carregarParaImpressao($prescricao);

        $pdf = Pdf::loadView('prescricoes.pdf.prescricao', ['prescricao' => $prescricao]);
        $pdf->setPaper('a4', 'portrait');

        $nome = 'prescricao-'.$prescricao->id.'-'
            .str($prescricao->paciente?->nome ?? 'paciente')->slug().'.pdf';

        return $pdf->download($nome);
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
            'observacoesRegistradas.user',
            'logs.user',
            'semanas.itens.medicamento',
            'semanas.itens.combo',
            'semanas.itens.aplicacoes',
            'semanas.parcelas',
            'financeiro.parcelas.semana',
            'financeiro.pagamentos.user',
        ]);

        // Cada semana olha a prescrição para saber se pode ir para a fila
        // (aplicação sequencial): sem isso seria uma consulta por linha.
        $prescricao->semanas->each->setRelation('prescricao', $prescricao);

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

        foreach ($this->salvarAnexos($prescricao, $request->file('anexos', [])) as $anexo) {
            PrescricaoLog::registrar($prescricao, TipoLogPrescricao::AnexoEnviado, 'Anexo enviado: '.$anexo->nome.'.', [
                'detalhes' => [
                    'Arquivo' => $anexo->nome,
                    'Tamanho' => $anexo->tamanho_formatado,
                    'Tipo' => $anexo->mime,
                ],
            ]);
        }

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    /**
     * Registra uma observação na prescrição. Fica gravado quem escreveu e
     * quando (a tela volta com a aba de observações aberta).
     */
    public function storeObservacao(Request $request, Prescricao $prescricao)
    {
        $dados = $request->validate([
            'observacao' => ['required', 'string', 'max:2000'],
        ], [
            'observacao.required' => 'Escreva a observação antes de registrar.',
            'observacao.max' => 'A observação deve ter no máximo 2000 caracteres.',
        ]);

        $prescricao->observacoesRegistradas()->create([
            'user_id' => auth()->id(),
            'observacao' => $dados['observacao'],
        ]);

        PrescricaoLog::registrar($prescricao, TipoLogPrescricao::Observacao, 'Observação registrada.', [
            'detalhes' => ['Observação' => $dados['observacao']],
        ]);

        return $this->voltarParaPrescricao($request, $prescricao, 'Observação registrada.', 'observacoes');
    }

    /**
     * Remove um anexo da prescrição.
     */
    public function destroyAnexo(Prescricao $prescricao, PrescricaoAnexo $anexo)
    {
        abort_if($anexo->prescricao_id !== $prescricao->id, 404);

        PrescricaoLog::registrar($prescricao, TipoLogPrescricao::AnexoRemovido, 'Anexo removido: '.$anexo->nome.'.', [
            'detalhes' => ['Arquivo' => $anexo->nome, 'Tamanho' => $anexo->tamanho_formatado],
        ]);

        if ($anexo->arquivo && file_exists(public_path($anexo->arquivo))) {
            @unlink(public_path($anexo->arquivo));
        }

        $anexo->delete();

        return redirect()
            ->route('prescricoes.show', $prescricao)
            ->with('success', 'Anexo removido.');
    }

    /**
     * Exclui a prescrição (e o financeiro). Só administradores — a rota também
     * está protegida pelo middleware perfil:administrador. Bloqueado quando
     * alguma semana já tem aplicação de Ampola/Miligrama.
     */
    public function destroy(Prescricao $prescricao)
    {
        if (! auth()->user()?->ehAdministrador()) {
            return back()->with('error', 'Só administradores podem excluir uma prescrição.');
        }

        if (! $prescricao->pode_ser_excluida) {
            return back()->with('error', $prescricao->motivo_bloqueio_exclusao);
        }

        DB::transaction(function () use ($prescricao) {
            $prescricao->load(['semanas.itens', 'anexos', 'financeiro']);

            PrescricaoLog::registrar($prescricao, TipoLogPrescricao::Exclusao, 'Prescrição excluída.', [
                'detalhes' => $this->detalhesDaPrescricao($prescricao),
            ]);

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
                // Vai para a tela: a observação precisa aparecer quando o
                // paciente é escolhido na prescrição.
                'observacao' => $paciente->observacao,
            ])
            ->values();

        return response()->json(['results' => $resultados]);
    }

    /**
     * Verifica se o paciente já tem uma prescrição em aberto (não finalizada).
     * Usado no cadastro para avisar e pedir confirmação antes de criar outra.
     */
    public function prescricaoAberta(Paciente $paciente)
    {
        $aberta = Prescricao::with('semanas')
            ->where('paciente_id', $paciente->id)
            ->orderByDesc('id')
            ->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->estaAberta());

        if (! $aberta) {
            return response()->json(['aberta' => false]);
        }

        return response()->json([
            'aberta' => true,
            'id' => $aberta->id,
            'situacao' => $aberta->situacao,
            'progresso' => ($aberta->ultima_semana_aplicada ?? 0).'/'.$aberta->semanas_com_aplicacao,
            'criada_em' => $aberta->created_at?->format('d/m/Y'),
        ]);
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
     * Resumo da prescrição (chave => valor) usado no histórico.
     *
     * @return array<string, string>
     */
    private function detalhesDaPrescricao(Prescricao $prescricao): array
    {
        $financeiro = $prescricao->financeiro;

        $detalhes = [
            'Paciente' => $prescricao->paciente?->nome ?? '—',
            'Clínica' => $prescricao->clinica?->nome ?? '—',
            'Médico' => $prescricao->medico_nome ?? '—',
            'Tipo de atendimento' => $prescricao->tipo_atendimento?->label() ?? '—',
            'Agendamento' => $prescricao->agendamento ?? '—',
            'Semanas' => (string) $prescricao->semanas->count(),
            'Valor bruto' => $financeiro?->valor_bruto_formatado ?? $prescricao->valor_total_formatado,
            'Desconto' => $financeiro && (float) $financeiro->valor_desconto > 0
                ? $financeiro->desconto_descricao.' ('.$financeiro->valor_desconto_formatado.')'
                : null,
            'Adicional' => $financeiro && (float) $financeiro->adicional_valor > 0
                ? $financeiro->valor_adicional_formatado
                : null,
            'Total' => $financeiro?->valor_total_formatado ?? $prescricao->valor_total_formatado,
            'Anexos' => $prescricao->anexos->isNotEmpty()
                ? $prescricao->anexos->pluck('nome')->implode(' · ')
                : null,
        ];

        return array_filter($detalhes, fn ($valor) => filled($valor) && $valor !== '—');
    }

    /**
     * Resumo do cabeçalho da prescrição para comparar antes/depois no histórico.
     *
     * @return array<string, string>
     */
    private function resumoDaPrescricao(Prescricao $prescricao): array
    {
        return [
            'Médico' => $prescricao->medico_nome ?? '—',
            'Clínica' => $prescricao->clinica?->nome ?? '—',
            'Tipo de atendimento' => $prescricao->tipo_atendimento?->label() ?? '—',
            'Agendamento' => $prescricao->agendamento ?? '—',
            'Observações' => $prescricao->observacoes ?? '—',
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
     * Salva os anexos enviados (arquivos ficam em public/uploads/prescricoes).
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $arquivos
     * @return array<int, PrescricaoAnexo>
     */
    private function salvarAnexos(Prescricao $prescricao, array $arquivos): array
    {
        if (empty($arquivos)) {
            return [];
        }

        $destino = public_path('uploads/prescricoes');
        $criados = [];

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

            $criados[] = $prescricao->anexos()->create([
                'nome' => $nomeOriginal,
                'arquivo' => 'uploads/prescricoes/'.$nomeArquivo,
                'mime' => $mime,
                'tamanho' => $tamanho,
                'user_id' => auth()->id(),
            ]);
        }

        return $criados;
    }
}
