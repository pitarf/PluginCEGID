# Manual do Desenvolvedor: Ecossistema WC CEGID Sync

Este documento detalha a arquitetura técnica do plugin **WC CEGID Sync** (WooCommerce) e do micro-serviço **CEGID License Server** (Next.js), servindo de guia para manutenção e desenvolvimento futuro.

---

## 1. Arquitetura do Plugin WooCommerce

O plugin está estruturado em uma arquitetura orientada a objetos modular dentro do diretório `wc-cegid-sync/includes/`.

### Classes Principais:
* **`Cegid_Settings` (`includes/class-cegid-settings.php`):** Registra as configurações via Settings API do WordPress. Centraliza a validação de licença ativa com o método estático `is_license_active()`. Registra e gerencia as opções fiscais padrões de produtos.
* **`Cegid_API_Client` (`includes/class-cegid-api-client.php`):** Abstrai as chamadas HTTP (POST, PATCH, GET) para os clusters v0 e v1 da API do TOConline. Gerencia a autenticação e renovação automática do Token OAuth2. Implementa os métodos `create_product()`, `update_product()`, `create_invoice()`, `finalize_invoice()`, `get_product_by_sku()`, `get_product()`, `get_all_products()`, `get_all_services()` e `update_stock()`. 
  * **Rotas Distintas de Produtos vs Serviços:** Na API v0 do TOConline, produtos físicos utilizam o recurso `/api/products`, enquanto serviços utilizam `/api/services`. Os métodos `create_product`, `get_product` e `get_product_by_sku` suportam o parâmetro `$is_service`, roteando para o endpoint correto e executando fallback cruzado inteligente para localização de cadastros pré-existentes.
  * **Extração Resiliente de Estoque (`extract_stock_quantity`):** Inspeciona dinamicamente os atributos (`inventory_quantity`, `stock_quantity`, `current_stock`, etc.). Caso a resposta da API do TOConline não contenha nenhum atributo numérico de saldo em estoque (comportamento padrão de `/api/products`), o método retorna `null` (em vez de 0.0), sinalizando que o saldo não está exposto na API pública e impedindo que o estoque local do WooCommerce seja apagado acidentalmente.
  * O método `log( $message, $level, $context )` recebe contexto estruturado gravando simultaneamente no `error_log`, no WooCommerce Logger (`wc-cegid-sync`) e na option `_cegid_audit_logs`.
  * Em erros de API, analisa minuciosamente objetos `errors` da CEGID (extraindo `code`, `title`, `detail`, `source.pointer`), diagnostica códigos como `JA000` e `JA011` e preserva payload e resposta completa para inspeção no painel.
* **`Cegid_Data_Mapper` (`includes/class-cegid-data-mapper.php`):** Mapeia os dados do pedido do WooCommerce para faturas e artigos para o TOConline.
  * `is_service( $product )`: Método centralizado que identifica se um produto deve ser classificado como **Serviço** (`type: 'Service'`, `product_inventory_type: 'service'`) ou **Produto Físico** (`type: 'Product'`, `product_inventory_type: 'physical'`). Produtos do tipo `subscription`, `variable-subscription`, `subscription_variation`, virtuais (`is_virtual()`) ou para download (`is_downloadable()`) são classificados como Serviços, indo para a aba **Empresa > Itens > Serviços** no CEGID (`data.type = 'services'`). Produtos simples físicos vão para **Empresa > Itens > Produtos** (`data.type = 'products'`).
  * `map_product_to_cegid( $product )`: Gera payload JSON-API v0 com tipagem estrita (`services` vs `products`), descrição, preços sem IVA, alíquota correta (NOR, INT, RED, ISE) e motivo de isenção se aplicável.
  * `map_order_to_cegid_invoice( $order )`: Utiliza `is_service()` para marcar itens de assinatura com `item_type: 'Service'`, garantindo emissão em conformidade no TOConline.
* **`Cegid_Admin_UI` (`includes/class-cegid-admin-ui.php`):** Controla a renderização das telas administrativas. 
  * A listagem de faturas suporta descarte individual/múltiplo via AJAX, checkboxes seletores e barra de Ações em Massa.
  * A listagem de estoques executa busca irrestrita por tipo de produto com SKU, apresentando colunas de existências, badges de tipo ("Serviço / Assinatura" ou "Produto Físico"), status de vínculo com checkboxes, dropdown de filtro dinâmico por status e botões de ação com suporte a desvinculação rápida. Itens cujo estoque não é retornado pela API da CEGID exibem o status `— (Não exposto na API)`.
  * A aba de Logs de Auditoria disponibiliza terminal interativo com blocos recolhíveis `<details>` (`🔍 Inspecionar Dados Técnicos & Payload`) com JSON identado, e botão dedicado **"Copiar Logs"** com fallback para clipboard.
* **`Cegid_Ajax_Handler` (`includes/class-cegid-ajax-handler.php`):** Intercepta e processa requisições assíncronas do admin. 
  * `ajax_generate_invoice`: Emite fatura via `POST /api/v1/commercial_sales_documents` com log detalhado de auditoria (pedido, NIF, série, total, itens e payload) e captura resiliente de ID e número.
  * `ajax_sync_stock_manual`: Atualiza estoque no WooCommerce consultando prioritariamente pelo ID CEGID (`get_product`) ou fallback por SKU. Se for serviço, mantém marcado como serviço sem controle físico. Se for produto físico e a CEGID retornar `null` de saldo, **preserva o estoque existente no WooCommerce** e salva `_cegid_stock_qty = 'na'`. Se a CEGID retornar valor numérico, atualiza o inventário do WooCommerce.
  * `ajax_trash_order`: Endpoint dedicado que recebe o ID de um pedido e o move para a lixeira do WooCommerce (`$order->delete( false )`).
  * `ajax_create_product`: Verifica previamente se o artigo já existe no TOConline via `get_product_by_sku( $sku, $is_service )`; se existir na área de produtos ou serviços, associa o ID e estoque no WooCommerce de imediato. Caso contrário, envia o payload v0 (Serviço ou Produto) e, em caso de duplicidade (`JA011`), recupera o ID existente na área de serviços ou produtos e converte o botão em "Sincronizar Estoque".
  * `ajax_unlink_product`: Remove o vínculo (`_cegid_product_id` e metadados) de um artigo no WooCommerce, permitindo recadastrá-lo com as novas diretrizes.
  * `ajax_verify_product_links`: Varre todos os produtos pendentes que possuem SKU e associa os IDs da CEGID em massa utilizando rotas de serviços e produtos.
  * `ajax_bulk_trash_orders`: Move uma lista de pedidos do WooCommerce para a lixeira em lote.
  * `ajax_bulk_create_products`: Cadastra e associa múltiplos produtos pendentes na CEGID em lote sequencial com verificação de pré-existência cruzada.
  * `ajax_bulk_sync_stocks`: Atualiza as existências de estoque de múltiplos produtos vinculados em lote com proteção para nunca zerar o WooCommerce quando a API não disponibilizar inventário.
  * `ajax_test_connection`: Dispara teste manual de comunicação OAuth2 detalhando ambiente, URLs e token retornado.
  * `ajax_clear_logs`: Limpa o buffer de auditoria persistente.
* **`Cegid_Stock_Sync` (`includes/class-cegid-stock-sync.php`):** Contém as rotinas de pull de estoques da CEGID e atualização de inventário no WooCommerce. Salva o estoque no metadado `_cegid_stock_qty`. Protege produtos locais de serem zerados quando a API não fornecer saldo de inventário. Gerencia o gatilho WP-Cron periódico.

### Persistência de Vínculos e Metadados:
O ecossistema utiliza as seguintes meta chaves adicionadas aos produtos do WooCommerce:
* `_cegid_product_id`: ID físico da entidade do artigo correspondente dentro do TOConline. Determina se o produto está vinculado/cadastrado (🟢) ou pendente (🟡).
* `_cegid_stock_qty`: A quantidade de estoque lida no último ciclo ou sincronização manual do TOConline.

### Hooks de Auto-Cadastro de Artigos:
Quando a opção "Enviar Automaticamente ao Publicar" está ativada nas configurações, a função `wc_cegid_sync_auto_product_sync( $product_id )` (registrada no arquivo principal `wc-cegid-sync.php`) intercepta os gatilhos `woocommerce_update_product` e `woocommerce_save_product_variation`. Se o SKU possuir valor mas não tiver o metadado `_cegid_product_id` configurado, a criação e vínculo na CEGID correm em segundo plano de forma silenciosa.

### Mecanismo de UX (Toast Pós-Reload):
Como ações de recarga de pedidos e existências recarregam a página via JavaScript, o plugin adota uma estratégia de passagem de estado baseada na query string:
1. O clique nos botões de recarga redireciona a página injetando o parâmetro `&cegid_msg=success` ou `&cegid_msg=orders_success` na URL.
2. Na inicialização do script jQuery (`assets/js/admin-script.js`), a URL é inspecionada via `URLSearchParams`. 
3. Se o parâmetro existir, exibe-se o Toast correspondente e limpa-se a query string da URL imediatamente utilizando `window.history.replaceState`, evitando exibições repetidas em novos refreshes normais.

### Constantes de Controle:
* `WC_CEGID_LICENSE_SERVER_URL`: URL oficial do servidor de licenças. Se omitida, assume o fallback de produção `https://license.rafaelpitaoficial.com.br`.

---

## 2. Servidor de Licenciamento (Next.js & Docker)

Localizado no diretório `cegid-license-server/`, o servidor foi desenvolvido em React e Next.js (App Router) e empacotado para execução em Docker isolado.

### Arquitetura na VPS Oracle Cloud (ARM64 / Ampere A1):
* **Isolamento de Recursos:** Executa em container Docker próprio com rede bridge dedicada `cegid-license-net` e volume persistente `cegid-postgres-data`.
* **Mapeamento de Portas:**
  * **Aplicação Next.js:** Escuta na porta `3010` (mapeada para `127.0.0.1:3010` no host), evitando conflitos com a porta `3005` do sistema VORTIXIA.
  * **Banco PostgreSQL 16:** Escuta na porta `5435` (mapeada para `127.0.0.1:5435` no host), evitando conflitos com as portas `5432` e `5433` do PostgreSQL existente.
* **Persistência de Dados e Fallback:**
  * **Prisma ORM (v6.4.1):** Model `License` em PostgreSQL com colunas `id`, `key`, `domain`, `clientName`, `clientNif`, `status` (ACTIVE/SUSPENDED), `expiresAt`, `createdAt`, `updatedAt`.
  * **Fallback em Memória:** Caso a `DATABASE_URL` não esteja acessível, ativa fallback in-memory permitindo testes offline sem interrupções.
* **Proxy Reverso Nginx & SSL:** O arquivo `deploy/nginx-license.conf` direciona o tráfego HTTPS público para `http://127.0.0.1:3010`.

### Rotas de API Pública:
* **`POST /api/license/activate`:** Recebe chave e domínio. Associa o domínio à chave caso esteja livre. Retorna status e data de expiração.
* **`POST /api/license/verify`:** Valida periodicamente se o domínio solicitante bate com o domínio registrado e se a chave não foi suspensa ou expirou.
* **`POST /api/license/deactivate`:** Desvincula o domínio de uma chave ativa.
* **`GET /api/licenses` / `POST /api/licenses`:** Listagem e criação de licenças administrativas (suporta `customKey` ou geração automática `VP-XXXX-XXXX-XXXX`).
* **`PATCH /api/licenses/[id]`:** Atualiza status ou efetua **Reset de Domínio** (`domain: null`), liberando a chave para uso em migrações.
* **`DELETE /api/licenses/[id]`:** Exclusão de licença.

---

## 3. Guia de Testes Locais e Deploy

### Testes Locais:
1. Suba o servidor Next.js na porta 3000 ou 3010:
   ```bash
   cd cegid-license-server
   npm run dev
   ```
2. No WooCommerce local, acesse a aba "Ativação da Licença".
3. Utilize a chave de teste pré-definida: **`VP-VALEDOPAIS-TEST-KEY-12345`** (ou gere uma nova no painel) para validar a ativação.

### Deploy na VPS:
Consulte o guia completo em [`deploy/setup-vps-instructions.md`](deploy/setup-vps-instructions.md).
O script automatizado [`deploy/deploy-vps.sh`](deploy/deploy-vps.sh) compila a imagem Docker e inicializa o banco e as migrações na VPS.

