<?php

namespace App\Http\Controllers;

use App\Enums\StatusAtivoInativo;
use App\Enums\TipoMedicamento;
use App\Models\Grupo;
use App\Models\Medicamento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicamentoController extends Controller
{
    /**
     * Lista os medicamentos cadastrados.
     */
    public function index()
    {
        $medicamentos = Medicamento::with('grupo')
            ->orderBy('nome')
            ->get();

        return view('medicamentos.index', compact('medicamentos'));
    }

    /**
     * Exibe o formulário de cadastro de medicamento.
     */
    public function create()
    {
        $grupos = Grupo::orderBy('nome')->get();

        return view('medicamentos.create', compact('grupos'));
    }

    /**
     * Cadastra um novo medicamento.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $dados['gera_aplicacao'] = $request->boolean('gera_aplicacao');

        Medicamento::create($dados);

        return redirect()
            ->route('medicamentos.index')
            ->with('success', 'Medicamento cadastrado com sucesso.');
    }

    /**
     * Exibe os detalhes de um medicamento.
     */
    public function show(Medicamento $medicamento)
    {
        return view('medicamentos.show', compact('medicamento'));
    }

    /**
     * Exibe o formulário de edição de um medicamento.
     */
    public function edit(Medicamento $medicamento)
    {
        $grupos = Grupo::orderBy('nome')->get();

        return view('medicamentos.edit', compact('medicamento', 'grupos'));
    }

    /**
     * Atualiza os dados de um medicamento.
     */
    public function update(Request $request, Medicamento $medicamento)
    {
        $dados = $this->validar($request);
        $dados['gera_aplicacao'] = $request->boolean('gera_aplicacao');

        $medicamento->update($dados);

        return redirect()
            ->route('medicamentos.index')
            ->with('success', 'Medicamento atualizado com sucesso.');
    }

    /**
     * Exclui (logicamente) um medicamento.
     */
    public function destroy(Medicamento $medicamento)
    {
        $medicamento->delete();

        return redirect()
            ->route('medicamentos.index')
            ->with('success', 'Medicamento excluído com sucesso.');
    }

    /**
     * Valida e normaliza os dados do medicamento.
     */
    private function validar(Request $request): array
    {
        // Máscaras vindas do formulário (1.234,56 → 1234.56 e 10,5 → 10.5)
        $request->merge([
            'ultimo_valor_pago' => $this->normalizarNumero($request->input('ultimo_valor_pago')),
            'valor_venda' => $this->normalizarNumero($request->input('valor_venda')),
            'tamanho_vasilhame' => $this->normalizarNumero($request->input('tamanho_vasilhame')),
        ]);

        return $request->validate(
            [
                'nome' => ['required', 'string', 'max:255'],
                'fabricante' => ['nullable', 'string', 'max:255'],
                'tipo' => ['required', Rule::in(array_column(TipoMedicamento::cases(), 'value'))],
                'tamanho_vasilhame' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'required_if:tipo,'.TipoMedicamento::Miligrama->value,
                ],
                'grupo_id' => ['nullable', Rule::exists('grupos', 'id')->whereNull('deleted_at')],
                'status' => ['required', Rule::in(array_column(StatusAtivoInativo::cases(), 'value'))],
                'ultimo_valor_pago' => ['nullable', 'numeric', 'min:0'],
                'valor_venda' => ['nullable', 'numeric', 'min:0'],
                'estoque_minimo' => ['nullable', 'integer', 'min:0'],
                'estoque_medio' => ['nullable', 'integer', 'min:0'],
                'gera_aplicacao' => ['nullable', 'boolean'],
                'feegow_aplicacao_id' => ['nullable', 'integer', 'min:0'],
            ],
            [],
            [
                'tamanho_vasilhame' => 'tamanho do vasilhame',
                'grupo_id' => 'grupo',
                'ultimo_valor_pago' => 'último valor pago',
                'valor_venda' => 'valor de venda',
                'estoque_minimo' => 'estoque mínimo',
                'estoque_medio' => 'estoque médio',
                'feegow_aplicacao_id' => 'ID de aplicação na Feegow',
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
