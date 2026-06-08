// ============================================================================
// auth/internalToken.js — Token interno (SSO) emitido para o php-app
// ----------------------------------------------------------------------------
// Após autenticar o usuário via Entra, o portal emite um JWT curto, assinado
// com um segredo compartilhado, que o php-app valida antes de rodar uma
// ferramenta. O token carrega a identidade resolvida (company_id, user_id,
// role) — o php-app NUNCA confia em IDs vindos do navegador.
// ============================================================================

import jwt from 'jsonwebtoken';
import { config } from '../config/governance.js';

const { secret, ttlSeconds, issuer, audience } = config.internalToken;

/**
 * Emite o token interno para o php-app.
 * @param {{userId:string, companyId:string|null, role:string, email:string, analysisType?:string}} claims
 * @returns {string} JWT assinado
 */
export function issueInternalToken(claims) {
  if (!secret) {
    throw new Error('INTERNAL_TOKEN_SECRET não configurado.');
  }
  const payload = {
    sub: claims.userId,
    company_id: claims.companyId || null,
    role: claims.role,
    email: claims.email,
    // ferramenta de destino (opcional) — restringe o uso do token
    tool: claims.analysisType || null,
  };
  return jwt.sign(payload, secret, {
    algorithm: 'HS256',
    expiresIn: ttlSeconds,
    issuer,
    audience,
  });
}

/**
 * Valida o token interno (usado em testes / endpoints internos).
 * @param {string} token
 * @returns {object} claims
 */
export function verifyInternalToken(token) {
  return jwt.verify(token, secret, {
    algorithms: ['HS256'],
    issuer,
    audience,
  });
}
