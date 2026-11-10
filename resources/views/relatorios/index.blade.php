@extends('layouts.app')
@section('titulo', 'Relatórios')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Relatórios</h1>
            <div class="suave">Para o escritório conferir o que foi fechado em campo.</div>
        </div>
    </div>

    @if($errors->any())
        <div class="alerta alerta-erro">@foreach($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach</div>
    @endif

    <form class="cartao" method="GET" action="{{ route('relatorios.pedidos') }}">
        <h2>Pedidos fechados (CSV)</h2>
        <p class="suave" style="margin-top:0">Um item por linha, pronto para abrir no Excel e lançar no ERP. Considera pedidos marcados como fechados no período.</p>

        <div class="grade-2">
            <div class="campo">
                <label for="de">De</label>
                <input id="de" type="date" name="de" required value="{{ old('de', now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="campo">
                <label for="ate">Até</label>
                <input id="ate" type="date" name="ate" required value="{{ old('ate', now()->toDateString()) }}">
            </div>
        </div>

        <button class="btn btn-primario" type="submit">Baixar CSV</button>
    </form>
@endsection
