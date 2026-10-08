#!/bin/bash
# ==============================================================================
#  NODERA BILLING — Linux Uninstaller & Reset
# ==============================================================================
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

clear
echo -e "${CYAN}${BOLD}"
echo "=================================================="
echo "      NODERA BILLING ISP — Uninstaller Linux      "
echo "=================================================="
echo -e "${NC}"

echo -e "${YELLOW}Perhatian: Proses ini akan menghentikan container Docker dan mereset database.${NC}"
read -p "Apakah Anda yakin ingin menghapus / mereset instalasi? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "Uninstal dibatalkan."
    exit 0
fi

echo ""
echo -e "${CYAN}1. Menghentikan & menghapus container Docker serta volume data...${NC}"
if [ -d "/opt/nodera-billing" ]; then
    cd /opt/nodera-billing
    sudo docker compose down -v --remove-orphans 2>/dev/null || docker compose down -v --remove-orphans 2>/dev/null || true
fi

# Stop dangling nodera containers & remove volumes
sudo docker rm -f nodera_billing_app nodera_billing_db nodera_billing_redis 2>/dev/null || true
sudo docker volume rm nodera-billing_db_data nodera-billing_redis_data nodera-billing_app_storage 2>/dev/null || true
sudo docker volume prune -f 2>/dev/null || true

echo -e "${CYAN}2. Membersihkan berkas konfigurasi .env & folder data...${NC}"

if [ -d "/opt/nodera-billing" ]; then
    sudo rm -rf /opt/nodera-billing
fi

echo ""
echo -e "${GREEN}${BOLD}=================================================="
echo "   UNINSTALL NODERA BILLING SELESAI DENGAN BERSIH!"
echo "==================================================${NC}"
echo -e "Sistem Anda sudah bersih dan siap untuk instalasi baru dari awal."
echo ""
