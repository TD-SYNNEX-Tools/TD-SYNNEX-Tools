@echo off
echo ========================================
echo  ArcCalculator - Teste de Organizacao
echo ========================================
echo.
echo Iniciando servidor PHP na porta 8000...
echo.
echo Acesse:
echo  - Home: http://localhost:8000/
echo  - Azure Migration: http://localhost:8000/azure-migration
echo  - SQL Advisor: http://localhost:8000/sql-advisor
echo.
echo Pressione Ctrl+C para parar o servidor
echo ========================================
echo.

cd /d "%~dp0"
php -c php.ini -S localhost:8000 -t public router.php
