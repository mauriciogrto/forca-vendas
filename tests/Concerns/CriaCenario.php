<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\PerfilImportacao;
use App\Models\Produto;
use App\Models\User;

/** Monta empresas, usuários e cadastros mínimos para os testes de feature (sem factories). */
trait CriaCenario
{
    protected function criarEmpresa(string $slug = 'a'): Empresa
    {
        return Empresa::create(['nome' => 'Empresa '.strtoupper($slug), 'slug' => $slug, 'desconto_maximo' => 10, 'validade_dias' => 7]);
    }

    protected function criarUsuario(Empresa $empresa, string $perfil = 'representante', ?string $email = null): User
    {
        return User::create([
            'empresa_id' => $empresa->id,
            'name' => ucfirst($perfil).' '.$empresa->slug,
            'email' => $email ?? $perfil.'.'.$empresa->slug.'@teste.local',
            'password' => 'senha12345',
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    protected function criarProduto(Empresa $empresa, string $codigo = 'P1', float $preco = 10.0, array $extra = []): Produto
    {
        return Produto::withoutGlobalScopes()->create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => $codigo,
            'descricao' => 'Produto '.$codigo,
            'unidade' => 'UN',
            'categoria' => 'Geral',
            'preco' => $preco,
            'estoque' => 100,
            'ativo' => true,
        ], $extra));
    }

    protected function criarCliente(Empresa $empresa, string $codigo = 'C1', string $nome = 'Cliente Um'): Cliente
    {
        return Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'codigo' => $codigo,
            'nome' => $nome,
            'cidade' => 'Bento Gonçalves',
            'uf' => 'RS',
        ]);
    }

    protected function criarPerfilImportacao(Empresa $empresa, string $entidade = 'produtos', bool $desativarAusentes = false): PerfilImportacao
    {
        $padrao = collect(PerfilImportacao::padroes())->firstWhere('entidade', $entidade);

        return PerfilImportacao::withoutGlobalScopes()->create(array_replace($padrao, ['empresa_id' => $empresa->id, 'desativar_ausentes' => $desativarAusentes]));
    }
}
