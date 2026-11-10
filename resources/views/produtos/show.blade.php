@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', $produto->descricao)

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>{{ $produto->descricao }}</h1>
            <div class="suave">Código {{ $produto->codigo }}</div>
        </div>
    </div>

    <div class="cartao">
        <dl class="dados">
            <dt>Preço</dt><dd><strong>{{ Formato::preco($produto->preco) }}</strong>@if($produto->unidade) / {{ $produto->unidade }}@endif</dd>
            <dt>Estoque</dt><dd>{{ $produto->estoque !== null ? Formato::quantidade($produto->estoque).' '.$produto->unidade : 'Não informado' }}</dd>
            <dt>Categoria</dt><dd>{{ $produto->categoria ?: '—' }}</dd>
            <dt>Situação</dt><dd>{!! $produto->ativo ? '<span class="selo selo-ok">Ativo</span>' : '<span class="selo selo-perigo">Inativo</span>' !!}</dd>
        </dl>
        <p class="carimbo" style="margin-top:10px">Preço e estoque vindos do ERP, atualizados em {{ Formato::data($produto->importado_em, true) }}. Confirme com o escritório em negociações grandes.</p>
    </div>
@endsection
