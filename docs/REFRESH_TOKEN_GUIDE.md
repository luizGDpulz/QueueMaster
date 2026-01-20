# 🔄 Guia Completo: Refresh Token e Token Rotation

## 📌 Índice

1. [O Problema que Resolve](#o-problema-que-resolve)
2. [Como Funciona](#como-funciona)
3. [Fluxo Completo](#fluxo-completo)
4. [Implementação no Sistema](#implementação-no-sistema)
5. [Uso Prático em Aplicações](#uso-prático-em-aplicações)
6. [Exemplos de Código Frontend](#exemplos-de-código-frontend)
7. [Segurança e Boas Práticas](#segurança-e-boas-práticas)

---

## 🎯 O Problema que Resolve

### Cenário sem Refresh Token

Imagine uma aplicação web/mobile onde:

1. **Access Token com validade longa (ex: 7 dias)**
   - ❌ Se roubado, atacante tem acesso por 7 dias inteiros
   - ❌ Usuário não pode "deslogar remotamente"
   - ❌ Alto risco de segurança

2. **Access Token com validade curta (ex: 15 minutos)**
   - ✅ Menor janela de exposição
   - ❌ Usuário precisa fazer login a cada 15 minutos
   - ❌ Péssima experiência de usuário

### Solução: Access Token + Refresh Token

**Access Token (curta duração: 15 min)**
- Usado em TODAS as requisições
- Armazenado em memória (não em localStorage)
- Se roubado, expira em 15 minutos

**Refresh Token (longa duração: 30 dias)**
- Usado APENAS para renovar o access token
- Armazenado com segurança (httpOnly cookie ou secure storage)
- Se roubado, pode ser invalidado remotamente

**Resultado:**
- ✅ Segurança alta (token de acesso expira rápido)
- ✅ Boa experiência (usuário não precisa fazer login constantemente)
- ✅ Controle (pode revogar tokens remotamente)

---

## 🔧 Como Funciona

### 1. Login Inicial

```
Cliente → POST /api/v1/auth/login
         {email, password}
         
API ← Response
      {
        "access_token": "eyJ0eXAi...",      // JWT RS256 - 15 min
        "refresh_token": "a1b2c3d4...",     // Random 64 bytes - 30 dias
        "expires_in": 900
      }
```

**O que acontece:**
1. API valida credenciais
2. Gera **access_token** (JWT assinado com chave privada)
3. Gera **refresh_token** (string aleatória de 64 caracteres)
4. Armazena hash do refresh_token no banco de dados
5. Retorna ambos os tokens

### 2. Requisições Normais (enquanto access token é válido)

```
Cliente → GET /api/v1/queues
          Authorization: Bearer eyJ0eXAi... (access_token)
         
API ← Response: {queues: [...]}
```

**O que acontece:**
1. AuthMiddleware valida JWT
2. Verifica assinatura RS256
3. Verifica expiração
4. Se válido: processa requisição
5. Se expirado: retorna 401

### 3. Quando Access Token Expira

```
Cliente → GET /api/v1/queues
          Authorization: Bearer eyJ0eXAi... (expirado)
         
API ← 401 Unauthorized
      {"error": "Invalid or expired token"}

Cliente → POST /api/v1/auth/refresh
          {refresh_token: "a1b2c3d4..."}
         
API ← Response
      {
        "access_token": "eyJ1eXQi...",      // NOVO access_token
        "refresh_token": "x9y8z7w6...",     // NOVO refresh_token
        "expires_in": 900
      }

Cliente → GET /api/v1/queues (repete com novo token)
          Authorization: Bearer eyJ1eXQi... (novo)
         
API ← Response: {queues: [...]}
```

**O que acontece (Token Rotation):**
1. Cliente detecta 401 (token expirado)
2. Envia refresh_token para `/api/v1/auth/refresh`
3. API valida refresh_token no banco
4. API **REVOGA** o refresh_token antigo (rotação)
5. API gera NOVO access_token + NOVO refresh_token
6. Cliente salva novos tokens
7. Cliente repete requisição original com novo access_token

---

## 🔄 Fluxo Completo (Diagrama)

```
┌──────────────────────────────────────────────────────────┐
│                    PRIMEIRO LOGIN                         │
└──────────────────────────────────────────────────────────┘

Cliente                   API                    Database
  │                        │                         │
  ├─ POST /login ─────────>│                         │
  │  {email, password}     │                         │
  │                        ├─ Valida credenciais ───>│
  │                        │<─ User data ────────────┤
  │                        │                         │
  │                        ├─ Gera access_token      │
  │                        │   (JWT RS256)           │
  │                        │                         │
  │                        ├─ Gera refresh_token     │
  │                        │   (random 64 bytes)     │
  │                        │                         │
  │                        ├─ INSERT refresh_token ─>│
  │                        │   (hash SHA256)         │
  │                        │                         │
  │<─ {access, refresh} ───┤                         │
  │                        │                         │
  │ [Salva tokens]         │                         │
  │                        │                         │

┌──────────────────────────────────────────────────────────┐
│               REQUISIÇÕES NORMAIS (0-15 min)             │
└──────────────────────────────────────────────────────────┘

  │                        │                         │
  ├─ GET /queues ─────────>│                         │
  │  Auth: Bearer {access} │                         │
  │                        ├─ Valida JWT (RS256)     │
  │                        ├─ Verifica expiração     │
  │                        ├─ ✓ Token válido         │
  │                        │                         │
  │<─ {queues} ────────────┤                         │
  │                        │                         │
  │ ... (múltiplas requisições) ...                  │
  │                        │                         │

┌──────────────────────────────────────────────────────────┐
│           TOKEN EXPIRA (após 15 minutos)                 │
└──────────────────────────────────────────────────────────┘

  │                        │                         │
  ├─ GET /appointments ───>│                         │
  │  Auth: Bearer {access} │                         │
  │                        ├─ Valida JWT             │
  │                        ├─ ✗ Token expirado!      │
  │                        │                         │
  │<─ 401 Unauthorized ────┤                         │
  │                        │                         │
  │ [Detecta 401]          │                         │
  │                        │                         │

┌──────────────────────────────────────────────────────────┐
│                   TOKEN REFRESH                          │
└──────────────────────────────────────────────────────────┘

  │                        │                         │
  ├─ POST /auth/refresh ──>│                         │
  │  {refresh_token}       │                         │
  │                        ├─ Hash SHA256(token) ───>│
  │                        │<─ SELECT refresh_tokens ┤
  │                        │   WHERE hash = ?         │
  │                        │                         │
  │                        ├─ ✓ Token válido         │
  │                        ├─ ✓ Não revogado         │
  │                        ├─ ✓ Não expirado         │
  │                        │                         │
  │                        ├─ UPDATE revoked_at ────>│
  │                        │   (REVOGA token antigo) │
  │                        │                         │
  │                        ├─ Gera NOVO access       │
  │                        ├─ Gera NOVO refresh      │
  │                        │                         │
  │                        ├─ INSERT novo refresh ──>│
  │                        │                         │
  │<─ {access, refresh} ───┤                         │
  │                        │                         │
  │ [Atualiza tokens]      │                         │
  │                        │                         │

┌──────────────────────────────────────────────────────────┐
│              REPETE REQUISIÇÃO ORIGINAL                  │
└──────────────────────────────────────────────────────────┘

  │                        │                         │
  ├─ GET /appointments ───>│                         │
  │  Auth: Bearer {novo}   │                         │
  │                        ├─ Valida JWT             │
  │                        ├─ ✓ Token válido         │
  │                        │                         │
  │<─ {appointments} ──────┤                         │
  │                        │                         │
```

---

## 💻 Implementação no Sistema

### Estrutura do Banco de Dados

```sql
CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,      -- SHA256 hash
    expires_at DATETIME NOT NULL,         -- 30 dias no futuro
    revoked_at DATETIME NULL,             -- NULL = ativo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Código Backend (PHP)

**Gerar Refresh Token:**
```php
public static function generateRefreshToken(int $userId): string
{
    // 1. Gerar token aleatório (64 caracteres)
    $token = bin2hex(random_bytes(32));
    
    // 2. Hash SHA256 para armazenar no banco
    $tokenHash = hash('sha256', $token);
    
    // 3. Expiração: 30 dias
    $expiresAt = date('Y-m-d H:i:s', time() + 2592000);
    
    // 4. Salvar no banco
    INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
    VALUES ($userId, $tokenHash, $expiresAt);
    
    // 5. Retornar token em texto plano (cliente precisa dele)
    return $token;
}
```

**Validar e Rotacionar Refresh Token:**
```php
public static function validateAndRotateRefreshToken(string $token): ?array
{
    // 1. Hash do token recebido
    $tokenHash = hash('sha256', $token);
    
    // 2. Buscar no banco
    SELECT * FROM refresh_tokens 
    WHERE token_hash = ? AND revoked_at IS NULL
    
    // 3. Verificações
    if (!exists) return null;           // Token não existe
    if (expired) return null;           // Token expirou
    if (revoked) return null;           // Token já foi usado
    
    // 4. REVOGAR token antigo (TOKEN ROTATION)
    UPDATE refresh_tokens 
    SET revoked_at = NOW() 
    WHERE id = ?
    
    // 5. Retornar dados do usuário
    return $user;
}
```

---

## 🌐 Uso Prático em Aplicações

### Cenário Real: Aplicação Web (React/Vue/Angular)

#### 1. **Armazenamento de Tokens**

```javascript
// ❌ NUNCA FAZER: localStorage é vulnerável a XSS
localStorage.setItem('access_token', token);

// ✅ MELHOR: Memória + httpOnly cookie para refresh
class TokenManager {
    constructor() {
        this.accessToken = null;  // Em memória (perde ao recarregar)
        this.refreshToken = null; // httpOnly cookie (mais seguro)
    }
    
    setTokens(access, refresh) {
        this.accessToken = access;
        // refresh_token vai em httpOnly cookie (backend envia Set-Cookie)
    }
    
    getAccessToken() {
        return this.accessToken;
    }
}
```

#### 2. **Interceptor HTTP (Axios)**

```javascript
// api.js
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:8080/api/v1'
});

let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
    failedQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            prom.resolve(token);
        }
    });
    failedQueue = [];
};

// Interceptor de REQUEST: Adiciona token
api.interceptors.request.use(
    config => {
        const token = tokenManager.getAccessToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => Promise.reject(error)
);

// Interceptor de RESPONSE: Trata 401 e faz refresh
api.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;

        // Se erro 401 e ainda não tentou refresh
        if (error.response?.status === 401 && !originalRequest._retry) {
            
            if (isRefreshing) {
                // Já está fazendo refresh, enfileira requisição
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then(token => {
                    originalRequest.headers.Authorization = `Bearer ${token}`;
                    return api(originalRequest);
                });
            }

            originalRequest._retry = true;
            isRefreshing = true;

            try {
                // Faz refresh do token
                const response = await api.post('/auth/refresh', {
                    refresh_token: tokenManager.getRefreshToken()
                });

                const { access_token, refresh_token } = response.data.data;

                // Salva novos tokens
                tokenManager.setTokens(access_token, refresh_token);

                // Processa fila de requisições pendentes
                processQueue(null, access_token);

                // Repete requisição original com novo token
                originalRequest.headers.Authorization = `Bearer ${access_token}`;
                return api(originalRequest);

            } catch (refreshError) {
                // Refresh falhou: redireciona para login
                processQueue(refreshError, null);
                tokenManager.clearTokens();
                window.location.href = '/login';
                return Promise.reject(refreshError);
            } finally {
                isRefreshing = false;
            }
        }

        return Promise.reject(error);
    }
);

export default api;
```

#### 3. **Uso na Aplicação**

```javascript
// QueueList.vue / QueueList.jsx
import api from './api';

async function loadQueues() {
    try {
        // Faz requisição normalmente
        const response = await api.get('/queues', {
            params: { establishment_id: 1 }
        });
        
        // Se token expirou, interceptor faz refresh automaticamente
        // e repete a requisição. Você nem percebe!
        
        setQueues(response.data.data.queues);
        
    } catch (error) {
        // Só chega aqui se refresh também falhou (usuário vai pro login)
        console.error('Erro ao carregar filas:', error);
    }
}
```

### Fluxo do Usuário (Experiência)

```
Usuário                         Sistema
────────────────────────────────────────────────────────
Dia 1, 09:00
└─ Faz login
   └─> access_token (expira 09:15)
   └─> refresh_token (expira em 30 dias)

Dia 1, 09:05
└─ Navega no app
   └─> Requisições usam access_token
   └─> ✓ Tudo funciona

Dia 1, 09:16 (token expirou)
└─ Clica em "Ver filas"
   └─> GET /queues com token expirado
   └─> API retorna 401
   └─> Interceptor detecta 401
   └─> Interceptor faz POST /auth/refresh
   └─> API retorna novos tokens
   └─> Interceptor repete GET /queues
   └─> ✓ Usuário vê filas (sem perceber nada!)

Dia 15
└─ Ainda logado
   └─> Tokens foram renovados automaticamente
   └─> Usuário nunca precisou fazer login de novo

Dia 31 (refresh token expirou)
└─ Tenta acessar app
   └─> Refresh falha (token expirou)
   └─> Redirecionado para login
```

---

## 📱 Cenário Real: Aplicação Mobile (React Native)

```javascript
// tokenService.js
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';

class TokenService {
    async saveTokens(accessToken, refreshToken) {
        // Access token: AsyncStorage (não é crítico, expira rápido)
        await AsyncStorage.setItem('access_token', accessToken);
        
        // Refresh token: SecureStore (armazenamento criptografado)
        await SecureStore.setItemAsync('refresh_token', refreshToken);
    }
    
    async getAccessToken() {
        return await AsyncStorage.getItem('access_token');
    }
    
    async getRefreshToken() {
        return await SecureStore.getItemAsync('refresh_token');
    }
    
    async clearTokens() {
        await AsyncStorage.removeItem('access_token');
        await SecureStore.deleteItemAsync('refresh_token');
    }
}

export default new TokenService();
```

---

## 🔐 Segurança e Boas Práticas

### 1. **Token Rotation (implementado no QueueMaster)**

**Por que?**
- Se um refresh token for roubado, ele só pode ser usado UMA VEZ
- Após usar, o token é revogado
- Próxima tentativa de uso falha
- Sistema pode detectar uso duplicado = possível ataque

**Como funciona:**
```
Situação Normal:
1. Cliente tem refresh_token_1
2. Cliente faz refresh
3. Sistema revoga refresh_token_1
4. Sistema retorna refresh_token_2
5. Cliente usa refresh_token_2

Situação de Ataque:
1. Atacante rouba refresh_token_1
2. Cliente (legítimo) faz refresh primeiro
3. Sistema revoga refresh_token_1
4. Atacante tenta usar refresh_token_1
5. Sistema detecta token revogado
6. Sistema registra tentativa suspeita
7. Sistema pode revogar TODOS os tokens do usuário
```

### 2. **Armazenamento Seguro**

| Local              | Access Token | Refresh Token | Segurança |
|--------------------|--------------|---------------|-----------|
| localStorage       | ❌ Não       | ❌ Não        | Vulnerável a XSS |
| sessionStorage     | ⚠️ Ok        | ❌ Não        | Perde ao fechar aba |
| Memória (variável) | ✅ Melhor    | ❌ Não        | Perde ao recarregar |
| httpOnly Cookie    | ⚠️ Ok        | ✅ Melhor     | Protegido contra XSS |
| SecureStore (mobile)| ✅ Ok       | ✅ Melhor     | Criptografado no device |

**Recomendação QueueMaster:**
- **Web**: Access em memória + Refresh em httpOnly cookie
- **Mobile**: Access em AsyncStorage + Refresh em SecureStore

### 3. **Tempo de Expiração**

| Token          | Duração Recomendada | QueueMaster |
|----------------|---------------------|-------------|
| Access Token   | 5-15 minutos        | 15 minutos  |
| Refresh Token  | 7-30 dias           | 30 dias     |

**Por que access token curto?**
- Menor janela de exposição se roubado
- Força renovação periódica
- Pode ser armazenado em memória

**Por que refresh token longo?**
- Boa experiência (usuário não faz login constantemente)
- Pode ser revogado remotamente
- Armazenamento mais seguro

### 4. **Revogação de Tokens**

**Logout:**
```php
// Revoga apenas o refresh_token atual
UPDATE refresh_tokens 
SET revoked_at = NOW() 
WHERE id = ?
```

**Logout de todos os dispositivos:**
```php
// Revoga TODOS os refresh_tokens do usuário
UPDATE refresh_tokens 
SET revoked_at = NOW() 
WHERE user_id = ? AND revoked_at IS NULL
```

**Quando usar:**
- Usuário clicou em "Sair"
- Usuário clicou em "Sair de todos os dispositivos"
- Senha foi alterada
- Conta foi comprometida
- Administrador suspendeu conta

### 5. **Detecção de Ataques**

**Cenários suspeitos:**
```php
// 1. Uso de token revogado
if ($token->revoked_at !== null) {
    Logger::logSecurity('Attempted use of revoked refresh token', [
        'user_id' => $token->user_id,
        'ip' => $request->getIp(),
        'user_agent' => $request->getUserAgent()
    ]);
    
    // Considerar revogar TODOS os tokens do usuário
    TokenMiddleware::revokeAllUserTokens($token->user_id);
    
    // Notificar usuário por email
    NotificationService::sendSecurityAlert($token->user_id);
}

// 2. Múltiplos refreshes de IPs diferentes
// 3. Refresh de país diferente do registro
// 4. Padrão incomum de uso
```

### 6. **Limpeza de Tokens Expirados**

```php
// Rodar diariamente via CRON
public static function cleanupExpiredTokens(): int
{
    $sql = "DELETE FROM refresh_tokens WHERE expires_at < NOW()";
    return $db->execute($sql);
}
```

```bash
# crontab -e
0 2 * * * php /path/to/queuemaster/scripts/cleanup-tokens.php
```

---

## 📊 Comparação: Com vs Sem Refresh Token

### Sem Refresh Token (Access Token de 30 dias)

```
❌ Problemas:
- Token roubado: atacante tem 30 dias de acesso
- Logout não funciona de verdade (token continua válido)
- Não pode revogar remotamente
- Token grande em toda requisição (JWT com payload)
- Difícil detectar uso indevido

✅ Vantagens:
- Implementação mais simples
- Menos requisições ao servidor
```

### Com Refresh Token (Sistema Atual)

```
✅ Vantagens:
- Token roubado: só tem 15 minutos de acesso
- Logout efetivo (revoga refresh token)
- Pode revogar remotamente
- Token pequeno em requisições (JWT menor)
- Detecta uso indevido (token rotation)
- Melhor experiência (usuário não reloga)

❌ Trade-offs:
- Implementação mais complexa
- Requisição extra a cada 15 minutos
- Armazenamento de tokens no banco
```

**Conclusão:** Para sistemas que lidam com dados sensíveis (como filas médicas, agendamentos), o sistema com refresh token é MUITO mais seguro.

---

## 🎓 Resumo Executivo

**O que é Refresh Token?**
Token de longa duração usado APENAS para renovar o access token.

**Por que usar?**
Combina segurança (token de acesso curto) com boa experiência (usuário não reloga).

**Como funciona no QueueMaster?**
1. Login retorna access (15 min) + refresh (30 dias)
2. Requisições usam access token
3. Access expira? Usa refresh para renovar
4. Refresh token é rotacionado (revogado após uso)

**Como implementar no frontend?**
1. Salvar tokens de forma segura
2. Interceptor HTTP detecta 401
3. Interceptor chama /auth/refresh
4. Repete requisição original
5. Usuário nem percebe!

**Benefícios:**
- 🔒 Mais seguro (token expira rápido)
- 😊 Melhor UX (não precisa relogar)
- 🛡️ Controle (pode revogar remotamente)
- 🔍 Rastreável (detecta ataques)

---

## 📚 Referências

- [RFC 6749 - OAuth 2.0](https://datatracker.ietf.org/doc/html/rfc6749)
- [OWASP Token Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
- [Auth0 - Refresh Token Rotation](https://auth0.com/docs/secure/tokens/refresh-tokens/refresh-token-rotation)
