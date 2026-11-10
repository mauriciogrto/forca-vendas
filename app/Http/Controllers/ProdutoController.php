<?php

namespace App\Http\Controllers;

use App\Models\Importacao;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Produtos, preços e estoque são somente leitura aqui: vêm da importação do ERP. */
class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $produtos = Produto::ativos()
            ->buscar($request->query('q'))
            ->relevancia($request->query('q'))
            ->orderBy('descricao')
            ->simplePaginate(20)
            ->withQueryString();

        return view('produtos.index', [
            'produtos' => $produtos,
            'q' => (string) $request->query('q', ''),
            'atualizadoEm' => Importacao::ultimaPublicada('produtos'),
        ]);
    }

    public function show(Produto $produto)
    {
        return view('produtos.show', [
            'produto' => $produto,
            'atualizadoEm' => Importacao::ultimaPublicada('produtos'),
        ]);
    }

    public function busca(Request $request): JsonResponse
    {
        $produtos = Produto::ativos()
            ->buscar($request->query('q'))
            ->relevancia($request->query('q'))
            ->orderBy('descricao')
            ->limit(20)
            ->get(['id', 'codigo', 'descricao', 'unidade', 'categoria', 'preco', 'estoque'])
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'descricao' => $p->descricao,
                'unidade' => $p->unidade,
                'categoria' => $p->categoria,
                'preco' => (float) $p->preco,
                'estoque' => $p->estoque === null ? null : (float) $p->estoque,
            ]);

        return response()->json($produtos);
    }
}
