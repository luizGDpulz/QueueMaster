# Fluxo de Autenticação JWT - QueueMaster API

Este documento explica o fluxo de autenticação JWT (JSON Web Token) utilizado no QueueMaster, incluindo o login e acesso a rotas protegidas.

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Fluxo de Login](#fluxo-de-login)
3. [Fluxo de Acesso a Rotas Protegidas](#fluxo-de-acesso-a-rotas-protegidas)
4. [Componentes do Sistema](#componentes-do-sistema)
5. [Diagrama Completo](#diagrama-completo)

---

## 🔐 Visão Geral

O QueueMaster utiliza autenticação baseada em JWT com o algoritmo **RS256** (RSA com SHA-256), que oferece maior segurança por usar criptografia assimétrica (par de chaves pública/privada).

**Características:**
- **Access Token**: Token JWT de curta duração (15 minutos padrão) para autenticação de requisições
- **Refresh Token**: Token opaco de longa duração armazenado no banco de dados para renovação
- **Algoritmo**: RS256 (chave privada assina, chave pública valida)
- **Armazenamento**: Tokens enviados via header `Authorization: Bearer <token>`

---

## 🔑 Fluxo de Login

### Rota: `POST /api/v1/auth/login`

```
┌─────────────┐
│   Cliente   │
└──────┬──────┘
       │
       │ POST /api/v1/auth/login
       │ {
       │   "email": "admin@example.com",
       │   "password": "password123"
       │ }
       │
       ▼
┌─────────────────────────────────────────────────────────┐
│  public/index.php                                       │
│  - Recebe requisição HTTP                               │
│  - Cria objeto Request                                  │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Core/Router.php                                    │
│  - Roteia para /api/v1/auth/login                       │
│  - Aplica middleware RateLimiter (10 req/min)           │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/RateLimiter.php                         │
│  - Verifica limite de requisições por IP                │
│  - Retorna 429 se exceder limite                        │
└──────────────────────────┬──────────────────────────────┘
                           │ OK
                           ▼
┌─────────────────────────────────────────────────────────┐
│  routes/api.php                                         │
│  - Chama closure que instancia AuthController           │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Controllers/AuthController.php::login()            │
│                                                         │
│  1. Validação de entrada (Validator)                    │
│     - email: required|email                             │
│     - password: required                                │
│                                                         │
│  2. Busca usuário no banco de dados                     │
│     SQL: SELECT * FROM users WHERE email = ?            │
│                                                         │
│  3. Verifica se usuário existe                          │
│     - Se não: Log de segurança + 401 Unauthorized       │
│                                                         │
│  4. Valida senha com password_verify()                  │
│     - Compara hash Argon2id do banco                    │
│     - Se inválida: Log de segurança + 401               │
│                                                         │
│  5. Gera tokens                                         │
│     a) Access Token (JWT RS256)                         │
│        - Chama AuthMiddleware::generateAccessToken()    │
│     b) Refresh Token (opaco)                            │
│        - Chama TokenMiddleware::generateRefreshToken()  │
│                                                         │
│  6. Log de sucesso                                      │
│                                                         │
│  7. Retorna resposta JSON                               │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/AuthMiddleware.php                      │
│  ::generateAccessToken()                                │
│                                                         │
│  1. Carrega chave privada RSA                           │
│     Arquivo: keys/private.key                           │
│                                                         │
│  2. Monta payload JWT                                   │
│     {                                                   │
│       "iss": "http://localhost:8080",                   │
│       "aud": "http://localhost:8080",                   │
│       "iat": 1737379200,                                │
│       "exp": 1737380100,      // +15 min                │
│       "user_id": 8,                                     │
│       "email": "admin@example.com",                     │
│       "role": "admin"                                   │
│     }                                                   │
│                                                         │
│  3. Assina com RS256                                    │
│     Usa: Firebase\JWT\JWT::encode()                     │
│                                                         │
│  4. Retorna token JWT                                   │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/TokenMiddleware.php                     │
│  ::generateRefreshToken()                               │
│                                                         │
│  1. Gera token aleatório (64 bytes)                     │
│     bin2hex(random_bytes(64))                           │
│                                                         │
│  2. Define expiração (30 dias)                          │
│                                                         │
│  3. Salva no banco de dados                             │
│     INSERT INTO refresh_tokens (                        │
│       user_id, token, expires_at                        │
│     )                                                   │
│                                                         │
│  4. Retorna token                                       │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Core/Response.php::success()                       │
│  - Retorna JSON:                                        │
│  {                                                      │
│    "success": true,                                     │
│    "data": {                                            │
│      "user": {                                          │
│        "id": 8,                                         │
│        "name": "Admin User",                            │
│        "email": "admin@example.com",                    │
│        "role": "admin"                                  │
│      },                                                 │
│      "access_token": "eyJ0eXAiOiJKV1QiLCJ...",          │
│      "refresh_token": "a1b2c3d4e5f6...",                │
│      "token_type": "Bearer",                            │
│      "expires_in": 900                                  │
│    }                                                    │
│  }                                                      │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
                    ┌─────────────┐
                    │   Cliente   │
                    │ Salva tokens│
                    └─────────────┘
```

---

## 🔒 Fluxo de Acesso a Rotas Protegidas

### Rota: `GET /api/v1/queues` (Lista de filas)

```
┌─────────────┐
│   Cliente   │
└──────┬──────┘
       │
       │ GET /api/v1/queues?establishment_id=1
       │ Headers:
       │   Authorization: Bearer eyJ0eXAiOiJKV1Qi...
       │
       ▼
┌─────────────────────────────────────────────────────────┐
│  public/index.php                                       │
│  - Recebe requisição HTTP                               │
│  - Cria objeto Request                                  │
│  - Request extrai Bearer token do header                │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Core/Router.php                                    │
│  - Roteia para /api/v1/queues                           │
│  - Aplica middlewares na ordem:                         │
│    1. RateLimiter                                       │
│    2. AuthMiddleware (PROTEÇÃO JWT)                     │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/RateLimiter.php                         │
│  - Verifica limite de requisições                       │
└──────────────────────────┬──────────────────────────────┘
                           │ OK
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/AuthMiddleware.php::__invoke()          │
│                                                         │
│  1. Extrai Bearer token                                 │
│     $token = $request->getBearerToken()                 │
│                                                         │
│  2. Verifica se token existe                            │
│     Se não: Log + 401 "Authentication required"         │
│                                                         │
│  3. Chama validateToken($token)                         │
│                                                         │
│  4. Se validação falhar:                                │
│     - Log de segurança                                  │
│     - 401 "Invalid or expired token"                    │
│                                                         │
│  5. Se validação OK:                                    │
│     - Anexa dados do usuário ao Request                 │
│       $request->user = $userData                        │
│     - Chama next($request)                              │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Middleware/AuthMiddleware.php::validateToken()     │
│                                                         │
│  1. Carrega chave pública RSA                           │
│     Arquivo: keys/public.key                            │
│     Se não existir: Exception                           │
│                                                         │
│  2. Decodifica e valida JWT                             │
│     Firebase\JWT\JWT::decode($token, Key, 'RS256')      │
│                                                         │
│     Validações automáticas:                             │
│     - Assinatura válida (RS256)                         │
│     - Token não expirado (exp)                          │
│     - Issuer correto (iss)                              │
│     - Audience correto (aud)                            │
│                                                         │
│  3. Verifica campos obrigatórios                        │
│     - user_id deve existir                              │
│     - email deve existir                                │
│                                                         │
│  4. Busca usuário no banco                              │
│     SELECT * FROM users WHERE id = ?                    │
│     - Garante que usuário ainda existe                  │
│     - Garante que não foi desativado                    │
│                                                         │
│  5. Valida email do token = email do banco              │
│     Segurança adicional contra manipulação              │
│                                                         │
│  6. Retorna dados do usuário                            │
│     return [                                            │
│       'id' => 8,                                        │
│       'name' => 'Admin User',                           │
│       'email' => 'admin@example.com',                   │
│       'role' => 'admin',                                │
│       'created_at' => '2026-01-20 ...'                  │
│     ]                                                   │
└──────────────────────────┬──────────────────────────────┘
                           │ user data
                           ▼
┌─────────────────────────────────────────────────────────┐
│  routes/api.php                                         │
│  - Chama closure que instancia QueuesController         │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Controllers/QueuesController.php::list()           │
│                                                         │
│  1. Recebe Request com $request->user preenchido        │
│     Usuário autenticado disponível em $request->user    │
│                                                         │
│  2. Extrai query parameters                             │
│     $params = $request->getQuery()                      │
│     - establishment_id (opcional)                       │
│     - status (opcional)                                 │
│                                                         │
│  3. Monta query SQL com filtros                         │
│     SELECT q.*, s.name as service_name, ...             │
│     FROM queues q                                       │
│     WHERE establishment_id = ?                          │
│                                                         │
│  4. Executa query no banco                              │
│     $queues = $db->query($sql, $values)                 │
│                                                         │
│  5. Retorna lista de filas                              │
│     Response::success(['queues' => $queues])            │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│  src/Core/Response.php::success()                       │
│  - Retorna JSON:                                        │
│  {                                                      │
│    "success": true,                                     │
│    "data": {                                            │
│      "queues": [                                        │
│        {                                                │
│          "id": 1,                                       │
│          "name": "Walk-in Consultation Queue",          │
│          "status": "open",                              │
│          "service_name": "General Consultation",        │
│          "establishment_name": "Central Medical ..."    │
│        }                                                │
│      ]                                                  │
│    }                                                    │
│  }                                                      │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
                    ┌─────────────┐
                    │   Cliente   │
                    │ Exibe filas │
                    └─────────────┘
```

---

## 🗂️ Componentes do Sistema

### 1. **Request Object** (`src/Core/Request.php`)
- **Método**: `getBearerToken()`
- **Função**: Extrai token do header `Authorization: Bearer <token>`
- **Retorno**: String do token ou null

### 2. **AuthController** (`src/Controllers/AuthController.php`)
- **Método**: `login(Request $request)`
- **Validações**:
  - Email válido
  - Senha fornecida
- **Processo**:
  1. Busca usuário por email
  2. Verifica senha com `password_verify()` (Argon2id)
  3. Gera access token (JWT)
  4. Gera refresh token (opaco)
  5. Retorna tokens + dados do usuário

### 3. **AuthMiddleware** (`src/Middleware/AuthMiddleware.php`)
- **Método**: `__invoke(Request $request, callable $next)`
- **Validações**:
  1. Token presente no header
  2. Token válido e não expirado
  3. Usuário existe no banco
  4. Email do token = email do banco
- **Resultado**: Anexa `$request->user` com dados do usuário autenticado

- **Método**: `generateAccessToken(array $user)`
- **Algoritmo**: RS256 (RSA SHA-256)
- **Chave**: `keys/private.key`
- **TTL**: 900 segundos (15 minutos)
- **Payload**:
  ```json
  {
    "iss": "http://localhost:8080",
    "aud": "http://localhost:8080",
    "iat": 1737379200,
    "exp": 1737380100,
    "user_id": 8,
    "email": "admin@example.com",
    "role": "admin"
  }
  ```

- **Método**: `validateToken(string $token)`
- **Chave**: `keys/public.key`
- **Biblioteca**: `firebase/php-jwt`
- **Validações automáticas**:
  - Assinatura RS256
  - Expiração (exp)
  - Issuer (iss)
  - Audience (aud)

### 4. **TokenMiddleware** (`src/Middleware/TokenMiddleware.php`)
- **Método**: `generateRefreshToken(int $userId)`
- **Função**: Gera token opaco de 128 caracteres
- **Armazenamento**: Tabela `refresh_tokens`
- **TTL**: 2592000 segundos (30 dias)

### 5. **Router** (`src/Core/Router.php`)
- **Função**: Gerencia rotas e aplica middlewares
- **Ordem de execução**:
  1. RateLimiter (se configurado)
  2. AuthMiddleware (para rotas protegidas)
  3. RoleMiddleware (para rotas com restrição de role)
  4. Controller action

### 6. **Database** (`src/Core/Database.php`)
- **Função**: Conexão PDO com MySQL
- **Queries de autenticação**:
  - `SELECT * FROM users WHERE email = ?`
  - `SELECT * FROM users WHERE id = ?`
  - `INSERT INTO refresh_tokens (...)`

---

## 📊 Diagrama Completo (Sequência)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant I as index.php
    participant R as Router
    participant RL as RateLimiter
    participant AC as AuthController
    participant AM as AuthMiddleware
    participant TM as TokenMiddleware
    participant DB as Database
    participant QC as QueuesController
    participant Res as Response

    Note over C,Res: FLUXO DE LOGIN

    C->>I: POST /api/v1/auth/login<br/>{email, password}
    I->>R: Criar Request & Rotear
    R->>RL: Aplicar RateLimiter
    RL->>AC: Chamar login()
    AC->>DB: SELECT user WHERE email=?
    DB-->>AC: Dados do usuário
    AC->>AC: password_verify(hash Argon2id)
    AC->>AM: generateAccessToken(user)
    AM->>AM: Carregar private.key
    AM->>AM: JWT::encode(payload, RS256)
    AM-->>AC: access_token (JWT)
    AC->>TM: generateRefreshToken(user_id)
    TM->>DB: INSERT refresh_token
    TM-->>AC: refresh_token
    AC->>Res: success(user, tokens)
    Res-->>C: {user, access_token, refresh_token}

    Note over C,Res: FLUXO DE ROTA PROTEGIDA

    C->>I: GET /api/v1/queues<br/>Authorization: Bearer {token}
    I->>R: Criar Request & Rotear
    R->>RL: Aplicar RateLimiter
    RL->>AM: Aplicar AuthMiddleware
    AM->>AM: getBearerToken()
    AM->>AM: validateToken(token)
    AM->>AM: Carregar public.key
    AM->>AM: JWT::decode(token, RS256)
    AM->>DB: SELECT user WHERE id=?
    DB-->>AM: Dados do usuário
    AM->>AM: Validar email do token
    AM->>R: Anexar user ao Request
    R->>QC: Chamar list(Request)
    QC->>QC: Acessar $request->user
    QC->>DB: SELECT queues WHERE...
    DB-->>QC: Lista de filas
    QC->>Res: success(['queues' => $queues])
    Res-->>C: {success: true, data: {queues: [...]}}
```

---

## 🔑 Arquivos de Chaves RSA

### Localização:
- **Chave Privada**: `keys/private.key` (assina tokens)
- **Chave Pública**: `keys/public.key` (valida tokens)

### Geração:
```bash
# Gerar chave privada
openssl genrsa -out keys/private.key 2048

# Extrair chave pública
openssl rsa -in keys/private.key -pubout -out keys/public.key
```

### Segurança:
- ✅ Chave privada NUNCA deve ser exposta
- ✅ Chave pública pode ser distribuída
- ✅ Algoritmo RS256 é mais seguro que HS256
- ✅ Tokens não podem ser forjados sem a chave privada

---

## 🛡️ Recursos de Segurança

1. **Criptografia Assimétrica (RS256)**
   - Chave privada assina tokens
   - Chave pública valida tokens
   - Tokens não podem ser forjados

2. **Password Hashing (Argon2id)**
   - Algoritmo resistente a GPU/ASIC
   - Salt automático
   - Custo computacional configurável

3. **Validação Dupla**
   - JWT valida estrutura e assinatura
   - Banco valida existência do usuário

4. **Rate Limiting**
   - Login: 10 requisições/minuto
   - Previne brute force

5. **Logs de Segurança**
   - Falhas de autenticação registradas
   - Inclui IP e timestamp
   - Facilita detecção de ataques

6. **Token Rotation**
   - Refresh tokens são revogados após uso
   - Previne replay attacks

7. **Expiração Curta**
   - Access tokens: 15 minutos
   - Minimiza janela de exposição

---

## 📝 Exemplo de Uso

### 1. Login
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 8,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "refresh_token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

### 2. Acessar Rota Protegida
```bash
curl -X GET "http://localhost:8080/api/v1/queues?establishment_id=1" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "queues": [
      {
        "id": 1,
        "name": "Walk-in Consultation Queue",
        "status": "open",
        "service_name": "General Consultation",
        "establishment_name": "Central Medical Clinic"
      }
    ]
  }
}
```

---

## ❌ Erros Comuns

### 1. Token não fornecido
**Requisição:** GET /api/v1/queues (sem Authorization header)

**Resposta:**
```json
{
  "success": false,
  "error": {
    "message": "Authentication required",
    "code": 401
  }
}
```

### 2. Token inválido ou expirado
**Requisição:** GET /api/v1/queues (com token inválido)

**Resposta:**
```json
{
  "success": false,
  "error": {
    "message": "Invalid or expired token",
    "code": 401
  }
}
```

### 3. Credenciais inválidas
**Requisição:** POST /api/v1/auth/login (senha incorreta)

**Resposta:**
```json
{
  "success": false,
  "error": {
    "message": "Invalid credentials",
    "code": 401
  }
}
```

---

## 🎯 Conclusão

O sistema de autenticação do QueueMaster implementa as melhores práticas de segurança:

- ✅ JWT RS256 (assinatura assimétrica)
- ✅ Argon2id password hashing
- ✅ Validação em múltiplas camadas
- ✅ Tokens de curta duração
- ✅ Rate limiting
- ✅ Logs de segurança
- ✅ Refresh token rotation

Este fluxo garante que apenas usuários autenticados com tokens válidos possam acessar rotas protegidas, mantendo a segurança e integridade do sistema.