<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orcamento extends Model
{
    use BelongsToEmpresa;

    public const STATUS = [
        'rascunho' => 'Rascunho',
        'enviado' => 'Enviado ao cliente',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
    ];

    protected $table = 'orcamentos';

    protected $fillable = [
        'empresa_id', 'user_id', 'cliente_id', 'numero', 'tipo', 'status', 'cliente_nome',
        'condicao_pagamento', 'observacoes', 'total', 'enviado_em', 'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'enviado_em' => 'datetime',
            'fechado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function representante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class)->orderBy('ordem');
    }

    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        return $usuario->veTodos() ? $query : $query->where('user_id', $usuario->id);
    }

    public function ehPedido(): bool
    {
        return $this->tipo === 'pedido';
    }

    public function rotuloTipo(): string
    {
        return $this->ehPedido() ? 'Pedido' : 'Orçamento';
    }

    public function rotuloStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Só o dono (ou o admin) mexe no documento. */
    public function podeSerAlteradoPor(User $usuario): bool
    {
        return $this->user_id === $usuario->id || $usuario->ehAdmin();
    }

    /** Orçamento em aberto pode ser editado; pedido fechado ou cancelado não. */
    public function editavel(): bool
    {
        return ! $this->ehPedido() && in_array($this->status, ['rascunho', 'enviado'], true);
    }
}
