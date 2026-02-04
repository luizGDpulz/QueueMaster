# 🔐 QueueMaster - Security Guide

This document describes the security mechanisms implemented in QueueMaster and best practices for deployment.

---

## ✅ Security Mechanisms Implemented

### 1. Authentication

| Feature | Implementation | Notes |
|---------|----------------|-------|
| **OAuth Provider** | Google OAuth 2.0 | No passwords stored |
| **Token Validation** | Google tokeninfo endpoint | Validates `aud`, `exp`, `email_verified` |
| **Access Token** | JWT RS256 (asymmetric) | 15 min TTL |
| **Refresh Token** | SHA256 hash + rotation | 30 days TTL |
| **Token Storage** | Database (hashed) | Never store plain tokens |

### 2. SQL Injection Protection

```php
// ✅ All queries use prepared statements
$db->query("SELECT * FROM users WHERE id = ?", [$userId]);

// ✅ PDO configured with real prepared statements
PDO::ATTR_EMULATE_PREPARES => false
```

### 3. XSS Protection

```php
// ✅ Input sanitization available
use QueueMaster\Utils\Validator;

$cleanName = Validator::sanitizeString($data['name']);
$cleanEmail = Validator::sanitizeEmail($data['email']);
$cleanUrl = Validator::sanitizeUrl($data['url']);
$cleanData = Validator::sanitizeArray($data);
```

### 4. Security Headers

All responses include:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
Permissions-Policy: geolocation=(), microphone=(), camera=()
Strict-Transport-Security: max-age=31536000 (production only)
```

### 5. Rate Limiting

```php
// ✅ Token bucket algorithm
// Default: 60 requests per minute per IP
// Uses Redis in production, memory fallback for dev
```

### 6. CORS

```php
// ✅ Configurable via .env
CORS_ORIGINS=https://yourdomain.com

// ✅ Credentials supported
Access-Control-Allow-Credentials: true
```

### 7. Logging

```php
// ✅ Secrets are automatically redacted
// password, token, secret, api_key, authorization, jwt, bearer
// All become [REDACTED] in logs
```

---

## 🔧 Security Configuration Checklist

### Environment Variables (.env)

```dotenv
# ✅ REQUIRED: Never commit this file!

# Database - Use strong password
DB_PASS=use_strong_password_here

# Google OAuth - Keep secret
GOOGLE_CLIENT_ID=your-id.apps.googleusercontent.com

# JWT Keys - Generate unique per environment
# openssl genrsa -out keys/private.key 2048
# openssl rsa -in keys/private.key -pubout -out keys/public.key

# Production settings
APP_ENV=production
APP_DEBUG=false
CORS_ORIGINS=https://yourdomain.com
```

### .gitignore Verification

```gitignore
# ✅ Already configured to exclude:
.env
.env.local
.env.*.local
api/keys/
*.key
*.pem
*.log
api/vendor/
```

---

## 🚀 Production Deployment Checklist

### Before Going Live

- [ ] **HTTPS Only** - Configure SSL/TLS certificate
- [ ] **Strong DB Password** - Change from default
- [ ] **Generate New Keys** - Don't reuse dev keys
- [ ] **Set APP_ENV=production** - Disables debug info
- [ ] **Set APP_DEBUG=false** - Hides stack traces
- [ ] **Configure CORS_ORIGINS** - List specific domains
- [ ] **Enable Redis** - For distributed rate limiting
- [ ] **Set SUPER_ADMIN_EMAIL** - Before first login!

### Cron Jobs (Recommended)

```bash
# Token cleanup (daily at 3 AM)
0 3 * * * /usr/bin/php /var/www/api/scripts/cleanup_tokens.php

# Log rotation (weekly)
0 0 * * 0 find /var/www/api/logs -name "*.log" -mtime +30 -delete
```

---

## 🛡️ Attack Prevention

### SQL Injection
- ✅ All queries use PDO prepared statements
- ✅ `EMULATE_PREPARES = false` for true prepared statements
- ✅ QueryBuilder escapes all user input

### XSS (Cross-Site Scripting)
- ✅ JSON API (no HTML rendering)
- ✅ `Content-Type: application/json`
- ✅ Sanitization methods available for text fields

### CSRF (Cross-Site Request Forgery)
- ✅ SameSite cookies supported
- ✅ CORS restricts origins
- ✅ Bearer token authentication (not cookies)

### Brute Force
- ✅ Rate limiting (60 req/min default)
- ✅ Google OAuth (no password to brute force)
- ✅ Failed attempts logged

### Token Theft
- ✅ Short-lived access tokens (15 min)
- ✅ Refresh token rotation
- ✅ Revoked tokens tracked
- ✅ Logout invalidates all tokens

### Directory Traversal
- ✅ Swagger file paths validated with `realpath()`
- ✅ No user-controlled file paths

---

## 📊 Security Monitoring

### Log Analysis

Check for security events:

```bash
# Failed logins
grep "Security" logs/app-*.log | grep -i "token"

# Rate limit hits
grep "RATE_LIMIT" logs/app-*.log

# Unauthorized access attempts
grep "401\|403" logs/app-*.log
```

### Database Audit

```sql
-- Check for suspicious tokens (too many per user)
SELECT user_id, COUNT(*) as token_count 
FROM refresh_tokens 
WHERE revoked_at IS NULL 
GROUP BY user_id 
HAVING token_count > 10;

-- Recent logins
SELECT name, email, last_login_at 
FROM users 
ORDER BY last_login_at DESC 
LIMIT 20;
```

---

## 🔑 JWT Key Management

### Generate Keys (One-time per environment)

```bash
cd api/keys/

# Generate 2048-bit RSA private key
openssl genrsa -out private.key 2048

# Extract public key
openssl rsa -in private.key -pubout -out public.key

# Set permissions (Linux/Mac)
chmod 600 private.key
chmod 644 public.key
```

### Key Rotation (Advanced)

For zero-downtime key rotation:
1. Generate new key pair
2. Support both old and new public keys during transition
3. Switch to only new private key for signing
4. Remove old public key after all tokens expire (24h)

---

## 🚨 Incident Response

### If Secrets Are Exposed

1. **Immediately** rotate all JWT keys
2. **Immediately** change database password
3. **Revoke** all refresh tokens in database:
   ```sql
   UPDATE refresh_tokens SET revoked_at = NOW();
   ```
4. **Generate** new Google OAuth credentials
5. **Audit** logs for unauthorized access
6. **Notify** affected users if data breach confirmed

### If Database Is Compromised

1. No passwords to steal (Google OAuth only)
2. Refresh tokens are hashed (SHA256)
3. Rotate JWT keys to invalidate all sessions
4. Check for unauthorized role changes:
   ```sql
   SELECT * FROM users WHERE role = 'admin';
   ```

---

## 📚 References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP API Security](https://owasp.org/www-project-api-security/)
- [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2)
- [JWT Security Best Practices](https://auth0.com/blog/a-look-at-the-latest-draft-for-jwt-bcp/)
