<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use BelongsToEmpresa;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id', 'codigo', 'descricao', 'unidade', 'categoria',
        'preco', 'estoque', 'ativo', 'importado_em',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:4',
            'estoque' => 'decimal:3',
            'ativo' => 'boolean',
            'importado_em' => 'datetime',
        ];
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeBuscar(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        $like = '%'.addcslashes($termo, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('descricao', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('categoria', 'like', $like);
        });
    }

    /** Ordena por relevância: código exato, código começando com o termo, nome começando com o termo, resto. */
    public function scopeRelevancia(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        $inicio = addcslashes($termo, '%_\\').'%';

        return $query->orderByRaw(
            'CASE WHEN LOWER(codigo) = LOWER(?) THEN 0 WHEN codigo LIKE ? THEN 1 WHEN descricao LIKE ? THEN 2 ELSE 3 END',
            [$termo, $inicio, $inicio]
        );
    }
}
