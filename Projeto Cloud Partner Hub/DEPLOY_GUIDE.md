# Guia de Deploy - Azure App Service

## Problema Comum: npm error exit code 254

Este erro geralmente ocorre durante o deployment no Azure por:
1. Timeout no npm install
2. Conflitos de dependências
3. Falta de memória
4. Configuração incorreta do build

## Solução Implementada

### Arquivos Criados/Atualizados:

1. **`.deployment`** - Configura o deploy customizado
2. **`deploy.sh`** - Script de build e deploy
3. **`.npmrc`** - Configurações npm para evitar timeouts
4. **`package.json`** - Scripts atualizados com postbuild
5. **`server/.npmrc`** - Configurações do servidor

### Configurações no Azure App Service

Configure as seguintes **Application Settings** no portal Azure:

```bash
# Build Settings
SCM_DO_BUILD_DURING_DEPLOYMENT=true
WEBSITE_NODE_DEFAULT_VERSION=~18
NODE_ENV=production

# Timeout settings
SCM_COMMAND_IDLE_TIMEOUT=3600

# Memory
WEBSITE_DYNAMIC_CACHE=0
WEBSITE_LOCAL_CACHE_OPTION=Never
```

### Estrutura de Deploy

```
1. Azure Kudu detecta .deployment
2. Executa deploy.sh
3. Instala dependências do frontend
4. Builda React/Vite
5. Copia arquivos do servidor para wwwroot
6. Copia dist/ para wwwroot/public
7. Instala dependências do servidor (production)
```

## Como Fazer Deploy

### Opção 1: Via Git (Recomendado)

```bash
git add .
git commit -m "Deploy configuration"
git push azure main
```

### Opção 2: Via Azure CLI

```bash
az webapp deployment source config-local-git --name <app-name> --resource-group <resource-group>
git remote add azure <url-retornada>
git push azure main
```

### Opção 3: Via GitHub Actions

O Azure pode criar automaticamente um workflow. Vá em:
- Portal Azure → App Service → Deployment Center → GitHub

## Troubleshooting

### Se continuar com erro 254:

1. **Aumentar timeout no Azure:**
   ```bash
   az webapp config appsettings set --name <app-name> --resource-group <rg> --settings SCM_COMMAND_IDLE_TIMEOUT=3600
   ```

2. **Verificar logs:**
   ```bash
   az webapp log tail --name <app-name> --resource-group <rg>
   ```

3. **Limpar cache do deployment:**
   - No Kudu (https://<app-name>.scm.azurewebsites.net)
   - CMD → `rm -rf /tmp/*`

4. **Deploy manual local:**
   ```bash
   # Build local
   npm install
   npm run build
   
   # Deploy pasta server
   cd server
   npm install --production
   
   # Zipar e fazer deploy
   az webapp deployment source config-zip --src server.zip --name <app-name> --resource-group <rg>
   ```

### Logs importantes:

- **Deployment logs:** https://<app-name>.scm.azurewebsites.net/api/deployments
- **Kudu console:** https://<app-name>.scm.azurewebsites.net/DebugConsole
- **Application logs:** Portal → App Service → Log stream

## Otimizações Aplicadas

1. ✅ `legacy-peer-deps=true` - Ignora conflitos de peer dependencies
2. ✅ `fetch-retries=5` - Retry automático em falhas de rede
3. ✅ `fetch-retry-mintimeout=20000` - Timeout maior para downloads
4. ✅ Build em etapas separadas
5. ✅ Apenas production dependencies no servidor
6. ✅ Script de deploy robusto com error handling

## Estrutura Final

```
wwwroot/
├── server.js (entry point)
├── package.json
├── web.config
├── config/
├── database/
├── routes/
└── public/
    ├── index.html
    └── assets/
```

## Comando de Startup

No Azure App Service, configure:
- **Startup Command:** `node server.js`
- **Stack:** Node 18 LTS

## Variáveis de Ambiente Necessárias

Configure no Azure (Application Settings):

```
DB_HOST=<seu-servidor>.database.windows.net
DB_USER=<usuario>
DB_PASSWORD=<senha>
DB_NAME=cloudpartner_hub
DB_PORT=1433
NODE_ENV=production
PORT=8080
```
