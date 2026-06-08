# 📋 Como Executar o Schema no Azure SQL Database

## Opção 1: Via Azure Portal (Recomendado)

1. Acesse o Portal Azure: https://portal.azure.com
2. Navegue até seu SQL Database
3. Clique em **Query editor** no menu lateral
4. Faça login com suas credenciais
5. Copie e cole o conteúdo de **schema-azure-sql.sql**
6. Clique em **Run** (executar)

## Opção 2: Via Azure Data Studio

1. Baixe o Azure Data Studio: https://aka.ms/azuredatastudio
2. Conecte ao seu Azure SQL Database
3. Abra o arquivo **schema-azure-sql.sql**
4. Execute o script (F5)

## Opção 3: Via SQL Server Management Studio (SSMS)

1. Baixe o SSMS: https://aka.ms/ssmsfullsetup
2. Conecte ao servidor:
   - Server: seu-servidor.database.windows.net
   - Authentication: SQL Server Authentication
   - Login: seu_admin
   - Password: sua_senha
3. Abra **schema-azure-sql.sql**
4. Execute (F5)

## ✅ Verificar se as Tabelas Foram Criadas

Execute no Query Editor:

```sql
-- Listar todas as tabelas
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

-- Verificar estrutura da tabela partners
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'partners'
ORDER BY ORDINAL_POSITION;
```

## 🔧 String de Conexão

Para sua aplicação (.env):

```
DB_HOST=seu-servidor.database.windows.net
DB_USER=seu_admin
DB_PASSWORD=SuaSenha123!
DB_NAME=cloudpartner_hub
DB_PORT=1433
```

## 📊 Tabelas Criadas

| Tabela | Descrição |
|--------|-----------|
| **partners** | Dados dos parceiros |
| **certifications** | Certificações Microsoft |
| **ai_analyses** | Análises de IA geradas |
| **gtm_activities** | Atividades Go-to-Market |
| **partner_summary** | View com resumo agregado |

## 🚀 Próximo Passo

Após criar as tabelas, atualize o arquivo **.env** com suas credenciais e teste a conexão:

```powershell
cd server
node server.js
```

Se a conexão for bem-sucedida, você verá:
✅ Azure SQL Database conectado com sucesso!
