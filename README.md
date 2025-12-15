# QueueMaster

A lightweight hybrid queue and appointment management system.

Premise
-------
QueueMaster lets customers either join a live walk-in queue or schedule appointments. The system reconciles both flows so scheduled customers get priority at their booked time while live queue members fill available slots.

Quick Summary
-------------
- **Backend:** PHP (Apache) — RESTful JSON API
- **Web frontend:** HTML + Tailwind CSS + vanilla JavaScript (AJAX via `fetch` / XHR; real-time via SSE/WebSocket optional)
- **Mobile:** Kotlin + Jetpack Compose
- **Database:** MySQL / MariaDB
- **Database:** MariaDB

Key features
------------
- Multi-role authentication (admin / attendant / client)
- Walk-in queue entries with atomic position calculation
- Appointments per service/professional with conflict checks
- Priority rules: appointments are prioritized in a configurable grace window
- Real-time updates via SSE/WebSocket or AJAX polling

Files
-----
- Proposal (English): `docs/PROPOSE_EN.md`
- Original Portuguese proposal: `docs/PROPOSE.md`

## Database Setup

### Prerequisites
- Docker and Docker Compose installed
- PHP 7.4+ (for running migration scripts locally)
- MariaDB 10.2+ (or MySQL 5.7+)

### Quick Start with Docker

1. **Start the MariaDB container:**
   ```bash
   docker-compose up -d
   ```

   This will start:
   - MariaDB on port 3306
   - phpMyAdmin on port 8081 (http://localhost:8081)

2. **Copy and configure environment file:**
   ```bash
   cp .env.example .env
   ```

   Edit `.env` if needed. Default configuration works with Docker setup.

3. **Run migrations:**
   ```bash
   php api/scripts/migrate.php up
   ```

4. **Verify the schema:**
   - Visit http://localhost:8081 (phpMyAdmin)
   - Login credentials are in docker-compose.yml
   - Check the `queue_system` database

### Migration Commands

**Apply migrations (create tables):**
```bash
php api/scripts/migrate.php up
```

**Rollback migrations (drop tables):**
```bash
php api/scripts/migrate.php down
```

**Show help:**
```bash
php api/scripts/migrate.php help
```

### Manual Database Setup

If you prefer to use an existing MariaDB/MySQL instance:

1. Update `.env` with your database credentials:
   ```env
   DB_HOST=your-host
   DB_PORT=3306
   DB_USER=your-user
   DB_PASS=your-password
   DB_NAME=queue_system
   ```

2. Run the migration script:
   ```bash
   php api/scripts/migrate.php up
   ```

### Database Schema

The migrations create the following tables:

- **users** - User accounts (clients, attendants, admins)
- **establishments** - Physical locations or tenants
- **services** - Service types with duration
- **professionals** - Staff members offering services
- **queues** - Logical queues per establishment/service
- **queue_entries** - Walk-in queue entries (position, status, priority)
- **appointments** - Scheduled bookings with time slots
- **notifications** - User notifications

**Key Indexes:**
- `appointments(start_at)` - Fast appointment lookups by time
- `appointments(professional_id, start_at)` - Conflict checking
- `queue_entries(queue_id, status, position)` - Efficient queue operations

### Verify Schema

After running migrations, verify the schema:

```bash
docker-compose exec mariadb mysql -uroot -prootpassword queue_system -e "SHOW TABLES;"
```

Check indexes on appointments table:
```bash
docker-compose exec mariadb mysql -uroot -prootpassword queue_system -e "SHOW INDEX FROM appointments;"
```

Check indexes on queue_entries table:
```bash
docker-compose exec mariadb mysql -uroot -prootpassword queue_system -e "SHOW INDEX FROM queue_entries;"
```

### Stopping the Database

```bash
docker-compose down
```

To remove all data:
```bash
docker-compose down -v
```