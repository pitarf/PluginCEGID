# Plugin CEGID Sync & Servidor de Licenças (Next.js / Docker)

Este repositório contém a solução completa de integração entre o **WooCommerce (WordPress)** e o **ERP CEGID Primavera**, acompanhada de um **Servidor Central de Licenças** gerenciável via painel web moderno em React/Next.js e Docker.

---

## 📁 Estrutura do Repositório

```text
├── cegid-license-server/       # Servidor de Gerenciamento e Validação de Licenças (Next.js 15 + Prisma + PostgreSQL)
│   ├── src/app/                # Rotas da API (/api/license/...) e Dashboard Administrativo
│   ├── prisma/                 # Schema do banco de dados relacional (Licenças e Logs de Ativação)
│   ├── Dockerfile              # Imagem Multi-stage otimizada para Linux ARM64 (Oracle Cloud Ampere A1)
│   └── docker-compose.yml      # Orquestração do App (porta 3010) e PostgreSQL 16 (porta 5435)
│
├── wc-cegid-sync/              # Plugin WordPress / WooCommerce
│   ├── includes/               # Classes do core (API CEGID, Sincronizador, Licenciamento, etc.)
│   ├── admin/                  # Telas de administração no wp-admin (Produtos, Clientes, Faturas, Licença)
│   └── wc-cegid-sync.php       # Arquivo principal do plugin
│
├── deploy/                     # Arquivos e scripts de automação de infraestrutura para VPS
│   ├── deploy-vps.sh           # Script bash de deploy contínuo para a VPS
│   ├── nginx-license.conf      # Configuração de Proxy Reverso Nginx com SSL
│   └── setup-vps-instructions.md # Passo a passo detalhado de instalação na VPS Oracle
│
├── documents/                  # Documentações e Roadmap
│   └── task.md                 # Roadmap de tarefas do projeto
├── CHANGELOG.md                # Histórico de alterações e versões
├── MANUAL_DEV.md               # Manual técnico de desenvolvimento e arquitetura
└── MANUAL_USER.md              # Manual de uso para o usuário final
```

---

## 🚀 Como Rodar o Servidor de Licenças (Docker)

Consulte o arquivo [`deploy/setup-vps-instructions.md`](deploy/setup-vps-instructions.md) para o guia passo a passo completo na VPS Oracle Cloud.

Resumo dos comandos:
```bash
cd cegid-license-server
cp .env.example .env
docker compose build --pull
docker compose up -d
docker compose exec cegid-license-app npx prisma db push
```

* **Painel Web:** `http://127.0.0.1:3010` (ou via Nginx com HTTPS no seu domínio)
* **API de Verificação:** `POST /api/license/verify`
* **API de Ativação:** `POST /api/license/activate`
* **API de Desativação:** `POST /api/license/deactivate`

---

## 🔌 Como Instalar e Ativar o Plugin WooCommerce

1. Compacte a pasta `wc-cegid-sync` em um arquivo `.zip` ou suba diretamente para `/wp-content/plugins/wc-cegid-sync`.
2. No painel do WordPress, vá em **Plugins > Plugins Instalados** e ative o **WooCommerce CEGID Sync**.
3. Acesse **CEGID Sync > Licença**:
   * Insira a URL do seu servidor de licenças.
   * Insira a chave gerada no painel web (formato `VP-XXXX-XXXX-XXXX`).
   * Clique em **Ativar Licença**.
