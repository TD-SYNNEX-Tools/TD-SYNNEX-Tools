@echo off
echo ========================================
echo   CloudPartner HUB - Build para Azure
echo ========================================
echo.

echo [1/3] Limpando build anterior...
if exist "server\public" rmdir /s /q "server\public"

echo [2/3] Buildando Frontend (React + Vite)...
call npm run build
if %ERRORLEVEL% neq 0 (
    echo ERRO: Falha no build do frontend!
    pause
    exit /b 1
)

echo [3/3] Movendo arquivos para pasta do servidor...
move dist server\public

echo.
echo ========================================
echo   Build Concluido!
echo ========================================
echo.
echo   Pasta pronta para deploy: server\
echo.
echo   Proximo passo: Fazer deploy da pasta 'server' no Azure
echo.
pause
