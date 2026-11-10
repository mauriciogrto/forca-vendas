<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nome', 'slug', 'desconto_maximo', 'validade_dias', 'rodape_pdf', 'cadastro_clientes', 'proximo_numero', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'desconto_maximo' => 'decimal:2',
            'validade_dias' => 'integer',
            'cadastro_clientes' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
