<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('perfil:admin,escritorio') */
class ExigePerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        abort_unless($usuario && $usuario->temPerfil(...$perfis), 403, 'Você não tem permissão para acessar esta área.');

        return $next($request);
    }
}
