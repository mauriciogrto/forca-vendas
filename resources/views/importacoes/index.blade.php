@extends('layouts.app')
@use('App\Support\Formato')
@section('titulo', 'Importações')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Importações</h1>
            <div class="suave">Planilhas exportadas do ERP que alimentam clientes, produtos, preços e estoque.</div>
        </div>
        <div class="acoes">
            <a class="btn" href="{{ route('perfis.index') }}">Perfis</a>
            <a class="btn btn-primario" href="{{ route('importacoes.create') }}">Nova importação</a>
        </div>
    </div>

    @if($perfis === 0)
        <div class="alerta alerta-aviso">Antes de importar, crie um <a href="{{ route('perfis.create') }}">perfil de importação</a> para o seu ERP.</div>
    @endif

    <div class="lista">
        @forelse($importacoes as $i)
            <a href="{{ route('importacoes.show', $i) }}">
                <div>
                    <div class="titulo">{{ $i->arquivo_original }}</div>
                    <div class="detalhe">
                        {{ \App\Services\Importacao\Campos::ENTIDADES[$i->entidade] ?? $i->entidade }}
                        · {{ Formato::data($i->created_at, true) }} · {{ $i->usuario->name ?? '—' }}
                    </div>
                </div>
                <div class="direita">
                    @php
                        $classe = ['previa' => 'selo-aviso', 'publicada' => 'selo-ok', 'descartada' => ''][$i->status] ?? '';
                        $rotulo = ['previa' => 'Aguardando publicação', 'publicada' => 'Publicada', 'descartada' => 'Descartada'][$i->status] ?? $i->status;
                    @endphp
                    <span class="selo {{ $classe }}">{{ $rotulo }}</span>
                    @if($i->status !== 'descartada')
                        <div class="detalhe">{{ $i->novos }} novos · {{ $i->atualizados }} atualizados @if($i->com_erro) · {{ $i->com_erro }} com erro @endif</div>
                    @endif
                </div>
            </a>
        @empty
            <div class="vazio">Nenhuma importação ainda.</div>
        @endforelse
    </div>

    {{ $importacoes->links() }}
@endsection
