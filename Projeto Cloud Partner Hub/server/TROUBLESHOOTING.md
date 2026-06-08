# 🔧 Guia de Configuração Azure SQL Database

## ❌ Problema: Informações não sendo cadastradas

### Checklist de Configuração:

#### ✅ 1. Firewall do Azure SQL
No Azure Portal:
1. Vá para seu SQL Database: **cloudpartnerhub-db**
2. Menu lateral > **Networking** (ou Security > Firewall)
3. Adicione uma regra:
   - **Nome:** AllowMyIP
   - **Start IP:** Seu IP público (google "meu ip")
   - **End IP:** Mesmo IP
4. Marque: ☑ **Allow Azure services to access server**
5. Clique em **Save**

#### ✅ 2. Escolher Tipo de Autenticação

**Opção A: SQL Authentication (Mais Simples)**
No arquivo **.env**, configure:
```env
USE_AZURE_AD_AUTH=false
DB_USER=seu_usuario_admin
DB_PASSWORD=sua_senha
```

**Opção B: Azure AD Authentication**
1. Execute: `az login` no terminal
2. No **.env**: `USE_AZURE_AD_AUTH=true`
3. Certifique-se de ter permissões no SQL Database

#### ✅ 3. Verificar Credenciais

No Azure Portal > SQL Database > **Settings** > **Connection strings**
Copie a connection string e extraia:
- Server: cloudpartnerhubserver.database.windows.net
- Database: cloudpartnerhub-db
- User: (se usar SQL Auth)

#### ✅ 4. Executar o Schema

Se as tabelas não existem:
1. Portal Azure > SQL Database > **Query editor**
2. Faça login
3. Execute o arquivo: `database/schema-azure-sql.sql`

#### ✅ 5. Testar Conexão

```powershell
cd server
node test-connection.js
```

Se conectar, você verá:
- ✅ Conexão estabelecida
- 📊 Versão do SQL Server
- 📋 Lista de tabelas

### 🐛 Erros Comuns:

| Erro | Solução |
|------|---------|
| **Connection lost** | Firewall bloqueando. Adicione seu IP |
| **Login failed** | Credenciais incorretas no .env |
| **Cannot open server** | Nome do server incorreto |
| **Cannot open database** | Nome do database incorreto |

### 📞 Comandos Úteis:

```powershell
# Ver seu IP público
(Invoke-WebRequest -Uri "https://api.ipify.org").Content

# Testar conexão
node test-connection.js

# Iniciar servidor
node server.js

# Ver logs em tempo real
node server.js | Tee-Object -FilePath server.log
```

### 📋 Checklist Final:

- [ ] Firewall configurado com meu IP
- [ ] .env com credenciais corretas
- [ ] Schema executado (tabelas criadas)
- [ ] test-connection.js executado com sucesso
- [ ] node server.js iniciando sem erros
- [ ] Tentando cadastrar um parceiro no frontend

Se ainda não funcionar, compartilhe o erro específico que aparece!
