# QueueMaster - Deploy com Docker (Ubuntu)

Guia completo para deploy do QueueMaster em um servidor Ubuntu usando Docker.

## Arquitetura

```
Internet → Cloudflare → Seu Server (NPM → Let's Encrypt SSL)
                                     ↓
                              Docker Compose
                         ┌───────────────────────────────┐
                         │  queuemaster_app (:3000)      │
                         │  PHP/Apache                   │
                         │  API + Frontend SPA           │
                         ├───────────────────────────────┤
                         │  queuemaster_mariadb (:3307)  │
                         │  MariaDB 10.11                │
                         └───────────────────────────────┘
```

**Apenas 2 containers** — limpo no Portainer:
- `queuemaster_app` — PHP 8.1 + Apache servindo API e Frontend (Quasar SPA buildado)
- `queuemaster_mariadb` — MariaDB com volume persistente

> O NPM do seu servidor faz o reverse proxy externo e SSL. O Docker expõe apenas uma porta interna.

## Pré-requisitos

- **Ubuntu** 20.04+ (testado em Oracle Cloud)
- **Docker** 24+ com Docker Compose v2
- **Git**
- **Nginx Proxy Manager** (NPM) já rodando no servidor
- Porta escolhida no setup **livre** (default: 3000)

> [!IMPORTANT]
> A porta que o Docker expõe (ex: 3000) **não pode conflitar** com portas já usadas no servidor. 
> Verifique com: `ss -tlnp | grep :3000`

## 1. Instalar Docker no Ubuntu

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependências
sudo apt install -y ca-certificates curl gnupg lsb-release git

# Adicionar repositório Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Adicionar seu usuário ao grupo docker (evita usar sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verificar instalação
docker --version
docker compose version
```

## 2. Clonar o Repositório

```bash
git clone https://github.com/seu-usuario/QueueMaster.git
cd QueueMaster
```

## 3. Executar o Setup

```bash
chmod +x deploy.sh
./deploy.sh
```

### Passo 3.1 → Opção 1: Setup Inicial

O wizard pergunta:

| Pergunta | Exemplo |
|----------|---------|
| Domínio/IP do servidor | `app.meusite.com` |
| Usar HTTPS? | `Y` (padrão — obrigatório em produção) |
| Porta HTTP do container | `3000` (a que o NPM vai apontar) |
| Senha root MariaDB | _gerada automaticamente_ |
| Senha app MariaDB | _gerada automaticamente_ |
| Google Client ID | `501117...apps.googleusercontent.com` |
| Email super admin | `seu@email.com` |

> O setup gera automaticamente:
> - Arquivo `.env` com todas as configurações
> - JWT keys RSA 2048 bits

### Passo 3.2 → Opção 2: Build & Deploy

Este comando:
1. Builda o container App (multi-stage: Node 20 compila Quasar → PHP/Apache serve tudo)
2. Inicia MariaDB e App
3. Aguarda o banco ficar saudável
4. Roda as migrations automaticamente

```bash
# Tempo estimado: 3-5 minutos (primeiro build)
```

## 4. Configurar o NPM

No Nginx Proxy Manager:

1. **Add Proxy Host**
2. **Domain Names**: seu subdomínio (ex: `app.meusite.com`)
3. **Forward Hostname / IP**: `localhost` (ou IP interno do servidor)
4. **Forward Port**: a porta que você escolheu (ex: `3000`)
5. **SSL** → Request a new SSL Certificate → Force SSL → HSTS

> [!IMPORTANT]
> Não abra a porta do container (ex: 3000) no firewall externo!  
> Apenas o NPM precisa alcançá-la internamente. As portas 80/443 já ficam no NPM.

## 5. Configurar Google OAuth

No [Google Cloud Console](https://console.cloud.google.com/apis/credentials):

1. Edite seu OAuth Client ID
2. Em **Authorized JavaScript origins**, adicione:
   - `https://app.meusite.com`
3. Em **Authorized redirect URIs**, adicione:
   - `https://app.meusite.com`

> Sem isso, o login com Google **não funcionará** no servidor.

## 6. Verificar o Deploy

| Serviço | URL |
|---------|-----|
| **Frontend** | `https://app.meusite.com/` |
| **API Status** | `https://app.meusite.com/api/v1/status` |
| **Swagger** | `https://app.meusite.com/swagger/` |

Via script: `./deploy.sh` → Opção 9 (Status)

## 7. Operações do Dia a Dia

Todas via `./deploy.sh`:

| Opção | Ação |
|-------|------|
| **6** | Rodar migrations |
| **7** | Rodar seeds |
| **8** | Regenerar JWT keys (invalida todos os tokens) |
| **10** | Backup do banco (salva em `backups/`) |
| **11** | Restaurar um backup |
| **12** | Rebuild após `git pull` |
| **13** | Limpar tokens expirados |

### Fluxo de Atualização

```bash
cd QueueMaster
git pull origin main
./deploy.sh  # → Opção 12 (Rebuild)
# Se houver novas migrations: → Opção 6
```

## 8. Firewall (Oracle Cloud)

```bash
# Firewall do Ubuntu (apenas SSH e NPM)
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Na Oracle Cloud Console: **VCN → Security Lists → Ingress Rules** para portas 80 e 443.

> **NÃO** abra a porta do container (3000) externamente. O NPM acessa localmente.

## 9. Troubleshooting

### Containers não iniciam
```bash
./deploy.sh  # → Opção 5 (Logs) → App
```

### API retorna erro 500
```bash
docker compose logs app --tail=100
./deploy.sh  # → Opção 6 → Migrations up
```

### Frontend mostra página em branco
```bash
# Verificar se o build do Quasar foi gerado
docker compose exec app ls -la /var/www/web/dist/spa/
# Rebuild: ./deploy.sh → Opção 12
```

### Login com Google não funciona
- Domínio nas **Authorized JavaScript origins** no Google Console?
- `GOOGLE_CLIENT_ID` correto no `.env`?
- Rebuild frontend após alterar Client ID

## 10. Estrutura Docker

```
QueueMaster/
├── docker-compose.yml          ← Produção (2 containers)
├── docker-compose.dev.yml      ← Dev local (DB + phpMyAdmin)
├── deploy.sh                   ← Script de deploy interativo
├── .env                        ← Configurações (gerado pelo setup)
├── .env.deploy.example         ← Template de referência
├── docker/
│   ├── api/
│   │   ├── Dockerfile          ← Multi-stage: Node build + PHP/Apache
│   │   └── apache.conf         ← VirtualHost config
│   └── mariadb/
│       └── init.sql            ← DB initialization
└── backups/                    ← Backups do banco
```

## 11. Dev Local Não é Afetado

| Ambiente | Como usar |
|----------|-----------|
| **Dev (XAMPP)** | Apache local + `api/.env` + `quasar dev` |
| **Dev (Docker DB)** | `docker compose -f docker-compose.dev.yml up` |
| **Produção** | `./deploy.sh` → Build & Deploy |

Os arquivos `api/.env` e `web/queuemaster/.env` do dev local **não são tocados**.
