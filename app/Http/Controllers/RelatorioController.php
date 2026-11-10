<?php

namespace App\Http\Controllers;

use App\Models\Orcamento;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public function index()
    {
        return view('relatorios.index');
    }

    /**
     * Pedidos fechados no app, um item por linha, para o escritório conferir e lançar no ERP.
     * CSV com ';' e BOM UTF-8 para abrir direto no Excel.
     */
    public function pedidosCsv(Request $request): StreamedResponse
    {
        $dados = $request->validate([
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
        ]);

        $pedidos = Orcamento::where('tipo', 'pedido')
            ->where('status', 'fechado')
            ->whereBetween('fechado_em', [$dados['de'].' 00:00:00', $dados['ate'].' 23:59:59'])
            ->with(['itens', 'representante:id,name', 'cliente:id,codigo,situacao'])
            ->orderBy('fechado_em')
            ->orderBy('numero');

        $nome = 'pedidos-'.$dados['de'].'-a-'.$dados['ate'].'.csv';

        return response()->streamDownload(function () use ($pedidos) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, [
                'Pedido', 'Fechado em', 'Cód. cliente', 'Cliente', 'Situação do cliente', 'Representante', 'Condição de pagamento',
                'Cód. produto', 'Descrição', 'Unid.', 'Quantidade', 'Preço unit.', 'Desconto %', 'Total do item', 'Total do pedido',
            ], ';');

            $pedidos->chunk(200, function ($lote) use ($saida) {
                foreach ($lote as $pedido) {
                    foreach ($pedido->itens as $item) {
                        fputcsv($saida, [
                            $pedido->numero,
                            $pedido->fechado_em?->format('d/m/Y H:i'),
                            $pedido->cliente?->codigo,
                            $pedido->cliente_nome,
                            self::situacaoDoCliente($pedido),
                            $pedido->representante?->name,
                            $pedido->condicao_pagamento,
                            $item->codigo,
                            $item->descricao,
                            $item->unidade,
                            self::decimal($item->quantidade, 3),
                            self::decimal($item->preco_unitario, 2),
                            self::decimal($item->desconto_percentual, 2),
                            self::decimal($item->total, 2),
                            self::decimal($pedido->total, 2),
                        ], ';');
                    }
                }
            });

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cliente novo precisa existir no ERP antes de o pedido ser lançado. */
    private static function situacaoDoCliente(Orcamento $pedido): string
    {
        return match (true) {
            $pedido->cliente === null => 'Sem cadastro',
            $pedido->cliente->situacao === 'pendente' => 'Novo (cadastrar no ERP)',
            default => 'Cadastrado',
        };
    }

    private static function decimal(mixed $valor, int $casas): string
    {
        return number_format((float) $valor, $casas, ',', '');
    }
}
