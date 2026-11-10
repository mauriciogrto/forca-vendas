<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f4e79">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <title>@yield('titulo', 'Início') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('vendor/alpine.min.js') }}" defer></script>
</head>
<body>
@auth
    @php $usuario = auth()->user(); @endphp

    @php
        $abas = [
            ['home', 'Início', request()->routeIs('home')],
            ['clientes.index', 'Clientes', request()->routeIs('clientes.*')],
            ['produtos.index', 'Produtos', request()->routeIs('produtos.*')],
            ['orcamentos.index', 'Orçamentos', request()->routeIs('orcamentos.*')],
        ];
        if ($usuario->podeImportar()) {
            $abas[] = ['importacoes.index', 'Importar', request()->routeIs('importacoes.*', 'perfis.*')];
        }
        if ($usuario->veTodos()) {
            $abas[] = ['relatorios.index', 'Relatórios', request()->routeIs('relatorios.*')];
        }
        if ($usuario->ehAdmin()) {
            $abas[] = ['usuarios.index', 'Usuários', request()->routeIs('usuarios.*')];
            $abas[] = ['configuracoes.edit', 'Empresa', request()->routeIs('configuracoes.*')];
        }
    @endphp

    <div class="topo-faixa">
        <header class="topo">
            <a class="marca" href="{{ route('home') }}">{{ config('app.name') }}<small>{{ $usuario->empresa->nome ?? '' }}</small></a>
            <div class="quem">
                <div>{{ $usuario->name }}<br>{{ $usuario->rotuloPerfil() }}</div>
                <form method="POST" action="{{ route('sair') }}">
                    @csrf
                    <button type="submit" class="btn btn-pequeno">Sair</button>
                </form>
            </div>
        </header>
        <nav class="abas" aria-label="Principal">
            @foreach($abas as [$rota, $rotulo, $ativa])
                <a href="{{ route($rota) }}" class="{{ $ativa ? 'ativo' : '' }}" @if($ativa) aria-current="page" @endif>{{ $rotulo }}</a>
            @endforeach
        </nav>
    </div>
@endauth

<main class="conteudo">
    @if(session('sucesso'))
        <div class="alerta alerta-ok" role="status">{{ session('sucesso') }}</div>
    @endif

    @if(session('erro'))
        <div class="alerta alerta-erro" role="alert">{{ session('erro') }}</div>
    @endif

    @yield('conteudo')
</main>
</body>
</html>
