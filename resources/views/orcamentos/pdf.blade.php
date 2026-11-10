@use('App\Support\Formato')
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $orcamento->rotuloTipo() }} {{ $orcamento->numero }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1f2933; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .empresa { font-size: 13px; font-weight: bold; }
        .suave { color: #667085; }
        .topo { border-bottom: 2px solid #1f4e79; padding-bottom: 8px; margin-bottom: 14px; }
        .topo table { width: 100%; }
        .direita { text-align: right; }
        .bloco { margin-bottom: 14px; }
        .bloco .rotulo { font-size: 8.5px; text-transform: uppercase; color: #667085; letter-spacing: .4px; }
        table.itens { width: 100%; border-collapse: collapse; }
        table.itens th { background: #eef1f5; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 6px 6px; border-bottom: 1px solid #b8c0cc; }
        table.itens td { padding: 6px; border-bottom: 1px solid #e1e5ea; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .total { margin-top: 12px; text-align: right; font-size: 14px; font-weight: bold; }
        .rodape { margin-top: 26px; font-size: 9px; color: #667085; border-top: 1px solid #d9dee5; padding-top: 8px; }
        .cancelado { color: #b42318; font-weight: bold; }
    </style>
</head>
<body>
    <div class="topo">
        <table>
            <tr>
                <td>
                    <div class="empresa">{{ $empresa->nome }}</div>
                    <div class="suave">Representante: {{ $orcamento->representante->name ?? '—' }}</div>
                </td>
                <td class="direita">
                    <h1>{{ $orcamento->rotuloTipo() }} nº {{ $orcamento->numero }}</h1>
                    <div class="suave">Emitido em {{ Formato::data($orcamento->created_at) }}</div>
                    @if(! $orcamento->ehPedido())
                        <div class="suave">Válido até {{ Formato::data($orcamento->created_at->copy()->addDays($empresa->validade_dias)) }}</div>
                    @endif
                    @if($orcamento->status === 'cancelado')<div class="cancelado">CANCELADO</div>@endif
                </td>
            </tr>
        </table>
    </div>

    <div class="bloco">
        <div class="rotulo">Cliente</div>
        <strong>{{ $orcamento->cliente_nome }}</strong>
        @if($orcamento->cliente)
            <div class="suave">
                @if($orcamento->cliente->documento){{ Formato::documento($orcamento->cliente->documento) }} · @endif
                @if($orcamento->cliente->cidade){{ $orcamento->cliente->cidade }}@if($orcamento->cliente->uf)/{{ $orcamento->cliente->uf }}@endif @endif
                @if($orcamento->cliente->telefone) · {{ $orcamento->cliente->telefone }} @endif
            </div>
        @endif
    </div>

    <table class="itens">
        <thead>
            <tr>
                <th>Cód.</th><th>Descrição</th><th class="num">Qtd.</th><th class="num">Preço unit.</th><th class="num">Desc.</th><th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orcamento->itens as $item)
                <tr>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->descricao }}</td>
                    <td class="num">{{ Formato::quantidade($item->quantidade) }} {{ $item->unidade }}</td>
                    <td class="num">{{ Formato::preco($item->preco_unitario) }}</td>
                    <td class="num">{{ (float) $item->desconto_percentual > 0 ? Formato::percentual($item->desconto_percentual) : '' }}</td>
                    <td class="num">{{ Formato::moeda($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Total: {{ Formato::moeda($orcamento->total) }}</div>

    @if($orcamento->condicao_pagamento || $orcamento->observacoes)
        <div class="bloco" style="margin-top:14px">
            @if($orcamento->condicao_pagamento)
                <div class="rotulo">Condição de pagamento</div>
                <div>{{ $orcamento->condicao_pagamento }}</div>
            @endif
            @if($orcamento->observacoes)
                <div class="rotulo" style="margin-top:8px">Observações</div>
                <div>{!! nl2br(e($orcamento->observacoes)) !!}</div>
            @endif
        </div>
    @endif

    @if($empresa->rodape_pdf)
        <div class="rodape">{!! nl2br(e($empresa->rodape_pdf)) !!}</div>
    @endif
</body>
</html>
