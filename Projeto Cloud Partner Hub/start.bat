@echo off
echo ========================================
echo   CloudPartner HUB - Iniciando Sistema
echo ========================================
echo.

echo [1/2] Iniciando Backend MySQL...
start "Backend MySQL" cmd /k "cd server && node server.js"
timeout /t 3 /nobreak >nul

echo [2/2] Iniciando Frontend React...
start "Frontend React" cmd /k "npm run dev"
timeout /t 2 /nobreak >nul

echo.
echo ========================================
echo   Sistema Iniciado com Sucesso!
echo ========================================
echo.
echo   Backend:  http://localhost:3001/api
echo   Frontend: http://localhost:5174
echo.
echo   Feche as janelas de terminal para parar
echo ========================================
echo.

pause
