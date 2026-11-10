<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Importacao;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCenario;
use Tests\TestCase;

class ImportacaoTest extends TestCase
{
    use CriaCenario;
    use RefreshDatabase;

    private function enviar($usuario, $perfil, string $conteudo, string $nome = 'produtos.csv')
    {
        return $this->actingAs($usuario)->post(route('importacoes.store'), [
            'perfil_importacao_id' => $perfil->id,
            'arquivo' => UploadedFile::fake()->createWithContent($nome, $conteudo),
        ]);
    }

    public function test_previa_nao_grava_e_publicar_cria_e_atualiza_produtos(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'produtos');
        $this->criarProduto($empresa, 'A1', 5.00, ['descricao' => 'Antigo']);

        $csv = "Código;Descrição;Unidade;Grupo;Preço;Estoque\nA1;Parafuso novo;UN;Fixação;R\$ 1.234,50;10\nB2;Porca;UN;Fixação;2,10;1.500\nC3;Sem preço;UN;Fixação;;5\n";

        $resposta = $this->enviar($esc, $perfil, $csv);
        $importacao = Importacao::firstOrFail();
        $resposta->assertRedirect(route('importacoes.show', $importacao));

        $this->assertSame('previa', $importacao->status);
        $this->assertSame(1, (int) $importacao->novos);
        $this->assertSame(1, (int) $importacao->atualizados);
        $this->assertSame(1, (int) $importacao->com_erro);
        $this->assertSame(1, Produto::count(), 'A prévia não pode gravar nada.');

        $this->actingAs($esc)->get(route('importacoes.show', $importacao))->assertOk()->assertSee('Linhas com erro');

        $this->actingAs($esc)->post(route('importacoes.publicar', $importacao))->assertRedirect();
        $this->assertSame('publicada', $importacao->fresh()->status);

        $this->assertSame(2, Produto::count());
        $this->assertEquals(1234.50, (float) Produto::where('codigo', 'A1')->first()->preco);
        $this->assertSame('Parafuso novo', Produto::where('codigo', 'A1')->first()->descricao);
        $this->assertEquals(1500, (float) Produto::where('codigo', 'B2')->first()->estoque);
    }

    public function test_descartar_nao_altera_nada(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'produtos');

        $this->enviar($esc, $perfil, "Código;Descrição;Preço;Unidade;Grupo;Estoque\nA1;X;1,00;UN;Geral;10\n")->assertSessionHasNoErrors();
        $importacao = Importacao::firstOrFail();

        $this->actingAs($esc)->post(route('importacoes.descartar', $importacao))->assertRedirect(route('importacoes.index'));
        $this->assertSame('descartada', $importacao->fresh()->status);
        $this->assertSame(0, Produto::count());
    }

    public function test_desativar_ausentes_inativa_produtos_fora_da_planilha(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'produtos', true);
        $this->criarProduto($empresa, 'FICA', 1);
        $this->criarProduto($empresa, 'SAI', 1);

        $this->enviar($esc, $perfil, "Código;Descrição;Preço;Unidade;Grupo;Estoque\nFICA;Fica;3,00;UN;Geral;10\n")->assertSessionHasNoErrors();
        $importacao = Importacao::firstOrFail();
        $this->actingAs($esc)->post(route('importacoes.publicar', $importacao));

        $this->assertTrue((bool) Produto::where('codigo', 'FICA')->first()->ativo);
        $this->assertFalse((bool) Produto::where('codigo', 'SAI')->first()->ativo);
    }

    public function test_importa_clientes(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'clientes');

        $csv = "Código;Nome;CNPJ/CPF;Telefone;E-mail;Endereço;Cidade;UF\nC1;Cliente Um;11.222.333/0001-44;(54) 3000-0000;a@b.com;Rua A;Garibaldi;rs\n";
        $this->enviar($esc, $perfil, $csv, 'clientes.csv');
        $this->actingAs($esc)->post(route('importacoes.publicar', Importacao::firstOrFail()));

        $cliente = Cliente::where('codigo', 'C1')->firstOrFail();
        $this->assertSame('Cliente Um', $cliente->nome);
        $this->assertSame('Garibaldi', $cliente->cidade);
    }

    public function test_arquivo_de_formato_invalido_e_recusado(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'produtos');

        $this->enviar($esc, $perfil, 'qualquer coisa', 'planilha.pdf')->assertSessionHasErrors('arquivo');
        $this->assertSame(0, Importacao::count());
    }

    public function test_planilha_sem_a_coluna_obrigatoria_mostra_erro_claro(): void
    {
        Storage::fake('local');
        $empresa = $this->criarEmpresa();
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $perfil = $this->criarPerfilImportacao($empresa, 'produtos');

        $this->enviar($esc, $perfil, "Código;Descrição\nA1;X\n")->assertSessionHasErrors('arquivo');
        $this->assertSame(0, Importacao::count());
    }

    public function test_importacao_de_uma_empresa_nao_aparece_para_outra(): void
    {
        Storage::fake('local');
        $a = $this->criarEmpresa('a');
        $b = $this->criarEmpresa('b');
        $escA = $this->criarUsuario($a, 'escritorio');
        $escB = $this->criarUsuario($b, 'escritorio');
        $perfilA = $this->criarPerfilImportacao($a, 'produtos');

        $this->enviar($escA, $perfilA, "Código;Descrição;Preço;Unidade;Grupo;Estoque\nA1;X;1,00;UN;Geral;10\n")->assertSessionHasNoErrors();
        $importacao = Importacao::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($escB)->get(route('importacoes.show', $importacao))->assertNotFound();
    }
}
