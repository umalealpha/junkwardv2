# Redis Installation Script for Windows
# This script helps install Redis on Windows using Docker

Write-Host "=== Redis Installation for Windows ===" -ForegroundColor Green
Write-Host ""

# Check if Docker is installed
Write-Host "Checking Docker installation..." -ForegroundColor Yellow
$dockerInstalled = Get-Command docker -ErrorAction SilentlyContinue

if (-not $dockerInstalled) {
    Write-Host "Docker is not installed!" -ForegroundColor Red
    Write-Host "Please install Docker Desktop from: https://www.docker.com/products/docker-desktop" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "After installing Docker Desktop, run this script again." -ForegroundColor Yellow
    exit 1
}

Write-Host "Docker is installed!" -ForegroundColor Green
Write-Host ""

# Check if Redis container already exists
Write-Host "Checking for existing Redis container..." -ForegroundColor Yellow
$redisContainer = docker ps -a --filter "name=redis-dev" --format "{{.Names}}"

if ($redisContainer -eq "redis-dev") {
    Write-Host "Redis container 'redis-dev' already exists!" -ForegroundColor Yellow
    $response = Read-Host "Do you want to remove and recreate it? (y/n)"
    
    if ($response -eq "y" -or $response -eq "Y") {
        Write-Host "Stopping and removing existing container..." -ForegroundColor Yellow
        docker stop redis-dev
        docker rm redis-dev
    } else {
        Write-Host "Starting existing container..." -ForegroundColor Yellow
        docker start redis-dev
        Write-Host "Redis is running on port 6379!" -ForegroundColor Green
        exit 0
    }
}

# Pull Redis image
Write-Host "Pulling Redis image..." -ForegroundColor Yellow
docker pull redis:latest

# Run Redis container
Write-Host "Starting Redis container..." -ForegroundColor Yellow
docker run -d --name redis-dev -p 6379:6379 redis:latest

# Wait a moment for container to start
Start-Sleep -Seconds 2

# Verify Redis is running
Write-Host "Verifying Redis is running..." -ForegroundColor Yellow
$redisRunning = docker ps --filter "name=redis-dev" --format "{{.Names}}"

if ($redisRunning -eq "redis-dev") {
    Write-Host ""
    Write-Host "✅ Redis is running successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Redis Details:" -ForegroundColor Cyan
    Write-Host "  Container Name: redis-dev" -ForegroundColor White
    Write-Host "  Port: 6379" -ForegroundColor White
    Write-Host "  Host: 127.0.0.1" -ForegroundColor White
    Write-Host ""
    
    # Test Redis connection
    Write-Host "Testing Redis connection..." -ForegroundColor Yellow
    $testResult = docker exec redis-dev redis-cli ping
    
    if ($testResult -eq "PONG") {
        Write-Host "✅ Redis connection test successful!" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Connection test failed, but container is running" -ForegroundColor Yellow
    }
    
    Write-Host ""
    Write-Host "Next Steps:" -ForegroundColor Cyan
    Write-Host "1. Update your .env file:" -ForegroundColor White
    Write-Host "   CACHE_DRIVER=redis" -ForegroundColor Gray
    Write-Host "   REDIS_HOST=127.0.0.1" -ForegroundColor Gray
    Write-Host "   REDIS_PORT=6379" -ForegroundColor Gray
    Write-Host ""
    Write-Host "2. Run: php artisan config:clear" -ForegroundColor White
    Write-Host "3. Run: php artisan cache:clear" -ForegroundColor White
    Write-Host ""
    Write-Host "Useful Commands:" -ForegroundColor Cyan
    Write-Host "  Start Redis: docker start redis-dev" -ForegroundColor Gray
    Write-Host "  Stop Redis: docker stop redis-dev" -ForegroundColor Gray
    Write-Host "  View Logs: docker logs redis-dev" -ForegroundColor Gray
    Write-Host "  Redis CLI: docker exec -it redis-dev redis-cli" -ForegroundColor Gray
    
} else {
    Write-Host "❌ Failed to start Redis container!" -ForegroundColor Red
    Write-Host "Check Docker Desktop is running and try again." -ForegroundColor Yellow
    exit 1
}
