<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento multi-tenant: toda consulta dos modelos com BelongsToEmpresa
 * é filtrada pela empresa do usuário logado.
 */
class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $usuario = Auth::user();

        if ($usuario && $usuario->empresa_id) {
            $builder->where($model->getTable().'.empresa_id', $usuario->empresa_id);
        }
    }
}
