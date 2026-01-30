# Deploy Local no XAMPP - Tutorial Completo

Este guia mostra como configurar o QueueMaster localmente usando XAMPP para testes e desenvolvimento.

## 📋 Pré-requisitos

Antes de começar, certifique-se de ter:

- **XAMPP** instalado (PHP 8.1+ e MySQL/MariaDB)
- **Git** instalado
- **Composer** instalado ([getcomposer.org](https://getcomposer.org/download/))
- **Node.js 18+** instalado (para o Web App)
- **OpenSSL** disponível (já vem com Git Bash ou Windows 10+)

---

## 🏗️ Estrutura do Projeto

O QueueMaster é um monorepo com a seguinte estrutura:

```
QueueMaster/
├── api/            # Backend PHP (API RESTful)
├── web/            # Frontend Web (Quasar/Vue 3)
├── docs/           # Documentação
├── public/         # Entry point unificado (Apache aponta aqui)
└── docker-compose.yml
```

---

## 🚀 Passo a Passo

### 1. Iniciar Serviços do XAMPP

Abra o **XAMPP Control Panel** e inicie:
- ✅ **Apache** (porta 80 ou 8080)
- ✅ **MySQL** (porta 3306)

### 2. Configurar Virtual Host no Apache

Configure o Apache para apontar para a pasta `public/` (entry point unificado).

#### 2.1. Editar httpd-vhosts.conf

Abra o arquivo: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

Adicione no **final do arquivo**:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs/public"
    <Directory "C:/xampp/htdocs/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nota:** O `public/` principal roteia automaticamente entre API e Web App.

#### 2.2. Verificar httpd.conf

Certifique-se de que o módulo `mod_rewrite` está habilitado. Abra: `C:\xampp\apache\conf\httpd.conf`

Procure e **descomente** (remova o `#`) desta linha:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

E procure e **descomente**:

```apache
Include conf/extra/httpd-vhosts.conf
```

#### 2.3. Reiniciar Apache

No XAMPP Control Panel, clique em **Stop** e depois **Start** no Apache.

### 3. Clonar o Repositório

Navegue até a pasta do XAMPP e clone o projeto:

```bash
cd C:\xampp
git clone https://github.com/seu-usuario/queuemaster.git htdocs
```

**Ou**, se já tiver arquivos na pasta `htdocs`, faça:

```bash
cd C:\xampp\htdocs
git clone https://github.com/seu-usuario/queuemaster.git .
```

### 4. Instalar Dependências da API (Composer)

No diretório da API:

```bash
cd C:\xampp\htdocs\api
php composer.phar install
```

**Ou**, se o Composer estiver instalado globalmente:

```bash
cd api
composer install
```

### 5. Gerar Chaves RSA para JWT

As chaves RSA são necessárias para autenticação JWT com RS256.

#### No Git Bash ou Linux/Mac:

```bash
cd api
mkdir -p keys
openssl genrsa -out keys/private.key 2048
openssl rsa -in keys/private.key -pubout -out keys/public.key
```

#### No PowerShell (Windows):

```powershell
cd api

# Criar diretório
New-Item -ItemType Directory -Force -Path keys

# Gerar chave privada
openssl genrsa -out keys/private.key 2048

# Gerar chave pública
openssl rsa -in keys/private.key -pubout -out keys/public.key
```

**Nota:** Se o comando `openssl` não for reconhecido no PowerShell, use o Git Bash ou adicione o OpenSSL ao PATH.

### 6. Configurar Variáveis de Ambiente

Copie o arquivo de exemplo `.env.example` para `.env` (dentro da pasta `api/`):

#### Bash:
```bash
cd api
cp .env.example .env
```

#### PowerShell:
```powershell
cd api
Copy-Item .env.example .env
```

Edite o arquivo `.env` com as configurações do XAMPP:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=queue_master

# JWT Configuration
JWT_PRIVATE_KEY_PATH=keys/private.key
JWT_PUBLIC_KEY_PATH=keys/public.key
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=2592000

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,PATCH,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With

# Rate Limiting (opcional - usa memória se Redis não disponível)
REDIS_ENABLED=false
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Firebase Cloud Messaging (opcional)
FCM_SERVER_KEY=your_fcm_server_key_here
```

### 7. Criar e Migrar o Banco de Dados

#### 7.1. Executar Migrations

```bash
cd api
php scripts/migrate.php up
```

Ou navegando até a pasta scripts:

```bash
cd api/scripts
php migrate.php up
cd ../..
```

**Saída esperada:**
```
✓ Connected to MySQL server at localhost:3306

Running migrations UP...
========================

Applying: 0001_initial_up.sql ... ✓ Done

✓ All migrations applied successfully!

Database: queue_master
Host: localhost:3306
```

#### 7.2. Popular com Dados de Teste (Opcional)

```bash
cd api
php scripts/seed.php sample
```

**Saída esperada:**
```
✓ Connected to database 'queue_master' at localhost:3306

Running SAMPLE seed data...
===========================

Loading: seed_sample_data.sql ... ✓ Done

✓ Sample data loaded successfully!

Database: queue_master
Host: localhost:3306
```

**Dados criados:**
- 3 usuários: `admin@example.com`, `attendant@example.com`, `client@example.com`
- Senha para todos: `password123`
- 1 estabelecimento: "Central Medical Clinic"
- 2 serviços, 2 profissionais, 1 fila, 3 agendamentos

### 8. Verificar Configuração

#### 8.1. Verificar Estrutura de Arquivos

Certifique-se de que a estrutura está correta:

```
C:\xampp\htdocs\
├── public/                  # Entry point unificado
│   ├── index.php
│   └── .htaccess
├── api/                     # Backend PHP
│   ├── public/
│   │   ├── index.php
│   │   ├── swagger/
│   │   └── .htaccess
│   ├── src/
│   ├── routes/
│   ├── keys/
│   │   ├── private.key
│   │   └── public.key
│   ├── .env
│   └── composer.json
├── web/                     # Frontend (Quasar) - futuro
├── docs/
└── README.md
```

#### 8.2. Testar Acesso ao Apache (API Status)

Acesse no navegador: [http://localhost/api/v1/status](http://localhost/api/v1/status)

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "message": "QueueMaster API is running",
    "version": "1.0.0",
    "timestamp": "2026-01-19T21:30:00+00:00",
    "environment": "development",
    "endpoints": {
      "auth": "/api/v1/auth",
      "establishments": "/api/v1/establishments",
      "queues": "/api/v1/queues",
      "appointments": "/api/v1/appointments",
      "dashboard": "/api/v1/dashboard",
      "notifications": "/api/v1/notifications",
      "streams": "/api/v1/streams"
    }
  }
}
```

**Nota:** A rota raiz `/` fica livre para servir o web app (dashboard) futuramente.

---

## ✅ Testes de Funcionamento

Use o **PowerShell**, **Git Bash**, ou **curl** para testar os endpoints:

### Teste 1: Registrar Usuário

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123"}'
```

**PowerShell alternativo:**
```powershell
$body = @{
    name = "Test User"
    email = "test@example.com"
    password = "password123"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8080/api/v1/auth/register" `
  -Method POST `
  -Headers @{"Content-Type"="application/json"} `
  -Body $body
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 4,
      "name": "Test User",
      "email": "test@example.com",
      "role": "client"
    },
    "message": "User registered successfully"
  }
}
```

### Teste 2: Fazer Login

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

**PowerShell alternativo:**
```powershell
$body = @{
    email = "test@example.com"
    password = "password123"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8080/api/v1/auth/login" `
  -Method POST `
  -Headers @{"Content-Type"="application/json"} `
  -Body $body
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "def502004a8b7f...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": {
      "id": 4,
      "name": "Test User",
      "email": "test@example.com",
      "role": "client"
    }
  }
}
```

### Teste 3: Listar Estabelecimentos

Copie o `access_token` do login e use:

```bash
curl -X GET http://localhost:8080/api/v1/establishments \
  -H "Authorization: Bearer SEU_ACCESS_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Central Medical Clinic",
      "address": "123 Health Street, Downtown, City 12345",
      "timezone": "America/Sao_Paulo"
    }
  ]
}
```

---

## 🔍 Verificação no phpMyAdmin

Acesse: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)

- **Usuário:** `root`
- **Senha:** *(deixe em branco por padrão)*

Verifique se o banco `queue_master` foi criado com as tabelas:
- users
- establishments
- services
- professionals
- queues
- queue_entries
- appointments
- notifications
- refresh_tokens
- routes
- idempotency_keys

---

## 🛠️ Troubleshooting

### Erro: "Access denied for user 'root'@'localhost'"

**Solução:** Verifique as credenciais no `.env` e no phpMyAdmin.

### Erro: "404 Not Found" em todas as rotas

**Solução:** 
1. Verifique se o `mod_rewrite` está habilitado no `httpd.conf`
2. Verifique se o arquivo `.htaccess` existe em `public/`
3. Reinicie o Apache

### Erro: "openssl: command not found"

**Solução PowerShell:**
```powershell
# Adicionar OpenSSL ao PATH (se tiver Git instalado)
$env:Path += ";C:\Program Files\Git\usr\bin"
```

Ou use o Git Bash para executar os comandos OpenSSL.

### Erro: "Cannot assign QueueMaster\Core\Database to property..."

**Solução:** Já corrigido na versão atual. Se persistir, verifique se fez `composer install` após clonar.

### Erro: "Foreign key constraint fails" ao popular banco

**Solução:** Execute as migrations antes de rodar o seed:
```bash
php scripts/migrate.php up
php scripts/seed.php sample
```

### Apache não inicia (Porta 80 em uso)

**Solução:** 
1. Altere a porta no `httpd.conf` de `Listen 80` para `Listen 8080`
2. Atualize o VirtualHost para `*:8080`
3. Reinicie o Apache

---

## 📚 Próximos Passos

Agora que o ambiente está funcionando:

1. **Explore a API** com a collection do Postman (`postman_collection.json`)
2. **Execute os testes** com `php vendor/bin/phpunit`
3. **Leia a documentação** completa no [README.md](../README.md)
4. **Desenvolva features** adicionais conforme necessário

---

## 🎉 Conclusão

Seu ambiente de desenvolvimento local está pronto! Você pode agora:

- ✅ Registrar e autenticar usuários
- ✅ Gerenciar filas e agendamentos
- ✅ Testar todos os 35 endpoints da API
- ✅ Desenvolver e debugar localmente

**Dúvidas?** Consulte o [README.md](../README.md) ou a documentação inline no código.

---

**Ambiente:** XAMPP (Apache + MySQL)  
**PHP:** 8.1+  
**Banco:** MariaDB/MySQL  
**Status:** ✅ Pronto para desenvolvimento