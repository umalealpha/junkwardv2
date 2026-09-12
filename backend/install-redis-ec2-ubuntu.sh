#!/bin/bash

# Redis Installation Script for EC2 Ubuntu
# This script installs and configures Redis for production use

set -e  # Exit on error

echo "=== Redis Installation for EC2 Ubuntu ==="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

# Update system
echo -e "${YELLOW}Updating system packages...${NC}"
apt-get update -qq
apt-get upgrade -y -qq

# Install Redis
echo -e "${YELLOW}Installing Redis...${NC}"
apt-get install redis-server -y -qq

# Get private IP address
PRIVATE_IP=$(hostname -I | awk '{print $1}')
echo -e "${GREEN}Detected private IP: ${PRIVATE_IP}${NC}"

# Generate strong password
REDIS_PASSWORD=$(openssl rand -base64 32)
# L3 (pentest) — never echo the secret to stdout (persists in terminal history /
# CI logs). It is written once to a root-only file (chmod 600) at the end.
echo -e "${GREEN}Generated a strong Redis password (written to /root/redis-password.txt).${NC}"
echo ""

# Backup original config
cp /etc/redis/redis.conf /etc/redis/redis.conf.backup.$(date +%Y%m%d_%H%M%S)

# Configure Redis
echo -e "${YELLOW}Configuring Redis...${NC}"

# Create log directory
mkdir -p /var/log/redis
chown redis:redis /var/log/redis

# Update Redis configuration
cat >> /etc/redis/redis.conf << EOF

# Production Configuration - Added by install script
# Bind to localhost and private IP
bind 127.0.0.1 ${PRIVATE_IP}

# Set password
requirepass ${REDIS_PASSWORD}

# Enable persistence
save 900 1
save 300 10
save 60 10000

# Memory management (adjust based on instance size)
maxmemory 256mb
maxmemory-policy allkeys-lru

# Security - Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command CONFIG "CONFIG_9a7b8c5d3e2f1g0h"

# Protected mode
protected-mode yes

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log

# Working directory
dir /var/lib/redis

# Network
tcp-keepalive 300
timeout 0

# Performance
tcp-backlog 511
databases 16
EOF

# Restart Redis
echo -e "${YELLOW}Restarting Redis...${NC}"
systemctl restart redis-server
systemctl enable redis-server

# Wait for Redis to start
sleep 2

# Test Redis
echo -e "${YELLOW}Testing Redis connection...${NC}"
if redis-cli -a "${REDIS_PASSWORD}" ping | grep -q "PONG"; then
    echo -e "${GREEN}✓ Redis is running successfully!${NC}"
else
    echo -e "${RED}✗ Redis connection test failed${NC}"
    exit 1
fi

# Create backup script
echo -e "${YELLOW}Setting up backup script...${NC}"
cat > /usr/local/bin/redis-backup.sh << 'BACKUP_SCRIPT'
#!/bin/bash
BACKUP_DIR="/var/backups/redis"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Get password from config
PASSWORD=$(grep "^requirepass" /etc/redis/redis.conf | awk '{print $2}')

# Create backup
redis-cli -a "$PASSWORD" --rdb $BACKUP_DIR/dump-$DATE.rdb

# Keep only last 7 days
find $BACKUP_DIR -name "dump-*.rdb" -mtime +7 -delete

echo "Redis backup completed: dump-$DATE.rdb"
BACKUP_SCRIPT

chmod +x /usr/local/bin/redis-backup.sh

# Add to crontab
(crontab -l 2>/dev/null | grep -v redis-backup.sh; echo "0 2 * * * /usr/local/bin/redis-backup.sh >> /var/log/redis-backup.log 2>&1") | crontab -

# Display summary
echo ""
echo -e "${GREEN}=== Installation Complete ===${NC}"
echo ""
echo "Redis Configuration:"
echo "  Status: $(systemctl is-active redis-server)"
echo "  Port: 6379"
echo "  Bind: 127.0.0.1, ${PRIVATE_IP}"
echo "  Password: (stored in /root/redis-password.txt)"
echo ""
echo -e "${YELLOW}IMPORTANT: Save this password!${NC}"
echo ""
echo "Next Steps:"
echo "1. Update Laravel .env file:"
echo "   REDIS_HOST=${PRIVATE_IP}"
echo "   REDIS_PASSWORD=<see /root/redis-password.txt>"
echo ""
echo "2. Configure AWS Security Group:"
echo "   - Allow port 6379 from your application server's private IP"
echo "   - DO NOT allow 0.0.0.0/0"
echo ""
echo "3. Test connection from application server:"
echo "   redis-cli -h ${PRIVATE_IP} -p 6379 -a \$(cat /root/redis-password.txt) ping"
echo ""
echo "Useful Commands:"
echo "  Check status: systemctl status redis-server"
echo "  View logs: tail -f /var/log/redis/redis-server.log"
echo "  Redis CLI: redis-cli -a \$(cat /root/redis-password.txt)"
echo "  Monitor: redis-cli -a \$(cat /root/redis-password.txt) MONITOR"
echo ""

# Save password to file (secure)
echo "${REDIS_PASSWORD}" > /root/redis-password.txt
chmod 600 /root/redis-password.txt
echo -e "${GREEN}Password saved to: /root/redis-password.txt${NC}"
echo ""
