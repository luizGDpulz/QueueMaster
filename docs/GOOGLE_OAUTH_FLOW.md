# Google OAuth Authentication Flow

Este documento descreve o fluxo completo de autenticação via Google OAuth implementado no QueueMaster.

## 📋 Visão Geral

O QueueMaster utiliza **Google OAuth 2.0 (ID Token/Implicit Grant)** como único método de autenticação. Isso significa:

- ✅ Não há cadastro tradicional com email/senha
- ✅ Não há senhas armazenadas no banco de dados
- ✅ Proteção contra bots e contas falsas (Google valida)
- ✅ Email sempre verificado pelo Google
- ✅ Simplificação do fluxo de segurança

---

## 🔄 Fluxo Completo de Autenticação

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           FLUXO DE AUTENTICAÇÃO                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Usuário acessa /login                                                   │
│        │                                                                    │
│        ▼                                                                    │
│  2. Frontend carrega Google Identity Services (GSI)                         │
│        │                                                                    │
│        ▼                                                                    │
│  3. Usuário clica "Entrar com Google"                                       │
│        │                                                                    │
│        ▼                                                                    │
│  4. Popup do Google abre → usuário seleciona conta                          │
│        │                                                                    │
│        ▼                                                                    │
│  5. Google redireciona para /#id_token=xxx...                               │
│        │                                                                    │
│        ▼                                                                    │
│  6. Vue Router intercepta no beforeEach                                     │
│        │                                                                    │
│        ├─► Redireciona para /auth/loading (skeleton)                        │
│        │                                                                    │
│        ▼                                                                    │
│  7. Frontend envia POST /api/v1/auth/google { id_token: "xxx" }             │
│        │                                                                    │
│        ▼                                                                    │
│  8. Backend valida token com Google (oauth2.googleapis.com/tokeninfo)       │
│        │                                                                    │
│        ├─► Verifica: audience, expiration, email_verified                   │
│        │                                                                    │
│        ▼                                                                    │
│  9. Backend busca/cria usuário no banco                                     │
│        │                                                                    │
│        ├─► findByGoogleId() → encontrou? Atualiza perfil                    │
│        │                                                                    │
│        ├─► findByEmail() → vincula Google ID à conta existente              │
│        │                                                                    │
│        └─► Não encontrou? Cria novo usuário                                 │
│                │                                                            │
│                ├─► email == SUPER_ADMIN_EMAIL? role = 'admin'               │
│                │                                                            │
│                └─► Senão: role = 'client'                                   │
│        │                                                                    │
│        ▼                                                                    │
│  10. Backend gera tokens JWT (RS256)                                        │
│        │                                                                    │
│        ├─► access_token (15 min)                                            │
│        │                                                                    │
│        └─► refresh_token (30 dias)                                          │
│        │                                                                    │
│        ▼                                                                    │
│  11. Backend retorna { user, access_token, refresh_token }                  │
│        │                                                                    │
│        ▼                                                                    │
│  12. Frontend salva tokens no localStorage                                  │
│        │                                                                    │
│        ▼                                                                    │
│  13. Redireciona para /app (dashboard)                                      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🔧 Componentes Envolvidos

### Frontend (Quasar/Vue 3)

| Arquivo | Responsabilidade |
|---------|------------------|
| `src/pages/LoginPage.vue` | Botão Google, carrega GSI, inicia popup |
| `src/pages/AuthLoadingPage.vue` | Skeleton loading durante autenticação |
| `src/router/index.js` | Intercepta `id_token` no hash, chama API |
| `src/boot/axios.js` | Configura interceptors, refresh automático |

### Backend (PHP)

| Arquivo | Responsabilidade |
|---------|------------------|
| `Controllers/AuthController.php` | Endpoint `/auth/google`, valida token |
| `Models/User.php` | `findOrCreateFromGoogle()`, `getDefaultRole()` |
| `Middleware/AuthMiddleware.php` | Gera e valida JWT access tokens |
| `Middleware/TokenMiddleware.php` | Gerencia refresh tokens |

---

## 🔐 Configuração do Google Cloud Console

### 1. Criar Projeto
1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um novo projeto ou selecione existente

### 2. Configurar OAuth Consent Screen
1. APIs & Services → OAuth consent screen
2. Selecione "External"
3. Preencha: App name, User support email, Developer contact
4. Adicione scopes: `email`, `profile`, `openid`

### 3. Criar Credenciais OAuth
1. APIs & Services → Credentials
2. Create Credentials → OAuth 2.0 Client ID
3. Application type: **Web application**
4. Authorized JavaScript origins:
   - `http://localhost:9000` (dev)
   - `https://seudominio.com` (produção)
5. Authorized redirect URIs:
   - `http://localhost:9000` (note: sem barra final também funciona)

### 4. Copiar Client ID
Copie o Client ID gerado (formato: `xxx.apps.googleusercontent.com`)

---

## ⚙️ Configuração do Ambiente

### Backend (`api/.env`)

```env
# Google OAuth (Required)
GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com

# Define o primeiro admin (configure ANTES do primeiro login)
SUPER_ADMIN_EMAIL=seu.email@gmail.com
```

### Frontend (`web/queuemaster/.env`)

```env
# Mesmo Client ID do backend
VITE_GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
```

---

## 👤 Como se Tornar Admin

O sistema não tem cadastro de admin manual. Para se tornar administrador:

### Opção 1: Pré-configurar (Recomendado)

**ANTES** do primeiro login:
1. Edite `api/.env`
2. Configure `SUPER_ADMIN_EMAIL=seu.email@gmail.com`
3. Faça login com essa conta Google
4. Pronto! Você é admin

### Opção 2: Promover via SQL

Se já logou como `client`:
```sql
UPDATE users SET role = 'admin' WHERE email = 'seu.email@gmail.com';
```

---

## 🔄 Refresh Token Flow

Após autenticação inicial, o fluxo de refresh é:

```
┌─────────────────────────────────────────────────────┐
│  access_token expira (15 min)                       │
│        │                                            │
│        ▼                                            │
│  Axios interceptor detecta 401                      │
│        │                                            │
│        ▼                                            │
│  POST /api/v1/auth/refresh { refresh_token }        │
│        │                                            │
│        ▼                                            │
│  Backend valida e rotaciona token                   │
│        │                                            │
│        ▼                                            │
│  Retorna novo access_token + refresh_token          │
│        │                                            │
│        ▼                                            │
│  Retry da requisição original                       │
└─────────────────────────────────────────────────────┘
```

---

## 🛡️ Segurança

| Aspecto | Implementação |
|---------|---------------|
| Validação do Token | Google's tokeninfo endpoint |
| Verificação de Audience | Client ID deve corresponder |
| Email Verificado | Requer `email_verified: true` |
| JWT Signing | RS256 (chaves RSA assimétricas) |
| Token Rotation | Refresh tokens são single-use |
| Rate Limiting | 10 req/min no endpoint de auth |

---

## 📝 Dados do Usuário

Campos salvos do Google OAuth:

| Campo | Origem | Descrição |
|-------|--------|-----------|
| `google_id` | `sub` | ID único do Google |
| `email` | `email` | Email do usuário |
| `name` | `name` | Nome completo |
| `avatar_url` | `picture` | URL da foto de perfil |
| `email_verified` | `email_verified` | Sempre `true` (exigido) |

---

## 🐛 Troubleshooting

### "popup_blocked_by_browser"
O navegador bloqueou o popup. Solução: Use `useGoogleOneTap` ou instrua usuário a permitir popups.

### "idpiframe_initialization_failed"
Cookies de terceiros desabilitados. O GSI precisa deles para funcionar.

### 401 após login
Verifique se `GOOGLE_CLIENT_ID` é idêntico no frontend e backend.

### "redirect_uri_mismatch"
A origem do JavaScript não está autorizada no Google Console. Adicione `http://localhost:9000`.

---

## 📚 Referências

- [Google Identity Services](https://developers.google.com/identity/gsi/web)
- [OAuth 2.0 for Client-side Apps](https://developers.google.com/identity/protocols/oauth2/javascript-implicit-flow)
- [Verify ID Tokens](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token)
