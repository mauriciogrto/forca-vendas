@extends('layouts.app')
@section('titulo', $usuario->exists ? 'Editar usuário' : 'Novo usuário')

@section('conteudo')
    <div class="cabecalho-pagina"><h1>{{ $usuario->exists ? 'Editar usuário' : 'Novo usuário' }}</h1></div>

    <form class="cartao" method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario->id) : route('usuarios.store') }}">
        @csrf
        @if($usuario->exists) @method('PUT') @endif

        <div class="campo">
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" required maxlength="255" value="{{ old('name', $usuario->name) }}">
            @error('name')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="email">E-mail (usado para entrar)</label>
            <input id="email" type="email" name="email" required maxlength="255" autocapitalize="none" value="{{ old('email', $usuario->email) }}">
            @error('email')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="perfil">Perfil</label>
            <select id="perfil" name="perfil" required>
                @foreach(\App\Models\User::PERFIS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(old('perfil', $usuario->perfil) === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <div class="ajuda">Representante vê só os próprios orçamentos. Escritório e gestor veem os de todos e os relatórios. Administrador gerencia usuários e configurações.</div>
            @error('perfil')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="password">{{ $usuario->exists ? 'Nova senha (deixe em branco para manter)' : 'Senha' }}</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" @required(! $usuario->exists)>
            <div class="ajuda">Mínimo de 8 caracteres.</div>
            @error('password')<div class="erro">{{ $message }}</div>@enderror
        </div>

        @if($usuario->exists)
            <label class="check" style="margin-bottom:14px">
                <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $usuario->ativo))>
                <span>Usuário ativo (desmarque para bloquear o acesso)</span>
            </label>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primario" type="submit">Salvar</button>
            <a class="btn" href="{{ route('usuarios.index') }}">Voltar</a>
        </div>
    </form>
@endsection
