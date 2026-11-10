<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f4e79">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}">
    <title>Entrar · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="login">
    <h1>{{ config('app.name') }}</h1>

    <form method="POST" action="{{ route('login.entrar') }}" class="cartao">
        @csrf

        <div class="campo">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email">
            @error('email')<div class="erro">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>

        <label class="check campo">
            <input type="checkbox" name="lembrar" value="1"> Manter conectado neste aparelho
        </label>

        <button class="btn btn-primario btn-bloco" type="submit">Entrar</button>
    </form>
</main>
</body>
</html>
