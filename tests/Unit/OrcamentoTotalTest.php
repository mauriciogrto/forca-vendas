<?php

namespace Tests\Unit;

use App\Services\OrcamentoService;
use PHPUnit\Framework\TestCase;

class OrcamentoTotalTest extends TestCase
{
    public function test_total_sem_desconto(): void
    {
        $this->assertSame(25.0, OrcamentoService::totalDoItem(2.5, 10.0, 0.0));
    }

    public function test_total_com_desconto_e_arredondamento(): void
    {
        $this->assertSame(90.0, OrcamentoService::totalDoItem(1, 100.0, 10.0));
        $this->assertSame(3.33, OrcamentoService::totalDoItem(3, 1.11, 0.0));
        $this->assertSame(0.0, OrcamentoService::totalDoItem(5, 10.0, 100.0));
    }
}
