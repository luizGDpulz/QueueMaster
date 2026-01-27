# 📚 QueueMaster API - Documentação Completa

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Autenticação](#autenticação)
3. [Estrutura de Resposta](#estrutura-de-resposta)
4. [Códigos de Status HTTP](#códigos-de-status-http)
5. [Rate Limiting](#rate-limiting)
6. [Endpoints da API](#endpoints-da-api)
   - [Authentication](#authentication)
   - [Users](#users)
   - [Establishments](#establishments)
   - [Services](#services)
   - [Professionals](#professionals)
   - [Queues](#queues)
   - [Appointments](#appointments)
   - [Notifications](#notifications)
   - [Dashboard](#dashboard)
   - [Server-Sent Events (SSE)](#server-sent-events-sse)
7. [Modelos de Dados](#modelos-de-dados)
8. [Códigos de Erro](#códigos-de-erro)
9. [Exemplos de Uso](#exemplos-de-uso)

---

## Visão Geral

**Base URL:** `http://localhost/api/v1`

**Protocolo:** HTTP/HTTPS

**Formato:** JSON

**Charset:** UTF-8

**Versão Atual:** 1.0.0

### Recursos Principais
- ✅ Autenticação JWT (RS256)
- ✅ Refresh Tokens rotativos
- ✅ CRUD completo para todos os recursos
- ✅ Sistema de filas em tempo real
- ✅ Agendamento de consultas com detecção de conflitos
- ✅ Notificações push
- ✅ SSE para atualizações em tempo real
- ✅ Rate limiting por endpoint
- ✅ Logging completo de requisições
- ✅ Validação de entrada robusta

---

## Autenticação

### Tipos de Autenticação

#### 1. Bearer Token (JWT)

A maioria dos endpoints requer autenticação via JWT no header:

```http
Authorization: Bearer <access_token>
```

#### 2. Endpoints Públicos

Alguns endpoints não requerem autenticação:
- `GET /establishments`
- `GET /establishments/{id}`
- `GET /establishments/{id}/services`
- `GET /establishments/{id}/professionals`
- `GET /services`
- `GET /services/{id}`
- `GET /professionals`
- `GET /professionals/{id}`
- `GET /queues`
- `GET /queues/{id}`
- `GET /status`
- `GET /health`

### Fluxo de Autenticação

1. **Registro:** `POST /auth/register`
2. **Login:** `POST /auth/login` → Retorna `access_token` e `refresh_token`
3. **Uso:** Inclua `access_token` no header `Authorization: Bearer <token>`
4. **Refresh:** Quando `access_token` expirar, use `POST /auth/refresh` com `refresh_token`
5. **Logout:** `POST /auth/logout` → Revoga todos os tokens

### Tempo de Expiração

- **Access Token:** 15 minutos
- **Refresh Token:** 30 dias

### Roles (Permissões)

| Role | Descrição | Permissões |
|------|-----------|-----------|
| **client** | Cliente/Paciente | Criar agendamentos, entrar em filas, ver próprio perfil |
| **attendant** | Atendente | Chamar próximo na fila, marcar agendamentos como completos, dashboard |
| **admin** | Administrador | CRUD completo de todos os recursos, gerenciamento de usuários |

---

## Estrutura de Resposta

### Resposta de Sucesso

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "request_id": "uuid-v4",
    "timestamp": "2026-01-21T10:30:00Z"
  }
}
```

### Resposta de Erro

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Descrição do erro",
    "details": { ... }
  },
  "meta": {
    "request_id": "uuid-v4",
    "timestamp": "2026-01-21T10:30:00Z"
  }
}
```

### Resposta com Paginação

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 150,
    "page": 1,
    "per_page": 20,
    "total_pages": 8
  },
  "meta": {
    "request_id": "uuid-v4"
  }
}
```

---

## Códigos de Status HTTP

| Código | Significado |
|--------|-------------|
| 200 | OK - Sucesso |
| 201 | Created - Recurso criado com sucesso |
| 400 | Bad Request - Requisição inválida |
| 401 | Unauthorized - Autenticação necessária |
| 403 | Forbidden - Sem permissão |
| 404 | Not Found - Recurso não encontrado |
| 409 | Conflict - Conflito (ex: horário já agendado) |
| 422 | Unprocessable Entity - Validação falhou |
| 429 | Too Many Requests - Rate limit excedido |
| 500 | Internal Server Error - Erro do servidor |

---

## Rate Limiting

### Limites por Endpoint

| Endpoint | Limite | Janela |
|----------|--------|--------|
| **Global** | 100 req | 60s |
| `/auth/register` | 5 req | 60s |
| `/auth/login` | 10 req | 60s |
| `/auth/refresh` | 20 req | 60s |
| `/queues/{id}/join` | 10 req | 60s |
| `/appointments` (POST) | 20 req | 60s |

### Resposta ao Exceder Limite

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again later."
  }
}
```

---

## Endpoints da API

## Authentication

### POST /auth/register
Registrar novo usuário.

**Autenticação:** ❌ Não requerida

**Rate Limit:** 5 req/min

**Request Body:**
```json
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha123456",
  "role": "client"
}
```

**Validação:**
- `name`: obrigatório, 2-150 caracteres
- `email`: obrigatório, formato válido, único
- `password`: obrigatório, mínimo 8 caracteres
- `role`: obrigatório, valores: `client`, `attendant`, `admin`

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "client"
    },
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

---

### POST /auth/login
Fazer login.

**Autenticação:** ❌ Não requerida

**Rate Limit:** 10 req/min

**Request Body:**
```json
{
  "email": "joao@example.com",
  "password": "senha123456"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "client"
    },
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

---

### POST /auth/refresh
Renovar access token.

**Autenticação:** ❌ Não requerida (usa refresh_token)

**Rate Limit:** 20 req/min

**Request Body:**
```json
{
  "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "access_token": "novo_token...",
    "refresh_token": "novo_refresh...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

---

### GET /auth/me
Obter perfil do usuário autenticado.

**Autenticação:** ✅ Requerida

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "client",
      "created_at": "2026-01-21 10:00:00"
    }
  }
}
```

---

### POST /auth/logout
Fazer logout (revoga tokens).

**Autenticação:** ✅ Requerida

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Logged out successfully"
  }
}
```

---

## Users

### GET /users
Listar todos os usuários (paginado).

**Autenticação:** ✅ Requerida (Admin only)

**Query Parameters:**
- `page` (int, opcional): Número da página (default: 1)
- `per_page` (int, opcional): Itens por página (default: 20, max: 100)
- `role` (string, opcional): Filtrar por role (`client`, `attendant`, `admin`)

**Exemplo:** `GET /users?page=1&per_page=20&role=client`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "client",
      "created_at": "2026-01-21 10:00:00"
    }
  ],
  "pagination": {
    "total": 150,
    "page": 1,
    "per_page": 20,
    "total_pages": 8
  }
}
```

---

### GET /users/{id}
Obter usuário específico.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "client",
      "created_at": "2026-01-21 10:00:00"
    }
  }
}
```

---

### POST /users
Criar novo usuário.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Maria Santos",
  "email": "maria@example.com",
  "password": "senha123456",
  "role": "attendant"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 2,
      "name": "Maria Santos",
      "email": "maria@example.com",
      "role": "attendant",
      "created_at": "2026-01-21 11:00:00"
    },
    "message": "User created successfully"
  }
}
```

---

### PUT /users/{id}
Atualizar usuário.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Request Body:**
```json
{
  "name": "João Silva Santos",
  "email": "joao.novo@example.com",
  "current_password": "senha_antiga",
  "password": "nova_senha_123"
}
```

**Regras:**
- Usuários podem atualizar próprio perfil
- Admins podem atualizar qualquer usuário
- Para trocar senha, `current_password` é obrigatório
- Apenas admins podem alterar `role`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": { ... },
    "message": "User updated successfully"
  }
}
```

---

### DELETE /users/{id}
Deletar usuário.

**Autenticação:** ✅ Requerida (Admin only)

**Restrições:**
- Admin não pode deletar a si mesmo

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "User deleted successfully"
  }
}
```

---

### GET /users/{id}/queue-entries
Obter entradas de fila do usuário.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Query Parameters:**
- `status` (string, opcional): Filtrar por status

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "queue_id": 1,
      "position": 5,
      "status": "waiting",
      "created_at": "2026-01-21 10:00:00"
    }
  ],
  "total": 1
}
```

---

### GET /users/{id}/appointments
Obter agendamentos do usuário.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Query Parameters:**
- `status` (string, opcional): Filtrar por status

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "professional_id": 1,
      "service_id": 1,
      "start_at": "2026-01-25 14:00:00",
      "status": "booked"
    }
  ],
  "total": 1
}
```

---

## Establishments

### GET /establishments
Listar todos os estabelecimentos.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "establishments": [
      {
        "id": 1,
        "name": "Clínica São Paulo",
        "address": "Av. Paulista, 1000",
        "timezone": "America/Sao_Paulo",
        "created_at": "2026-01-15 09:00:00"
      }
    ],
    "total": 1
  }
}
```

---

### GET /establishments/{id}
Obter estabelecimento específico.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "establishment": {
      "id": 1,
      "name": "Clínica São Paulo",
      "address": "Av. Paulista, 1000",
      "timezone": "America/Sao_Paulo",
      "created_at": "2026-01-15 09:00:00"
    }
  }
}
```

---

### POST /establishments
Criar estabelecimento.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Clínica Rio de Janeiro",
  "address": "Rua das Flores, 500",
  "timezone": "America/Sao_Paulo"
}
```

**Validação:**
- `name`: obrigatório, 2-255 caracteres
- `address`: opcional, max 255 caracteres
- `timezone`: opcional, default "America/Sao_Paulo"

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "establishment": { ... },
    "message": "Establishment created successfully"
  }
}
```

---

### PUT /establishments/{id}
Atualizar estabelecimento.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Clínica São Paulo - Unidade Central",
  "address": "Av. Paulista, 1000 - Sala 500"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "establishment": { ... },
    "message": "Establishment updated successfully"
  }
}
```

---

### DELETE /establishments/{id}
Deletar estabelecimento.

**Autenticação:** ✅ Requerida (Admin only)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Establishment deleted successfully"
  }
}
```

---

### GET /establishments/{id}/services
Listar serviços do estabelecimento.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "services": [
      {
        "id": 1,
        "name": "Consulta Geral",
        "duration": 30,
        "description": "Consulta médica geral"
      }
    ],
    "total": 1
  }
}
```

---

### GET /establishments/{id}/professionals
Listar profissionais do estabelecimento.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "professionals": [
      {
        "id": 1,
        "name": "Dr. Carlos Silva",
        "specialization": "Clínico Geral"
      }
    ],
    "total": 1
  }
}
```

---

## Services

### GET /services
Listar todos os serviços.

**Autenticação:** ❌ Não requerida (público)

**Query Parameters:**
- `establishment_id` (int, opcional): Filtrar por estabelecimento

**Exemplo:** `GET /services?establishment_id=1`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "services": [
      {
        "id": 1,
        "establishment_id": 1,
        "establishment_name": "Clínica São Paulo",
        "name": "Consulta Geral",
        "description": "Consulta médica geral",
        "duration": 30,
        "created_at": "2026-01-15 09:00:00"
      }
    ],
    "total": 1
  }
}
```

---

### GET /services/{id}
Obter serviço específico.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "service": {
      "id": 1,
      "establishment_id": 1,
      "name": "Consulta Geral",
      "description": "Consulta médica geral",
      "duration": 30,
      "establishment": {
        "id": 1,
        "name": "Clínica São Paulo"
      }
    }
  }
}
```

---

### POST /services
Criar serviço.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "establishment_id": 1,
  "name": "Consulta Especializada",
  "description": "Consulta com especialista",
  "duration": 60
}
```

**Validação:**
- `establishment_id`: obrigatório, inteiro, deve existir
- `name`: obrigatório, 2-150 caracteres
- `description`: opcional, max 500 caracteres
- `duration`: obrigatório, inteiro, 5-480 minutos

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "service": { ... },
    "message": "Service created successfully"
  }
}
```

---

### PUT /services/{id}
Atualizar serviço.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Consulta Especializada Premium",
  "duration": 90
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "service": { ... },
    "message": "Service updated successfully"
  }
}
```

---

### DELETE /services/{id}
Deletar serviço.

**Autenticação:** ✅ Requerida (Admin only)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Service deleted successfully"
  }
}
```

---

## Professionals

### GET /professionals
Listar todos os profissionais.

**Autenticação:** ❌ Não requerida (público)

**Query Parameters:**
- `establishment_id` (int, opcional): Filtrar por estabelecimento

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "professionals": [
      {
        "id": 1,
        "establishment_id": 1,
        "establishment_name": "Clínica São Paulo",
        "name": "Dr. Carlos Silva",
        "specialization": "Clínico Geral",
        "created_at": "2026-01-15 09:00:00"
      }
    ],
    "total": 1
  }
}
```

---

### GET /professionals/{id}
Obter profissional específico.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "professional": {
      "id": 1,
      "establishment_id": 1,
      "name": "Dr. Carlos Silva",
      "specialization": "Clínico Geral",
      "establishment": {
        "id": 1,
        "name": "Clínica São Paulo"
      }
    }
  }
}
```

---

### POST /professionals
Criar profissional.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "establishment_id": 1,
  "name": "Dra. Ana Paula",
  "specialization": "Cardiologia"
}
```

**Validação:**
- `establishment_id`: obrigatório, inteiro, deve existir
- `name`: obrigatório, 2-150 caracteres
- `specialization`: opcional, max 100 caracteres

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "professional": { ... },
    "message": "Professional created successfully"
  }
}
```

---

### PUT /professionals/{id}
Atualizar profissional.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Dra. Ana Paula Santos",
  "specialization": "Cardiologia Clínica"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "professional": { ... },
    "message": "Professional updated successfully"
  }
}
```

---

### DELETE /professionals/{id}
Deletar profissional.

**Autenticação:** ✅ Requerida (Admin only)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Professional deleted successfully"
  }
}
```

---

### GET /professionals/{id}/appointments
Obter agendamentos do profissional.

**Autenticação:** ❌ Não requerida (público)

**Query Parameters:**
- `status` (string, opcional): Filtrar por status
- `date` (string, opcional): Filtrar por data (Y-m-d)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointments": [
      {
        "id": 1,
        "user_id": 1,
        "service_id": 1,
        "start_at": "2026-01-25 14:00:00",
        "status": "booked"
      }
    ],
    "total": 1
  }
}
```

---

## Queues

### GET /queues
Listar filas.

**Autenticação:** ❌ Não requerida (público)

**Query Parameters:**
- `establishment_id` (int, opcional): Filtrar por estabelecimento
- `status` (string, opcional): Filtrar por status (`open`, `closed`)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "queues": [
      {
        "id": 1,
        "establishment_id": 1,
        "establishment_name": "Clínica São Paulo",
        "service_id": 1,
        "service_name": "Consulta Geral",
        "name": "Fila Geral",
        "status": "open",
        "waiting_count": 5,
        "created_at": "2026-01-21 08:00:00"
      }
    ],
    "total": 1
  }
}
```

---

### GET /queues/{id}
Obter fila específica.

**Autenticação:** ❌ Não requerida (público)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "queue": {
      "id": 1,
      "establishment_id": 1,
      "establishment_name": "Clínica São Paulo",
      "service_id": 1,
      "service_name": "Consulta Geral",
      "name": "Fila Geral",
      "status": "open",
      "waiting_count": 5
    }
  }
}
```

---

### POST /queues
Criar fila.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "establishment_id": 1,
  "service_id": 1,
  "name": "Fila Prioritária",
  "status": "open"
}
```

**Validação:**
- `establishment_id`: obrigatório, inteiro, deve existir
- `service_id`: opcional, inteiro
- `name`: obrigatório, 2-150 caracteres
- `status`: obrigatório, valores: `open`, `closed`

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "queue": { ... },
    "message": "Queue created successfully"
  }
}
```

---

### PUT /queues/{id}
Atualizar fila.

**Autenticação:** ✅ Requerida (Admin only)

**Request Body:**
```json
{
  "name": "Fila VIP",
  "status": "closed"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Queue updated successfully"
  }
}
```

---

### DELETE /queues/{id}
Deletar fila.

**Autenticação:** ✅ Requerida (Admin only)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Queue deleted successfully"
  }
}
```

---

### POST /queues/{id}/join
Entrar na fila.

**Autenticação:** ✅ Requerida

**Rate Limit:** 10 req/min

**Request Body:**
```json
{
  "priority": 0
}
```

**Validação:**
- `priority`: opcional, inteiro, default 0 (normal), 1 (prioritário)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "entry": {
      "id": 1,
      "queue_id": 1,
      "user_id": 1,
      "position": 6,
      "status": "waiting",
      "priority": 0,
      "joined_at": "2026-01-21 10:30:00"
    },
    "message": "Successfully joined queue"
  }
}
```

---

### GET /queues/{id}/status
Obter status da fila e posição do usuário.

**Autenticação:** ❌ Não requerida (se autenticado, mostra posição do usuário)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "queue": {
      "id": 1,
      "name": "Fila Geral",
      "status": "open"
    },
    "statistics": {
      "total_waiting": 5,
      "total_being_served": 2,
      "total_completed_today": 10,
      "average_wait_time_minutes": 15
    },
    "user_entry": {
      "id": 1,
      "position": 3,
      "status": "waiting",
      "estimated_wait_minutes": 45
    }
  }
}
```

---

### POST /queues/{id}/leave
Sair da fila.

**Autenticação:** ✅ Requerida

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Successfully left queue"
  }
}
```

---

### POST /queues/{id}/call-next
Chamar próximo da fila.

**Autenticação:** ✅ Requerida (Attendant/Admin only)

**Request Body:**
```json
{
  "establishment_id": 1,
  "professional_id": 1
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Successfully called next",
    "type": "queue",
    "called": {
      "entry_id": 1,
      "user_id": 5,
      "user_name": "João Silva",
      "position": 1
    }
  }
}
```

---

## Appointments

### GET /appointments
Listar agendamentos.

**Autenticação:** ✅ Requerida

**Query Parameters:**
- `page` (int, opcional): Número da página
- `per_page` (int, opcional): Itens por página
- `user_id` (int, opcional): Filtrar por usuário (admin/attendant only)
- `professional_id` (int, opcional): Filtrar por profissional
- `establishment_id` (int, opcional): Filtrar por estabelecimento
- `status` (string, opcional): Filtrar por status
- `date` (string, opcional): Filtrar por data (Y-m-d)

**Status possíveis:**
- `booked`: Agendado
- `checked_in`: Check-in feito
- `in_progress`: Em andamento
- `completed`: Concluído
- `no_show`: Não compareceu
- `cancelled`: Cancelado

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "professional_id": 1,
      "service_id": 1,
      "establishment_id": 1,
      "start_at": "2026-01-25 14:00:00",
      "end_at": "2026-01-25 14:30:00",
      "status": "booked",
      "created_at": "2026-01-21 10:00:00"
    }
  ],
  "pagination": {
    "total": 10,
    "page": 1,
    "per_page": 20,
    "total_pages": 1
  }
}
```

---

### GET /appointments/{id}
Obter agendamento específico.

**Autenticação:** ✅ Requerida (próprio usuário ou admin/attendant)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointment": {
      "id": 1,
      "user_id": 1,
      "professional_id": 1,
      "service_id": 1,
      "establishment_id": 1,
      "start_at": "2026-01-25 14:00:00",
      "end_at": "2026-01-25 14:30:00",
      "status": "booked"
    }
  }
}
```

---

### POST /appointments
Criar agendamento.

**Autenticação:** ✅ Requerida

**Rate Limit:** 20 req/min

**Request Body:**
```json
{
  "establishment_id": 1,
  "professional_id": 1,
  "service_id": 1,
  "start_at": "2026-01-25 14:00:00"
}
```

**Validação:**
- `establishment_id`: obrigatório, inteiro, deve existir
- `professional_id`: obrigatório, inteiro, deve existir
- `service_id`: obrigatório, inteiro, deve existir
- `start_at`: obrigatório, formato: "Y-m-d H:i:s"

**Regras de Negócio:**
- Verifica conflito de horário com profissional
- Calcula `end_at` automaticamente baseado na duração do serviço
- Status inicial: `booked`

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "appointment": { ... },
    "message": "Appointment created successfully"
  }
}
```

**Erro de Conflito (409 Conflict):**
```json
{
  "success": false,
  "error": {
    "code": "APPOINTMENT_CONFLICT",
    "message": "Time slot is already booked"
  }
}
```

---

### PUT /appointments/{id}
Atualizar agendamento.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Request Body:**
```json
{
  "start_at": "2026-01-25 15:00:00",
  "professional_id": 2
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointment": { ... },
    "message": "Appointment updated successfully"
  }
}
```

---

### DELETE /appointments/{id}
Cancelar agendamento.

**Autenticação:** ✅ Requerida (próprio usuário ou admin)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Appointment cancelled successfully"
  }
}
```

---

### POST /appointments/{id}/checkin
Fazer check-in no agendamento.

**Autenticação:** ✅ Requerida (próprio usuário)

**Regras:**
- Check-in permitido 30 minutos antes até 15 minutos após horário agendado
- Status deve ser `booked`
- Altera status para `checked_in`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointment": { ... },
    "message": "Successfully checked-in"
  }
}
```

---

### POST /appointments/{id}/complete
Marcar agendamento como concluído.

**Autenticação:** ✅ Requerida (Attendant/Admin only)

**Regras:**
- Status deve ser `checked_in` ou `in_progress`
- Altera status para `completed`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointment": { ... },
    "message": "Appointment marked as complete"
  }
}
```

---

### POST /appointments/{id}/no-show
Marcar agendamento como não compareceu.

**Autenticação:** ✅ Requerida (Attendant/Admin only)

**Regras:**
- Status deve ser `booked`
- Altera status para `no_show`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointment": { ... },
    "message": "Appointment marked as no-show"
  }
}
```

---

### GET /appointments/available-slots
Obter horários disponíveis.

**Autenticação:** ❌ Não requerida (público)

**Query Parameters:**
- `professional_id` (int, obrigatório): ID do profissional
- `service_id` (int, obrigatório): ID do serviço
- `date` (string, obrigatório): Data no formato Y-m-d

**Exemplo:** `GET /appointments/available-slots?professional_id=1&service_id=1&date=2026-01-25`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "slots": [
      "08:00:00",
      "08:30:00",
      "09:00:00",
      "14:00:00",
      "14:30:00",
      "15:00:00"
    ],
    "total": 6,
    "date": "2026-01-25"
  }
}
```

---

## Notifications

### GET /notifications
Listar notificações do usuário.

**Autenticação:** ✅ Requerida

**Query Parameters:**
- `page` (int, opcional): Número da página
- `per_page` (int, opcional): Itens por página

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "type": "appointment_reminder",
      "title": "Lembrete de Consulta",
      "message": "Sua consulta é amanhã às 14:00",
      "data": {
        "appointment_id": 1,
        "start_at": "2026-01-25 14:00:00"
      },
      "is_read": false,
      "read_at": null,
      "created_at": "2026-01-24 10:00:00"
    }
  ],
  "pagination": {
    "total": 15,
    "page": 1,
    "per_page": 20,
    "total_pages": 1
  }
}
```

---

### GET /notifications/{id}
Obter notificação específica.

**Autenticação:** ✅ Requerida (própria notificação)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "notification": {
      "id": 1,
      "type": "appointment_reminder",
      "title": "Lembrete de Consulta",
      "message": "Sua consulta é amanhã às 14:00",
      "data": { ... },
      "is_read": false,
      "created_at": "2026-01-24 10:00:00"
    }
  }
}
```

---

### POST /notifications/{id}/read
Marcar notificação como lida.

**Autenticação:** ✅ Requerida (própria notificação)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Notification marked as read"
  }
}
```

---

### DELETE /notifications/{id}
Deletar notificação.

**Autenticação:** ✅ Requerida (própria notificação)

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "message": "Notification deleted successfully"
  }
}
```

---

## Dashboard

### GET /dashboard/queue-overview
Visão geral das filas.

**Autenticação:** ✅ Requerida (Attendant/Admin only)

**Query Parameters:**
- `establishment_id` (int, opcional): Filtrar por estabelecimento

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "queues": [
      {
        "queue_id": 1,
        "queue_name": "Fila Geral",
        "establishment": "Clínica São Paulo",
        "total_waiting": 5,
        "total_being_served": 2,
        "average_wait_time_minutes": 15
      }
    ],
    "summary": {
      "total_queues": 3,
      "total_waiting": 12,
      "total_being_served": 5,
      "total_completed_today": 45
    }
  }
}
```

---

### GET /dashboard/appointments-list
Lista de agendamentos para dashboard.

**Autenticação:** ✅ Requerida (Attendant/Admin only)

**Query Parameters:**
- `date` (string, opcional): Filtrar por data (Y-m-d)
- `professional_id` (int, opcional): Filtrar por profissional
- `establishment_id` (int, opcional): Filtrar por estabelecimento

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "appointments": [
      {
        "id": 1,
        "user_name": "João Silva",
        "professional_name": "Dr. Carlos Silva",
        "service_name": "Consulta Geral",
        "start_at": "2026-01-21 14:00:00",
        "status": "checked_in"
      }
    ],
    "statistics": {
      "total": 20,
      "booked": 10,
      "checked_in": 5,
      "completed": 4,
      "no_show": 1
    }
  }
}
```

---

## Server-Sent Events (SSE)

### GET /streams/queue/{id}
Stream de atualizações da fila.

**Autenticação:** ✅ Requerida

**Content-Type:** `text/event-stream`

**Eventos:**
- `position_update`: Atualização de posição na fila
- `called`: Usuário foi chamado
- `queue_status`: Status da fila mudou

**Exemplo:**
```
event: position_update
data: {"position": 3, "waiting_count": 8}

event: called
data: {"entry_id": 1, "message": "You have been called!"}
```

---

### GET /streams/appointments
Stream de atualizações de agendamentos do usuário.

**Autenticação:** ✅ Requerida

**Content-Type:** `text/event-stream`

**Eventos:**
- `appointment_reminder`: Lembrete de agendamento
- `appointment_updated`: Agendamento atualizado
- `appointment_cancelled`: Agendamento cancelado

---

### GET /streams/notifications
Stream de notificações do usuário.

**Autenticação:** ✅ Requerida

**Content-Type:** `text/event-stream`

**Eventos:**
- `notification`: Nova notificação recebida

---

## Modelos de Dados

### User
```json
{
  "id": 1,
  "name": "João Silva",
  "email": "joao@example.com",
  "role": "client",
  "created_at": "2026-01-21 10:00:00",
  "updated_at": "2026-01-21 10:00:00"
}
```

### Establishment
```json
{
  "id": 1,
  "name": "Clínica São Paulo",
  "address": "Av. Paulista, 1000",
  "timezone": "America/Sao_Paulo",
  "created_at": "2026-01-15 09:00:00",
  "updated_at": "2026-01-15 09:00:00"
}
```

### Service
```json
{
  "id": 1,
  "establishment_id": 1,
  "name": "Consulta Geral",
  "description": "Consulta médica geral",
  "duration": 30,
  "created_at": "2026-01-15 09:00:00",
  "updated_at": "2026-01-15 09:00:00"
}
```

### Professional
```json
{
  "id": 1,
  "establishment_id": 1,
  "name": "Dr. Carlos Silva",
  "specialization": "Clínico Geral",
  "created_at": "2026-01-15 09:00:00",
  "updated_at": "2026-01-15 09:00:00"
}
```

### Queue
```json
{
  "id": 1,
  "establishment_id": 1,
  "service_id": 1,
  "name": "Fila Geral",
  "status": "open",
  "created_at": "2026-01-21 08:00:00",
  "updated_at": "2026-01-21 08:00:00"
}
```

### QueueEntry
```json
{
  "id": 1,
  "queue_id": 1,
  "user_id": 1,
  "position": 5,
  "status": "waiting",
  "priority": 0,
  "joined_at": "2026-01-21 10:00:00",
  "called_at": null,
  "served_at": null,
  "completed_at": null,
  "cancelled_at": null
}
```

### Appointment
```json
{
  "id": 1,
  "user_id": 1,
  "professional_id": 1,
  "service_id": 1,
  "establishment_id": 1,
  "start_at": "2026-01-25 14:00:00",
  "end_at": "2026-01-25 14:30:00",
  "status": "booked",
  "created_at": "2026-01-21 10:00:00",
  "updated_at": "2026-01-21 10:00:00"
}
```

### Notification
```json
{
  "id": 1,
  "user_id": 1,
  "type": "appointment_reminder",
  "title": "Lembrete de Consulta",
  "message": "Sua consulta é amanhã às 14:00",
  "data": {},
  "read_at": null,
  "created_at": "2026-01-24 10:00:00"
}
```

---

## Códigos de Erro

### Erros de Autenticação

| Código | Mensagem | Status HTTP |
|--------|----------|-------------|
| `UNAUTHORIZED` | Authentication required | 401 |
| `INVALID_CREDENTIALS` | Invalid email or password | 401 |
| `TOKEN_EXPIRED` | Access token expired | 401 |
| `TOKEN_INVALID` | Invalid or malformed token | 401 |
| `REFRESH_TOKEN_INVALID` | Invalid refresh token | 401 |

### Erros de Validação

| Código | Mensagem | Status HTTP |
|--------|----------|-------------|
| `VALIDATION_ERROR` | Validation failed | 422 |
| `REQUIRED_FIELD` | Field is required | 422 |
| `INVALID_EMAIL` | Invalid email format | 422 |
| `EMAIL_ALREADY_EXISTS` | Email already registered | 422 |
| `PASSWORD_TOO_SHORT` | Password must be at least 8 characters | 422 |

### Erros de Permissão

| Código | Mensagem | Status HTTP |
|--------|----------|-------------|
| `FORBIDDEN` | You don't have permission | 403 |
| `ROLE_REQUIRED` | This action requires specific role | 403 |
| `CANNOT_DELETE_SELF` | Cannot delete your own account | 400 |

### Erros de Recurso

| Código | Mensagem | Status HTTP |
|--------|----------|-------------|
| `NOT_FOUND` | Resource not found | 404 |
| `ALREADY_EXISTS` | Resource already exists | 409 |
| `APPOINTMENT_CONFLICT` | Time slot already booked | 409 |

### Erros do Sistema

| Código | Mensagem | Status HTTP |
|--------|----------|-------------|
| `INTERNAL_ERROR` | Internal server error | 500 |
| `DATABASE_ERROR` | Database operation failed | 500 |
| `RATE_LIMIT_EXCEEDED` | Too many requests | 429 |

---

## Exemplos de Uso

### Exemplo 1: Fluxo Completo de Registro e Agendamento

```bash
# 1. Registrar usuário
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@example.com",
    "password": "senha123456",
    "role": "client"
  }'

# Salvar access_token da resposta

# 2. Listar estabelecimentos (público)
curl -X GET http://localhost/api/v1/establishments

# 3. Ver serviços do estabelecimento
curl -X GET http://localhost/api/v1/establishments/1/services

# 4. Ver profissionais disponíveis
curl -X GET http://localhost/api/v1/establishments/1/professionals

# 5. Ver horários disponíveis
curl -X GET "http://localhost/api/v1/appointments/available-slots?professional_id=1&service_id=1&date=2026-01-25"

# 6. Criar agendamento
curl -X POST http://localhost/api/v1/appointments \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "establishment_id": 1,
    "professional_id": 1,
    "service_id": 1,
    "start_at": "2026-01-25 14:00:00"
  }'

# 7. Fazer check-in (no dia do agendamento)
curl -X POST http://localhost/api/v1/appointments/1/checkin \
  -H "Authorization: Bearer <access_token>"
```

### Exemplo 2: Fluxo de Fila

```bash
# 1. Listar filas abertas
curl -X GET "http://localhost/api/v1/queues?status=open"

# 2. Entrar na fila (autenticado)
curl -X POST http://localhost/api/v1/queues/1/join \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{"priority": 0}'

# 3. Ver status da fila e sua posição
curl -X GET http://localhost/api/v1/queues/1/status \
  -H "Authorization: Bearer <access_token>"

# 4. Atendente chama próximo
curl -X POST http://localhost/api/v1/queues/1/call-next \
  -H "Authorization: Bearer <attendant_token>"

# 5. Sair da fila (se necessário)
curl -X POST http://localhost/api/v1/queues/1/leave \
  -H "Authorization: Bearer <access_token>"
```

### Exemplo 3: Renovar Token Expirado

```bash
# Token expirou, usar refresh token
curl -X POST http://localhost/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "<refresh_token>"
  }'

# Retorna novo access_token e refresh_token
```

---

## Notas Importantes

### Segurança

1. **Sempre use HTTPS em produção**
2. **Nunca exponha as chaves privadas** (`keys/`)
3. **Rotacione refresh tokens regularmente**
4. **Valide todos os inputs no cliente antes de enviar**
5. **Armazene tokens com segurança** (nunca em localStorage se possível)

### Boas Práticas

1. **Sempre inclua `request_id` nos logs** para rastreabilidade
2. **Trate erros 429 (rate limit)** com exponential backoff
3. **Use SSE para atualizações em tempo real** em vez de polling
4. **Implemente retry logic** para erros 5xx
5. **Cache responses públicas** (estabelecimentos, serviços)

### Performance

1. **Use paginação** sempre que listar recursos
2. **Filtre no servidor** em vez de no cliente
3. **Considere usar CDN** para arquivos estáticos
4. **Monitore logs** regularmente para identificar gargalos

---

**Documentação gerada em:** 21/01/2026  
**Versão da API:** 1.0.0  
**Autores:** QueueMaster Team

**Repositório:** [GitHub](#)  
**Support:** support@queuemaster.com
