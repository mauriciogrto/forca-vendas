@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', $orcamento->rotuloTipo().' nº '.$orcamento->numero)

@section('conteudo')
    @php
        $editavel = $podeAlterar && $orcamento->editavel();
        $arquivo = strtolower($orcamento->rotuloTipo()).'-'.$orcamento->numero.'.pdf';
    @endphp

    <div class="cabecalho-pagina">
        <div>
            <h1>{{ $orcamento->rotuloTipo() }} nº {{ $orcamento->numero }}</h1>
            @include('partials.selo-status', ['orcamento' => $orcamento])
        </div>
        <div class="acoes">
            <a class="btn" href="{{ route('orcamentos.pdf', $orcamento) }}" target="_blank" rel="noopener">Ver PDF</a>
            <button type="button" class="btn btn-primario" onclick="compartilharPdf('{{ route('orcamentos.pdf', $orcamento) }}', '{{ $arquivo }}')">Compartilhar</button>
        </div>
    </div>

    <div class="cartao">
        <dl class="dados">
            <dt>Cliente</dt>
            <dd>
                @if($orcamento->cliente)<a href="{{ route('clientes.show', $orcamento->cliente) }}">{{ $orcamento->cliente_nome }}</a>
                @else {{ $orcamento->cliente_nome }} <span class="selo">sem cadastro</span> @endif
            </dd>
            <dt>Representante</dt><dd>{{ $orcamento->representante->name ?? '—' }}</dd>
            <dt>Criado em</dt><dd>{{ Formato::data($orcamento->created_at, true) }}</dd>
            @if($orcamento->enviado_em)<dt>Enviado em</dt><dd>{{ Formato::data($orcamento->enviado_em, true) }}</dd>@endif
            @if($orcamento->fechado_em)<dt>Fechado em</dt><dd>{{ Formato::data($orcamento->fechado_em, true) }}</dd>@endif
            @if(! $orcamento->ehPedido())<dt>Validade</dt><dd>{{ Formato::data($orcamento->created_at->copy()->addDays($validade)) }}</dd>@endif
            <dt>Pagamento</dt><dd>{{ $orcamento->condicao_pagamento ?: '—' }}</dd>
        </dl>
        @if($orcamento->observacoes)
            <p style="margin-top:12px"><span class="rotulo">Observações</span><br>{!! nl2br(e($orcamento->observacoes)) !!}</p>
        @endif
    </div>

    <div class="cartao">
        <h2>Itens</h2>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Preço</th><th class="num">Desc.</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                    @foreach($orcamento->itens as $item)
                        <tr>
                            <td><strong>{{ $item->descricao }}</strong><br><small>Cód. {{ $item->codigo }}</small></td>
                            <td class="num">{{ Formato::quantidade($item->quantidade) }} {{ $item->unidade }}</td>
                            <td class="num">{{ Formato::preco($item->preco_unitario) }}</td>
                            <td class="num">{{ (float) $item->desconto_percentual > 0 ? Formato::percentual($item->desconto_percentual) : '—' }}</td>
                            <td class="num"><strong>{{ Formato::moeda($item->total) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="direita" style="margin:12px 0 0"><span class="rotulo">Total</span><br><span class="numero-grande">{{ Formato::moeda($orcamento->total) }}</span></p>
    </div>

    @if($podeAlterar && $orcamento->status !== 'cancelado')
        <div class="cartao">
            <h2>Ações</h2>
            <div class="acoes" style="display:flex;gap:8px;flex-wrap:wrap">
                @if($editavel)
                    <a class="btn" href="{{ route('orcamentos.edit', $orcamento) }}">Editar</a>

                    @if($orcamento->status === 'rascunho')
                        <form method="POST" action="{{ route('orcamentos.enviar', $orcamento) }}">@csrf
                            <button class="btn" type="submit">Marcar como enviado</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('orcamentos.converter', $orcamento) }}" onsubmit="return confirm('Converter em pedido? Depois disso o documento não pode mais ser editado.')">@csrf
                        <button class="btn btn-primario" type="submit">Converter em pedido</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('orcamentos.cancelar', $orcamento) }}" onsubmit="return confirm('Cancelar este documento?')">@csrf
                    <button class="btn btn-perigo" type="submit">Cancelar</button>
                </form>

                @if($orcamento->status === 'rascunho' && ! $orcamento->ehPedido())
                    <form method="POST" action="{{ route('orcamentos.destroy', $orcamento) }}" onsubmit="return confirm('Excluir este rascunho de vez?')">@csrf @method('DELETE')
                        <button class="btn btn-perigo" type="submit">Excluir rascunho</button>
                    </form>
                @endif
            </div>
            @if($orcamento->ehPedido() && $orcamento->status === 'fechado')
                <p class="ajuda" style="margin-top:10px">Este pedido existe só no app. O lançamento no ERP é feito pelo escritório, fora do sistema.</p>
            @endif
        </div>
    @endif
@endsection
