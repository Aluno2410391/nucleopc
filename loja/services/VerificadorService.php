<?php
require_once __DIR__ . '/../core/Validacao.php';

/**
 * VerificadorService
 * Verifica informações cadastrais usando APIs públicas e gratuitas:
 *
 *   • CEP   -> ViaCEP (viacep.com.br), com BrasilAPI (brasilapi.com.br) como reserva
 *   • CNPJ  -> formato (14 dígitos); se for um CNPJ real, consulta a situação cadastral na BrasilAPI
 *   • CPF   -> formato (11 dígitos). Não existe API pública e gratuita de CPF (dados protegidos
 *              pela LGPD) e o sistema não exige um CPF real
 *   • E-mail-> formato + existência do domínio (registros MX/A no DNS) + bloqueio de
 *              e-mails descartáveis
 *
 * Princípio de projeto: se a API estiver fora do ar (ou o servidor de hospedagem bloquear
 * conexões de saída, como alguns planos gratuitos fazem), o sistema NÃO trava — devolve
 * "verificado_online = false" e o cadastro segue com a validação local (formato/dígitos).
 *
 * Formato de retorno de todos os métodos:
 *   ['ok' => bool, 'verificado_online' => bool, 'fonte' => string, 'mensagem' => string,
 *    'aviso' => ?string, 'dados' => ?array]
 */
class VerificadorService
{
    private const TIMEOUT   = 4;      // segundos por chamada
    private const CACHE_TTL = 86400;  // 24 h

    private const EMAILS_DESCARTAVEIS = [
        'mailinator.com', '10minutemail.com', 'guerrillamail.com', 'tempmail.com',
        'yopmail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com',
    ];

    // ------------------------------------------------------------------ CEP

    public static function cep(string $cep): array
    {
        $cep = Validacao::soDigitos($cep);
        if (strlen($cep) !== 8) {
            return self::resposta(false, 'Informe um CEP com 8 dígitos.');
        }
        if ($c = self::cacheLer('cep_' . $cep)) {
            return $c;
        }

        // 1) ViaCEP
        $r = self::http('https://viacep.com.br/ws/' . $cep . '/json/');
        if ($r && is_array($r['json'])) {
            if (!empty($r['json']['erro'])) {
                return self::cacheGravar('cep_' . $cep, self::resposta(false, 'CEP não encontrado.', true, 'viacep'));
            }
            if (isset($r['json']['logradouro'])) {
                $j = $r['json'];
                return self::cacheGravar('cep_' . $cep, self::resposta(true, 'CEP encontrado.', true, 'viacep', null, [
                    'cep'         => Validacao::formatarCep($cep),
                    'logradouro'  => $j['logradouro'] ?? '',
                    'bairro'      => $j['bairro'] ?? '',
                    'cidade'      => $j['localidade'] ?? '',
                    'uf'          => $j['uf'] ?? '',
                ]));
            }
        }

        // 2) BrasilAPI (reserva)
        $r = self::http('https://brasilapi.com.br/api/cep/v2/' . $cep);
        if ($r && is_array($r['json'])) {
            if ($r['status'] === 200 && isset($r['json']['city'])) {
                $j = $r['json'];
                return self::cacheGravar('cep_' . $cep, self::resposta(true, 'CEP encontrado.', true, 'brasilapi', null, [
                    'cep'        => Validacao::formatarCep($cep),
                    'logradouro' => $j['street'] ?? '',
                    'bairro'     => $j['neighborhood'] ?? '',
                    'cidade'     => $j['city'] ?? '',
                    'uf'         => $j['state'] ?? '',
                ]));
            }
            if ($r['status'] === 404) {
                return self::cacheGravar('cep_' . $cep, self::resposta(false, 'CEP não encontrado.', true, 'brasilapi'));
            }
        }

        // 3) Nenhuma API respondeu: aceita pelo formato
        return self::resposta(true, 'CEP com formato válido (consulta online indisponível no momento).', false, 'indisponivel');
    }

    // ------------------------------------------------------------------ CPF

    public static function cpf(string $cpf): array
    {
        // Só o formato é exigido (não precisa ser um CPF real).
        if (Validacao::cpf($cpf)) {
            return self::resposta(true, 'CPF no formato correto.', false, 'local');
        }
        return self::resposta(false, 'CPF deve ter 11 dígitos (000.000.000-00).', false, 'local');
    }

    // ----------------------------------------------------------------- CNPJ

    public static function cnpj(string $cnpj): array
    {
        $n = Validacao::soDigitos($cnpj);
        // Só o formato é exigido (não precisa ser um CNPJ real).
        if (!Validacao::cnpj($n)) {
            return self::resposta(false, 'CNPJ deve ter 14 dígitos (00.00.000/0000-00).', false, 'local');
        }
        // Dígitos verificadores não conferem: aceita (CNPJ de teste) e nem consulta a Receita.
        if (!Validacao::cnpjValido($n)) {
            return self::resposta(true, 'CNPJ no formato correto.', false, 'local',
                'Este CNPJ não é real (dígitos verificadores não conferem), mas foi aceito pelo formato.');
        }
        if ($c = self::cacheLer('cnpj_' . $n)) {
            return $c;
        }

        $r = self::http('https://brasilapi.com.br/api/cnpj/v1/' . $n);
        if ($r && $r['status'] === 200 && is_array($r['json']) && isset($r['json']['razao_social'])) {
            $j = $r['json'];
            $situacao = (string) ($j['descricao_situacao_cadastral'] ?? '');
            $aviso = ($situacao !== '' && strtoupper($situacao) !== 'ATIVA')
                ? 'Atenção: a situação cadastral deste CNPJ na Receita Federal é "' . $situacao . '".' : null;
            $endereco = trim(($j['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($j['logradouro'] ?? ''));
            if (!empty($j['numero'])) { $endereco .= ', ' . $j['numero']; }
            if (!empty($j['bairro'])) { $endereco .= ' - ' . $j['bairro']; }
            if (!empty($j['municipio'])) { $endereco .= ' - ' . $j['municipio'] . '/' . ($j['uf'] ?? ''); }
            return self::cacheGravar('cnpj_' . $n, self::resposta(true, 'CNPJ encontrado na Receita Federal.', true, 'brasilapi', $aviso, [
                'razao_social'  => $j['razao_social'],
                'nome_fantasia' => $j['nome_fantasia'] ?? '',
                'situacao'      => $situacao,
                'cep'           => Validacao::formatarCep((string) ($j['cep'] ?? '')),
                'endereco'      => $endereco,
                'telefone'      => $j['ddd_telefone_1'] ?? '',
                'email'         => $j['email'] ?? '',
            ]));
        }
        if ($r && $r['status'] === 404) {
            // Dígitos corretos, mas o CNPJ não existe na base: aceita com aviso (útil para dados de teste).
            return self::resposta(true, 'CNPJ com dígitos válidos.', true, 'brasilapi',
                'Este CNPJ não foi localizado na base da Receita Federal. Confirme se está correto.');
        }
        return self::resposta(true, 'CNPJ com dígitos válidos (consulta online indisponível no momento).', false, 'indisponivel');
    }

    // --------------------------------------------------------------- E-MAIL

    public static function email(string $email): array
    {
        $email = trim($email);
        if (!Validacao::email($email)) {
            return self::resposta(false, 'E-mail inválido. Use o formato nome@dominio.com.', false, 'local');
        }
        $dominio = strtolower(substr(strrchr($email, '@'), 1));
        if (in_array($dominio, self::EMAILS_DESCARTAVEIS, true)) {
            return self::resposta(false, 'Não aceitamos e-mails temporários/descartáveis.', false, 'local');
        }

        // Só rejeita por DNS se o DNS do servidor estiver funcionando (evita falso "domínio inexistente" offline).
        if (self::dnsFunciona()) {
            $host = function_exists('idn_to_ascii') ? (idn_to_ascii($dominio) ?: $dominio) : $dominio;
            if (!checkdnsrr($host . '.', 'MX') && !checkdnsrr($host . '.', 'A')) {
                return self::resposta(false, 'O domínio "' . $dominio . '" não existe ou não recebe e-mails.', true, 'dns');
            }
            return self::resposta(true, 'E-mail com domínio válido.', true, 'dns');
        }
        return self::resposta(true, 'E-mail com formato válido.', false, 'local');
    }

    private static function dnsFunciona(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = function_exists('checkdnsrr') && checkdnsrr('google.com.', 'A');
        }
        return $ok;
    }

    // ------------------------------------------------------------- Internos

    private static function resposta(bool $ok, string $mensagem, bool $online = false, string $fonte = 'local', ?string $aviso = null, ?array $dados = null): array
    {
        return [
            'ok'                => $ok,
            'verificado_online' => $online,
            'fonte'             => $fonte,
            'mensagem'          => $mensagem,
            'aviso'             => $aviso,
            'dados'             => $dados,
        ];
    }

    /** GET simples que devolve ['status' => int, 'json' => ?array], ou null se não conseguiu conectar. */
    private static function http(string $url): ?array
    {
        $corpo = false;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: NucleoPC/1.0 (projeto academico)'],
            ]);
            $corpo = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\nUser-Agent: NucleoPC/1.0 (projeto academico)\r\n",
            ]]);
            $corpo = @file_get_contents($url, false, $ctx);
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
        }

        if ($corpo === false || $corpo === '' || $status === 0) {
            return null;
        }
        $json = json_decode($corpo, true);
        return ['status' => $status, 'json' => is_array($json) ? $json : null];
    }

    // Cache em arquivo (pasta storage/ do próprio sistema, bloqueada para acesso via navegador)

    private static function cacheDir(): string
    {
        return __DIR__ . '/../storage/cache_api/';
    }

    private static function cacheLer(string $chave): ?array
    {
        $arq = self::cacheDir() . preg_replace('/\W/', '', $chave) . '.json';
        if (!is_file($arq) || (time() - filemtime($arq)) > self::CACHE_TTL) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($arq), true);
        return is_array($j) ? $j : null;
    }

    private static function cacheGravar(string $chave, array $resposta): array
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . preg_replace('/\W/', '', $chave) . '.json', json_encode($resposta, JSON_UNESCAPED_UNICODE));
        }
        return $resposta;
    }
}
