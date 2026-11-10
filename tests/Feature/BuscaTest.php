<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenario;
use Tests\TestCase;

class BuscaTest extends TestCase
{
    use CriaCenario;
    use RefreshDatabase;

    public function test_cliente_com_codigo_exato_vem_antes_de_quem_so_tem_os_digitos_no_cnpj(): void
    {
        $empresa = $this->criarEmpresa();
        $usuario = $this->criarUsuario($empresa);

        // "Aaa" e "Bbb" vêm antes na ordem alfabética e têm 0001 no CNPJ.
        foreach (['Aaa Comércio', 'Bbb Comércio'] as $i => $nome) {
            $c = $this->criarCliente($empresa, 'X'.$i, $nome);
            $c->update(['documento' => '12000100000199']);
        }
        $this->criarCliente($empresa, 'C0001', 'Zeta Distribuidora');

        $this->actingAs($usuario)->get('/clientes?q=C0001')
            ->assertOk()
            ->assertSeeInOrder(['Zeta Distribuidora']);

        $ordem = Cliente::withoutGlobalScopes()->buscar('C0001')->relevancia('C0001')->orderBy('nome')->pluck('codigo')->all();
        $this->assertSame('C0001', $ordem[0]);
    }

    public function test_prefixo_do_codigo_vem_antes_de_nome_comum(): void
    {
        $empresa = $this->criarEmpresa();
        $this->criarProduto($empresa, 'ZZ-9', 10, ['descricao' => 'Cabo A001 reforçado']);
        $this->criarProduto($empresa, 'A001', 10, ['descricao' => 'Zinco']);
        $this->criarProduto($empresa, 'A0010', 10, ['descricao' => 'Alicate']);

        $codigos = Produto::withoutGlobalScopes()->buscar('A001')->relevancia('A001')->orderBy('descricao')->pluck('codigo')->all();

        $this->assertSame(['A001', 'A0010', 'ZZ-9'], $codigos);
    }

    public function test_busca_ajax_de_produtos_respeita_a_relevancia(): void
    {
        $empresa = $this->criarEmpresa();
        $usuario = $this->criarUsuario($empresa);
        $this->criarProduto($empresa, 'ZZ-9', 10, ['descricao' => 'Cabo A001']);
        $this->criarProduto($empresa, 'A001', 10, ['descricao' => 'Zinco']);

        $this->actingAs($usuario)->getJson(route('produtos.busca', ['q' => 'a001']))
            ->assertOk()
            ->assertJsonPath('0.codigo', 'A001');
    }
}
