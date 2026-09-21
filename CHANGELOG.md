# Changelog

## [1.4.1] - 2026-09-20
### Segurança & Auditoria (gstack / OWASP / Pontas Soltas)
- **Instalação e Execução de Auditoria com gstack (`/cso`, `/review`):**
  - Repositório `gstack` instalado e dependências sincronizadas com Bun (`bun 1.4.2`).
  - Varredura de segurança cobrindo ciclo de vida de credenciais, sanitização de inputs, headers HTTP OWASP, validações CSRF/nonce e controle de acesso.
- **Servidor de Licenças (`cegid-license-server`):**
  - **Injeção de Cabeçalhos HTTP de Segurança:** Adicionados no `next.config.mjs` os cabeçalhos `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block` e `Referrer-Policy: origin-when-cross-origin`. Desativado o vazamento de tecnologia via `poweredByHeader: false`.
  - **Normalização Resiliente de Domínios:** Implementada a função utilitária `normalizeDomain` nas rotas `/activate` e `/verify` para tratar inconsistências de maiúsculas/minúsculas, portas (ex: `:443`, `:80`), barra final (`/`) e protocolos (`http://`, `https://`).
  - **Novo Endpoint de Desativação Remota (`/api/license/deactivate`):** Criado endpoint para liberar a chave (`domain: null`) remotamente quando o administrador desativa o plugin no WordPress, permitindo a reutilização legal da licença em migrações de servidor sem intervenção manual no banco.
  - **Hardening no Proxy Nginx (`deploy/nginx-license.conf`):** Inclusão de cabeçalhos de segurança nativos e restrição de payload (`client_max_body_size 10M`).
- **Plugin WooCommerce (`wc-cegid-sync`):**
  - **Proteção contra Perda Acidental de Credenciais:** Corrigido método `Cegid_Settings::sanitize_settings` para que campos de senha (`password`) e segredo (`client_secret`) não sejam sobrescritos com valores vazios caso o formulário de configurações seja salvo sem re-digitação de credenciais.
  - **Sincronização de Desativação Remota de Licença:** O manipulador `Cegid_Ajax_Handler::ajax_deactivate_license` agora dispara chamada POST remota à API do servidor para liberar a licença antes de limpar os metadados locais.
  - **Detecção Confiável de Domínio no WordPress:** Utiliza fallback seguro para `wp_parse_url(home_url(), PHP_URL_HOST)` caso a variável `$_SERVER['SERVER_NAME']` esteja ausente ou manipulada por reverse proxy.
  - **Reempacotamento Distribuível:** Gerado novo arquivo `wc-cegid-sync.zip` contendo todas as correções.
- **Integração da Stack de Testes Automatizados & Auditoria Visual (Playwright + Vitest):**
  - **Suíte de Testes Unitários e de Integração com Vitest (`npm test`):**
    - Criados 14 testes automatizados em [`tests/unit/license-api.test.js`](file:///c:/Git/Wordpress/ValedoPais/cegid-license-server/tests/unit/license-api.test.js) cobrindo todos os cenários de ativação, rejeições por expiração/suspensão, detecção de mismatch de domínio, verificação de status, desativação remota e operações de CRUD com geração automática de chaves `VP-XXXX-XXXX-XXXX`.
    - Identificada e corrigida ponta solta na rota `/api/licenses` (falta de parsing do body no POST).
  - **Auditoria Visual & Responsividade com Playwright (`npm run test:visual`):**
    - Script [`tests/audit-visual.mjs`](file:///c:/Git/Wordpress/ValedoPais/cegid-license-server/tests/audit-visual.mjs) executando Chromium Headless em 5 viewports: Desktop (1440x900), Tablet (768x1024), iPhone (390x844), Mobile SE (375x667) e Mobile Compact (320x568).
  - **Mecanismo Anti-Pirataria Nível 3: Verificação de Integridade e Tamper Detection (SHA-256):**
    - **Módulo de Autenticação de Código (`Cegid_Integrity`):** Implementado no plugin WordPress para gerar manifestos criptográficos SHA-256 normalizados dos 4 arquivos vitais (`class-cegid-settings.php`, `class-cegid-ajax-handler.php`, `class-cegid-api-client.php` e `wc-cegid-sync.php`).
    - **Validação Cruzada no Servidor de Licenças (`integrity-validator.js`):** Cada ativação ou verificação remota compara os hashes recebidos com os originais oficiais assinados. Caso o cliente altere uma única linha para contornar a licença, o servidor bloqueia o acesso imediatamente com código `tampered_code`.
    - **Bloqueio Local Preventivo:** Se os arquivos vitais forem corrompidos ou apagados, o método `Cegid_Settings::is_license_active()` retorna `false` por padrão.
    - **Domínio de Produção Oficial com HTTPS:** Configurado subdomínio `https://license.rafaelpitaoficial.com.br` com certificado SSL Let's Encrypt na VPS.
    - **Modais de Confirmação Modernos:** Eliminado qualquer uso do `window.confirm()` nativo do navegador. Criado componente modal React com backdrop blur, ícones de alerta dinâmicos, textos explicativos e touch targets ergonômicos para ações críticas (Reset de Domínio e Exclusão).
    - **Visualização em Tabela Mantida para Telas Grandes (`hidden lg:block`):** Exibição compacta preservada apenas para resoluções de desktop.



## [1.4.0] - 2026-09-20
### Adicionado / UX & Dicas de Ferramenta
- **Sistema de Dicas de Ferramenta e Guias Rápidos Integrados:**
  - **Guias Rápidos em Cada Aba (`cegid-tab-help-box`):** Painéis recolhíveis elegantes no topo de todas as abas (**Validação de Faturas**, **Sincronizar Estoques**, **Logs de Auditoria**, **Ativação da Licença** e **Configurações**) com cartões passo a passo coloridos que orientam a operação diária.
  - **Tooltips Contextuais Nativos (`data-cegid-tooltip`):** Balões flutuantes em CSS escuro aplicados a todos os botões, cabeçalhos de coluna e ícones de interrogação (<span class="dashicons dashicons-editor-help"></span>), explicando o que cada ação executa e como proceder.
  - **Guia Rápido no Servidor de Licenças (Next.js):** Seção colapsável integrada no painel administrativo explicando a geração de chaves `VP-XXXX-XXXX-XXXX`, o vínculo de domínio, a função de Reset de Domínio e as métricas.
  - **Manual do Usuário Expandido:** Atualizado o `MANUAL_USER.md` com explicações do sistema de auxílio visual e orientações de cada recurso.
  - **Novo Pacote do Plugin (`wc-cegid-sync.zip`):** Regenerado com as melhorias visuais e tooltips.

## [1.3.9] - 2026-09-20
### Adicionado / Infraestrutura
- **Servidor de Licenças Isolado em Docker na VPS Oracle Cloud:**
  - **Containers Isolados:** Configuração do `docker-compose.yml` e `Dockerfile` multi-stage otimizado para arquitetura ARM64 (Oracle Cloud Ampere A1).
  - **Portas Exclusivas:** Servidor Next.js na porta `3010` e banco de dados PostgreSQL 16 na porta `5435`, garantindo zero conflito com serviços legados da VPS (VORTIXIA).
  - **Dashboard Web Administrativo (React/Next.js):** Interface com Tailwind CSS, métricas em tempo real (ativas, expiradas, suspensas), gerador de chaves formatadas (`VP-XXXX-XXXX-XXXX`), presets de expiração (1 mês, 6 meses, 1 ano, Vitalícia) e ação de reset de domínio com 1 clique.
  - **Infraestrutura e Deploy Automatizado:** Criação do script `deploy/deploy-vps.sh`, template Nginx com proxy reverso e instruções completas em `deploy/setup-vps-instructions.md`.
  - **Versionamento Unificado:** Estrutura de repositório unindo o plugin WordPress (`wc-cegid-sync`) e o servidor de licenças (`cegid-license-server`) para publicação no GitHub (`https://github.com/pitarf/PluginCEGID.git`).

## [1.3.8] - 2026-09-20
### Corrigido / Aprimorado
- **Vínculo Transparente de Serviços Pré-existentes no CEGID:**
  - **Suporte Nativo a Serviços (`/api/services`):** Implementada a rota `/api/services` para artigos de serviços/assinaturas (`subscription`, `variable-subscription`, `subscription_variation`, produtos virtuais).
  - **Resolução do Erro JA011:** Quando um serviço já existia no CEGID, a tentativa de criação em `/api/products` retornava `HTTP 400 JA011 (Já existe um registo com o codigo...)`. Agora, a detecção prévia e o fallback de cadastro consultam tanto `/api/services` quanto `/api/products`.
  - **Conversão Imediata do Botão:** O artigo existente na área de Serviços do CEGID é imediatamente identificado, salvando o `_cegid_product_id` e convertendo o botão "Cadastrar Artigo" em "Sincronizar Estoque" com status **Cadastrado** (🟢).
- **Proteção Absoluta do Estoque do WooCommerce:**
  - **Prevenção de Zeramento Acidental:** A API pública de artigos do TOConline/CEGID não expõe saldos de inventário de armazém por padrão. Anteriormente, o fallback padrão `0.0` sobrescrevia o estoque real do WooCommerce (ex: 10 unidades viravam 0).
  - **Preservação de Saldo:** Se a CEGID não retornar nenhum atributo numérico de estoque na resposta, a função `extract_stock_quantity` retorna `null`. O plugin **PRESERVA** o estoque existente no WooCommerce intacto e registra o status `'na'` para a CEGID.
  - **Tratamento Específico de Serviços:** Serviços e assinaturas são explicitamente marcados como `Serviço` tanto na coluna WooCommerce quanto na coluna CEGID, sem acionar rotinas de controle de estoque físico.
- **Interface e Feedback Visual Aprimorados:**
  - Coluna **Estoque CEGID**: Exibe `— (Não exposto na API)` com tooltip informativo quando o saldo não for retornado pela API da CEGID, ou badge `Serviço` para assinaturas.
  - Notificações Toast informam com clareza a preservação do saldo local quando aplicável.

## [1.3.7] - 2026-09-19
### Corrigido / Aprimorado
- **Mapeamento de Assinaturas como Serviços no CEGID (TOConline):**
  - **Classificação Precisa de Tipos:** Implementado o método centralizado `Cegid_Data_Mapper::is_service( $product )`, que detecta com precisão se o item é uma Assinatura (`subscription`, `variable-subscription`, `subscription_variation`), Produto Virtual ou Downloadable.
  - **Roteamento Automático:**
    - **Simple Subscriptions / Assinaturas / Virtuais:** Enviados com `type: 'Service'` e `product_inventory_type: 'service'`, sendo cadastrados diretamente na área de **Serviços** (`Empresa > Itens > Serviços`) do CEGID Business.
    - **Simple Products / Produtos Físicos:** Enviados com `type: 'Product'` e `product_inventory_type: 'physical'`, sendo cadastrados na área de **Produtos** (`Empresa > Itens > Produtos`) do CEGID Business.
  - **Faturamento (Linhas de Fatura):** Linhas de pedidos de assinaturas são automaticamente faturadas com `item_type: 'Service'`, garantindo total conformidade fiscal no TOConline.
- **Identificação Visual na Tabela de Estoque:**
  - Adicionado badge de tipo em cada item: **"Serviço / Assinatura"** (roxo) ou **"Produto Físico"** (cinza).
  - Produtos do tipo Serviço exibem status de estoque amigável (`Serviço`), evitando confusão de controle de estoque físico.
- **Recurso de Desvinculação Rápida:**
  - Adicionado botão de desvinculação (`cegid-unlink-product-btn`) ao lado de "Sincronizar Estoque", permitindo resetar o vínculo do WooCommerce para recadastrar artigos que tenham sido previamente associados como produtos físicos incorretamente.

## [1.3.6] - 2026-09-18
### Corrigido / Aprimorado
- **Correção da Sincronização de Estoques (CEGID -> WooCommerce):**
  - **Single Source of Truth:** A quantidade de estoque lida no TOConline/CEGID agora é atualizada com precisão no WooCommerce forçando o controle de inventário ativo (`manage_stock = true`), atualizando o status (`instock`/`outofstock`) e sincronizando o cache interno (`wc_update_product_stock`).
  - **Consulta Direta por ID (`get_product`):** Para produtos já vinculados (`_cegid_product_id`), a consulta agora é feita diretamente no endpoint `products/{id}` na CEGID, eliminando falhas de filtro por SKU.
  - **Extração Resiliente de Estoque (`extract_stock_quantity`):** Novo método que inspeciona dinamicamente os atributos (`inventory_quantity`, `stock_quantity`, `current_stock`, `stock`, `quantity`), garantindo leitura correta e fallback seguro para 0.0 sem travar em erro.
  - **Delegação de Eventos jQuery no Botão:** Corrigido o manipulador do botão `Sincronizar Estoque` para delegação global no `document` (`$(document).on('click', '.cegid-sync-stock-btn')`), garantindo que o clique funcione tanto em botões estáticos quanto nos recém-convertidos após o cadastro.
  - **Feedback Visual na Tabela:** Atualiza imediatamente a coluna de "Última Sincronização" com a hora local, limpa o ícone de aviso de erro anterior e destaca a linha com animação suave de sucesso.

## [1.3.5] - 2026-09-18
### Adicionado / Aprimorado
- **Vínculo e Reconhecimento Automático de Artigos Pré-existentes:**
  - **Detecção Prévia:** Ao clicar em **"Cadastrar Artigo"** (ou processar em lote), o plugin agora verifica previamente se o SKU já existe no TOConline/CEGID antes de enviar requisição de cadastro.
  - Se o artigo já existir, associa o `_cegid_product_id` e o estoque atual `_cegid_stock_qty` automaticamente no WooCommerce, transicionando o status imediatamente para **Cadastrado** (🟢) e convertendo o botão azul em **"Sincronizar Estoque"**.
  - **Fallback de Duplicidade:** Caso a criação falhe por recusa de duplicidade, o sistema realiza uma varredura de recuperação por SKU, localiza o ID existente no CEGID e converte a ação em sucesso com vínculo estabelecido.
  - **Aprimoramento em `Cegid_API_Client::get_product_by_sku()`:**
    - Tentativa 1: `filter[item_code]=` (filtro padrão JSON-API).
    - Tentativa 2: `filter[item_code][eq]=` (notação estrita).
    - Tentativa 3: Varredura da lista geral de produtos (`get_all_products()`) com correspondência case-insensitive, contornando servidores que ignoram parâmetros de filtro na query string.
  - **Botão "Verificar Vínculos na CEGID" e Ação em Massa:** Atualizados para escanear e vincular todos os artigos pré-existentes de uma só vez.

## [1.3.4] - 2026-09-18
### Aprimorado
- **Logs Não-Genéricos e Contexto Estruturado:**
  - O método `Cegid_API_Client::log()` agora aceita metadados e parâmetros estruturados (`$context`), registrando informações técnicas detalhadas no PHP, no WooCommerce Logger e no banco de auditoria local.
  - **Tratamento de Erros da API CEGID:**
    - Parsing aprofundado do array `errors` retornado pela API da CEGID, extraindo código (`code`), título (`title`), detalhe (`detail`) e o apontador exato do campo com erro (`source.pointer`).
    - Adição de diagnóstico explicativo automático para o erro genérico `JA000`, orientando sobre incompatibilidades no payload ou códigos fiscais (IVA).
    - Registro do payload JSON original enviado e status HTTP no contexto de erro.
  - **Detalhamento nas Operações do Plugin:**
    - **Emissão de Fatura:** Log registra número do pedido, valor total com moeda, NIF do cliente, série de faturação, quantidade de itens e payload completo; sucesso registra número oficial emitido e ID CEGID.
    - **Cadastro de Artigos (Individual e Lote):** Log registra SKU, nome do produto, preço base e payload enviado; discrimina falhas com o motivo exato de rejeição retornado pelo TOConline.
    - **Sincronização de Estoques:** Registra cada SKU alterado com quantidade anterior e quantidade atualizada.
    - **Teste de Conexão:** Registra ambiente (Produção/Sandbox), URL da API, usuário e client ID mascarado.
- **Interface e Usabilidade do Console de Auditoria:**
  - Bloco expansível `[+] Inspecionar Dados Técnicos & Payload` com visualização de JSON formatado e identado (`<pre>`) para qualquer requisição com contexto.
  - Novo botão **"Copiar Logs"** com suporte a cópia imediata para a área de transferência, facilitando o envio de diagnósticos completos via WhatsApp ou suporte.

## [1.3.3] - 2026-09-18
### Corrigido
- **Correção no Cadastro de Artigos (Erro JA000):** Unificado o payload de criação de produtos (`ajax_create_product`, `ajax_bulk_create_products` e `wc_cegid_sync_auto_product_sync`) para utilizar o método centralizado `Cegid_Data_Mapper::map_product_to_cegid()`.
- Removidos atributos inválidos no schema JSON-API da CEGID (`description`, `unit_price`, `unit_of_measure`, `tax_descriptor`) que causavam o erro de sistema `JA000 (400 Bad Request)`.
- Adicionados campos fiscais obrigatórios: `item_description`, `sales_price`, `sales_price_includes_vat`, `tax_code` (NOR, INT, RED, ISE), `product_inventory_type`, `type` e `is_active`.
- **Aprimoramento na Emissão de Faturas:**
  - Validação resiliente de resposta da API de vendas aceitando tanto `$response['id']` quanto `$response['data']['id']`.
  - Cabeçalho `Content-Type: application/vnd.api+json` e `Accept: application/json, application/vnd.api+json` configurados para o endpoint `v1/commercial_sales_documents`.
  - Registro informativo no console de Auditoria indicando início, sucesso detalhado (Tipo de documento, ID, Número e Status Rascunho/Finalizado) e erro específico.
  - Fallback automático para `Consumidor Final` em faturas caso o cliente não tenha nome/sobrenome cadastrados no pedido.

## [1.3.2] - 2026-09-17
### Corrigido
- Remoção da dependência do módulo de Contabilidade (`/fiscal_years_list` e sub-switch para `fiscal-year`) na autenticação do TOConline.
- Uso direto do token OAuth2 para operações puramente comerciais/faturação, sanando o erro `Não tem permissão para aceder ao exercício`.

## [1.3.1] - 2026-09-01
### Adicionado
- Aba de **Logs de Auditoria** com console dark em tempo real no painel administrativo.
- Botão "Testar Conexão Agora" para diagnóstico imediato da API da CEGID.
- Parsing aprimorado e logs detalhados de requisições e respostas.

## [1.3.0] - 2026-09-01
### Adicionado
- Módulo de Licenciamento com suporte a validação de licenças e bypass vitalício.
- Ações em massa via AJAX para faturas e produtos.

## [1.0.4] - 2026-07-31
### Adicionado
- Botão "Sincronizar Pedidos (Recarregar)" no topo da aba de faturas para atualização instantânea da lista com o WooCommerce.
- Botão "Editar" na coluna Ações dos pedidos pendentes para abrir o painel de edição do pedido WooCommerce em nova aba.
- Botão "Suprimir" (Lixeira) na coluna Ações dos pedidos para permitir mover pedidos indesejados para a lixeira diretamente da tabela via AJAX.
- Coluna comparativa de estoque "Estoque CEGID" na listagem de estoque para facilitar a visualização de divergências com o WooCommerce.

### Alterado
- Modificada a consulta de produtos de estoque para incluir todos os tipos publicados com SKU (como variáveis, variações e assinaturas), corrigindo a ausência de SKUs adicionais e listando todos os 10 produtos de produção.
- Gravação determinística do metadado de quantidade do CEGID (`_cegid_stock_qty`) em todas as operações de pull manual e global de estoque.

## [1.0.3] - 2026-07-30
### Adicionado
- Nova Metabox dedicada na barra lateral de edição de pedidos do WooCommerce para exibição do ID da Fatura e link direto para baixar o PDF do TOConline.
- Campos de configuração personalizados para a URL da API e a URL de Autenticação da CEGID, fornecendo 100% de suporte a clusters customizados (como a infraestrutura `api3` de produção).
- Plugin utilitário de simulação local `CEGID API Mock` (`cegid-mock.php`) para interceptar requisições locais de teste e simular faturamento e estoques de forma autônoma.

### Alterado
- Inversão da lógica de estoque do plugin para o formato **PULL** (WooCommerce consulta o estoque atualizado na CEGID de forma global periódica via WP-Cron e sob demanda via botões no painel admin, eliminando hooks de escrita desnecessários).
- Correção de conflitos de portas locais do LocalWP no banco de dados (`http://valedopais.local:10004`), sanando erros de CORS no console durante disparos AJAX.
- Redefinição da série de documentos padrão do plugin para **OLIAK** (série de faturamento comercial de olivicultura), isolando-a da série de consultoria `CONAK`.
- Estilização premium e reativa dos botões de ação do painel administrativo (gradientes modernos, sombras dinâmicas, micro-animações no hover e alinhamento central pixel-perfect).

## [1.0.2] - 2026-07-30
### Adicionado
- Importação completa do banco de dados local com tratamento de incompatibilidades de MySQL 8.
- Correção de strict mode (datas com valor padrão zerado) e verificação de chaves estrangeiras.
- Correção de conflitos de codificação de caracteres específica do MariaDB (`utf8mb3_uca1400_ai_ci`).
- Correção de erro de sintaxe nos blobs binários vazios do Wordfence (`0x`).
- Reordenação estrutural e determinística de Views SQL no dump (views dependentes de BounceRate e uniqueVisitors).
- Atualização das URLs de domínio para `http://valedopais.local` e ativação imediata via banco dos plugins WooCommerce e WC CEGID Sync.
- Redefinição da senha do administrador `support@aksert.com` para `local123` para testes.

## [1.0.1] - 2026-07-30
### Alterado
- Atualização do ambiente de homologação a partir de um novo backup de produção do cliente (`valedopais-farm-20260730-111202-6lm0geavzqzn.wpress`).
- Atualização do script python de extração (`wpress.py`) para descompactar de forma resiliente blocos nulos e evitar falhas de `EOF` e `EISDIR`.
- Cópia do plugin local desenvolvido (`wc-cegid-sync/`) para a pasta de plugins do novo backup e atualização do arquivo zip correspondente.
- Análise e resolução da taxa negativa de `-10,00 €` no pedido `4919` (identificado como ajuste manual e intencional do suporte para equilíbrio de totais).

## [1.0.0] - 2026-07-24
### Adicionado
- Desenvolvimento completo do plugin **WC CEGID Sync**.
- Arquivo principal do plugin `wc-cegid-sync.php` e lógica modular sob `/includes`.
- Integração da API da CEGID (TOConline) com suporte a OAuth2 de duas etapas e troca de subentidade fiscal por NIF.
- Mapeador de dados para documentos de venda (Faturas FT/FR/FS) e produtos/existências.
- Painel de controle no WP Admin com duas abas em AJAX (Faturas e Estoques) e sistema premium de toasts de notificação.
- Sincronização em segundo plano de estoque de produtos via hooks do WooCommerce e WP-Cron.
- Criação de backup do WordPress do cliente e arquivos de controle (`documents/task.md` e manuaism de desenvolvimento/usuário).
