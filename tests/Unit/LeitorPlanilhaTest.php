<?php

namespace Tests\Unit;

use App\Services\Importacao\ImportacaoException;
use App\Services\Importacao\LeitorPlanilha;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class LeitorPlanilhaTest extends TestCase
{
    /** @var list<string> */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $arquivo) {
            @unlink($arquivo);
        }
    }

    private function temporario(string $conteudo, string $sufixo): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'fv').$sufixo;
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;

        return $caminho;
    }

    public function test_csv_utf8_com_ponto_e_virgula_e_bom(): void
    {
        $csv = "\xEF\xBB\xBFCódigo;Descrição;Preço\n1001;Parafuso M8;\"1.234,50\"\n\n1002;Porca;2,5\n";
        $linhas = iterator_to_array(LeitorPlanilha::linhas($this->temporario($csv, '.csv'), 'csv'));

        $this->assertSame([1, 2, 4], array_keys($linhas));
        $this->assertSame(['Código', 'Descrição', 'Preço'], $linhas[1]);
        $this->assertSame(['1001', 'Parafuso M8', '1.234,50'], $linhas[2]);
        $this->assertSame(['1002', 'Porca', '2,5'], $linhas[4]);
    }

    public function test_csv_windows_1252_e_virgula(): void
    {
        $utf8 = "Código,Descrição\n1,Ação\n";
        $ansi = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
        $linhas = iterator_to_array(LeitorPlanilha::linhas($this->temporario($ansi, '.csv'), 'csv'));

        $this->assertSame(['Código', 'Descrição'], $linhas[1]);
        $this->assertSame(['1', 'Ação'], $linhas[2]);
    }

    public function test_csv_vazio_gera_erro(): void
    {
        $this->expectException(ImportacaoException::class);
        iterator_to_array(LeitorPlanilha::linhas($this->temporario('', '.csv'), 'csv'));
    }

    public function test_xlsx_com_textos_compartilhados_numeros_e_celulas_vazias(): void
    {
        $caminho = $this->criarXlsx();
        $linhas = iterator_to_array(LeitorPlanilha::linhas($caminho, 'xlsx'));

        $this->assertSame([1, 3], array_keys($linhas)); // linha 2 vazia é omitida
        $this->assertSame(['Código', 'Descrição', 'Preço'], $linhas[1]);
        $this->assertSame([1001, null, 12.5], $linhas[3]);
    }

    public function test_xlsx_invalido(): void
    {
        $this->expectException(ImportacaoException::class);
        iterator_to_array(LeitorPlanilha::linhas($this->temporario('isto nao e um zip', '.xlsx'), 'xlsx'));
    }

    public function test_xls_antigo_nao_suportado(): void
    {
        $this->expectException(ImportacaoException::class);
        iterator_to_array(LeitorPlanilha::linhas($this->temporario('x', '.xls'), 'xls'));
    }

    public function test_indice_da_coluna(): void
    {
        $this->assertSame(0, LeitorPlanilha::indiceDaColuna('A1'));
        $this->assertSame(2, LeitorPlanilha::indiceDaColuna('C5'));
        $this->assertSame(26, LeitorPlanilha::indiceDaColuna('AA10'));
    }

    private function criarXlsx(): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'fv').'.xlsx';
        $this->temporarios[] = $caminho;

        $zip = new ZipArchive();
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Dados" sheetId="1" r:id="rId7"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId7" Type="worksheet" Target="worksheets/dados.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Código</t></si><si><t>Descrição</t></si><si><r><t>Pre</t></r><r><t>ço</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/dados.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
            .'<row r="2"><c r="A2"/></row>'
            .'<row r="3"><c r="A3"><v>1001</v></c><c r="C3"><v>12.5</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        return $caminho;
    }
}
