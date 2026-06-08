// ============================================================================
// auth/entra.js — Validação de access token do Microsoft Entra ID
// ----------------------------------------------------------------------------
// Verifica a assinatura do JWT contra as chaves públicas (JWKS) do tenant e
// confere issuer e audience. Retorna os claims úteis (oid, email, name).
// ============================================================================

import jwt from 'jsonwebtoken';
import { JwksClient } from 'jwks-rsa';
import { config } from '../config/governance.js';

const tenantId = config.entra.tenantId;

// JWKS endpoint do tenant (v2.0)
const jwksClient = new JwksClient({
  jwksUri: `https://login.microsoftonline.com/${tenantId}/discovery/v2.0/keys`,
  cache: true,
  cacheMaxEntries: 5,
  cacheMaxAge: 24 * 60 * 60 * 1000, // 24h
  rateLimit: true,
});

function getSigningKey(header, callback) {
  jwksClient.getSigningKey(header.kid, (err, key) => {
    if (err) return callback(err);
    callback(null, key.getPublicKey());
  });
}

// Emissores aceitos (v2.0). Para multi-tenant, o tid do token define o issuer.
function acceptedIssuers() {
  return [
    `https://login.microsoftonline.com/${tenantId}/v2.0`,
    `https://sts.windows.net/${tenantId}/`,
  ];
}

/**
 * Valida o access token do Entra e retorna os claims normalizados.
 * @param {string} token  JWT bruto (sem o prefixo "Bearer ")
 * @returns {Promise<{oid:string, email:string, name:string, tid:string, raw:object}>}
 */
export function verifyEntraToken(token) {
  return new Promise((resolve, reject) => {
    const options = {
      algorithms: ['RS256'],
      audience: config.entra.audience || undefined,
      issuer: acceptedIssuers(),
    };

    jwt.verify(token, getSigningKey, options, (err, decoded) => {
      if (err) return reject(err);

      const email =
        decoded.preferred_username ||
        decoded.email ||
        decoded.upn ||
        (Array.isArray(decoded.emails) ? decoded.emails[0] : undefined);

      resolve({
        oid: decoded.oid || decoded.sub,
        email: (email || '').toLowerCase(),
        name: decoded.name || '',
        tid: decoded.tid || tenantId,
        raw: decoded,
      });
    });
  });
}
