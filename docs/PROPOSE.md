# Escopo geral — Sistema Híbrido (Fila em tempo real + Agendamento)

---

# 1. Visão de alto nível

* **Objetivo:** permitir que clientes entrem em fila em tempo real *ou* agendem horários. O sistema deve reconciliar os dois fluxos de modo que agendados tenham prioridade no horário marcado, mas que a fila em tempo real preencha buracos quando houver.
* **Stack:**

  * Backend: **PHP puro** (rodando em Apache) — RESTful API JSON.
  * Front web: **HTML + Tailwind CSS + vanilla JS** (AJAX using `fetch` / XHR; SSE/WebSocket optional).
  * Mobile: **Kotlin + Jetpack Compose**.
  * Banco: MariaDB (padrão LAMP).
* **Caracter�sticas chave:** autentica��o, roles (admin/atendente/cliente), filas m�ltiplas, agendamentos por profissional/servi�o, notifica��es persistidas com atualiza��o em tempo real (SSE/WebSocket) para painel/cliente, regras de prioridade e no-show.

---

# 2. Modelos de dados principais (tabelas sugeridas — MariaDB)

```sql
-- users
CREATE TABLE users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  email VARCHAR(150) UNIQUE,
  password_hash VARCHAR(255),
  role ENUM('client','attendant','admin') DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- establishments
CREATE TABLE establishments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  address VARCHAR(255),
  timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- services (tipo de atendimento com duração média)
CREATE TABLE services (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT,
  name VARCHAR(150),
  duration_minutes INT, -- duração padrão para agendamentos/estimativas
  FOREIGN KEY (establishment_id) REFERENCES establishments(id)
);

-- professionals (pessoas que atendem)
CREATE TABLE professionals (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT,
  name VARCHAR(150),
  FOREIGN KEY (establishment_id) REFERENCES establishments(id)
);

-- queues (uma fila lógica por estabelecimento/serviço)
CREATE TABLE queues (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT,
  service_id BIGINT NULL,
  name VARCHAR(150),
  status ENUM('open','closed') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id),
  FOREIGN KEY (service_id) REFERENCES services(id)
);

-- queue_entries (entradas walk-in)
CREATE TABLE queue_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  queue_id BIGINT,
  user_id BIGINT,
  position INT, -- calculado
  status ENUM('waiting', 'called', 'serving', 'done', 'no_show', 'cancelled'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  called_at TIMESTAMP NULL,
  served_at TIMESTAMP NULL,
  priority INT DEFAULT 0, -- 0 normal, higher for preferenciais
  FOREIGN KEY (queue_id) REFERENCES queues(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- appointments (agendamentos com horário)
CREATE TABLE appointments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  establishment_id BIGINT,
  professional_id BIGINT,
  service_id BIGINT,
  user_id BIGINT,
  start_at DATETIME,
  end_at DATETIME,
  status ENUM('booked','checked_in','in_progress','completed','no_show','cancelled'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  checkin_at TIMESTAMP NULL,
  FOREIGN KEY (establishment_id) REFERENCES establishments(id),
  FOREIGN KEY (professional_id) REFERENCES professionals(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- audit / logs / notifications (simplificado)
CREATE TABLE notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT,
  title VARCHAR(255), body TEXT,
  data JSON,
  sent_at TIMESTAMP NULL
);
```

Índices importantes: `appointments(start_at)`, `queue_entries(queue_id, status, position)`, `appointments(professional_id, start_at)` para checagem de conflitos.

---

# 3. Regras de negócio essenciais

### Prioridade entre agendamento × fila

* **Agendados** têm prioridade do intervalo `[start_at - grace_before, start_at + grace_after]` (ex.: grace_before = 10 min).
* Se um agendado fizer **check-in** (ou confirmar via app) até `start_at + grace_after`, ele é atendido na ordem prioritária — ou seja, ao chegar o horário, o sistema **chama** o cliente agendado, que poderá entrar na frente das entradas walk-in naquela posição definida.
* Se agendado não aparecer dentro do `grace_after` → marcar `no_show` e oferecer vaga para fila walk-in (ou remarcar).

### Regras de fila (walk-in)

* Ao entrar, criação de `queue_entries` com `position` = `MAX(position)+1` (atomically).
* Quando `call-next` é executado:

  * Preferir clientes agendados checados e *no-show*-não marcado cujo horário esteja devido.
  * Se não houver agendados prontos, pegar o menor `position` com status `waiting`.
* Evitar chamadas duplicadas com lock-transacional (ver seção concorrência).

### Conflitos de agendamento

* Ao criar appointment: verificar existência de intervalos sobrepostos para o mesmo profissional e serviço (check SQL `NOT EXISTS` com `start_at`/`end_at`).
* Permitir bloqueios manuais (p. ex. lunch, feriado).

### Tempo estimado para fila

* Calcular com base em `services.duration_minutes` e média histórica de `served_at - called_at` por serviço/profissional.

---

# 4. Endpoints REST principais (JSON)

> Autenticação: token JWT (recomendado) ou sessions com cookie seguro (HTTPS obrigatório)

### Auth

* `POST /api/v1/auth/register` — {name,email,password}
* `POST /api/v1/auth/login` — {email,password} → {token, user}
* `GET /api/v1/auth/me` — header: Authorization: Bearer <token>

### Estabelecimento / catálogo

* `GET /api/v1/establishments`
* `GET /api/v1/establishments/{id}`
* `GET /api/v1/establishments/{id}/services`
* `GET /api/v1/establishments/{id}/professionals`

### Filas (walk-in)

* `GET /api/v1/queues` — lista filas (query by establishment)
* `POST /api/v1/queues/{id}/join` — body: {user_id? (ou token), priority?} → cria queue_entry e retorna posição
* `GET /api/v1/queues/{id}/status` — posição do usuário + tempo estimado + total waiting
* `POST /api/v1/queues/{id}/leave` — cancela entry
* `POST /api/v1/queues/{id}/call-next` — (auth attendant) → aciona o próximo e retorna queue_entry chamado

### Agendamentos

* `POST /api/v1/appointments` — {establishment_id, professional_id, service_id, start_at} → cria com checagem de conflito
* `GET /api/v1/appointments?user_id=` — listar do usuário
* `POST /api/v1/appointments/{id}/checkin` — marca check-in (pode transformar em prioridade imediata)
* `POST /api/v1/appointments/{id}/cancel`

### Painel — atendente/admin

* `GET /api/v1/dashboard/queue?establishment_id=`
* `GET /api/v1/dashboard/appointments?establishment_id=&date=YYYY-MM-DD`
* `POST /api/v1/entries/{id}/mark-served`
* `POST /api/v1/entries/{id}/mark-no-show`

### Notificações

* `GET /api/v1/notifications` — listar notifica��es persistidas do usu�rio
* `POST /api/v1/notifications/send` — enviar notificação (interno/admin)

---

# 5. Concorrência e consistência (pontos críticos)

* **Chamar próximo** e **entrar na fila** são operações concorrentes — use transações SQL com locks apropriados:

  * Abordagem 1 (simples): `SELECT ... FOR UPDATE` na linha agregada (p. ex. tabela `queue_state`) para calcular posição/consumir próximo.
  * Abordagem 2 (fila baseada em timestamp): usar `created_at` e `status = 'waiting'` + `ORDER BY priority DESC, created_at ASC LIMIT 1 FOR UPDATE`.
* **Agendamento**: ao criar appointment, usar `SELECT ... FOR UPDATE` nas linhas do profissional e checagem `NOT EXISTS` para impedir double-booking.
* Sempre atualizar o status dentro da mesma transação (ex.: marcar chamado e gravar `called_at`).

---

# 6. Tempo real / sincronização

* Painel Web e app do cliente precisam refletir posição/estado em tempo real.
* **Opções**:

  1. **SSE (Server-Sent Events)** — simples para stream uni-direcional (API em PHP puro: endpoint que mantém conexão; bom para painel e cliente).
  2. **WebSockets** — bi-direcional (mais flexível). Em PHP puro, você pode usar um processo separado (Ratchet, Swoole) ou delegar realtime a um serviço (p.ex. Pusher / Ably).
  3. **Polling** — simples, escala mal, mas aceitável para MVP (p. ex. refresh a cada 5-10s).
* **Recomendação para MVP:** SSE ou polling. SSE traz menos infra e integra bem com PHP/Apache via endpoint que flush continuamente — porém exige worker/process para long-poll; em hosting compartilhado talvez polling seja mais seguro inicialmente.
* Para escala: usar Redis para pub/sub e um processo worker que empurra eventos aos clientes.

---

# 7. Notificações (Mobile + Web)

* **Mobile (Kotlin)**: consumir notifica��es persistidas pela API e refletir atualiza��es via SSE/polling quando aplic�vel.

  * App sincroniza notifica��es do usu�rio pela API autenticada.
  * Backend persiste eventos como: chamado, reminder de agendamento e mudan�as de status.
* **Web**: notificação por Web Push (opcional) + in-app toast quando SSE/WebSocket notificar.
* Mensagens padrões: `Você é o próximo`, `Faltam X atendimentos`, `Seu agendamento começa em 10 minutos`.

---

# 8. UX / Telas essenciais

### Mobile (cliente)

* Splash / Auth (login / cadastro)
* Tela principal: lista de estabelecimentos → serviços
* Tela serviço: escolher fila (entrar) ou agendar
* Tela fila: posição, tempo estimado, botão sair
* Tela agendamento: calendário + horas disponíveis
* Perfil: meus agendamentos, histórico
* Notificações

### Web (painel do estabelecimento)

* Login/roles
* Dashboard: hoje — filas + agendamentos
* Tela de fila em tempo real: lista waiting + botão "chamar próximo" / "pular" / "marcar atendido"
* Agenda por profissional (calendário simples)
* Configurações: tempo médio, grace window, mensagens automáticas

---

# 9. MVP (escopo reduzido e mínimo necessário)

**MVP objetivo:** permitir operação prática sem complexidade extra.

**MVP inclui:**

* Autenticação básica (JWT).
* CRUD de establishments, services, professionals (admin web).
* Entrar em fila (walk-in) + visualizar posição.
* Criar agendamento (apenas por serviço e profissional).
* Painel web básico para chamar próximo e ver agendados do dia.
* Notificações via polling + in-app (push opcional).
* Regras mínimas de prioridade (agendado no intervalo tem prioridade).
* Persistência em MariaDB + endpoints REST documentados.

**Critérios de aceite MVP:**

* Cliente consegue entrar e receber posição correta.
* Atendente chama e marca atendido sem conflito (duas chamadas não devem atribuir mesmo cliente).
* Cliente que agendou consegue fazer check-in e ser priorizado.

---

# 10. Features avançadas (para evolução)

* Evoluir a experi�ncia de notifica��es in-app/browser com templates consistentes.
* Estimativa de tempo automática por ML simples / média ponderada.
* Painel TV (display) via WebSocket/SSE.
* Filas multicanal com QR code para entrar sem app.
* Integração com Google Calendar / iCal.
* Relatórios e métricas por dia, profissional e serviço.
* SLA / espera máxima (alerta se tempo médio ultrapassar).
* Multi-tenant com planos (se virar produto).

---

# 11. Segurança & operações

* **HTTPS obrigatório** (SSL/TSL no Apache).
* **Hash de senha:** use `password_hash()` (bcrypt) do PHP.
* **JWT:** expirations curtos + refresh tokens armazenados com segurança.
* **CORS:** configurar origem do front web.
* **Rate limiting:** endpoints sensíveis (`/auth`, `/join`) para evitar abuso.
* **Input validation & prepared statements** (PDO) para evitar SQL injection.
* **Sincroniza��o de notifica��es** consistente entre dispositivos autenticados.
* **Logs e auditoria:** registrar ações críticas (chamar, cancelar, marcar no-show).

---

# 12. Mobile specifics (Kotlin + Jetpack Compose)

* Persistir JWT em `EncryptedSharedPreferences`.
* Use WorkManager para sincronizar notifica��es n�o lidas e enviar check-ins em background quando necess�rio.
* Implementar telas reativas (StateFlow / ViewModel) para refletir SSE/polling.
* Lidar com offline: permitir mostrar “último estado” e tentar reconectar.

---

# 13. Deploy / Infra (simples)

* **Servidor Apache + PHP-FPM** (ou mod_php) + MariaDB.
* Estrutura: `public/` como document root com index.php (API router).
* Use Composer para dependências (mesmo com PHP “puro” é útil).
* Ambiente: configurar `.env` (não commitar).
* Backups do banco e rotinas de migração (migrations simples com SQL scripts).

---

# 14. Testes e QA

* Unit tests (PHPUnit) para lógica de fila e agendamento.
* Teste de concorrência: scripts que simulam `join` e `call-next` concorrentes (verificar race conditions).
* Testes E2E (manual) mobile ↔ web (caminhos críticos: entrar fila, agendar, check-in, chamada).
* Logs de produção com correlação request_id para debug.

---

# 15. Backlog sugerido (itens por prioridade — do MVP para cima)

1. Infra básica + autenticação + modelos (users, establishment, services).
2. Implementar filas walk-in: join, leave, call-next (transacional).
3. Painel web simples para atendente (chamar, marcar atendido).
4. Implementar agendamentos com verificação de conflito.
5. Integrar prioridades (agendado vs walk-in).
6. Implementar tela mobile básica + login, entrar na fila, criar agendamento.
7. Notificações in-app via SSE / polling.
8. Melhorias na experi�ncia de notifica��es mobile/web.
9. Métricas e relatórios.
10. Recursos avançados (QR, integração calendário, estimativas).

---

# 16. Exemplos de payloads rápidos

**Entrar na fila**

```
POST /api/v1/queues/12/join
Authorization: Bearer <token>
Body: { "user_id": 45, "priority": 0 }
Response: { "entry_id": 987, "position": 8, "estimated_wait_minutes": 22 }
```

**Criar agendamento**

```
POST /api/v1/appointments
Body: {
  "establishment_id": 3,
  "professional_id": 10,
  "service_id": 5,
  "start_at": "2025-12-01T14:00:00"
}
Response: { "appointment_id": 123, "status": "booked" }
```


