<?php

namespace App\Http\Controllers;

use App\Enums\StatusAtivoInativo;
use App\Models\Fornecedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FornecedorController extends Controller
{
    /**
     * Lista os fornecedores cadastrados.
     */
    public function index()
    {
        $fornecedores = Fornecedor::query()
            ->orderBy('nome')
            ->get();

        return view('fornecedores.index', compact('fornecedores'));
    }

    /**
     * Exibe o formulário de cadastro de fornecedor.
     */
    public function create()
    {
        return view('fornecedores.create');
    }

    /**
     * Cadastra um novo fornecedor.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        Fornecedor::create($dados);

        return redirect()
            ->route('fornecedores.index')
            ->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    /**
     * Exibe os detalhes de um fornecedor.
     */
    public function show(Fornecedor $fornecedor)
    {
        return view('fornecedores.show', compact('fornecedor'));
    }

    /**
     * Exibe o formulário de edição de um fornecedor.
     */
    public function edit(Fornecedor $fornecedor)
    {
        return view('fornecedores.edit', compact('fornecedor'));
    }

    /**
     * Atualiza os dados de um fornecedor.
     */
    public function update(Request $request, Fornecedor $fornecedor)
    {
        $dados = $this->validar($request, $fornecedor->id);

        $fornecedor->update($dados);

        return redirect()
            ->route('fornecedores.index')
            ->with('success', 'Fornecedor atualizado com sucesso.');
    }

    /**
     * Exclui (logicamente) um fornecedor.
     */
    public function destroy(Fornecedor $fornecedor)
    {
        $fornecedor->delete();

        return redirect()
            ->route('fornecedores.index')
            ->with('success', 'Fornecedor excluído com sucesso.');
    }

    /**
     * Valida e normaliza os dados do fornecedor.
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        // Remove máscaras antes de validar/salvar (guarda somente os dígitos)
        $request->merge([
            'cnpj' => $this->somenteDigitos($request->input('cnpj')),
            'telefone' => $this->somenteDigitos($request->input('telefone')),
            'celular' => $this->somenteDigitos($request->input('celular')),
        ]);

        return $request->validate(
            [
                'nome' => ['required', 'string', 'max:255'],
                'cnpj' => [
                    'nullable',
                    'digits:14',
                    Rule::unique('fornecedores', 'cnpj')->whereNull('deleted_at')->ignore($ignorarId),
                ],
                'email' => ['nullable', 'string', 'email', 'max:255'],
                'telefone' => ['nullable', 'digits_between:8,11'],
                'celular' => ['nullable', 'digits_between:8,11'],
                'status' => ['required', Rule::in(array_column(StatusAtivoInativo::cases(), 'value'))],
            ],
            [],
            [
                'cnpj' => 'CNPJ',
                'telefone' => 'telefone',
                'celular' => 'celular',
            ]
        );
    }

    /**
     * Retorna apenas os dígitos de um valor (ou null se vazio).
     */
    private function somenteDigitos(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return preg_replace('/\D+/', '', $valor);
    }
}
