<?php

namespace Tests\Feature;

use App\Models\Orcamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenario;
use Tests\TestCase;

class OrcamentoTest extends TestCase
{
    use CriaCenario;
    use RefreshDatabase;

    private function dados(int $produtoId, array $item = []): array
    {
        return [
            'cliente_nome' => 'Cliente Avulso',
            'condicao_pagamento' => '30 dias',
            'itens' => [array_merge(['produto_id' => $produtoId, 'quantidade' => 3, 'desconto_percentual' => 10], $item)],
        ];
    }

    public function test_cria_orcamento_com_preco_do_cadastro_e_total_correto(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa, 'P1', 10.00);

        // O navegador tenta mandar um preço falso: o servidor deve ignorar.
        $dados = $this->dados($produto->id, ['preco' => 0.01, 'preco_unitario' => 0.01]);

        $this->actingAs($rep)->post(route('orcamentos.store'), $dados)->assertSessionHasNoErrors()->assertRedirect();

        $orcamento = Orcamento::with('itens')->firstOrFail();
        $this->assertSame(1, (int) $orcamento->numero);
        $this->assertSame('orcamento', $orcamento->tipo);
        $this->assertSame('rascunho', $orcamento->status);
        $this->assertEquals(27.00, (float) $orcamento->total);
        $this->assertEquals(10.00, (float) $orcamento->itens->first()->preco_unitario);
        $this->assertSame('P1', $orcamento->itens->first()->codigo);
    }

    public function test_numeracao_e_sequencial_por_empresa(): void
    {
        $a = $this->criarEmpresa('a');
        $b = $this->criarEmpresa('b');
        $repA = $this->criarUsuario($a);
        $repB = $this->criarUsuario($b);
        $pa = $this->criarProduto($a);
        $pb = $this->criarProduto($b);

        $this->actingAs($repA)->post(route('orcamentos.store'), $this->dados($pa->id));
        $this->actingAs($repA)->post(route('orcamentos.store'), $this->dados($pa->id));
        $this->actingAs($repB)->post(route('orcamentos.store'), $this->dados($pb->id));

        $this->assertSame([1, 2], Orcamento::withoutGlobalScopes()->where('empresa_id', $a->id)->orderBy('numero')->pluck('numero')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([1], Orcamento::withoutGlobalScopes()->where('empresa_id', $b->id)->pluck('numero')->map(fn ($n) => (int) $n)->all());
    }

    public function test_desconto_acima_do_maximo_e_recusado(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa);

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id, ['desconto_percentual' => 25]))
            ->assertSessionHasErrors('itens.0.desconto_percentual');

        $this->assertSame(0, Orcamento::count());
    }

    public function test_recusa_orcamento_sem_itens_ou_com_quantidade_zero(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa);

        $this->actingAs($rep)->post(route('orcamentos.store'), ['cliente_nome' => 'X', 'itens' => []])->assertSessionHasErrors('itens');
        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id, ['quantidade' => 0]))->assertSessionHasErrors('itens.0.quantidade');
    }

    public function test_nao_aceita_produto_de_outra_empresa_nem_inativo(): void
    {
        $a = $this->criarEmpresa('a');
        $b = $this->criarEmpresa('b');
        $rep = $this->criarUsuario($a);
        $deOutra = $this->criarProduto($b, 'X1');
        $inativo = $this->criarProduto($a, 'X2', 5, ['ativo' => false]);

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($deOutra->id))->assertSessionHasErrors('itens.0.produto_id');
        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($inativo->id))->assertSessionHasErrors('itens.0.produto_id');
    }

    public function test_fluxo_enviar_converter_em_pedido_e_bloqueio_de_edicao(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa);

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        $orcamento = Orcamento::firstOrFail();

        $this->actingAs($rep)->get(route('orcamentos.show', $orcamento))->assertOk()->assertSee('Cliente Avulso');
        $this->actingAs($rep)->get(route('orcamentos.edit', $orcamento))->assertOk();

        $this->actingAs($rep)->post(route('orcamentos.enviar', $orcamento))->assertRedirect();
        $this->assertSame('enviado', $orcamento->fresh()->status);

        $this->actingAs($rep)->post(route('orcamentos.converter', $orcamento))->assertRedirect();
        $orcamento = $orcamento->fresh();
        $this->assertSame('pedido', $orcamento->tipo);
        $this->assertSame('fechado', $orcamento->status);
        $this->assertNotNull($orcamento->fechado_em);

        // Pedido fechado não pode mais ser editado.
        $this->actingAs($rep)->put(route('orcamentos.update', $orcamento), $this->dados($produto->id))->assertStatus(403);
    }

    public function test_representante_nao_ve_nem_altera_orcamento_de_outro(): void
    {
        $empresa = $this->criarEmpresa();
        $dono = $this->criarUsuario($empresa, 'representante', 'dono@teste.local');
        $outro = $this->criarUsuario($empresa, 'representante', 'outro@teste.local');
        $escritorio = $this->criarUsuario($empresa, 'escritorio');
        $produto = $this->criarProduto($empresa);

        $this->actingAs($dono)->post(route('orcamentos.store'), $this->dados($produto->id));
        $orcamento = Orcamento::firstOrFail();

        $this->actingAs($outro)->get(route('orcamentos.show', $orcamento))->assertForbidden();
        $this->actingAs($outro)->post(route('orcamentos.cancelar', $orcamento))->assertForbidden();

        // O escritório consulta, mas não altera o documento de outra pessoa.
        $this->actingAs($escritorio)->get(route('orcamentos.show', $orcamento))->assertOk();
        $this->actingAs($escritorio)->post(route('orcamentos.cancelar', $orcamento))->assertForbidden();
    }

    public function test_cancelar_e_excluir_rascunho(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa);

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        [$primeiro, $segundo] = Orcamento::orderBy('numero')->get()->all();

        $this->actingAs($rep)->post(route('orcamentos.cancelar', $primeiro))->assertRedirect();
        $this->assertSame('cancelado', $primeiro->fresh()->status);

        $this->actingAs($rep)->delete(route('orcamentos.destroy', $segundo))->assertRedirect(route('orcamentos.index'));
        $this->assertNull(Orcamento::find($segundo->id));
    }

    public function test_pdf_e_gerado(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $produto = $this->criarProduto($empresa);

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        $orcamento = Orcamento::firstOrFail();

        $resposta = $this->actingAs($rep)->get(route('orcamentos.pdf', $orcamento))->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));
    }

    public function test_relatorio_csv_traz_so_pedidos_fechados_no_periodo(): void
    {
        $empresa = $this->criarEmpresa();
        $rep = $this->criarUsuario($empresa);
        $esc = $this->criarUsuario($empresa, 'escritorio');
        $produto = $this->criarProduto($empresa, 'P9');

        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        $this->actingAs($rep)->post(route('orcamentos.store'), $this->dados($produto->id));
        [$fechado, $aberto] = Orcamento::orderBy('numero')->get()->all();
        $this->actingAs($rep)->post(route('orcamentos.converter', $fechado));

        $hoje = now()->toDateString();
        $resposta = $this->actingAs($esc)->get(route('relatorios.pedidos', ['de' => $hoje, 'ate' => $hoje]))->assertOk();
        $csv = $resposta->streamedContent();

        $this->assertStringContainsString('Pedido;', $csv);
        $this->assertStringContainsString('P9', $csv);
        $this->assertStringContainsString('27,00', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1, 'Cabeçalho + 1 linha de item do pedido fechado.');
    }
}
