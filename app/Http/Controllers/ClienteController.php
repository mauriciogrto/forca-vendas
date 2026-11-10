<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\Importacao;
use App\Models\Orcamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clientes vêm da importação do ERP e são somente leitura.
 * Se a empresa liga o cadastro em campo, o representante cria clientes "pendentes"
 * e o escritório confere e informa o código do ERP.
 */
class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::buscar($request->query('q'))
            ->relevancia($request->query('q'))
            ->orderBy('nome')
            ->simplePaginate(20)
            ->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            'q' => (string) $request->query('q', ''),
            'atualizadoEm' => Importacao::ultimaPublicada('clientes'),
            'pendentes' => $request->user()->podeConferirClientes() ? Cliente::pendentes()->count() : 0,
        ]);
    }

    public function show(Request $request, Cliente $cliente)
    {
        $orcamentos = Orcamento::visiveisPara($request->user())
            ->where('cliente_id', $cliente->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('clientes.show', compact('cliente', 'orcamentos'));
    }

    public function busca(Request $request): JsonResponse
    {
        $clientes = Cliente::buscar($request->query('q'))
            ->relevancia($request->query('q'))
            ->orderBy('nome')
            ->limit(20)
            ->get(['id', 'codigo', 'nome', 'documento', 'cidade', 'uf', 'situacao']);

        return response()->json($clientes);
    }

    public function pendentes()
    {
        return view('clientes.pendentes', [
            'clientes' => Cliente::pendentes()->with('cadastradoPor:id,name')->orderBy('created_at')->get(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->podeCadastrarCliente(), 403);

        return view('clientes.form', ['cliente' => new Cliente(['uf' => 'RS'])]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        abort_unless($request->user()->podeCadastrarCliente(), 403);

        $dados = $request->safe()->only(['nome', 'documento', 'telefone', 'email', 'endereco', 'cidade', 'uf']);

        $cliente = DB::transaction(function () use ($dados, $request) {
            $cliente = Cliente::create($dados + [
                'codigo' => 'NOVO-'.Str::upper(Str::random(10)), // provisório, trocado pelo id logo abaixo
                'situacao' => 'pendente',
                'cadastrado_por' => $request->user()->id,
            ]);
            $cliente->update(['codigo' => sprintf('NOVO-%05d', $cliente->id)]);

            return $cliente;
        });

        return redirect()->route('clientes.show', $cliente)
            ->with('sucesso', 'Cliente cadastrado. O escritório vai conferir e informar o código do ERP; você já pode fazer o orçamento.');
    }

    public function edit(Request $request, Cliente $cliente)
    {
        $this->autorizarEdicao($request, $cliente);

        return view('clientes.form', compact('cliente'));
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizarEdicao($request, $cliente);

        $dados = $request->safe()->only(['nome', 'documento', 'telefone', 'email', 'endereco', 'cidade', 'uf']);

        if ($request->confirmando()) {
            abort_unless($request->user()->podeConferirClientes(), 403);

            $cliente->update($dados + [
                'codigo' => trim($request->validated('codigo')),
                'situacao' => 'ativo',
                'conferido_em' => now(),
            ]);

            return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Cadastro confirmado.');
        }

        $cliente->update($dados);

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Cadastro atualizado.');
    }

    /** Recusa um cadastro pendente (duplicado, enganado). Se já há documentos, o cadastro tem que ser confirmado. */
    public function destroy(Request $request, Cliente $cliente): RedirectResponse
    {
        abort_unless($request->user()->podeConferirClientes() && $cliente->estaPendente(), 403);

        if ($cliente->orcamentos()->exists()) {
            return back()->with('erro', 'Este cliente já tem orçamentos ou pedidos. Confirme o cadastro em vez de excluir.');
        }

        $cliente->delete();

        return redirect()->route('clientes.pendentes')->with('sucesso', 'Cadastro excluído.');
    }

    /** Só cadastro pendente se altera: o escritório qualquer um, o representante os próprios. */
    private function autorizarEdicao(Request $request, Cliente $cliente): void
    {
        $usuario = $request->user();

        abort_unless(
            $cliente->estaPendente()
                && ($usuario->podeConferirClientes() || ((int) $cliente->cadastrado_por === (int) $usuario->id && $usuario->podeCadastrarCliente())),
            403
        );
    }
}
