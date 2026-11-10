@extends('layouts.app')
@use('App\Support\Formato')
@php
    $conferindo = $cliente->exists && auth()->user()->podeConferirClientes();
    $titulo = $cliente->exists ? ($conferindo ? 'Conferir cadastro' : 'Corrigir cadastro') : 'Novo cliente';
@endphp
@section('titulo', $titulo)

@section('conteudo')
    <div class="cabecalho-pagina"><h1>{{ $titulo }}</h1></div>

    @if(! $cliente->exists)
        <p class="suave" style="margin-top:0">Fica como pendente até o escritório conferir. Enquanto isso você já pode fazer orçamentos para ele.</p>
    @elseif($conferindo)
        <p class="suave" style="margin-top:0">Confira os dados, cadastre o cliente no ERP e informe aqui o código que ele recebeu.</p>
    @endif

    <form class="cartao" method="POST" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store') }}">
        @csrf
        @if($cliente->exists) @method('PUT') @endif

        <div class="campo">
            <label for="nome">Nome / razão social</label>
            <input id="nome" type="text" name="nome" required maxlength="255" value="{{ old('nome', $cliente->nome) }}">
            @error('nome')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="grade-2">
            <div class="campo">
                <label for="documento">CPF ou CNPJ</label>
                <input id="documento" type="text" name="documento" required inputmode="numeric" maxlength="18" autocomplete="off" value="{{ old('documento', $cliente->documento ? Formato::documento($cliente->documento) : '') }}">
                @error('documento')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo">
                <label for="telefone">Telefone</label>
                <input id="telefone" type="tel" name="telefone" maxlength="40" value="{{ old('telefone', $cliente->telefone) }}">
                @error('telefone')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="campo">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" maxlength="255" autocapitalize="none" value="{{ old('email', $cliente->email) }}">
            @error('email')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="endereco">Endereço</label>
            <input id="endereco" type="text" name="endereco" maxlength="255" value="{{ old('endereco', $cliente->endereco) }}">
            @error('endereco')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="grade-2">
            <div class="campo">
                <label for="cidade">Cidade</label>
                <input id="cidade" type="text" name="cidade" required maxlength="120" value="{{ old('cidade', $cliente->cidade) }}">
                @error('cidade')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo">
                <label for="uf">UF</label>
                <input id="uf" type="text" name="uf" required maxlength="2" autocapitalize="characters" value="{{ old('uf', $cliente->uf) }}">
                @error('uf')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </div>

        @if($conferindo)
            <div class="campo">
                <label for="codigo">Código no ERP</label>
                <input id="codigo" type="text" name="codigo" maxlength="60" autocomplete="off" placeholder="Obrigatório para confirmar" value="{{ old('codigo') }}">
                <div class="ajuda">Se este código chegar depois na planilha importada, o cadastro é atualizado com os dados do ERP, sem duplicar.</div>
                @error('codigo')<div class="erro">{{ $message }}</div>@enderror
            </div>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if($conferindo)
                <button class="btn btn-primario" type="submit" name="acao" value="confirmar">Confirmar cadastro</button>
                <button class="btn" type="submit" name="acao" value="salvar">Só salvar</button>
            @else
                <button class="btn btn-primario" type="submit" name="acao" value="salvar">Salvar</button>
            @endif
            <a class="btn" href="{{ $cliente->exists ? route('clientes.show', $cliente) : route('clientes.index') }}">Voltar</a>
        </div>
    </form>

    @if($conferindo)
        <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" style="margin-top:12px" onsubmit="return confirm('Excluir este cadastro? Use para duplicados ou enganos.')">
            @csrf @method('DELETE')
            <button class="btn btn-perigo btn-pequeno" type="submit">Excluir cadastro</button>
        </form>
    @endif
@endsection
