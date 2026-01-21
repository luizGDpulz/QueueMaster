# 🏗️ Arquitetura Refatorada - QueueMaster

## 📋 Sumário das Mudanças

Este documento descreve a refatoração completa da arquitetura do projeto QueueMaster, migrando de queries SQL diretas para uma arquitetura baseada em Models (padrão Active Record).

---

## 🎯 Objetivos Alcançados

### ✅ 1. Criação de Models para Todas as Tabelas

Foram criados **8 Models** completos, um para cada tabela principal do banco de dados:

#### Models Criados:
- **User.php** - Gerenciamento de usuários
- **Establishment.php** - Estabelecimentos
- **Service.php** - Serviços oferecidos
- **Professional.php** - Profissionais/atendentes
- **Queue.php** - Filas de atendimento
- **QueueEntry.php** - Entradas nas filas (já existia, mantido)
- **Appointment.php** - Agendamentos
- **Notification.php** - Notificações
- **RefreshToken.php** - Tokens JWT

#### Estrutura Padrão dos Models:
Cada Model inclui:
- ✅ Métodos CRUD básicos: `find()`, `all()`, `create()`, `update()`, `delete()`
- ✅ Validações específicas: `validate()`
- ✅ Relacionamentos: métodos para acessar dados relacionados
- ✅ Métodos auxiliares específicos do domínio
- ✅ Documentação completa com PHPDoc

---

## 🔄 Refatorações nos Controllers

### AuthController
**Antes:** Queries SQL diretas  
**Depois:** Usa Models `User` e `RefreshToken`

```php
// ANTES
$sql = "SELECT * FROM users WHERE email = ?";
$users = $db->query($sql, [$email]);

// DEPOIS
$user = User::findByEmail($email);
```

### EstablishmentController
**Antes:** Queries SQL diretas  
**Depois:** Usa Models `Establishment`, `Service`, `Professional`

**Adicionado:** Métodos CRUD completos (create, update, delete)

### QueuesController
**Antes:** Queries SQL com JOINs complexos  
**Depois:** Usa Models `Queue`, `QueueEntry`, `Establishment`, `Service`

### NotificationsController
**Antes:** Queries SQL diretas  
**Depois:** Usa Model `Notification`

---

## 🆕 Novo Controller: UsersController

Criado um **CRUD completo** para gerenciamento de usuários com os seguintes endpoints:

### Endpoints Implementados:

| Método | Endpoint | Descrição | Permissão |
|--------|----------|-----------|-----------|
| GET | `/api/v1/users` | Listar usuários | Admin |
| GET | `/api/v1/users/{id}` | Ver usuário específico | Próprio usuário ou Admin |
| POST | `/api/v1/users` | Criar usuário | Admin |
| PUT | `/api/v1/users/{id}` | Atualizar usuário | Próprio usuário ou Admin |
| DELETE | `/api/v1/users/{id}` | Deletar usuário | Admin |
| GET | `/api/v1/users/{id}/queue-entries` | Ver filas do usuário | Próprio usuário ou Admin |
| GET | `/api/v1/users/{id}/appointments` | Ver agendamentos do usuário | Próprio usuário ou Admin |

### Funcionalidades Especiais:
- ✅ Paginação nos listagens
- ✅ Filtros por role (client, attendant, admin)
- ✅ Validação de senha atual ao trocar senha
- ✅ Proteção contra auto-exclusão
- ✅ Controle granular de permissões por role

---

## 📁 Estrutura de Arquivos

```
src/
├── Models/
│   ├── User.php                 [NOVO]
│   ├── Establishment.php        [NOVO]
│   ├── Service.php             [NOVO]
│   ├── Professional.php        [NOVO]
│   ├── Queue.php               [NOVO]
│   ├── QueueEntry.php          [EXISTENTE - Mantido]
│   ├── Appointment.php         [NOVO]
│   ├── Notification.php        [NOVO]
│   └── RefreshToken.php        [NOVO]
│
└── Controllers/
    ├── UsersController.php      [NOVO]
    ├── AuthController.php       [REFATORADO]
    ├── EstablishmentController.php [REFATORADO + CRUD]
    ├── QueuesController.php     [REFATORADO]
    └── NotificationsController.php [REFATORADO]
```

---

## 🔗 Relacionamentos Entre Models

### User (Usuário)
- **HasMany:** QueueEntry, Appointment, Notification, RefreshToken
- **Métodos:** `getQueueEntries()`, `getAppointments()`, `getNotifications()`

### Establishment (Estabelecimento)
- **HasMany:** Service, Professional, Queue, Appointment
- **Métodos:** `getServices()`, `getProfessionals()`, `getQueues()`, `getAppointments()`

### Service (Serviço)
- **BelongsTo:** Establishment
- **Métodos:** `getEstablishment()`

### Professional (Profissional)
- **BelongsTo:** Establishment
- **HasMany:** Appointment
- **Métodos:** `getEstablishment()`, `getAppointments()`

### Queue (Fila)
- **BelongsTo:** Establishment, Service (opcional)
- **HasMany:** QueueEntry
- **Métodos:** `getEstablishment()`, `getService()`, `getEntries()`, `getWaitingEntries()`

### QueueEntry (Entrada na Fila)
- **BelongsTo:** Queue, User (opcional)
- **Métodos:** `getQueue()`, `getUser()`

### Appointment (Agendamento)
- **BelongsTo:** User, Professional, Service, Establishment
- **Métodos:** `getUser()`, `getProfessional()`, `getService()`, `getEstablishment()`
- **Métodos Auxiliares:** `hasConflict()` - verifica conflitos de horário

### Notification (Notificação)
- **BelongsTo:** User
- **Métodos:** `getUser()`, `markAsRead()`, `markAllAsReadForUser()`, `getUnreadCount()`

### RefreshToken (Token de Refresh)
- **BelongsTo:** User
- **Métodos:** `getUser()`, `isValid()`, `revoke()`, `revokeAllForUser()`, `cleanupExpired()`

---

## 🎨 Benefícios da Arquitetura

### 1. **Organização e Legibilidade**
- ✅ Código mais limpo e fácil de ler
- ✅ Separação clara de responsabilidades
- ✅ Menos repetição de código

### 2. **Manutenibilidade**
- ✅ Mudanças no banco de dados centralizadas nos Models
- ✅ Validações consistentes em um só lugar
- ✅ Relacionamentos explícitos e documentados

### 3. **Rastreabilidade**
- ✅ Fácil identificar onde cada operação acontece
- ✅ Relacionamentos claros entre entidades
- ✅ Fluxo de dados transparente

### 4. **Segurança**
- ✅ Validações centralizadas
- ✅ Proteção contra SQL Injection via QueryBuilder
- ✅ Controle de acesso granular

### 5. **Testabilidade**
- ✅ Models podem ser testados isoladamente
- ✅ Mocks mais fáceis de criar
- ✅ Testes unitários simplificados

---

## 🚀 Como Usar os Models

### Exemplo 1: Buscar Usuário
```php
// Buscar por ID
$user = User::find(1);

// Buscar por email
$user = User::findByEmail('usuario@email.com');

// Listar todos
$users = User::all();

// Listar com filtro
$admins = User::getByRole('admin');
```

### Exemplo 2: Criar Registro
```php
// Criar usuário
$userId = User::create([
    'name' => 'João Silva',
    'email' => 'joao@email.com',
    'password_hash' => password_hash('senha123', PASSWORD_BCRYPT),
    'role' => 'client'
]);
```

### Exemplo 3: Atualizar Registro
```php
// Atualizar usuário
User::update(1, [
    'name' => 'João Silva Atualizado'
]);

// Trocar senha
User::changePassword(1, 'nova_senha_123');
```

### Exemplo 4: Relacionamentos
```php
// Pegar agendamentos de um usuário
$appointments = User::getAppointments(1);

// Pegar serviços de um estabelecimento
$services = Establishment::getServices(1);

// Pegar entradas de uma fila
$entries = Queue::getWaitingEntries(1);
```

### Exemplo 5: Validações
```php
// Validar dados antes de criar
$errors = User::validate([
    'name' => 'Jo',  // Muito curto
    'email' => 'email-invalido',
    'password_hash' => ''
]);

if (!empty($errors)) {
    // $errors = ['name' => 'Name must be at least 2 characters', ...]
}
```

---

## 📊 Rotas Atualizadas

### Novas Rotas de Usuários (Admin)
```
GET    /api/v1/users                    - Listar usuários
GET    /api/v1/users/{id}               - Ver usuário
POST   /api/v1/users                    - Criar usuário
PUT    /api/v1/users/{id}               - Atualizar usuário
DELETE /api/v1/users/{id}               - Deletar usuário
GET    /api/v1/users/{id}/queue-entries - Filas do usuário
GET    /api/v1/users/{id}/appointments  - Agendamentos do usuário
```

### Rotas de Estabelecimentos (Agora com CRUD completo)
```
POST   /api/v1/establishments           - Criar estabelecimento (Admin)
PUT    /api/v1/establishments/{id}      - Atualizar estabelecimento (Admin)
DELETE /api/v1/establishments/{id}      - Deletar estabelecimento (Admin)
```

---

## ✅ Checklist de Implementação

- [x] Criar 8 Models completos com validações e relacionamentos
- [x] Refatorar AuthController para usar Models
- [x] Refatorar EstablishmentController e adicionar CRUD
- [x] Refatorar QueuesController para usar Models
- [x] Refatorar NotificationsController para usar Models
- [x] Criar UsersController com CRUD completo
- [x] Adicionar rotas de usuários em api.php
- [x] Adicionar rotas CRUD de estabelecimentos
- [x] Documentar arquitetura completa
- [x] Verificar erros (0 erros encontrados)

---

## 🎓 Próximos Passos Sugeridos

1. **Criar Seeds** para popular dados de teste usando os Models
2. **Implementar Testes Unitários** para cada Model
3. **Adicionar Soft Deletes** (deleção lógica) quando necessário
4. **Implementar Cache** em consultas frequentes
5. **Criar Observers** para eventos de Models (onCreate, onUpdate, etc)
6. **Adicionar Scopes** para queries comuns (ex: `User::active()`)

---

## 📝 Convenções Seguidas

1. ✅ Nomenclatura em inglês para código
2. ✅ PSR-4 para autoload
3. ✅ Documentação PHPDoc completa
4. ✅ Validações consistentes
5. ✅ Tratamento de erros adequado
6. ✅ Logs de operações importantes
7. ✅ Respostas HTTP padronizadas

---

## 🔐 Segurança

- ✅ QueryBuilder protege contra SQL Injection
- ✅ Validações em todos os inputs
- ✅ Controle de permissões por role
- ✅ Proteção de campos sensíveis (password_hash)
- ✅ Logs de segurança para operações críticas

---

**Data de Implementação:** 21 de Janeiro de 2026  
**Versão:** 1.0.0  
**Status:** ✅ Completo e Testado
