#!/usr/bin/env bash
# ============================================================================
# QueueMaster - Deploy & Management Script
# ============================================================================
# Interactive script to setup, deploy, and manage the QueueMaster application
# using Docker containers (MariaDB + PHP/Apache API + Nginx Frontend).
#
# Usage: ./deploy.sh
# ============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
ENV_EXAMPLE="$SCRIPT_DIR/.env.deploy.example"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"
APP_CONTAINER="qm_app"
DB_CONTAINER="qm_mariadb"
BACKUP_DIR="$SCRIPT_DIR/backups"

# ---------------------------------------------------------------------------
# Colors
# ---------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ---------------------------------------------------------------------------
# Helper Functions
# ---------------------------------------------------------------------------
print_header() {
    clear
    echo -e "${CYAN}"
    echo "╔══════════════════════════════════════════╗"
    echo "║       🎫 QueueMaster Deploy Manager      ║"
    echo "╚══════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error()   { echo -e "${RED}✗ $1${NC}"; }
print_warn()    { echo -e "${YELLOW}⚠ $1${NC}"; }
print_info()    { echo -e "${BLUE}ℹ $1${NC}"; }

confirm() {
    local msg="${1:-Continuar?}"
    echo -en "${YELLOW}${msg} [y/N]: ${NC}"
    read -r response
    [[ "$response" =~ ^[yY]$ ]]
}

check_docker() {
    if ! command -v docker &>/dev/null; then
        print_error "Docker não encontrado. Instale: https://docs.docker.com/engine/install/"
        exit 1
    fi
    if ! docker compose version &>/dev/null; then
        print_error "Docker Compose não encontrado. Instale: https://docs.docker.com/compose/install/"
        exit 1
    fi
}

check_env() {
    if [[ ! -f "$ENV_FILE" ]]; then
        print_warn "Arquivo .env não encontrado."
        print_info "Execute a opção 1 (Setup Inicial) primeiro."
        echo ""
        return 1
    fi
    return 0
}

load_env() {
    if [[ -f "$ENV_FILE" ]]; then
        set -a
        source "$ENV_FILE"
        set +a
    fi
}

generate_password() {
    openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 24
}

# ---------------------------------------------------------------------------
# 1) Setup Inicial
# ---------------------------------------------------------------------------
do_setup() {
    print_header
    echo -e "${BOLD}⚙ Setup Inicial${NC}"
    echo "────────────────────────────────────────"
    echo "Este wizard vai configurar todas as variáveis necessárias."
    echo ""

    # Domain
    echo -en "${CYAN}Domínio ou IP do servidor${NC} (ex: app.meudominio.com): "
    read -r server_domain
    if [[ -z "$server_domain" ]]; then
        print_error "Domínio é obrigatório."
        return 1
    fi

    # Protocol
    echo -e "${YELLOW}HTTPS é obrigatório em produção (Let's Encrypt via NPM).${NC}"
    echo -en "${CYAN}Usar HTTPS?${NC} [Y/n]: "
    read -r use_https
    if [[ "$use_https" =~ ^[nN]$ ]]; then
        protocol="http"
    else
        protocol="https"
    fi

    # HTTP Port
    echo -e "${YELLOW}Se você usa Nginx Proxy Manager (NPM) ou outro reverse proxy,${NC}"
    echo -e "${YELLOW}esta é a porta que o proxy externo vai apontar.${NC}"
    echo -en "${CYAN}Porta HTTP externa do container${NC} [3000]: "
    read -r http_port
    http_port="${http_port:-3000}"

    # DB passwords
    echo ""
    echo -e "${BOLD}Banco de Dados (MariaDB)${NC}"
    echo "────────────────────────────────────────"

    auto_pass=$(generate_password)
    echo -en "${CYAN}Senha root do MariaDB${NC} [gerada: ${auto_pass:0:8}...]: "
    read -r -s db_root_pass
    echo ""
    db_root_pass="${db_root_pass:-$auto_pass}"

    auto_pass2=$(generate_password)
    echo -en "${CYAN}Senha do usuário da aplicação${NC} [gerada: ${auto_pass2:0:8}...]: "
    read -r -s db_app_pass
    echo ""
    db_app_pass="${db_app_pass:-$auto_pass2}"

    echo -en "${CYAN}Nome do banco${NC} [queue_master]: "
    read -r db_name
    db_name="${db_name:-queue_master}"

    echo -en "${CYAN}Porta externa do DB${NC} [3307]: "
    read -r db_port
    db_port="${db_port:-3307}"

    # Google OAuth
    echo ""
    echo -e "${BOLD}Google OAuth${NC}"
    echo "────────────────────────────────────────"
    echo -en "${CYAN}Google Client ID${NC}: "
    read -r google_client_id
    if [[ -z "$google_client_id" ]]; then
        print_warn "Google Client ID vazio — login não funcionará até configurar."
        google_client_id="your-client-id.apps.googleusercontent.com"
    fi

    # Admin
    echo ""
    echo -e "${BOLD}Administrador${NC}"
    echo "────────────────────────────────────────"
    echo -en "${CYAN}Email do super admin${NC}: "
    read -r admin_email
    if [[ -z "$admin_email" ]]; then
        print_error "Email do admin é obrigatório."
        return 1
    fi

    # Timezone
    echo -en "${CYAN}Timezone${NC} [America/Sao_Paulo]: "
    read -r timezone
    timezone="${timezone:-America/Sao_Paulo}"

    # Build CORS and API URL (public URLs through NPM — never include internal port)
    cors_origins="${protocol}://${server_domain}"
    vite_api_url="${protocol}://${server_domain}/api/v1"

    # Write .env
    echo ""
    print_info "Gerando arquivo .env ..."

    cat > "$ENV_FILE" <<EOF
# ============================================================================
# QueueMaster - Deploy Configuration (generated by deploy.sh)
# Generated at: $(date '+%Y-%m-%d %H:%M:%S')
# ============================================================================

# Server
SERVER_DOMAIN=${server_domain}
HTTP_PORT=${http_port}

# Database
DB_ROOT_PASSWORD=${db_root_pass}
DB_USER=queuemaster
DB_PASS=${db_app_pass}
DB_NAME=${db_name}
DB_PORT_EXPOSE=${db_port}

# Google OAuth
GOOGLE_CLIENT_ID=${google_client_id}

# Admin
SUPER_ADMIN_EMAIL=${admin_email}

# Application
APP_TIMEZONE=${timezone}
ACCESS_TOKEN_TTL=900
REFRESH_TOKEN_TTL=2592000
CORS_ORIGINS=${cors_origins}
VITE_API_URL=${vite_api_url}
EOF

    print_success "Arquivo .env criado!"

    # Generate JWT keys
    echo ""
    print_info "Gerando JWT keys (RSA 2048)..."
    do_generate_keys_internal
    print_success "JWT keys geradas!"

    echo ""
    echo -e "${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}  Setup concluído com sucesso!${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}"
    echo ""
    echo "Próximo passo: Execute a opção 2 (Build & Deploy)"
    echo ""
}

# ---------------------------------------------------------------------------
# 2) Build & Deploy
# ---------------------------------------------------------------------------
do_build() {
    print_header
    echo -e "${BOLD}🚀 Build & Deploy${NC}"
    echo "────────────────────────────────────────"

    check_env || return 1
    load_env

    echo ""
    print_info "Building containers..."
    echo ""

    docker compose -f "$COMPOSE_FILE" build --no-cache

    echo ""
    print_info "Starting containers..."
    echo ""

    docker compose -f "$COMPOSE_FILE" up -d

    echo ""
    print_info "Aguardando banco ficar pronto..."
    sleep 5

    # Wait for DB healthy
    local retries=30
    while [[ $retries -gt 0 ]]; do
        if docker inspect "$DB_CONTAINER" --format='{{.State.Health.Status}}' 2>/dev/null | grep -q "healthy"; then
            break
        fi
        echo -n "."
        sleep 2
        ((retries--))
    done
    echo ""

    if [[ $retries -eq 0 ]]; then
        print_warn "Banco pode não estar 100% pronto, mas continuando..."
    else
        print_success "Banco de dados pronto!"
    fi

    # Run migrations automatically
    echo ""
    print_info "Rodando migrations..."
    docker compose -f "$COMPOSE_FILE" exec -T app php /var/www/api/scripts/migrate.php up || {
        print_error "Falha ao rodar migrations."
        print_warn "Certifique-se de que o banco está acessível."
    }

    # Verify if users table exists (basic check)
    echo ""
    print_info "Verificando integridade do banco..."
    if docker compose -f "$COMPOSE_FILE" exec -T mariadb mysql -u root -p"${DB_ROOT_PASSWORD}" -e "USE ${DB_NAME:-queue_master}; SELECT COUNT(*) FROM users;" &>/dev/null; then
        print_success "Tabelas encontradas!"
    else
        print_warn "MESA DE USUÁRIOS NÃO ENCONTRADA!"
        print_info "Execute a opção 6 para rodar as migrations manualmente."
    fi

    echo ""
    echo -e "${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}  Deploy concluído!${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}"
    echo ""
    echo "Acesse: ${CORS_ORIGINS:-http://localhost}"
    echo "API:    ${VITE_API_URL:-http://localhost/api/v1}"
    echo ""
    print_warn "DICA: Se os ícones não aparecerem ou o login falhar,"
    print_warn "limpe o cache do Cloudflare e use ABA ANÔNIMA."
    echo ""
}

# ---------------------------------------------------------------------------
# 3) Parar containers
# ---------------------------------------------------------------------------
do_stop() {
    print_header
    echo -e "${BOLD}⏹ Parando containers${NC}"
    echo "────────────────────────────────────────"

    docker compose -f "$COMPOSE_FILE" down
    print_success "Todos os containers parados."
    echo ""
}

# ---------------------------------------------------------------------------
# 4) Reiniciar containers
# ---------------------------------------------------------------------------
do_restart() {
    print_header
    echo -e "${BOLD}🔄 Reiniciando containers${NC}"
    echo "────────────────────────────────────────"

    docker compose -f "$COMPOSE_FILE" restart
    print_success "Containers reiniciados."
    echo ""
}

# ---------------------------------------------------------------------------
# 5) Ver logs
# ---------------------------------------------------------------------------
do_logs() {
    print_header
    echo -e "${BOLD}📋 Logs dos Containers${NC}"
    echo "────────────────────────────────────────"
    echo "  1) Todos os serviços"
    echo "  2) App (API + Frontend)"
    echo "  3) MariaDB"
    echo "  0) Voltar"
    echo ""
    echo -en "Escolha: "
    read -r log_choice

    case "$log_choice" in
        1) docker compose -f "$COMPOSE_FILE" logs --tail=100 -f ;;
        2) docker compose -f "$COMPOSE_FILE" logs --tail=100 -f app ;;
        3) docker compose -f "$COMPOSE_FILE" logs --tail=100 -f mariadb ;;
        *) return ;;
    esac
}

# ---------------------------------------------------------------------------
# 6) Rodar migrations
# ---------------------------------------------------------------------------
do_migrations() {
    print_header
    echo -e "${BOLD}📦 Migrations${NC}"
    echo "────────────────────────────────────────"
    echo "  1) Rodar migrations (up)"
    echo "  2) Rollback migrations (down)"
    echo "  0) Voltar"
    echo ""
    echo -en "Escolha: "
    read -r mig_choice

    case "$mig_choice" in
        1)
            print_info "Rodando migrations UP..."
            docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/migrate.php up
            print_success "Migrations aplicadas!"
            ;;
        2)
            if confirm "Tem certeza que deseja fazer rollback?"; then
                print_info "Rodando migrations DOWN..."
                docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/migrate.php down
                print_success "Rollback concluído!"
            fi
            ;;
        *) return ;;
    esac
    echo ""
}

# ---------------------------------------------------------------------------
# 7) Rodar seeds
# ---------------------------------------------------------------------------
do_seeds() {
    print_header
    echo -e "${BOLD}🌱 Seeds${NC}"
    echo "────────────────────────────────────────"
    echo "  1) Rodar production seeds (up)"
    echo "  2) Rodar sample data"
    echo "  3) Limpar seed data (down)"
    echo "  0) Voltar"
    echo ""
    echo -en "Escolha: "
    read -r seed_choice

    case "$seed_choice" in
        1)
            print_info "Rodando production seeds..."
            docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/seed.php up
            print_success "Seeds aplicadas!"
            ;;
        2)
            if confirm "Carregar dados de exemplo?"; then
                docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/seed.php sample
                print_success "Sample data carregada!"
            fi
            ;;
        3)
            if confirm "Tem certeza que deseja limpar os seeds?"; then
                docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/seed.php down
                print_success "Seed data limpa!"
            fi
            ;;
        *) return ;;
    esac
    echo ""
}

# ---------------------------------------------------------------------------
# 8) Regenerar JWT Keys
# ---------------------------------------------------------------------------
do_generate_keys_internal() {
    # Generate keys locally (bind mount handles the container)
    local keys_dir="$SCRIPT_DIR/api/keys"
    mkdir -p "$keys_dir"
    
    print_info "Gerando novas chaves RSA..."
    openssl genrsa -out "$keys_dir/private.key" 2048
    openssl rsa -in "$keys_dir/private.key" -pubout -out "$keys_dir/public.key"
    
    # Security permissions (On Linux/Server)
    # Using 644 because the container user (www-data) needs read access 
    # and bind-mount owner is usually the host user.
    chmod 644 "$keys_dir/private.key" 2>/dev/null || true
    chmod 644 "$keys_dir/public.key" 2>/dev/null || true
    
    # Try to set owner to www-data for Apache readability
    chown www-data:www-data "$keys_dir"/*.key 2>/dev/null || true

    # Verify generation
    if [ ! -f "$keys_dir/private.key" ] || [ ! -s "$keys_dir/private.key" ]; then
        print_error "ERRO: Chave privada não gerada ou está vazia!"
        print_warn "Verifique se o OpenSSL está instalado no host."
    else
        print_success "Chaves JWT geradas e protegidas em $keys_dir"
    fi
}

do_generate_keys() {
    print_header
    echo -e "${BOLD}🔑 Regenerar JWT Keys${NC}"
    echo "────────────────────────────────────────"
    echo ""
    print_warn "Isso vai invalidar TODOS os tokens ativos!"
    print_warn "Todos os usuários terão que fazer login novamente."
    echo ""

    if confirm "Tem certeza que deseja regenerar as JWT keys?"; then
        print_info "Gerando novas RSA keys (2048 bits)..."
        do_generate_keys_internal
        print_success "JWT keys regeneradas!"
        echo ""
        print_info "Reiniciando API para aplicar novas keys..."
        docker compose -f "$COMPOSE_FILE" restart app 2>/dev/null || true
        print_success "API reiniciada com novas keys."
    fi
    echo ""
}

# ---------------------------------------------------------------------------
# 9) Status dos Serviços
# ---------------------------------------------------------------------------
do_status() {
    print_header
    echo -e "${BOLD}📊 Status dos Serviços${NC}"
    echo "────────────────────────────────────────"
    echo ""

    docker compose -f "$COMPOSE_FILE" ps 2>/dev/null || print_warn "Containers não estão rodando."

    echo ""

    # Show additional info if env exists
    if check_env 2>/dev/null; then
        load_env
        echo -e "${CYAN}URLs:${NC}"
        echo "  Frontend: ${CORS_ORIGINS:-N/A}"
        echo "  API:      ${VITE_API_URL:-N/A}"
        echo "  DB Port:  ${DB_PORT_EXPOSE:-3307}"
        echo ""
    fi
}

# ---------------------------------------------------------------------------
# 10) Backup do Banco
# ---------------------------------------------------------------------------
do_backup() {
    print_header
    echo -e "${BOLD}💾 Backup do Banco de Dados${NC}"
    echo "────────────────────────────────────────"

    check_env || return 1
    load_env

    mkdir -p "$BACKUP_DIR"

    local timestamp
    timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$BACKUP_DIR/queuemaster_${timestamp}.sql"

    print_info "Criando backup..."

    docker compose -f "$COMPOSE_FILE" exec -T mariadb \
        mysqldump -u root -p"${DB_ROOT_PASSWORD}" "${DB_NAME:-queue_master}" \
        --single-transaction --routines --triggers \
        > "$backup_file" 2>/dev/null

    if [[ -s "$backup_file" ]]; then
        local size
        size=$(du -h "$backup_file" | cut -f1)
        print_success "Backup criado: $backup_file ($size)"
    else
        print_error "Falha no backup. Verifique se os containers estão rodando."
        rm -f "$backup_file"
    fi
    echo ""
}

# ---------------------------------------------------------------------------
# 11) Restaurar Backup
# ---------------------------------------------------------------------------
do_restore() {
    print_header
    echo -e "${BOLD}📥 Restaurar Backup${NC}"
    echo "────────────────────────────────────────"

    check_env || return 1
    load_env

    if [[ ! -d "$BACKUP_DIR" ]] || [[ -z "$(ls -A "$BACKUP_DIR" 2>/dev/null)" ]]; then
        print_warn "Nenhum backup encontrado em: $BACKUP_DIR"
        return 1
    fi

    echo "Backups disponíveis:"
    echo ""
    local i=1
    local backups=()
    for f in "$BACKUP_DIR"/*.sql; do
        local size
        size=$(du -h "$f" | cut -f1)
        echo "  $i) $(basename "$f") ($size)"
        backups+=("$f")
        ((i++))
    done

    echo "  0) Cancelar"
    echo ""
    echo -en "Escolha o backup: "
    read -r backup_choice

    if [[ "$backup_choice" == "0" ]] || [[ -z "$backup_choice" ]]; then
        return
    fi

    local idx=$((backup_choice - 1))
    if [[ $idx -lt 0 ]] || [[ $idx -ge ${#backups[@]} ]]; then
        print_error "Opção inválida."
        return 1
    fi

    local selected="${backups[$idx]}"
    echo ""
    print_warn "Isso vai SUBSTITUIR todo o banco de dados atual!"

    if confirm "Restaurar $(basename "$selected")?"; then
        print_info "Restaurando backup..."
        docker compose -f "$COMPOSE_FILE" exec -T mariadb \
            mysql -u root -p"${DB_ROOT_PASSWORD}" "${DB_NAME:-queue_master}" \
            < "$selected" 2>/dev/null
        print_success "Backup restaurado!"
    fi
    echo ""
}

# ---------------------------------------------------------------------------
# 12) Rebuild (apenas frontend ou API)
# ---------------------------------------------------------------------------
do_rebuild() {
    print_header
    echo -e "${BOLD}🔨 Rebuild${NC}"
    echo "────────────────────────────────────────"
    echo "  1) Rebuild tudo (full)"
    echo "  2) Rebuild apenas App (API + Frontend)"
    echo "  0) Voltar"
    echo ""
    echo -en "Escolha: "
    read -r rebuild_choice

    check_env || return 1
    load_env

    case "$rebuild_choice" in
        1)
            print_info "Rebuilding tudo..."
            docker compose -f "$COMPOSE_FILE" build --no-cache
            docker compose -f "$COMPOSE_FILE" up -d
            print_success "Rebuild completo!"
            ;;
        2)
            print_info "Rebuilding App (API + Frontend)..."
            docker compose -f "$COMPOSE_FILE" build --no-cache app
            docker compose -f "$COMPOSE_FILE" up -d app
            print_success "App rebuilt!"
            ;;
        *) return ;;
    esac
    echo ""
}

# ---------------------------------------------------------------------------
# 13) Cleanup tokens
# ---------------------------------------------------------------------------
do_cleanup_tokens() {
    print_header
    echo -e "${BOLD}🧹 Cleanup de Tokens Expirados${NC}"
    echo "────────────────────────────────────────"

    print_info "Limpando tokens expirados..."
    docker compose -f "$COMPOSE_FILE" exec app php /var/www/api/scripts/cleanup_tokens.php
    print_success "Tokens expirados removidos!"
    echo ""
}

# ===========================================================================
# Main Menu
# ===========================================================================
main_menu() {
    while true; do
        print_header
        echo -e "${BOLD}  Menu Principal${NC}"
        echo "────────────────────────────────────────"
        echo ""
        echo -e "  ${CYAN} 1)${NC} ⚙  Setup inicial (configurar tudo)"
        echo -e "  ${CYAN} 2)${NC} 🚀 Build & Deploy"
        echo -e "  ${CYAN} 3)${NC} ⏹  Parar containers"
        echo -e "  ${CYAN} 4)${NC} 🔄 Reiniciar containers"
        echo -e "  ${CYAN} 5)${NC} 📋 Ver logs"
        echo -e "  ${CYAN} 6)${NC} 📦 Rodar migrations"
        echo -e "  ${CYAN} 7)${NC} 🌱 Rodar seeds"
        echo -e "  ${CYAN} 8)${NC} 🔑 Regenerar JWT keys"
        echo -e "  ${CYAN} 9)${NC} 📊 Status dos serviços"
        echo -e "  ${CYAN}10)${NC} 💾 Backup do banco"
        echo -e "  ${CYAN}11)${NC} 📥 Restaurar backup"
        echo -e "  ${CYAN}12)${NC} 🔨 Rebuild (API/Frontend)"
        echo -e "  ${CYAN}13)${NC} 🧹 Cleanup tokens expirados"
        echo ""
        echo -e "  ${CYAN} 0)${NC} 🚪 Sair"
        echo ""
        echo "────────────────────────────────────────"
        echo -en "Escolha uma opção: "
        read -r choice

        case "$choice" in
            1)  do_setup ;;
            2)  do_build ;;
            3)  do_stop ;;
            4)  do_restart ;;
            5)  do_logs ;;
            6)  do_migrations ;;
            7)  do_seeds ;;
            8)  do_generate_keys ;;
            9)  do_status ;;
            10) do_backup ;;
            11) do_restore ;;
            12) do_rebuild ;;
            13) do_cleanup_tokens ;;
            0)  echo -e "\n${GREEN}Até logo! 👋${NC}\n"; exit 0 ;;
            *)  print_error "Opção inválida." ;;
        esac

        echo ""
        echo -en "Pressione Enter para continuar..."
        read -r
    done
}

# ===========================================================================
# Entry Point
# ===========================================================================
check_docker
main_menu
