@echo off
echo === Redis Installation for Windows ===
echo.

REM Check if Docker is installed
echo Checking Docker installation...
docker --version >nul 2>&1
if errorlevel 1 (
    echo Docker is not installed!
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    echo.
    echo After installing Docker Desktop, run this script again.
    pause
    exit /b 1
)

echo Docker is installed!
echo.

REM Check if Redis container already exists
echo Checking for existing Redis container...
docker ps -a --filter "name=redis-dev" --format "{{.Names}}" | findstr /C:"redis-dev" >nul
if %errorlevel% == 0 (
    echo Redis container 'redis-dev' already exists!
    set /p response="Do you want to remove and recreate it? (y/n): "
    if /i "%response%"=="y" (
        echo Stopping and removing existing container...
        docker stop redis-dev
        docker rm redis-dev
    ) else (
        echo Starting existing container...
        docker start redis-dev
        echo Redis is running on port 6379!
        pause
        exit /b 0
    )
)

REM Pull Redis image
echo Pulling Redis image...
docker pull redis:latest

REM Run Redis container
echo Starting Redis container...
docker run -d --name redis-dev -p 6379:6379 redis:latest

REM Wait a moment
timeout /t 2 /nobreak >nul

REM Verify Redis is running
echo Verifying Redis is running...
docker ps --filter "name=redis-dev" --format "{{.Names}}" | findstr /C:"redis-dev" >nul
if %errorlevel% == 0 (
    echo.
    echo [SUCCESS] Redis is running successfully!
    echo.
    echo Redis Details:
    echo   Container Name: redis-dev
    echo   Port: 6379
    echo   Host: 127.0.0.1
    echo.
    
    REM Test Redis connection
    echo Testing Redis connection...
    docker exec redis-dev redis-cli ping
    echo.
    echo Next Steps:
    echo 1. Update your .env file:
    echo    CACHE_DRIVER=redis
    echo    REDIS_HOST=127.0.0.1
    echo    REDIS_PORT=6379
    echo.
    echo 2. Run: php artisan config:clear
    echo 3. Run: php artisan cache:clear
    echo.
    echo Useful Commands:
    echo   Start Redis: docker start redis-dev
    echo   Stop Redis: docker stop redis-dev
    echo   View Logs: docker logs redis-dev
    echo   Redis CLI: docker exec -it redis-dev redis-cli
) else (
    echo [ERROR] Failed to start Redis container!
    echo Check Docker Desktop is running and try again.
    pause
    exit /b 1
)

pause
