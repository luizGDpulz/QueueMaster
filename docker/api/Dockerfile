# ============================================================================
# Stage 1: Build Quasar Frontend
# ============================================================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Copy package files for layer caching
COPY web/queuemaster/package.json web/queuemaster/package-lock.json* ./

# Install dependencies
RUN npm ci --ignore-scripts 2>/dev/null || npm install

# Copy frontend source
COPY web/queuemaster/ .

# Build args for env vars baked into the SPA at build time
ARG VITE_API_URL
ARG VITE_GOOGLE_CLIENT_ID

# Write .env for the build
RUN echo "VITE_API_URL=${VITE_API_URL}" > .env \
    && echo "VITE_GOOGLE_CLIENT_ID=${VITE_GOOGLE_CLIENT_ID}" >> .env

# Install Quasar CLI and build
RUN npx quasar build

# ============================================================================
# Stage 2: PHP + Apache (serves API + SPA via unified entry point)
# ============================================================================
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    openssl \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Apache virtual host config
COPY docker/api/apache.conf /etc/apache2/sites-available/000-default.conf

# ---- Project layout inside container ----
# /var/www/
#   public/         <- Document root (index.php routes API + SPA)
#   api/            <- PHP backend
#   web/dist/spa/   <- Built Quasar SPA

WORKDIR /var/www

# Copy root public/ entry point (unified router: API + SPA)
COPY public/ /var/www/public/

# Copy API composer files first for layer caching
COPY api/composer.json api/composer.phar /var/www/api/

# Install composer dependencies (no dev, no lock file in repo)
RUN cd /var/www/api && php composer.phar update --no-dev --no-audit --optimize-autoloader --no-interaction

# Copy API source code
COPY api/ /var/www/api/

# Copy built SPA from frontend builder stage
COPY --from=frontend-builder /app/dist/spa /var/www/web/dist/spa

# Create required directories and set permissions
RUN mkdir -p /var/www/api/keys /var/www/api/logs \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod 700 /var/www/api/keys

EXPOSE 80
