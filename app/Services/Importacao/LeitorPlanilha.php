<?php

namespace App\Services\Importacao;

use Generator;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Lê planilhas .xlsx e .csv sem dependências externas.
 * Entrega linha a linha: [número da linha (1 = primeira) => lista de células].
 * Células vazias vêm como null; números do Excel vêm como int/float.
 */
final class LeitorPlanilha
{
    public static function extensaoSuportada(string $extensao): bool
    {
        return in_array(strtolower($extensao), ['xlsx', 'csv', 'txt'], true);
    }

    /** @return Generator<int, array<int, mixed>> */
    public static function linhas(string $caminho, string $extensao): Generator
    {
        $extensao = strtolower($extensao);

        if ($extensao === 'xlsx') {
            yield from self::linhasXlsx($caminho);

            return;
        }

        if (in_array($extensao, ['csv', 'txt'], true)) {
            yield from self::linhasCsv($caminho);

            return;
        }

        if ($extensao === 'xls') {
            throw new ImportacaoException('Arquivos .xls (Excel antigo) não são suportados. Salve a planilha como .xlsx ou .csv e envie novamente.');
        }

        throw new ImportacaoException('Formato de arquivo não suportado. Envie uma planilha .xlsx ou .csv.');
    }

    // ------------------------------------------------------------------ CSV

    /** @return Generator<int, array<int, mixed>> */
    private static function linhasCsv(string $caminho): Generator
    {
        $amostra = (string) file_get_contents($caminho, false, null, 0, 65536);
        if ($amostra === '') {
            throw new ImportacaoException('O arquivo está vazio.');
        }

        $utf8 = mb_check_encoding($amostra, 'UTF-8');
        $delimitador = self::detectarDelimitador($amostra);

        $arquivo = fopen($caminho, 'rb');
        if ($arquivo === false) {
            throw new ImportacaoException('Não foi possível abrir o arquivo enviado.');
        }

        try {
            // Remove BOM UTF-8
            $inicio = fread($arquivo, 3);
            if ($inicio !== "\xEF\xBB\xBF") {
                rewind($arquivo);
            }

            $numero = 0;
            while (($registro = fgetcsv($arquivo, 0, $delimitador, '"', '')) !== false) {
                $numero++;

                if ($registro === [null]) { // linha em branco
                    continue;
                }

                if (! $utf8) {
                    $registro = array_map(
                        static fn ($c) => $c === null ? null : mb_convert_encoding($c, 'UTF-8', 'Windows-1252'),
                        $registro
                    );
                }

                yield $numero => array_map(static fn ($c) => $c === '' ? null : $c, $registro);
            }
        } finally {
            fclose($arquivo);
        }
    }

    private static function detectarDelimitador(string $amostra): string
    {
        $linhas = array_slice(preg_split('/\R/', $amostra) ?: [], 0, 5);
        $texto = implode("\n", $linhas);

        $contagem = [
            ';' => substr_count($texto, ';'),
            ',' => substr_count($texto, ','),
            "\t" => substr_count($texto, "\t"),
        ];
        arsort($contagem);

        return array_key_first($contagem);
    }

    // ----------------------------------------------------------------- XLSX

    /** @return Generator<int, array<int, mixed>> */
    private static function linhasXlsx(string $caminho): Generator
    {
        if (! class_exists(ZipArchive::class)) {
            throw new ImportacaoException('A extensão zip do PHP não está habilitada; não é possível ler .xlsx. Envie um .csv.');
        }

        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new ImportacaoException('O arquivo não parece ser uma planilha .xlsx válida.');
        }

        try {
            $planilha = self::caminhoPrimeiraPlanilha($zip);
            $textos = self::textosCompartilhados($zip);

            $leitor = new XMLReader();
            $aberto = @$leitor->open('zip://'.$caminho.'#'.$planilha);
            if (! $aberto) {
                $xml = $zip->getFromName($planilha);
                if ($xml === false) {
                    throw new ImportacaoException('Não foi possível ler a primeira aba da planilha.');
                }
                $leitor->XML($xml);
            }

            try {
                $proximaLinha = 0;
                $documento = new \DOMDocument();
                while ($leitor->read()) {
                    if ($leitor->nodeType !== XMLReader::ELEMENT || $leitor->name !== 'row') {
                        continue;
                    }

                    $proximaLinha++;
                    $numero = (int) ($leitor->getAttribute('r') ?: $proximaLinha);
                    $proximaLinha = $numero;

                    $no = simplexml_import_dom($leitor->expand($documento));
                    $celulas = self::celulasDaLinha($no, $textos);

                    if ($celulas !== []) {
                        yield $numero => $celulas;
                    }
                }
            } finally {
                $leitor->close();
            }
        } finally {
            $zip->close();
        }
    }

    private static function caminhoPrimeiraPlanilha(ZipArchive $zip): string
    {
        $padrao = 'xl/worksheets/sheet1.xml';

        $workbook = $zip->getFromName('xl/workbook.xml');
        $relacoes = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relacoes === false) {
            return $zip->locateName($padrao) !== false ? $padrao : self::primeiraPlanilhaListada($zip, $padrao);
        }

        $wb = @simplexml_load_string($workbook);
        $rel = @simplexml_load_string($relacoes);
        if (! $wb || ! $rel) {
            return $padrao;
        }

        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $abas = $wb->xpath('//m:sheets/m:sheet');
        if (! $abas) {
            return $padrao;
        }

        $atributos = $abas[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $id = (string) ($atributos['id'] ?? '');

        foreach ($rel->Relationship as $r) {
            if ((string) $r['Id'] === $id) {
                $alvo = (string) $r['Target'];

                return str_starts_with($alvo, '/') ? ltrim($alvo, '/') : 'xl/'.$alvo;
            }
        }

        return $padrao;
    }

    private static function primeiraPlanilhaListada(ZipArchive $zip, string $padrao): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $nome)) {
                return $nome;
            }
        }

        return $padrao;
    }

    /** @return list<string> */
    private static function textosCompartilhados(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $raiz = $xml === false ? null : @simplexml_load_string($xml);
        if (! $raiz) {
            return [];
        }

        $textos = [];
        foreach ($raiz->si as $si) {
            if (isset($si->t)) {
                $textos[] = (string) $si->t;
                continue;
            }

            $parte = '';
            foreach ($si->r as $trecho) {
                $parte .= (string) $trecho->t;
            }
            $textos[] = $parte;
        }

        return $textos;
    }

    /**
     * @param  list<string>  $textos
     * @return array<int, mixed>
     */
    private static function celulasDaLinha(SimpleXMLElement $linha, array $textos): array
    {
        $celulas = [];
        $maior = -1;
        $sequencial = -1;

        foreach ($linha->c as $c) {
            $sequencial++;
            $indice = isset($c['r']) ? self::indiceDaColuna((string) $c['r']) : $sequencial;
            $sequencial = $indice;

            $tipo = (string) ($c['t'] ?? '');
            $bruto = isset($c->v) ? (string) $c->v : '';

            $valor = match ($tipo) {
                's' => $textos[(int) $bruto] ?? null,
                'inlineStr' => self::textoInline($c),
                'str' => $bruto,
                'b' => $bruto === '1' ? '1' : '0',
                'e' => null,
                default => ($bruto !== '' && is_numeric($bruto)) ? $bruto + 0 : ($bruto !== '' ? $bruto : null),
            };

            if ($valor === '') {
                $valor = null;
            }

            $celulas[$indice] = $valor;
            $maior = max($maior, $indice);
        }

        if ($maior < 0) {
            return [];
        }

        $completa = [];
        for ($i = 0; $i <= $maior; $i++) {
            $completa[$i] = $celulas[$i] ?? null;
        }

        // Linha só com células vazias não conta.
        foreach ($completa as $v) {
            if ($v !== null) {
                return $completa;
            }
        }

        return [];
    }

    private static function textoInline(SimpleXMLElement $c): ?string
    {
        if (! isset($c->is)) {
            return null;
        }
        if (isset($c->is->t)) {
            return (string) $c->is->t;
        }

        $texto = '';
        foreach ($c->is->r as $trecho) {
            $texto .= (string) $trecho->t;
        }

        return $texto;
    }

    /** "C5" -> 2; "AA1" -> 26 */
    public static function indiceDaColuna(string $referencia): int
    {
        preg_match('/^[A-Za-z]+/', $referencia, $m);
        $letras = strtoupper($m[0] ?? 'A');

        $indice = 0;
        for ($i = 0, $n = strlen($letras); $i < $n; $i++) {
            $indice = $indice * 26 + (ord($letras[$i]) - 64);
        }

        return $indice - 1;
    }
}
