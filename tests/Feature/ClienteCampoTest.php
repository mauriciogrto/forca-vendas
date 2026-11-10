<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Importacao;
use App\Models\Orcamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCenario;
use Tests\TestCase;

class ClienteCampoTest extends TestCase
{
    use CriaCenario;
    use RefreshDatabase;

    private function empresaComCadastro(string $slug = 'a')
    {
        $empresa = $this->criarEmpresa($slug);
        $empresa->update(['cadastro_clientes' => true]);

        return $empresa;
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Serralheria Nova Ltda',
            'documento' => '11.222.333/0001-44',
            'telefone' => '(54) 3000-1111',
            'email' => 'contato@nova.com.br',
            'cidade' => 'Garibaldi',
            'uf' => 'rs',
            'acao' => 'salvar',
        ], $extra);
    }

    private function cadastrar($usuario, array $extra = []): Cliente
    {
        $this->actingAs($usuario)->post(route('clientes.store'), $this->dados($extra))->assertSessionHasNoErrors();

        return Cliente::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_modo_consulta_nao_permite_cadastrar(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);

        $this->actingAs($rep)->get(route('clientes.create'))->assertForbidden();
        $this->actingAs($rep)->post(route('clientes.store'), $this->dados())->assertForbidden();
        $this->actingAs($rep)->get(route('clientes.index'))->assertOk()->assertDontSee('Novo cliente');
        $this->assertSame(0, Cliente::count());
    }

    public function test_admin_liga_o_cadastro_em_configuracoes(): void
    {
        $empresa = $this->criarEmpresa();
        $admin = $this->criarUsuario($empresa, 'admin');

        $campos = ['nome' => 'Empresa A', 'desconto_maximo' => 10, 'validade_dias' => 7];

        $this->actingAs($admin)->put(route('configuracoes.update'), $campos + ['cadastro_clientes' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($empresa->fresh()->cadastro_clientes);

        $this->actingAs($admin)->put(route('configuracoes.update'), $campos)->assertSessionHasNoErrors();
        $this->assertFalse($empresa->fresh()->cadastro_clientes);
    }

    public function test_representante_cadastra_cliente_pendente(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);

        $cliente = $this->cadastrar($rep);

        $this->assertSame('pendente', $cliente->situacao);
        $this->assertSame(sprintf('NOVO-%05d', $cliente->id), $cliente->codigo);
        $this->assertSame('11222333000144', $cliente->documento);
        $this->assertSame('RS', $cliente->uf);
        $this->assertSame($rep->id, (int) $cliente->cadastrado_por);

        $this->actingAs($rep)->get(route('clientes.index'))->assertOk()->assertSee('Serralheria Nova')->assertSee('Pendente');
        $this->actingAs($rep)->get(route('clientes.show', $cliente))->assertOk()->assertSee('aguardando conferência');
    }

    public function test_cliente_pendente_ja_serve_para_orcamento(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa, 'P1', 10.00);
        $cliente = $this->cadastrar($rep);

        $this->actingAs($rep)->post(route('orcamentos.store'), [
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1, 'desconto_percentual' => 0]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($cliente->id, (int) Orcamento::firstOrFail()->cliente_id);
        $this->actingAs($rep)->getJson(route('clientes.busca', ['q' => 'Serralheria']))
            ->assertOk()->assertJsonPath('0.situacao', 'pendente');
    }

    public function test_validacao_do_cadastro(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $this->criarCliente($empresa, 'C1', 'Cliente do ERP')->update(['documento' => '55.666.777/0001-88']);

        $this->actingAs($rep)->post(route('clientes.store'), $this->dados(['documento' => '123']))->assertSessionHasErrors('documento');
        $this->actingAs($rep)->post(route('clientes.store'), $this->dados(['documento' => '1234567890123']))->assertSessionHasErrors('documento');
        $this->actingAs($rep)->post(route('clientes.store'), $this->dados(['documento' => '55666777000188']))->assertSessionHasErrors('documento');
        $this->actingAs($rep)->post(route('clientes.store'), $this->dados(['uf' => 'RSX']))->assertSessionHasErrors('uf');
        $this->actingAs($rep)->post(route('clientes.store'), $this->dados(['nome' => '']))->assertSessionHasErrors('nome');
        $this->assertSame(1, Cliente::count());
    }

    public function test_representante_corrige_so_o_proprio_cadastro_pendente(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa, 'representante', 'rep1@teste.local');
        $outro = $this->criarUsuario($empresa, 'representante', 'rep2@teste.local');
        $cliente = $this->cadastrar($rep);

        $this->actingAs($outro)->get(route('clientes.edit', $cliente))->assertForbidden();
        $this->actingAs($outro)->put(route('clientes.update', $cliente), $this->dados())->assertForbidden();

        $this->actingAs($rep)->get(route('clientes.edit', $cliente))->assertOk();
        $this->actingAs($rep)->put(route('clientes.update', $cliente), $this->dados(['telefone' => '(54) 9999-0000']))
            ->assertSessionHasNoErrors()->assertRedirect(route('clientes.show', $cliente));
        $this->assertSame('(54) 9999-0000', $cliente->fresh()->telefone);
        $this->assertSame('pendente', $cliente->fresh()->situacao);

        // Representante não confirma nem exclui, mesmo mandando a ação na mão.
        $this->actingAs($rep)->put(route('clientes.update', $cliente), $this->dados(['acao' => 'confirmar', 'codigo' => 'X1']))->assertForbidden();
        $this->actingAs($rep)->delete(route('clientes.destroy', $cliente))->assertForbidden();
        $this->assertSame('pendente', $cliente->fresh()->situacao);
    }

    public function test_so_escritorio_e_admin_veem_a_fila_de_conferencia(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $gestor = $this->criarUsuario($empresa, 'gestor');
        $cliente = $this->cadastrar($rep);

        $this->actingAs($rep)->get(route('clientes.pendentes'))->assertForbidden();
        $this->actingAs($gestor)->get(route('clientes.pendentes'))->assertForbidden();
        $this->actingAs($esc)->get(route('clientes.pendentes'))->assertOk()->assertSee($cliente->nome);
        $this->actingAs($esc)->get(route('home'))->assertOk()->assertSee('aguarda conferência');
        $this->actingAs($rep)->get(route('home'))->assertOk()->assertDontSee('aguarda conferência');
    }

    public function test_escritorio_confirma_com_o_codigo_do_erp(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $this->criarCliente($empresa, 'C1', 'Outro');
        $cliente = $this->cadastrar($rep);

        $confirmar = fn (array $extra) => $this->actingAs($esc)->put(route('clientes.update', $cliente), $this->dados(['acao' => 'confirmar'] + $extra));

        $confirmar([])->assertSessionHasErrors('codigo');
        $confirmar(['codigo' => 'NOVO-00001'])->assertSessionHasErrors('codigo');
        $confirmar(['codigo' => 'C1'])->assertSessionHasErrors('codigo');
        $this->assertSame('pendente', $cliente->fresh()->situacao);

        $confirmar(['codigo' => 'C0099', 'nome' => 'Serralheria Nova Conferida'])
            ->assertSessionHasNoErrors()->assertRedirect(route('clientes.show', $cliente));

        $cliente->refresh();
        $this->assertSame('ativo', $cliente->situacao);
        $this->assertSame('C0099', $cliente->codigo);
        $this->assertSame('Serralheria Nova Conferida', $cliente->nome);
        $this->assertNotNull($cliente->conferido_em);

        // Depois de conferido é cliente do ERP: ninguém altera pelo app.
        $this->actingAs($esc)->get(route('clientes.edit', $cliente))->assertForbidden();
        $this->actingAs($esc)->delete(route('clientes.destroy', $cliente))->assertForbidden();
        $this->actingAs($rep)->get(route('clientes.edit', $cliente))->assertForbidden();
    }

    public function test_escritorio_salva_sem_confirmar_e_exclui_duplicado(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $cliente = $this->cadastrar($rep);

        $this->actingAs($esc)->put(route('clientes.update', $cliente), $this->dados(['cidade' => 'Bento Gonçalves']))->assertSessionHasNoErrors();
        $this->assertSame('Bento Gonçalves', $cliente->fresh()->cidade);
        $this->assertSame('pendente', $cliente->fresh()->situacao);

        $this->actingAs($esc)->delete(route('clientes.destroy', $cliente))->assertRedirect(route('clientes.pendentes'));
        $this->assertSame(0, Cliente::count());
    }

    public function test_cliente_pendente_com_documentos_nao_pode_ser_excluido(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $produto = $this->criarProduto($empresa);
        $cliente = $this->cadastrar($rep);

        $this->actingAs($rep)->post(route('orcamentos.store'), [
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1, 'desconto_percentual' => 0]],
        ]);

        $this->actingAs($esc)->from(route('clientes.edit', $cliente))->delete(route('clientes.destroy', $cliente))
            ->assertSessionHas('erro');
        $this->assertNotNull(Cliente::find($cliente->id));
    }

    public function test_cadastros_de_outra_empresa_ficam_isolados(): void
    {
        $a = $this->empresaComCadastro('a');
        $b = $this->empresaComCadastro('b');
        $repA = $this->criarUsuario($a);
        $escB = $this->criarUsuario($b, 'escritorio');
        $cliente = $this->cadastrar($repA);

        $this->actingAs($escB)->get(route('clientes.edit', $cliente))->assertNotFound();
        $this->actingAs($escB)->get(route('clientes.pendentes'))->assertOk()->assertDontSee($cliente->nome);

        // O mesmo CNPJ pode existir em empresas diferentes.
        $repB = $this->criarUsuario($b, 'representante', 'repb@teste.local');
        $this->actingAs($repB)->post(route('clientes.store'), $this->dados())->assertSessionHasNoErrors();
        $this->assertSame(2, Cliente::withoutGlobalScopes()->count());
    }

    public function test_importacao_com_o_mesmo_codigo_assume_o_cadastro_pendente(): void
    {
        Storage::fake('local');
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'clientes');
        $cliente = $this->cadastrar($rep);
        $cliente->update(['codigo' => 'C0777']); // código combinado com o ERP, ainda sem conferência

        $csv = "Código;Nome;CNPJ/CPF;Telefone;E-mail;Endereço;Cidade;UF\nC0777;Serralheria Nova Ltda;11.222.333/0001-44;(54) 3000-1111;a@b.com;Rua A;Garibaldi;RS\nC0778;Outro;22.333.444/0001-55;(54) 3000-2222;x@y.com;Rua B;Caxias;RS\n";
        $this->actingAs($esc)->post(route('importacoes.store'), [
            'perfil_importacao_id' => $perfil->id,
            'arquivo' => UploadedFile::fake()->createWithContent('clientes.csv', $csv),
        ]);
        $this->actingAs($esc)->post(route('importacoes.publicar', Importacao::firstOrFail()));

        $this->assertSame(2, Cliente::count(), 'Não duplica: atualiza o cadastro de campo.');
        $this->assertSame('ativo', $cliente->fresh()->situacao);
        $this->assertNotNull($cliente->fresh()->importado_em);
    }

    public function test_csv_de_pedidos_avisa_quando_o_cliente_e_novo(): void
    {
        $empresa = $this->empresaComCadastro();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $produto = $this->criarProduto($empresa, 'P9');
        $cliente = $this->cadastrar($rep);

        $this->actingAs($rep)->post(route('orcamentos.store'), [
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1, 'desconto_percentual' => 0]],
        ]);
        $this->actingAs($rep)->post(route('orcamentos.converter', Orcamento::firstOrFail()));

        $hoje = now()->toDateString();
        $csv = $this->actingAs($esc)->get(route('relatorios.pedidos', ['de' => $hoje, 'ate' => $hoje]))->assertOk()->streamedContent();

        $this->assertStringContainsString('Situação do cliente', $csv);
        $this->assertStringContainsString('Novo (cadastrar no ERP)', $csv);
    }
}
