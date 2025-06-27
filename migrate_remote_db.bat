@echo off
echo ========================================
echo Script de migration de la base de donnees
echo Correction: employee_id vers user_id
echo ========================================
echo.

echo ATTENTION: Ce script va modifier la base de donnees distante!
echo Assurez-vous d'avoir une sauvegarde avant de continuer.
echo.
set /p confirm="Voulez-vous continuer? (o/N): "
if /i not "%confirm%"=="o" (
    echo Migration annulee.
    pause
    exit /b
)

echo.
echo Execution de la migration...
echo.

cd /d "%~dp0"
php database\migrate_database.php

echo.
echo Migration terminee.
echo.
echo Vous pouvez maintenant tester reports.php:
echo - Local: http://localhost:80/panel/reports.php
echo - Distant: https://yellow-jack.wstr.fr/panel/reports.php
echo.
pause