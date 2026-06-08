# Deploy CloudPartner HUB com Azure SQL Database

## 🗄️ Passo 1: Criar Azure SQL Database

```bash
# Login no Azure
az login

# Criar grupo de recursos
az group create --name rg-cloudpartner-hub --location brazilsouth

# Criar SQL Server
az sql server create \
  --resource-group rg-cloudpartner-hub \
  --name cloudpartner-sql-server \
  --location brazilsouth \
  --admin-user sqladmin \
  --admin-password "SuaSenhaForte123!"

# Criar Database
az sql db create \
  --resource-group rg-cloudpartner-hub \
  --server cloudpartner-sql-server \
  --name cloudpartner_hub \
  --service-objective S0 \
  --backup-storage-redundancy Local

# Permitir acesso do Azure Services
az sql server firewall-rule create \
  --resource-group rg-cloudpartner-hub \
  --server cloudpartner-sql-server \
  --name AllowAzureServices \
  --start-ip-address 0.0.0.0 \
  --end-ip-address 0.0.0.0

# Permitir seu IP (para testes locais)
az sql server firewall-rule create \
  --resource-group rg-cloudpartner-hub \
  --server cloudpartner-sql-server \
  --name AllowMyIP \
  --start-ip-address SEU_IP \
  --end-ip-address SEU_IP
```

## 📋 Passo 2: Executar Schema no Banco

1. Acesse o Azure Portal
2. Vá para o SQL Database criado
3. Clique em **Query editor**
4. Faça login com as credenciais do admin
5. Copie e execute o conteúdo de `database/schema-azure-sql.sql`

## 🚀 Passo 3: Criar App Service

```bash
# Criar App Service Plan
az appservice plan create \
  --name plan-cloudpartner \
  --resource-group rg-cloudpartner-hub \
  --sku B1 \
  --is-linux

# Criar Web App
az webapp create \
  --resource-group rg-cloudpartner-hub \
  --plan plan-cloudpartner \
  --name cloudpartner-hub \
  --runtime "NODE:20-lts"
```

## ⚙️ Passo 4: Configurar Variáveis de Ambiente

No Portal Azure > App Service > Configuration > Application settings:

| Nome | Valor |
|------|-------|
| `NODE_ENV` | `production` |
| `DB_HOST` | `cloudpartner-sql-server.database.windows.net` |
| `DB_USER` | `sqladmin` |
| `DB_PASSWORD` | `SuaSenhaForte123!` |
| `DB_NAME` | `cloudpartner_hub` |
| `DB_PORT` | `1433` |

## 📦 Passo 5: Deploy

```powershell
# Na pasta server
cd server

# Criar arquivo ZIP
Compress-Archive -Path * -DestinationPath deploy.zip -Force

# Deploy
az webapp deploy \
  --resource-group rg-cloudpartner-hub \
  --name cloudpartner-hub \
  --src-path deploy.zip \
  --type zip
```

## 💰 Custos Estimados

| Recurso | SKU | Custo/mês |
|---------|-----|-----------|
| SQL Database | S0 (10 DTU) | ~R$ 60 |
| App Service | B1 | ~R$ 70 |
| **Total** | | **~R$ 130/mês** |

## 🧪 Testar

Após deploy, acesse:
- Frontend: `https://cloudpartner-hub.azurewebsites.net`
- API Health: `https://cloudpartner-hub.azurewebsites.net/api/health`
