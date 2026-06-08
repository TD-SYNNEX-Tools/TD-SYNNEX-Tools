# 🗄️ Integração MySQL - Guia Rápido

## ✅ O que foi criado:

### Backend (Node.js + Express + MySQL)
- ✅ API REST completa em `/server`
- ✅ Schema MySQL com 4 tabelas + 1 view
- ✅ Endpoints CRUD para parceiros
- ✅ Suporte a certificações e atividades GTM

### Frontend (Services)
- ✅ `apiService.ts` - Cliente API
- ✅ `usePartnerData.ts` - Hook React customizado
- ✅ Fallback para localStorage (modo offline)

---

## 🚀 Como usar (3 minutos):

### 1️⃣ Setup MySQL

```bash
# Instalar MySQL (se não tem)
# Windows: https://dev.mysql.com/downloads/installer/

# Criar banco de dados
mysql -u root -p
> source server/database/schema.sql
```

### 2️⃣ Configurar Backend

```bash
cd server
cp .env.example .env
# Editar .env com suas credenciais MySQL
npm install
npm run dev
```

✅ Servidor rodando em `http://localhost:3001`

### 3️⃣ Configurar Frontend

```bash
# Na raiz do projeto
cp .env.example .env
# VITE_API_URL=http://localhost:3001/api
npm run dev
```

---

## 🔄 Como integrar no código existente:

### Opção A: Usar o Hook (Recomendado)

```typescript
import { usePartnerData } from './src/services/usePartnerData';

function App() {
  const { partner, savePartner, loading, error } = usePartnerData();

  const handleSubmit = async () => {
    await savePartner({
      companyName: 'Empresa Teste',
      email: 'teste@email.com',
      // ... outros campos
    });
  };

  return (
    <div>
      {loading && <p>Salvando...</p>}
      {error && <p>Erro: {error}</p>}
    </div>
  );
}
```

### Opção B: Usar diretamente o apiService

```typescript
import apiService from './src/services/apiService';

// Salvar parceiro
await apiService.createPartner(formData);

// Atualizar parceiro
await apiService.updatePartner(partnerId, formData);

// Buscar parceiro
const partner = await apiService.getPartnerById(partnerId);

// Listar todos
const partners = await apiService.getAllPartners();
```

---

## 🎯 Substituir localStorage por MySQL:

### Antes (localStorage):
```typescript
localStorage.setItem('cloudpartner_formdata', JSON.stringify(formData));
const saved = localStorage.getItem('cloudpartner_formdata');
```

### Depois (MySQL):
```typescript
await apiService.updatePartner(formData.id, formData);
const partner = await apiService.getPartnerById(formData.id);
```

---

## 📊 Testar se está funcionando:

```bash
# Backend
curl http://localhost:3001/api/health
# Deve retornar: {"status":"ok"}

# Criar parceiro
curl -X POST http://localhost:3001/api/partners \
  -H "Content-Type: application/json" \
  -d '{"companyName":"Test","email":"test@test.com"}'

# Listar parceiros
curl http://localhost:3001/api/partners
```

---

## 💡 Benefícios:

✅ **Dados centralizados** - Vários usuários acessam mesmos dados  
✅ **Backup automático** - MySQL é confiável  
✅ **Relatórios** - Queries SQL para analytics  
✅ **Escalável** - Suporta milhares de parceiros  
✅ **Modo offline** - Fallback para localStorage  

---

## 🔧 Troubleshooting:

### ❌ "Cannot connect to MySQL"
```bash
net start MySQL80
# Verificar se porta 3306 está aberta
```

### ❌ "Access denied"
```bash
# No MySQL:
ALTER USER 'root'@'localhost' IDENTIFIED BY 'sua_senha';
```

### ❌ "CORS error"
```bash
# Já configurado no server.js com cors()
# Certifique-se que ambos servidores estão rodando
```

---

## 📝 Logs e Monitoring:

### Backend logs:
```bash
cd server
npm run dev
# Logs aparecem no terminal
```

### Verificar dados no MySQL:
```sql
SELECT * FROM partners;
SELECT * FROM certifications;
SELECT * FROM partner_summary; -- View agregada
```

---

## 🚀 Próximas melhorias:

- [ ] Autenticação JWT
- [ ] Rate limiting
- [ ] Cache (Redis)
- [ ] Upload de arquivos (S3/Azure Storage)
- [ ] WebSockets (real-time updates)
- [ ] Logs estruturados (Winston)

---

📖 **Documentação completa:** `server/README.md`
