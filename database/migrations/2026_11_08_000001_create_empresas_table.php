<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            // Regras comerciais configuráveis por empresa
            $table->decimal('desconto_maximo', 5, 2)->default(10);
            $table->unsignedSmallInteger('validade_dias')->default(7);
            $table->text('rodape_pdf')->nullable();
            // Numeração sequencial de orçamentos/pedidos por empresa
            $table->unsignedBigInteger('proximo_numero')->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
            // admin | gestor | escritorio | representante
            $table->string('perfil', 20)->default('representante')->after('password');
            $table->boolean('ativo')->default(true)->after('perfil');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->dropColumn(['perfil', 'ativo']);
        });

        Schema::dropIfExists('empresas');
    }
};
