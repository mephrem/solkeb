@echo off
rem ======================================================================================
rem  Solkeb Hotel - make the system start automatically when the PC boots
rem  (no one needs to log in or open Laragon).
rem
rem  - makes the hotel system the default site, so other computers open http://THIS-PC-IP
rem  - registers Laragon's MySQL and Apache as Windows services that start at boot
rem  - opens port 80 on the PRIVATE network for the other hotel computers and phones
rem  - schedules the nightly backup (deploy\backup.bat) at 23:30
rem
rem  Run ONCE on the hotel PC: right-click this file > "Run as administrator".
rem  Before running: in Laragon click "Stop All" (the ports must be free).
rem ======================================================================================
setlocal

net session >nul 2>&1
if errorlevel 1 (
  echo Please right-click this file and choose "Run as administrator".
  pause & exit /b 1
)

set "LARAGON=C:\laragon"
pushd "%~dp0.." & set "APP=%CD%" & popd
set "APPF=%APP:\=/%"

rem --- Find Laragon's MySQL 8 and Apache folders (the newest one of each) ---
set "MYSQL="
for /d %%D in ("%LARAGON%\bin\mysql\mysql-8*") do set "MYSQL=%%D"
set "APACHE="
for /d %%D in ("%LARAGON%\bin\apache\httpd-*") do set "APACHE=%%D"

if not defined MYSQL  ( echo MySQL 8 was not found in %LARAGON%\bin\mysql.  & pause & exit /b 1 )
if not defined APACHE ( echo Apache was not found in %LARAGON%\bin\apache. & pause & exit /b 1 )
if not exist "%MYSQL%\my.ini" (
  echo %MYSQL%\my.ini is missing. Open Laragon, click "Start All", then "Stop All", and run this again.
  pause & exit /b 1
)
echo App:    %APP%
echo MySQL:  %MYSQL%
echo Apache: %APACHE%
echo.

rem --- The hotel system as the default site (loaded before Laragon's own default) ---
set "SITE=%LARAGON%\etc\apache2\sites-enabled\0-solkeb-hotel.conf"
(
  echo # Solkeb Hotel: default site for this PC's address. Written by deploy\install-services.bat
  echo ^<VirtualHost *:80^>
  echo     DocumentRoot "%APPF%/public"
  echo     ^<Directory "%APPF%/public"^>
  echo         AllowOverride All
  echo         Require all granted
  echo     ^</Directory^>
  echo ^</VirtualHost^>
) > "%SITE%"
"%APACHE%\bin\httpd.exe" -t
if errorlevel 1 ( echo The Apache configuration has an error, see above. & pause & exit /b 1 )

rem --- MySQL as a service ---
sc query SolkebMySQL >nul 2>&1
if errorlevel 1 (
  "%MYSQL%\bin\mysqld.exe" --install SolkebMySQL --defaults-file="%MYSQL%\my.ini"
) else ( echo SolkebMySQL service already exists. )
sc config SolkebMySQL start= auto >nul
sc failure SolkebMySQL reset= 86400 actions= restart/60000/restart/60000/restart/60000 >nul

rem --- Apache as a service (Laragon's configuration, including the site above) ---
sc query SolkebApache >nul 2>&1
if errorlevel 1 (
  "%APACHE%\bin\httpd.exe" -k install -n "SolkebApache"
) else ( echo SolkebApache service already exists. )
sc config SolkebApache start= auto >nul
sc failure SolkebApache reset= 86400 actions= restart/60000/restart/60000/restart/60000 >nul

net start SolkebMySQL
net start SolkebApache

rem --- Let the other computers / phones on the hotel network open the system ---
netsh advfirewall firewall show rule name="Solkeb Hotel (web)" >nul 2>&1
if errorlevel 1 (
  netsh advfirewall firewall add rule name="Solkeb Hotel (web)" dir=in action=allow protocol=TCP localport=80 profile=private
)

rem --- Nightly backup at 23:30 ---
schtasks /create /f /tn "Solkeb Hotel backup" /tr "\"%APP%\deploy\backup.bat\"" /sc daily /st 23:30 /ru SYSTEM >nul

echo.
echo Done. The system now starts by itself whenever this PC is switched on.
echo From now on do NOT click "Start All" in Laragon (the services are already running).
echo Test: open http://localhost on this PC, then http://THIS-PC-IP from another computer.
pause
