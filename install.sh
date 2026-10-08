#!/bin/bash
# ==============================================================================
#  NODERA BILLING — Local & Standalone Linux Docker Installer
#  Supports: Ubuntu 20.04/22.04/24.04, Debian 11/12, Linux Mint, Proxmox VM, VPS
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || pwd)"
cd "$SCRIPT_DIR"

clear
echo -e "${CYAN}${BOLD}"
echo "=================================================="
echo "      NODERA BILLING ISP — Docker Installer       "
echo "=================================================="
echo -e "${NC}"

# If running directly from a clone or downloaded dir
INSTALL_TARGET="/opt/nodera-billing"
if [ -f "$SCRIPT_DIR/artisan" ] && [ "$SCRIPT_DIR" != "$INSTALL_TARGET" ]; then
    echo -e "${CYAN}Menyiapkan direktori aplikasi di ${INSTALL_TARGET}...${NC}"
    sudo mkdir -p "${INSTALL_TARGET}"
    sudo cp -rf "$SCRIPT_DIR/." "${INSTALL_TARGET}/" 2>/dev/null || true
    sudo chown -R "$USER:$USER" "${INSTALL_TARGET}" 2>/dev/null || true
    cd "${INSTALL_TARGET}"
elif [ ! -f "artisan" ] || [ ! -f "docker-compose.yml" ]; then
    echo -e "${CYAN}Menyiapkan direktori instalasi di ${INSTALL_TARGET}...${NC}"
    sudo mkdir -p "${INSTALL_TARGET}"
    
    echo -e "      Mengunduh paket rilis NODERA Billing..."
    sudo curl -fsSL "https://billing.example.com/downloads/nodera-latest.tar.gz" -o /tmp/nodera-latest.tar.gz 2>/dev/null || true
    if [ -f /tmp/nodera-latest.tar.gz ] && [ -s /tmp/nodera-latest.tar.gz ]; then
        sudo tar -xzf /tmp/nodera-latest.tar.gz -C "${INSTALL_TARGET}" --strip-components=1 2>/dev/null || true
        sudo rm -f /tmp/nodera-latest.tar.gz
    fi

    sudo chown -R "$USER:$USER" "${INSTALL_TARGET}" 2>/dev/null || true
    cd "${INSTALL_TARGET}"
fi

# 1. Install Docker & Docker Compose automatically if not found
if ! command -v docker &> /dev/null; then
    echo -e "${YELLOW}[INFO] Docker belum terpasang. Menginstall Docker secara otomatis...${NC}"
    
    sudo rm -f /etc/apt/sources.list.d/docker*.list /etc/apt/sources.list.d/*docker*.list /etc/apt/keyrings/docker.asc 2>/dev/null || true
    
    if command -v apt-get &> /dev/null; then
        echo -e "      Mengupdate repository dan memasang docker.io & docker-compose-v2..."
        sudo apt-get update -qq >/dev/null 2>&1 || true
        sudo apt-get install -y docker.io docker-compose-v2 2>/dev/null || sudo apt-get install -y docker.io 2>/dev/null || true
    elif command -v dnf &> /dev/null; then
        sudo dnf install -y docker docker-compose-plugin 2>/dev/null || true
    elif command -v yum &> /dev/null; then
        sudo yum install -y docker 2>/dev/null || true
    fi

    if ! command -v docker &> /dev/null; then
        echo -e "      Mencoba instalasi via official Docker script..."
        curl -fsSL https://get.docker.com | sudo sh 2>/dev/null || true
    fi

    if command -v docker &> /dev/null; then
        echo -e "${GREEN}Docker berhasil terpasang!${NC}"
    else
        echo -e "${RED}[ERROR] Gagal memasang Docker. Silakan install docker manual: sudo apt install docker.io docker-compose-v2${NC}"
        exit 1
    fi
fi

# Ensure Docker service is running
sudo systemctl enable --now docker 2>/dev/null || true
sudo systemctl start docker 2>/dev/null || true
sudo usermod -aG docker "$USER" 2>/dev/null || true
sudo chmod 666 /var/run/docker.sock 2>/dev/null || true

# 2. Setup Environment (.env)
echo -e "${CYAN}[1/3] Menyiapkan konfigurasi environment (.env)...${NC}"

APP_KEY="base64:$(openssl rand -base64 32 2>/dev/null || echo 'base64:7qY0d8cK5uU2xQ+8a1B2c3D4e5F6g7H8i9J0k1L2m3N=')"
DB_USER_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 20 || echo 'nodera_db_pass_2026')
DB_ROOT_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 20 || echo 'nodera_root_pass_2026')

if [ ! -f ".env" ]; then
    cat <<ENV_FILE > .env
APP_NAME="NODERA Billing"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://localhost:2300
STANDALONE_MODE=true
SETUP_COMPLETED=false

LICENSE_KEY=
NODERA_LICENSE_KEY=
NODERA_LICENSE_SERVER=https://billing.example.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=nodera_billing
DB_USERNAME=nodera_user
DB_PASSWORD=${DB_USER_PASS}
DB_ROOT_PASSWORD=${DB_ROOT_PASS}

BROADCAST_DRIVER=log
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

HTTP_PORT=2300
APP_PORT=2300
DB_EXTERNAL_PORT=3308

APP_TIMEZONE=Asia/Jakarta
APP_CURRENCY=IDR
APP_CURRENCY_SYMBOL=Rp
APP_LOCALE=id
ENV_FILE
fi

# Ensure storage permissions
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 3. Start Docker Containers
echo -e "${CYAN}[2/3] Menyalakan container aplikasi via Docker Compose...${NC}"
docker compose down --remove-orphans 2>/dev/null || true
docker compose up -d

echo -e "${CYAN}[3/3] Menunggu database siap & menjalankan migrasi awal...${NC}"
sleep 5
docker compose exec -T app php artisan migrate --force 2>/dev/null || true
docker compose exec -T app php artisan optimize:clear 2>/dev/null || true

# Get Server IP
SERVER_IP=$(curl -s -4 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}' 2>/dev/null || echo "127.0.0.1")

echo ""
echo -e "${GREEN}${BOLD}================================================================${NC}"
echo -e "${GREEN}${BOLD}       🎉 INSTALASI DOCKER NODERA BILLING BERHASIL!             ${NC}"
echo -e "${GREEN}${BOLD}================================================================${NC}"
echo ""
echo -e "Silakan buka browser Anda untuk menyelesaikan ${BOLD}Setup Wizard${NC}:"
echo ""
echo -e "   👉  ${CYAN}${BOLD}http://${SERVER_IP}:2300/setup${NC}"
echo -e "   (atau ${CYAN}http://${SERVER_IP}:2300${NC})"
echo ""
echo -e "Pada Setup Wizard di browser, Anda akan:"
echo -e "  1. 🎫 ${BOLD}Mengaktivasi License Key${NC} NODERA Anda"
echo -e "  2. 🏢 ${BOLD}Mengisi Nama & Profil ISP${NC} Anda"
echo -e "  3. 👤 ${BOLD}Membuat Akun Admin Billing${NC}"
echo ""
echo -e "${YELLOW}Perintah penting Docker:${NC}"
echo -e "  • Restart aplikasi : cd ${INSTALL_TARGET} && docker compose restart"
echo -e "  • Cek status log   : cd ${INSTALL_TARGET} && docker compose logs -f app"
echo -e "  • Stop aplikasi    : cd ${INSTALL_TARGET} && docker compose down"
echo -e "${GREEN}${BOLD}================================================================${NC}"
echo ""
