@extends('layouts.app')
@section('titulo', 'Usuários')

@section('conteudo')
    <div class="cabecalho-pagina">
        <div>
            <h1>Usuários</h1>
            <div class="suave">Quem acessa o sistema da sua empresa.</div>
        </div>
        <div class="acoes"><a class="btn btn-primario" href="{{ route('usuarios.create') }}">Novo usuário</a></div>
    </div>

    <div class="lista">
        @foreach($usuarios as $u)
            <a href="{{ route('usuarios.edit', $u->id) }}">
                <div>
                    <div class="titulo">{{ $u->name }}</div>
                    <div class="detalhe">{{ $u->email }}</div>
                </div>
                <div class="direita">
                    <span class="selo selo-azul">{{ \App\Models\User::PERFIS[$u->perfil] ?? $u->perfil }}</span>
                    @unless($u->ativo)<span class="selo selo-perigo">Inativo</span>@endunless
                </div>
            </a>
        @endforeach
    </div>
@endsection
