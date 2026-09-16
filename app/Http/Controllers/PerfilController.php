<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PerfilController extends Controller
{
    /**
     * Exibe o perfil do usuário autenticado (dados editáveis + foto).
     */
    public function edit()
    {
        $usuario = auth()->user();

        $clinicas = Clinica::orderBy('nome')->get();

        return view('perfil.edit', compact('usuario', 'clinicas'));
    }

    /**
     * Atualiza o perfil do usuário autenticado (nome, e-mail, clínica, COREN e foto).
     */
    public function update(Request $request)
    {
        $usuario = auth()->user();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'coren' => ['nullable', 'string', 'max:255'],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
            'imagem' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Nova foto enviada?
        if ($request->hasFile('imagem')) {
            $nomeArquivo = 'usuario_'.time().'_'.uniqid().'.'.$request->file('imagem')->extension();
            $destino = public_path('uploads/users');

            File::ensureDirectoryExists($destino);

            $request->file('imagem')->move($destino, $nomeArquivo);

            // remove a foto antiga (se existir e for local)
            if ($usuario->imagem && file_exists(public_path($usuario->imagem))) {
                @unlink(public_path($usuario->imagem));
            }

            $usuario->imagem = 'uploads/users/'.$nomeArquivo;
        }

        $usuario->nome = $dados['nome'];
        $usuario->email = $dados['email'];
        $usuario->coren = $dados['coren'] ?: null;
        $usuario->clinica_id = $dados['clinica_id'] ?: null;
        $usuario->save();

        return redirect()
            ->route('perfil.edit')
            ->with('success', 'Perfil atualizado com sucesso.');
    }

    /**
     * Exibe o formulário de alteração de senha.
     */
    public function editSenha()
    {
        return view('perfil.alterar-senha');
    }

    /**
     * Altera a senha do usuário autenticado.
     */
    public function updateSenha(Request $request)
    {
        $request->validate(
            [
                'senha_atual' => ['required', 'string'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ],
            [],
            [
                'senha_atual' => 'senha atual',
                'password' => 'nova senha',
            ]
        );

        $usuario = auth()->user();

        if (! Hash::check($request->senha_atual, $usuario->password)) {
            return back()
                ->withErrors(['senha_atual' => 'A senha atual informada está incorreta.'])
                ->onlyInput('senha_atual');
        }

        $usuario->password = $request->password;
        $usuario->save();

        return redirect()
            ->route('perfil.senha')
            ->with('success', 'Senha alterada com sucesso.');
    }
}
