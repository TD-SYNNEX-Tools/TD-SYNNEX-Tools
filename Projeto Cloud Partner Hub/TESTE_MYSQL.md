# ✅ Guia de Teste - Integração MySQL

## 🎯 Sistema Rodando

### Backend (MySQL API)
- ✅ Servidor: http://localhost:3001
- ✅ Health Check: http://localhost:3001/api/health
- ✅ MySQL conectado

### Frontend (React + Vite)
- ✅ Aplicação: http://localhost:5174
- ✅ Integrado com API MySQL
- ✅ Fallback para localStorage (se API falhar)

---

## 📝 Como Testar

### 1️⃣ Verificar Conexão com Banco
```powershell
# Testar API
Invoke-WebRequest http://localhost:3001/api/health
# Deve retornar: {"status":"ok","message":"CloudPartner HUB API rodando"}
```

### 2️⃣ Testar Fluxo Completo

1. **Abra a aplicação**: http://localhost:5174

2. **Preencha o Wizard (Passo 2-6)**:
   - Passo 2: Nome da empresa, contato, email
   - Passo 3: Tipo de parceiro, Solution Area
   - Passo 4: Certificações
   - Passo 5: Pontuação PCS
   - Passo 6: Conclusão

3. **Observe os indicadores**:
   - ✅ "Salvando..." aparece ao preencher dados
   - ✅ Dados persistem ao recarregar página
   - ❌ Se houver erro, aparece mensagem vermelha

### 3️⃣ Verificar Dados no MySQL

```powershell
# Abrir MySQL
mysql -u root -p

# Ver parceiros salvos
USE cloudpartner_hub;
SELECT id, companyName, email, selectedSolutionArea FROM partners;

# Ver certificações
SELECT * FROM certifications;

# Ver resumo agregado
SELECT * FROM partner_summary;
```

### 4️⃣ Testar API Diretamente

```powershell
# Listar todos os parceiros
Invoke-WebRequest -Uri "http://localhost:3001/api/partners" | Select-Object -ExpandProperty Content

# Ver parceiro específico (substitua ID)
Invoke-WebRequest -Uri "http://localhost:3001/api/partners/1" | Select-Object -ExpandProperty Content

# Criar parceiro via API
$body = @{
    companyName = "Empresa Teste API"
    email = "teste-api@empresa.com"
    selectedSolutionArea = "Azure Infra"
} | ConvertTo-Json

Invoke-WebRequest -Method POST -Uri "http://localhost:3001/api/partners" `
    -Body $body -ContentType "application/json" | Select-Object -ExpandProperty Content
```

---

## 🔍 Monitorar Logs

### Backend (Terminal Server)
```
Ver requisições em tempo real:
- POST /api/partners (criar/atualizar)
- GET /api/partners (listar)
- GET /api/partners/:id (buscar)
```

### Frontend (Console do Navegador)
```
F12 > Console
- Ver erros de conexão
- Ver requisições fetch()
- Ver dados salvos
```

---

## ✨ Funcionalidades Implementadas

### ✅ Auto-Save
- Dados salvos automaticamente ao preencher
- Sem necessidade de botão "Salvar"
- Feedback visual: "Salvando..."

### ✅ Persistência Robusta
- **Primary**: MySQL (centralizado)
- **Fallback**: localStorage (modo offline)
- Sincronização automática

### ✅ Tratamento de Erros
- Exibe mensagem se API não responder
- Continua funcionando com localStorage
- Reconecta automaticamente quando possível

### ✅ Multi-usuário
- Vários usuários podem acessar simultaneamente
- Dados compartilhados no banco
- Admin vê todos os parceiros

---

## 🐛 Troubleshooting

### ❌ "Erro ao salvar: Network Error"
**Causa**: Backend não está rodando
**Solução**:
```powershell
cd "Projeto Cloud Partner Hub\server"
node server.js
```

### ❌ "MySQL connect error"
**Causa**: Credenciais erradas ou MySQL parado
**Solução**:
1. Verificar `server/.env`
2. Iniciar MySQL: `net start MySQL80`

### ❌ "CORS error no console"
**Causa**: Frontend/Backend em portas diferentes
**Solução**: Já configurado! Verifique se ambos estão rodando

### ❌ Dados não aparecem no Admin
**Causa**: Parceiros criados antes da integração (ainda no localStorage)
**Solução**: Preencher novo parceiro pelo wizard

---

## 📊 Queries Úteis

```sql
-- Ver total de parceiros por Solution Area
SELECT 
    selectedSolutionArea,
    COUNT(*) as total,
    AVG(pcsPerformance + pcsSkilling + pcsCustomerSuccess) as avg_pcs
FROM partners
GROUP BY selectedSolutionArea;

-- Parceiros que atingiram 70 pontos
SELECT companyName, 
       (pcsPerformance + pcsSkilling + pcsCustomerSuccess) as total_pcs
FROM partners
WHERE (pcsPerformance + pcsSkilling + pcsCustomerSuccess) >= 70;

-- Últimos 5 parceiros cadastrados
SELECT companyName, email, createdAt 
FROM partners 
ORDER BY createdAt DESC 
LIMIT 5;

-- Certificações mais comuns
SELECT certificationName, SUM(quantity) as total
FROM certifications
GROUP BY certificationName
ORDER BY total DESC;
```

---

## 🚀 Próximos Passos

### Opcional: Migrar dados antigos
Se você tem parceiros salvos no localStorage:
1. Abra Console (F12)
2. Execute:
```javascript
// Ver dados antigos
const old = localStorage.getItem('cloudpartner_formdata');
console.log(JSON.parse(old));

// Migrar para MySQL (reload página após preencher wizard)
```

### Deployment (Produção)
1. **Backend**: Azure App Service ou Heroku
2. **MySQL**: Azure Database for MySQL
3. **Frontend**: Vercel, Netlify ou Azure Static Web Apps
4. **Env vars**: Configurar URLs de produção

---

## ✅ Checklist de Validação

- [ ] Backend responde em http://localhost:3001/api/health
- [ ] Frontend carrega em http://localhost:5174
- [ ] Preencher wizard até Step 6
- [ ] Ver "Salvando..." ao preencher campos
- [ ] Recarregar página e dados permanecem
- [ ] MySQL tem registros: `SELECT * FROM partners;`
- [ ] AdminPage lista parceiros salvos
- [ ] Sem erros no console do navegador

---

🎉 **Integração concluída com sucesso!**

Suas informações agora estão sendo salvas no MySQL em tempo real.
