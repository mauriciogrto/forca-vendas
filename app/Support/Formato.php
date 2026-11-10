<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Formatação de valores para tela e PDF (padrão brasileiro). */
final class Formato
{
    public static function moeda(mixed $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /** Preço unitário: mostra 2 casas e, se houver, até 4 (sem zeros sobrando). */
    public static function preco(mixed $valor): string
    {
        $texto = rtrim(rtrim(number_format((float) $valor, 4, ',', '.'), '0'), ',');
        $partes = explode(',', $texto);
        $decimais = isset($partes[1]) ? strlen($partes[1]) : 0;

        return 'R$ '.number_format((float) $valor, max(2, $decimais), ',', '.');
    }

    /** Quantidade sem zeros desnecessários: 12,500 -> 12,5 */
    public static function quantidade(mixed $valor): string
    {
        $texto = number_format((float) $valor, 3, ',', '.');

        return rtrim(rtrim($texto, '0'), ',');
    }

    public static function percentual(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',').'%';
    }

    public static function data(?CarbonInterface $data, bool $comHora = false): string
    {
        if (! $data) {
            return '—';
        }

        return $data->timezone(config('app.timezone'))->format($comHora ? 'd/m/Y H:i' : 'd/m/Y');
    }

    public static function documento(?string $documento): string
    {
        $d = preg_replace('/\D+/', '', (string) $documento);

        if (strlen($d) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        }
        if (strlen($d) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        }

        return (string) $documento;
    }
}
