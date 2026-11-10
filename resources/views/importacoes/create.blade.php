@extends('layouts.app')
@section('titulo', 'Nova importação')

@section('conteudo')
    <div class="cabecalho-pagina"><h1>Nova importação</h1></div>

    @if($perfis->isEmpty())
        <div class="alerta alerta-aviso">Nenhum perfil cadastrado. <a href="{{ route('perfis.create') }}">Crie um perfil de importação</a> primeiro.</div>
    @else
        <form class="cartao" method="POST" action="{{ route('importacoes.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="campo">
                <label for="perfil">Perfil (qual relatório do ERP é esta planilha?)</label>
                <select id="perfil" name="perfil_importacao_id" required>
                    <option value="">Escolha…</option>
                    @foreach($perfis->groupBy('entidade') as $entidade => $grupo)
                        <optgroup label="{{ \App\Services\Importacao\Campos::ENTIDADES[$entidade] ?? $entidade }}">
                            @foreach($grupo as $p)
                                <option value="{{ $p->id }}" @selected((int) old('perfil_importacao_id') === $p->id)>{{ $p->nome }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('perfil_importacao_id')<div class="erro">{{ $message }}</div>@enderror
            </div>

            <div class="campo">
                <label for="arquivo">Planilha (.xlsx ou .csv)</label>
                <input id="arquivo" type="file" name="arquivo" accept=".xlsx,.csv,.txt" required>
                @error('arquivo')<div class="erro">{{ $message }}</div>@enderror
                <div class="ajuda">Nada é gravado ainda: você confere uma prévia antes de publicar.</div>
            </div>

            <button class="btn btn-primario" type="submit">Enviar e conferir</button>
        </form>
    @endif
@endsection
