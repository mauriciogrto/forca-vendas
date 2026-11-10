<?php

namespace App\Http\Controllers;

use App\Models\Importacao;
use App\Models\Orcamento;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $base = fn () => Orcamento::visiveisPara($usuario);

        return view('home', [
            'pendentes' => $usuario->podeConferirClientes() ? \App\Models\Cliente::pendentes()->count() : 0,
            'recentes' => $base()->latest()->limit(5)->get(),
            'carimbos' => [
                'clientes' => Importacao::ultimaPublicada('clientes'),
                'produtos' => Importacao::ultimaPublicada('produtos'),
            ],
            'resumo' => [
                'orcamentos_abertos' => $base()->where('tipo', 'orcamento')->whereIn('status', ['rascunho', 'enviado'])->count(),
                'pedidos_mes' => $base()->where('tipo', 'pedido')->where('status', 'fechado')
                    ->where('fechado_em', '>=', now()->startOfMonth())->count(),
                'total_mes' => (float) $base()->where('tipo', 'pedido')->where('status', 'fechado')
                    ->where('fechado_em', '>=', now()->startOfMonth())->sum('total'),
            ],
        ]);
    }
}
