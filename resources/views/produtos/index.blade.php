@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Produtos')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Produtos</h1>
            @include('partials.carimbo', ['quando' => $atualizadoEm, 'rotulo' => 'Preços e estoque'])
        </div>
    </div>

    <form class="pesquisa" method="GET" action="{{ route('produtos.index') }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="Descrição, código ou categoria" autocomplete="off">
        <button class="btn btn-primario" type="submit">Buscar</button>
    </form>

    <div class="lista">
        @forelse($produtos as $p)
            <a href="{{ route('produtos.show', $p) }}">
                <div>
                    <div class="titulo">{{ $p->descricao }}</div>
                    <div class="detalhe">
                        Cód. {{ $p->codigo }}@if($p->categoria) · {{ $p->categoria }}@endif
                        @if($p->estoque !== null) · Estoque: {{ Formato::quantidade($p->estoque) }} {{ $p->unidade }} @endif
                    </div>
                </div>
                <div class="titulo nowrap">{{ Formato::preco($p->preco) }}</div>
            </a>
        @empty
            <div class="vazio">{{ $q !== '' ? 'Nenhum produto encontrado para essa busca.' : 'Nenhum produto importado ainda.' }}</div>
        @endforelse
    </div>

    {{ $produtos->links() }}
@endsection
