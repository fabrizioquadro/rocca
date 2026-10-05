<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota aos perfis informados, por exemplo:
 *
 *     ->middleware('perfil:administrador')
 *     ->middleware('perfil:administrador,secretaria')
 *
 * É usado apenas nas áreas iniciais de cada perfil (Dashboard e Secretaria).
 * As demais rotas seguem o fluxo normal para todos os perfis.
 */
class RestringirPorPerfil
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || in_array($usuario->tipo?->value, $perfis, true)) {
            return $next($request);
        }

        abort(403, 'Acesso não autorizado para o seu perfil.');
    }
}
