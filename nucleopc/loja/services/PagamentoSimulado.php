<?php
require_once __DIR__ . '/../config/pagamento.php';

/**
 * PagamentoSimulado
 * Gera os dados dos meios de pagamento representativos: código Pix "copia e cola"
 * (padrão BR Code/EMV com CRC16), boleto (linha digitável e código de barras) e
 * validação/identificação de cartões. Nada aqui movimenta dinheiro de verdade.
 */
class PagamentoSimulado
{
    /** Formas de pagamento oferecidas no checkout. */
    public const FORMAS = [
        'pix'            => 'Pix',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito'  => 'Cartão de débito',
        'boleto'         => 'Boleto bancário',
    ];

    /** Rótulo de uma forma de pagamento (inclui valores antigos que ainda podem existir no banco). */
    public static function rotulo(?string $forma): string
    {
        if ($forma === 'balcao') {
            return 'Balcão';
        }
        if ($forma === 'mercado_pago') {
            return 'Mercado Pago (pedido antigo)';
        }
        return self::FORMAS[$forma ?? ''] ?? '—';
    }

    public static function ehSimulada(?string $forma): bool
    {
        return isset(self::FORMAS[$forma ?? '']);
    }

    /** Tempo (em segundos) que o cliente tem para pagar, conforme a forma escolhida. */
    public static function prazoSegundos(?string $forma): int
    {
        return match ($forma) {
            'boleto'         => BOLETO_DIAS_VENCIMENTO * 86400,
            'pix'            => PIX_MINUTOS_VALIDADE * 60,
            default          => CARTAO_MINUTOS_RESERVA * 60,
        };
    }

    // ------------------------------------------------------------------ PIX

    /** Identificador da transação Pix (txid) do pedido. */
    public static function pixTxid(int $pedidoId): string
    {
        return 'NPC' . str_pad((string) $pedidoId, 8, '0', STR_PAD_LEFT);
    }

    /** Monta o código Pix "copia e cola" (BR Code estático) para o pedido. */
    public static function pixPayload(int $pedidoId, float $valor): string
    {
        $campo = fn (string $id, string $valor) => $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;

        $conta = $campo('00', 'br.gov.bcb.pix') . $campo('01', PIX_CHAVE);
        $txid  = self::pixTxid($pedidoId);

        $payload = $campo('00', '01')
            . $campo('01', '12')
            . $campo('26', $conta)
            . $campo('52', '0000')
            . $campo('53', '986')
            . $campo('54', number_format($valor, 2, '.', ''))
            . $campo('58', 'BR')
            . $campo('59', PIX_RECEBEDOR)
            . $campo('60', PIX_CIDADE)
            . $campo('62', $campo('05', $txid))
            . '6304';

        return $payload . self::crc16($payload);
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF) exigido pelo BR Code. */
    public static function crc16(string $texto): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $crc ^= ord($texto[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    // ---------------------------------------------------------------- BOLETO

    /**
     * Gera os dados do boleto do pedido. Sempre resulta nos mesmos números para o
     * mesmo pedido e vencimento (o pedido guarda linha e código de barras, RF18).
     *
     * @return array{linha:string, codigo:string, vencimento:string, nosso_numero:string}
     */
    public static function boleto(int $pedidoId, float $valor, string $dataVencimento): array
    {
        $vencimento = new DateTime($dataVencimento);

        // Fator de vencimento = dias corridos desde 07/10/1997
        $base  = new DateTime('1997-10-07');
        $dias  = (int) $base->diff($vencimento)->days;
        // O fator volta a 1000 a cada 9000 dias (regra vigente desde 22/02/2025)
        $fator = str_pad((string) (1000 + (($dias - 1000) % 9000)), 4, '0', STR_PAD_LEFT);

        $valorCentavos = str_pad((string) (int) round($valor * 100), 10, '0', STR_PAD_LEFT);
        $nosso = str_pad((string) $pedidoId, 11, '0', STR_PAD_LEFT);

        // Campo livre (25 dígitos): agência(4) carteira(2) nosso número(11) conta(7) zero(1)
        $campoLivre = '0001' . '09' . $nosso . '1234567' . '0';

        $semDv = '999' . '9' . $fator . $valorCentavos . $campoLivre; // banco fictício 999, moeda 9
        $dv    = self::dvCodigoBarras($semDv);
        $codigo = substr($semDv, 0, 4) . $dv . substr($semDv, 4);      // 44 dígitos

        $c1 = '9999' . substr($campoLivre, 0, 5);
        $c2 = substr($campoLivre, 5, 10);
        $c3 = substr($campoLivre, 15, 10);
        $f1 = $c1 . self::mod10($c1);
        $f2 = $c2 . self::mod10($c2);
        $f3 = $c3 . self::mod10($c3);

        $linha = substr($f1, 0, 5) . '.' . substr($f1, 5) . ' '
            . substr($f2, 0, 5) . '.' . substr($f2, 5) . ' '
            . substr($f3, 0, 5) . '.' . substr($f3, 5) . ' '
            . $dv . ' ' . $fator . $valorCentavos;

        return [
            'linha'        => $linha,
            'codigo'       => $codigo,
            'vencimento'   => $vencimento->format('d/m/Y'),
            'nosso_numero' => $nosso,
        ];
    }

    private static function mod10(string $num): int
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $p = (int) $num[$i] * $peso;
            $soma += intdiv($p, 10) + ($p % 10);
            $peso = ($peso === 2) ? 1 : 2;
        }
        return (10 - ($soma % 10)) % 10;
    }

    private static function dvCodigoBarras(string $num43): int
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($num43) - 1; $i >= 0; $i--) {
            $soma += (int) $num43[$i] * $peso;
            $peso = ($peso === 9) ? 2 : $peso + 1;
        }
        $dv = 11 - ($soma % 11);
        return ($dv === 0 || $dv === 10 || $dv === 11) ? 1 : $dv;
    }

    /** Desenha o código de barras (padrão Intercalado 2 de 5, usado em boletos) como SVG. */
    public static function codigoBarrasSvg(string $digitos): string
    {
        $padroes = ['nnwwn', 'wnnnw', 'nwnnw', 'wwnnn', 'nnwnw', 'wnwnn', 'nwwnn', 'nnnww', 'wnnwn', 'nwnwn'];
        $barras = [];                       // sequência de larguras alternando barra/espaço
        foreach (str_split('nnnn') as $l) { $barras[] = $l; }   // início
        for ($i = 0; $i < strlen($digitos); $i += 2) {
            $a = $padroes[(int) $digitos[$i]];
            $b = $padroes[(int) $digitos[$i + 1]];
            for ($k = 0; $k < 5; $k++) { $barras[] = $a[$k]; $barras[] = $b[$k]; }
        }
        foreach (['w', 'n', 'n'] as $l) { $barras[] = $l; }     // fim

        $x = 0;
        $rects = '';
        foreach ($barras as $i => $l) {
            $larg = ($l === 'w') ? 3 : 1;
            if ($i % 2 === 0) {
                $rects .= '<rect x="' . $x . '" y="0" width="' . $larg . '" height="60"/>';
            }
            $x += $larg;
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $x . ' 60" preserveAspectRatio="none" '
            . 'class="boleto-barras" role="img" aria-label="Código de barras do boleto"><g fill="#000">' . $rects . '</g></svg>';
    }

    // ---------------------------------------------------------------- CARTÃO

    public static function soDigitos(string $texto): string
    {
        return preg_replace('/\D+/', '', $texto) ?? '';
    }

    public static function luhnValido(string $numero): bool
    {
        $numero = self::soDigitos($numero);
        $n = strlen($numero);
        if ($n < 13 || $n > 19) {
            return false;
        }
        $soma = 0;
        for ($i = 0; $i < $n; $i++) {
            $d = (int) $numero[$n - 1 - $i];
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) { $d -= 9; }
            }
            $soma += $d;
        }
        return $soma % 10 === 0;
    }

    public static function bandeira(string $numero): string
    {
        $n = self::soDigitos($numero);
        if (preg_match('/^(636368|438935|504175|451416|636297|5067|4576|4011)/', $n)) { return 'Elo'; }
        if (preg_match('/^(606282|3841)/', $n)) { return 'Hipercard'; }
        if (preg_match('/^3[47]/', $n)) { return 'Amex'; }
        $prefixo4 = (int) substr($n, 0, 4);
        if (preg_match('/^5[1-5]/', $n) || ($prefixo4 >= 2221 && $prefixo4 <= 2720)) { return 'Mastercard'; }
        if (preg_match('/^4/', $n)) { return 'Visa'; }
        return 'Cartão';
    }

    /**
     * Valida os dados de cartão enviados no formulário. Devolve a lista de erros
     * (vazia quando tudo está certo). O número completo e o CVV NUNCA são gravados.
     */
    public static function validarCartao(array $d, bool $credito, float $total): array
    {
        $erros = [];
        $numero = self::soDigitos($d['numero'] ?? '');
        if (!self::luhnValido($numero)) {
            $erros[] = 'Número de cartão inválido. Confira os dígitos.';
        }
        if (mb_strlen(trim($d['nome'] ?? '')) < 3) {
            $erros[] = 'Informe o nome impresso no cartão.';
        }
        if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*(\d{2}|\d{4})$#', trim($d['validade'] ?? ''), $m)) {
            $erros[] = 'Validade inválida. Use o formato MM/AA ou MM/AAAA.';
        } else {
            $ano = strlen($m[2]) === 2 ? '20' . $m[2] : $m[2]; // MM/AA ou MM/AAAA (MER)
            $fim = new DateTime($ano . '-' . $m[1] . '-01');
            $fim->modify('last day of this month 23:59:59');
            if ($fim < new DateTime()) {
                $erros[] = 'Cartão vencido.';
            }
        }
        $cvv = self::soDigitos($d['cvv'] ?? '');
        $tamCvv = (self::bandeira($numero) === 'Amex') ? 4 : 3;
        if (strlen($cvv) !== $tamCvv) {
            $erros[] = 'Código de segurança (CVV) inválido.';
        }
        if ($credito) {
            $parcelas = (int) ($d['parcelas'] ?? 1);
            if ($parcelas < 1 || $parcelas > self::maxParcelas($total)) {
                $erros[] = 'Número de parcelas inválido.';
            }
        }
        return $erros;
    }

    /** Até 10x, com parcela mínima de R$ 20,00. */
    public static function maxParcelas(float $total): int
    {
        return max(1, min(10, (int) floor($total / 20)));
    }

    /** Cartões terminados em 0002 são "recusados" — permite demonstrar o cenário de falha. */
    public static function cartaoRecusado(string $numeroOuFinal): bool
    {
        return substr(self::soDigitos($numeroOuFinal), -4) === '0002';
    }

    /** Valida só o CVV (usado ao pagar com um cartão já salvo). */
    public static function cvvValido(string $cvv, string $bandeira): bool
    {
        return strlen(self::soDigitos($cvv)) === ($bandeira === 'Amex' ? 4 : 3);
    }
}
