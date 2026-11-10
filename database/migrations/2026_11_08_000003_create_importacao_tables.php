<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perfil = receita de como ler a planilha de um ERP (qual linha é o cabeçalho,
        // qual coluna alimenta qual campo). Cada ERP/relatório vira um perfil, sem código novo.
        Schema::create('perfis_importacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('entidade', 20); // clientes | produtos
            $table->unsignedSmallInteger('linha_cabecalho')->default(1);
            $table->json('mapeamento'); // { campo: "nome da coluna na planilha" }
            // Só produtos: o que não vier na planilha fica inativo (relatório completo do catálogo)
            $table->boolean('desativar_ausentes')->default(false);
            $table->timestamps();
        });

        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('perfil_importacao_id')->nullable()->constrained('perfis_importacao')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidade', 20);
            $table->string('arquivo_original');
            $table->string('caminho')->nullable();
            // previa -> publicada | descartada
            $table->string('status', 20)->default('previa');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('novos')->default(0);
            $table->unsignedInteger('atualizados')->default(0);
            $table->unsignedInteger('com_erro')->default(0);
            $table->json('resumo')->nullable();
            $table->timestamp('publicada_em')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'entidade', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
        Schema::dropIfExists('perfis_importacao');
    }
};
