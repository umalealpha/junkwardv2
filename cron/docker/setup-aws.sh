#!/bin/bash
# ============================================================
# setup-aws.sh — One-time AWS infrastructure for graphite-cron
# Run this ONCE from a machine with AWS CLI configured.
#
# Creates:
#   - ALB Target Group (graphite-cron-tg) on port 80
#   - ALB Listener Rule for graphite-v2-cron.alphadirect.co.bw
#   - ECS Task Definition update (adds port 80 mapping)
#   - ECS Service update (attaches to ALB)
#
# Pre-requisites:
#   - Existing ALB ARN (same one used by backend/frontend)
#   - Existing ECS Cluster: graphite-cluster
#   - Existing ECS Service: graphite-cron (may be running without ALB)
#   - Existing VPC + Subnets from prior services
# ============================================================

set -euo pipefail

REGION="af-south-1"
CLUSTER="graphite-cluster"
SERVICE="graphite-cron"
ECR="623467544388.dkr.ecr.af-south-1.amazonaws.com"
DOMAIN="graphite-v2-cron.alphadirect.co.bw"

# ── Fetch existing ALB ARN (shared with backend/frontend) ──
echo "Fetching existing ALB ARN..."
ALB_ARN=$(aws elbv2 describe-load-balancers \
  --region $REGION \
  --query "LoadBalancers[?contains(LoadBalancerName,'graphite')].LoadBalancerArn | [0]" \
  --output text)
echo "ALB: $ALB_ARN"

# Fetch VPC ID from ALB
VPC_ID=$(aws elbv2 describe-load-balancers \
  --load-balancer-arns $ALB_ARN --region $REGION \
  --query "LoadBalancers[0].VpcId" --output text)
echo "VPC: $VPC_ID"

# ── Create Target Group ────────────────────────────────────
echo "Creating target group graphite-cron-tg..."
TG_ARN=$(aws elbv2 create-target-group \
  --region $REGION \
  --name "graphite-cron-tg" \
  --protocol HTTP \
  --port 80 \
  --vpc-id $VPC_ID \
  --target-type ip \
  --health-check-protocol HTTP \
  --health-check-path "/health" \
  --health-check-interval-seconds 30 \
  --health-check-timeout-seconds 10 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3 \
  --matcher "HttpCode=200" \
  --query "TargetGroups[0].TargetGroupArn" \
  --output text 2>/dev/null || \
  aws elbv2 describe-target-groups \
    --region $REGION --names "graphite-cron-tg" \
    --query "TargetGroups[0].TargetGroupArn" --output text)
echo "Target Group: $TG_ARN"

# ── Get HTTPS listener (port 443) ─────────────────────────
echo "Fetching HTTPS listener..."
LISTENER_ARN=$(aws elbv2 describe-listeners \
  --load-balancer-arn $ALB_ARN --region $REGION \
  --query "Listeners[?Port==\`443\`].ListenerArn | [0]" \
  --output text)
echo "Listener: $LISTENER_ARN"

# ── Add listener rule for cron domain ─────────────────────
echo "Adding host-header rule for $DOMAIN..."
aws elbv2 create-rule \
  --region $REGION \
  --listener-arn $LISTENER_ARN \
  --priority 30 \
  --conditions "Field=host-header,Values=[\"$DOMAIN\"]" \
  --actions "Type=forward,TargetGroupArn=$TG_ARN" \
  --output text || echo "Rule may already exist — continuing."

# ── Fetch current task definition ─────────────────────────
echo "Fetching current task definition for $SERVICE..."
TASK_DEF=$(aws ecs describe-services \
  --cluster $CLUSTER --services $SERVICE --region $REGION \
  --query "services[0].taskDefinition" --output text)
echo "Current task def: $TASK_DEF"

TASK_DEF_DETAIL=$(aws ecs describe-task-definition \
  --task-definition $TASK_DEF --region $REGION \
  --query "taskDefinition")

# Extract family
FAMILY=$(echo $TASK_DEF_DETAIL | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['family'])")

# ── Register new task def with port 80 exposed ────────────
echo "Registering new task definition with port 80..."
NEW_TASK_DEF=$(echo $TASK_DEF_DETAIL | python3 -c "
import sys, json
d = json.load(sys.stdin)
# Add portMappings if not present
for c in d.get('containerDefinitions', []):
    ports = c.get('portMappings', [])
    if not any(p.get('containerPort') == 80 for p in ports):
        ports.append({'containerPort': 80, 'protocol': 'tcp'})
        c['portMappings'] = ports

# Keep only fields needed for registration
keep = ['family','containerDefinitions','executionRoleArn','taskRoleArn',
        'networkMode','requiresCompatibilities','cpu','memory','volumes']
out = {k: d[k] for k in keep if k in d}
print(json.dumps(out))
")

NEW_TASK_ARN=$(aws ecs register-task-definition \
  --region $REGION \
  --cli-input-json "$NEW_TASK_DEF" \
  --query "taskDefinition.taskDefinitionArn" \
  --output text)
echo "New task def: $NEW_TASK_ARN"

# ── Get subnets and security groups from existing service ──
SVC_DETAIL=$(aws ecs describe-services \
  --cluster $CLUSTER --services $SERVICE --region $REGION \
  --query "services[0]")

SUBNETS=$(echo $SVC_DETAIL | python3 -c "
import sys,json; d=json.load(sys.stdin)
print(','.join(d['networkConfiguration']['awsvpcConfiguration']['subnets']))
")
SGS=$(echo $SVC_DETAIL | python3 -c "
import sys,json; d=json.load(sys.stdin)
print(','.join(d['networkConfiguration']['awsvpcConfiguration']['securityGroups']))
")
echo "Subnets: $SUBNETS"
echo "Security Groups: $SGS"

# ── Update ECS service to attach ALB ──────────────────────
echo "Updating ECS service to attach to ALB target group..."
aws ecs update-service \
  --region $REGION \
  --cluster $CLUSTER \
  --service $SERVICE \
  --task-definition $NEW_TASK_ARN \
  --load-balancers "targetGroupArn=$TG_ARN,containerName=$(echo $TASK_DEF_DETAIL | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['containerDefinitions'][0]['name'])" ),containerPort=80" \
  --desired-count 1 \
  --force-new-deployment \
  --query "service.serviceName" \
  --output text

echo ""
echo "============================================"
echo "Setup complete!"
echo "  Target Group: $TG_ARN"
echo "  Domain:       https://$DOMAIN"
echo ""
echo "DNS: Point CNAME $DOMAIN → ALB DNS name:"
aws elbv2 describe-load-balancers \
  --load-balancer-arns $ALB_ARN --region $REGION \
  --query "LoadBalancers[0].DNSName" --output text
echo "============================================"
