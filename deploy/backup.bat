@echo off
rem ======================================================================================
rem  Solkeb Hotel - backup of the database and the guests' ID photos.
rem  Scheduled nightly by install-services.bat; can also be double-clicked any time.
rem
rem  BACKUP_DIR: put it on ANOTHER drive (second disk, USB disk, network folder) so a
rem  broken disk does not take the backups with it. Keeps the last 30 days.
rem ======================================================================================
setlocal
set "BACKUP_DIR=D:\SolkebBackups"
set "APP=%~dp0.."
set "LARAGON=C:\laragon"
set "DB=solkeb"
set "KEEP_DAYS=30"

rem --- Database name, user and password from the app's .env file ---
for /f "usebackq tokens=1,* delims==" %%A in ("%APP%\.env") do (
  if "%%A"=="DB_DATABASE" set "DB=%%~B"
  if "%%A"=="DB_USERNAME" set "DBUSER=%%~B"
  if "%%A"=="DB_PASSWORD" set "DBPASS=%%~B"
)
if not defined DBUSER set "DBUSER=root"

set "MYSQL="
for /d %%D in ("%LARAGON%\bin\mysql\mysql-8*") do if exist "%%D\my.ini" set "MYSQL=%%D"

for /f %%T in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set "STAMP=%%T"
set "OUT=%BACKUP_DIR%\%STAMP%"
mkdir "%OUT%" 2>nul

rem --- 1. Database ---
if defined DBPASS (
  "%MYSQL%\bin\mysqldump.exe" -u "%DBUSER%" -p"%DBPASS%" --single-transaction --routines "%DB%" > "%OUT%\%DB%.sql"
) else (
  "%MYSQL%\bin\mysqldump.exe" -u "%DBUSER%" --single-transaction --routines "%DB%" > "%OUT%\%DB%.sql"
)

rem --- 2. Guests' ID photos (kept privately, not in the database) ---
robocopy "%APP%\storage\app\private" "%OUT%\id-photos" /E /NFL /NDL /NJH /NJS >nul

rem --- 3. Settings file (database password, app key) ---
copy /y "%APP%\.env" "%OUT%\env.txt" >nul

rem --- Remove backups older than KEEP_DAYS ---
forfiles /p "%BACKUP_DIR%" /d -%KEEP_DAYS% /c "cmd /c if @isdir==TRUE rmdir /s /q @path" 2>nul

echo Backup saved in %OUT%
