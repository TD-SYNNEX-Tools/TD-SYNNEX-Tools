// ============================================================================
// config/governance.js — Configuração central do Portal de Governança T2
// ----------------------------------------------------------------------------
// Lê variáveis de ambiente (.env) e expõe a configuração tipada usada pela
// camada de auth, Blob e endpoints. Nada de segredo fica hardcoded aqui.
// ============================================================================

import dotenv from 'dotenv';

dotenv.config();

const bool = (v, def = false) =>
  v === undefined ? def : ['1', 'true', 'yes', 'on'].includes(String(v).toLowerCase());

const list = (v) =>
  (v || '')
    .split(',')
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean);

export const config = {
  // --- Microsoft Entra ID (validação do token do portal) ---
  entra: {
    tenantId: process.env.AAD_TENANT_ID || 'common',
    // audience aceito no token: client id da API (ou App ID URI)
    audience: process.env.AAD_API_AUDIENCE || process.env.AAD_CLIENT_ID || '',
    // Quando true, valida assinatura/claims do Entra. Em DEV pode-se desligar
    // para usar um token interno de teste (NUNCA desligar em produção).
    enabled: bool(process.env.AAD_VALIDATION_ENABLED, true),
  },

  // --- Token interno emitido para o php-app (SSO) ---
  internalToken: {
    secret: process.env.INTERNAL_TOKEN_SECRET || '',
    // validade curta (segundos) — o php-app troca por sessão própria logo após
    ttlSeconds: parseInt(process.env.INTERNAL_TOKEN_TTL || '120', 10),
    issuer: 'cloudpartner-hub',
    audience: 'php-app',
  },

  // --- Vínculo usuário -> empresa ---
  onboarding: {
    // domínios públicos que NUNCA entram no auto-vínculo por domínio
    publicEmailDomains: list(
      process.env.PUBLIC_EMAIL_DOMAINS ||
        'gmail.com,hotmail.com,outlook.com,live.com,yahoo.com,icloud.com,protonmail.com'
    ),
    // e-mails que entram automaticamente como provider (você)
    providerEmails: list(process.env.PROVIDER_EMAILS),
  },

  // --- Azure Blob Storage ---
  blob: {
    connectionString: process.env.AZURE_STORAGE_CONNECTION_STRING || '',
    containerName: process.env.AZURE_BLOB_CONTAINER || 'proposals',
    // validade do link de download assinado (SAS), em minutos
    sasTtlMinutes: parseInt(process.env.BLOB_SAS_TTL_MINUTES || '15', 10),
  },
};

// Avisos de configuração ausente (não derruba o processo em DEV)
export function warnMissingConfig() {
  const warn = (msg) => console.warn(`⚠️  [config] ${msg}`);
  if (config.entra.enabled && !config.entra.audience)
    warn('AAD_API_AUDIENCE/AAD_CLIENT_ID ausente — validação Entra exigirá audience.');
  if (!config.internalToken.secret)
    warn('INTERNAL_TOKEN_SECRET ausente — necessário para emitir token do php-app.');
  if (!config.blob.connectionString)
    warn('AZURE_STORAGE_CONNECTION_STRING ausente — upload/download de arquivos desabilitado.');
}
