<?php

namespace App\Http\Controllers;

use App\Models\PerfilImportacao;
use App\Services\Importacao\Campos;
use App\Services\Importacao\ConversorValores;
use App\Services\Importacao\ImportacaoException;
use App\Services\Importacao\LeitorPlanilha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PerfilImportacaoController extends Controller
{
    public function index()
    {
        return view('perfis.index', ['perfis' => PerfilImportacao::orderBy('entidade')->orderBy('nome')->get()]);
    }

    public function create()
    {
        return view('perfis.form', ['perfil' => new PerfilImportacao(['entidade' => 'produtos', 'linha_cabecalho' => 1, 'mapeamento' => []])]);
    }

    public function store(Request $request): RedirectResponse
    {
        PerfilImportacao::create($this->validar($request));

        return redirect()->route('perfis.index')->with('sucesso', 'Perfil criado.');
    }

    public function edit(PerfilImportacao $perfil)
    {
        return view('perfis.form', compact('perfil'));
    }

    public function update(Request $request, PerfilImportacao $perfil): RedirectResponse
    {
        $perfil->update($this->validar($request, $perfil));

        return redirect()->route('perfis.index')->with('sucesso', 'Perfil atualizado.');
    }

    public function destroy(PerfilImportacao $perfil): RedirectResponse
    {
        $perfil->delete();

        return redirect()->route('perfis.index')->with('sucesso', 'Perfil excluído.');
    }

    /** Lê só o cabeçalho de uma planilha de exemplo para o usuário escolher as colunas na tela. */
    public function cabecalhos(Request $request): JsonResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'max:30720'],
            'linha' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $arquivo = $request->file('arquivo');
        $extensao = $arquivo->getClientOriginalExtension();

        if (! LeitorPlanilha::extensaoSuportada($extensao)) {
            return response()->json(['erro' => 'Envie uma planilha .xlsx ou .csv.'], 422);
        }

        $linhaCabecalho = (int) $request->input('linha');

        try {
            foreach (LeitorPlanilha::linhas($arquivo->getRealPath(), $extensao) as $numero => $celulas) {
                if ($numero < $linhaCabecalho) {
                    continue;
                }
                if ($numero > $linhaCabecalho) {
                    break;
                }

                $cabecalhos = array_values(array_filter(array_map(
                    static fn ($c) => ConversorValores::texto($c),
                    $celulas
                )));

                return response()->json(['cabecalhos' => $cabecalhos]);
            }
        } catch (ImportacaoException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json(['erro' => "A linha {$linhaCabecalho} não existe ou está vazia nessa planilha."], 422);
    }

    private function validar(Request $request, ?PerfilImportacao $atual = null): array
    {
        $entidade = $atual?->entidade ?? (string) $request->input('entidade');

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'entidade' => [Rule::requiredIf($atual === null), Rule::in(array_keys(Campos::ENTIDADES))],
            'linha_cabecalho' => ['required', 'integer', 'min:1', 'max:200'],
            'mapeamento' => ['required', 'array'],
            'mapeamento.*' => ['nullable', 'string', 'max:120'],
            'desativar_ausentes' => ['nullable', 'boolean'],
        ], [
            'mapeamento.required' => 'Informe as colunas da planilha.',
        ]);

        $mapeamento = [];
        $faltando = [];
        foreach (Campos::para($entidade) as $campo => $definicao) {
            $coluna = ConversorValores::texto($dados['mapeamento'][$campo] ?? null);
            if ($coluna !== null) {
                $mapeamento[$campo] = $coluna;
            } elseif ($definicao['obrigatorio']) {
                $faltando[] = $definicao['rotulo'];
            }
        }

        if ($faltando !== []) {
            throw ValidationException::withMessages([
                'mapeamento' => 'Indique a coluna de: '.implode(', ', $faltando).'.',
            ]);
        }

        return [
            'nome' => $dados['nome'],
            'entidade' => $entidade,
            'linha_cabecalho' => (int) $dados['linha_cabecalho'],
            'mapeamento' => $mapeamento,
            'desativar_ausentes' => $entidade === 'produtos' && $request->boolean('desativar_ausentes'),
        ];
    }
}
