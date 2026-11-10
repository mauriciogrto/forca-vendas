<?php

namespace Tests\Unit;

use App\Services\Importacao\ConversorValores;
use PHPUnit\Framework\TestCase;

class ConversorValoresTest extends TestCase
{
    public function test_numero_em_formatos_brasileiros(): void
    {
        $this->assertSame(1234.56, ConversorValores::numero('1.234,56'));
        $this->assertSame(1234.56, ConversorValores::numero('1234,56'));
        $this->assertSame(1234.56, ConversorValores::numero('R$ 1.234,56'));
        $this->assertSame(1234.56, ConversorValores::numero('1,234.56'));
        $this->assertSame(1234.56, ConversorValores::numero('1234.56'));
        $this->assertSame(12.5, ConversorValores::numero('12.5'));
        $this->assertSame(1234.0, ConversorValores::numero('1.234'));
        $this->assertSame(1234567.0, ConversorValores::numero('1.234.567'));
        $this->assertSame(0.5, ConversorValores::numero('0,5'));
    }

    public function test_numero_negativo_e_nativo(): void
    {
        $this->assertSame(-10.0, ConversorValores::numero('-10,00'));
        $this->assertSame(-10.0, ConversorValores::numero('(10,00)'));
        $this->assertSame(-10.0, ConversorValores::numero('10,00-'));
        $this->assertSame(7.0, ConversorValores::numero(7));
        $this->assertSame(7.25, ConversorValores::numero(7.25));
    }

    public function test_numero_invalido_ou_vazio(): void
    {
        $this->assertNull(ConversorValores::numero(null));
        $this->assertNull(ConversorValores::numero(''));
        $this->assertNull(ConversorValores::numero('abc'));
        $this->assertNull(ConversorValores::numero('12a'));
        $this->assertNull(ConversorValores::numero('1.2.3'));
    }

    public function test_texto(): void
    {
        $this->assertNull(ConversorValores::texto(null));
        $this->assertNull(ConversorValores::texto('   '));
        $this->assertSame('Parafuso M8', ConversorValores::texto("  Parafuso   M8 \n"));
        $this->assertSame('1001', ConversorValores::texto(1001.0));
        $this->assertSame('1001', ConversorValores::texto(1001));
        $this->assertSame('7891234567890', ConversorValores::texto(7891234567890.0));
    }

    public function test_normalizar_cabecalhos(): void
    {
        $this->assertSame('preco unitario', ConversorValores::normalizar('  Preço  Unitário '));
        $this->assertSame('codigo', ConversorValores::normalizar('CÓDIGO'));
        $this->assertSame('valor r', ConversorValores::normalizar('Valor (R$)'));
        $this->assertSame('', ConversorValores::normalizar(null));
    }
}
