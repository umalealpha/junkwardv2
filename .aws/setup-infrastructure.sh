#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# Graphite v2 — AWS Infrastructure Setup (af-south-1)
# Run this in AWS CloudShell or with AWS CLI configured
# ═══════════════════════════════════════════════════════════════

set -e
REGION="af-south-1"
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
VPC_ID="vpc-d7d733be"

echo "Account: $ACCOUNT_ID"
echo "Region: $REGION"
echo "VPC: $VPC_ID"

# ── 1. Create ECR Repositories ─────────────────────────────────
echo ""
echo "=== Creating ECR Repositories ==="

aws ecr create-repository \
  --region $REGION \
  --repository-name graphite-backend \
  --image-scanning-configuration scanOnPush=true \
  --encryption-configuration encryptionType=AES256 2>/dev/null || echo "graphite-backend repo already exists"

aws ecr create-repository \
  --region $REGION \
  --repository-name graphite-frontend \
  --image-scanning-configuration scanOnPush=true \
  --encryption-configuration encryptionType=AES256 2>/dev/null || echo "graphite-frontend repo already exists"

ECR_URI="$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com"
echo "ECR Registry: $ECR_URI"

# ── 2. Create CloudWatch Log Groups ────────────────────────────
echo ""
echo "=== Creating Log Groups ==="

aws logs create-log-group --region $REGION --log-group-name /ecs/graphite-backend 2>/dev/null || true
aws logs create-log-group --region $REGION --log-group-name /ecs/graphite-frontend 2>/dev/null || true

# ── 3. Create ECS Cluster ──────────────────────────────────────
echo ""
echo "=== Creating ECS Cluster ==="

aws ecs create-cluster \
  --region $REGION \
  --cluster-name graphite-cluster \
  --capacity-providers FARGATE FARGATE_SPOT \
  --default-capacity-provider-strategy \
    capacityProvider=FARGATE,weight=1 \
    capacityProvider=FARGATE_SPOT,weight=3

# ── 4. Create IAM Roles ────────────────────────────────────────
echo ""
echo "=== Creating IAM Roles ==="

# ECS Task Execution Role (pulls images, reads secrets)
aws iam create-role \
  --role-name ecsTaskExecutionRole \
  --assume-role-policy-document '{
    "Version":"2012-10-17",
    "Statement":[{
      "Effect":"Allow",
      "Principal":{"Service":"ecs-tasks.amazonaws.com"},
      "Action":"sts:AssumeRole"
    }]
  }' 2>/dev/null || echo "ecsTaskExecutionRole already exists"

aws iam attach-role-policy \
  --role-name ecsTaskExecutionRole \
  --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy

# Allow reading SSM parameters (for secrets)
aws iam put-role-policy \
  --role-name ecsTaskExecutionRole \
  --policy-name SSMReadAccess \
  --policy-document '{
    "Version":"2012-10-17",
    "Statement":[{
      "Effect":"Allow",
      "Action":["ssm:GetParameters","ssm:GetParameter"],
      "Resource":"arn:aws:ssm:'$REGION':'$ACCOUNT_ID':parameter/graphite/*"
    }]
  }'

# ECS Task Role (what the app itself can do)
aws iam create-role \
  --role-name ecsTaskRole \
  --assume-role-policy-document '{
    "Version":"2012-10-17",
    "Statement":[{
      "Effect":"Allow",
      "Principal":{"Service":"ecs-tasks.amazonaws.com"},
      "Action":"sts:AssumeRole"
    }]
  }' 2>/dev/null || echo "ecsTaskRole already exists"

# Allow S3 access for the app
aws iam put-role-policy \
  --role-name ecsTaskRole \
  --policy-name S3Access \
  --policy-document '{
    "Version":"2012-10-17",
    "Statement":[{
      "Effect":"Allow",
      "Action":["s3:GetObject","s3:PutObject","s3:ListBucket"],
      "Resource":["arn:aws:s3:::alphadirect*","arn:aws:s3:::alphadirect*/*"]
    }]
  }'

# ── 5. Store Secrets in SSM Parameter Store ─────────────────────
echo ""
echo "=== Storing Secrets in SSM ==="

aws ssm put-parameter --region $REGION --name /graphite/APP_KEY --value "base64:DLarRcHtyoneJbTz7bhteSPT4oTzlocwnqQ0kmLlWIk=" --type SecureString --overwrite
aws ssm put-parameter --region $REGION --name /graphite/DB_HOST --value "graphite-test-write.c1y5xpqlrcpg.af-south-1.rds.amazonaws.com" --type SecureString --overwrite
aws ssm put-parameter --region $REGION --name /graphite/DB_DATABASE --value "Graphite_live" --type SecureString --overwrite
aws ssm put-parameter --region $REGION --name /graphite/DB_USERNAME --value "graphitebwlive" --type SecureString --overwrite
aws ssm put-parameter --region $REGION --name /graphite/DB_PASSWORD --value '89JJX(aBQ' --type SecureString --overwrite
aws ssm put-parameter --region $REGION --name /graphite/GROQ_API_KEY --value "YOUR_GROQ_KEY_HERE" --type SecureString --overwrite

# ── 6. Create ElastiCache Redis ─────────────────────────────────
echo ""
echo "=== Creating Redis (ElastiCache) ==="

# Get subnets in the VPC
SUBNET_IDS=$(aws ec2 describe-subnets \
  --region $REGION \
  --filters "Name=vpc-id,Values=$VPC_ID" \
  --query "Subnets[*].SubnetId" \
  --output text | tr '\t' ',')

aws elasticache create-cache-subnet-group \
  --region $REGION \
  --cache-subnet-group-name graphite-redis-subnet \
  --cache-subnet-group-description "Graphite Redis subnets" \
  --subnet-ids $(echo $SUBNET_IDS | tr ',' ' ') 2>/dev/null || echo "Subnet group already exists"

# Get the RDS security group to reuse
RDS_SG=$(aws rds describe-db-instances \
  --region $REGION \
  --db-instance-identifier graphite-test-write \
  --query "DBInstances[0].VpcSecurityGroups[0].VpcSecurityGroupId" \
  --output text)

aws elasticache create-cache-cluster \
  --region $REGION \
  --cache-cluster-id graphite-redis \
  --cache-node-type cache.t3.small \
  --engine redis \
  --num-cache-nodes 1 \
  --cache-subnet-group-name graphite-redis-subnet \
  --security-group-ids $RDS_SG 2>/dev/null || echo "Redis cluster already exists"

# Store Redis endpoint
REDIS_HOST=$(aws elasticache describe-cache-clusters \
  --region $REGION \
  --cache-cluster-id graphite-redis \
  --show-cache-node-info \
  --query "CacheClusters[0].CacheNodes[0].Endpoint.Address" \
  --output text 2>/dev/null || echo "pending")

if [ "$REDIS_HOST" != "pending" ] && [ "$REDIS_HOST" != "None" ]; then
  aws ssm put-parameter --region $REGION --name /graphite/REDIS_HOST --value "$REDIS_HOST" --type String --overwrite
  echo "Redis endpoint: $REDIS_HOST"
else
  echo "Redis still creating... run this later to get endpoint:"
  echo "  aws elasticache describe-cache-clusters --region $REGION --cache-cluster-id graphite-redis --show-cache-node-info"
fi

# ── 7. Create Security Group for ECS ───────────────────────────
echo ""
echo "=== Creating ECS Security Group ==="

ECS_SG=$(aws ec2 create-security-group \
  --region $REGION \
  --group-name graphite-ecs-sg \
  --description "Graphite ECS tasks" \
  --vpc-id $VPC_ID \
  --query "GroupId" \
  --output text 2>/dev/null || \
  aws ec2 describe-security-groups \
    --region $REGION \
    --filters "Name=group-name,Values=graphite-ecs-sg" "Name=vpc-id,Values=$VPC_ID" \
    --query "SecurityGroups[0].GroupId" \
    --output text)

# Allow inbound HTTP
aws ec2 authorize-security-group-ingress \
  --region $REGION \
  --group-id $ECS_SG \
  --protocol tcp --port 80 --cidr 0.0.0.0/0 2>/dev/null || true

# Allow ECS to talk to RDS
aws ec2 authorize-security-group-ingress \
  --region $REGION \
  --group-id $RDS_SG \
  --protocol tcp --port 3306 --source-group $ECS_SG 2>/dev/null || true

# Allow ECS to talk to Redis
aws ec2 authorize-security-group-ingress \
  --region $REGION \
  --group-id $RDS_SG \
  --protocol tcp --port 6379 --source-group $ECS_SG 2>/dev/null || true

echo ""
echo "=== Setup Complete ==="
echo ""
echo "ECR Registry:    $ECR_URI"
echo "ECS Cluster:     graphite-cluster"
echo "ECS SG:          $ECS_SG"
echo "RDS SG:          $RDS_SG"
echo ""
echo "=== Next Steps ==="
echo "1. Add these GitHub Secrets:"
echo "   AWS_ACCESS_KEY_ID     = (create an IAM user with ECR+ECS permissions)"
echo "   AWS_SECRET_ACCESS_KEY = (the secret key)"
echo "   VITE_API_URL          = http://YOUR_ALB_DNS_NAME"
echo ""
echo "2. Update task definitions with your Account ID:"
echo "   sed -i 's/ACCOUNT_ID/$ACCOUNT_ID/g' .aws/backend-task-def.json"
echo "   sed -i 's/ACCOUNT_ID/$ACCOUNT_ID/g' .aws/frontend-task-def.json"
echo ""
echo "3. Create an ALB (Application Load Balancer) and ECS services"
echo "   (Run setup-alb-services.sh next)"
