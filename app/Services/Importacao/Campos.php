<?php

namespace App\Services\Importacao;

/**
 * Campos que cada entidade aceita na importação.
 * 'obrigatorio' = a coluna precisa estar mapeada e preenchida em cada linha.
 */
final class Campos
{
    public const ENTIDADES = [
        'clientes' => 'Clientes',
        'produtos' => 'Produtos, preços e estoque',
    ];

    private const DEFINICAO = [
        'clientes' => [
            'codigo' => ['rotulo' => 'Código do cliente', 'obrigatorio' => true],
            'nome' => ['rotulo' => 'Nome / razão social', 'obrigatorio' => true],
            'documento' => ['rotulo' => 'CNPJ / CPF', 'obrigatorio' => false],
            'telefone' => ['rotulo' => 'Telefone', 'obrigatorio' => false],
            'email' => ['rotulo' => 'E-mail', 'obrigatorio' => false],
            'endereco' => ['rotulo' => 'Endereço', 'obrigatorio' => false],
            'cidade' => ['rotulo' => 'Cidade', 'obrigatorio' => false],
            'uf' => ['rotulo' => 'UF', 'obrigatorio' => false],
        ],
        'produtos' => [
            'codigo' => ['rotulo' => 'Código do produto', 'obrigatorio' => true],
            'descricao' => ['rotulo' => 'Descrição', 'obrigatorio' => true],
            'preco' => ['rotulo' => 'Preço de venda', 'obrigatorio' => true],
            'unidade' => ['rotulo' => 'Unidade', 'obrigatorio' => false],
            'categoria' => ['rotulo' => 'Categoria / grupo', 'obrigatorio' => false],
            'estoque' => ['rotulo' => 'Estoque', 'obrigatorio' => false],
        ],
    ];

    /** @return array<string, array{rotulo: string, obrigatorio: bool}> */
    public static function para(string $entidade): array
    {
        if (! isset(self::DEFINICAO[$entidade])) {
            throw new ImportacaoException("Entidade de importação desconhecida: {$entidade}.");
        }

        return self::DEFINICAO[$entidade];
    }

    public static function todas(): array
    {
        return self::DEFINICAO;
    }

    public static function entidadeValida(string $entidade): bool
    {
        return isset(self::DEFINICAO[$entidade]);
    }
}
