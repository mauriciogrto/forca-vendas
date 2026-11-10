<?php

namespace App\Services\Importacao;

/**
 * Transforma uma linha crua da planilha em um registro validado,
 * segundo o mapeamento do perfil (campo => nome da coluna).
 */
final class Mapeador
{
    /**
     * Descobre a posição de cada campo no cabeçalho.
     *
     * @param  array<int, mixed>  $cabecalhos  células da linha de cabeçalho
     * @param  array<string, string|null>  $mapeamento  campo => nome da coluna
     * @return array<string, int> campo => índice da coluna
     */
    public static function resolverColunas(array $cabecalhos, array $mapeamento, string $entidade): array
    {
        $indice = [];
        foreach ($cabecalhos as $posicao => $cabecalho) {
            $chave = ConversorValores::normalizar($cabecalho);
            if ($chave !== '' && ! isset($indice[$chave])) {
                $indice[$chave] = $posicao;
            }
        }

        $encontradas = array_values(array_filter(array_map(
            static fn ($c) => ConversorValores::texto($c),
            $cabecalhos
        )));

        $colunas = [];
        foreach (Campos::para($entidade) as $campo => $definicao) {
            $nomeColuna = ConversorValores::texto($mapeamento[$campo] ?? null);

            if ($nomeColuna === null) {
                if ($definicao['obrigatorio']) {
                    throw new ImportacaoException("O perfil não mapeia a coluna obrigatória \"{$definicao['rotulo']}\".");
                }
                continue;
            }

            $chave = ConversorValores::normalizar($nomeColuna);
            if (! isset($indice[$chave])) {
                $lista = $encontradas === [] ? 'nenhuma (confira a linha do cabeçalho no perfil)' : implode(', ', $encontradas);
                throw new ImportacaoException("Coluna \"{$nomeColuna}\" ({$definicao['rotulo']}) não encontrada na planilha. Colunas encontradas: {$lista}.");
            }

            $colunas[$campo] = $indice[$chave];
        }

        return $colunas;
    }

    /**
     * @param  array<int, mixed>  $celulas
     * @param  array<string, int>  $colunas
     * @return array{dados: array<string, mixed>, erros: list<string>}
     */
    public static function linha(array $celulas, array $colunas, string $entidade): array
    {
        $dados = [];
        $erros = [];

        foreach (Campos::para($entidade) as $campo => $definicao) {
            if (! isset($colunas[$campo])) {
                continue;
            }

            $bruto = $celulas[$colunas[$campo]] ?? null;
            $rotulo = $definicao['rotulo'];

            switch ($campo) {
                case 'preco':
                case 'estoque':
                    $texto = ConversorValores::texto($bruto);
                    if ($texto === null) {
                        $dados[$campo] = null;
                        break;
                    }
                    $numero = ConversorValores::numero($bruto);
                    if ($numero === null) {
                        $erros[] = "{$rotulo} inválido: \"{$texto}\".";
                        $dados[$campo] = null;
                        break;
                    }
                    if ($campo === 'preco' && $numero < 0) {
                        $erros[] = "{$rotulo} negativo: \"{$texto}\".";
                    }
                    $dados[$campo] = $numero;
                    break;

                case 'uf':
                    $texto = ConversorValores::texto($bruto);
                    if ($texto === null) {
                        $dados[$campo] = null;
                        break;
                    }
                    $texto = strtoupper($texto);
                    if (! preg_match('/^[A-Z]{2}$/', $texto)) {
                        $erros[] = "UF inválida: \"{$texto}\".";
                        $dados[$campo] = null;
                        break;
                    }
                    $dados[$campo] = $texto;
                    break;

                case 'email':
                    $texto = ConversorValores::texto($bruto);
                    // E-mail malformado não derruba a linha: só deixa de ser importado.
                    $dados[$campo] = ($texto !== null && filter_var($texto, FILTER_VALIDATE_EMAIL)) ? $texto : null;
                    break;

                case 'codigo':
                    $texto = ConversorValores::texto($bruto);
                    if ($texto !== null && mb_strlen($texto) > 60) {
                        $erros[] = "{$rotulo} com mais de 60 caracteres.";
                    }
                    $dados[$campo] = $texto;
                    break;

                case 'documento':
                    $texto = ConversorValores::texto($bruto);
                    $dados[$campo] = $texto === null ? null : mb_substr($texto, 0, 20);
                    break;

                case 'unidade':
                    $texto = ConversorValores::texto($bruto);
                    $dados[$campo] = $texto === null ? null : mb_substr($texto, 0, 10);
                    break;

                case 'telefone':
                    $texto = ConversorValores::texto($bruto);
                    $dados[$campo] = $texto === null ? null : mb_substr($texto, 0, 40);
                    break;

                default:
                    $texto = ConversorValores::texto($bruto);
                    $dados[$campo] = $texto === null ? null : mb_substr($texto, 0, 255);
            }

            if ($definicao['obrigatorio'] && ($dados[$campo] ?? null) === null && ! self::jaTemErroDoCampo($erros, $rotulo)) {
                $erros[] = "{$rotulo} em branco.";
            }
        }

        return ['dados' => $dados, 'erros' => $erros];
    }

    /** @param list<string> $erros */
    private static function jaTemErroDoCampo(array $erros, string $rotulo): bool
    {
        foreach ($erros as $erro) {
            if (str_starts_with($erro, $rotulo)) {
                return true;
            }
        }

        return false;
    }
}
