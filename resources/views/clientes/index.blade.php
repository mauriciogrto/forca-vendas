@extends('layouts.app')
@section('titulo', 'Clientes')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Clientes</h1>
            @include('partials.carimbo', ['quando' => $atualizadoEm, 'rotulo' => 'Clientes'])
        </div>
        <div class="acoes">
            @if($pendentes > 0)
                <a class="btn" href="{{ route('clientes.pendentes') }}">Conferir cadastros ({{ $pendentes }})</a>
            @endif
            @if(auth()->user()->podeCadastrarCliente())
                <a class="btn btn-primario" href="{{ route('clientes.create') }}">Novo cliente</a>
            @endif
        </div>
    </div>

    <form class="pesquisa" method="GET" action="{{ route('clientes.index') }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="Nome, código, CNPJ/CPF ou cidade" autocomplete="off">
        <button class="btn btn-primario" type="submit">Buscar</button>
    </form>

    <div class="lista">
        @forelse($clientes as $c)
            <a href="{{ route('clientes.show', $c) }}">
                <div>
                    <div class="titulo">{{ $c->nome }}</div>
                    <div class="detalhe">Cód. {{ $c->codigo }}@if($c->cidade) · {{ $c->cidade }}@if($c->uf)/{{ $c->uf }}@endif @endif</div>
                </div>
                @if($c->estaPendente())<span class="selo selo-aviso">Pendente</span>@else<span class="suave">›</span>@endif
            </a>
        @empty
            <div class="vazio">{{ $q !== '' ? 'Nenhum cliente encontrado para essa busca.' : 'Nenhum cliente importado ainda.' }}</div>
        @endforelse
    </div>

    {{ $clientes->links() }}
@endsection
