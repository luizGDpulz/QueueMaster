# 🚀 Guia Rápido - Models e CRUD de Usuários

## 📚 Índice Rápido
1. [Models Disponíveis](#models-disponíveis)
2. [Como Usar Models](#como-usar-models)
3. [CRUD de Usuários](#crud-de-usuários)
4. [Exemplos Práticos](#exemplos-práticos)

---

## Models Disponíveis

### 🗂️ Lista Completa de Models

| Model | Tabela | Responsabilidade |
|-------|--------|-----------------|
| `User` | users | Gerenciamento de usuários e autenticação |
| `Establishment` | establishments | Estabelecimentos/empresas |
| `Service` | services | Serviços oferecidos |
| `Professional` | professionals | Profissionais/atendentes |
| `Queue` | queues | Filas de atendimento |
| `QueueEntry` | queue_entries | Entradas nas filas |
| `Appointment` | appointments | Agendamentos |
| `Notification` | notifications | Notificações do sistema |
| `RefreshToken` | refresh_tokens | Tokens de autenticação JWT |

---

## Como Usar Models

### 1️⃣ Importar o Model
```php
use QueueMaster\Models\User;
use QueueMaster\Models\Establishment;
```

### 2️⃣ Operações Básicas

#### Buscar por ID
```php
$user = User::find(1);
// Retorna: array com dados do usuário ou null
```

#### Listar Todos
```php
$users = User::all();
// Retorna: array de usuários

// Com filtros
$admins = User::all(['role' => 'admin'], 'name', 'ASC');
```

#### Criar
```php
$userId = User::create([
    'name' => 'Maria Silva',
    'email' => 'maria@email.com',
    'password_hash' => password_hash('senha123', PASSWORD_BCRYPT),
    'role' => 'client'
]);
```

#### Atualizar
```php
User::update(1, [
    'name' => 'Maria Silva Santos'
]);
```

#### Deletar
```php
User::delete(1);
```

### 3️⃣ Métodos Especiais

#### User
```php
// Buscar por email
$user = User::findByEmail('usuario@email.com');

// Trocar senha
User::changePassword(1, 'nova_senha');

// Verificar senha
$isValid = User::verifyPassword(1, 'senha_teste');

// Dados seguros (sem password_hash)
$safeUser = User::getSafeData($user);

// Relacionamentos
$appointments = User::getAppointments(1);
$queueEntries = User::getQueueEntries(1);
$notifications = User::getNotifications(1);
```

#### Establishment
```php
// Relacionamentos
$services = Establishment::getServices(1);
$professionals = Establishment::getProfessionals(1);
$queues = Establishment::getQueues(1);
$appointments = Establishment::getAppointments(1, '2026-01-21');
```

#### Queue
```php
// Entradas aguardando
$waiting = Queue::getWaitingEntries(1);

// Todas as entradas
$entries = Queue::getEntries(1);

// Filas por estabelecimento
$queues = Queue::getByEstablishment(1, 'open');
```

#### Appointment
```php
// Verificar conflitos
$hasConflict = Appointment::hasConflict(
    $professionalId,
    '2026-01-21 10:00:00',
    '2026-01-21 11:00:00'
);

// Agendamentos por usuário
$appointments = Appointment::getByUser(1, 'booked');

// Agendamentos por profissional
$appointments = Appointment::getByProfessional(1, '2026-01-21');
```

#### Notification
```php
// Marcar como lida
Notification::markAsRead(1);

// Marcar todas como lidas
Notification::markAllAsReadForUser(1);

// Contar não lidas
$count = Notification::getUnreadCount(1);

// Notificações não lidas
$unread = Notification::getByUser(1, true);
```

#### RefreshToken
```php
// Verificar validade
$isValid = RefreshToken::isValid($tokenHash);

// Revogar token
RefreshToken::revoke($tokenHash);

// Revogar todos os tokens do usuário
RefreshToken::revokeAllForUser(1);

// Limpar tokens expirados
RefreshToken::cleanupExpired();
```

---

## CRUD de Usuários

### 🔐 Permissões

| Operação | Cliente | Atendente | Admin |
|----------|---------|-----------|-------|
| Listar todos | ❌ | ❌ | ✅ |
| Ver próprio perfil | ✅ | ✅ | ✅ |
| Ver perfil de outros | ❌ | ❌ | ✅ |
| Criar usuário | ❌ | ❌ | ✅ |
| Atualizar próprio | ✅ | ✅ | ✅ |
| Atualizar outros | ❌ | ❌ | ✅ |
| Deletar usuário | ❌ | ❌ | ✅ |

### 📡 Endpoints de Usuários

#### 1. Listar Usuários (Admin)
```http
GET /api/v1/users?role=client&page=1&per_page=20
Authorization: Bearer {access_token}
```

**Response:**
```json
{
  "users": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "total_pages": 3
  }
}
```

#### 2. Ver Usuário Específico
```http
GET /api/v1/users/1
Authorization: Bearer {access_token}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@email.com",
    "role": "client",
    "created_at": "2026-01-21 10:00:00"
  }
}
```

#### 3. Criar Usuário (Admin)
```http
POST /api/v1/users
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "Maria Santos",
  "email": "maria@email.com",
  "password": "senha_segura_123",
  "role": "attendant"
}
```

#### 4. Atualizar Usuário
```http
PUT /api/v1/users/1
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "João Silva Santos",
  "email": "joao.novo@email.com"
}
```

**Para trocar senha (usuário próprio):**
```json
{
  "current_password": "senha_antiga",
  "password": "senha_nova_123"
}
```

**Para trocar role (Admin apenas):**
```json
{
  "role": "attendant"
}
```

#### 5. Deletar Usuário (Admin)
```http
DELETE /api/v1/users/1
Authorization: Bearer {access_token}
```

⚠️ **Não é possível deletar o próprio usuário**

#### 6. Ver Filas do Usuário
```http
GET /api/v1/users/1/queue-entries?status=waiting
Authorization: Bearer {access_token}
```

#### 7. Ver Agendamentos do Usuário
```http
GET /api/v1/users/1/appointments?status=booked
Authorization: Bearer {access_token}
```

---

## Exemplos Práticos

### Exemplo 1: Criar Usuário no Controller
```php
public function createUser(Request $request): void
{
    $data = $request->all();
    
    // Validar
    $errors = Validator::make($data, [
        'name' => 'required|min:2',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
    ]);
    
    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }
    
    try {
        // Criar usando Model
        $userId = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'client'
        ]);
        
        // Buscar criado
        $user = User::find($userId);
        $user = User::getSafeData($user); // Remove password_hash
        
        Response::created(['user' => $user]);
    } catch (\Exception $e) {
        Response::serverError('Failed to create user');
    }
}
```

### Exemplo 2: Atualizar com Validação
```php
public function updateEstablishment(Request $request, int $id): void
{
    // Verificar se existe
    $establishment = Establishment::find($id);
    if (!$establishment) {
        Response::notFound('Establishment not found');
        return;
    }
    
    $data = $request->all();
    
    // Validar e atualizar
    if (isset($data['name'])) {
        Establishment::update($id, ['name' => $data['name']]);
    }
    
    $updated = Establishment::find($id);
    Response::success(['establishment' => $updated]);
}
```

### Exemplo 3: Relacionamentos
```php
public function getUserDashboard(Request $request, int $userId): void
{
    $user = User::find($userId);
    if (!$user) {
        Response::notFound('User not found');
        return;
    }
    
    // Pegar dados relacionados
    $appointments = User::getAppointments($userId, 'booked');
    $queueEntries = User::getQueueEntries($userId, 'waiting');
    $unreadCount = Notification::getUnreadCount($userId);
    
    Response::success([
        'user' => User::getSafeData($user),
        'appointments' => $appointments,
        'queue_entries' => $queueEntries,
        'unread_notifications' => $unreadCount
    ]);
}
```

### Exemplo 4: Validação Custom
```php
// No Model
public static function validate(array $data): array
{
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    return $errors;
}

// Usando no Controller
$errors = User::validate($data);
if (!empty($errors)) {
    Response::validationError($errors);
    return;
}
```

---

## 🧪 Testando no Postman

### 1. Fazer Login (obter token)
```http
POST http://localhost/api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "senha123"
}
```

Copie o `access_token` da resposta.

### 2. Listar Usuários
```http
GET http://localhost/api/v1/users
Authorization: Bearer {cole_o_token_aqui}
```

### 3. Criar Usuário
```http
POST http://localhost/api/v1/users
Authorization: Bearer {seu_token}
Content-Type: application/json

{
  "name": "Novo Usuário",
  "email": "novo@email.com",
  "password": "senha123",
  "role": "client"
}
```

### 4. Atualizar Usuário
```http
PUT http://localhost/api/v1/users/5
Authorization: Bearer {seu_token}
Content-Type: application/json

{
  "name": "Nome Atualizado"
}
```

---

## 🎯 Dicas Importantes

1. **Sempre validar antes de criar/atualizar:**
   ```php
   $errors = Model::validate($data);
   if (!empty($errors)) { /* handle */ }
   ```

2. **Usar getSafeData para usuários:**
   ```php
   $user = User::getSafeData($user); // Remove password_hash
   ```

3. **Verificar existência antes de atualizar/deletar:**
   ```php
   $record = Model::find($id);
   if (!$record) { Response::notFound(); return; }
   ```

4. **Usar relacionamentos ao invés de queries:**
   ```php
   // ✅ Bom
   $services = Establishment::getServices($id);
   
   // ❌ Evitar
   $db->query("SELECT * FROM services WHERE establishment_id = ?", [$id]);
   ```

5. **Tratar exceções:**
   ```php
   try {
       Model::create($data);
   } catch (\InvalidArgumentException $e) {
       // Erro de validação
   } catch (\Exception $e) {
       // Erro genérico
   }
   ```

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Consulte os Models em `src/Models/`
2. Veja exemplos nos Controllers em `src/Controllers/`
3. Revise a documentação completa em `docs/ARCHITECTURE_REFACTORING.md`

---

**Última atualização:** 21/01/2026
