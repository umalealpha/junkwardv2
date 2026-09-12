#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# Graphite v2 — ALB + ECS Services Setup (af-south-1)
# Run AFTER setup-infrastructure.sh
# ═══════════════════════════════════════════════════════════════

set -e
REGION="af-south-1"
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
VPC_ID="vpc-d7d733be"
# Public subnets
SUBNET_1="subnet-5fd13536"   # af-south-1a
SUBNET_2="subnet-31505649"   # af-south-1b
SUBNET_3="subnet-3cd4f576"   # af-south-1c

# Get ECS security group
ECS_SG=$(aws ec2 describe-security-groups \
  --region $REGION \
  --filters "Name=group-name,Values=graphite-ecs-sg" "Name=vpc-id,Values=$VPC_ID" \
  --query "SecurityGroups[0].GroupId" \
  --output text)

echo "ECS SG: $ECS_SG"

# ── 1. Create ALB Security Group ───────────────────────────────
echo ""
echo "=== Creating ALB Security Group ==="

ALB_SG=$(aws ec2 create-security-group \
  --region $REGION \
  --group-name graphite-alb-sg \
  --description "Graphite ALB" \
  --vpc-id $VPC_ID \
  --query "GroupId" \
  --output text 2>/dev/null || \
  aws ec2 describe-security-groups \
    --region $REGION \
    --filters "Name=group-name,Values=graphite-alb-sg" "Name=vpc-id,Values=$VPC_ID" \
    --query "SecurityGroups[0].GroupId" \
    --output text)

aws ec2 authorize-security-group-ingress --region $REGION --group-id $ALB_SG \
  --protocol tcp --port 80 --cidr 0.0.0.0/0 2>/dev/null || true
aws ec2 authorize-security-group-ingress --region $REGION --group-id $ALB_SG \
  --protocol tcp --port 443 --cidr 0.0.0.0/0 2>/dev/null || true

# Allow ALB to talk to ECS tasks
aws ec2 authorize-security-group-ingress --region $REGION --group-id $ECS_SG \
  --protocol tcp --port 80 --source-group $ALB_SG 2>/dev/null || true

echo "ALB SG: $ALB_SG"

# ── 2. Create Application Load Balancer ────────────────────────
echo ""
echo "=== Creating ALB ==="

ALB_ARN=$(aws elbv2 create-load-balancer \
  --region $REGION \
  --name graphite-alb \
  --subnets $SUBNET_1 $SUBNET_2 $SUBNET_3 \
  --security-groups $ALB_SG \
  --scheme internet-facing \
  --type application \
  --query "LoadBalancers[0].LoadBalancerArn" \
  --output text)

ALB_DNS=$(aws elbv2 describe-load-balancers \
  --region $REGION \
  --load-balancer-arns $ALB_ARN \
  --query "LoadBalancers[0].DNSName" \
  --output text)

echo "ALB ARN: $ALB_ARN"
echo "ALB DNS: $ALB_DNS"

# ── 3. Create Target Groups ───────────────────────────────────
echo ""
echo "=== Creating Target Groups ==="

BACKEND_TG=$(aws elbv2 create-target-group \
  --region $REGION \
  --name graphite-backend-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id $VPC_ID \
  --target-type ip \
  --health-check-path /api/v1/health \
  --health-check-interval-seconds 30 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3 \
  --query "TargetGroups[0].TargetGroupArn" \
  --output text)

FRONTEND_TG=$(aws elbv2 create-target-group \
  --region $REGION \
  --name graphite-frontend-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id $VPC_ID \
  --target-type ip \
  --health-check-path / \
  --health-check-interval-seconds 30 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3 \
  --query "TargetGroups[0].TargetGroupArn" \
  --output text)

echo "Backend TG: $BACKEND_TG"
echo "Frontend TG: $FRONTEND_TG"

# ── 4. Create ALB Listeners ───────────────────────────────────
echo ""
echo "=== Creating ALB Listeners ==="

# Port 80 → Frontend (default)
aws elbv2 create-listener \
  --region $REGION \
  --load-balancer-arn $ALB_ARN \
  --protocol HTTP \
  --port 80 \
  --default-actions Type=forward,TargetGroupArn=$FRONTEND_TG

# Port 8000 → Backend API
aws elbv2 create-listener \
  --region $REGION \
  --load-balancer-arn $ALB_ARN \
  --protocol HTTP \
  --port 8000 \
  --default-actions Type=forward,TargetGroupArn=$BACKEND_TG

echo "Listeners created: :80 → Frontend, :8000 → Backend"

# ── 5. Update task defs with Account ID ────────────────────────
echo ""
echo "=== Registering Task Definitions ==="

# Update ACCOUNT_ID in task defs
sed "s/ACCOUNT_ID/$ACCOUNT_ID/g" /tmp/backend-task-def.json > /tmp/backend-task-final.json 2>/dev/null || true
sed "s/ACCOUNT_ID/$ACCOUNT_ID/g" /tmp/frontend-task-def.json > /tmp/frontend-task-final.json 2>/dev/null || true

# ── 6. Create ECS Services ────────────────────────────────────
echo ""
echo "=== Creating ECS Services ==="

# Backend service
aws ecs create-service \
  --region $REGION \
  --cluster graphite-cluster \
  --service-name graphite-backend-svc \
  --task-definition graphite-backend \
  --desired-count 2 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_1,$SUBNET_2,$SUBNET_3],securityGroups=[$ECS_SG],assignPublicIp=ENABLED}" \
  --load-balancers "targetGroupArn=$BACKEND_TG,containerName=graphite-backend,containerPort=80" \
  --deployment-configuration "maximumPercent=200,minimumHealthyPercent=100" \
  --enable-execute-command 2>/dev/null || echo "Backend service already exists"

# Frontend service
aws ecs create-service \
  --region $REGION \
  --cluster graphite-cluster \
  --service-name graphite-frontend-svc \
  --task-definition graphite-frontend \
  --desired-count 2 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_1,$SUBNET_2,$SUBNET_3],securityGroups=[$ECS_SG],assignPublicIp=ENABLED}" \
  --load-balancers "targetGroupArn=$FRONTEND_TG,containerName=graphite-frontend,containerPort=80" \
  --deployment-configuration "maximumPercent=200,minimumHealthyPercent=100" 2>/dev/null || echo "Frontend service already exists"

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  DEPLOYMENT COMPLETE!"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "  Frontend: http://$ALB_DNS"
echo "  Backend:  http://$ALB_DNS:8000"
echo "  Health:   http://$ALB_DNS:8000/api/v1/health"
echo ""
echo "  GitHub Secrets to add:"
echo "    AWS_ACCESS_KEY_ID"
echo "    AWS_SECRET_ACCESS_KEY"
echo "    VITE_API_URL = http://$ALB_DNS:8000"
echo ""
echo "═══════════════════════════════════════════════════════════"
