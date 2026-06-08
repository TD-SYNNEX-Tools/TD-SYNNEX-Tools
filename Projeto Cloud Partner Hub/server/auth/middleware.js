// ============================================================================
// auth/middleware.js — Middlewares de autenticação e autorização (Express)
// ----------------------------------------------------------------------------
//   requireAuth   valida o token Entra (Bearer), resolve o usuário no banco e
//                 anexa req.user = { id, companyId, role, email, status }.
//   requireActive exige status 'active' (bloqueia pendentes/rejeitados).
//   requireRole   exige um papel específico (ex.: 'provider').
//
// Regra anti-OWASP A01: a identidade vem SEMPRE do token resolvido no servidor,
// nunca de parâmetros da requisição.
// ============================================================================

import { verifyEntraToken } from './entra.js';
import { verifyInternalToken } from './internalToken.js';
import { resolveOrCreateUser } from '../services/userService.js';
import { config } from '../config/governance.js';

function getBearer(req) {
  const h = req.headers.authorization || '';
  return h.startsWith('Bearer ') ? h.slice(7).trim() : null;
}

export async function requireAuth(req, res, next) {
  try {
    const token = getBearer(req);
    if (!token) return res.status(401).json({ error: 'Token ausente.' });

    let entra;
    if (config.entra.enabled) {
      const claims = await verifyEntraToken(token);
      entra = { oid: claims.oid, email: claims.email, name: claims.name };
    } else {
      // DEV: aceita token interno assinado para testes locais sem Entra real.
      const claims = verifyInternalToken(token);
      entra = { oid: claims.sub, email: claims.email, name: claims.email };
    }

    const user = await resolveOrCreateUser(entra);
    req.user = {
      id: user.id,
      companyId: user.company_id,
      role: user.role,
      email: user.email,
      status: user.status,
    };
    next();
  } catch (err) {
    return res.status(401).json({ error: 'Token inválido.', detail: err.message });
  }
}

export function requireActive(req, res, next) {
  if (!req.user) return res.status(401).json({ error: 'Não autenticado.' });
  if (req.user.status !== 'active') {
    return res.status(403).json({
      error: 'Conta pendente de aprovação.',
      status: req.user.status,
    });
  }
  next();
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: 'Não autenticado.' });
    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Permissão insuficiente.' });
    }
    next();
  };
}
