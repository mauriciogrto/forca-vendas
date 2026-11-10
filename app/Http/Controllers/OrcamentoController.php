<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrcamentoRequest;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\Produto;
use App\Services\OrcamentoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function __construct(private OrcamentoService $servico)
    {
    }

    public function index(Request $request)
    {
        $usuario = $request->user();
        $q = trim((string) $request->query('q', ''));

        $orcamentos = Orcamento::visiveisPara($usuario)
            ->with('representante:id,name')
            ->when($request->filled('tipo'), fn ($query) => $query->where('tipo', $request->query('tipo')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($w) use ($like, $q) {
                    $w->where('cliente_nome', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('numero', (int) $q);
                    }
                });
            })
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        return view('orcamentos.index', [
            'orcamentos' => $orcamentos,
            'q' => $q,
            'tipo' => (string) $request->query('tipo', ''),
            'status' => (string) $request->query('status', ''),
        ]);
    }

    public function create(Request $request)
    {
        $cliente = $request->filled('cliente') ? Cliente::find($request->query('cliente')) : null;

        return view('orcamentos.form', [
            'orcamento' => new Orcamento(),
            'inicial' => $this->montarInicial(null, $cliente),
            'descontoMaximo' => (float) $request->user()->empresa->desconto_maximo,
        ]);
    }

    public function store(OrcamentoRequest $request): RedirectResponse
    {
        $orcamento = $this->servico->criar($request->user(), $request->validated());

        return redirect()->route('orcamentos.show', $orcamento)->with('sucesso', 'Orçamento nº '.$orcamento->numero.' salvo.');
    }

    public function show(Request $request, Orcamento $orcamento)
    {
        $this->autorizarLeitura($request, $orcamento);

        return view('orcamentos.show', [
            'orcamento' => $orcamento->load(['itens', 'representante:id,name', 'cliente']),
            'podeAlterar' => $orcamento->podeSerAlteradoPor($request->user()),
            'validade' => $request->user()->empresa->validade_dias,
        ]);
    }

    public function edit(Request $request, Orcamento $orcamento)
    {
        $this->autorizarEdicao($request, $orcamento);

        return view('orcamentos.form', [
            'orcamento' => $orcamento,
            'inicial' => $this->montarInicial($orcamento->load('itens'), null),
            'descontoMaximo' => (float) $request->user()->empresa->desconto_maximo,
        ]);
    }

    public function update(OrcamentoRequest $request, Orcamento $orcamento): RedirectResponse
    {
        $this->autorizarEdicao($request, $orcamento);

        $this->servico->atualizar($orcamento, $request->validated());

        return redirect()->route('orcamentos.show', $orcamento)->with('sucesso', 'Alterações salvas.');
    }

    public function enviar(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $this->autorizarEdicao($request, $orcamento);
        $this->servico->marcarEnviado($orcamento);

        return back()->with('sucesso', 'Marcado como enviado ao cliente.');
    }

    public function converter(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $this->autorizarEdicao($request, $orcamento);
        $this->servico->converterEmPedido($orcamento);

        return back()->with('sucesso', 'Orçamento convertido em pedido.');
    }

    public function cancelar(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $this->autorizarLeitura($request, $orcamento);
        abort_unless($orcamento->podeSerAlteradoPor($request->user()), 403);

        if ($orcamento->status !== 'cancelado') {
            $this->servico->cancelar($orcamento);
        }

        return back()->with('sucesso', 'Documento cancelado.');
    }

    public function destroy(Request $request, Orcamento $orcamento): RedirectResponse
    {
        $this->autorizarLeitura($request, $orcamento);
        abort_unless($orcamento->podeSerAlteradoPor($request->user()) && $orcamento->status === 'rascunho' && ! $orcamento->ehPedido(), 403);

        $orcamento->delete();

        return redirect()->route('orcamentos.index')->with('sucesso', 'Rascunho excluído.');
    }

    public function pdf(Request $request, Orcamento $orcamento)
    {
        $this->autorizarLeitura($request, $orcamento);

        $orcamento->load(['itens', 'representante:id,name', 'cliente']);
        $empresa = $request->user()->empresa;

        $documento = Pdf::loadView('orcamentos.pdf', [
            'orcamento' => $orcamento,
            'empresa' => $empresa,
        ])->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans']);

        $arquivo = strtolower($orcamento->rotuloTipo()).'-'.$orcamento->numero.'.pdf';

        return $request->boolean('baixar') ? $documento->download($arquivo) : $documento->stream($arquivo);
    }

    /**
     * Estado inicial do formulário. Se o envio anterior falhou na validação,
     * reaproveita o que o usuário tinha digitado (itens recarregados do cadastro).
     *
     * @return array<string, mixed>
     */
    private function montarInicial(?Orcamento $orcamento, ?Cliente $cliente): array
    {
        $itensAntigos = old('itens');

        if (is_array($itensAntigos)) {
            $produtos = Produto::whereIn('id', array_filter(array_column($itensAntigos, 'produto_id')))->get()->keyBy('id');
            $itens = [];
            foreach ($itensAntigos as $item) {
                $produto = $produtos->get((int) ($item['produto_id'] ?? 0));
                if (! $produto) {
                    continue;
                }
                $itens[] = [
                    'produto_id' => $produto->id,
                    'codigo' => $produto->codigo,
                    'descricao' => $produto->descricao,
                    'unidade' => $produto->unidade,
                    'preco' => (float) $produto->preco,
                    'quantidade' => (float) ($item['quantidade'] ?? 1),
                    'desconto' => (float) ($item['desconto_percentual'] ?? 0),
                ];
            }

            return [
                'clienteId' => old('cliente_id') ?: null,
                'clienteNome' => (string) old('cliente_nome', ''),
                'condicao' => (string) old('condicao_pagamento', ''),
                'observacoes' => (string) old('observacoes', ''),
                'itens' => $itens,
            ];
        }

        if ($orcamento) {
            return [
                'clienteId' => $orcamento->cliente_id,
                'clienteNome' => $orcamento->cliente_nome,
                'condicao' => $orcamento->condicao_pagamento ?? '',
                'observacoes' => $orcamento->observacoes ?? '',
                'itens' => $orcamento->itens->map(fn ($i) => [
                    'produto_id' => $i->produto_id,
                    'codigo' => $i->codigo,
                    'descricao' => $i->descricao,
                    'unidade' => $i->unidade,
                    'preco' => (float) $i->preco_unitario,
                    'quantidade' => (float) $i->quantidade,
                    'desconto' => (float) $i->desconto_percentual,
                ])->values()->all(),
            ];
        }

        return [
            'clienteId' => $cliente?->id,
            'clienteNome' => $cliente?->nome ?? '',
            'condicao' => '',
            'observacoes' => '',
            'itens' => [],
        ];
    }

    private function autorizarLeitura(Request $request, Orcamento $orcamento): void
    {
        $usuario = $request->user();
        abort_unless($orcamento->user_id === $usuario->id || $usuario->veTodos(), 403);
    }

    private function autorizarEdicao(Request $request, Orcamento $orcamento): void
    {
        $this->autorizarLeitura($request, $orcamento);
        abort_unless($orcamento->podeSerAlteradoPor($request->user()) && $orcamento->editavel(), 403, 'Este documento não pode mais ser alterado.');
    }
}
