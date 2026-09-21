# Atualização do Plugin: Gestão de Produtos e Ações em Massa (v1.2.2)

Esta atualização introduz o suporte à integração bidirecional e cadastro de produtos do WooCommerce diretamente na CEGID (TOConline), além de controles avançados de **Ações em Massa (Bulk Actions)**, filtros dinâmicos de status na listagem de inventários e **layout 100% responsivo para telas menores** (evitando estouro horizontal de botões).

---

## ⚙️ Parametrização Fiscal Obrigatória

Para que o WooCommerce possa criar produtos na CEGID de forma automática ou com apenas um clique, o TOConline exige uma parametrização fiscal básica. Isso ocorre porque o WooCommerce nativo não possui os campos burocráticos exigidos pela Autoridade Tributária de Portugal.

Nas **Configurações** do plugin, foram adicionados os seguintes campos que devem ser preenchidos uma única vez:

1. **Descritor de IVA Padrão:** O código de imposto que será associado ao produto na CEGID. 
   * *Exemplo para taxa normal (23%):* `IVA-M23`
   * *Exemplo para taxa reduzida (6%):* `IVA-M06`
2. **Unidade de Medida Padrão:** A unidade usada para o inventário.
   * *Exemplo:* `unidade`, `litro` ou `kg`.
3. **Motivo de Isenção de IVA (Opcional):** Se o seu descritor de IVA padrão for de isenção, insira a menção da lei correspondente aqui.
4. **Enviar Automaticamente ao Publicar (Opcional):** Se marcar esta opção, sempre que publicar um produto novo ou salvar alterações de preço/nome no WooCommerce, o plugin criará ou atualizará o artigo na CEGID em segundo plano de forma silenciosa.

---

## 📦 Novas Ações em Massa (Bulk Actions) e Filtros

Tanto na aba de Faturas quanto na aba de Estoques, agora você dispõe de caixas de seleção (checkboxes) e menus suspensos de ações:

### 1. Filtro Dinâmico por Status (Visualização Rápida)
* **Onde fica:** Na aba de **Sincronizar Estoques**, ao lado do menu de Ações em Massa.
* **O que faz:** Permite alternar a visualização da tabela de existências entre:
  * **Todos os Status:** Exibe o catálogo completo.
  * **Apenas Pendentes:** Oculta os itens já vinculados e **mostra instantaneamente apenas os novos produtos que precisam ser cadastrados ou vinculados na CEGID**.
  * **Apenas Cadastrados:** Mostra apenas os produtos já integrados.
* **UX Premium:** A filtragem ocorre em tempo real via JavaScript no navegador, sem necessidade de recarregar a tela do plugin.

### 2. Na aba "Validação de Faturas" (Suprimir em Lote)
* **Como usar:** Selecione os pedidos desejados clicando nos checkboxes de cada linha, selecione **"Suprimir (Lixeira)"** no dropdown superior direito e clique em **"Aplicar"**.
* **Resultado:** O plugin move todos os pedidos selecionados para a lixeira do WooCommerce de uma só vez via AJAX, limpando a tela instantaneamente.

### 3. Na aba "Sincronizar Estoques" (Cadastro e Sincronização em Lote)
* **Como usar:** Marque os produtos desejados na tabela e utilize as seguintes ações em massa no menu dropdown:
  * **Sincronizar Estoques:** Puxa o estoque em tempo real de todos os SKUs marcados de uma vez só, atualizando o WooCommerce local.
  * **Cadastrar na CEGID:** Cadastra de uma só vez todos os artigos marcados que estavam com status "Pendente" no TOConline.

---

## 🛠️ Funcionalidades de Produtos (Aba "Sincronizar Estoques")

* **Coluna "Cadastro CEGID" (Status de Vínculo):** A tabela de estoques agora exibe claramente o status de cada SKU com badges coloridas (🟢 **CADASTRADO** ou 🟡 **PENDENTE**).
* **Botão "Cadastrar Artigo" (Com um clique):** Permite criar o produto na CEGID via API imediatamente e vincular seu ID na hora.
* **Verificar Vínculos na CEGID (Em Lote):** Botão superior que busca correspondências de SKUs no portal da CEGID para associar os IDs de forma automática e silenciosa.

---

## 🎨 Ajustes Visuais, de Grid e Layout Responsivo

* **Barra de Ações Inteligente (Flex-Wrap):** As barras superiores com botões e seletores agora usam `flex-wrap: wrap;` e regras de media query responsivas no CSS do plugin. Em telas menores (como notebooks de 13 polegadas ou resoluções abaixo de 1150px), **os elementos quebram de linha e se organizam de forma limpa e automatizada, sem nunca "esmagar" ou gerar barra de rolagem horizontal**.
* **Ícone de Emissão Visível:** O ícone do botão "Emitir Fatura" foi corrigido e forçado a herdar a cor de contraste branca, tornando-se perfeitamente visível.
* **Simetria dos Botões:** Os botões de **Editar** e **Suprimir** receberam a mesma altura e layout flexbox de 36px do botão de faturamento, alinhando toda a linha de forma limpa e premium.
* **Ajuste Fino de Colunas:** A coluna de ações de faturas foi redimensionada para `32%` e os botões receberam regras para impedir encolhimento, garantindo que fiquem 100% alinhados no desktop.
