<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\PerfilImportacao;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CriarEmpresa extends Command
{
    protected $signature = 'empresa:criar
        {nome : Nome da empresa}
        {email : E-mail do administrador}
        {--nome-admin= : Nome do administrador (padrão: Administrador)}
        {--senha= : Senha inicial (se omitida, é gerada e exibida)}';

    protected $description = 'Cria uma empresa (tenant) com o administrador e os perfis de importação iniciais.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("Já existe um usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        $senha = $this->option('senha') ?: Str::password(12, symbols: false);

        DB::transaction(function () use ($email, $senha) {
            $nome = (string) $this->argument('nome');

            $slug = Str::slug($nome);
            $base = $slug;
            for ($i = 2; Empresa::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            $empresa = Empresa::create(['nome' => $nome, 'slug' => $slug]);

            User::create([
                'empresa_id' => $empresa->id,
                'name' => $this->option('nome-admin') ?: 'Administrador',
                'email' => $email,
                'password' => $senha,
                'perfil' => 'admin',
                'ativo' => true,
            ]);

            foreach (PerfilImportacao::padroes() as $perfil) {
                PerfilImportacao::create($perfil + ['empresa_id' => $empresa->id]);
            }
        });

        $this->info('Empresa criada.');
        $this->line("  Acesso: {$email}");
        $this->line("  Senha:  {$senha}");

        return self::SUCCESS;
    }
}
