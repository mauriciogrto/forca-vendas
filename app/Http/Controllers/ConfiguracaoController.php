<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    public function edit(Request $request)
    {
        return view('configuracoes.edit', ['empresa' => $request->user()->empresa]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'desconto_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'validade_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'rodape_pdf' => ['nullable', 'string', 'max:1000'],
        ]);

        $dados['cadastro_clientes'] = $request->boolean('cadastro_clientes');

        $request->user()->empresa->update($dados);

        return back()->with('sucesso', 'Configurações salvas.');
    }
}
