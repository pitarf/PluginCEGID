# Guia de Instalação e Deploy na VPS Oracle Cloud

Este guia contém as instruções completas para subir o **Servidor de Licenças CEGID** na VPS Oracle Cloud (`144.22.173.125`), isolado em containers Docker específicos para não interferir nas aplicações já existentes (VORTIXIA).

---

## 1. Conexão SSH à VPS

Utilize o PowerShell no seu computador com o caminho da chave privada:

```powershell
ssh -i "G:\Meu Drive\Pita\VPS ORACLE\ssh-key-vpsOraclePrivate.key" ubuntu@144.22.173.125
```

---

## 2. Clonar ou Baixar o Repositório

Na VPS, clone o repositório no diretório home do usuário `ubuntu`:

```bash
cd ~
git clone https://github.com/pitarf/PluginCEGID.git
cd PluginCEGID/cegid-license-server
```

*(Se já tiver clonado anteriormente, faça `git pull origin main`)*

---

## 3. Configurar Variáveis de Ambiente

Copie o `.env.example` para `.env`:

```bash
cp .env.example .env
```

Edite o arquivo `.env` com `nano .env` e configure sua chave secreta da API (`LICENSE_API_KEY`) e senhas do PostgreSQL conforme desejado.

---

## 4. Subir os Containers Docker

Execute o script de deploy automatizado ou utilize o docker-compose:

```bash
chmod +x ../deploy/deploy-vps.sh
../deploy/deploy-vps.sh
```

Ou manualmente:

```bash
docker compose build --pull
docker compose up -d
docker compose exec cegid-license-app npx prisma db push
```

Verifique se os containers estão rodando:

```bash
docker compose ps
```

* **App (Next.js):** `127.0.0.1:3010`
* **PostgreSQL:** `127.0.0.1:5435`

---

## 5. Configurar o Proxy Reverso no Nginx

Copie o arquivo de configuração fornecido para o Nginx da VPS:

```bash
sudo cp ../deploy/nginx-license.conf /etc/nginx/sites-available/cegid-license.conf
```

Edite o arquivo para definir seu domínio ou subdomínio (ex: `licenca.valedopais.com`):

```bash
sudo nano /etc/nginx/sites-available/cegid-license.conf
```

Ative o site e recarregue o Nginx:

```bash
sudo ln -sf /etc/nginx/sites-available/cegid-license.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. Configurar Certificado SSL (HTTPS gratuito via Certbot)

```bash
sudo certbot --nginx -d seu-subdominio.com
```

---

## 7. Configuração no Plugin WooCommerce

No painel do WordPress (`wp-admin`), vá em **CEGID Sync > Configurações > Licença**:
* **URL do Servidor:** `https://seu-subdominio.com` (ou `http://144.22.173.125:3010` para testes locais)
* **Chave da Licença:** Cole a chave gerada no painel web (formato `VP-XXXX-XXXX-XXXX`).
