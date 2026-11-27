<?php
/**
 * XlsxWriter
 * Gera arquivos .xlsx REAIS (Excel 2007+) usando apenas recursos nativos do PHP
 * (extensão ZipArchive). Não precisa de Composer nem de bibliotecas externas.
 *
 * Um .xlsx é só um .zip com arquivos XML dentro; esta classe monta esse zip.
 *
 * Uso:
 *   $x = new XlsxWriter();
 *   $x->addSheet('Vendas', ['Origem', 'Pedidos', 'Valor'], $linhas, ['text', 'int', 'money']);
 *   $x->download('relatorio.xlsx');
 *
 * Tipos de coluna: 'text' (padrão), 'int', 'money' (R$ #.##0,00), 'date' (texto).
 */
class XlsxWriter
{
    private array $sheets = [];

    public function addSheet(string $nome, array $cabecalho, array $linhas, array $tipos = []): void
    {
        $this->sheets[] = [
            'nome'      => $this->nomeAba($nome),
            'cabecalho' => array_values($cabecalho),
            'linhas'    => $linhas,
            'tipos'     => array_values($tipos),
        ];
    }

    /** Monta o arquivo e envia para o navegador como download. */
    public function download(string $arquivo): void
    {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            die('A extensão ZipArchive do PHP não está habilitada neste servidor.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            http_response_code(500);
            die('Não foi possível criar o arquivo temporário da planilha.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $i => $aba) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($aba));
        }
        $zip->close();

        // Descarta QUALQUER byte já enviado (espaços, avisos, BOM) — é isso que corrompe o arquivo.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ------------------------------------------------------------------
    // Partes do arquivo
    // ------------------------------------------------------------------

    private function sheetXml(array $aba): string
    {
        $tipos = $aba['tipos'];
        $nCols = count($aba['cabecalho']);

        // Largura das colunas conforme o conteúdo (limitada a 60)
        $larg = [];
        foreach ($aba['cabecalho'] as $c => $t) {
            $larg[$c] = mb_strlen((string) $t) + 2;
        }
        foreach ($aba['linhas'] as $linha) {
            foreach (array_values($linha) as $c => $v) {
                $larg[$c] = max($larg[$c] ?? 8, min(60, mb_strlen((string) $v) + 2));
            }
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        // Congela a linha do cabeçalho
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<cols>';
        foreach ($larg as $c => $w) {
            $n = $c + 1;
            $xml .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        // Cabeçalho (estilo 1 = negrito com fundo cinza)
        $xml .= '<row r="1">';
        foreach ($aba['cabecalho'] as $c => $titulo) {
            $xml .= $this->celulaTexto($this->coluna($c) . '1', (string) $titulo, 1);
        }
        $xml .= '</row>';

        // Dados
        $r = 2;
        foreach ($aba['linhas'] as $linha) {
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($linha) as $c => $valor) {
                if ($c >= $nCols) { break; }
                $ref  = $this->coluna($c) . $r;
                $tipo = $tipos[$c] ?? 'text';
                if ($valor === null || $valor === '') {
                    continue; // célula vazia
                }
                if ($tipo === 'money' && is_numeric($valor)) {
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . (float) $valor . '</v></c>';
                } elseif ($tipo === 'int' && is_numeric($valor)) {
                    $xml .= '<c r="' . $ref . '" s="3"><v>' . (int) $valor . '</v></c>';
                } else {
                    $xml .= $this->celulaTexto($ref, (string) $valor, 0);
                }
            }
            $xml .= '</row>';
            $r++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function celulaTexto(string $ref, string $texto, int $estilo): string
    {
        return '<c r="' . $ref . '" s="' . $estilo . '" t="inlineStr"><is><t xml:space="preserve">'
             . $this->xml($texto) . '</t></is></c>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;R$&quot;\ #,##0.00"/></numFmts>'
             . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
             . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/><bgColor indexed="64"/></patternFill></fill></fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="4">'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
             . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
             . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
             . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
             . '</cellXfs>'
             . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
             . '</styleSheet>';
    }

    private function workbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
             . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->sheets as $i => $aba) {
            $xml .= '<sheet name="' . $this->xml($aba['nome']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return $xml . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $aba) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml" ContentType="application/xml"/>'
             . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
             . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $aba) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $xml . '</Types>';
    }

    // ------------------------------------------------------------------
    // Utilitários
    // ------------------------------------------------------------------

    /** 0 => A, 25 => Z, 26 => AA ... */
    private function coluna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    /** Escapa texto para XML e remove caracteres de controle inválidos (causam o aviso "arquivo corrompido"). */
    private function xml(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Nome de aba válido no Excel: até 31 caracteres, sem  \ / ? * [ ] : */
    private function nomeAba(string $nome): string
    {
        $nome = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nome));
        $nome = mb_substr($nome === '' ? 'Planilha' : $nome, 0, 31);
        $base = $nome;
        $n = 2;
        $existentes = array_column($this->sheets, 'nome');
        while (in_array($nome, $existentes, true)) {
            $nome = mb_substr($base, 0, 28) . ' ' . $n++;
        }
        return $nome;
    }
}