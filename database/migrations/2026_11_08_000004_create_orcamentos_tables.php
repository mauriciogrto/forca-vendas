<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orçamentos e pedidos vivem só no app (modo consulta). tipo: orcamento | pedido.
        // status: rascunho | enviado | fechado | cancelado
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->unsignedBigInteger('numero');
            $table->string('tipo', 10)->default('orcamento');
            $table->string('status', 15)->default('rascunho');
            // Cópia do nome: o documento continua legível se o cliente sumir da próxima importação
            $table->string('cliente_nome');
            $table->string('condicao_pagamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'user_id', 'created_at']);
            $table->index(['empresa_id', 'tipo', 'status']);
        });

        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            // Cópia do produto no momento da venda (preço não muda depois)
            $table->string('codigo', 60);
            $table->string('descricao');
            $table->string('unidade', 10)->nullable();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 14, 4);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
        Schema::dropIfExists('orcamentos');
    }
};
