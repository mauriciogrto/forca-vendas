<?php

namespace App\Services\Importacao;

/** Conversões tolerantes para os formatos que os ERPs brasileiros costumam exportar. */
final class ConversorValores
{
    /**
     * Converte "1.234,56", "1234,56", "R$ 1.234,56", "1234.56" ou números nativos do Excel.
     * Retorna null se não for um número.
     */
    public static function numero(mixed $valor): ?float
    {
        if ($valor === null) {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(["\u{00A0}", ' '], '', $texto);
        $texto = preg_replace('/^(R\$|r\$)/', '', $texto);

        $negativo = false;
        if (preg_match('/^\((.*)\)$/', $texto, $m)) { // contabilidade: (10,00)
            $negativo = true;
            $texto = $m[1];
        }
        if (str_ends_with($texto, '-')) { // 10,00-
            $negativo = true;
            $texto = substr($texto, 0, -1);
        }
        if (str_starts_with($texto, '-')) {
            $negativo = true;
            $texto = substr($texto, 1);
        }

        if ($texto === '' || ! preg_match('/^[0-9.,]+$/', $texto)) {
            return null;
        }

        $temVirgula = str_contains($texto, ',');
        $temPonto = str_contains($texto, '.');

        if ($temVirgula && $temPonto) {
            // O último separador é o decimal.
            if (strrpos($texto, ',') > strrpos($texto, '.')) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($temVirgula) {
            if (substr_count($texto, ',') > 1) {
                $texto = str_replace(',', '', $texto);
            } else {
                $texto = str_replace(',', '.', $texto);
            }
        } elseif ($temPonto) {
            // "1.234" ou "1.234.567" = milhar; "12.5" = decimal.
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
                $texto = str_replace('.', '', $texto);
            } elseif (substr_count($texto, '.') > 1) {
                return null;
            }
        }

        if (! is_numeric($texto)) {
            return null;
        }

        $numero = (float) $texto;

        return $negativo ? -$numero : $numero;
    }

    /** Texto limpo ou null. Números inteiros do Excel (1001.0) viram "1001". */
    public static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
            $valor = (int) $valor;
        }

        $texto = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');

        return $texto === '' ? null : $texto;
    }

    /** Minúsculas, sem acentos e sem espaços nas pontas, para comparar cabeçalhos. */
    public static function normalizar(mixed $valor): string
    {
        $texto = mb_strtolower((string) (self::texto($valor) ?? ''), 'UTF-8');

        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'º' => 'o', 'ª' => 'a',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }
}
