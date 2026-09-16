<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Exibe o formulário para solicitar o link de redefinição.
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Envia o e-mail com o link de redefinição.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response === Password::RESET_LINK_SENT) {
            return back()
                ->with('status', 'Enviamos por e-mail o link para redefinir a sua senha.');
        }

        return back()
            ->withErrors(['email' => 'Não encontramos uma conta com este e-mail.'])
            ->onlyInput('email');
    }
}
