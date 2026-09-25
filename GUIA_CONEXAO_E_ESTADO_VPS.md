# 🖥️ Guia de Conexão VPS e Estado Atual da Infraestrutura (Multi-Projetos)

Este documento reúne todas as credenciais de acesso SSH, especificações da máquina, portas em uso, domínios configurados no Nginx, status dos containers Docker, armazenamento e rotinas de backup da **VPS Oracle Cloud**.

> **Última Auditoria e Atualização Via SSH:** 25 de Setembro de 2026  
> **Status Geral:** 🟢 Todos os 7 containers e 3 domínios SSL ativos e operacionais.

---

## 🔑 1. Dados de Acesso SSH à VPS

* **IP da Máquina:** `144.22.173.125`
* **Usuário:** `ubuntu`
* **Caminho da Chave Privada:** `"G:\Meu Drive\Pita\VPS ORACLE\ssh-key-vpsOraclePrivate.key"`
* **Domínio Administrativo / SSH:** `tv.connectadvec.online`

### Comandos de Conexão (PowerShell / Terminal / Git Bash):

```powershell
# Conexão direta via IP:
ssh -i "G:\Meu Drive\Pita\VPS ORACLE\ssh-key-vpsOraclePrivate.key" ubuntu@144.22.173.125

# Conexão via hostname:
ssh -i "G:\Meu Drive\Pita\VPS ORACLE\ssh-key-vpsOraclePrivate.key" ubuntu@tv.connectadvec.online
```

---

## ⚙️ 2. Especificações de Hardware e Sistema

* **Provedor:** Oracle Cloud Infrastructure (OCI) - Always Free / Ampere A1 ARM
* **Sistema Operacional:** Ubuntu 24.04 LTS (Kernel Linux 6.8.0-1011-oracle aarch64)
* **Memória RAM:** 24 GB RAM (Uso médio: ~2.3 GB / ~21 GB livres/disponíveis)
* **Disco / Armazenamento:** 200 GB SSD NVMe (`/dev/sda1`: apenas **9.1 GB usados / 184 GB livres** - apenas **5% de ocupação**!)
* **Uptime:** Estável, sem reinicializações não programadas.

---

## 🌐 3. Proxy Reverso Nginx & Domínios (Host Nativo)

O Nginx roda como serviço nativo do sistema (`systemctl status nginx`) escutando as portas públicas **80** (HTTP com redirecionamento 301 forçado) e **443** (HTTPS com certificados SSL Let's Encrypt ativos e renovação automática via Certbot).

### Mapeamento Completo de Domínios e Serviços:

| Domínio / Hostname | Status SSL (Let's Encrypt) | Destino Interno (Proxy Pass) | Finalidade / Mídias | Arquivo Nginx |
| :--- | :--- | :--- | :--- | :--- |
| **`vortixia.com.br`**<br>**`www.vortixia.com.br`** | ✅ Válido até 04/12/2026 (ECDSA) | `http://127.0.0.1:3005` | Aplicação Web VORTIXIA.<br>`/videos/` e `/uploads/` mapeados para `/var/www/vorixa-uploads/` (Cache 30d, max 100M) | `/etc/nginx/sites-available/vortixia.conf` |
| **`license.rafaelpitaoficial.com.br`** | ✅ Válido até 20/12/2026 (ECDSA) | `http://127.0.0.1:3010` (Raiz /)<br>`http://127.0.0.1:3025` (`/api/` e `/rest/v1/`) | **Servidor de Licenças CEGID** (Raiz)<br>**API REST do Portfólio Rafael Pita** (`/api/` e `/rest/v1/`) | `/etc/nginx/sites-available/cegid-license.conf` |
| **`tv.connectadvec.online`** | ✅ Válido até 08/12/2026 (ECDSA) | `http://127.0.0.1:8080` | Frontend Web da Connect TV 26 Advec | `/etc/nginx/sites-available/tv` |

---

## 🐳 4. Containers Docker em Execução

Atualmente existem **9 containers Docker** ativos simultaneamente na VPS, isolados por redes bridge dedicadas:

| Nome do Container | Projeto / Serviço | Porta Interna | Porta do Host (Host Binding) | Status Atual | Diretório Base | Volume Docker / Persistência |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`portfolio-api`** | **Portfólio API REST** (PostgREST / Supabase compatible) | `3000` | `127.0.0.1:3025` | Up (Ativo) | `/home/ubuntu/portfolio-db` | — |
| **`portfolio-db`** | **Portfólio PostgreSQL 15** (`portfolio_db`) | `5432` | `127.0.0.1:5436` | Up (Healthy) | `/home/ubuntu/portfolio-db` | `portfolio-postgres-data` |
| **`cegid-license-app`** | **CEGID License Server** (API Node.js/Next.js) | `3000` | `127.0.0.1:3010` | Up (Ativo) | `/home/ubuntu/PluginCEGID/cegid-license-server` | — |
| **`cegid-license-db`** | **CEGID PostgreSQL 16** (`cegid_license_db`) | `5432` | `127.0.0.1:5435` | Up (Healthy) | `/home/ubuntu/PluginCEGID/cegid-license-server` | `cegid-postgres-data` |
| **`vorixa-app`** | **VORTIXIA Web** (Next.js 16.3 + Turbopack) | `3000` | `127.0.0.1:3005` | Up (Ativo) | `/home/ubuntu/vorixa` | — |
| **`vorixa-postgres`** | **VORTIXIA PostgreSQL 15** (`vortixia_db`) | `5432` | `127.0.0.1:5433` | Up (Healthy) | `/home/ubuntu/vorixa` | `vorixa_vorixa-postgres-data` |
| **`vorixa-minio`** | **MinIO Object Storage** (S3 Local) | `9000` / `9001` | `127.0.0.1:9010` (API)<br>`127.0.0.1:9011` (Console) | Up (Ativo) | `/home/ubuntu/vorixa` | `vorixa_vorixa-minio-data` |
| **`connecttv_frontend`** | **Connect TV 26 Advec** (Frontend Nginx) | `80` | `0.0.0.0:8080` | Up (Ativo) | `/home/ubuntu/connecttv26advec` | — |
| **`test_postgres_db`** | **PostgreSQL Testes/Geral** (v15 Alpine) | `5432` | `0.0.0.0:5432` (Pública) | Up (Ativo) | `/home/ubuntu/postgres-test` | `postgres-test_postgres-test-data` |

---

## 📂 5. Diretórios de Projetos e Estrutura na VPS

* **`/home/ubuntu/PluginCEGID`**:
  * **`cegid-license-server/`**: Aplicação de licenciamento e banco de dados PostgreSQL rodando sob Docker (`docker-compose.yml`).
  * **`wc-cegid-sync/`**: Código-fonte do plugin WordPress/WooCommerce de sincronização de produtos, estoque e clientes com a API CEGID.
  * **`deploy/`**: Scripts de implantação rápida (`deploy-vps.sh`, `nginx-license.conf`).
* **`/home/ubuntu/vorixa`**:
  * Repositório Git, `docker-compose.yml`, scripts de geração com IA e aplicação principal VORTIXIA.
* **`/home/ubuntu/connecttv26advec`**:
  * Aplicação frontend da TV Connect ADVEC.
* **`/home/ubuntu/postgres-test`**:
  * Configuração do banco PostgreSQL de testes compartilhado.
* **`/var/www/vorixa-uploads`**:
  * Diretório de armazenamento de vídeos, fotos e uploads servidos estaticamente pelo Nginx.
* **`/home/ubuntu/backups`**:
  * Armazenamento de dumps diários automatizados do PostgreSQL (`/home/ubuntu/backups/vorixa_postgres`).
* **`/etc/nginx/sites-available/` & `/etc/nginx/sites-enabled/`**:
  * Arquivos de configuração dos Virtual Hosts do Nginx (`vortixia.conf`, `cegid-license.conf`, `tv`, `default`).

---

## 💾 6. Rotinas de Backup Automatizadas

* **VORTIXIA (PostgreSQL):**
  * **Script:** `/home/ubuntu/vorixa/backup-simple.sh`
  * **Cron Agendado:** Diariamente às **03:00 da madrugada** (`0 3 * * *`).
  * **Arquivo de Log:** `/home/ubuntu/backups/vorixa_postgres/backup.log`
  * **Retenção:** Dumps compactados `.sql.gz` com carimbo de data/hora.

---

## 🚀 7. Como Fazer Deploy / Atualizar os Projetos

### A. VORTIXIA:
```bash
cd /home/ubuntu/vorixa
git pull origin master
docker compose build app && docker compose up -d --no-deps app
curl -I http://127.0.0.1:3005
```

### B. CEGID License Server (`license.rafaelpitaoficial.com.br`):
```bash
cd /home/ubuntu/PluginCEGID/cegid-license-server
git pull origin main # ou atualizar arquivos
docker compose build cegid-license-app && docker compose up -d --no-deps cegid-license-app
curl -I http://127.0.0.1:3010
```

### C. Connect TV 26 Advec:
```bash
cd /home/ubuntu/connecttv26advec
docker compose restart
curl -I http://127.0.0.1:8080
```

---

## ⚠️ 8. Mapa de Portas no Host e Regras de Segurança

Para evitar conflitos de portas ao adicionar novos containers ou serviços, consulte a lista abaixo:

| Porta Host | Protocolo | Bind / Acesso | Serviço Atribuído |
| :--- | :--- | :--- | :--- |
| **`22`** | TCP | `0.0.0.0` (Público) | Acesso SSH Administrativo |
| **`80`** | TCP | `0.0.0.0` (Público) | Nginx HTTP (Redireciona para HTTPS) |
| **`443`** | TCP | `0.0.0.0` (Público) | Nginx HTTPS (SSL Let's Encrypt) |
| **`3005`** | TCP | `127.0.0.1` (Localhost) | VORTIXIA App |
| **`3010`** | TCP | `127.0.0.1` (Localhost) | CEGID License Server |
| **`3025`** | TCP | `127.0.0.1` (Localhost) | Portfólio API REST (PostgREST) |
| **`5432`** | TCP | `0.0.0.0` (Público) | PostgreSQL Testes (`test_postgres_db`) |
| **`5433`** | TCP | `127.0.0.1` (Localhost) | PostgreSQL VORTIXIA (`vorixa-postgres`) |
| **`5435`** | TCP | `127.0.0.1` (Localhost) | PostgreSQL CEGID (`cegid-license-db`) |
| **`5436`** | TCP | `127.0.0.1` (Localhost) | PostgreSQL Portfólio (`portfolio-db`) |
| **`8080`** | TCP | `0.0.0.0` (Acesso Nginx) | Connect TV 26 Advec Frontend |
| **`9010`** | TCP | `127.0.0.1` (Localhost) | MinIO API (VORTIXIA) |
| **`9011`** | TCP | `127.0.0.1` (Localhost) | MinIO Console (VORTIXIA) |

> [!TIP]
> ### Portas Livres Recomendadas para Novos Projetos:
> - **Aplicações Web:** `3001`, `3002`, `3020`, `3030`, `8085`, `8090`.
> - **Bancos de Dados:** `5437`, `5438`, `3306` (MySQL), `27017` (MongoDB).
> - **Storage / Redis:** `6379`, `9020`, `9021`.

---

## 🧹 9. Manutenção Periódica de Disco (Limpeza de Build Cache)

Durante sucessivos deploys de aplicações Node.js/Next.js (`docker compose build`), o Docker BuildKit acumula camadas temporárias de cache. 

Para recuperar espaço em disco sem afetar nenhum container em execução ou banco de dados:

```bash
# Limpeza segura do cache de compilação do Docker:
docker builder prune -a -f

# Verificar o espaço em disco recuperado:
df -h /
```

