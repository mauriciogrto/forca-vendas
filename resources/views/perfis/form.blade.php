@extends('layouts.app')
@section('titulo', $perfil->exists ? 'Editar perfil' : 'Novo perfil')

@section('conteudo')
    @php
        $mapeamentoAtual = old('mapeamento', $perfil->mapeamento ?? []);
        $entidadeAtual = old('entidade', $perfil->entidade ?: 'produtos');
    @endphp

    <div class="cabecalho-pagina"><h1>{{ $perfil->exists ? 'Editar perfil' : 'Novo perfil de importação' }}</h1></div>

    @if($errors->any())
        <div class="alerta alerta-erro">@foreach($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach</div>
    @endif

    <form method="POST" action="{{ $perfil->exists ? route('perfis.update', $perfil) : route('perfis.store') }}"
          x-data="perfilForm(@js(['entidade' => $entidadeAtual, 'linha' => old('linha_cabecalho', $perfil->linha_cabecalho ?: 1), 'mapeamento' => (object) $mapeamentoAtual]), @js(route('perfis.cabecalhos')))">
        @csrf
        @if($perfil->exists) @method('PUT') @endif

        <div class="cartao">
            <div class="campo">
                <label for="nome">Nome do perfil</label>
                <input id="nome" type="text" name="nome" maxlength="120" required value="{{ old('nome', $perfil->nome) }}" placeholder="Ex.: Produtos — relatório de preços do ERP">
            </div>

            <div class="grade-2">
                <div class="campo">
                    <label for="entidade">O que a planilha contém</label>
                    @if($perfil->exists)
                        <input type="text" value="{{ \App\Services\Importacao\Campos::ENTIDADES[$perfil->entidade] }}" disabled>
                    @else
                        <select id="entidade" name="entidade" x-model="entidade">
                            @foreach(\App\Services\Importacao\Campos::ENTIDADES as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="campo">
                    <label for="linha">Linha do cabeçalho</label>
                    <input id="linha" type="number" name="linha_cabecalho" min="1" max="200" required x-model="linha">
                    <div class="ajuda">Relatórios de ERP costumam ter título nas primeiras linhas. Informe em qual linha estão os nomes das colunas.</div>
                </div>
            </div>
        </div>

        <div class="cartao">
            <h2>Colunas da planilha</h2>
            <div class="campo">
                <label for="exemplo">Planilha de exemplo (opcional)</label>
                <input id="exemplo" type="file" accept=".xlsx,.csv,.txt" @change="lerCabecalhos($event)">
                <div class="ajuda">Envie uma planilha real do ERP para escolher as colunas numa lista, em vez de digitar. O arquivo não é guardado.</div>
                <div class="ajuda" x-show="lendo" style="display:none">Lendo cabeçalho…</div>
                <div class="erro" x-show="erro" style="display:none" x-text="erro"></div>
            </div>

            @foreach(\App\Services\Importacao\Campos::todas() as $entidade => $campos)
                <div x-show="entidade === '{{ $entidade }}'" @if($entidade !== $entidadeAtual) style="display:none" @endif>
                    @foreach($campos as $campo => $def)
                        <div class="campo">
                            <label>{{ $def['rotulo'] }} @if($def['obrigatorio'])<span class="suave">(obrigatório)</span>@endif</label>

                            <template x-if="cabecalhos.length > 0">
                                <select data-campo="{{ $campo }}" name="mapeamento[{{ $campo }}]" :disabled="entidade !== '{{ $entidade }}'" x-model="mapeamento['{{ $campo }}']">
                                    <option value="">{{ $def['obrigatorio'] ? 'Escolha a coluna…' : '— não importar —' }}</option>
                                    <template x-for="c in cabecalhos" :key="c"><option :value="c" x-text="c"></option></template>
                                </select>
                            </template>
                            <template x-if="cabecalhos.length === 0">
                                <input type="text" data-campo="{{ $campo }}" name="mapeamento[{{ $campo }}]" :disabled="entidade !== '{{ $entidade }}'" x-model="mapeamento['{{ $campo }}']" placeholder="Nome da coluna na planilha">
                            </template>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div x-show="entidade === 'produtos'" @if($entidadeAtual !== 'produtos') style="display:none" @endif>
                <label class="check">
                    <input type="checkbox" name="desativar_ausentes" value="1" @checked(old('desativar_ausentes', $perfil->desativar_ausentes))>
                    <span>Esta planilha é o catálogo completo: produtos que não aparecerem nela ficam inativos<br>
                        <small class="suave">Use só se o relatório lista todos os produtos. Se for uma lista parcial, deixe desmarcado.</small></span>
                </label>
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primario" type="submit">Salvar perfil</button>
            <a class="btn" href="{{ route('perfis.index') }}">Voltar</a>
        </div>
    </form>

    @if($perfil->exists)
        <form method="POST" action="{{ route('perfis.destroy', $perfil) }}" style="margin-top:18px" onsubmit="return confirm('Excluir este perfil? O histórico de importações é mantido.')">
            @csrf @method('DELETE')
            <button class="btn btn-perigo" type="submit">Excluir perfil</button>
        </form>
    @endif
@endsection
