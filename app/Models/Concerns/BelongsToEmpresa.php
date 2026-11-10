<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope());

        static::creating(function ($model) {
            if (empty($model->empresa_id) && Auth::check()) {
                $model->empresa_id = Auth::user()->empresa_id;
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
