<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcamentoItem extends Model
{
    public $timestamps = false;

    protected $table = 'orcamento_itens';

    protected $fillable = [
        'orcamento_id', 'produto_id', 'codigo', 'descricao', 'unidade',
        'quantidade', 'preco_unitario', 'desconto_percentual', 'total', 'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:4',
            'desconto_percentual' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
