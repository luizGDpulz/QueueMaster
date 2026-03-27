# Scope — Hybrid System (Real-time Queue + Scheduling)

## 1. High-level overview


## 2. Stack

# Scope — Hybrid System (Real-time Queue + Scheduling)

## 1. High-level overview

- Objective: allow customers to join a live (walk-in) queue or schedule appointments. The system must reconcile both flows so scheduled customers have priority at their booked time, while the live queue fills unused slots.
- Stack:

	- Backend: PHP (plain PHP, running on Apache) — RESTful JSON API.
	- Web frontend: HTML + Tailwind CSS + vanilla JavaScript (AJAX using `fetch` / XHR; SSE/WebSocket optional for realtime). AJAX will be used for standard client-server interactions and polling when needed.
	- Mobile: Kotlin + Jetpack Compose
	- Database: MariaDB (LAMP standard)

Key characteristics: authentication, roles (admin/attendant/client), multiple queues, scheduling per professional/service, persisted notifications with realtime updates (SSE/WebSocket) for panel/client, priority rules and no-show handling.

---

## 2. Main data models (suggested tables — MariaDB)

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

-- services (service type with average duration)
CREATE TABLE services (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	establishment_id BIGINT,
	name VARCHAR(150),
	duration_minutes INT, -- default duration for appointments/estimates
	FOREIGN KEY (establishment_id) REFERENCES establishments(id)
);

-- professionals (staff members)
CREATE TABLE professionals (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	establishment_id BIGINT,
	name VARCHAR(150),
	FOREIGN KEY (establishment_id) REFERENCES establishments(id)
);

-- queues (a logical queue per establishment/service)
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

-- queue_entries (walk-in entries)
CREATE TABLE queue_entries (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	queue_id BIGINT,
	user_id BIGINT,
	position INT, -- calculated
	status ENUM('waiting', 'called', 'serving', 'done', 'no_show', 'cancelled'),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	called_at TIMESTAMP NULL,
	served_at TIMESTAMP NULL,
	priority INT DEFAULT 0, -- 0 normal, higher for privileged
	FOREIGN KEY (queue_id) REFERENCES queues(id),
	FOREIGN KEY (user_id) REFERENCES users(id)
);

-- appointments (scheduled bookings)
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

-- audit / logs / notifications (simplified)
CREATE TABLE notifications (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT,
	title VARCHAR(255), body TEXT,
	data JSON,
	sent_at TIMESTAMP NULL
);
```

Important indexes: `appointments(start_at)`, `queue_entries(queue_id, status, position)`, `appointments(professional_id, start_at)` for conflict checks.

---

## 3. Essential business rules

### Priority between appointments × queue

- Appointments have priority in the interval `[start_at - grace_before, start_at + grace_after]` (e.g., `grace_before = 10 min`).
- If an appointment checks in (or confirms via app) up to `start_at + grace_after`, they are prioritized — at their scheduled time the system should `call` the booked customer and place them ahead of walk-ins at the appropriate position.
- If the booked customer does not show within `grace_after` → mark `no_show` and offer the slot to the walk-in queue (or reschedule).

### Walk-in queue rules

- On join, create a `queue_entries` row with `position = MAX(position)+1` (atomically).
- When `call-next` runs:

	- Prefer checked-in appointments that are due and not marked `no_show`.
	- If none are ready, pick the smallest `position` with `status = 'waiting'`.
- Avoid duplicate calls using transactional locks (see concurrency section).

### Appointment conflicts

- When creating an appointment: verify no overlapping intervals for the same professional and service (use `NOT EXISTS` checks with `start_at`/`end_at`).
- Support manual blocks (e.g., lunch, holidays).

### Estimated wait time

- Calculate based on `services.duration_minutes` and historical average of `served_at - called_at` per service/professional.

---

## 4. Main REST endpoints (JSON)

> Authentication: JWT recommended, or sessions with secure cookie (HTTPS required)

### Auth

- `POST /api/v1/auth/register` — {name,email,password}
- `POST /api/v1/auth/login` — {email,password} → {token, user}
- `GET /api/v1/auth/me` — header: `Authorization: Bearer <token>`

### Establishment / catalog

- `GET /api/v1/establishments`
- `GET /api/v1/establishments/{id}`
- `GET /api/v1/establishments/{id}/services`
- `GET /api/v1/establishments/{id}/professionals`

### Queues (walk-in)

- `GET /api/v1/queues` — list queues (filter by establishment)
- `POST /api/v1/queues/{id}/join` — body: `{user_id? (or token), priority?}` → creates `queue_entry` and returns position
- `GET /api/v1/queues/{id}/status` — user's position + estimated wait + total waiting
- `POST /api/v1/queues/{id}/leave` — cancel entry
- `POST /api/v1/queues/{id}/call-next` — (auth: attendant) → calls next and returns the called `queue_entry`

### Appointments

- `POST /api/v1/appointments` — `{establishment_id, professional_id, service_id, start_at}` → creates with conflict checks
- `GET /api/v1/appointments?user_id=` — list user's appointments
- `POST /api/v1/appointments/{id}/checkin` — mark check-in (can grant immediate priority)
- `POST /api/v1/appointments/{id}/cancel`

### Panel — attendant/admin

- `GET /api/v1/dashboard/queue?establishment_id=`
- `GET /api/v1/dashboard/appointments?establishment_id=&date=YYYY-MM-DD`
- `POST /api/v1/entries/{id}/mark-served`
- `POST /api/v1/entries/{id}/mark-no-show`

### Notifications

- `GET /api/v1/notifications` — list the user's persisted notifications
- `POST /api/v1/notifications/send` — send notification (internal/admin)

---

## 5. Concurrency and consistency (critical points)

- `call-next` and `join` are concurrent operations — use SQL transactions with proper locks:

	- Approach 1 (simple): `SELECT ... FOR UPDATE` on an aggregate row (e.g., a `queue_state` table) to calculate position/consume next.
	- Approach 2 (timestamp-based): use `created_at` with `status = 'waiting'` + `ORDER BY priority DESC, created_at ASC LIMIT 1 FOR UPDATE`.
- Appointment creation: use `SELECT ... FOR UPDATE` on the professional's related data and `NOT EXISTS` checks to prevent double-booking.
- Always update status inside the same transaction (e.g., mark `called` and write `called_at`).

---

## 6. Real-time / synchronization

- Panel and client should reflect position/state in near real-time.
- Options:

	1. SSE (Server-Sent Events) — simple uni-directional streaming; easy to implement in PHP with a worker/process.
	2. WebSockets — bi-directional (more flexible). In plain PHP, use a separate process (Ratchet, Swoole) or a managed realtime provider.
	3. Polling (AJAX) — simple and reliable for MVP (e.g., refresh every 5–10s) and recommended if hosting restricts long-lived connections.

Recommendation for MVP: implement AJAX polling (via `fetch`/XHR) or SSE depending on hosting constraints; for scale add Redis pub/sub and a worker to push events.

---

## 7. Notifications (Mobile + Web)

- Mobile (Kotlin): consume persisted notifications through the API and reflect updates via SSE/polling when applicable.

	- App syncs the user's notifications through the authenticated API.
	- Backend persists events such as called, appointment reminders, and status changes.
- Web: Web Push (optional) + in-app toasts when SSE/WebSocket notify.
- Message examples: `You're up next`, `X customers ahead`, `Your appointment starts in 10 minutes`.

---

## 8. UX / Essential screens

- Mobile (client)

- Splash / Auth (login / register)
- Main screen: establishments → services
- Service screen: choose queue (join) or schedule
- Queue screen: position, estimated time, leave button
- Appointment screen: calendar + available slots
- Profile: my appointments, history
- Notifications

- Web (establishment panel)

- Login/roles
- Dashboard: today — queues + appointments
- Live queue view: waiting list + buttons `call next` / `skip` / `mark served`
- Schedule by professional (simple calendar)
- Settings: avg duration, grace window, auto messages

---

## 9. MVP (reduced scope)

Objective: enable practical operation with minimal complexity.

Includes:

- Basic authentication (JWT).
- CRUD for establishments, services, professionals (admin web).
- Join walk-in queue + view position (AJAX interactions).
- Create appointment (service + professional).
- Basic attendant panel to call next and view today's appointments.
- Notifications via polling + in-app (push optional).
- Minimal priority rules (appointments in window prioritized).
- Persistence in MariaDB + documented REST endpoints.

Acceptance criteria:

- Client can join and receive correct position.
- Attendant calls and marks served without conflicts (no double-assign).
- Booked client can check-in and be prioritized.

---

## 10. Advanced features (future)

- Evolve the in-app/browser notification experience with consistent templates.
- Automated wait-time estimation via simple ML / weighted average.
- TV panel (display) via WebSocket/SSE.
- Multi-channel queue entry with QR code.
- Google Calendar / iCal integration.
- Reports and metrics per day/professional/service.
- SLA / maximum wait alerts.
- Multi-tenant plans (if productized).

---

## 11. Security & operations

- HTTPS mandatory (TLS on Apache).
- Password hashing: use PHP `password_hash()` (bcrypt).
- JWT: short expirations + refresh tokens stored securely.
- CORS: configure allowed origins for the web frontend.
- Rate limiting: protect critical endpoints (`/auth`, `/join`).
- Input validation & prepared statements (PDO) to avoid SQL injection.
- Consistent notification synchronization across devices.
- Logs & audit: record critical actions (call, cancel, mark no-show).

---

## 12. Mobile specifics (Kotlin + Jetpack Compose)

- Persist JWT in `EncryptedSharedPreferences`.
- Use WorkManager to sync unread notifications and perform background check-ins when needed.
- Build reactive screens (StateFlow / ViewModel) to reflect SSE/polling updates.
- Offline handling: show last-known state and attempt reconnects.

---

## 13. Deploy / Infra (simple)

- Apache + PHP-FPM (or mod_php) + MariaDB.
- Structure: `public/` as document root with `index.php` (API router).
- Use Composer for dependencies even if using "plain PHP".
- Environment via `.env` (do not commit secrets).
- Database backups and simple migration scripts.

---

## 14. Tests and QA

- Unit tests (PHPUnit) for queue and scheduling logic.
- Concurrency tests: scripts simulating concurrent `join` and `call-next` operations.
- E2E manual flows: join queue, schedule, check-in, call.
- Production logs with `request_id` for traceability.

---

## 15. Suggested backlog (prioritized)

1. Basic infra + auth + models (users, establishments, services).
2. Implement walk-in queue: join, leave, call-next (transactional).
3. Simple attendant web panel (call, mark served).
4. Appointments with conflict checks.
5. Reconcile priorities (appointment vs walk-in).
6. Mobile client: login, join queue, create appointment.
7. In-app notifications via SSE/polling.
8. Better notification UX for mobile/web.
9. Reports and metrics.
10. Advanced features (QR, calendar integration, estimations).

---

## 16. Quick payload examples

**Join queue**

```
POST /api/v1/queues/12/join
Authorization: Bearer <token>
Body: { "user_id": 45, "priority": 0 }
Response: { "entry_id": 987, "position": 8, "estimated_wait_minutes": 22 }
```

**Create appointment**

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

---

This English document now mirrors the original Portuguese proposal (`docs/PROPOSE.md`) and explicitly states the use of AJAX for the web frontend and MariaDB as the single database.
## 3. Core data models (summary)

- `users` — clients, attendants, admins
- `establishments` — tenant or physical location
- `services` — service types with average duration
- `professionals` — staff members offering services
- `queues` — logical queues per establishment/service
- `queue_entries` — walk-in entries (position, status, priority)
- `appointments` — scheduled bookings with start/end, status

Indexes: `appointments(start_at)`, `queue_entries(queue_id,status,created_at)` and `appointments(professional_id,start_at)` are recommended.

## 4. Business rules (concise)

- Appointments have priority within `[start_at - grace_before, start_at + grace_after]` (e.g., grace_before = 10 min).
- Check-in by an appointment within the grace window grants priority at the scheduled time; otherwise mark `no_show` and release the slot to the live queue.
- Walk-in entries get `position = MAX(position)+1` atomically.
- `call-next` prefers checked-in appointments whose slot is due; otherwise picks the oldest waiting walk-in (order by priority DESC, created_at ASC).

## 5. Concurrency

- Use SQL transactions and `SELECT ... FOR UPDATE` when calculating positions or consuming the next entry.
- Prevent double-booking on appointment create with `NOT EXISTS` checks inside transactions.

## 6. Real-time / sync options

1. SSE (Server-Sent Events) — simple uni-directional streaming, easy to implement in PHP with a worker/process.
2. WebSockets — bi-directional (requires additional service or process: Ratchet, Swoole, or a managed provider).
3. AJAX polling — simple and reliable for MVP; use polling (e.g., every 5–10s) if hosting restricts long-lived connections.

Recommendation for MVP: implement AJAX polling (via `fetch`/XHR) or SSE depending on hosting constraints; add Redis pub/sub and a worker for scale.

## 7. Key REST endpoints (examples)

- `POST /api/v1/auth/login` — returns JWT
- `POST /api/v1/queues/{id}/join` — join walk-in queue
- `POST /api/v1/queues/{id}/call-next` — attendant calls next
- `POST /api/v1/appointments` — create appointment (with conflict checks)
- `POST /api/v1/appointments/{id}/checkin` — mark appointment check-in

## 8. Notifications

- Mobile: in-app notifications synchronized via API
- Web: in-app toasts via SSE/WebSocket or Web Push (optional)

## 9. MVP (minimum scope)

- JWT-based authentication
- CRUD for establishments, services, professionals (admin)
- Walk-in queue: join, leave, call-next (transactional)
- Appointment creation with conflict checks
- Basic attendant panel to call and mark served
- Real-time via polling or SSE for MVP

Acceptance criteria

- Client can join queue and see a correct position.
- Attendant can call next and mark served without race conditions.
- Booked client can check in and be prioritized.

## 10. Security & ops (high level)

- HTTPS required
- Use `password_hash()` (bcrypt) for passwords
- JWT with short expiration + refresh tokens
- Prepared statements / PDO to avoid SQL injection
- Rate-limit critical endpoints (auth, join)

## 11. Roadmap (next items)

1. Implement core models and migrations
2. Build auth and basic API routes
3. Implement transactional walk-in queue logic
4. Add appointment flow and priority reconciliation
5. Create simple web attendant panel and mobile client

---

This document is an English, structured summary of the project scope. For the original Portuguese version, see `docs/PROPOSE.md`.


