## Pendentes
- [ ] Subir containers na VPS Oracle Cloud via `deploy/deploy-vps.sh` e configurar Nginx com SSL.
- [ ] Ativar a licença no WooCommerce apontando para a URL pública da VPS.

## Fazendo
- [ ] Inicialização e sincronização do repositório Git com o GitHub (`https://github.com/pitarf/PluginCEGID.git`).

## Concluído
- [x] Reformulação Mobile-First da Interface de Licenças: substituição de tabelas por Cards táteis e elegantes no mobile/tablet, e criação de Modais de Confirmação modernos (eliminando confirm nativo) (v1.4.1).
- [x] Implementação da Stack Completa de Testes no Servidor de Licenças: Vitest (14 testes unitários e de integração de API), Playwright (auditoria visual responsiva e checagem de overflow horizontal) e validação Prisma (v1.4.1).
- [x] Instalação do **gstack** do GitHub e auditoria completa de segurança (/cso, /review, OWASP) e eliminação de pontas soltas (v1.4.1).
- [x] Injeção de cabeçalhos HTTP OWASP, normalização de domínios e endpoint de desativação remota no servidor de licenças.
- [x] Proteção contra perda acidental de senhas e segredos no salvamento das configurações do WooCommerce.
- [x] Atualização do manual do usuário (`MANUAL_USER.md`) e empacotamento do novo `wc-cegid-sync.zip`.
- [x] Atualização completa da interface de gerenciamento de licenças em React/Next.js (chaves customizadas ou automáticas, reset de domínio com 1 clique, presets de validade e toasts nativos).
- [x] Validação e compilação do build de produção do Next.js 16 com Prisma 6.4.1 (sem erros).
- [x] Criação da infraestrutura de deploy na VPS (`deploy/deploy-vps.sh`, `deploy/nginx-license.conf`, `deploy/setup-vps-instructions.md`).
- [x] Suporte à rota `/api/services` e busca cruzada de serviços/produtos, resolvendo o erro HTTP 400 JA011 e convertendo o botão em "Sincronizar Estoque" (v1.3.8).
- [x] Proteção do estoque local do WooCommerce contra sobrescrita com zero quando a API pública do CEGID não expuser saldo de inventário (v1.3.8).
- [x] Identificação explícita de itens de Serviço e estado '— (Não exposto na API)' nas colunas e toasts da interface administrativa (v1.3.8).
- [x] Adição do botão de desvinculação rápida para permitir recadastrar itens ajustados no CEGID (v1.3.7).
- [x] Correção cirúrgica da sincronização de estoque individual e global (CEGID -> WooCommerce), persistência de data/hora e delegação de eventos (v1.3.6).
- [x] Detecção e associação automática de artigos pré-existentes na CEGID, convertendo botão para 'Sincronizar Estoque' (v1.3.5).
- [x] Aprimoramento completo dos Logs de Auditoria: eliminação de mensagens genéricas, inspeção de payload/resposta técnica em JSON identado e botão "Copiar Logs" (v1.3.4).
- [x] Correção do schema JSON-API no cadastro de produtos (v0) eliminando erro JA000 da CEGID (v1.3.3).
- [x] Resiliência de resposta e headers JSON-API na emissão de faturas (v1) e logs de auditoria informativos (v1.3.3).
- [x] Remoção da dependência contábil (`fiscal-year`) no fluxo de autenticação da CEGID (v1.3.2).
- [x] Implementação da aba de Logs de Auditoria e botão de Testar Conexão em tempo real (v1.3.1).
- [x] Ações em Massa via AJAX para faturas e produtos (v1.3.0).
- [x] Listar todos os produtos com SKU na aba de Estoque (incluindo variáveis, variações e assinaturas).
- [x] Adicionar coluna comparativa 'Estoque CEGID' e salvar quantidade em metadado.
- [x] Adicionar botões 'Editar' e 'Suprimir' (Lixeira) na tabela de Validação de Faturas.
- [x] Adicionar botão de Sincronizar Pedidos (Recarregar) na aba de faturas.
- [x] Implementar endpoint AJAX de descarte (Trash) de pedidos e interatividade jQuery.
- [x] Criação do Servidor de Licenças em Next.js (React) integrado ao plugin do WooCommerce.
- [x] Testar a comunicação OAuth2 em Sandbox do plugin WC CEGID Sync localmente com simulador.
- [x] Validar a sincronização automática de faturas (v1) e estoque (v0) via PULL.
- [x] Criação de simulador de API CEGID local (cegid-mock.php) para testes independentes.
- [x] Identificação do arquivo de backup do WordPress do cliente.
- [x] Desenvolvimento do plugin **WC CEGID Sync** (código modular completo de faturamento e estoque).
- [x] Escrita de manuais técnicos (`MANUAL_DEV.md`) e de utilização (`MANUAL_USER.md`).
- [x] Extração resiliente do novo backup de produção (`valedopais-farm-20260730-111202-6lm0geavzqzn.wpress`).
- [x] Instalação do plugin na nova pasta e atualização do arquivo zip correspondente.
- [x] Investigação e identificação da origem da taxa negativa de `-10,00 €` no pedido `4919` (ajuste manual da Aksert).
- [x] Configurar o banco de dados local.
- [x] Restaurar o banco de dados corrigindo incompatibilidades de data, collation e views do MySQL 8.
- [x] Configurar o prefixo correto no `wp-config.php`.
- [x] Atualizar as opções de URL local (`siteurl`/`home`) no banco de dados para evitar redirecionamentos.
- [x] Ativar o WooCommerce e o plugin WC CEGID Sync no banco local para testes imediatos.
- [x] Redefinir a senha do administrador local para acesso fácil.
