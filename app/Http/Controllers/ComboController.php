<?php

namespace App\Http\Controllers;

use App\Enums\StatusAtivoInativo;
use App\Models\Combo;
use App\Models\Medicamento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComboController extends Controller
{
    /**
     * Lista os combos cadastrados.
     */
    public function index()
    {
        $combos = Combo::query()
            ->with('itens.medicamento')
            ->orderBy('nome')
            ->get();

        return view('combos.index', compact('combos'));
    }

    /**
     * Exibe o formulário de cadastro de combo.
     */
    public function create()
    {
        $medicamentos = $this->medicamentosDisponiveis();
        $itensIniciais = old('itens', []);

        return view('combos.create', compact('medicamentos', 'itensIniciais'));
    }

    /**
     * Cadastra um novo combo (com os medicamentos).
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $combo = Combo::create([
            'nome' => $dados['nome'],
            'status' => $dados['status'],
        ]);

        $this->salvarItens($combo, $dados['itens']);

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo cadastrado com sucesso.');
    }

    /**
     * Exibe os detalhes de um combo.
     */
    public function show(Combo $combo)
    {
        $combo->load('itens.medicamento');

        return view('combos.show', compact('combo'));
    }

    /**
     * Exibe o formulário de edição de um combo.
     */
    public function edit(Combo $combo)
    {
        $combo->load('itens');

        $medicamentos = $this->medicamentosDisponiveis($combo);

        $itensIniciais = old('itens', $combo->itens->map(fn ($item) => [
            'medicamento_id' => $item->medicamento_id,
            'quantidade' => $item->quantidade_formatada,
            'valor' => $item->valor_formatado,
        ])->all());

        return view('combos.edit', compact('combo', 'medicamentos', 'itensIniciais'));
    }

    /**
     * Atualiza os dados de um combo.
     */
    public function update(Request $request, Combo $combo)
    {
        $dados = $this->validar($request);

        $combo->update([
            'nome' => $dados['nome'],
            'status' => $dados['status'],
        ]);

        // Substitui os medicamentos do combo
        $combo->itens()->delete();
        $this->salvarItens($combo, $dados['itens']);

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo atualizado com sucesso.');
    }

    /**
     * Exclui (logicamente) um combo.
     */
    public function destroy(Combo $combo)
    {
        $combo->delete();

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo excluído com sucesso.');
    }

    /**
     * Medicamentos disponíveis para escolha (mantém os já usados pelo combo, mesmo excluídos).
     */
    private function medicamentosDisponiveis(?Combo $combo = null)
    {
        $idsUsados = $combo ? $combo->itens->pluck('medicamento_id')->all() : [];

        return Medicamento::withTrashed()
            ->where(function ($query) use ($idsUsados) {
                $query->whereNull('deleted_at');

                if ($idsUsados) {
                    $query->orWhereIn('id', $idsUsados);
                }
            })
            ->orderBy('nome')
            ->get();
    }

    /**
     * Grava os medicamentos do combo.
     */
    private function salvarItens(Combo $combo, array $itens): void
    {
        foreach ($itens as $item) {
            $combo->itens()->create([
                'medicamento_id' => $item['medicamento_id'],
                'quantidade' => $item['quantidade'],
                'valor' => $item['valor'],
            ]);
        }
    }

    /**
     * Valida e normaliza os dados do combo.
     */
    private function validar(Request $request): array
    {
        // Normaliza as máscaras de cada item (1.234,56 → 1234.56 / 6,25 → 6.25)
        // e descarta linhas totalmente vazias.
        $itens = collect($request->input('itens', []))
            ->map(fn ($item) => [
                'medicamento_id' => $item['medicamento_id'] ?? null,
                'quantidade' => $this->normalizarNumero($item['quantidade'] ?? null),
                'valor' => $this->normalizarNumero($item['valor'] ?? null),
            ])
            ->filter(fn ($item) => filled($item['medicamento_id']) || filled($item['quantidade']) || filled($item['valor']))
            ->values()
            ->all();

        $request->merge(['itens' => $itens]);

        return $request->validate(
            [
                'nome' => ['required', 'string', 'max:255'],
                'status' => ['required', Rule::in(array_column(StatusAtivoInativo::cases(), 'value'))],
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.medicamento_id' => ['required', 'distinct', 'exists:medicamentos,id'],
                'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
                'itens.*.valor' => ['required', 'numeric', 'min:0'],
            ],
            [
                'itens.required' => 'Adicione pelo menos um medicamento ao combo.',
                'itens.min' => 'Adicione pelo menos um medicamento ao combo.',
                'itens.*.medicamento_id.required' => 'Selecione o medicamento em todas as linhas.',
                'itens.*.medicamento_id.distinct' => 'O mesmo medicamento não pode ser adicionado duas vezes.',
                'itens.*.quantidade.required' => 'Informe a quantidade de todos os medicamentos.',
                'itens.*.quantidade.numeric' => 'A quantidade deve ser um número.',
                'itens.*.quantidade.gt' => 'A quantidade deve ser maior que zero.',
                'itens.*.valor.required' => 'Informe o valor de todos os medicamentos.',
                'itens.*.valor.numeric' => 'O valor deve ser um número.',
                'itens.*.valor.min' => 'O valor não pode ser negativo.',
            ]
        );
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
}
