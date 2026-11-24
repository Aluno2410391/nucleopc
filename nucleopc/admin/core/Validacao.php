<?php
/**
 * Validacao
 * Regras de validação compartilhadas pelos controllers (e pela API de verificação):
 * política de senha (mínimo de 8 caracteres), CPF, CNPJ, CEP e e-mail.
 * Todas as funções são estáticas e não acessam o banco de dados.
 */
class Validacao
{
    /** Tamanho mínimo de qualquer senha (clientes e usuários do painel). */
    public const SENHA_MIN = 8;

    public static function soDigitos(string $texto): string
    {
        return preg_replace('/\D+/', '', $texto) ?? '';
    }

    /**
     * Valida uma senha nova. Devolve a mensagem de erro, ou null se estiver tudo certo.
     * Quando $confirmacao é informada, também confere se as duas digitações são iguais.
     */
    public static function senha(string $senha, ?string $confirmacao = null): ?string
    {
        if (mb_strlen($senha, 'UTF-8') < self::SENHA_MIN) {
            return 'A senha deve ter no mínimo ' . self::SENHA_MIN . ' caracteres.';
        }
        if ($confirmacao !== null && $senha !== $confirmacao) {
            return 'A confirmação de senha não confere.';
        }
        return null;
    }

    public static function email(string $email): bool
    {
        return mb_strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function cep(string $cep): bool
    {
        return strlen(self::soDigitos($cep)) === 8;
    }

    /**
     * CPF: só o FORMATO é obrigatório (11 dígitos, ex.: 123.456.789-00). Não é preciso ser um CPF real.
     * Use cpfValido() se algum dia for necessário conferir os dígitos verificadores.
     */
    public static function cpf(string $cpf): bool
    {
        return strlen(self::soDigitos($cpf)) === 11;
    }

    /** CPF com os dois dígitos verificadores corretos (módulo 11). Não é exigido pelo sistema. */
    public static function cpfValido(string $cpf): bool
    {
        $c = self::soDigitos($cpf);
        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $c[$i] * (($t + 1) - $i);
            }
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $c[$t] !== $dv) {
                return false;
            }
        }
        return true;
    }

    /**
     * CNPJ: só o FORMATO é obrigatório (14 dígitos, ex.: 12.345.678/0001-95). Não é preciso ser um CNPJ real.
     */
    public static function cnpj(string $cnpj): bool
    {
        return strlen(self::soDigitos($cnpj)) === 14;
    }

    /** CNPJ com os dois dígitos verificadores corretos (módulo 11). Usado só para decidir se vale consultar a Receita. */
    public static function cnpjValido(string $cnpj): bool
    {
        $c = self::soDigitos($cnpj);
        if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }
        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ($pesos as $k => $p) {
            $soma = 0;
            foreach ($p as $i => $peso) {
                $soma += (int) $c[$i] * $peso;
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $c[12 + $k] !== $dv) {
                return false;
            }
        }
        return true;
    }

    /** Telefone brasileiro com DDD: 10 dígitos (fixo) ou 11 (celular). */
    public static function telefone(string $telefone): bool
    {
        $n = strlen(self::soDigitos($telefone));
        return $n === 10 || $n === 11;
    }

    /** Formata como (12)12345-6789 (celular) ou (12)1234-5678 (fixo). */
    public static function formatarTelefone(string $telefone): string
    {
        $t = self::soDigitos($telefone);
        if (strlen($t) === 11) { return '(' . substr($t, 0, 2) . ')' . substr($t, 2, 5) . '-' . substr($t, 7); }
        if (strlen($t) === 10) { return '(' . substr($t, 0, 2) . ')' . substr($t, 2, 4) . '-' . substr($t, 6); }
        return $telefone;
    }

    public static function formatarCpf(string $cpf): string
    {
        $c = self::soDigitos($cpf);
        return strlen($c) === 11 ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9) : $cpf;
    }

    public static function formatarCnpj(string $cnpj): string
    {
        $c = self::soDigitos($cnpj);
        return strlen($c) === 14
            ? substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12)
            : $cnpj;
    }

    public static function formatarCep(string $cep): string
    {
        $c = self::soDigitos($cep);
        return strlen($c) === 8 ? substr($c, 0, 5) . '-' . substr($c, 5) : $cep;
    }
}
