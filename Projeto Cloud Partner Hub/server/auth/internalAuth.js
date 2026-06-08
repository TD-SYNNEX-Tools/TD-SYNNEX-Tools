// ============================================================================
// auth/internalAuth.js — Middleware para chamadas php-app -> API (token interno)
// ----------------------------------------------------------------------------
// O php-app autentica suas chamadas à API com o mesmo token interno (HS256)
// emitido pelo portal. Extrai company_id/user_id/role do token assinado —
// nunca confia em valores enviados no corpo da requisição.
// ============================================================================

import { verifyInternalToken } from './internalToken.js';

function getBearer(req) {
  const h = req.headers.authorization || '';
  return h.startsWith('Bearer ') ? h.slice(7).trim() : null;
}

export function requireInternalToken(req, res, next) {
  try {
    const token = getBearer(req);
    if (!token) return res.status(401).json({ error: 'Token interno ausente.' });

    const claims = verifyInternalToken(token);
    req.internal = {
      userId: claims.sub,
      companyId: claims.company_id || null,
      role: claims.role,
      email: claims.email,
      tool: claims.tool || null,
    };
    next();
  } catch (err) {
    return res.status(401).json({ error: 'Token interno inválido.', detail: err.message });
  }
}
