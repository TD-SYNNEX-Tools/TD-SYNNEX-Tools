// ============================================================================
// services/userService.js — Resolução e onboarding de usuários T2
// ----------------------------------------------------------------------------
// Regras (decisões dos pontos 3 e 5):
//   - Usuário identificado pelo oid/email do Entra.
//   - Vínculo usuário->empresa: AUTOMÁTICO por domínio do e-mail, mas o usuário
//     entra como 'pending' até aprovação do provider.
//   - Domínios públicos (gmail etc.) nunca entram no auto-vínculo (manual).
//   - E-mails listados em PROVIDER_EMAILS entram como provider/active.
// Toda autorização downstream usa company_id/role resolvidos aqui — nunca a URL.
// ============================================================================

import crypto from 'crypto';
import db from '../config/database.js';
import { config } from '../config/governance.js';

const uuid = () => crypto.randomUUID();

function emailDomain(email) {
  const at = (email || '').lastIndexOf('@');
  return at >= 0 ? email.slice(at + 1).toLowerCase() : '';
}

async function findUserByOidOrEmail(oid, email) {
  const [rows] = await db.query(
    `SELECT TOP 1 * FROM users WHERE (entra_oid = ? AND entra_oid IS NOT NULL) OR email = ?`,
    [oid || '', email]
  );
  return rows[0] || null;
}

async function findCompanyByDomain(domain) {
  if (!domain) return null;
  const [rows] = await db.query(
    `SELECT TOP 1 * FROM companies WHERE email_domain = ?`,
    [domain]
  );
  return rows[0] || null;
}

/**
 * Resolve (ou cria) o usuário a partir dos claims do Entra.
 * Retorna o registro do usuário já com company_id/role/status.
 * @param {{oid:string, email:string, name:string}} entra
 */
export async function resolveOrCreateUser(entra) {
  const email = (entra.email || '').toLowerCase();
  if (!email) throw new Error('Token sem e-mail — não é possível resolver usuário.');

  const existing = await findUserByOidOrEmail(entra.oid, email);
  if (existing) {
    // Backfill do oid caso o usuário tenha sido pré-criado só com e-mail
    if (!existing.entra_oid && entra.oid) {
      await db.query(`UPDATE users SET entra_oid = ? WHERE id = ?`, [entra.oid, existing.id]);
      existing.entra_oid = entra.oid;
    }
    return existing;
  }

  // Novo usuário
  const isProvider = config.onboarding.providerEmails.includes(email);
  const domain = emailDomain(email);
  const isPublic = config.onboarding.publicEmailDomains.includes(domain);

  let companyId = null;
  let status = 'pending';
  let role = 'partner';

  if (isProvider) {
    role = 'provider';
    status = 'active';
  } else if (!isPublic) {
    const company = await findCompanyByDomain(domain);
    if (company) {
      companyId = company.id; // auto-vínculo por domínio, mas continua pending
    }
  }

  const id = uuid();
  await db.query(
    `INSERT INTO users (id, company_id, entra_oid, email, name, role, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [id, companyId, entra.oid || null, email, entra.name || null, role, status]
  );

  const [rows] = await db.query(`SELECT TOP 1 * FROM users WHERE id = ?`, [id]);
  return rows[0];
}

/** Lista usuários pendentes de aprovação (visão do provider). */
export async function listPendingUsers() {
  const [rows] = await db.query(
    `SELECT u.*, c.name AS company_name
       FROM users u
       LEFT JOIN companies c ON c.id = u.company_id
      WHERE u.status = 'pending'
      ORDER BY u.created_at ASC`
  );
  return rows;
}

/** Aprova um usuário, opcionalmente (re)vinculando a uma empresa. */
export async function approveUser(userId, companyId) {
  if (companyId) {
    await db.query(`UPDATE users SET company_id = ?, status = 'active' WHERE id = ?`, [
      companyId,
      userId,
    ]);
  } else {
    await db.query(`UPDATE users SET status = 'active' WHERE id = ?`, [userId]);
  }
  const [rows] = await db.query(`SELECT TOP 1 * FROM users WHERE id = ?`, [userId]);
  return rows[0];
}

/** Rejeita um usuário pendente. */
export async function rejectUser(userId) {
  await db.query(`UPDATE users SET status = 'rejected' WHERE id = ?`, [userId]);
}
