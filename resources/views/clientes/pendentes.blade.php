@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Cadastros para conferir')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Cadastros para conferir</h1>
            <div class="suave">Clientes cadastrados em campo. Cadastre no ERP e informe o código aqui.</div>
        </div>
        <div class="acoes"><a class="btn" href="{{ route('clientes.index') }}">Clientes</a></div>
    </div>

    <div class="lista">
        @forelse($clientes as $c)
            <a href="{{ route('clientes.edit', $c) }}">
                <div>
                    <div class="titulo">{{ $c->nome }}</div>
                    <div class="detalhe">
                        {{ $c->documento ? Formato::documento($c->documento) : 'Sem documento' }}
                        @if($c->cidade) · {{ $c->cidade }}@if($c->uf)/{{ $c->uf }}@endif @endif
                    </div>
                    <div class="detalhe">{{ $c->cadastradoPor?->name ?? 'Usuário removido' }} · {{ Formato::data($c->created_at, true) }}</div>
                </div>
                <span class="suave">›</span>
            </a>
        @empty
            <div class="vazio">Nenhum cadastro aguardando conferência.</div>
        @endforelse
    </div>
@endsection
