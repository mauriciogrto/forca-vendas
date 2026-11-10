<?php

namespace Tests\Unit;

use App\Support\Formato;
use PHPUnit\Framework\TestCase;

class FormatoTest extends TestCase
{
    public function test_moeda_e_preco(): void
    {
        $this->assertSame('R$ 1.234,50', Formato::moeda(1234.5));
        $this->assertSame('R$ 10,00', Formato::preco(10));
        $this->assertSame('R$ 10,125', Formato::preco(10.125));
    }

    public function test_quantidade_e_percentual_sem_zeros_sobrando(): void
    {
        $this->assertSame('12,5', Formato::quantidade(12.5));
        $this->assertSame('3', Formato::quantidade(3));
        $this->assertSame('1.000', Formato::quantidade(1000));
        $this->assertSame('10%', Formato::percentual(10));
        $this->assertSame('7,5%', Formato::percentual(7.5));
    }

    public function test_documento(): void
    {
        $this->assertSame('12.345.678/0001-90', Formato::documento('12345678000190'));
        $this->assertSame('123.456.789-09', Formato::documento('12345678909'));
        $this->assertSame('abc', Formato::documento('abc'));
        $this->assertSame('', Formato::documento(null));
    }

    public function test_data_vazia(): void
    {
        $this->assertSame('—', Formato::data(null));
    }
}
