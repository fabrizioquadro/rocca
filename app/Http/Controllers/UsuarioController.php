<?php

namespace App\Http\Controllers;

use App\Enums\StatusUsuario;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /**
     * Lista os usuários do sistema.
     */
    public function index()
    {
        $usuarios = User::with('clinica')
            ->orderBy('nome')
            ->get();

        $clinicas = Clinica::orderBy('nome')->get();

        return view('usuarios.index', compact('usuarios', 'clinicas'));
    }

    /**
     * Exibe o formulário de cadastro de usuário.
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();

        return view('usuarios.create', compact('clinicas'));
    }

    /**
     * Cadastra um novo usuário (com imagem opcional).
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'tipo' => ['required', 'in:administrador,secretaria,enfermagem'],
            'status' => ['required', 'in:ativo,inativo,excluido'],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
            'coren' => ['nullable', 'string', 'max:255'],
            'imagem' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Upload da imagem (se enviada)
        $imagem = null;
        if ($request->hasFile('imagem')) {
            $nomeArquivo = 'usuario_'.time().'_'.uniqid().'.'.$request->file('imagem')->extension();
            $destino = public_path('uploads/users');

            File::ensureDirectoryExists($destino);

            $request->file('imagem')->move($destino, $nomeArquivo);
            $imagem = 'uploads/users/'.$nomeArquivo;
        }

        User::create([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'password' => $dados['password'],
            'tipo' => $dados['tipo'],
            'status' => $dados['status'],
            'clinica_id' => $dados['clinica_id'] ?: null,
            'coren' => $dados['coren'] ?: null,
            'imagem' => $imagem,
        ]);

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuário cadastrado com sucesso.');
    }

    /**
     * Exibe os detalhes de um usuário.
     */
    public function show(User $usuario)
    {
        return view('usuarios.show', compact('usuario'));
    }

    /**
     * Exibe o formulário de edição de um usuário.
     */
    public function edit(User $usuario)
    {
        $clinicas = Clinica::orderBy('nome')->get();

        return view('usuarios.edit', compact('usuario', 'clinicas'));
    }

    /**
     * Atualiza os dados de um usuário.
     */
    public function update(Request $request, User $usuario)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'tipo' => ['required', 'in:administrador,secretaria,enfermagem'],
            'status' => ['required', 'in:ativo,inativo,excluido'],
            'clinica_id' => ['nullable', 'exists:clinicas,id'],
            'coren' => ['nullable', 'string', 'max:255'],
            'imagem' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Nova imagem enviada?
        if ($request->hasFile('imagem')) {
            $nomeArquivo = 'usuario_'.time().'_'.uniqid().'.'.$request->file('imagem')->extension();
            $destino = public_path('uploads/users');

            File::ensureDirectoryExists($destino);
            $request->file('imagem')->move($destino, $nomeArquivo);

            // remove a imagem antiga (se existir e for local)
            if ($usuario->imagem && file_exists(public_path($usuario->imagem))) {
                @unlink(public_path($usuario->imagem));
            }

            $usuario->imagem = 'uploads/users/'.$nomeArquivo;
        }

        $usuario->nome = $dados['nome'];
        $usuario->email = $dados['email'];
        $usuario->tipo = $dados['tipo'];
        $usuario->status = $dados['status'];
        $usuario->clinica_id = $dados['clinica_id'] ?: null;
        $usuario->coren = $dados['coren'] ?: null;

        if (! empty($dados['password'])) {
            $usuario->password = $dados['password'];
        }

        $usuario->save();

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }
}
