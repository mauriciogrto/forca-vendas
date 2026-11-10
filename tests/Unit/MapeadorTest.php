<?php

namespace Tests\Unit;

use App\Services\Importacao\ImportacaoException;
use App\Services\Importacao\Mapeador;
use PHPUnit\Framework\TestCase;

class MapeadorTest extends TestCase
{
    private const MAPEAMENTO_PRODUTOS = [
        'codigo' => 'Código',
        'descricao' => 'Descrição',
        'preco' => 'Preço de venda',
        'estoque' => 'Saldo',
        'unidade' => '',
    ];

    public function test_resolve_colunas_ignorando_acentos_e_caixa(): void
    {
        $cabecalhos = ['CODIGO', 'descricao ', 'Saldo', 'PREÇO DE VENDA'];
        $colunas = Mapeador::resolverColunas($cabecalhos, self::MAPEAMENTO_PRODUTOS, 'produtos');

        $this->assertSame(['codigo' => 0, 'descricao' => 1, 'preco' => 3, 'estoque' => 2], $colunas);
    }

    public function test_coluna_inexistente_lista_as_encontradas(): void
    {
        try {
            Mapeador::resolverColunas(['Código', 'Descrição'], self::MAPEAMENTO_PRODUTOS, 'produtos');
            $this->fail('Deveria lançar ImportacaoException');
        } catch (ImportacaoException $e) {
            $this->assertStringContainsString('Preço de venda', $e->getMessage());
            $this->assertStringContainsString('Código, Descrição', $e->getMessage());
        }
    }

    public function test_campo_obrigatorio_sem_mapeamento(): void
    {
        $this->expectException(ImportacaoException::class);
        Mapeador::resolverColunas(['Código'], ['codigo' => 'Código'], 'produtos');
    }

    public function test_linha_de_produto_valida(): void
    {
        $colunas = ['codigo' => 0, 'descricao' => 1, 'preco' => 2, 'estoque' => 3];
        $r = Mapeador::linha([1001, ' Parafuso   M8 ', '1.234,50', '10'], $colunas, 'produtos');

        $this->assertSame([], $r['erros']);
        $this->assertSame('1001', $r['dados']['codigo']);
        $this->assertSame('Parafuso M8', $r['dados']['descricao']);
        $this->assertSame(1234.5, $r['dados']['preco']);
        $this->assertSame(10.0, $r['dados']['estoque']);
    }

    public function test_linha_de_produto_com_erros(): void
    {
        $colunas = ['codigo' => 0, 'descricao' => 1, 'preco' => 2];
        $r = Mapeador::linha([null, 'Item', 'abc'], $colunas, 'produtos');

        $this->assertCount(2, $r['erros']);
        $this->assertStringContainsString('Código do produto em branco', $r['erros'][0]);
        $this->assertStringContainsString('Preço de venda inválido', $r['erros'][1]);
    }

    public function test_preco_em_branco_conta_como_obrigatorio(): void
    {
        $r = Mapeador::linha(['1', 'Item', null], ['codigo' => 0, 'descricao' => 1, 'preco' => 2], 'produtos');
        $this->assertSame(['Preço de venda em branco.'], $r['erros']);
    }

    public function test_cliente_uf_e_email(): void
    {
        $colunas = ['codigo' => 0, 'nome' => 1, 'uf' => 2, 'email' => 3];

        $ok = Mapeador::linha(['C1', 'Cliente A', 'rs', 'a@b.com'], $colunas, 'clientes');
        $this->assertSame([], $ok['erros']);
        $this->assertSame('RS', $ok['dados']['uf']);
        $this->assertSame('a@b.com', $ok['dados']['email']);

        $ruim = Mapeador::linha(['C2', 'Cliente B', 'Rio Grande', 'nao-e-email'], $colunas, 'clientes');
        $this->assertCount(1, $ruim['erros']);
        $this->assertNull($ruim['dados']['email']);
    }
}
