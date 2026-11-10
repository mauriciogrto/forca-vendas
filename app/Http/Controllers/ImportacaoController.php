<?php

namespace App\Http\Controllers;

use App\Models\Importacao;
use App\Models\PerfilImportacao;
use App\Services\Importacao\Campos;
use App\Services\Importacao\ImportacaoException;
use App\Services\Importacao\ImportadorService;
use App\Services\Importacao\LeitorPlanilha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportacaoController extends Controller
{
    public function __construct(private ImportadorService $importador)
    {
    }

    public function index()
    {
        return view('importacoes.index', [
            'importacoes' => Importacao::with(['perfil:id,nome', 'usuario:id,name'])->latest()->simplePaginate(15),
            'perfis' => PerfilImportacao::count(),
        ]);
    }

    public function create()
    {
        return view('importacoes.create', [
            'perfis' => PerfilImportacao::orderBy('entidade')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'perfil_importacao_id' => ['required', 'integer'],
            'arquivo' => ['required', 'file', 'max:30720'],
        ], [
            'arquivo.required' => 'Escolha a planilha para importar.',
            'arquivo.max' => 'A planilha passa de 30 MB. Divida o arquivo ou envie um .csv.',
        ]);

        $perfil = PerfilImportacao::findOrFail($dados['perfil_importacao_id']);
        $arquivo = $request->file('arquivo');

        if (! LeitorPlanilha::extensaoSuportada($arquivo->getClientOriginalExtension())) {
            return back()->withInput()->withErrors(['arquivo' => 'Envie uma planilha .xlsx ou .csv. Arquivos .xls antigos precisam ser salvos como .xlsx.']);
        }

        $importacao = $this->importador->armazenar($arquivo, $perfil, $request->user());

        try {
            $this->importador->gerarPrevia($importacao);
        } catch (ImportacaoException $e) {
            $this->importador->removerArquivo($importacao);
            $importacao->delete();

            return back()->withInput()->withErrors(['arquivo' => $e->getMessage()]);
        }

        return redirect()->route('importacoes.show', $importacao);
    }

    public function show(Importacao $importacao)
    {
        return view('importacoes.show', [
            'importacao' => $importacao->load(['perfil', 'usuario:id,name']),
            'campos' => Campos::para($importacao->entidade),
        ]);
    }

    public function publicar(Importacao $importacao): RedirectResponse
    {
        try {
            $this->importador->publicar($importacao);
        } catch (ImportacaoException $e) {
            return back()->withErrors(['publicar' => $e->getMessage()]);
        }

        return redirect()->route('importacoes.show', $importacao)->with('sucesso', 'Dados publicados. O app já mostra as informações novas.');
    }

    public function descartar(Importacao $importacao): RedirectResponse
    {
        if ($importacao->status === 'previa') {
            $this->importador->descartar($importacao);
        }

        return redirect()->route('importacoes.index')->with('sucesso', 'Importação descartada. Nada foi alterado.');
    }
}
