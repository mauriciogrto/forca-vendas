<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Importacao;
use App\Models\PerfilImportacao;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Empresa de demonstração para testar o app sem planilha. Senha de todos: demo12345 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstOrCreate(
            ['slug' => 'empresa-demonstracao'],
            ['nome' => 'Empresa Demonstração', 'rodape_pdf' => 'Proposta sujeita à disponibilidade de estoque.', 'cadastro_clientes' => true]
        );

        $usuarios = [
            ['Ana Admin', 'admin@demo.test', 'admin'],
            ['Eduardo Escritório', 'escritorio@demo.test', 'escritorio'],
            ['Gabriela Gestora', 'gestor@demo.test', 'gestor'],
            ['Rafael Representante', 'rep@demo.test', 'representante'],
        ];
        foreach ($usuarios as [$nome, $email, $perfil]) {
            User::firstOrCreate(
                ['email' => $email],
                ['empresa_id' => $empresa->id, 'name' => $nome, 'password' => 'demo12345', 'perfil' => $perfil, 'ativo' => true]
            );
        }

        if (! PerfilImportacao::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            foreach (PerfilImportacao::padroes() as $perfil) {
                PerfilImportacao::create($perfil + ['empresa_id' => $empresa->id]);
            }
        }

        $agora = now();

        $cidades = [['Bento Gonçalves', 'RS'], ['Caxias do Sul', 'RS'], ['Garibaldi', 'RS'], ['Farroupilha', 'RS'], ['Carlos Barbosa', 'RS'], ['Flores da Cunha', 'RS']];
        $sufixos = ['Metalúrgica', 'Móveis', 'Usinagem', 'Distribuidora', 'Engenharia', 'Comércio de Ferragens', 'Indústria de Plásticos', 'Auto Peças'];
        $sobrenomes = ['Rossi', 'Zanella', 'Bertoldo', 'Cavalli', 'Dalla Vecchia', 'Fontana', 'Marchetti', 'Pedrotti', 'Tonet', 'Vanin'];

        for ($i = 1; $i <= 40; $i++) {
            [$cidade, $uf] = $cidades[$i % count($cidades)];
            $nome = $sobrenomes[$i % count($sobrenomes)].' '.$sufixos[$i % count($sufixos)].' Ltda';
            Cliente::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => sprintf('C%04d', $i)],
                [
                    'nome' => $nome,
                    'documento' => sprintf('%02d.%03d.%03d/0001-%02d', 10 + $i % 80, 100 + $i, 200 + $i, 10 + $i % 90),
                    'telefone' => sprintf('(54) 3%03d-%04d', 100 + $i, 1000 + $i * 7 % 9000),
                    'email' => 'contato'.$i.'@exemplo.com.br',
                    'endereco' => 'Rua das Indústrias, '.(100 + $i * 3),
                    'cidade' => $cidade,
                    'uf' => $uf,
                    'importado_em' => $agora,
                ]
            );
        }

        $familias = [
            ['Parafuso sextavado', 'Fixação', 'CX', 18.5],
            ['Porca sextavada', 'Fixação', 'CX', 12.9],
            ['Arruela lisa', 'Fixação', 'CX', 9.4],
            ['Chapa de aço 2mm', 'Matéria-prima', 'KG', 7.8],
            ['Tubo industrial', 'Matéria-prima', 'MT', 32.0],
            ['Disco de corte', 'Consumíveis', 'UN', 6.5],
            ['Eletrodo de solda', 'Consumíveis', 'KG', 41.9],
            ['Rolamento', 'Mecânica', 'UN', 54.0],
        ];
        $medidas = ['M6', 'M8', 'M10', 'M12', 'M16', 'M20', 'M24', 'M30', 'M36', 'M42'];

        $n = 0;
        foreach ($familias as $f => [$descricao, $grupo, $unidade, $base]) {
            foreach ($medidas as $m => $medida) {
                $n++;
                Produto::withoutGlobalScopes()->updateOrCreate(
                    ['empresa_id' => $empresa->id, 'codigo' => sprintf('P%04d', $n)],
                    [
                        'descricao' => $descricao.' '.$medida,
                        'categoria' => $grupo,
                        'unidade' => $unidade,
                        'preco' => round($base * (1 + $m * 0.12), 2),
                        'estoque' => ($n * 37) % 400,
                        'ativo' => true,
                        'importado_em' => $agora,
                    ]
                );
            }
        }

        foreach (['clientes', 'produtos'] as $entidade) {
            if (! Importacao::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('entidade', $entidade)->exists()) {
                Importacao::withoutGlobalScopes()->create([
                    'empresa_id' => $empresa->id,
                    'entidade' => $entidade,
                    'arquivo_original' => 'dados-de-demonstracao',
                    'status' => 'publicada',
                    'publicada_em' => $agora,
                ]);
            }
        }
    }
}
