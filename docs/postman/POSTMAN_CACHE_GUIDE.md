# Guia: Problemas de Cache no Postman

## 🔍 Problema Identificado

O Postman do VS Code (e versão desktop) às vezes **mantém respostas antigas em cache**, fazendo parecer que a API não está funcionando corretamente.

### Exemplo do Problema:
1. Você troca a senha de `password123` para `password1234`
2. Faz a requisição novamente com a senha antiga
3. **Deveria dar erro "senha incorreta"**, mas parece que funciona
4. Na verdade, o Postman está mostrando a **resposta antiga em cache**

---

## ✅ Solução Implementada na API

### 1. **Invalidação de Tokens ao Trocar Senha**

Quando um usuário troca a senha, agora a API automaticamente:
- ✅ Revoga **todos os refresh tokens** ativos do usuário
- ✅ Força o usuário a fazer **login novamente**
- ✅ Previne que tokens antigos continuem funcionando

**Arquivo modificado:** `src/Models/User.php`
```php
public static function changePassword(int $id, string $newPassword): int
{
    // ... hash da senha ...
    
    $result = $qb->select(self::$table)
        ->where(self::$primaryKey, '=', $id)
        ->update(['password_hash' => $passwordHash]);

    // Revoga todos os tokens para forçar novo login
    if ($result > 0) {
        RefreshToken::revokeAllForUser($id);
    }

    return $result;
}
```

---

## 🛠️ Soluções para Cache no Postman

### Método 1: Desabilitar Cache (Recomendado)

**No Postman Desktop:**
1. Settings → General
2. Desmarque "Enable SSL certificate verification"
3. Ative "Send anonymous usage data to Postman"

**No VS Code Postman:**
1. Ctrl+, (Settings)
2. Procure por "Postman"
3. Configure cache settings

### Método 2: Adicionar Headers Anti-Cache

Adicione estes headers em **TODAS as requisições**:
```
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
```

### Método 3: Limpar Dados do Postman

**Desktop:**
1. View → Developer → Show DevTools
2. Application → Clear storage
3. Clear all

**VS Code:**
1. Feche o VS Code
2. Delete cache: `%AppData%\Code\User\workspaceStorage\`

### Método 4: Adicionar Query String Aleatória

Adicione parâmetro único em cada requisição:
```
{{base_url}}/auth/login?_={{$timestamp}}
```

---

## 🧪 Como Testar se Funciona

### Teste 1: Trocar Senha
1. Faça login e salve o token
2. Troque a senha via PUT `/users/{id}` com:
   ```json
   {
     "current_password": "password123",
     "password": "password1234"
   }
   ```
3. Tente fazer login com a senha **ANTIGA** (`password123`)
4. **Deve retornar erro 401**: "Invalid credentials"

### Teste 2: Token Invalidado
1. Faça login e copie o `refresh_token`
2. Troque a senha
3. Tente usar o `refresh_token` antigo em `/auth/refresh`
4. **Deve retornar erro 401**: "Invalid or expired refresh token"

---

## 📋 Checklist de Segurança

Quando implementar operações sensíveis, sempre considere:

- [x] **Trocar senha** → Revogar todos os tokens
- [ ] **Trocar email** → Enviar confirmação (futuro)
- [ ] **Detectar login suspeito** → Notificar usuário (futuro)
- [ ] **2FA habilitado** → Revogar tokens ao desabilitar (futuro)
- [ ] **Deletar conta** → Revogar todos os tokens

---

## 🔐 Impacto de Segurança

### Antes da Correção ❌
- Usuário troca senha
- Tokens antigos continuam funcionando
- **Risco:** Se alguém roubou o token, ele continua válido mesmo após troca de senha

### Depois da Correção ✅
- Usuário troca senha
- Todos os refresh tokens são revogados
- Access tokens expiram em 15 minutos
- **Resultado:** Máximo de 15 minutos de exposição após troca de senha

---

## 📱 Testando no Browser (Alternativa ao Postman)

Se o cache do Postman estiver muito problemático, use:

### Opção 1: cURL
```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

### Opção 2: Thunder Client (VS Code Extension)
- Mais leve que Postman
- Menos problemas com cache
- Install: `Ctrl+P` → `ext install rangav.vscode-thunder-client`

### Opção 3: REST Client (VS Code Extension)
Crie arquivo `.http`:
```http
### Login
POST http://localhost/api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password123"
}
```

---

## 💡 Dicas Finais

1. **Sempre verifique os logs** em `public/logs/` para confirmar o que a API realmente retornou
2. **Use o Console do Postman** (View → Show Postman Console) para ver requisições/respostas reais
3. **Timestamps nos logs** confirmam se é resposta nova ou antiga
4. **Network Inspector do VS Code** mostra requisições HTTP reais

---

## 🐛 Reportando Problemas

Se ainda tiver problemas de cache:
1. Abra Postman Console
2. Copie a requisição/resposta completa
3. Compare com os logs em `public/logs/app.log`
4. Verifique timestamp para confirmar se é cache

**Horário esperado:**
- Requisição no Postman: `14:30:15`
- Log da API: `14:30:15`
- **Se diferente = CACHE!**
