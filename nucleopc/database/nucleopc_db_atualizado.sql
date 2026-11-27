-- NucleoPC - banco atualizado a partir do dump da hospedagem (InfinityFree)
-- Importe no phpMyAdmin do XAMPP (aba "Importar"). ATENÇÃO: recria o banco nucleopc_db do zero.
DROP DATABASE IF EXISTS `nucleopc_db`;
CREATE DATABASE `nucleopc_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `nucleopc_db`;

-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql109.infinityfree.com
-- Tempo de geração: 26/11/2026 às 11:31
-- Versão do servidor: 11.4.14-MariaDB
-- Versão do PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `nucleopc_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cartoes`
--

CREATE TABLE `cartoes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `bandeira` varchar(20) NOT NULL COMMENT 'Visa, Mastercard, Elo, Amex - detectada por Luhn',
  `ultimos4` char(4) NOT NULL COMMENT 'Últimos 4 dígitos para exibição',
  `titular` varchar(100) NOT NULL,
  `validade` varchar(7) NOT NULL COMMENT 'MM/AA ou MM/AAAA',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RF18 - Cartões salvos do cliente, RNF08 sem dados sensíveis';

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `slug`, `descricao`) VALUES
(1, 'Processadores', 'processadores', 'CPUs para desktop e notebook'),
(2, 'Placas de Vídeo', 'placas-de-video', 'GPUs para jogos e trabalho gráfico'),
(3, 'Memórias RAM', 'memorias-ram', 'Módulos de memória DDR4 e DDR5'),
(4, 'Armazenamento', 'armazenamento', 'SSDs e HDs'),
(5, 'Placas-mãe', 'placas-mae', 'Motherboards para diversos sockets'),
(6, 'Periféricos', 'perifericos', 'Teclados, mouses e headsets'),
(9, 'teste3', 'teste3', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `endereco` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expira` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `email`, `senha`, `cpf`, `telefone`, `cep`, `endereco`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `reset_token`, `reset_token_expira`, `criado_em`) VALUES
(1, 'teste', 'teste@email.com', '$2y$10$uq16YJdjUsRXib8ci55nvuBhFFZWF8GpBXiHUDGlsZVUhnf.mtp6O', '123.456.789-01', '(12)3456-7890', '26290-249', 'Estrada Granja', '01', 'Condomínio Roraima', 'Palhada', 'Nova Iguaçu', 'RJ', NULL, NULL, '2026-11-22 10:43:22'),
(2, 'Samuel', 'samuelopes067@gmail.com', '$2y$12$UZL9Nn7NKGTNBCBiWI0Un.1qgtzmkdJBJM7a/WpjKfL1Xi9ihfz3C', '157.575.747-80', '(21)99866-6822', '26290-726', 'Rua Pettena', '21', '', 'Valverde', 'Nova Iguaçu', 'RJ', NULL, NULL, '2026-11-23 11:21:34'),
(3, 'Renildo', 'samuelopes0899@gmail.com', '$2y$12$bIr43ampGc8565GbNoSG.OqAmHU8kSGx82OiM9ETt1WahQK0YMMIa', '157.243.815-40', '(21)9976-8405', '26290-726', 'Rua Pettena', '95', '', 'Valverde', 'Nova Iguaçu', 'RJ', NULL, NULL, '2026-11-24 03:30:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contatos`
--

CREATE TABLE `contatos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `assunto` varchar(150) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `respondido` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `entradas`
--

CREATE TABLE `entradas` (
  `id` int(11) NOT NULL,
  `fornecedor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_entrada` date NOT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pendente','confirmada') NOT NULL DEFAULT 'confirmada',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `entradas`
--

INSERT INTO `entradas` (`id`, `fornecedor_id`, `usuario_id`, `data_entrada`, `valor_total`, `status`, `criado_em`) VALUES
(1, 1, 1, '2026-11-22', '1400.00', 'confirmada', '2026-11-22 10:47:14'),
(2, 2, 1, '2026-11-22', '1100.00', 'confirmada', '2026-11-22 10:47:57'),
(3, 1, 3, '2026-11-23', '807.00', 'confirmada', '2026-11-23 11:23:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `entrada_itens`
--

CREATE TABLE `entrada_itens` (
  `id` int(11) NOT NULL,
  `entrada_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `custo_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `entrada_itens`
--

INSERT INTO `entrada_itens` (`id`, `entrada_id`, `produto_id`, `quantidade`, `custo_unitario`) VALUES
(1, 1, 4, 1, '1400.00'),
(2, 2, 3, 1, '1100.00'),
(3, 3, 4, 1, '807.00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `formas_pagamento_config`
--

CREATE TABLE `formas_pagamento_config` (
  `id` int(11) NOT NULL,
  `codigo` enum('pix','cartao_credito','cartao_debito','boleto') NOT NULL,
  `nome` varchar(50) NOT NULL,
  `prazo_minutos` int(11) NOT NULL COMMENT 'RN10 - tempo para expirar',
  `taxa_percentual` decimal(5,2) DEFAULT 0.00 COMMENT 'Taxa simulada',
  `ativo` tinyint(1) DEFAULT 1,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `formas_pagamento_config`
--

INSERT INTO `formas_pagamento_config` (`id`, `codigo`, `nome`, `prazo_minutos`, `taxa_percentual`, `ativo`, `descricao`, `criado_em`) VALUES
(1, 'pix', 'Pix', 30, '0.00', 1, 'QR Code e código \"copia e cola\" — confirmação imediata', '2026-11-22 10:27:12'),
(2, 'cartao_credito', 'Cartão de crédito', 30, '0.00', 1, 'Em até 10x sem juros (parcela mínima de R$ 20,00)', '2026-11-22 10:27:12'),
(3, 'cartao_debito', 'Cartão de débito', 30, '0.00', 1, 'À vista, direto da sua conta', '2026-11-22 10:27:12'),
(4, 'boleto', 'Boleto bancário', 4320, '0.00', 1, 'Vence em 3 dias', '2026-11-22 10:27:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE `fornecedores` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `endereco` varchar(200) DEFAULT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `fornecedores`
--

INSERT INTO `fornecedores` (`id`, `nome`, `cnpj`, `telefone`, `email`, `cep`, `endereco`, `status`, `criado_em`) VALUES
(1, 'Distribuidora TechParts LTDA', '12.345.678/0001-95', '(21)3333-4444', 'contato@techparts.com.br', '26010-000', 'Av. Marechal Rondon, 500 - Centro - Nova Iguaçu/RJ', 'ativo', '2026-11-22 10:27:12'),
(2, 'HardCore Componentes S.A.', '98.765.432/0001-98', '(11)4444-5555', 'vendas@hardcore.com.br', '01310-000', 'Av. Paulista, 1000 - Bela Vista - São Paulo/SP', 'ativo', '2026-11-22 10:27:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notas_fiscais`
--

CREATE TABLE `notas_fiscais` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `serie` int(11) NOT NULL DEFAULT 1,
  `data_emissao` datetime DEFAULT current_timestamp(),
  `valor_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notas_fiscais`
--

INSERT INTO `notas_fiscais` (`id`, `pedido_id`, `numero`, `serie`, `data_emissao`, `valor_total`) VALUES
(1, 1, 1, 1, '2026-11-22 10:39:46', '2339.20'),
(2, 2, 2, 1, '2026-11-22 10:43:50', '6749.30'),
(3, 3, 3, 1, '2026-11-22 10:46:44', '9499.50'),
(4, 4, 4, 1, '2026-11-22 15:21:34', '279.90'),
(5, 5, 5, 1, '2026-11-23 09:05:38', '549.90'),
(6, 6, 6, 1, '2026-11-23 15:29:49', '61158.90');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamento_log`
--

CREATE TABLE `pagamento_log` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `status_anterior` varchar(30) DEFAULT NULL,
  `status_novo` varchar(30) NOT NULL,
  `forma_pagamento` enum('pix','cartao_credito','cartao_debito','boleto') DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Quem confirmou manualmente - UC08',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log de todas as mudanças de pagamento para auditoria';

--
-- Despejando dados para a tabela `pagamento_log`
--

INSERT INTO `pagamento_log` (`id`, `pedido_id`, `status_anterior`, `status_novo`, `forma_pagamento`, `observacao`, `usuario_id`, `criado_em`) VALUES
(1, 1, NULL, 'aprovado', 'cartao_credito', 'Venda de balcão paga no ato', 1, '2026-11-22 10:39:46'),
(2, 2, NULL, 'aguardando_pagamento', 'cartao_credito', 'Pedido criado no checkout (prazo de 30 min para pagar)', NULL, '2026-11-22 10:43:34'),
(3, 2, 'aguardando_pagamento', 'aprovado', 'cartao_credito', 'Cartão aprovado (simulação): Visa final 4242 - 10x', NULL, '2026-11-22 10:43:50'),
(4, 3, NULL, 'aprovado', 'boleto', 'Venda de balcão paga no ato', 1, '2026-11-22 10:46:44'),
(5, 4, NULL, 'aguardando_pagamento', 'cartao_credito', 'Pedido criado no checkout (prazo de 30 min para pagar)', NULL, '2026-11-22 15:21:23'),
(6, 4, 'aguardando_pagamento', 'aprovado', 'cartao_credito', 'Cartão aprovado (simulação): Visa final 4242 - 1x', NULL, '2026-11-22 15:21:34'),
(7, 5, NULL, 'aguardando_pagamento', 'pix', 'Pedido criado no checkout (prazo de 30 min para pagar)', NULL, '2026-11-23 09:05:33'),
(8, 5, 'aguardando_pagamento', 'aprovado', 'pix', 'PIX confirmado pelo cliente (simulação)', NULL, '2026-11-23 09:05:38'),
(9, 6, NULL, 'aprovado', 'pix', 'Venda de balcão paga no ato', 1, '2026-11-23 15:29:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `origem` enum('loja_fisica','online') NOT NULL COMMENT 'RN04',
  `status` enum('aguardando_pagamento','confirmado','enviado','entregue','cancelado') NOT NULL DEFAULT 'confirmado',
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `endereco_entrega` varchar(255) DEFAULT NULL,
  `forma_pagamento` enum('pix','cartao_credito','cartao_debito','boleto') NOT NULL COMMENT 'RF17 - forma escolhida no checkout',
  `preferencia_id` varchar(60) DEFAULT NULL,
  `pagamento_id` varchar(60) DEFAULT NULL COMMENT 'ID representativo: codigo Pix copia e cola / linha digitavel boleto / id transacao cartao',
  `pagamento_status` varchar(30) DEFAULT 'aguardando_pagamento' COMMENT 'RN09: aguardando_pagamento, aprovado, recusado, confirmado_manual_admin, cancelado_expirado',
  `data_pedido` datetime DEFAULT current_timestamp(),
  `pagamento_expira_em` datetime DEFAULT NULL COMMENT 'RN10 - prazo de validade da forma de pagamento',
  `pagamento_codigo_pix` text DEFAULT NULL COMMENT 'Pix copia e cola BR Code - RF18',
  `pagamento_qr_code` text DEFAULT NULL COMMENT 'Payload Pix para gerar QR Code',
  `pagamento_boleto_linha` varchar(100) DEFAULT NULL COMMENT 'Linha digitável do boleto - RF18',
  `pagamento_boleto_barra` varchar(100) DEFAULT NULL COMMENT 'Codigo de barras do boleto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `cliente_id`, `usuario_id`, `origem`, `status`, `valor_total`, `endereco_entrega`, `forma_pagamento`, `preferencia_id`, `pagamento_id`, `pagamento_status`, `data_pedido`, `pagamento_expira_em`, `pagamento_codigo_pix`, `pagamento_qr_code`, `pagamento_boleto_linha`, `pagamento_boleto_barra`) VALUES
(1, NULL, 1, 'loja_fisica', 'confirmado', '2339.20', NULL, 'cartao_credito', NULL, NULL, 'aprovado', '2026-11-22 10:39:46', NULL, NULL, NULL, NULL, NULL),
(2, 1, NULL, 'online', 'confirmado', '6749.30', 'Estrada Granja, 01 - Palhada - Nova Iguaçu/RJ', 'cartao_credito', NULL, 'TX3D1615C40578', 'aprovado', '2026-11-22 10:43:32', '2026-11-22 11:13:32', NULL, NULL, NULL, NULL),
(3, NULL, 1, 'loja_fisica', 'confirmado', '9499.50', NULL, 'boleto', NULL, NULL, 'aprovado', '2026-11-22 10:46:43', NULL, NULL, NULL, NULL, NULL),
(4, 1, NULL, 'online', 'confirmado', '279.90', 'Estrada Granja, 01 - Palhada - Nova Iguaçu/RJ', 'cartao_credito', NULL, 'TXCB7D648F9935', 'aprovado', '2026-11-22 15:21:23', '2026-11-22 15:51:23', NULL, NULL, NULL, NULL),
(5, 1, NULL, 'online', 'confirmado', '549.90', 'Estrada Granja, 01 - Palhada - Nova Iguaçu/RJ', 'pix', NULL, 'NPC00000005', 'aprovado', '2026-11-23 09:05:33', '2026-11-23 09:35:33', '00020101021226580014br.gov.bcb.pix013600000000-0000-4000-8000-0000000000005204000053039865406549.905802BR5914NUCLEO PC DEMO6011NOVA IGUACU62150511NPC0000000563043D73', '00020101021226580014br.gov.bcb.pix013600000000-0000-4000-8000-0000000000005204000053039865406549.905802BR5914NUCLEO PC DEMO6011NOVA IGUACU62150511NPC0000000563043D73', NULL, NULL),
(6, NULL, 1, 'loja_fisica', 'confirmado', '61158.90', NULL, 'pix', NULL, NULL, 'aprovado', '2026-11-23 15:29:49', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedido_itens`
--

CREATE TABLE `pedido_itens` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedido_itens`
--

INSERT INTO `pedido_itens` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`) VALUES
(1, 1, 5, 2, '249.90'),
(2, 1, 6, 3, '399.90'),
(3, 1, 9, 1, '129.90'),
(4, 1, 8, 1, '229.90'),
(5, 1, 10, 1, '279.90'),
(6, 2, 3, 1, '2199.90'),
(7, 2, 5, 2, '249.90'),
(8, 2, 7, 1, '549.90'),
(9, 2, 1, 1, '899.90'),
(10, 2, 6, 2, '1299.90'),
(11, 3, 4, 5, '1899.90'),
(12, 4, 10, 1, '279.90'),
(13, 5, 7, 1, '549.90'),
(14, 6, 11, 8, '3124.00'),
(15, 6, 13, 10, '10.00'),
(16, 6, 14, 10, '279.90'),
(17, 6, 15, 10, '279.90'),
(18, 6, 16, 11, '2769.90');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `sku` varchar(30) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `custo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estoque` int(11) NOT NULL DEFAULT 0,
  `estoque_minimo` int(11) NOT NULL DEFAULT 3,
  `imagem` varchar(255) DEFAULT 'sem-imagem.png',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `sku`, `nome`, `descricao`, `categoria_id`, `marca`, `preco`, `custo`, `estoque`, `estoque_minimo`, `imagem`, `ativo`, `criado_em`) VALUES
(1, 'CPU-R5-5600', 'Processador AMD Ryzen 5 5600', '6 núcleos, 12 threads, clock de até 4.4GHz. Ótimo para jogos em Full HD.', 1, 'AMD', '899.90', '650.00', 14, 5, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRMMPRme3Ky0Xq1SAwQFR9tGH5KgS7is63_W2erbehY-w&s', 1, '2026-11-22 10:27:12'),
(2, 'CPU-I5-13400F', 'Processador Intel Core i5-13400F', '10 núcleos (6P+4E), clock de até 4.6GHz.', 1, 'Intel', '1199.90', '900.00', 10, 5, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTeuceaIFRftXZfKPVqsUY0_YgmxCxR9ZNssrP-ZOAxHQ&s', 1, '2026-11-22 10:27:12'),
(3, 'GPU-RTX4060', 'Placa de Vídeo RTX 4060 8GB', 'Ray tracing e DLSS 3 para jogos em alta qualidade.', 2, 'NVIDIA', '2199.90', '1750.00', 8, 3, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTorh4cXKx9WJt4WmYu26ISGbAGBxMnn6UUZAdW19dVgA&s=10', 1, '2026-11-22 10:27:12'),
(4, 'GPU-RX7600', 'Placa de Vídeo RX 7600 8GB', 'Excelente custo-benefício para Full HD.', 2, 'AMD', '1899.90', '1500.00', 3, 3, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcScPOJMLZg9BPeEfwaDicE87U1o1y70_25mc3CEGfEsuA&s=10', 1, '2026-11-22 10:27:12'),
(5, 'RAM-16GB-3200', 'Memória RAM 16GB DDR4 3200MHz (Kit 2x8GB)', 'Kit dual channel para maior desempenho.', 3, 'Kingston', '249.90', '180.00', 26, 10, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTmG_2LFBqMzvDZr5YKgf_7grXyGpKFUk8MV2bMjGtC5Q&s=10', 1, '2026-11-22 10:27:12'),
(6, 'SSD-NVME-1TB', 'SSD NVMe 1TB', 'Leitura de até 3500MB/s, interface PCIe.', 4, 'Kingston', '1299.90', '990.00', 15, 8, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSv2TyaROsur5y7Jo8C-eaJjeM9ADWSjQv_ZnM3CFXQWQ&s', 1, '2026-11-22 10:27:12'),
(7, 'MB-B550', 'Placa-mãe B550M Gaming', 'Socket AM4, suporte a DDR4 e PCIe 4.0.', 5, 'ASRock', '549.90', '400.00', 10, 5, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTqFVMIO6XFEuF6xvk9gGUE5bIh3TApklQYntDESsa2wQ&s', 1, '2026-11-22 10:27:12'),
(8, 'TEC-MEC-RGB', 'Teclado Mecânico RGB', 'Switch blue, layout ABNT2, iluminação RGB.', 6, 'Redragon', '229.90', '140.00', 24, 10, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQiFSur43nAOpUwov6UlyGzUZn9D8zOyw5itVQGDsXMPw&s=10', 1, '2026-11-22 10:27:12'),
(9, 'MOUSE-GAMER', 'Mouse Gamer 12000 DPI', '6 botões programáveis, sensor óptico de precisão.', 6, 'Redragon', '129.90', '75.00', 39, 10, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSKYeNnrLOv8drSN3Gm_nRx6QQxhCog75zm8i77eQubgQ&s=10', 1, '2026-11-22 10:27:12'),
(10, 'HEADSET-71', 'Headset Gamer 7.1 Surround', 'Som surround virtual e microfone retrátil.', 6, 'HyperX', '279.90', '190.00', 16, 6, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTPmCxfsi_TDDAjspwyqhpstwABGUFhosu0vb6_o1lB8g&s=10', 1, '2026-11-22 10:27:12'),
(11, 'TESTE-01MKI20', 'teste', '', 9, 'teste', '3124.00', '180.00', 0, 2, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjtXrUTQ6MmXhwG7s4PNZAGPc85Wmqp2n73RXSE2VZcA&s', 0, '2026-11-22 15:26:37'),
(12, 'TESTE-01MKI200', 'teste3', '', 9, 'testando', '10.00', '5.00', 0, 3, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjtXrUTQ6MmXhwG7s4PNZAGPc85Wmqp2n73RXSE2VZcA&s', 0, '2026-11-23 12:15:57'),
(13, 'TESTE-01MKI11', 'teste3', '', 9, 'testando', '10.00', '5.00', 0, 2, 'produto_6b04b8f3d3d06.png', 0, '2026-11-23 14:06:44'),
(14, 'TESTE-01MKI1', 'teste3 2', '', 9, 'testando', '279.90', '190.00', 0, 1, 'https://kinsta.com/pt/wp-content/uploads/sites/3/2021/08/encenacao-wordpress-1024x512.jpeg', 0, '2026-11-23 14:07:42'),
(15, 'TESTE-01MKI201', 'teste4', '00', 9, 'testando', '279.90', '180.00', 0, 5, 'produto_6b04c8df2fffe.png', 0, '2026-11-23 15:14:39'),
(16, 'TESTE-01MKI12', 'teste4', '', 9, 'testando', '2769.90', '2323.00', 0, 4, 'https://kinsta.com/pt/wp-content/uploads/sites/3/2021/08/encenacao-wordpress-1024x512.jpeg', 0, '2026-11-23 15:18:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expira` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`, `ativo`, `reset_token`, `reset_token_expira`, `criado_em`) VALUES
(1, 'Administrador Núcleo PC', 'admin@nucleopc.com', '$2y$10$GfxFCwFaF8402omcujAtAuT2bBa3hCrqgJWzjDjwX0hQWpwAuaYDy', 'admin', 1, NULL, NULL, '2026-11-22 10:27:12'),
(2, 'Vendedor Balcão', 'vendedor@nucleopc.com', '$2y$10$goRs0SH28GdQxAF01.bApeniGIFZYdt5KhvwLdx7qeORtlCeo0pby', 'vendedor', 1, NULL, NULL, '2026-11-22 10:27:12'),
(3, 'Samuel', 'samuelopes067@gmail.com', '$2y$12$3qtjXfmHA5g5FDe9TG.0juK8XBwqTyBk78JGtigwUPuqq6nx1t3sK', 'admin', 1, NULL, NULL, '2026-11-22 15:56:52'),
(4, 'teste', 'teste@email.com', '$2y$12$9FXLpGMGHxbBh/YwzYMCIefwOdYsvkPvHUiPz5RNjfFodEeUr5KNW', 'vendedor', 1, NULL, NULL, '2026-11-23 09:09:01');

--
-- Índices de tabelas apagadas
--

--
-- Índices de tabela `cartoes`
--
ALTER TABLE `cartoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cartoes_cliente` (`cliente_id`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `contatos`
--
ALTER TABLE `contatos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `entradas`
--
ALTER TABLE `entradas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_entradas_fornecedor` (`fornecedor_id`),
  ADD KEY `fk_entradas_usuario` (`usuario_id`);

--
-- Índices de tabela `entrada_itens`
--
ALTER TABLE `entrada_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_entrada_itens_entrada` (`entrada_id`),
  ADD KEY `fk_entrada_itens_produto` (`produto_id`);

--
-- Índices de tabela `formas_pagamento_config`
--
ALTER TABLE `formas_pagamento_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nf_pedido` (`pedido_id`),
  ADD UNIQUE KEY `uq_nf_numero_serie` (`serie`,`numero`);

--
-- Índices de tabela `pagamento_log`
--
ALTER TABLE `pagamento_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pagamento_log_pedido` (`pedido_id`),
  ADD KEY `fk_pagamento_log_usuario` (`usuario_id`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pedidos_cliente` (`cliente_id`),
  ADD KEY `fk_pedidos_usuario` (`usuario_id`);

--
-- Índices de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pedido_itens_pedido` (`pedido_id`),
  ADD KEY `fk_pedido_itens_produto` (`produto_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `fk_produtos_categoria` (`categoria_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de tabelas apagadas
--

--
-- AUTO_INCREMENT de tabela `cartoes`
--
ALTER TABLE `cartoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `contatos`
--
ALTER TABLE `contatos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `entradas`
--
ALTER TABLE `entradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `entrada_itens`
--
ALTER TABLE `entrada_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `formas_pagamento_config`
--
ALTER TABLE `formas_pagamento_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `pagamento_log`
--
ALTER TABLE `pagamento_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para dumps de tabelas
--

--
-- Restrições para tabelas `cartoes`
--
ALTER TABLE `cartoes`
  ADD CONSTRAINT `fk_cartoes_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `entradas`
--
ALTER TABLE `entradas`
  ADD CONSTRAINT `fk_entradas_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`),
  ADD CONSTRAINT `fk_entradas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `entrada_itens`
--
ALTER TABLE `entrada_itens`
  ADD CONSTRAINT `fk_entrada_itens_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `entradas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entrada_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  ADD CONSTRAINT `fk_notas_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `pagamento_log`
--
ALTER TABLE `pagamento_log`
  ADD CONSTRAINT `fk_pagamento_log_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagamento_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `fk_pedido_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
