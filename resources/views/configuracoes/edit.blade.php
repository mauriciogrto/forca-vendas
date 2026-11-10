@extends('layouts.app')
@section('titulo', 'Configurações')

@section('conteudo')
    <div class="cabecalho-pagina"><h1>Configurações da empresa</h1></div>

    <form class="cartao" method="POST" action="{{ route('configuracoes.update') }}">
        @csrf @method('PUT')

        <div class="campo">
            <label for="nome">Nome da empresa (aparece no PDF)</label>
            <input id="nome" type="text" name="nome" required maxlength="255" value="{{ old('nome', $empresa->nome) }}">
            @error('nome')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="grade-2">
            <div class="campo">
                <label for="desconto_maximo">Desconto máximo por item (%)</label>
                <input id="desconto_maximo" type="number" name="desconto_maximo" min="0" max="100" step="0.01" inputmode="decimal" required value="{{ old('desconto_maximo', $empresa->desconto_maximo) }}">
                <div class="ajuda">O servidor recusa descontos acima deste valor. Use 0 para não permitir desconto.</div>
                @error('desconto_maximo')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo">
                <label for="validade_dias">Validade do orçamento (dias)</label>
                <input id="validade_dias" type="number" name="validade_dias" min="1" max="365" required value="{{ old('validade_dias', $empresa->validade_dias) }}">
                @error('validade_dias')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="campo">
            <label for="rodape_pdf">Rodapé do PDF (opcional)</label>
            <textarea id="rodape_pdf" name="rodape_pdf" rows="3" maxlength="1000" placeholder="Ex.: Preços sujeitos a alteração sem aviso prévio. Frete por conta do cliente.">{{ old('rodape_pdf', $empresa->rodape_pdf) }}</textarea>
            @error('rodape_pdf')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <label class="check" style="margin-bottom:14px">
            <input type="checkbox" name="cadastro_clientes" value="1" @checked(old('cadastro_clientes', $empresa->cadastro_clientes))>
            <span>Permitir cadastrar clientes em campo<br><span class="ajuda">O representante cadastra o cliente no app e o escritório confere e informa o código do ERP. Desligado, os clientes vêm só da planilha importada.</span></span>
        </label>

        <button class="btn btn-primario" type="submit">Salvar</button>
    </form>
@endsection
