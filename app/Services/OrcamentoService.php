<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Orcamento;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrcamentoService
{
    /**
     * @param  array{cliente_id?: int|null, cliente_nome: string, condicao_pagamento?: ?string, observacoes?: ?string, itens: list<array<string, mixed>>}  $dados
     */
    public function criar(User $usuario, array $dados): Orcamento
    {
        return DB::transaction(function () use ($usuario, $dados) {
            // Trava a empresa para numerar sem repetição quando dois representantes salvam ao mesmo tempo.
            $empresa = Empresa::query()->lockForUpdate()->findOrFail($usuario->empresa_id);
            $numero = (int) $empresa->proximo_numero;
            $empresa->increment('proximo_numero');

            $orcamento = Orcamento::create([
                'empresa_id' => $usuario->empresa_id,
                'user_id' => $usuario->id,
                'cliente_id' => $dados['cliente_id'] ?? null,
                'numero' => $numero,
                'tipo' => 'orcamento',
                'status' => 'rascunho',
                'cliente_nome' => $dados['cliente_nome'],
                'condicao_pagamento' => $dados['condicao_pagamento'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $this->gravarItens($orcamento, $dados['itens']);

            return $orcamento->load('itens');
        });
    }

    public function atualizar(Orcamento $orcamento, array $dados): Orcamento
    {
        return DB::transaction(function () use ($orcamento, $dados) {
            $orcamento->update([
                'cliente_id' => $dados['cliente_id'] ?? null,
                'cliente_nome' => $dados['cliente_nome'],
                'condicao_pagamento' => $dados['condicao_pagamento'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $this->gravarItens($orcamento, $dados['itens']);

            return $orcamento->load('itens');
        });
    }

    public function marcarEnviado(Orcamento $orcamento): void
    {
        $orcamento->update(['status' => 'enviado', 'enviado_em' => $orcamento->enviado_em ?? now()]);
    }

    public function converterEmPedido(Orcamento $orcamento): void
    {
        $orcamento->update(['tipo' => 'pedido', 'status' => 'fechado', 'fechado_em' => now()]);
    }

    public function cancelar(Orcamento $orcamento): void
    {
        $orcamento->update(['status' => 'cancelado']);
    }

    public static function totalDoItem(float $quantidade, float $preco, float $descontoPercentual): float
    {
        return round($quantidade * $preco * (1 - $descontoPercentual / 100), 2);
    }

    /**
     * Preço, código e descrição vêm sempre do cadastro (nunca do navegador),
     * então o representante só controla quantidade e desconto.
     *
     * @param  list<array<string, mixed>>  $itens
     */
    private function gravarItens(Orcamento $orcamento, array $itens): void
    {
        $ids = array_map(static fn ($i) => (int) $i['produto_id'], $itens);
        $produtos = Produto::ativos()->whereIn('id', $ids)->get()->keyBy('id');

        $orcamento->itens()->delete();

        $total = 0.0;
        foreach (array_values($itens) as $ordem => $item) {
            /** @var Produto|null $produto */
            $produto = $produtos->get((int) $item['produto_id']);
            if (! $produto) {
                throw ValidationException::withMessages([
                    'itens' => 'Um dos produtos não está mais disponível. Remova-o e tente novamente.',
                ]);
            }

            $quantidade = (float) $item['quantidade'];
            $desconto = (float) ($item['desconto_percentual'] ?? 0);
            $linha = self::totalDoItem($quantidade, (float) $produto->preco, $desconto);

            $orcamento->itens()->create([
                'produto_id' => $produto->id,
                'codigo' => $produto->codigo,
                'descricao' => $produto->descricao,
                'unidade' => $produto->unidade,
                'quantidade' => $quantidade,
                'preco_unitario' => $produto->preco,
                'desconto_percentual' => $desconto,
                'total' => $linha,
                'ordem' => $ordem,
            ]);

            $total += $linha;
        }

        $orcamento->update(['total' => round($total, 2)]);
    }
}
