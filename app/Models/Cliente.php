<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use BelongsToEmpresa;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id', 'codigo', 'nome', 'documento', 'telefone', 'email',
        'endereco', 'cidade', 'uf', 'importado_em', 'situacao', 'cadastrado_por', 'conferido_em',
    ];

    protected function casts(): array
    {
        return ['importado_em' => 'datetime', 'conferido_em' => 'datetime'];
    }

    public function cadastradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadastrado_por');
    }

    public function estaPendente(): bool
    {
        return $this->situacao === 'pendente';
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('situacao', 'pendente');
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    /** Busca por nome, código ou documento (com ou sem pontuação). */
    public function scopeBuscar(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        $like = '%'.addcslashes($termo, '%_\\').'%';
        $digitos = preg_replace('/\D+/', '', $termo);

        return $query->where(function (Builder $q) use ($like, $digitos) {
            $q->where('nome', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('cidade', 'like', $like);

            if ($digitos !== '') {
                $q->orWhere('documento', 'like', '%'.$digitos.'%');
            }
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
            'CASE WHEN LOWER(codigo) = LOWER(?) THEN 0 WHEN codigo LIKE ? THEN 1 WHEN nome LIKE ? THEN 2 ELSE 3 END',
            [$termo, $inicio, $inicio]
        );
    }
}
