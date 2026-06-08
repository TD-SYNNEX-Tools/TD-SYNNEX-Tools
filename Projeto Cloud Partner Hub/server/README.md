# CloudPartner HUB - Backend MySQL

## 📋 Pré-requisitos

- **Node.js** 18+ instalado
- **MySQL** 8.0+ instalado e rodando
- **npm** ou **yarn**

## 🚀 Setup do Banco de Dados

### 1. Instalar MySQL (se ainda não tem)

**Windows:**
```bash
# Baixar MySQL Installer
https://dev.mysql.com/downloads/installer/

# Ou usar Chocolatey
choco install mysql
```

**Iniciar MySQL:**
```bash
# Via MySQL Workbench ou
net start MySQL80
```

### 2. Criar o Banco de Dados

Abra o MySQL Workbench ou terminal MySQL:

```bash
mysql -u root -p
```

Execute o schema:
```sql
source server/database/schema.sql
```

Ou copie e cole o conteúdo do arquivo `server/database/schema.sql`

### 3. Configurar Variáveis de Ambiente

```bash
cd server
cp .env.example .env
```

Edite o arquivo `.env` com suas credenciais:
```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=sua_senha_mysql
DB_NAME=cloudpartner_hub
DB_PORT=3306

PORT=3001
```

## 🛠️ Instalação e Execução

### Backend (API)

```bash
cd server
npm install
npm run dev
```

A API estará rodando em: `http://localhost:3001`

### Frontend (Vite)

Adicione no `.env` do frontend:
```env
VITE_API_URL=http://localhost:3001/api
```

```bash
npm install
npm run dev
```

## 📡 Endpoints da API

### Partners

- `GET /api/partners` - Listar todos os parceiros
- `GET /api/partners/:id` - Buscar parceiro específico
- `POST /api/partners` - Criar novo parceiro
- `PUT /api/partners/:id` - Atualizar parceiro
- `DELETE /api/partners/:id` - Deletar parceiro

### Health Check

- `GET /api/health` - Status da API

## 🧪 Testar a API

```bash
# Health check
curl http://localhost:3001/api/health

# Listar parceiros
curl http://localhost:3001/api/partners

# Criar parceiro
curl -X POST http://localhost:3001/api/partners \
  -H "Content-Type: application/json" \
  -d '{
    "id": "test123",
    "companyName": "Test Company",
    "email": "test@example.com",
    "currentStep": 1
  }'
```

## 📊 Estrutura do Banco

### Tabelas

1. **partners** - Dados principais dos parceiros
2. **certifications** - Certificações por parceiro
3. **ai_analyses** - Histórico de análises de IA
4. **gtm_activities** - Atividades de Go-to-Market

### Views

- **partner_summary** - Resumo agregado dos parceiros

## 🔧 Troubleshooting

### Erro: "Access denied for user"
```bash
# Resetar senha do MySQL
ALTER USER 'root'@'localhost' IDENTIFIED BY 'nova_senha';
FLUSH PRIVILEGES;
```

### Erro: "Can't connect to MySQL server"
```bash
# Verificar se MySQL está rodando
net start MySQL80

# Verificar porta
netstat -ano | findstr :3306
```

### Erro: "Database doesn't exist"
```bash
# Criar manualmente
CREATE DATABASE cloudpartner_hub;
```

## 📈 Próximos Passos

1. ✅ Backend rodando
2. ✅ Banco de dados criado
3. 🔄 Atualizar frontend para usar API (veja abaixo)
4. 🔐 Adicionar autenticação (JWT)
5. 🤖 Integrar Azure OpenAI

## 🔄 Migrar do localStorage para API

O frontend já tem localStorage. Para migrar:

1. Importar apiService no componente
2. Substituir `localStorage.setItem` por `apiService.updatePartner`
3. Substituir `localStorage.getItem` por `apiService.getPartnerById`

Exemplo:
```typescript
// Antes
localStorage.setItem('cloudpartner_formdata', JSON.stringify(formData));

// Depois
await apiService.updatePartner(formData.id, formData);
```

## 📝 Logs

Os logs aparecem no terminal onde você rodou `npm run dev`:
```
✅ MySQL conectado com sucesso!
🚀 Servidor rodando na porta 3001
```

## 🔒 Segurança

Para produção, adicione:
- Helmet.js (headers de segurança)
- Rate limiting
- JWT authentication
- Input validation (Joi/Yup)
- SQL injection protection (já incluído via prepared statements)
