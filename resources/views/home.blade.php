@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Início')

@section('conteudo')
    @php $usuario = auth()->user(); @endphp

    <div class="cabecalho-pagina">
        <div>
            <h1>Olá, {{ \Illuminate\Support\Str::of($usuario->name)->explode(' ')->first() }}</h1>
            <div class="suave">{{ $usuario->veTodos() ? 'Resumo da empresa' : 'Seu resumo' }}</div>
        </div>
        <div class="acoes">
            <a class="btn btn-primario" href="{{ route('orcamentos.create') }}">Novo orçamento</a>
        </div>
    </div>

    @if($pendentes > 0)
        <div class="alerta alerta-aviso">
            {{ $pendentes }} {{ $pendentes === 1 ? 'cliente cadastrado em campo aguarda' : 'clientes cadastrados em campo aguardam' }} conferência.
            <a href="{{ route('clientes.pendentes') }}">Conferir agora</a>
        </div>
    @endif

    <div class="grade-3" style="margin-bottom:14px">
        <div class="tile">
            <div class="rotulo">Orçamentos em aberto</div>
            <div class="numero-grande">{{ $resumo['orcamentos_abertos'] }}</div>
        </div>
        <div class="tile">
            <div class="rotulo">Pedidos no mês</div>
            <div class="numero-grande">{{ $resumo['pedidos_mes'] }}</div>
        </div>
        <div class="tile">
            <div class="rotulo">Vendido no mês</div>
            <div class="numero-grande">{{ Formato::moeda($resumo['total_mes']) }}</div>
        </div>
    </div>

    <div class="cartao">
        <h2>Dados do ERP</h2>
        @include('partials.carimbo', ['quando' => $carimbos['clientes'], 'rotulo' => 'Clientes'])
        @include('partials.carimbo', ['quando' => $carimbos['produtos'], 'rotulo' => 'Produtos, preços e estoque'])
        @if($usuario->podeImportar())
            <p style="margin-top:10px"><a class="btn btn-pequeno" href="{{ route('importacoes.create') }}">Atualizar com nova planilha</a></p>
        @endif
    </div>

    <h2>Últimos documentos</h2>
    <div class="lista">
        @forelse($recentes as $o)
            <a href="{{ route('orcamentos.show', $o) }}">
                <div>
                    <div class="titulo">Nº {{ $o->numero }} · {{ $o->cliente_nome }}</div>
                    <div class="detalhe">{{ Formato::data($o->created_at, true) }}</div>
                </div>
                <div class="direita">
                    <div class="titulo nowrap">{{ Formato::moeda($o->total) }}</div>
                    @include('partials.selo-status', ['orcamento' => $o])
                </div>
            </a>
        @empty
            <div class="vazio">Nenhum orçamento ainda. Toque em “Novo orçamento” para começar.</div>
        @endforelse
    </div>
@endsection
