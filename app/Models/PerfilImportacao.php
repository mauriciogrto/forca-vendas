<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class PerfilImportacao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'perfis_importacao';

    protected $fillable = [
        'empresa_id', 'nome', 'entidade', 'linha_cabecalho', 'mapeamento', 'desativar_ausentes',
    ];

    protected function casts(): array
    {
        return [
            'mapeamento' => 'array',
            'linha_cabecalho' => 'integer',
            'desativar_ausentes' => 'boolean',
        ];
    }

    /**
     * Perfis iniciais criados com a empresa. Os nomes de coluna seguem as planilhas de exemplo
     * (docs/exemplos); cada ERP real vira um perfil novo, ajustado na tela de perfis.
     *
     * @return list<array<string, mixed>>
     */
    public static function padroes(): array
    {
        return [
            [
                'nome' => 'Produtos — planilha padrão',
                'entidade' => 'produtos',
                'linha_cabecalho' => 1,
                'desativar_ausentes' => false,
                'mapeamento' => [
                    'codigo' => 'Código', 'descricao' => 'Descrição', 'preco' => 'Preço',
                    'unidade' => 'Unidade', 'categoria' => 'Grupo', 'estoque' => 'Estoque',
                ],
            ],
            [
                'nome' => 'Clientes — planilha padrão',
                'entidade' => 'clientes',
                'linha_cabecalho' => 1,
                'desativar_ausentes' => false,
                'mapeamento' => [
                    'codigo' => 'Código', 'nome' => 'Nome', 'documento' => 'CNPJ/CPF', 'telefone' => 'Telefone',
                    'email' => 'E-mail', 'endereco' => 'Endereço', 'cidade' => 'Cidade', 'uf' => 'UF',
                ],
            ],
        ];
    }
}
