#!/bin/bash

# EC2 Connection Helper Script
# Edit the variables below with your EC2 details

# Your private key file path
KEY_FILE="your-key-name.pem"

# Your EC2 public IP address
EC2_IP="your-ec2-public-ip"

# Username (ubuntu for Ubuntu, ec2-user for Amazon Linux)
USER="ubuntu"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}=== EC2 Connection Helper ===${NC}"
echo ""

# Check if key file exists
if [ ! -f "$KEY_FILE" ]; then
    echo -e "${RED}Error: Key file not found: $KEY_FILE${NC}"
    echo "Please update KEY_FILE variable in this script"
    exit 1
fi

# Set correct permissions
echo -e "${YELLOW}Setting key file permissions...${NC}"
chmod 400 "$KEY_FILE"

# Check if EC2_IP is set
if [ "$EC2_IP" == "your-ec2-public-ip" ]; then
    echo -e "${RED}Error: Please update EC2_IP variable with your EC2 public IP${NC}"
    exit 1
fi

# Connect
echo -e "${GREEN}Connecting to EC2 instance...${NC}"
echo "IP: $EC2_IP"
echo "User: $USER"
echo ""

ssh -i "$KEY_FILE" "$USER@$EC2_IP"
