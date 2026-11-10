@extends('layouts.app')
@section('titulo', $orcamento->exists ? 'Editar orçamento '.$orcamento->numero : 'Novo orçamento')

@section('conteudo')
    <div class="cabecalho-pagina">
        <h1>{{ $orcamento->exists ? 'Editar orçamento nº '.$orcamento->numero : 'Novo orçamento' }}</h1>
    </div>

    @if($errors->any())
        <div class="alerta alerta-erro" role="alert">
            @foreach($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ $orcamento->exists ? route('orcamentos.update', $orcamento) : route('orcamentos.store') }}"
          x-data="orcamentoForm(@js($inicial), @js($descontoMaximo), @js(['clientes' => route('clientes.busca'), 'produtos' => route('produtos.busca')]))">
        @csrf
        @if($orcamento->exists) @method('PUT') @endif

        {{-- Cliente --}}
        <div class="cartao">
            <h2><span class="num-passo">1.</span>Cliente</h2>

            <input type="hidden" name="cliente_id" :value="clienteId ?? ''">
            <input type="hidden" name="cliente_nome" :value="clienteNome">

            <template x-if="clienteNome !== ''">
                <div class="lista" style="margin:0">
                    <div class="item">
                        <div>
                            <div class="titulo" x-text="clienteNome"></div>
                            <div class="detalhe" x-text="clienteId ? 'Cliente do cadastro' : 'Cliente sem cadastro (não vem do ERP)'"></div>
                        </div>
                        <button type="button" class="btn btn-pequeno" @click="trocarCliente()">Trocar</button>
                    </div>
                </div>
            </template>

            <div x-show="clienteNome === ''">
                <input type="search" x-model="buscaCliente" @input="buscarClientes()" placeholder="Buscar cliente por nome, código ou CNPJ" autocomplete="off">
                <div class="sugestoes" x-show="buscaCliente.trim().length >= 2" style="display:none">
                    <template x-for="c in sugestoesCliente" :key="c.id">
                        <button type="button" @click="escolherCliente(c)">
                            <span><strong x-text="c.nome"></strong><br><small x-text="(c.situacao === 'pendente' ? 'Novo, aguardando conferência' : 'Cód. ' + c.codigo) + (c.cidade ? ' · ' + c.cidade : '')"></small></span>
                        </button>
                    </template>
                    <button type="button" @click="clienteAvulso()">
                        <span>Usar “<strong x-text="buscaCliente.trim()"></strong>” sem cadastro</span>
                    </button>
                </div>
                <div class="ajuda">Se o cliente não aparece, ele ainda não está na planilha importada do ERP.
                    @if(auth()->user()->podeCadastrarCliente())
                        <a href="{{ route('clientes.create') }}">Cadastrar cliente novo</a> ou siga com o nome digitado.
                    @else
                        Você pode seguir com o nome digitado.
                    @endif
                </div>
            </div>
        </div>

        {{-- Produtos --}}
        <div class="cartao">
            <h2><span class="num-passo">2.</span>Produtos</h2>

            <input type="search" x-model="buscaProduto" @input="buscarProdutos()" placeholder="Buscar produto por descrição ou código" autocomplete="off">
            <div class="sugestoes" x-show="buscaProduto.trim().length >= 2" style="display:none">
                <template x-for="p in sugestoesProduto" :key="p.id">
                    <button type="button" @click="adicionarProduto(p)">
                        <span><strong x-text="p.descricao"></strong><br><small x-text="'Cód. ' + p.codigo + (p.estoque !== null ? ' · Estoque: ' + p.estoque : '')"></small></span>
                        <span x-text="moeda.format(p.preco)"></span>
                    </button>
                </template>
                <div class="vazio" style="padding:12px" x-show="!carregandoProduto && sugestoesProduto.length === 0"><small>Nenhum produto encontrado.</small></div>
            </div>

            <div style="margin-top:12px">
                <template x-for="(item, indice) in itens" :key="item.produto_id">
                    <div class="item-orc">
                        <input type="hidden" :name="`itens[${indice}][produto_id]`" :value="item.produto_id">

                        <div class="topo-item">
                            <div>
                                <strong x-text="item.descricao"></strong><br>
                                <small x-text="'Cód. ' + item.codigo + ' · ' + moeda.format(item.preco) + (item.unidade ? ' / ' + item.unidade : '')"></small>
                            </div>
                            <button type="button" class="btn btn-pequeno btn-perigo" @click="remover(indice)" aria-label="Remover item">Remover</button>
                        </div>

                        <div class="campos">
                            <div>
                                <label>Quantidade</label>
                                <div class="passo-qtd">
                                    <button type="button" aria-label="Diminuir quantidade" @click="item.quantidade = Math.max(0, (Number(item.quantidade) || 0) - 1)">−</button>
                                    <input type="number" inputmode="decimal" min="0" step="any" :name="`itens[${indice}][quantidade]`" x-model.number="item.quantidade">
                                    <button type="button" aria-label="Aumentar quantidade" @click="item.quantidade = (Number(item.quantidade) || 0) + 1">+</button>
                                </div>
                            </div>
                            <div>
                                <label x-text="'Desconto % (máx. ' + descontoMaximo + ')'"></label>
                                <input type="number" inputmode="decimal" min="0" :max="descontoMaximo" step="any" :name="`itens[${indice}][desconto_percentual]`" x-model.number="item.desconto">
                            </div>
                            <div>
                                <label>Total</label>
                                <div style="padding:11px 0;font-weight:600" x-text="moeda.format(totalItem(item))"></div>
                            </div>
                        </div>
                        <div class="erro" x-show="descontoInvalido(item)" style="display:none" x-text="'Desconto acima do permitido (' + descontoMaximo + '%).'"></div>
                    </div>
                </template>

                <div class="suave" x-show="itens.length === 0">Busque e toque num produto para adicioná-lo.</div>
            </div>
        </div>

        {{-- Condições --}}
        <div class="cartao">
            <h2><span class="num-passo">3.</span>Condições</h2>
            <div class="campo">
                <label for="condicao">Condição de pagamento</label>
                <input id="condicao" type="text" name="condicao_pagamento" maxlength="255" value="{{ old('condicao_pagamento', $inicial['condicao']) }}" placeholder="Ex.: 30/60/90 dias, à vista">
            </div>
            <div class="campo">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" maxlength="2000" placeholder="Prazo de entrega, frete, detalhes combinados">{{ old('observacoes', $inicial['observacoes']) }}</textarea>
            </div>
        </div>

        <div class="total-barra">
            <div><span class="rotulo">Total</span><br><strong x-text="moeda.format(total)"></strong></div>
            <button type="submit" class="btn btn-primario" :disabled="!podeSalvar">Salvar</button>
        </div>
    </form>
@endsection
