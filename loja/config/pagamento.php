<?php
/**
 * Configuração do pagamento da Loja Online.
 *
 * ATENÇÃO — PAGAMENTO REPRESENTATIVO (DEMONSTRAÇÃO):
 * Pix (QR Code), cartão de crédito/débito e boleto funcionam como no fluxo real
 * (telas, validações, geração dos códigos, baixa de estoque, confirmação do pedido),
 * mas NENHUM valor é cobrado e nenhuma operadora/banco é consultado. Os dados abaixo
 * são FICTÍCIOS. Para vender de verdade, troque a classe PagamentoSimulado por uma
 * integração com um gateway (Mercado Pago, Pagar.me, Stripe etc.).
 */

// Chave Pix FICTÍCIA (formato "chave aleatória"): não pertence a ninguém, então
// nenhum app de banco consegue concluir um pagamento com este código.
define('PIX_CHAVE', '00000000-0000-4000-8000-000000000000');
define('PIX_RECEBEDOR', 'NUCLEO PC DEMO');      // máx. 25 caracteres, sem acentos
define('PIX_CIDADE', 'NOVA IGUACU');            // máx. 15 caracteres, sem acentos
define('BOLETO_BENEFICIARIO', 'Núcleo PC Ltda — CNPJ 00.000.000/0001-00 (fictício)');

// Prazos para pagar. Passado o prazo, o pedido é cancelado e o estoque reservado volta.
define('BOLETO_DIAS_VENCIMENTO', 3);
define('PIX_MINUTOS_VALIDADE', 30);
define('CARTAO_MINUTOS_RESERVA', 30);
