<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Importacao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'importacoes';

    protected $fillable = [
        'empresa_id', 'perfil_importacao_id', 'user_id', 'entidade', 'arquivo_original',
        'caminho', 'status', 'total_linhas', 'novos', 'atualizados', 'com_erro',
        'resumo', 'publicada_em',
    ];

    protected function casts(): array
    {
        return [
            'resumo' => 'array',
            'publicada_em' => 'datetime',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilImportacao::class, 'perfil_importacao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getValidosAttribute(): int
    {
        return $this->novos + $this->atualizados;
    }

    /** Data da última importação publicada de uma entidade (carimbo "atualizado em"). */
    public static function ultimaPublicada(string $entidade): ?\Illuminate\Support\Carbon
    {
        $valor = static::where('entidade', $entidade)
            ->where('status', 'publicada')
            ->max('publicada_em');

        return $valor ? \Illuminate\Support\Carbon::parse($valor) : null;
    }
}
