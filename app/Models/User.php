<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const PERFIS = [
        'admin' => 'Administrador',
        'gestor' => 'Gestor comercial',
        'escritorio' => 'Escritório',
        'representante' => 'Representante',
    ];

    protected $fillable = [
        'empresa_id', 'name', 'email', 'password', 'perfil', 'ativo',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function temPerfil(string ...$perfis): bool
    {
        return in_array($this->perfil, $perfis, true);
    }

    public function ehAdmin(): bool
    {
        return $this->perfil === 'admin';
    }

    /** Quem enxerga os orçamentos e pedidos de todos os representantes. */
    public function veTodos(): bool
    {
        return $this->temPerfil('admin', 'gestor', 'escritorio');
    }

    /** Quem mantém os dados importados do ERP. */
    public function podeImportar(): bool
    {
        return $this->temPerfil('admin', 'escritorio');
    }

    /** Quem confere os cadastros feitos em campo e lança o código do ERP. */
    public function podeConferirClientes(): bool
    {
        return $this->podeImportar();
    }

    /** Cadastro de cliente em campo depende de a empresa ter ligado a opção. */
    public function podeCadastrarCliente(): bool
    {
        return (bool) $this->empresa?->cadastro_clientes;
    }

    public function rotuloPerfil(): string
    {
        return self::PERFIS[$this->perfil] ?? $this->perfil;
    }
}
