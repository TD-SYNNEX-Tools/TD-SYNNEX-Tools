@echo off
echo ========================================
echo  ArcCalculator - Teste Completo
echo ========================================
echo.
echo Verificando arquivos...
echo.

cd /d "%~dp0"

if exist "public\assets\css\style.css" (
    echo [OK] CSS encontrado
) else (
    echo [ERRO] CSS nao encontrado!
)

if exist "public\assets\images\logo.png" (
    echo [OK] Logo encontrada
) else (
    echo [ERRO] Logo nao encontrada!
)

if exist "src\Core\Router.php" (
    echo [OK] Router encontrado
) else (
    echo [ERRO] Router nao encontrado!
)

if exist "config\routes.php" (
    echo [OK] Rotas encontradas
) else (
    echo [ERRO] Rotas nao encontradas!
)

echo.
echo ========================================
echo  Iniciando servidor na porta 8000...
echo ========================================
echo.
echo Acesse as paginas:
echo.
echo  Home:                http://localhost:8000/
echo  Azure Migration:     http://localhost:8000/azure-migration
echo  Analise Tecnica:     http://localhost:8000/azure-migration/technical-analysis
echo  Analise Financeira:  http://localhost:8000/azure-migration/financial-analysis
echo  SQL Advisor:         http://localhost:8000/sql-advisor
echo  CSP Pricing:         http://localhost:8000/csp-pricing/comparison
echo  M365 Migration:      http://localhost:8000/m365-migration
echo.
echo TODAS as paginas devem estar COM CSS funcionando!
echo.
echo Pressione Ctrl+C para parar o servidor
echo ========================================
echo.

php -c php.ini -S localhost:8000 -t public router.php
