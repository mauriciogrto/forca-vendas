<?php

namespace App\Services\Importacao;

use App\Models\Cliente;
use App\Models\Importacao;
use App\Models\PerfilImportacao;
use App\Models\Produto;
use App\Models\User;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fluxo: armazenar -> gerarPrevia (nada é gravado nas tabelas finais) -> publicar (upsert por código do ERP).
 */
class ImportadorService
{
    private const LOTE = 500;

    public function armazenar(UploadedFile $arquivo, PerfilImportacao $perfil, User $usuario): Importacao
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        $caminho = $arquivo->storeAs(
            'importacoes/'.$usuario->empresa_id,
            Str::uuid().'.'.$extensao,
            'local'
        );

        return Importacao::create([
            'empresa_id' => $usuario->empresa_id,
            'perfil_importacao_id' => $perfil->id,
            'user_id' => $usuario->id,
            'entidade' => $perfil->entidade,
            'arquivo_original' => $arquivo->getClientOriginalName(),
            'caminho' => $caminho,
            'status' => 'previa',
        ]);
    }

    public function gerarPrevia(Importacao $importacao): Importacao
    {
        $perfil = $this->perfilDe($importacao);
        $modelo = $this->modeloDe($perfil->entidade);

        $existentes = array_flip(
            $modelo::withoutGlobalScopes()
                ->where('empresa_id', $importacao->empresa_id)
                ->pluck('codigo')
                ->all()
        );

        $total = $novos = $atualizados = $comErro = 0;
        $erros = [];
        $amostra = [];

        foreach ($this->varrer($importacao, $perfil) as $linha) {
            $total++;

            if ($linha['dados'] === null) {
                $comErro++;
                if (count($erros) < 50) {
                    $erros[] = ['linha' => $linha['linha'], 'mensagem' => implode(' ', $linha['erros'])];
                }
                continue;
            }

            isset($existentes[$linha['dados']['codigo']]) ? $atualizados++ : $novos++;

            if (count($amostra) < 10) {
                $amostra[] = $linha['dados'];
            }
        }

        if ($total === 0) {
            throw new ImportacaoException('Nenhuma linha de dados encontrada abaixo do cabeçalho. Confira a linha do cabeçalho no perfil e se os dados estão na primeira aba da planilha.');
        }

        $importacao->update([
            'total_linhas' => $total,
            'novos' => $novos,
            'atualizados' => $atualizados,
            'com_erro' => $comErro,
            'resumo' => [
                'erros' => $erros,
                'amostra' => $amostra,
                'campos' => array_keys($amostra[0] ?? []),
            ],
        ]);

        return $importacao;
    }

    public function publicar(Importacao $importacao): Importacao
    {
        if ($importacao->status !== 'previa') {
            throw new ImportacaoException('Esta importação já foi publicada ou descartada.');
        }
        if ($importacao->validos === 0) {
            throw new ImportacaoException('Não há linhas válidas para publicar.');
        }

        $perfil = $this->perfilDe($importacao);
        $modelo = $this->modeloDe($perfil->entidade);
        $produtos = $perfil->entidade === 'produtos';
        $agora = now()->setMicroseconds(0);

        DB::transaction(function () use ($importacao, $perfil, $modelo, $produtos, $agora) {
            $lote = [];
            $campos = null;

            foreach ($this->varrer($importacao, $perfil) as $linha) {
                if ($linha['dados'] === null) {
                    continue;
                }

                $campos ??= array_keys($linha['dados']);

                $registro = $linha['dados'] + [
                    'empresa_id' => $importacao->empresa_id,
                    'importado_em' => $agora,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
                if ($produtos) {
                    $registro['ativo'] = true;
                }

                $lote[] = $registro;

                if (count($lote) >= self::LOTE) {
                    $this->gravar($modelo, $lote, $campos, $produtos);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $this->gravar($modelo, $lote, $campos ?? [], $produtos);
            }

            if ($produtos && $perfil->desativar_ausentes) {
                Produto::withoutGlobalScopes()
                    ->where('empresa_id', $importacao->empresa_id)
                    ->where(function ($q) use ($agora) {
                        $q->whereNull('importado_em')->orWhere('importado_em', '<', $agora);
                    })
                    ->update(['ativo' => false]);
            }

            $importacao->update(['status' => 'publicada', 'publicada_em' => $agora]);
        });

        $this->removerArquivo($importacao);

        return $importacao;
    }

    public function descartar(Importacao $importacao): void
    {
        $this->removerArquivo($importacao);
        $importacao->update(['status' => 'descartada']);
    }

    public function removerArquivo(Importacao $importacao): void
    {
        if ($importacao->caminho) {
            Storage::disk('local')->delete($importacao->caminho);
            $importacao->update(['caminho' => null]);
        }
    }

    /**
     * Lê o arquivo do início ao fim e devolve cada linha já mapeada e validada.
     *
     * @return Generator<int, array{linha: int, dados: array<string, mixed>|null, erros: list<string>}>
     */
    private function varrer(Importacao $importacao, PerfilImportacao $perfil): Generator
    {
        if (! $importacao->caminho || ! Storage::disk('local')->exists($importacao->caminho)) {
            throw new ImportacaoException('O arquivo desta importação não está mais disponível. Envie a planilha novamente.');
        }

        $arquivo = Storage::disk('local')->path($importacao->caminho);
        $extensao = pathinfo($importacao->caminho, PATHINFO_EXTENSION);
        $linhaCabecalho = max(1, (int) $perfil->linha_cabecalho);

        $colunas = null;
        $vistos = [];

        foreach (LeitorPlanilha::linhas($arquivo, $extensao) as $numero => $celulas) {
            if ($numero < $linhaCabecalho) {
                continue;
            }

            if ($numero === $linhaCabecalho) {
                $colunas = Mapeador::resolverColunas($celulas, $perfil->mapeamento ?? [], $perfil->entidade);
                continue;
            }

            if ($colunas === null) {
                throw new ImportacaoException("A linha {$linhaCabecalho}, indicada como cabeçalho no perfil, está vazia na planilha.");
            }

            $resultado = Mapeador::linha($celulas, $colunas, $perfil->entidade);
            $erros = $resultado['erros'];
            $codigo = $resultado['dados']['codigo'] ?? null;

            if ($codigo !== null) {
                if (isset($vistos[$codigo])) {
                    $erros[] = "Código \"{$codigo}\" repetido (já aparece na linha {$vistos[$codigo]}).";
                } else {
                    $vistos[$codigo] = $numero;
                }
            }

            yield [
                'linha' => $numero,
                'dados' => $erros === [] ? $resultado['dados'] : null,
                'erros' => $erros,
            ];
        }

        if ($colunas === null) {
            throw new ImportacaoException("Não encontrei a linha {$linhaCabecalho} (cabeçalho) na planilha.");
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @param  list<array<string, mixed>>  $lote
     * @param  list<string>  $campos
     */
    private function gravar(string $modelo, array $lote, array $campos, bool $produtos): void
    {
        // Só atualiza o que o perfil mapeia: campos não mapeados mantêm o valor atual.
        $atualizar = array_values(array_diff($campos, ['codigo']));
        $atualizar[] = 'importado_em';
        if ($produtos) {
            $atualizar[] = 'ativo';
        }

        $modelo::withoutGlobalScopes()->upsert($lote, ['empresa_id', 'codigo'], $atualizar);

        // Cliente cadastrado em campo cujo código chegou do ERP passa a valer como cliente do ERP.
        if (! $produtos && $lote !== []) {
            Cliente::withoutGlobalScopes()
                ->where('empresa_id', $lote[0]['empresa_id'])
                ->whereIn('codigo', array_column($lote, 'codigo'))
                ->where('situacao', 'pendente')
                ->update(['situacao' => 'ativo', 'conferido_em' => now()]);
        }
    }

    private function perfilDe(Importacao $importacao): PerfilImportacao
    {
        $perfil = $importacao->perfil;
        if (! $perfil) {
            throw new ImportacaoException('O perfil de importação desta planilha foi excluído.');
        }

        return $perfil;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function modeloDe(string $entidade): string
    {
        return $entidade === 'clientes' ? Cliente::class : Produto::class;
    }
}
