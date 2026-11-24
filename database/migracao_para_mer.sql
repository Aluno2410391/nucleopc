-- =========================================================
-- NÚCLEO PC — Migração de um banco ANTIGO para o MER oficial
-- Use este script SOMENTE se você já tem o banco nucleopc_db criado
-- com a versão anterior do schema.sql e quer manter os dados.
-- Para uma instalação nova, importe apenas o schema.sql.
--
-- Requer MariaDB 10.0+ (o que acompanha o XAMPP e o InfinityFree),
-- pois usa "ADD COLUMN IF NOT EXISTS". Faça um backup antes!
-- Selecione o banco no phpMyAdmin e importe este arquivo.
-- =========================================================

-- 1) cartoes: titular e validade conforme o MER
ALTER TABLE cartoes
    MODIFY titular VARCHAR(100) NOT NULL,
    MODIFY validade VARCHAR(7) NOT NULL COMMENT 'MM/AA ou MM/AAAA';

-- 2) formas_pagamento_config (RN10)
CREATE TABLE IF NOT EXISTS formas_pagamento_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo ENUM('pix','cartao_credito','cartao_debito','boleto') NOT NULL UNIQUE,
    nome VARCHAR(50) NOT NULL,
    prazo_minutos INT NOT NULL COMMENT 'RN10 - tempo para expirar',
    taxa_percentual DECIMAL(5,2) NULL DEFAULT 0.00 COMMENT 'Taxa simulada',
    ativo TINYINT(1) NULL DEFAULT 1,
    descricao VARCHAR(255) NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO formas_pagamento_config (codigo, nome, prazo_minutos, taxa_percentual, ativo, descricao) VALUES
('pix',            'Pix',               30,   0.00, 1, 'QR Code e código "copia e cola" — confirmação imediata'),
('cartao_credito', 'Cartão de crédito', 30,   0.00, 1, 'Em até 10x sem juros (parcela mínima de R$ 20,00)'),
('cartao_debito',  'Cartão de débito',  30,   0.00, 1, 'À vista, direto da sua conta'),
('boleto',         'Boleto bancário',   4320, 0.00, 1, 'Vence em 3 dias');

-- 3) pedidos: novas colunas de pagamento
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS preferencia_id VARCHAR(60) NULL AFTER forma_pagamento,
    ADD COLUMN IF NOT EXISTS pagamento_expira_em DATETIME NULL COMMENT 'RN10 - prazo de validade da forma de pagamento',
    ADD COLUMN IF NOT EXISTS pagamento_codigo_pix TEXT NULL COMMENT 'Pix copia e cola BR Code - RF18',
    ADD COLUMN IF NOT EXISTS pagamento_qr_code TEXT NULL COMMENT 'Payload Pix para gerar QR Code',
    ADD COLUMN IF NOT EXISTS pagamento_boleto_linha VARCHAR(100) NULL COMMENT 'Linha digitável do boleto - RF18',
    ADD COLUMN IF NOT EXISTS pagamento_boleto_barra VARCHAR(100) NULL COMMENT 'Codigo de barras do boleto';

-- 3.1) Normaliza valores antigos ANTES de transformar forma_pagamento em ENUM.
--      Vendas de balcão antigas gravavam 'balcao' (não existe no MER): viram 'pix'.
--      Pedidos do extinto gateway ('mercado_pago') também viram 'pix'.
UPDATE pedidos SET forma_pagamento = 'pix'
 WHERE forma_pagamento IS NULL
    OR forma_pagamento NOT IN ('pix','cartao_credito','cartao_debito','boleto');

-- 3.2) Normaliza pagamento_status para os valores da RN09
UPDATE pedidos SET pagamento_status = 'aprovado'
 WHERE pagamento_status IN ('approved_simulado','confirmado') OR (pagamento_status IS NULL AND status IN ('confirmado','enviado','entregue'));
UPDATE pedidos SET pagamento_status = 'cancelado_expirado' WHERE pagamento_status = 'expirado';
UPDATE pedidos SET pagamento_status = 'recusado'           WHERE pagamento_status IN ('cancelado_cliente','cancelado_admin') OR (pagamento_status IS NULL AND status = 'cancelado');
UPDATE pedidos SET pagamento_status = 'aguardando_pagamento' WHERE pagamento_status IS NULL;

ALTER TABLE pedidos
    MODIFY origem ENUM('loja_fisica','online') NOT NULL COMMENT 'RN04',
    MODIFY forma_pagamento ENUM('pix','cartao_credito','cartao_debito','boleto') NOT NULL COMMENT 'RF17 - forma escolhida no checkout',
    MODIFY pagamento_id VARCHAR(60) NULL COMMENT 'ID representativo: codigo Pix copia e cola / linha digitavel boleto / id transacao cartao',
    MODIFY pagamento_status VARCHAR(30) NULL DEFAULT 'aguardando_pagamento' COMMENT 'RN09: aguardando_pagamento, aprovado, recusado, confirmado_manual_admin, cancelado_expirado';

-- 3.3) Pedidos ainda pendentes ganham um prazo (RN10) a partir da data do pedido
UPDATE pedidos p
  JOIN formas_pagamento_config f ON f.codigo = p.forma_pagamento
   SET p.pagamento_expira_em = DATE_ADD(p.data_pedido, INTERVAL f.prazo_minutos MINUTE)
 WHERE p.status = 'aguardando_pagamento' AND p.pagamento_expira_em IS NULL;

-- 4) Nota fiscal: garante RN05 (no máximo uma nota por pedido) e número único por série.
--    Obs.: as regras ON UPDATE/ON DELETE das chaves estrangeiras antigas (ex.: pedidos.cliente_id
--    ON DELETE SET NULL do MER) NÃO são alteradas aqui, porque os nomes das constraints antigas
--    variam de instalação para instalação. Para tê-las exatamente como no MER, reimporte o schema.sql.
--    Se já existirem notas duplicadas para o mesmo pedido, apague as duplicadas antes de rodar.
ALTER TABLE notas_fiscais
    ADD UNIQUE KEY uq_nf_pedido (pedido_id),
    ADD UNIQUE KEY uq_nf_numero_serie (serie, numero);

-- 5) pagamento_log (auditoria)
CREATE TABLE IF NOT EXISTS pagamento_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    status_anterior VARCHAR(30) NULL,
    status_novo VARCHAR(30) NOT NULL,
    forma_pagamento ENUM('pix','cartao_credito','cartao_debito','boleto') NULL,
    observacao VARCHAR(255) NULL,
    usuario_id INT NULL COMMENT 'Quem confirmou manualmente - UC08',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagamento_log_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pagamento_log_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Log de todas as mudanças de pagamento para auditoria';

-- 6) View vw_pedidos_pagamento
CREATE OR REPLACE VIEW vw_pedidos_pagamento AS
SELECT
    p.id AS id,
    p.cliente_id AS cliente_id,
    c.nome AS cliente_nome,
    p.origem AS origem,
    p.forma_pagamento AS forma_pagamento,
    p.pagamento_status AS pagamento_status,
    p.valor_total AS valor_total,
    p.pagamento_expira_em AS pagamento_expira_em,
    f.nome AS forma_nome,
    f.prazo_minutos AS prazo_minutos,
    CASE
        WHEN p.status = 'aguardando_pagamento'
             AND p.pagamento_expira_em IS NOT NULL
             AND p.pagamento_expira_em < NOW() THEN 'cancelado'
        ELSE p.status
    END AS status_real,
    CASE
        WHEN p.status = 'aguardando_pagamento' AND p.pagamento_expira_em IS NOT NULL
        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, NOW(), p.pagamento_expira_em))
        ELSE NULL
    END AS minutos_restantes
FROM pedidos p
LEFT JOIN clientes c ON c.id = p.cliente_id
LEFT JOIN formas_pagamento_config f ON f.codigo = p.forma_pagamento;

-- 7) Log retroativo mínimo: um registro por pedido já existente
INSERT INTO pagamento_log (pedido_id, status_anterior, status_novo, forma_pagamento, observacao)
SELECT p.id, NULL, COALESCE(p.pagamento_status, 'aguardando_pagamento'), p.forma_pagamento, 'Registro criado pela migração para o MER'
  FROM pedidos p
 WHERE NOT EXISTS (SELECT 1 FROM pagamento_log l WHERE l.pedido_id = p.id);

-- 8) (Opcional) Corrige o hash dos usuários de teste para a senha admin123 (8 caracteres)
-- UPDATE usuarios SET senha = '$2y$10$GfxFCwFaF8402omcujAtAuT2bBa3hCrqgJWzjDjwX0hQWpwAuaYDy'
--  WHERE email IN ('admin@nucleopc.com','vendedor@nucleopc.com');
