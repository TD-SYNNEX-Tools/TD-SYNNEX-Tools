# ArcCalculator - TD SYNNEX Tools

**Aplicação interna TD SYNNEX** para análise de licenciamento e migração Microsoft Azure, SQL Server e M365.

---

## 🚀 Quick Start

### 1. Instalar Dependências

```bash
composer install
```

### 2. Iniciar Servidor

#### Opção A: Script Automático (Windows)
```bash
start-server.bat
```

#### Opção B: Comando Manual
```bash
php -S localhost:8000 -t public router.php
```

### 3. Acessar

Abra seu navegador em: **http://localhost:8000/**

---

## 📁 Estrutura do Projeto

```
php-app/
├── public/               # Document root
│   ├── index.php         # Front controller
│   ├── assets/           # CSS, JS, Images
│   └── uploads/
│
├── src/
│   ├── Core/             # Sistema de rotas e base
│   ├── Features/         # Funcionalidades (5 features)
│   ├── Shared/           # Código compartilhado
│   ├── Templates/        # Layouts reutilizáveis
│   └── Data/             # Dados estáticos
│
├── config/
│   └── routes.php        # Definição de rotas
│
└── reports/              # PDFs gerados
```

---

## 🌐 Rotas Disponíveis

| Rota | Descrição |
|------|-----------|
| `/` | Home (Dashboard) |
| `/azure-migration` | Página principal Azure Migration |
| `/azure-migration/technical-analysis` | Análise Técnica de Recursos |
| `/azure-migration/financial-analysis` | Análise Financeira MOSP vs CSP |
| `/csp-pricing/comparison` | Comparação de Preços CSP |
| `/sql-advisor` | SQL Licensing Advisor |
| `/m365-migration` | Migração M365 |
| `/cloud-partner-hub` | Cloud Partner HUB |

---

## 🛠️ Features

### 1. **Azure Migration**
- Análise Técnica: Verifica viabilidade de migração de 16.000+ recursos Azure
- Análise Financeira: Compara custos MOSP vs CSP usando API pública Microsoft

### 2. **CSP Pricing**
- Validação de preços CSP
- Comparação entre modelos de contrato

### 3. **SQL Licensing Advisor**
- Compara 6 modelos de licenciamento SQL Server 2022
- Chat IA especializado (GPT-4o)
- Gestão de SKUs e preços

### 4. **M365 Migration**
- Processamento de faturas Partner Center
- Normalização de dados de licenciamento

### 5. **Cloud Partner HUB**
- Dashboard centralizado
- Acesso rápido a todas as ferramentas

---

## 🏗️ Arquitetura

### Feature-Based Architecture

Organização por funcionalidade (não por tipo de arquivo):

```
Features/
├── AzureMigration/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   └── Views/
│
├── SqlLicensing/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   ├── Config/
│   └── Views/
│
└── ...
```

### Vantagens

✅ Código relacionado agrupado  
✅ Fácil adicionar/remover features  
✅ Trabalho em equipe sem conflitos  
✅ Manutenção simplificada  

---

## 📝 Documentação

- **[MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)** - Guia completo de uso e padrões
- **[STATUS.md](STATUS.md)** - Checklist de progresso  
- **[SUMMARY.md](SUMMARY.md)** - Resumo executivo da reorganização

---

## 🔧 Desenvolvimento

### Adicionar Nova Rota

Edite `config/routes.php`:

```php
$router->get('/minha-rota', [MeuController::class, 'index']);
```

### Criar Novo Controller

```php
<?php
namespace App\Features\MinhaFeature\Controllers;

use App\Core\BaseController;
use App\Core\Response;

class MeuController extends BaseController
{
    public function index(): Response
    {
        return $this->view('Features/MinhaFeature/Views/index.php');
    }
}
```

### Estrutura de View

```php
<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Minha Página</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../../Templates/components/navigation.php'; ?>
    
    <!-- Conteúdo -->
    
</body>
</html>
```

---

## 🎨 Stack Técnica

- **Backend:** PHP 8.0+
- **Frontend:** HTML5, Tailwind CSS, Vanilla JavaScript
- **PDF:** jsPDF (client-side)
- **Routing:** Custom Router (PSR-like)
- **Autoload:** Composer PSR-4

---

## 📦 Dependências

```json
{
    "php": ">=8.0",
    "composer packages": "via vendor/"
}
```

---

## 🔐 Segurança

- ✅ Apenas `public/` acessível via web
- ✅ `.htaccess` protege arquivos sensíveis
- ✅ Validação de inputs
- ✅ Output sanitizado

---

## 🚢 Deploy

### Apache

Configure Virtual Host apontando para `public/`:

```apache
DocumentRoot "/path/to/php-app/public"
```

### Nginx

```nginx
root /path/to/php-app/public;
try_files $uri $uri/ /index.php?$query_string;
```

---

## 📞 Suporte

**TD SYNNEX Internal Tools**  
Desenvolvido para uso interno da equipe de vendas.

---

## 📄 Licença

Propriedade TD SYNNEX - Uso Interno Exclusivo
