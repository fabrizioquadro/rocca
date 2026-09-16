<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Exibe o formulário de login.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Autentica o usuário.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Permite login apenas para usuários com status "ativo"
        $attempt = Auth::attempt(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'status' => 'ativo',
            ],
            $request->boolean('remember')
        );

        if (! $attempt) {
            return back()
                ->withErrors([
                    'email' => 'Credenciais inválidas ou usuário inativo.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * Encerra a sessão do usuário.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
