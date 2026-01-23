# 📮 Guia de Testes com Postman - QueueMaster API

## 🚀 Início Rápido

### 1. Importar Collection no Postman

1. Abra o Postman
2. Clique em **Import** (botão no canto superior esquerdo)
3. Selecione o arquivo `postman_collection_complete.json`
4. A collection será importada com todas as requisições organizadas

### 2. Configurar Variáveis de Ambiente

A collection já vem com variáveis configuradas:
- `base_url`: http://localhost/api/v1
- `access_token`: (será preenchido automaticamente após login)
- `refresh_token`: (será preenchido automaticamente após login)
- `user_id`: (será preenchido automaticamente após login)

### 3. Ajustar Base URL (se necessário)

Se sua API estiver rodando em outra porta/host:
1. Na collection, clique em **Variables**
2. Altere `base_url` para o endereço correto (ex: `http://localhost:8080/api/v1`)

---

## 📋 Estrutura da Collection

### 🔐 Authentication (5 endpoints)
- ✅ Register User
- ✅ Login
- ✅ Refresh Token
- ✅ Get Current User (Me)
- ✅ Logout

### 👥 Users - CRUD (9 endpoints)
- ✅ List Users (Admin)
- ✅ Get User by ID
- ✅ Create User (Admin)
- ✅ Update User
- ✅ Update User Password
- ✅ Update User Role (Admin)
- ✅ Delete User (Admin)
- ✅ Get User Queue Entries
- ✅ Get User Appointments

### 🏢 Establishments (7 endpoints)
- ✅ List Establishments
- ✅ Get Establishment
- ✅ Create Establishment (Admin)
- ✅ Update Establishment (Admin)
- ✅ Delete Establishment (Admin)
- ✅ Get Establishment Services
- ✅ Get Establishment Professionals

### 📋 Queues (9 endpoints)
- ✅ List Queues
- ✅ Get Queue
- ✅ Create Queue (Admin)
- ✅ Update Queue (Admin)
- ✅ Delete Queue (Admin)
- ✅ Join Queue
- ✅ Get Queue Status
- ✅ Leave Queue
- ✅ Call Next in Queue (Attendant/Admin)

### 📅 Appointments (8 endpoints)
- ✅ List Appointments
- ✅ Get Appointment
- ✅ Create Appointment
- ✅ Update Appointment
- ✅ Cancel Appointment
- ✅ Checkin Appointment
- ✅ Complete Appointment (Attendant/Admin)
- ✅ Mark No-Show (Attendant/Admin)

### 🔔 Notifications (2 endpoints)
- ✅ List Notifications
- ✅ Mark Notification as Read

### 📊 Dashboard (2 endpoints)
- ✅ Queue Overview (Attendant/Admin)
- ✅ Appointments List (Attendant/Admin)

### 🔧 System (2 endpoints)
- ✅ API Status
- ✅ Health Check

**Total: 44 endpoints prontos para teste!**

---

## 🎯 Fluxo de Teste Recomendado

### 1️⃣ Setup Inicial

#### A. Registrar Usuário Admin
```
POST /auth/register
{
  "name": "Admin Teste",
  "email": "admin@teste.com",
  "password": "senha123456",
  "role": "admin"
}
```

✅ **Resultado:** Token salvo automaticamente na variável `access_token`

#### B. Verificar Login Automático
```
GET /auth/me
```

✅ **Resultado:** Deve retornar os dados do usuário admin

---

### 2️⃣ Testar CRUD de Usuários

#### A. Criar Usuário Cliente
```
POST /users
{
  "name": "Cliente Teste",
  "email": "cliente@teste.com",
  "password": "senha123456",
  "role": "client"
}
```

#### B. Listar Usuários
```
GET /users?page=1&per_page=20
```

#### C. Atualizar Usuário
```
PUT /users/{id}
{
  "name": "Cliente Teste Atualizado"
}
```

#### D. Ver Usuário Específico
```
GET /users/{id}
```

#### E. Deletar Usuário (opcional)
```
DELETE /users/{id}
```

---

### 3️⃣ Testar Estabelecimentos

#### A. Criar Estabelecimento
```
POST /establishments
{
  "name": "Clínica Exemplo",
  "address": "Rua Teste, 123",
  "timezone": "America/Sao_Paulo"
}
```

#### B. Listar Estabelecimentos (público - sem token)
```
GET /establishments
```
⚠️ **Remover autenticação desta requisição**

#### C. Ver Estabelecimento
```
GET /establishments/1
```

---

### 4️⃣ Testar Filas

#### A. Criar Fila
```
POST /queues
{
  "establishment_id": 1,
  "name": "Fila Geral",
  "status": "open"
}
```

#### B. Entrar na Fila (como cliente)
```
POST /queues/1/join
{
  "priority": 0
}
```

#### C. Ver Status da Fila
```
GET /queues/1/status
```

#### D. Chamar Próximo (como admin/attendant)
```
POST /queues/1/call-next
```

#### E. Sair da Fila
```
POST /queues/1/leave
```

---

### 5️⃣ Testar Agendamentos

#### A. Criar Agendamento
```
POST /appointments
{
  "establishment_id": 1,
  "professional_id": 1,
  "service_id": 1,
  "start_at": "2026-01-25 10:00:00"
}
```

#### B. Listar Agendamentos
```
GET /appointments
```

#### C. Fazer Check-in
```
POST /appointments/1/checkin
```

#### D. Completar Agendamento
```
POST /appointments/1/complete
```

---

## 🔑 Scripts Automáticos

A collection possui **scripts automáticos** que facilitam os testes:

### Script de Login/Register
Após login ou registro bem-sucedido, os seguintes valores são salvos automaticamente:
- ✅ `access_token` - Token de acesso
- ✅ `refresh_token` - Token de refresh
- ✅ `user_id` - ID do usuário

### Como Funciona
```javascript
// Executado após login/register
if (pm.response.code === 200 || pm.response.code === 201) {
    const response = pm.response.json();
    pm.collectionVariables.set('access_token', response.access_token);
    pm.collectionVariables.set('refresh_token', response.refresh_token);
    pm.collectionVariables.set('user_id', response.user.id);
}
```

Você não precisa copiar e colar tokens manualmente! 🎉

---

## 🎭 Testando com Diferentes Roles

### 1. Como Admin
```json
// Registrar/Login como admin
{
  "email": "admin@teste.com",
  "role": "admin"
}
```

**Pode acessar:**
- ✅ Todos os endpoints
- ✅ CRUD de usuários
- ✅ CRUD de estabelecimentos
- ✅ CRUD de filas
- ✅ Dashboard

### 2. Como Attendant
```json
// Registrar/Login como attendant
{
  "email": "atendente@teste.com",
  "role": "attendant"
}
```

**Pode acessar:**
- ✅ Dashboard
- ✅ Chamar próximo na fila
- ✅ Completar agendamentos
- ✅ Marcar no-show
- ❌ CRUD de usuários
- ❌ CRUD de estabelecimentos

### 3. Como Client
```json
// Registrar/Login como client
{
  "email": "cliente@teste.com",
  "role": "client"
}
```

**Pode acessar:**
- ✅ Ver próprio perfil
- ✅ Atualizar próprio perfil
- ✅ Entrar/sair de filas
- ✅ Criar agendamentos
- ❌ Dashboard
- ❌ CRUD administrativo

---

## 📝 Exemplos de Payloads

### Criar Usuário Completo
```json
{
  "name": "João Silva Santos",
  "email": "joao.silva@exemplo.com",
  "password": "senha_segura_123",
  "role": "attendant"
}
```

### Atualizar Usuário com Senha
```json
{
  "name": "João Silva Santos Jr.",
  "email": "joao.novo@exemplo.com",
  "current_password": "senha_antiga",
  "password": "nova_senha_123"
}
```

### Criar Estabelecimento Completo
```json
{
  "name": "Clínica Médica São Paulo",
  "address": "Av. Paulista, 1000 - Sala 500 - Bela Vista, São Paulo/SP",
  "timezone": "America/Sao_Paulo"
}
```

### Criar Fila com Serviço
```json
{
  "establishment_id": 1,
  "service_id": 1,
  "name": "Fila de Consultas Gerais",
  "status": "open"
}
```

### Criar Agendamento
```json
{
  "establishment_id": 1,
  "professional_id": 1,
  "service_id": 1,
  "start_at": "2026-01-25 14:30:00"
}
```

---

## 🔍 Filtros Disponíveis

### Usuários
```
GET /users?role=client&page=1&per_page=20
```
- `role`: client|attendant|admin
- `page`: Número da página
- `per_page`: Registros por página (máx: 100)

### Filas
```
GET /queues?establishment_id=1&status=open
```
- `establishment_id`: ID do estabelecimento
- `status`: open|closed

### Agendamentos
```
GET /appointments?user_id=1&status=booked&date=2026-01-21
```
- `user_id`: ID do usuário
- `status`: booked|checked_in|in_progress|completed|no_show|cancelled
- `date`: Data no formato Y-m-d

### Entradas de Fila
```
GET /users/1/queue-entries?status=waiting
```
- `status`: waiting|called|serving|done|no_show|cancelled

---

## ⚠️ Dicas Importantes

### 1. Ordem de Execução
Para testes completos, execute nesta ordem:
1. ✅ Register/Login → Salva token
2. ✅ Create Establishment → Cria estabelecimento
3. ✅ Create Queue → Cria fila
4. ✅ Join Queue → Entra na fila
5. ✅ Create Appointment → Cria agendamento

### 2. Requisições Públicas
Algumas requisições **não precisam** de autenticação:
- GET /establishments
- GET /establishments/{id}
- GET /establishments/{id}/services
- GET /establishments/{id}/professionals
- GET /queues
- GET /queues/{id}
- GET /status
- GET /health

Para testar sem token, **desabilite a autenticação** na requisição.

### 3. Limpar Tokens
Para testar com novo usuário:
1. Execute **Logout**
2. Execute **Login** com outro usuário
3. O token será atualizado automaticamente

### 4. Renovar Token Expirado
Se o token expirar:
```
POST /auth/refresh
{
  "refresh_token": "{{refresh_token}}"
}
```
O novo token será salvo automaticamente.

---

## 🐛 Troubleshooting

### Erro 401 Unauthorized
✅ **Solução:** Execute Login novamente para obter novo token

### Erro 403 Forbidden
✅ **Solução:** Verifique se o usuário tem permissão (role correto)

### Erro 404 Not Found
✅ **Solução:** Verifique se o ID existe no banco de dados

### Erro 422 Validation Error
✅ **Solução:** Verifique os campos obrigatórios no payload

### Token não está sendo salvo
✅ **Solução:** 
1. Verifique a aba **Tests** da requisição Login/Register
2. Execute a requisição e verifique o Console do Postman
3. Verifique se a resposta tem `access_token`

---

## 📊 Monitoramento

### Ver Variáveis Atuais
1. Clique na collection
2. Vá em **Variables**
3. Veja os valores de `access_token`, `user_id`, etc.

### Console do Postman
Para debug, abra o Console (View → Show Postman Console):
- Ver requisições enviadas
- Ver respostas completas
- Ver scripts executados
- Ver erros de autenticação

---

## 🎓 Casos de Teste Sugeridos

### Teste 1: Fluxo Completo de Usuário Cliente
1. ✅ Register como client
2. ✅ Get /auth/me
3. ✅ List establishments
4. ✅ Join queue
5. ✅ Create appointment
6. ✅ List notifications

### Teste 2: Fluxo Completo de Admin
1. ✅ Login como admin
2. ✅ Create user (attendant)
3. ✅ Create establishment
4. ✅ Create queue
5. ✅ List users
6. ✅ Update user role
7. ✅ Delete user

### Teste 3: Fluxo de Fila
1. ✅ Create queue (admin)
2. ✅ Join queue (client)
3. ✅ Get queue status
4. ✅ Call next (attendant)
5. ✅ Leave queue

### Teste 4: Validações
1. ❌ Create user sem email → 422
2. ❌ Login senha incorreta → 401
3. ❌ Delete próprio usuário → 400
4. ❌ Client acessar /users → 403

---

## 📞 Suporte

**Documentação Completa:**
- `docs/ARCHITECTURE_REFACTORING.md`
- `docs/QUICK_GUIDE_MODELS.md`

**Collection:** `postman_collection_complete.json`

**Total de Endpoints Prontos:** 44 ✅

---

**Última atualização:** 21/01/2026  
**Versão da API:** 1.0.0
