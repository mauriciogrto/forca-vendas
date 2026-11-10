@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Orçamentos e pedidos')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Orçamentos e pedidos</h1>
            <div class="suave">{{ auth()->user()->veTodos() ? 'De todos os representantes' : 'Os seus documentos' }}</div>
        </div>
        <div class="acoes">
            <a class="btn btn-primario" href="{{ route('orcamentos.create') }}">Novo orçamento</a>
        </div>
    </div>

    <form class="cartao" method="GET" action="{{ route('orcamentos.index') }}">
        <div class="campo">
            <input type="search" name="q" value="{{ $q }}" placeholder="Nome do cliente ou número" autocomplete="off">
        </div>
        <div class="grade-3">
            <select name="tipo" aria-label="Tipo">
                <option value="">Orçamentos e pedidos</option>
                <option value="orcamento" @selected($tipo === 'orcamento')>Só orçamentos</option>
                <option value="pedido" @selected($tipo === 'pedido')>Só pedidos</option>
            </select>
            <select name="status" aria-label="Situação">
                <option value="">Qualquer situação</option>
                @foreach(\App\Models\Orcamento::STATUS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <button class="btn btn-primario" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="lista">
        @forelse($orcamentos as $o)
            <a href="{{ route('orcamentos.show', $o) }}">
                <div>
                    <div class="titulo">Nº {{ $o->numero }} · {{ $o->cliente_nome }}</div>
                    <div class="detalhe">
                        {{ Formato::data($o->created_at, true) }}
                        @if(auth()->user()->veTodos() && $o->representante) · {{ $o->representante->name }} @endif
                    </div>
                </div>
                <div class="direita">
                    <div class="titulo nowrap">{{ Formato::moeda($o->total) }}</div>
                    @include('partials.selo-status', ['orcamento' => $o])
                </div>
            </a>
        @empty
            <div class="vazio">Nenhum documento encontrado.</div>
        @endforelse
    </div>

    {{ $orcamentos->links() }}
@endsection
