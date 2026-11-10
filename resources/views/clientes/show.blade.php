@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', $cliente->nome)

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>{{ $cliente->nome }}</h1>
            <div class="suave">{{ $cliente->estaPendente() ? 'Código provisório '.$cliente->codigo : 'Código '.$cliente->codigo }}</div>
        </div>
        <div class="acoes">
            @php $u = auth()->user(); @endphp
            @if($cliente->estaPendente() && ($u->podeConferirClientes() || ((int) $cliente->cadastrado_por === (int) $u->id && $u->podeCadastrarCliente())))
                <a class="btn" href="{{ route('clientes.edit', $cliente) }}">{{ $u->podeConferirClientes() ? 'Conferir cadastro' : 'Corrigir' }}</a>
            @endif
            <a class="btn btn-primario" href="{{ route('orcamentos.create', ['cliente' => $cliente->id]) }}">Novo orçamento</a>
        </div>
    </div>

    @if($cliente->estaPendente())
        <div class="alerta alerta-aviso">
            Cadastro feito em campo{{ $cliente->cadastradoPor ? ' por '.$cliente->cadastradoPor->name : '' }}, aguardando conferência do escritório. O código ainda é provisório.
        </div>
    @endif

    <div class="cartao">
        <dl class="dados">
            <dt>CNPJ/CPF</dt><dd>{{ $cliente->documento ? Formato::documento($cliente->documento) : '—' }}</dd>
            <dt>Telefone</dt><dd>@if($cliente->telefone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $cliente->telefone) }}">{{ $cliente->telefone }}</a>@else — @endif</dd>
            <dt>E-mail</dt><dd>@if($cliente->email)<a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a>@else — @endif</dd>
            <dt>Endereço</dt><dd>{{ $cliente->endereco ?: '—' }}</dd>
            <dt>Cidade</dt><dd>{{ $cliente->cidade ? $cliente->cidade.($cliente->uf ? '/'.$cliente->uf : '') : '—' }}</dd>
        </dl>
        @if($cliente->estaPendente())
            <p class="carimbo" style="margin-top:10px">Cadastrado em {{ Formato::data($cliente->created_at, true) }}.</p>
        @elseif($cliente->importado_em)
            <p class="carimbo" style="margin-top:10px">Cadastro vindo do ERP, atualizado em {{ Formato::data($cliente->importado_em, true) }}. Alterações são feitas no ERP.</p>
        @else
            <p class="carimbo" style="margin-top:10px">Cadastro feito em campo e conferido em {{ Formato::data($cliente->conferido_em, true) }}. Alterações são feitas no ERP.</p>
        @endif
    </div>

    <h2>Orçamentos e pedidos feitos no app</h2>
    <div class="lista">
        @forelse($orcamentos as $o)
            <a href="{{ route('orcamentos.show', $o) }}">
                <div>
                    <div class="titulo">Nº {{ $o->numero }}</div>
                    <div class="detalhe">{{ Formato::data($o->created_at) }}</div>
                </div>
                <div class="direita">
                    <div class="titulo nowrap">{{ Formato::moeda($o->total) }}</div>
                    @include('partials.selo-status', ['orcamento' => $o])
                </div>
            </a>
        @empty
            <div class="vazio">Nenhum documento para este cliente.</div>
        @endforelse
    </div>
@endsection
