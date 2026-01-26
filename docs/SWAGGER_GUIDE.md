# 📚 Swagger / OpenAPI - Guia de Uso

## Visão Geral

O QueueMaster API agora possui documentação interativa completa usando **Swagger UI** com especificação **OpenAPI 3.0**.

## 🌐 Acessando a Documentação

### URLs de Acesso

| URL | Descrição |
|-----|-----------|
| `http://localhost/swagger/` | Swagger UI (XAMPP) |
| `http://localhost:8080/swagger/` | Swagger UI (PHP Dev Server) |
| `http://localhost/api/docs` | Redirect para Swagger |
| `http://localhost/docs` | Shortcut para Swagger |
| `http://localhost/api/openapi.yaml` | Especificação OpenAPI (YAML) |
| `http://localhost/api/openapi.json` | Especificação OpenAPI (JSON)* |

> *Requer extensão YAML do PHP para conversão automática

---

## 🚀 Iniciando o Servidor

### Opção 1: XAMPP (Recomendado para Windows)

1. Certifique-se que o Apache está rodando no XAMPP
2. Acesse: `http://localhost/swagger/`

### Opção 2: PHP Built-in Server

```bash
# Na pasta do projeto
cd public
php -S localhost:8080
```

Acesse: `http://localhost:8080/swagger/`

### Opção 3: Docker

```bash
docker-compose up -d
```

Acesse: `http://localhost/swagger/`

---

## 🔐 Autenticação no Swagger

### Passo 1: Registrar ou Fazer Login

1. Expanda a seção **Auth**
2. Use `POST /auth/register` ou `POST /auth/login`
3. Clique em **Try it out**
4. Preencha os dados e clique em **Execute**
5. Copie o `access_token` da resposta

### Passo 2: Autorizar

1. Clique no botão **Authorize** 🔒 (canto superior direito)
2. Cole o token no formato: `{seu_access_token}`
3. Clique em **Authorize**
4. Feche o modal

Agora todas as requisições incluirão o header `Authorization: Bearer {token}`.

---

## 📋 Recursos Disponíveis

### Autenticação
- `POST /auth/register` - Registrar usuário
- `POST /auth/login` - Login
- `POST /auth/refresh` - Renovar token
- `GET /auth/me` - Perfil atual
- `POST /auth/logout` - Logout

### Usuários
- `GET /users` - Listar (admin)
- `POST /users` - Criar (admin)
- `GET /users/{id}` - Obter
- `PUT /users/{id}` - Atualizar
- `DELETE /users/{id}` - Deletar (admin)

### Estabelecimentos
- `GET /establishments` - Listar
- `POST /establishments` - Criar (admin)
- `GET /establishments/{id}` - Obter
- `PUT /establishments/{id}` - Atualizar (admin)
- `DELETE /establishments/{id}` - Deletar (admin)

### Serviços
- `GET /services` - Listar
- `POST /services` - Criar (admin)
- `GET /services/{id}` - Obter
- `PUT /services/{id}` - Atualizar (admin)
- `DELETE /services/{id}` - Deletar (admin)

### Profissionais
- `GET /professionals` - Listar
- `POST /professionals` - Criar (admin)
- `GET /professionals/{id}` - Obter
- `PUT /professionals/{id}` - Atualizar (admin)
- `DELETE /professionals/{id}` - Deletar (admin)

### Filas
- `GET /queues` - Listar
- `POST /queues` - Criar (admin)
- `GET /queues/{id}` - Obter
- `PUT /queues/{id}` - Atualizar (admin)
- `DELETE /queues/{id}` - Deletar (admin)
- `GET /queues/{id}/status` - Status da fila
- `POST /queues/{id}/join` - Entrar na fila
- `POST /queues/{id}/leave` - Sair da fila
- `POST /queues/{id}/call-next` - Chamar próximo (atendente/admin)

### Agendamentos
- `GET /appointments` - Listar meus agendamentos
- `POST /appointments` - Criar agendamento
- `GET /appointments/{id}` - Obter
- `PUT /appointments/{id}` - Atualizar
- `DELETE /appointments/{id}` - Cancelar
- `GET /appointments/available-slots` - Horários disponíveis
- `POST /appointments/{id}/checkin` - Check-in
- `POST /appointments/{id}/complete` - Concluir (atendente/admin)
- `POST /appointments/{id}/no-show` - No-show (atendente/admin)

### Dashboard
- `GET /dashboard/queue-overview` - Visão geral das filas
- `GET /dashboard/appointments-list` - Agendamentos do dia

### Notificações
- `GET /notifications` - Listar
- `GET /notifications/{id}` - Obter
- `POST /notifications/{id}/read` - Marcar como lida
- `DELETE /notifications/{id}` - Deletar

### Streams (SSE)
- `GET /streams/queue/{id}` - Stream da fila
- `GET /streams/appointments` - Stream de agendamentos
- `GET /streams/notifications` - Stream de notificações

---

## 🛠️ Funcionalidades do Swagger UI

### Filtro de Endpoints
Use a barra de busca para filtrar endpoints por nome ou descrição.

### Try it out
Clique em **Try it out** em qualquer endpoint para testar diretamente.

### Exemplos de Request
Cada endpoint inclui exemplos de request body quando aplicável.

### Código de Resposta
Veja todos os possíveis códigos de resposta e seus schemas.

### Download OpenAPI
Clique em **Download OpenAPI** para baixar a especificação.

### Seletor de Servidor
Use o dropdown no header para alternar entre servidores:
- Local (XAMPP)
- PHP Dev Server
- Production

### Modo Escuro
Clique no ícone 🌙 para alternar entre modo claro e escuro.

---

## 📦 Integrações

### Postman

1. Acesse `http://localhost/api/openapi.yaml`
2. No Postman: **Import** → **Link** → Cole a URL
3. Clique em **Import**

### Insomnia

1. **Application** → **Preferences** → **Data**
2. **Import Data** → **From URL**
3. Cole: `http://localhost/api/openapi.yaml`

### VS Code (REST Client Extension)

Instale a extensão **OpenAPI (Swagger) Editor** para visualizar o arquivo `openapi.yaml`.

### Geração de SDK

Use ferramentas como **OpenAPI Generator** para gerar SDKs:

```bash
# Instalar OpenAPI Generator
npm install @openapitools/openapi-generator-cli -g

# Gerar SDK JavaScript
openapi-generator-cli generate -i http://localhost/api/openapi.yaml -g javascript -o ./sdk/js

# Gerar SDK PHP
openapi-generator-cli generate -i http://localhost/api/openapi.yaml -g php -o ./sdk/php

# Gerar SDK Python
openapi-generator-cli generate -i http://localhost/api/openapi.yaml -g python -o ./sdk/python
```

---

## 🔧 Personalização

### Alterando o Logo/Título

Edite `public/swagger/index.html`:

```html
<div class="header-title">
    <h1>Seu Título</h1>
    <p>Sua Descrição</p>
</div>
```

### Adicionando Novos Endpoints

Edite `public/swagger/openapi.yaml` e adicione os novos paths e schemas.

### Alterando Cores

Edite as variáveis CSS em `public/swagger/index.html`:

```css
:root {
    --primary-color: #3b82f6;
    --secondary-color: #1e40af;
    --background-color: #f8fafc;
    --text-color: #1e293b;
}
```

---

## 🐛 Troubleshooting

### Swagger UI não carrega

1. Verifique se os arquivos existem em `public/swagger/`
2. Verifique permissões dos arquivos
3. Verifique se o Apache/PHP está rodando

### CORS Error

O `.htaccess` na pasta swagger já configura CORS. Se ainda houver problemas:

```apache
Header set Access-Control-Allow-Origin "*"
```

### YAML não é parseado

A conversão para JSON requer a extensão `yaml` do PHP:

```bash
# Ubuntu/Debian
sudo apt-get install php-yaml

# Windows (XAMPP)
# Baixe a DLL de https://pecl.php.net/package/yaml
# Adicione no php.ini: extension=yaml
```

### Token expira rapidamente

O access token expira em 15 minutos por segurança. Use o endpoint `/auth/refresh` para renovar.

---

## 📈 Métricas e Monitoramento

### Rate Limiting

| Endpoint | Limite |
|----------|--------|
| Global | 100 req/min |
| Login | 10 req/min |
| Registro | 5 req/min |
| Agendamentos | 20 req/min |

### Headers de Resposta

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1706054400
```

---

## 🚀 Deploy em Produção

### 1. Atualizar URLs do Servidor

Edite `public/swagger/openapi.yaml`:

```yaml
servers:
  - url: https://api.seudominio.com/api/v1
    description: Production Server
```

### 2. Proteger Swagger (Opcional)

Para proteger o Swagger em produção, adicione autenticação básica:

```apache
# public/swagger/.htaccess
AuthType Basic
AuthName "API Documentation"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

### 3. HTTPS

Certifique-se de usar HTTPS em produção:

```yaml
servers:
  - url: https://api.seudominio.com/api/v1
```

---

## 📞 Suporte

- **Documentação**: `/swagger/`
- **Status da API**: `GET /api/v1/status`
- **Health Check**: `GET /health`

---

*Documentação gerada com Swagger UI v5.11.0 e OpenAPI 3.0.3*
