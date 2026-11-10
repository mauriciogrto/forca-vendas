<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Desligado = modo consulta: clientes só chegam por importação do ERP.
            $table->boolean('cadastro_clientes')->default(false)->after('rodape_pdf');
        });

        Schema::table('clientes', function (Blueprint $table) {
            // ativo = veio do ERP ou já foi conferido; pendente = cadastrado em campo, aguardando o escritório
            $table->string('situacao', 10)->default('ativo')->after('uf')->index();
            $table->foreignId('cadastrado_por')->nullable()->after('situacao')->constrained('users')->nullOnDelete();
            $table->timestamp('conferido_em')->nullable()->after('cadastrado_por');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cadastrado_por');
            $table->dropColumn(['situacao', 'conferido_em']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('cadastro_clientes');
        });
    }
};
