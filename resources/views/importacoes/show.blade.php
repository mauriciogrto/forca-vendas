@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Importação')

@section('conteudo')
    @php
        $validos = $importacao->novos + $importacao->atualizados;
        $entidadeRotulo = \App\Services\Importacao\Campos::ENTIDADES[$importacao->entidade] ?? $importacao->entidade;
        $resumo = $importacao->resumo ?? [];
    @endphp

    <div class="cabecalho-pagina">
        <div>
            <h1>{{ $importacao->arquivo_original }}</h1>
            <div class="suave">
                {{ $entidadeRotulo }} · perfil “{{ $importacao->perfil->nome ?? 'excluído' }}” ·
                enviada em {{ Formato::data($importacao->created_at, true) }} por {{ $importacao->usuario->name ?? '—' }}
            </div>
        </div>
    </div>

    @if($errors->has('publicar'))
        <div class="alerta alerta-erro">{{ $errors->first('publicar') }}</div>
    @endif

    <div class="grade-3" style="margin-bottom:14px">
        <div class="cartao" style="margin:0"><div class="rotulo">Novos</div><div class="numero-grande">{{ $importacao->novos }}</div></div>
        <div class="cartao" style="margin:0"><div class="rotulo">Atualizados</div><div class="numero-grande">{{ $importacao->atualizados }}</div></div>
        <div class="cartao" style="margin:0"><div class="rotulo">Com erro (ignorados)</div><div class="numero-grande" style="{{ $importacao->com_erro ? 'color:var(--perigo)' : '' }}">{{ $importacao->com_erro }}</div></div>
    </div>

    @if($importacao->status === 'previa')
        <div class="alerta alerta-aviso">
            Prévia: nada foi alterado ainda. Ao publicar, {{ $validos }} linha(s) válida(s) entram no app
            @if($importacao->com_erro) e {{ $importacao->com_erro }} linha(s) com erro ficam de fora @endif.
            @if($importacao->perfil?->desativar_ausentes && $importacao->entidade === 'produtos')
                Produtos que <strong>não</strong> estão nesta planilha serão marcados como inativos.
            @endif
        </div>

        <div class="cartao" style="display:flex;gap:8px;flex-wrap:wrap">
            <form method="POST" action="{{ route('importacoes.publicar', $importacao) }}">@csrf
                <button class="btn btn-primario" type="submit" @disabled($validos === 0)>Publicar no app</button>
            </form>
            <form method="POST" action="{{ route('importacoes.descartar', $importacao) }}">@csrf
                <button class="btn" type="submit">Descartar</button>
            </form>
        </div>
    @elseif($importacao->status === 'publicada')
        <div class="alerta alerta-ok">Publicada em {{ Formato::data($importacao->publicada_em, true) }}.</div>
    @else
        <div class="alerta">Importação descartada. Nada foi alterado.</div>
    @endif

    @if(! empty($resumo['amostra']))
        <div class="cartao">
            <h2>Primeiras linhas lidas</h2>
            <div class="tabela-rolagem">
                <table class="tabela">
                    <thead>
                        <tr>@foreach($resumo['campos'] as $campo)<th>{{ $campos[$campo]['rotulo'] ?? $campo }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach($resumo['amostra'] as $linha)
                            <tr>
                                @foreach($resumo['campos'] as $campo)
                                    <td class="{{ in_array($campo, ['preco', 'estoque']) ? 'num' : '' }}">
                                        @if(in_array($campo, ['preco', 'estoque']) && isset($linha[$campo]))
                                            {{ number_format((float) $linha[$campo], $campo === 'preco' ? 2 : 3, ',', '.') }}
                                        @else
                                            {{ $linha[$campo] ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ajuda" style="margin-top:8px">Confira se preço e estoque estão nas colunas certas antes de publicar.</p>
        </div>
    @endif

    @if(! empty($resumo['erros']))
        <div class="cartao">
            <h2>Linhas com erro @if($importacao->com_erro > count($resumo['erros'])) <small>(mostrando as primeiras {{ count($resumo['erros']) }})</small> @endif</h2>
            <div class="tabela-rolagem">
                <table class="tabela">
                    <thead><tr><th>Linha</th><th>Problema</th></tr></thead>
                    <tbody>
                        @foreach($resumo['erros'] as $erro)
                            <tr><td class="nowrap">{{ $erro['linha'] }}</td><td>{{ $erro['mensagem'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
