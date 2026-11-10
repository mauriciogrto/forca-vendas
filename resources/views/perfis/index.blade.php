@extends('layouts.app')
@section('titulo', 'Perfis de importação')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Perfis de importação</h1>
            <div class="suave">Cada relatório do ERP vira um perfil: qual coluna da planilha alimenta qual campo.</div>
        </div>
        <div class="acoes">
            <a class="btn btn-primario" href="{{ route('perfis.create') }}">Novo perfil</a>
        </div>
    </div>

    <div class="lista">
        @forelse($perfis as $p)
            <a href="{{ route('perfis.edit', $p) }}">
                <div>
                    <div class="titulo">{{ $p->nome }}</div>
                    <div class="detalhe">{{ \App\Services\Importacao\Campos::ENTIDADES[$p->entidade] ?? $p->entidade }} · cabeçalho na linha {{ $p->linha_cabecalho }} · {{ count($p->mapeamento ?? []) }} coluna(s) mapeada(s)</div>
                </div>
                <span class="suave">›</span>
            </a>
        @empty
            <div class="vazio">Nenhum perfil ainda.</div>
        @endforelse
    </div>
@endsection
