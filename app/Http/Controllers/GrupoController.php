<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GrupoController extends Controller
{
    /**
     * Lista os grupos cadastrados.
     */
    public function index()
    {
        $grupos = Grupo::query()
            ->orderBy('nome')
            ->get();

        return view('grupos.index', compact('grupos'));
    }

    /**
     * Exibe o formulário de cadastro de grupo.
     */
    public function create()
    {
        return view('grupos.create');
    }

    /**
     * Cadastra um novo grupo.
     */
    public function store(Request $request)
    {
        $dados = $this->validar($request);

        Grupo::create($dados);

        return redirect()
            ->route('grupos.index')
            ->with('success', 'Grupo cadastrado com sucesso.');
    }

    /**
     * Exibe os detalhes de um grupo.
     */
    public function show(Grupo $grupo)
    {
        return view('grupos.show', compact('grupo'));
    }

    /**
     * Exibe o formulário de edição de um grupo.
     */
    public function edit(Grupo $grupo)
    {
        return view('grupos.edit', compact('grupo'));
    }

    /**
     * Atualiza os dados de um grupo.
     */
    public function update(Request $request, Grupo $grupo)
    {
        $dados = $this->validar($request, $grupo->id);

        $grupo->update($dados);

        return redirect()
            ->route('grupos.index')
            ->with('success', 'Grupo atualizado com sucesso.');
    }

    /**
     * Exclui (logicamente) um grupo.
     */
    public function destroy(Grupo $grupo)
    {
        $grupo->delete();

        return redirect()
            ->route('grupos.index')
            ->with('success', 'Grupo excluído com sucesso.');
    }

    /**
     * Valida os dados do grupo.
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grupos', 'nome')->whereNull('deleted_at')->ignore($ignorarId),
            ],
        ]);
    }
}
