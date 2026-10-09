<?php

namespace App\Http\Controllers;

use App\Models\Prescricao;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Para onde voltar depois de salvar um formulário da prescrição.
     *
     * A one-page "Imprimir cadastro" manda origem=imprimir nos formulários;
     * sem isso o usuário volta para a tela da prescrição (na aba informada).
     */
    protected function voltarParaPrescricao(
        Request $request,
        Prescricao $prescricao,
        string $mensagem,
        ?string $aba = null
    ): RedirectResponse {
        if ($request->input('origem') === 'imprimir') {
            return redirect()
                ->route('prescricoes.imprimir', $prescricao)
                ->with('success', $mensagem);
        }

        return redirect()
            ->route('prescricoes.show', array_filter([
                'prescricao' => $prescricao,
                'aba' => $aba,
            ]))
            ->with('success', $mensagem);
    }
}
