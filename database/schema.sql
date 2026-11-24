-- =========================================================
-- NÚCLEO PC - Loja de Computadores e Peças
-- Script de criação do banco de dados
-- SGBD: MariaDB/MySQL (compatível com XAMPP / phpMyAdmin / InfinityFree)
--
-- Este script segue o MER oficial do projeto (MER_.pdf):
-- 14 tabelas + a view vw_pedidos_pagamento.
-- Referências: RF17/RF18 (pagamento), RN04, RN09, RN10, UC08, RNF08.
-- =========================================================

CREATE DATABASE IF NOT EXISTS nucleopc_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nucleopc_db;

-- ---------------------------------------------------------
-- 1. Usuários internos (login do Painel Admin - loja física)
-- ---------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    reset_token VARCHAR(64) NULL,
    reset_token_expira DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 2. Clientes (cadastro/login da Loja Online)
-- ---------------------------------------------------------
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NULL,
    telefone VARCHAR(20) NULL,
    cep VARCHAR(9) NULL,
    endereco VARCHAR(200) NULL,
    numero VARCHAR(10) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    reset_token VARCHAR(64) NULL,
    reset_token_expira DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 3. Cartões salvos pelo cliente para agilizar compras futuras
--    RF18 - guarda só bandeira, últimos 4 dígitos, titular e
--    validade. RNF08 - o número completo e o CVV NUNCA são
--    armazenados (ver loja/services/PagamentoSimulado.php).
-- ---------------------------------------------------------
CREATE TABLE cartoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    bandeira VARCHAR(20) NOT NULL COMMENT 'Visa, Mastercard, Elo, Amex - detectada por Luhn',
    ultimos4 CHAR(4) NOT NULL COMMENT 'Últimos 4 dígitos para exibição',
    titular VARCHAR(100) NOT NULL,
    validade VARCHAR(7) NOT NULL COMMENT 'MM/AA ou MM/AAAA',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cartoes_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='RF18 - Cartões salvos do cliente, RNF08 sem dados sensíveis';

-- ---------------------------------------------------------
-- 4. Fornecedores
-- ---------------------------------------------------------
CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20) NULL,
    telefone VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    cep VARCHAR(9) NULL,
    endereco VARCHAR(200) NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 5. Categorias de produtos
-- ---------------------------------------------------------
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 6. Produtos
-- ---------------------------------------------------------
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(30) NOT NULL UNIQUE,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria_id INT NULL,
    marca VARCHAR(80) NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    custo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estoque INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 3,
    imagem VARCHAR(255) NULL DEFAULT 'sem-imagem.png',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 7. Entradas de mercadoria (compras de fornecedores)
-- ---------------------------------------------------------
CREATE TABLE entradas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_entrada DATE NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pendente','confirmada') NOT NULL DEFAULT 'confirmada',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_entradas_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entradas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 8. Itens da entrada de mercadoria
-- ---------------------------------------------------------
CREATE TABLE entrada_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrada_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    custo_unitario DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_entrada_itens_entrada FOREIGN KEY (entrada_id) REFERENCES entradas(id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_entrada_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 9. Configuração das formas de pagamento
--    RN10 - prazo (em minutos) para o pagamento expirar.
--    taxa_percentual é uma taxa SIMULADA (informativa).
-- ---------------------------------------------------------
CREATE TABLE formas_pagamento_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo ENUM('pix','cartao_credito','cartao_debito','boleto') NOT NULL UNIQUE,
    nome VARCHAR(50) NOT NULL,
    prazo_minutos INT NOT NULL COMMENT 'RN10 - tempo para expirar',
    taxa_percentual DECIMAL(5,2) NULL DEFAULT 0.00 COMMENT 'Taxa simulada',
    ativo TINYINT(1) NULL DEFAULT 1,
    descricao VARCHAR(255) NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 10. Pedidos: usada tanto para venda de balcão (loja física)
--     quanto para pedidos feitos pelo cliente na loja online
-- ---------------------------------------------------------
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NULL,
    usuario_id INT NULL,
    origem ENUM('loja_fisica','online') NOT NULL COMMENT 'RN04',
    status ENUM('aguardando_pagamento','confirmado','enviado','entregue','cancelado') NOT NULL DEFAULT 'confirmado',
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    endereco_entrega VARCHAR(255) NULL,
    forma_pagamento ENUM('pix','cartao_credito','cartao_debito','boleto') NOT NULL COMMENT 'RF17 - forma escolhida no checkout',
    preferencia_id VARCHAR(60) NULL,
    pagamento_id VARCHAR(60) NULL COMMENT 'ID representativo: codigo Pix copia e cola / linha digitavel boleto / id transacao cartao',
    pagamento_status VARCHAR(30) NULL DEFAULT 'aguardando_pagamento' COMMENT 'RN09: aguardando_pagamento, aprovado, recusado, confirmado_manual_admin, cancelado_expirado',
    data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    pagamento_expira_em DATETIME NULL COMMENT 'RN10 - prazo de validade da forma de pagamento',
    pagamento_codigo_pix TEXT NULL COMMENT 'Pix copia e cola BR Code - RF18',
    pagamento_qr_code TEXT NULL COMMENT 'Payload Pix para gerar QR Code',
    pagamento_boleto_linha VARCHAR(100) NULL COMMENT 'Linha digitável do boleto - RF18',
    pagamento_boleto_barra VARCHAR(100) NULL COMMENT 'Codigo de barras do boleto',
    CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 11. Itens do pedido
-- ---------------------------------------------------------
CREATE TABLE pedido_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_pedido_itens_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pedido_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 12. Nota fiscal (emitida automaticamente quando o pedido é
--     confirmado; RN05 - no máximo uma nota por pedido, o que é
--     garantido pelo índice único uq_nf_pedido)
-- ---------------------------------------------------------
CREATE TABLE notas_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    numero INT NOT NULL,
    serie INT NOT NULL DEFAULT 1,
    data_emissao DATETIME DEFAULT CURRENT_TIMESTAMP,
    valor_total DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_nf_pedido (pedido_id),
    UNIQUE KEY uq_nf_numero_serie (serie, numero),
    CONSTRAINT fk_notas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 13. Log de todas as mudanças de pagamento (auditoria)
-- ---------------------------------------------------------
CREATE TABLE pagamento_log (
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

-- ---------------------------------------------------------
-- 14. Mensagens de contato (formulário da loja online)
-- ---------------------------------------------------------
CREATE TABLE contatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    assunto VARCHAR(150) NULL,
    mensagem TEXT NOT NULL,
    respondido TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- View: situação do pagamento de cada pedido (RN09 / RN10).
-- status_real mostra "cancelado" quando o prazo já venceu mas o
-- pedido ainda não foi processado pela rotina de expiração.
-- ---------------------------------------------------------
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

-- =========================================================
-- DADOS INICIAIS (SEED)
-- =========================================================

-- Usuários padrão -> senha: admin123  (8 caracteres, mínimo exigido pelo sistema)
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador Núcleo PC', 'admin@nucleopc.com', '$2y$10$GfxFCwFaF8402omcujAtAuT2bBa3hCrqgJWzjDjwX0hQWpwAuaYDy', 'admin'),
('Vendedor Balcão', 'vendedor@nucleopc.com', '$2y$10$GfxFCwFaF8402omcujAtAuT2bBa3hCrqgJWzjDjwX0hQWpwAuaYDy', 'vendedor');

-- RN10: prazos para pagar (Pix 30 min, cartão 30 min de reserva, boleto 3 dias)
INSERT INTO formas_pagamento_config (codigo, nome, prazo_minutos, taxa_percentual, ativo, descricao) VALUES
('pix',            'Pix',               30,   0.00, 1, 'QR Code e código "copia e cola" — confirmação imediata'),
('cartao_credito', 'Cartão de crédito', 30,   0.00, 1, 'Em até 10x sem juros (parcela mínima de R$ 20,00)'),
('cartao_debito',  'Cartão de débito',  30,   0.00, 1, 'À vista, direto da sua conta'),
('boleto',         'Boleto bancário',   4320, 0.00, 1, 'Vence em 3 dias');

INSERT INTO categorias (nome, slug, descricao) VALUES
('Processadores', 'processadores', 'CPUs para desktop e notebook'),
('Placas de Vídeo', 'placas-de-video', 'GPUs para jogos e trabalho gráfico'),
('Memórias RAM', 'memorias-ram', 'Módulos de memória DDR4 e DDR5'),
('Armazenamento', 'armazenamento', 'SSDs e HDs'),
('Placas-mãe', 'placas-mae', 'Motherboards para diversos sockets'),
('Periféricos', 'perifericos', 'Teclados, mouses e headsets');

-- CNPJs fictícios com dígitos verificadores válidos (passam na validação do sistema)
INSERT INTO fornecedores (nome, cnpj, telefone, email, cep, endereco) VALUES
('Distribuidora TechParts LTDA', '12.345.678/0001-95', '(21)3333-4444', 'contato@techparts.com.br', '26010-000', 'Av. Marechal Rondon, 500 - Centro - Nova Iguaçu/RJ'),
('HardCore Componentes S.A.', '98.765.432/0001-98', '(11)4444-5555', 'vendas@hardcore.com.br', '01310-000', 'Av. Paulista, 1000 - Bela Vista - São Paulo/SP');

INSERT INTO produtos (sku, nome, descricao, categoria_id, marca, preco, custo, estoque, estoque_minimo, imagem) VALUES
('CPU-R5-5600', 'Processador AMD Ryzen 5 5600', '6 núcleos, 12 threads, clock de até 4.4GHz. Ótimo para jogos em Full HD.', 1, 'AMD', 899.90, 650.00, 15, 5, 'ryzen5600.png'),
('CPU-I5-13400F', 'Processador Intel Core i5-13400F', '10 núcleos (6P+4E), clock de até 4.6GHz.', 1, 'Intel', 1199.90, 900.00, 10, 5, 'i5-13400f.png'),
('GPU-RTX4060', 'Placa de Vídeo RTX 4060 8GB', 'Ray tracing e DLSS 3 para jogos em alta qualidade.', 2, 'NVIDIA', 2199.90, 1750.00, 8, 3, 'rtx4060.png'),
('GPU-RX7600', 'Placa de Vídeo RX 7600 8GB', 'Excelente custo-benefício para Full HD.', 2, 'AMD', 1899.90, 1500.00, 6, 3, 'rx7600.png'),
('RAM-16GB-3200', 'Memória RAM 16GB DDR4 3200MHz (Kit 2x8GB)', 'Kit dual channel para maior desempenho.', 3, 'Kingston', 249.90, 180.00, 30, 10, 'ram16gb.png'),
('SSD-NVME-1TB', 'SSD NVMe 1TB', 'Leitura de até 3500MB/s, interface PCIe.', 4, 'Kingston', 399.90, 290.00, 20, 8, 'ssd1tb.png'),
('MB-B550', 'Placa-mãe B550M Gaming', 'Socket AM4, suporte a DDR4 e PCIe 4.0.', 5, 'ASRock', 549.90, 400.00, 12, 5, 'b550.png'),
('TEC-MEC-RGB', 'Teclado Mecânico RGB', 'Switch blue, layout ABNT2, iluminação RGB.', 6, 'Redragon', 229.90, 140.00, 25, 10, 'teclado.png'),
('MOUSE-GAMER', 'Mouse Gamer 12000 DPI', '6 botões programáveis, sensor óptico de precisão.', 6, 'Redragon', 129.90, 75.00, 40, 10, 'mouse.png'),
('HEADSET-71', 'Headset Gamer 7.1 Surround', 'Som surround virtual e microfone retrátil.', 6, 'HyperX', 279.90, 190.00, 18, 6, 'headset.png');
