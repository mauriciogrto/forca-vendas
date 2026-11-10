<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dados vindos do ERP do cliente (importação de planilha ou, no futuro, API).
        // A chave de negócio é o código do ERP: reimportar atualiza, não duplica.
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 60);
            $table->string('nome');
            $table->string('documento', 20)->nullable();
            $table->string('telefone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->timestamp('importado_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'nome']);
            $table->index(['empresa_id', 'documento']);
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 60);
            $table->string('descricao');
            $table->string('unidade', 10)->nullable();
            $table->string('categoria')->nullable();
            $table->decimal('preco', 14, 4)->default(0);
            // null = a planilha não informa estoque
            $table->decimal('estoque', 14, 3)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('importado_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'descricao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('clientes');
    }
};
