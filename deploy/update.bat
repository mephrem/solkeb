@echo off
rem ======================================================================================
rem  Solkeb Hotel - install the latest version from GitHub on the hotel PC.
rem  Takes a backup first, then updates the code and the database, then clears caches.
rem  Run it when no one is in the middle of a check-in (it takes about a minute).
rem ======================================================================================
setlocal
cd /d "%~dp0.."

echo 1/5 Backup first...
call "%~dp0backup.bat"

echo 2/5 Getting the latest code...
git pull --ff-only || ( echo Could not update the code. Nothing was changed. & pause & exit /b 1 )

echo 3/5 Libraries...
call composer install --no-dev --optimize-autoloader --no-interaction

echo 4/5 Database changes...
php artisan migrate --force

echo 5/5 Refreshing caches...
php artisan optimize:clear >nul
php artisan optimize

echo.
echo Updated. Ask staff to refresh their browser page.
pause
