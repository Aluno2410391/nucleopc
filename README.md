# Núcleo PC — Loja de Computadores e Peças

Projeto acadêmico de Programação Web (padrão **MVC**, PHP **OOP** e **MySQL**),
inspirado no modelo apresentado no manual da "Loja Cosplay", porém aplicado a
um novo domínio (loja de hardware, no estilo Pichau/Kabum) e com **dois
sistemas independentes**:

| Sistema | Pasta | Público | O que faz |
|---|---|---|---|
| **Painel Admin** | `/admin` | Administrador e vendedores da loja física | Login, criar conta (perfil vendedor) e recuperação de senha, cadastro de usuários internos, categorias, fornecedores, produtos (com upload de foto real), entrada de mercadoria (estoque), venda de balcão, confirmação manual de pagamento pendente, emissão de nota fiscal, relatórios |
| **Loja Online** | `/loja` | Clientes finais (como Mercado Livre/Shopee) | Vitrine, catálogo com filtros/categorias/busca, página de produto com foto real, carrinho, cadastro/login de cliente (+ recuperação de senha), checkout com pagamento **representativo** (Pix com QR Code, cartão de crédito/débito, boleto), histórico de pedidos, formulário de contato |

Os dois sistemas são **aplicações PHP separadas**, mas compartilham o **mesmo
banco de dados** (`nucleopc_db`) e a **mesma pasta de uploads** (`uploads/`),
o que permite que uma venda de balcão e um pedido online sejam controlados na
mesma tabela `pedidos` e afetem o mesmo estoque em `produtos`.

---

## 1. Estrutura (padrão MVC)

```
nucleopc/
├── admin/            -> Painel Admin (loja física)
│   ├── index.php      (front controller, agora na raiz do sistema)
│   ├── .htaccess
│   ├── assets/         CSS/JS
│   ├── config/         Database.php, mail.php, empresa.php (dados do emitente da nota fiscal)
│   ├── core/           App.php (roteador), Controller.php (classe base), Validacao.php (senha ≥ 8, CPF, CNPJ, CEP, e-mail)
│   ├── services/       VerificadorService.php (APIs ViaCEP/BrasilAPI/DNS)
│   ├── controllers/    uma classe por área do sistema
│   ├── models/         uma classe por entidade do banco
│   ├── views/           arquivos .php de apresentação
│   └── storage/         log local de e-mails (ver seção 4) — acesso bloqueado por .htaccess
├── loja/             -> Loja Online (cliente final)
│   ├── index.php
│   ├── .htaccess
│   ├── assets/          CSS/JS, incluindo o gerador de QR Code (assets/js/qrcode.js)
│   ├── config/          Database.php, mail.php, pagamento.php, empresa.php
│   ├── core/            ..., Validacao.php
│   ├── controllers/
│   ├── models/          ..., Cartao.php (cartões salvos do cliente)
│   ├── services/        PagamentoSimulado.php (Pix, boleto e cartão representativos), VerificadorService.php
│   ├── views/
│   └── storage/
├── uploads/produtos/  -> pasta COMPARTILHADA de fotos de produto (ver seção 5)
├── database/          schema.sql (conforme o MER), migracao_para_mer.sql (bancos antigos)
└── docs/              documentação (.docx) e diagramas (diagramas/*.png, Diagrama.drawio)
```

O `index.php` de cada sistema agora fica na **raiz** da pasta (`admin/index.php`
e `loja/index.php`) em vez de dentro de uma subpasta `public/`, o que
simplifica a publicação em hospedagens simples (ex.: `seusite.com/` para a
loja e `seusite.com/admin/` para o painel).

O roteamento usa uma URL amigável no formato:

```
index.php?url=controller/metodo/parametro
```

Exemplo: `index.php?url=produto/detalhe/3` chama `ProdutoController::detalhe(3)`.

Todas as classes (`Database`, `App`, `Controller`, os *Models*, os
*Controllers* e os *Services*) são escritas em **PHP Orientado a
Objetos** (POO), com conexão ao banco via **PDO** (evitando SQL Injection com
*prepared statements*).

---

## 2. Banco de dados

O script `database/schema.sql` segue o **MER oficial** (`MER_.pdf`): 14 tabelas
e a view `vw_pedidos_pagamento`, com os mesmos tipos, chaves estrangeiras
(`ON UPDATE/ON DELETE`) e comentários (RF17, RF18, RN04, RN09, RN10, UC08,
RNF08). Já inclui dados de exemplo: usuários, categorias, fornecedores,
produtos e a configuração das formas de pagamento (`formas_pagamento_config`,
com o prazo de cada uma — RN10). Toda mudança de pagamento é registrada em
`pagamento_log` (auditoria).

### Passo a passo (XAMPP + phpMyAdmin)

1. Inicie o **Apache** e o **MySQL** no painel do XAMPP.
2. Abra `http://localhost/phpmyadmin`.
3. Clique em **Importar**, selecione o arquivo `database/schema.sql` e
   confirme. Isso cria o banco `nucleopc_db` com todas as tabelas e os
   dados iniciais.

> Se você já tinha uma versão anterior deste banco, faça backup e importe
> `database/migracao_para_mer.sql` (requer MariaDB, que acompanha o XAMPP): ele
> cria `formas_pagamento_config`, `pagamento_log` e a view, adiciona as colunas
> de pagamento em `pedidos` e converte os valores antigos (vendas de balcão
> antigas, que gravavam `balcao`, passam a `pix`). Alternativa: apague o banco
> e importe o `schema.sql` de novo.

---

## 3. Instalação dos sistemas

1. Copie a pasta **inteira** do projeto (`admin/`, `loja/`, `uploads/`,
   `database/`) para dentro de `htdocs` do XAMPP, por exemplo:
   ```
   C:\xampp\htdocs\nucleopc\admin
   C:\xampp\htdocs\nucleopc\loja
   C:\xampp\htdocs\nucleopc\uploads
   ```
   É importante manter `admin/`, `loja/` e `uploads/` como pastas **irmãs**
   (uma do lado da outra), pois o código referencia `uploads/` a partir de
   `../uploads/produtos/` em cada sistema.
2. Confirme os dados de conexão em `admin/config/Database.php` e
   `loja/config/Database.php` (por padrão: host `localhost`, usuário
   `root`, senha em branco — configuração padrão do XAMPP).
3. Dê permissão de escrita para a pasta `uploads/produtos/` (e para
   `admin/storage/` e `loja/storage/`), usada para salvar as fotos de
   produto e os logs de e-mail. No Linux/Mac: `chmod -R 775 uploads admin/storage loja/storage`.
4. Acesse no navegador:
   - Painel Admin: `http://localhost/nucleopc/admin/`
   - Loja Online: `http://localhost/nucleopc/loja/`

> Se o Apache tiver o módulo `mod_rewrite` ativado, o arquivo `.htaccess`
> de cada sistema permite usar URLs mais limpas (ex.: `/produto/index` em
> vez de `/index.php?url=produto/index`).

### Login de teste do Painel Admin

| E-mail | Senha | Perfil |
|---|---|---|
| admin@nucleopc.com | admin123 | Administrador |
| vendedor@nucleopc.com | admin123 | Vendedor |

Só o **Administrador** vê o menu **Usuários**, onde é possível cadastrar
novos vendedores/administradores e alterar perfis. A tela de login também tem
**Criar conta** (`auth/cadastro`): quem se cadastra por ali entra sempre como
**vendedor** — o perfil administrador só é concedido por um administrador, em
*Usuários*. Na Loja Online, qualquer cliente pode se cadastrar em **Cadastre-se**.

---

## 4. Recuperação de senha

Implementada nos dois sistemas (`auth/esqueciSenha` no admin e
`cliente/esqueciSenha` na loja): o usuário informa o e-mail, o sistema gera
um token válido por 1 hora e tenta enviar um e-mail com o link de
redefinição.

**Importante:** o XAMPP, por padrão, **não** envia e-mails de verdade (a
função `mail()` do PHP precisa de um servidor de envio configurado, o que o
XAMPP não traz pronto). Por isso, além de tentar o envio, o sistema também:

- grava uma cópia do e-mail em `admin/storage/emails.log` ou
  `loja/storage/emails.log` (pastas bloqueadas para acesso via navegador); e
- **exibe o link de redefinição diretamente na tela**, logo após o pedido de
  recuperação — assim dá para testar o fluxo completo mesmo sem SMTP
  configurado.

Para envio real em produção, configure um serviço SMTP (Gmail, SendGrid,
Mailtrap etc.) — o local para isso é `admin/config/mail.php` e
`loja/config/mail.php`.

---

## 5. Upload de imagens de produto

O cadastro de produto (Painel Admin → Produtos → Novo/Editar) agora aceita
o **envio real de uma foto** (JPG, PNG ou WEBP, até 2MB), em vez de apenas
digitar o nome de um arquivo. O arquivo é validado (tipo e tamanho) e salvo
em `uploads/produtos/`, uma pasta **compartilhada** entre os dois sistemas
(fica um nível acima de `admin/` e `loja/`), para que a mesma foto apareça
tanto na listagem do painel quanto na vitrine da loja online.

Produtos sem foto cadastrada (ou se o arquivo não for encontrado) mostram
automaticamente a imagem `uploads/produtos/sem-imagem.png`, incluída no
projeto como placeholder padrão.

---

## 6. Pagamento (Pix, cartão e boleto representativos)

O checkout da Loja Online oferece **Pix, cartão de crédito, cartão de débito
e boleto bancário**, com o fluxo completo de cada um: QR Code, formulário de
cartão com validação e bandeira detectada automaticamente, e boleto com
linha digitável e código de barras de verdade (mesmos algoritmos — CRC16 do
Pix, DV módulo 10/11 do boleto, Luhn do cartão — usados no mercado real).

**Nenhum valor é cobrado de verdade.** Não há gateway, banco ou operadora
envolvidos — é um pagamento **representativo**, pensado para a apresentação
e para rodar sem depender de internet, conta em serviço externo ou
credenciais de terceiros. Toda a lógica está isolada em
`loja/services/PagamentoSimulado.php` e `loja/config/pagamento.php`; para
processar pagamentos de verdade no futuro, essa classe é o único lugar a
trocar por uma integração real (Mercado Pago, Pagar.me, Stripe etc.).

### Como funciona o fluxo

1. No checkout, o cliente informa o endereço e escolhe a forma de pagamento.
2. Ao confirmar, o pedido é criado com status `aguardando_pagamento` e o
   estoque é **reservado** (debitado desde já, para não vender o mesmo item
   duas vezes enquanto o cliente ainda está pagando).
3. A tela de pagamento mostra o que for adequado à forma escolhida:
   - **Pix** — QR Code (gerado no navegador, sem internet) e código
     "copia e cola"; botão **"Já paguei"** confirma o pedido na hora.
   - **Cartão de crédito/débito** — formulário com prévia do cartão,
     detecção de bandeira, parcelamento (crédito) e opção de salvar o
     cartão (guardamos só bandeira, final e validade — nunca o número
     completo nem o CVV); ao pagar, o pedido é confirmado na hora.
     Cartões terminados em **0002** simulam uma recusa, para testar esse
     cenário.
   - **Boleto** — linha digitável e código de barras gerados na hora, com
     vencimento em alguns dias; o pedido fica `aguardando_pagamento` até
     alguém confirmar (veja o próximo item), como acontece com um boleto
     real.
4. Cada forma tem um prazo para pagar (configurável em
   `loja/config/pagamento.php`); se o tempo esgotar, o pedido é cancelado
   automaticamente e o estoque reservado volta — isso é conferido sempre
   que o cliente abre o checkout, a tela de pagamento ou "Meus pedidos".
5. O cliente também pode cancelar o pedido manualmente a qualquer momento
   antes de pagar.

### Confirmação manual (boleto) no Painel Admin

Como não existe banco de verdade avisando quando um boleto foi pago, o
Painel Admin (em **Vendas**) mostra o status de cada pedido online e
oferece, para os que estão `aguardando_pagamento`, os botões **"Confirmar
pagamento"** (simula a compensação) e **"Cancelar"** (devolve o estoque) —
é assim que o vendedor "liquida" um boleto na loja física antes de emitir a
nota fiscal, que só pode ser emitida para pedidos **confirmados**.

---

## 7. Fluxo sugerido para testar

**No Painel Admin:**
1. Login → Dashboard.
2. Em **Usuários**, cadastrar um novo vendedor (apenas Administrador vê essa opção).
3. Cadastrar uma **Categoria** e um **Fornecedor** (o CEP preenche o endereço automaticamente).
4. Cadastrar um novo produto **enviando uma foto real**, ou usar os produtos do seed.
5. Registrar uma **Entrada de estoque** (soma no estoque do produto).
6. Registrar uma **Venda (balcão)** — o estoque é debitado automaticamente.
7. Emitir a **Nota Fiscal** da venda.
8. Consultar **Relatórios** e exportar em CSV.
9. Testar **Esqueci minha senha** na tela de login.

**Na Loja Online:**
1. Navegar pela vitrine e pelo catálogo (agora com fotos reais dos produtos).
2. Abrir um produto e adicionar ao carrinho.
3. Criar uma conta de cliente (ou fazer login) — e testar **Esqueci minha senha**.
4. Finalizar a compra e experimentar cada forma de pagamento:
   - **Pix**: veja o QR Code e clique em "Já paguei" para confirmar na hora.
   - **Cartão**: use o botão "preencher automaticamente" (cartão de teste) ou
     digite qualquer número válido pelo algoritmo de Luhn; termine em
     **0002** para simular uma recusa; marque "salvar cartão" para testar o
     cartão salvo numa próxima compra.
   - **Boleto**: veja a linha digitável e o código de barras gerados; para
     confirmar o pagamento, use o botão "Confirmar pagamento" no Painel
     Admin (Vendas) — assim como aconteceria com a compensação real.
5. Conferir o resultado em **Meus pedidos** (ou deixar o prazo vencer, para
   ver o cancelamento automático e a devolução do estoque).

Como as duas aplicações usam o mesmo banco, uma compra feita na Loja Online
aparece no Painel Admin em **Vendas**, com origem "Online", e também entra
nos relatórios gerenciais assim que for confirmada.

---

## 8. Design

Os dois sistemas têm identidades visuais propositalmente diferentes:

- **Painel Admin**: visual de console de operação (fundo grafite escuro,
  azul elétrico, fonte monoespaçada para SKUs/valores) — pensado para uso
  interno e leitura rápida de dados.
- **Loja Online**: visual de e-commerce (cabeçalho azul-marinho, cards
  claros, laranja como cor de ação) — pensado para o cliente final navegar
  e comprar.

---

## 9. Novidades desta versão

- **Banco conforme o MER**: `schema.sql` reescrito; a tabela de configuração
  de pagamento define o prazo (RN10) e os pedidos guardam código Pix, QR Code,
  linha digitável e código de barras do boleto (RF18).
- **Logo**: chip com a letra "N" (`assets/img/` de cada sistema, SVG e PNG),
  usada no cabeçalho/rodapé, telas de login, favicon e nota fiscal.
- **Nota fiscal automática**: emitida assim que o pedido é confirmado (venda de
  balcão no ato; pedido online ao aprovar o pagamento, inclusive confirmação
  manual). O cliente vê e imprime em *Meus pedidos* → *Nota fiscal*
  (`pedido/notaFiscal/{id}`); o painel abre a nota logo após a venda.
- **APIs de verificação** (`index.php?url=api/...`): CEP (ViaCEP, com BrasilAPI
  como reserva), CNPJ (formato; se for real, consulta a BrasilAPI), CPF (só o formato; não precisa ser real) e e-mail (formato
  + DNS + bloqueio de descartáveis + já cadastrado). Se a API estiver fora do ar
  ou o servidor bloquear conexões de saída, o sistema segue com a validação
  local. O servidor sempre valida de novo ao salvar.
- **Senha com no mínimo 8 caracteres** em todos os fluxos (cadastro, redefinição
  e usuários do painel), no navegador e no servidor (`Validacao::senha`).
- **Permissões conforme os diagramas**: vendedor atua em login, venda de
  balcão, pedidos pendentes, nota fiscal, entrada de estoque e mensagens;
  produtos, categorias, fornecedores, relatórios e usuários são do administrador
  (`requireAdmin` nesses controllers).
- **Correções**: o hash de senha do seed antigo correspondia a "password" e não
  a "admin123"; editar fornecedor sem trocar o CNPJ acusava "CNPJ já cadastrado";
  relatórios agora contam só pedidos pagos.
