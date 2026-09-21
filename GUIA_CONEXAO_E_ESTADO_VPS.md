# 🖥️ Guia de Conexão VPS e Estado Atual da Infraestrutura (VORTIXIA & Serviços)

Este documento reúne todas as credenciais de acesso SSH, especificações da máquina, portas em uso, domínios configurados no Nginx, armazenamento e rotinas de backup da **VPS Oracle Cloud**.

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
* **Sistema Operacional:** Ubuntu 24.04 LTS (Kernel Linux aarch64)
* **Memória RAM:** 24 GB RAM (Uso médio ~2.2 GB / ~21 GB disponíveis)
* **Disco / Armazenamento:** 200 GB SSD NVMe (~85 GB livres em `/`)

---

## 🌐 3. Proxy Reverso Nginx & Domínios (Host Nativo)

O Nginx roda como serviço nativo do sistema (`systemctl status nginx`) escutando as portas públicas **80** (HTTP) e **443** (HTTPS com certificados SSL Let's Encrypt / Certbot).

### Mapeamento de Domínios:

| Domínio / Hostname | SSL | Destino Interno (Proxy Pass) | Mídias Estáticas / Cache |
| :--- | :--- | :--- | :--- |
| **`vortixia.com.br`**<br>**`www.vortixia.com.br`** | Let's Encrypt (Ativo) | `http://127.0.0.1:3005` | `/videos/` e `/uploads/` mapeados diretamente para `/var/www/vorixa-uploads/` (Cache 30 dias, max 100M) |
| **`tv.connectadvec.online`** | Let's Encrypt (Ativo) | `http://127.0.0.1:8080` | Connect TV 26 Advec Frontend |

---

## 🐳 4. Containers Docker em Execução

| Nome do Container | Serviço / Aplicação | Porta Interna | Porta do Host | Status Típico | Diretório do Código |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`vorixa-app`** | Aplicação Web Principal **VORTIXIA** (Next.js 16.3 + Turbopack) | `3000` | `127.0.0.1:3005` (Localhost) | Ativo | `/home/ubuntu/vorixa` |
| **`vorixa-postgres`** | Banco de Dados PostgreSQL de Produção (`vortixia_db`) | `5432` | `127.0.0.1:5433` (Localhost) | Ativo (Healthy) | `/home/ubuntu/vorixa` (Volume: `postgres-data`) |
| **`vorixa-minio`** | Object Storage S3 compatível (MinIO) | `9000` (API)<br>`9001` (Console) | `127.0.0.1:9010`<br>`127.0.0.1:9011` | Ativo | `/home/ubuntu/vorixa` (Volume: `minio-data`) |
| **`connecttv_frontend`** | Connect TV 26 Advec (Container Nginx) | `80` | `0.0.0.0:8080` (Acesso Nginx) | Ativo | `/home/ubuntu/connecttv26advec` |
| **`test_postgres_db`** | PostgreSQL de testes / uso compartilhado | `5432` | `0.0.0.0:5432` (Pública) | Ativo | `/home/ubuntu/postgres-test` |

---

## 📂 5. Diretórios Importantes na VPS

* **`/home/ubuntu/vorixa`**: Repositório Git e arquivos de configuração (`docker-compose.yml`, `.env`, `prisma/`) da plataforma VORTIXIA.
* **`/var/www/vorixa-uploads`**: Diretório compartilhado onde ficam os arquivos de mídia gerados (vídeos, fotos e uploads) servidos de forma ultrarrápida pelo Nginx.
* **`/home/ubuntu/backups/vorixa_postgres`**: Dumps de banco de dados gerados diariamente.
* **`/etc/nginx/sites-enabled/`**: Configurações dos hosts virtuais do Nginx (`vortixia.conf`, `tv`, `default`).

---

## 💾 6. Rotinas de Backup Automatizadas

* **Script de Execução:** `/home/ubuntu/vorixa/backup-simple.sh`
* **Cron Agendado:** Diariamente às **03:00 da madrugada** (`0 3 * * *`).
* **Log de Backup:** `/home/ubuntu/backups/vorixa_postgres/backup.log`
* **Política de Retenção:** Salva dumps compactados `.sql.gz` com data/hora e mantém a última cópia descompactada pronta para restauração imediata.

---

## 🚀 7. Como Fazer Deploy / Atualizar o VORTIXIA

Sempre que enviar alterações pelo Git (`git push origin master`), execute na VPS:

```bash
# 1. Acessar a pasta do projeto
cd /home/ubuntu/vorixa

# 2. Puxar as últimas alterações
git pull origin master

# 3. Recompilar a imagem e recriar o container sem derrubar o banco de dados
docker compose build app && docker compose up -d --no-deps app

# 4. Validar se a aplicação respondeu com sucesso
curl -I http://127.0.0.1:3005
```

---

> [!WARNING]
> ### ⚠️ Regra para Adição de Novos Serviços ou Containers:
> Caso vá criar um novo projeto ou container Docker, utilize portas de Host **não conflitantes**.
> - **Portas Proibidas (Já ocupadas no Host):** `80`, `443`, `3005`, `5432`, `5433`, `8080`, `9010`, `9011`.
> - **Portas Livres Recomendadas:** `3001`, `3002`, `8081`, `8082`, `8085`, `9020`, etc.

