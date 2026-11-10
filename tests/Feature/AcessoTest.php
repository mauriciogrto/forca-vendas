<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenario;
use Tests\TestCase;

class AcessoTest extends TestCase
{
    use CriaCenario;
    use RefreshDatabase;

    public function test_visitante_e_enviado_para_o_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/clientes')->assertRedirect(route('login'));
    }

    public function test_tela_de_login_abre(): void
    {
        $this->get('/login')->assertOk()->assertSee('Força de Vendas');
    }

    public function test_login_correto_entra_e_incorreto_nao(): void
    {
        $empresa = $this->criarEmpresa();
        $usuario = $this->criarUsuario($empresa, 'representante', 'rep@teste.local');

        $this->post('/login', ['email' => 'rep@teste.local', 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'rep@teste.local', 'password' => 'senha12345'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_inativo_nao_entra(): void
    {
        $empresa = $this->criarEmpresa();
        $usuario = $this->criarUsuario($empresa);
        $usuario->update(['ativo' => false]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'senha12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_representante_nao_acessa_areas_restritas(): void
    {
        $rep = $this->criarUsuario($this->criarEmpresa(), 'representante');

        foreach (['importacoes.index', 'importacoes.create', 'perfis.index', 'relatorios.index', 'usuarios.index', 'configuracoes.edit'] as $rota) {
            $this->actingAs($rep)->get(route($rota))->assertForbidden();
        }
    }

    public function test_escritorio_importa_mas_nao_administra(): void
    {
        $esc = $this->criarUsuario($this->criarEmpresa(), 'escritorio');

        $this->actingAs($esc)->get(route('importacoes.index'))->assertOk();
        $this->actingAs($esc)->get(route('relatorios.index'))->assertOk();
        $this->actingAs($esc)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($esc)->get(route('configuracoes.edit'))->assertForbidden();
    }

    public function test_admin_abre_todas_as_telas(): void
    {
        $empresa = $this->criarEmpresa();
        $admin = $this->criarUsuario($empresa, 'admin');
        $this->criarCliente($empresa);
        $produto = $this->criarProduto($empresa);
        $this->criarPerfilImportacao($empresa);

        $telas = [
            route('home'), route('clientes.index'), route('produtos.index'), route('produtos.show', $produto),
            route('orcamentos.index'), route('orcamentos.create'),
            route('importacoes.index'), route('importacoes.create'), route('perfis.index'), route('perfis.create'),
            route('relatorios.index'), route('usuarios.index'), route('usuarios.create'), route('configuracoes.edit'),
        ];

        foreach ($telas as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_menu_mostra_abas_conforme_o_perfil(): void
    {
        $empresa = $this->criarEmpresa();

        $this->actingAs($this->criarUsuario($empresa, 'representante'))->get('/')
            ->assertOk()->assertSee('Orçamentos')->assertDontSee('Importar')->assertDontSee('Usuários');

        $this->actingAs($this->criarUsuario($empresa, 'admin'))->get('/')
            ->assertOk()->assertSee('Importar')->assertSee('Relatórios')->assertSee('Usuários')->assertSee('Empresa');
    }

    public function test_empresas_nao_enxergam_dados_uma_da_outra(): void
    {
        $a = $this->criarEmpresa('a');
        $b = $this->criarEmpresa('b');
        $clienteA = $this->criarCliente($a, 'C1', 'Cliente Secreto da A');
        $produtoA = $this->criarProduto($a, 'P1');
        $usuarioB = $this->criarUsuario($b, 'admin');

        $this->actingAs($usuarioB)->get(route('clientes.show', $clienteA))->assertNotFound();
        $this->actingAs($usuarioB)->get(route('produtos.show', $produtoA))->assertNotFound();
        $this->actingAs($usuarioB)->get(route('clientes.index'))->assertOk()->assertDontSee('Cliente Secreto da A');
    }
}
