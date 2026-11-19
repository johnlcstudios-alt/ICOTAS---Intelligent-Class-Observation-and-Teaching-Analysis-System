@echo off
:: ============================================================================
:: ICOTAS - One-Click Setup & Start Script
:: Intelligent Class Observation & Teaching Analytics System
:: Institution: ACLC College Tacloban City
:: ============================================================================

setlocal enabledelayedexpansion

:: ----------------------------------------------------------------------------
:: Configuration
:: ----------------------------------------------------------------------------
set "PHP_EXE=C:\xampp\php\php.exe"
set "MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
set "APP_ROOT=%~dp0"
set "SERVER_PORT=8090"
set "SERVER_URL=http://127.0.0.1:%SERVER_PORT%"
set "DB_NAME=icotas"
set "DB_USER=root"
set "DB_PASS="

:: ----------------------------------------------------------------------------
:: Helper: print colored messages
:: ----------------------------------------------------------------------------
set "COLOR_OK=02"
set "COLOR_WARN=06"
set "COLOR_ERR=04"
set "COLOR_INFO=03"

echo.
echo ================================================================================
echo   ICOTAS - Intelligent Class Observation & Teaching Analytics System
echo   ACLC College Tacloban City
echo ================================================================================
echo.

:: ----------------------------------------------------------------------------
:: Step 0: Check PHP
:: ----------------------------------------------------------------------------
echo [1/6] Checking PHP...
if not exist "%PHP_EXE%" (
    echo   [ERROR] PHP not found at %PHP_EXE%
    echo   Please install XAMPP or PHP and update PHP_EXE in this script.
    goto :pause
)
for /f "tokens=*" %%v in ('"%PHP_EXE%" -v 2^>nul') do set "PHP_VER=%%v"
echo   [OK] %PHP_VER%

:: ----------------------------------------------------------------------------
:: Step 1: Check MySQL
:: ----------------------------------------------------------------------------
echo [2/6] Checking MySQL...
if not exist "%MYSQL_EXE%" (
    echo   [ERROR] MySQL not found at %MYSQL_EXE%
    echo   Please install XAMPP or MySQL and update MYSQL_EXE in this script.
    goto :pause
)
for /f "tokens=*" %%v in ('"%MYSQL_EXE%" --version 2^>nul') do set "MYSQL_VER=%%v"
echo   [OK] %MYSQL_VER%

:: ----------------------------------------------------------------------------
:: Step 2: Create database
:: ----------------------------------------------------------------------------
echo [3/6] Creating database "%DB_NAME%"...
"%MYSQL_EXE%" -u %DB_USER% -e "CREATE DATABASE IF NOT EXISTS `%DB_NAME%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if %ERRORLEVEL% equ 0 (
    echo   [OK] Database "%DB_NAME%" is ready.
) else (
    echo   [WARN] Could not create database automatically.
    echo   Please create it manually:
    echo       "%MYSQL_EXE%" -u %DB_USER% -e "CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo   Then press any key to continue anyway...
    pause >nul
)

:: ----------------------------------------------------------------------------
:: Step 3: Check config/local.php
:: ----------------------------------------------------------------------------
echo [4/6] Checking configuration...
if not exist "%APP_ROOT%config\local.php" (
    echo   [WARN] config/local.php not found. The installer will create it.
    echo   Run the installer at %SERVER_URL%/install.php after startup.
) else (
    echo   [OK] config/local.php found.
)

:: ----------------------------------------------------------------------------
:: Step 4: Start the server
:: ----------------------------------------------------------------------------
echo [5/6] Starting ICOTAS server on %SERVER_URL%...
cd /d "%APP_ROOT%"
start "ICOTAS Server" /min "%PHP_EXE%" -S 127.0.0.1:%SERVER_PORT% tools/dev-router.php

:: Give the server a moment to start
timeout /t 3 >nul

:: ----------------------------------------------------------------------------
:: Step 5: Open browser
:: ----------------------------------------------------------------------------
echo [6/6] Opening browser...
echo.
echo ================================================================================
echo   ICOTAS is starting!
echo ================================================================================
echo.
echo   Server URL:  %SERVER_URL%
echo   Installer:   %SERVER_URL%/install.php
echo   Login:       %SERVER_URL%/login
echo.
echo   The PHP server window is minimized in the taskbar.
echo   Do not close it while using ICOTAS.
echo.
echo   After installation, sign in with the Dean account you created.
echo   Demo credentials (if seeded): Demo@12345 for all accounts.
echo.
echo   To stop ICOTAS, close the "ICOTAS Server" window or press Ctrl+C in it.
echo ================================================================================
echo.

:: Open the installer in the default browser
start "" "%SERVER_URL%/install.php"

goto :end

:pause
echo.
echo Press any key to exit...
pause >nul

:end
endlocal
