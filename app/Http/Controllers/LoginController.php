<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tentativa = Auth::attempt(
            ['email' => $credenciais['email'], 'password' => $credenciais['password'], 'ativo' => true],
            $request->boolean('lembrar')
        );

        if ($tentativa && Auth::user()->empresa && ! Auth::user()->empresa->ativo) {
            Auth::logout();
            $tentativa = false;
        }

        if (! $tentativa) {
            return back()
                ->withErrors(['email' => 'E-mail ou senha incorretos, ou acesso desativado.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
