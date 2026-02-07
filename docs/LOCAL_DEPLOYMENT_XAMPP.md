# Deploy Local no XAMPP

Guia para configurar o QueueMaster localmente usando XAMPP.

## 📋 Pré-requisitos

- **XAMPP** instalado (PHP 8.1+ e MySQL/MariaDB)
- **Composer** instalado ([getcomposer.org](https://getcomposer.org/))
- **Node.js 18+** instalado
- **OpenSSL** (já vem com Git Bash ou Windows 10+)
- **Conta Google** (para autenticação)

---

## 🚀 Instalação Rápida

### 1. Clone e Instale Dependências

```bash
# Clone para a pasta htdocs do XAMPP
cd C:\xampp
git clone https://github.com/seu-usuario/queuemaster.git htdocs

# Instale dependências da API
cd htdocs/api
composer install

# Instale dependências do Web App
cd ../web/queuemaster
npm install
```

### 2. Gere as Chaves JWT

```bash
cd api
mkdir keys
openssl genrsa -out keys/private.key 2048
openssl rsa -in keys/private.key -pubout -out keys/public.key
```

### 3. Configure o Google OAuth

1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um projeto (ou use existente)
3. Vá em **APIs & Services → Credentials**
4. Clique **Create Credentials → OAuth 2.0 Client ID**
5. Tipo: **Web application**
6. Authorized JavaScript origins: `http://localhost:9000`
7. Copie o **Client ID**

### 4. Configure o Ambiente

**Backend** (`api/.env`):
```bash
cd api
cp .env.example .env
# Edite o .env:
```

```env
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=queue_master

GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com

# ⚠️ IMPORTANTE: Configure SEU email para ser admin
SUPER_ADMIN_EMAIL=seu.email@gmail.com
```

**Frontend** (`web/queuemaster/.env`):
```bash
cd web/queuemaster
cp .env.example .env
# Edite o .env:
```

```env
VITE_GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
VITE_API_URL=http://localhost/api/v1
```

### 5. Configure o Apache

Edite `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

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

Verifique em `C:\xampp\apache\conf\httpd.conf` que estas linhas estão **descomentadas**:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
Include conf/extra/httpd-vhosts.conf
```

**Reinicie o Apache** no XAMPP Control Panel.

### 6. Execute as Migrations

```bash
cd api
php scripts/migrate.php up
```

### 7. (Opcional) Popule com Dados de Teste

```bash
php scripts/seed.php sample
```

> ⚠️ O seed NÃO cria usuários. Você deve fazer login com Google.

---

## 👤 Como se Tornar Admin

O QueueMaster usa **apenas Google OAuth** para autenticação. Não há cadastro tradicional.

### Método Recomendado (ANTES do primeiro login)

1. Edite `api/.env`
2. Configure `SUPER_ADMIN_EMAIL=seu.email@gmail.com`
3. Faça login no sistema com essa conta Google
4. ✅ Você será automaticamente admin!

### Se já logou como client

```sql
UPDATE users SET role = 'admin' WHERE email = 'seu.email@gmail.com';
```

---

## ▶️ Executando

### Backend (API)
Inicie Apache e MySQL no XAMPP Control Panel.

### Frontend (Web App)
```bash
cd web/queuemaster
npm run dev
```

### Acessar
- **Web App:** http://localhost:9000
- **API:** http://localhost/api/v1/status
- **Swagger:** http://localhost/api/v1/swagger

---

## ✅ Verificação

### Teste a API
```bash
curl http://localhost/api/v1/status
```

Resposta esperada:
```json
{
  "success": true,
  "data": {
    "message": "QueueMaster API is running",
    "version": "1.0.0"
  }
}
```

### Teste o Login
1. Acesse http://localhost:9000
2. Clique "Entrar com Google"
3. Selecione sua conta Google
4. Você será redirecionado para o Dashboard

---

## 🛠️ Troubleshooting

### "redirect_uri_mismatch" no Google OAuth
- Adicione `http://localhost:9000` em **Authorized JavaScript origins** no Google Console
- Verifique se não há barra no final da URL

### 404 em todas as rotas da API
- Verifique se `mod_rewrite` está habilitado
- Verifique se `.htaccess` existe em `public/`
- Reinicie o Apache

### Login não funciona / Tela branca
- Verifique se `VITE_GOOGLE_CLIENT_ID` está configurado no frontend
- Verifique o console do navegador (F12) para erros
- Verifique se o Apache está rodando

### "GOOGLE_CLIENT_ID not configured"
- Verifique se o arquivo `api/.env` existe e tem `GOOGLE_CLIENT_ID` configurado

---

## 📂 Estrutura de Arquivos

```
C:\xampp\htdocs\
├── public/                  # Entry point (Apache aponta aqui)
│   └── index.php
├── api/
│   ├── public/
│   │   ├── index.php
│   │   └── swagger/
│   ├── src/
│   ├── routes/
│   ├── migrations/
│   ├── keys/                # Chaves JWT (NÃO commitar!)
│   │   ├── private.key
│   │   └── public.key
│   └── .env                 # Config local (NÃO commitar!)
├── web/
│   └── queuemaster/
│       ├── src/
│       └── .env             # Config local (NÃO commitar!)
└── docs/
```

---

## 📚 Próximos Passos

1. ✅ Faça login com sua conta Google
2. ✅ Verifique se você é admin (canto superior direito mostra "Admin")
3. 📖 Leia a [Documentação da API](API_DOCUMENTATION.md)
4. 🔐 Entenda o [Fluxo de Autenticação](GOOGLE_OAUTH_FLOW.md)
5. 🧪 Teste os endpoints com [Postman](POSTMAN_GUIDE.md)

---

**Ambiente:** XAMPP (Apache + MySQL)  
**PHP:** 8.1+  
**Auth:** Google OAuth 2.0  
**Status:** ✅ Pronto para desenvolvimento
