# QueueMaster

A lightweight hybrid queue and appointment management system built with PHP.

## Overview

QueueMaster lets customers either join a live walk-in queue or schedule appointments. The system reconciles both flows so scheduled customers get priority at their booked time while live queue members fill available slots.

### Key Features

- **Multi-role authentication** - Admin, attendant, and client roles with JWT (RS256)
- **Walk-in queues** - Atomic position calculation with real-time updates
- **Appointment scheduling** - Time slot management with conflict detection
- **Priority system** - Appointments get priority in configurable grace windows
- **Real-time updates** - Server-Sent Events (SSE) for queue and appointment changes
- **RESTful API** - Version 1 (`/api/v1/`) with comprehensive endpoints
- **Rate limiting** - Built-in request throttling for security
- **Mobile ready** - Designed for Android (Kotlin + Jetpack Compose) integration

### Architecture

- **Backend:** PHP 8.1+ with RESTful JSON API
- **Database:** MariaDB 10.2+ or MySQL 5.7+
- **Authentication:** JWT with RSA key pair (RS256)
- **Real-time:** Server-Sent Events (SSE) for live updates
- **Web Frontend:** HTML + Tailwind CSS + vanilla JavaScript
- **Mobile:** Kotlin + Jetpack Compose (Android)

## Requirements

- **PHP 8.1 or higher**
- **MariaDB 10.2+** or **MySQL 5.7+**
- **Composer** (dependency manager)
- **OpenSSL** (for generating RSA keys)
- **Apache** or **PHP built-in server** (development)

### PHP Extensions Required

- `ext-pdo` - Database connectivity
- `ext-json` - JSON encoding/decoding
- `ext-openssl` - JWT signature generation

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/QueueMaster.git
cd QueueMaster
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Generate RSA Keys for JWT

Create a `keys` directory and generate RSA key pair:

```bash
mkdir -p keys
openssl genrsa -out keys/private.key 2048
openssl rsa -in keys/private.key -pubout -out keys/public.key
chmod 600 keys/private.key
chmod 644 keys/public.key
```

### 4. Configure Environment

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=rootpassword
DB_NAME=queue_system

# JWT Configuration
JWT_PRIVATE_KEY_PATH=keys/private.key
JWT_PUBLIC_KEY_PATH=keys/public.key
ACCESS_TOKEN_TTL=900
REFRESH_TOKEN_TTL=2592000

# Application
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=America/Sao_Paulo
API_BASE_URL=http://localhost:8080
```

### 5. Create Database

Using Docker (recommended):

```bash
docker-compose up -d
```

Or manually create the database:

```bash
mysql -u root -p -e "CREATE DATABASE queue_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 6. Run Migrations

Apply database migrations to create all tables:

```bash
php api/scripts/migrate.php up
```

### 7. Seed Sample Data (Optional)

Load sample data for testing:

```bash
mysql -u root -p queue_system < scripts/seed_sample_data.sql
```

This creates:
- 3 users: admin, attendant, client (password: `password123`)
- 1 establishment: Central Medical Clinic
- 2 services: General Consultation, Medical Exam
- 2 professionals: Dr. Maria Silva, Dr. João Santos
- 1 active queue with 3 entries
- 3 sample appointments

### 8. Start the Server

**Option A: PHP Built-in Server (Development)**

```bash
php -S 127.0.0.1:8080 -t public
```

**Option B: Apache Configuration**

Create a virtual host pointing to the `public` directory:

```apache
<VirtualHost *:80>
    DocumentRoot "/path/to/QueueMaster/public"
    ServerName queuemaster.local
    
    <Directory "/path/to/QueueMaster/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 9. Verify Installation

Test the health endpoint:

```bash
curl http://127.0.0.1:8080/health
```

Expected response:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": 1234567890,
    "version": "1.0.0"
  }
}
```

## Quick Start with cURL

### 1. Register a New User

```bash
curl -X POST http://127.0.0.1:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "securepassword123",
    "role": "client"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "client"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 900
  }
}
```

### 2. Login

```bash
curl -X POST http://127.0.0.1:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "securepassword123"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 900,
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "client"
    }
  }
}
```

### 3. Get Authenticated User Info

```bash
curl http://127.0.0.1:8080/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "client",
      "created_at": "2024-01-19 10:30:00"
    }
  }
}
```

### 4. List Establishments

```bash
curl http://127.0.0.1:8080/api/v1/establishments
```

**Response:**
```json
{
  "success": true,
  "data": {
    "establishments": [
      {
        "id": 1,
        "name": "Central Medical Clinic",
        "address": "123 Health Street, Downtown",
        "created_at": "2024-01-19 10:00:00"
      }
    ],
    "total": 1
  }
}
```

### 5. Join a Queue

```bash
curl -X POST http://127.0.0.1:8080/api/v1/queues/1/join \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "priority": 0
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "queue_entry": {
      "id": 4,
      "queue_id": 1,
      "user_id": 1,
      "position": 4,
      "status": "waiting",
      "priority": 0,
      "created_at": "2024-01-19 11:00:00"
    },
    "message": "Successfully joined the queue"
  }
}
```

### 6. Call Next Person in Queue (Attendant/Admin)

```bash
curl -X POST http://127.0.0.1:8080/api/v1/queues/1/call-next \
  -H "Authorization: Bearer ATTENDANT_ACCESS_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "called_entry": {
      "id": 1,
      "queue_id": 1,
      "user_id": 1,
      "position": 1,
      "status": "called",
      "updated_at": "2024-01-19 11:05:00"
    },
    "message": "Next person called successfully"
  }
}
```

### 7. Create an Appointment

```bash
curl -X POST http://127.0.0.1:8080/api/v1/appointments \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "establishment_id": 1,
    "professional_id": 1,
    "service_id": 1,
    "start_at": "2024-01-20 14:00:00"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "appointment": {
      "id": 4,
      "establishment_id": 1,
      "professional_id": 1,
      "service_id": 1,
      "user_id": 1,
      "start_at": "2024-01-20 14:00:00",
      "end_at": "2024-01-20 14:30:00",
      "status": "booked",
      "created_at": "2024-01-19 11:10:00"
    },
    "message": "Appointment created successfully"
  }
}
```

### 8. Check In for Appointment

```bash
curl -X POST http://127.0.0.1:8080/api/v1/appointments/4/checkin \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "appointment": {
      "id": 4,
      "status": "checked_in",
      "checkin_at": "2024-01-20 13:55:00"
    },
    "message": "Successfully checked-in"
  }
}
```

### 9. Server-Sent Events (SSE) Stream

Connect to real-time queue updates:

```bash
curl -N http://127.0.0.1:8080/api/v1/streams/queue/1 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

**SSE Events:**
```
event: queue_update
data: {"queue_id":1,"total_waiting":3,"position":2}

event: position_changed
data: {"entry_id":4,"old_position":4,"new_position":2}

event: entry_called
data: {"entry_id":1,"user_id":1,"called_at":"2024-01-19 11:05:00"}
```

## Testing

### Run PHPUnit Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
vendor/bin/phpunit --testsuite "QueueMaster Test Suite"
```

### Run with Coverage Report

```bash
vendor/bin/phpunit --coverage-html coverage/html
```

### Test Configuration

Tests use a separate database (`queue_system_test`). Configuration is in `phpunit.xml.dist`.

## API Documentation

### API Versioning

All API endpoints are versioned under `/api/v1/`. This allows for future API changes without breaking existing clients.

**Base URL:** `http://your-domain.com/api/v1/`

### Authentication

QueueMaster uses JWT (JSON Web Tokens) with RS256 algorithm for authentication.

**Token Types:**
- **Access Token** - Short-lived (15 minutes default), used for API requests
- **Refresh Token** - Long-lived (30 days default), used to obtain new access tokens

**Using Tokens:**

Include the access token in the `Authorization` header:

```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### Endpoints Summary

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| **Authentication** |
| POST | `/api/v1/auth/register` | Register new user | No |
| POST | `/api/v1/auth/login` | Login user | No |
| POST | `/api/v1/auth/refresh` | Refresh access token | No |
| GET | `/api/v1/auth/me` | Get current user | Yes |
| POST | `/api/v1/auth/logout` | Logout user | Yes |
| **Establishments** |
| GET | `/api/v1/establishments` | List establishments | No |
| GET | `/api/v1/establishments/{id}` | Get establishment | No |
| GET | `/api/v1/establishments/{id}/services` | Get services | No |
| GET | `/api/v1/establishments/{id}/professionals` | Get professionals | No |
| **Queues** |
| GET | `/api/v1/queues` | List queues | No |
| GET | `/api/v1/queues/{id}` | Get queue details | No |
| POST | `/api/v1/queues/{id}/join` | Join queue | Yes |
| GET | `/api/v1/queues/{id}/status` | Get queue status | No |
| POST | `/api/v1/queues/{id}/leave` | Leave queue | Yes |
| POST | `/api/v1/queues/{id}/call-next` | Call next person | Yes (Attendant/Admin) |
| **Appointments** |
| GET | `/api/v1/appointments` | List appointments | Yes |
| GET | `/api/v1/appointments/{id}` | Get appointment | Yes |
| POST | `/api/v1/appointments` | Create appointment | Yes |
| DELETE | `/api/v1/appointments/{id}` | Cancel appointment | Yes |
| POST | `/api/v1/appointments/{id}/checkin` | Check-in | Yes |
| GET | `/api/v1/appointments/available-slots` | Get available slots | No |
| **Dashboard** (Attendant/Admin) |
| GET | `/api/v1/dashboard/overview` | Dashboard stats | Yes |
| GET | `/api/v1/dashboard/queue-stats` | Queue statistics | Yes |
| GET | `/api/v1/dashboard/appointment-stats` | Appointment stats | Yes |
| **Notifications** |
| GET | `/api/v1/notifications` | List notifications | Yes |
| POST | `/api/v1/notifications/{id}/read` | Mark as read | Yes |
| **SSE Streams** |
| GET | `/api/v1/streams/queue/{id}` | Queue updates stream | Yes |
| GET | `/api/v1/streams/appointments` | Appointments stream | Yes |
| GET | `/api/v1/streams/notifications` | Notifications stream | Yes |

## Security

### JWT Authentication (RS256)

- Uses RSA key pair for token signing/verification
- Access tokens expire after 15 minutes
- Refresh tokens expire after 30 days
- Tokens include user ID, role, and expiration

### Android Security

For Android clients, store tokens securely:

```kotlin
// Use EncryptedSharedPreferences
val masterKey = MasterKey.Builder(context)
    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
    .build()

val sharedPreferences = EncryptedSharedPreferences.create(
    context,
    "secure_prefs",
    masterKey,
    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
)
```

### Rate Limiting

- Global: 100 requests per minute per IP
- Registration: 5 requests per minute
- Login: 10 requests per minute
- Queue join: 10 requests per minute

### CORS Configuration

Configure allowed origins in `.env`:

```env
CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
```

## Production Deployment

### Prerequisites

1. **HTTPS** - Always use SSL/TLS in production
2. **Strong RSA Keys** - Use 4096-bit keys
3. **Database Backup** - Regular automated backups
4. **Monitoring** - Application and database monitoring
5. **Error Logging** - Centralized log management

### Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=your-db-host
DB_USER=your-db-user
DB_PASS=strong-password
JWT_PRIVATE_KEY_PATH=/secure/path/private.key
JWT_PUBLIC_KEY_PATH=/secure/path/public.key
```

### Apache Production Configuration

```apache
<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/queuemaster/public
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    <Directory /var/www/queuemaster/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    # Security headers
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</VirtualHost>
```

### Database Optimization

```sql
-- Enable query cache
SET GLOBAL query_cache_size = 268435456;

-- Optimize tables periodically
OPTIMIZE TABLE appointments, queue_entries, users;

-- Add additional indexes for production
CREATE INDEX idx_appointments_status_date ON appointments(status, start_at);
CREATE INDEX idx_queue_entries_status_created ON queue_entries(status, created_at);
```

## Troubleshooting

### Common Issues

#### 1. JWT Token Errors

**Problem:** "Invalid token" or "Token verification failed"

**Solutions:**
- Verify RSA keys exist in `keys/` directory
- Check file permissions: `chmod 600 keys/private.key`
- Ensure `.env` paths are correct
- Regenerate keys if corrupted

```bash
rm -rf keys/
mkdir keys
openssl genrsa -out keys/private.key 2048
openssl rsa -in keys/private.key -pubout -out keys/public.key
```

#### 2. Database Connection Failed

**Problem:** "Could not connect to database"

**Solutions:**
- Check database is running: `docker-compose ps`
- Verify credentials in `.env`
- Test connection: `mysql -h 127.0.0.1 -u root -p queue_system`
- Check firewall rules

#### 3. CORS Errors in Browser

**Problem:** "Access-Control-Allow-Origin" errors

**Solutions:**
- Add your domain to `CORS_ORIGINS` in `.env`
- Restart server after changing `.env`
- Use `*` for development only: `CORS_ORIGINS=*`

#### 4. Rate Limit Exceeded

**Problem:** "Too many requests" (429 error)

**Solutions:**
- Wait for rate limit window to reset
- Increase limits in `.env` for testing:
```env
RATE_LIMIT_MAX_REQUESTS=1000
RATE_LIMIT_WINDOW_SECONDS=60
```

#### 5. Migration Failures

**Problem:** Migration script fails

**Solutions:**
- Drop all tables and retry: `php api/scripts/migrate.php down`
- Check database user has CREATE/ALTER permissions
- Verify database charset: `utf8mb4`

#### 6. Server Not Starting

**Problem:** PHP built-in server won't start

**Solutions:**
- Check port is not in use: `lsof -i :8080`
- Try different port: `php -S 127.0.0.1:8000 -t public`
- Check PHP version: `php -v` (must be 8.1+)

#### 7. Appointment Conflicts

**Problem:** "Time slot is already booked"

**Solutions:**
- This is expected behavior - choose different time
- Check available slots: `GET /api/v1/appointments/available-slots`
- Verify professional schedule

#### 8. Queue Position Not Updating

**Problem:** Queue position stuck or incorrect

**Solutions:**
- Reconnect to SSE stream
- Check queue status: `GET /api/v1/queues/{id}/status`
- Verify database indexes: `SHOW INDEX FROM queue_entries`

### Debug Mode

Enable detailed error messages for development:

```env
APP_DEBUG=true
APP_ENV=development
```

Check logs:
```bash
tail -f logs/app.log
```

## Additional Resources

- **API Postman Collection:** Import `postman_collection.json` for full API testing
- **Database Schema:** See `api/migrations/` for table definitions
- **Proposals:** Read `docs/PROPOSE_EN.md` for detailed requirements
- **Sample Data:** Review `scripts/seed_sample_data.sql` for examples

## License

MIT License - See LICENSE file for details

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -am 'Add feature'`
4. Push to branch: `git push origin feature-name`
5. Submit pull request

## Support

For issues and questions:
- Create an issue on GitHub
- Check existing documentation in `docs/`
- Review API examples in Postman collection