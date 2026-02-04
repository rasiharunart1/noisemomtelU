#!/bin/bash

# Supervisor Setup Script for Laravel MQTT Listener & Scheduler
# Usage: sudo bash setup-supervisor.sh

set -e

echo "========================================="
echo "  Laravel Supervisor Setup Script"
echo "========================================="
echo ""

# Define paths
PROJECT_PATH="/www/wwwroot/noisemonlab/noisemomtelU"
WEB_USER="www-data"
SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Error: Please run as root (use sudo)${NC}"
    exit 1
fi

# Step 1: Install Supervisor
echo -e "${YELLOW}[1/5] Checking Supervisor installation...${NC}"
if ! command -v supervisorctl &> /dev/null; then
    echo "Installing Supervisor..."
    apt update
    apt install supervisor -y
    systemctl enable supervisor
    systemctl start supervisor
    echo -e "${GREEN}✓ Supervisor installed${NC}"
else
    echo -e "${GREEN}✓ Supervisor already installed${NC}"
fi

# Step 2: Create MQTT Listener config
echo -e "${YELLOW}[2/5] Creating MQTT Listener config...${NC}"
cat > ${SUPERVISOR_CONF_DIR}/laravel-mqtt-listener.conf <<EOF
[program:laravel-mqtt-listener]
process_name=%(program_name)s
command=php ${PROJECT_PATH}/artisan mqtt:listen
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${PROJECT_PATH}/storage/logs/mqtt-listener.log
stopwaitsecs=3600
startsecs=5
EOF
echo -e "${GREEN}✓ MQTT Listener config created${NC}"

# Step 3: Create Laravel Scheduler config
echo -e "${YELLOW}[3/5] Creating Laravel Scheduler config...${NC}"
cat > ${SUPERVISOR_CONF_DIR}/laravel-scheduler.conf <<EOF
[program:laravel-scheduler]
process_name=%(program_name)s
command=/bin/bash -c 'while [ true ]; do php ${PROJECT_PATH}/artisan schedule:run --verbose --no-interaction >> ${PROJECT_PATH}/storage/logs/scheduler.log 2>&1; sleep 60; done'
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${PROJECT_PATH}/storage/logs/scheduler.log
EOF
echo -e "${GREEN}✓ Laravel Scheduler config created${NC}"

# Step 4: Create log files and set permissions
echo -e "${YELLOW}[4/5] Setting up log files and permissions...${NC}"
touch ${PROJECT_PATH}/storage/logs/mqtt-listener.log
touch ${PROJECT_PATH}/storage/logs/scheduler.log
chown -R ${WEB_USER}:${WEB_USER} ${PROJECT_PATH}/storage/logs/
chmod -R 775 ${PROJECT_PATH}/storage/logs/
echo -e "${GREEN}✓ Permissions set${NC}"

# Step 5: Reload and start Supervisor
echo -e "${YELLOW}[5/5] Reloading Supervisor...${NC}"
supervisorctl reread
supervisorctl update
supervisorctl start laravel-mqtt-listener
supervisorctl start laravel-scheduler
echo -e "${GREEN}✓ Supervisor reloaded and services started${NC}"

echo ""
echo "========================================="
echo -e "${GREEN}  Setup Complete!${NC}"
echo "========================================="
echo ""
echo "Service Status:"
supervisorctl status

echo ""
echo "Useful Commands:"
echo "  - View MQTT logs:      tail -f ${PROJECT_PATH}/storage/logs/mqtt-listener.log"
echo "  - View Scheduler logs: tail -f ${PROJECT_PATH}/storage/logs/scheduler.log"
echo "  - Restart MQTT:        supervisorctl restart laravel-mqtt-listener"
echo "  - Restart Scheduler:   supervisorctl restart laravel-scheduler"
echo "  - Stop all:            supervisorctl stop all"
echo "  - Status:              supervisorctl status"
echo ""
