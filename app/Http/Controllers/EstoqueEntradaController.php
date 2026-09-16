<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Clinica;
use App\Models\CodigoBarraMedicamento;
use App\Models\Entrada;
use App\Models\EntradaAnexo;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Fornecedor;
use App\Models\Medicamento;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class EstoqueEntradaController extends Controller
{
    /**
     * Lista as entradas lançadas.
     */
    public function index()
    {
        $entradas = Entrada::with(['clinica', 'fornecedor', 'user', 'itens.medicamento'])
            ->orderByDesc('data_entrada')
            ->orderByDesc('id')
            ->get();

        return view('estoque.entradas.index', compact('entradas'));
    }

    /**
     * Exibe o formulário de lançamento de entrada (nota fiscal).
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();
        $fornecedores = Fornecedor::orderBy('nome')->get();
        $medicamentos = Medicamento::orderBy('nome')->get();
        $itensIniciais = old('itens', []);

        return view('estoque.entradas.create', compact('clinicas', 'fornecedores', 'medicamentos', 'itensIniciais'));
    }

    /**
     * Gera o próximo código de barras do medicamento (sequencial por medicamento).
     * Formato: 2 dígitos do medicamento + 5 dígitos do contador (ex.: 0300001).
     */
    public function gerarCodigoBarras(Request $request)
    {
        $dados = $request->validate([
            'medicamento_id' => ['required', 'exists:medicamentos,id'],
        ]);

        $controle = CodigoBarraMedicamento::firstOrNew(['medicamento_id' => $dados['medicamento_id']]);
        $controle->contador = (int) $controle->contador + 1;
        $controle->save();

        $parteMedicamento = str_pad((string) $dados['medicamento_id'], 2, '0', STR_PAD_LEFT);
        $parteContador = str_pad((string) $controle->contador, 5, '0', STR_PAD_LEFT);

        return response()->json([
            'codigo' => $parteMedicamento.$parteContador,
        ]);
    }

    /**
     * Endpoint usado pelo formulário (ao sair do campo) para validar o código de barras.
     */
    public function verificarCodigoBarras(Request $request)
    {
        $codigo = trim((string) $request->input('codigo_barras'));

        if ($codigo === '') {
            return response()->json(['ok' => true]);
        }

        $mensagem = $this->conflitoCodigoBarras(
            $codigo,
            $request->input('lote'),
            $request->input('medicamento_id')
        );

        return response()->json([
            'ok' => $mensagem === null,
            'mensagem' => $mensagem,
        ]);
    }

    /**
     * Lança a entrada: grava os itens (lote/código/vencimento/valor) e movimenta o estoque.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $entrada = DB::transaction(function () use ($dados, $request) {
            $entrada = Entrada::create([
                'clinica_id' => $dados['clinica_id'],
                'user_id' => auth()->id(),
                'fornecedor_id' => $dados['fornecedor_id'] ?? null,
                'numero_nota' => $dados['numero_nota'] ?? null,
                'data_entrada' => $dados['data_entrada'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ]);

            $this->salvarItens($entrada, $dados['itens']);
            $this->salvarAnexos($entrada, $request->file('anexos', []));

            return $entrada;
        });

        return redirect()
            ->route('estoque.entradas.show', $entrada)
            ->with('success', 'Entrada lançada com sucesso.');
    }

    /**
     * Exibe os detalhes de uma entrada.
     */
    public function show(Entrada $entrada)
    {
        $entrada->load([
            'clinica',
            'fornecedor',
            'user',
            'anexos',
            'itens.medicamento',
        ]);

        return view('estoque.entradas.show', compact('entrada'));
    }

    /**
     * Anexa arquivos (nota fiscal, recibo e etc.) a uma entrada já lançada.
     */
    public function storeAnexo(Request $request, Entrada $entrada)
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

        $this->salvarAnexos($entrada, $request->file('anexos', []));

        return redirect()
            ->route('estoque.entradas.show', $entrada)
            ->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    /**
     * Remove um anexo da entrada.
     */
    public function destroyAnexo(Entrada $entrada, EntradaAnexo $anexo)
    {
        abort_if($anexo->entrada_id !== $entrada->id, 404);

        if ($anexo->arquivo && file_exists(public_path($anexo->arquivo))) {
            @unlink(public_path($anexo->arquivo));
        }

        $anexo->delete();

        return redirect()
            ->route('estoque.entradas.show', $entrada)
            ->with('success', 'Anexo removido.');
    }

    /**
     * Exclui a entrada, estornando o estoque (movimentação negativa).
     */
    public function destroy(Entrada $entrada)
    {
        $entrada->load('itens');

        DB::transaction(function () use ($entrada) {
            foreach ($entrada->itens as $item) {
                EstoqueMovimentacao::create([
                    'medicamento_id' => $item->medicamento_id,
                    'entrada_item_id' => $item->id,
                    'clinica_id' => $entrada->clinica_id,
                    'tipo' => TipoMovimentacaoEstoque::Estorno,
                    'quantidade' => -1 * (int) $item->quantidade,
                    'user_id' => auth()->id(),
                    'observacao' => 'Estorno da exclusão da entrada #'.$entrada->id,
                ]);
            }

            // Exclusão lógica (os itens e o histórico de movimentações são mantidos)
            $entrada->delete();
        });

        return redirect()
            ->route('estoque.entradas.index')
            ->with('success', 'Entrada excluída e estoque estornado.');
    }

    /**
     * Grava os itens da entrada, movimenta o estoque e atualiza o último valor pago.
     */
    private function salvarItens(Entrada $entrada, array $itens): void
    {
        foreach ($itens as $item) {
            $entradaItem = $entrada->itens()->create([
                'medicamento_id' => $item['medicamento_id'],
                'lote' => $item['lote'] ?? null,
                'codigo_barras' => $item['codigo_barras'],
                'vencimento' => $item['vencimento'] ?? null,
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'] ?? null,
            ]);

            EstoqueMovimentacao::create([
                'medicamento_id' => $entradaItem->medicamento_id,
                'entrada_item_id' => $entradaItem->id,
                'clinica_id' => $entrada->clinica_id,
                'tipo' => TipoMovimentacaoEstoque::Entrada,
                'quantidade' => (int) $entradaItem->quantidade,
                'user_id' => auth()->id(),
                'observacao' => 'Entrada #'.$entrada->id.' — NF '.($entrada->numero_nota ?: 's/n'),
            ]);

            // O último valor pago passa a ser o valor unitário desta entrada
            if ($entradaItem->valor_unitario !== null) {
                $entradaItem->medicamento?->update(['ultimo_valor_pago' => $entradaItem->valor_unitario]);
            }
        }
    }

    /**
     * Grava os anexos da entrada (nota fiscal, recibo e etc.).
     */
    private function salvarAnexos(Entrada $entrada, array $arquivos): void
    {
        if (empty($arquivos)) {
            return;
        }

        $destino = public_path('uploads/entradas');

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

            $nomeArquivo = 'entrada_'.$entrada->id.'_'.time().'_'.uniqid().'.'.$extensao;

            $arquivo->move($destino, $nomeArquivo);

            $entrada->anexos()->create([
                'nome' => $nomeOriginal,
                'arquivo' => 'uploads/entradas/'.$nomeArquivo,
                'mime' => $mime,
                'tamanho' => $tamanho,
            ]);
        }
    }

    /**
     * Valida os dados da entrada.
     */
    private function validar(Request $request): array
    {
        $itens = collect($request->input('itens', []))
            ->map(fn ($item) => [
                'medicamento_id' => $item['medicamento_id'] ?? null,
                'lote' => $item['lote'] ?? null,
                'codigo_barras' => filled($item['codigo_barras'] ?? null) ? trim($item['codigo_barras']) : null,
                'vencimento' => filled($item['vencimento'] ?? null) ? $item['vencimento'] : null,
                'quantidade' => filled($item['quantidade'] ?? null) ? $item['quantidade'] : null,
                'valor_unitario' => $this->normalizarNumero($item['valor_unitario'] ?? null),
            ])
            ->filter(fn ($item) => filled($item['medicamento_id'])
                || filled($item['quantidade'])
                || filled($item['codigo_barras'])
                || filled($item['valor_unitario']))
            ->values()
            ->all();

        $request->merge(['itens' => $itens]);

        return $request->validate(
            [
                'clinica_id' => ['required', Rule::exists('clinicas', 'id')],
                'fornecedor_id' => ['nullable', Rule::exists('fornecedores', 'id')->whereNull('deleted_at')],
                'numero_nota' => ['nullable', 'string', 'max:255'],
                'data_entrada' => ['nullable', 'date'],
                'observacao' => ['nullable', 'string', 'max:1000'],
                'anexos' => ['nullable', 'array'],
                'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xml,csv', 'max:10240'],
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.medicamento_id' => ['required', 'exists:medicamentos,id'],
                'itens.*.lote' => ['nullable', 'string', 'max:255'],
                'itens.*.codigo_barras' => ['required', 'string', 'max:100', 'distinct', $this->codigoDeBarrasConsistente($request)],
                'itens.*.vencimento' => ['nullable', 'date'],
                'itens.*.quantidade' => ['required', 'integer', 'min:1'],
                'itens.*.valor_unitario' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'clinica_id.required' => 'Selecione a clínica que está comprando.',
                'itens.required' => 'Adicione pelo menos um medicamento na entrada.',
                'itens.min' => 'Adicione pelo menos um medicamento na entrada.',
                'itens.*.medicamento_id.required' => 'Selecione o medicamento em todas as linhas.',
                'itens.*.codigo_barras.required' => 'Informe o código de barras de todos os itens.',
                'itens.*.codigo_barras.distinct' => 'O mesmo código de barras não pode ser repetido nesta entrada.',
                'itens.*.vencimento.date' => 'O vencimento deve ser uma data válida.',
                'itens.*.quantidade.required' => 'Informe a quantidade de todos os itens.',
                'itens.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
                'itens.*.quantidade.min' => 'A quantidade deve ser pelo menos 1.',
                'itens.*.valor_unitario.numeric' => 'O valor unitário deve ser um número.',
                'itens.*.valor_unitario.min' => 'O valor unitário não pode ser negativo.',
            ]
        );
    }

    /**
     * Garante que o código de barras esteja sempre vinculado ao mesmo lote e medicamento.
     */
    private function codigoDeBarrasConsistente(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request) {
            if (blank($value)) {
                return;
            }

            preg_match('/^itens\.(\d+)\.codigo_barras$/', $attribute, $partes);
            $indice = $partes[1] ?? null;

            $mensagem = $this->conflitoCodigoBarras(
                $value,
                $indice === null ? null : $request->input("itens.$indice.lote"),
                $indice === null ? null : $request->input("itens.$indice.medicamento_id")
            );

            if ($mensagem) {
                $fail($mensagem);
            }
        };
    }

    /**
     * O código de barras já foi lançado com lote ou medicamento diferente?
     * Retorna a mensagem de erro ou null quando não há conflito.
     */
    private function conflitoCodigoBarras(string $codigo, ?string $lote, $medicamentoId): ?string
    {
        // whereHas('entrada') ignora entradas excluídas (soft delete)
        $existentes = EntradaItem::with('medicamento')
            ->where('codigo_barras', $codigo)
            ->whereHas('entrada')
            ->get();

        foreach ($existentes as $item) {
            if ($medicamentoId && (int) $item->medicamento_id !== (int) $medicamentoId) {
                return 'O código de barras '.$codigo.' já foi lançado para o medicamento "'
                    .($item->medicamento?->nome ?? '—').'" e não pode ser usado em outro medicamento.';
            }

            if ($this->normalizarTexto($item->lote) !== $this->normalizarTexto($lote)) {
                return 'O código de barras '.$codigo.' já foi usado com o lote "'
                    .($item->lote ?: 'não informado').'". Informe o mesmo lote ou gere um novo código.';
            }
        }

        return null;
    }

    /**
     * Converte valores mascarados (1.234,56) em número decimal (1234.56).
     */
    private function normalizarNumero(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $limpo = preg_replace('/[^\d,.]/', '', $valor);
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);

        return $limpo === '' ? null : $limpo;
    }

    /**
     * Normaliza texto para comparação (sem espaços nas pontas, maiúsculas).
     */
    private function normalizarTexto(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return mb_strtoupper(trim($valor));
    }
}
