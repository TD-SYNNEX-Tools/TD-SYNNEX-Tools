// ============================================================================
// services/proposalService.js — Regras de propostas e eventos de uso
// ----------------------------------------------------------------------------
// Princípio: TODA leitura/escrita é filtrada pelo company_id resolvido do
// token (anti-OWASP A01). O provider enxerga todas as empresas; o partner só
// a própria. Metadados + KPIs ficam aqui (SQL); arquivos vão para o Blob.
// ============================================================================

import crypto from 'crypto';
import db from '../config/database.js';

const uuid = () => crypto.randomUUID();

const ALLOWED_STATUS = ['draft', 'submitted', 'won', 'lost'];
const ALLOWED_CLOSED_VIA = ['td_synnex', 'other_distributor'];
const ALLOWED_TYPES = ['financial', 'sql'];

/**
 * Cria uma proposta. Identidade (company/created_by) vem do token interno.
 * @param {{companyId:string, userId:string}} ctx
 * @param {object} data
 */
export async function createProposal(ctx, data) {
  if (!ctx.companyId) {
    throw Object.assign(new Error('Usuário sem empresa vinculada.'), { httpStatus: 403 });
  }
  if (!ALLOWED_TYPES.includes(data.analysisType)) {
    throw Object.assign(new Error('analysisType inválido.'), { httpStatus: 400 });
  }

  const id = uuid();
  const summary =
    data.resultSummary && typeof data.resultSummary === 'object'
      ? JSON.stringify(data.resultSummary)
      : data.resultSummary || null;

  await db.query(
    `INSERT INTO proposals
       (id, company_id, created_by, analysis_type, title,
        customer_name, customer_tax_id, total_value, status,
        closed_via, blob_path, result_summary)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      id,
      ctx.companyId,
      ctx.userId,
      data.analysisType,
      data.title || 'Análise sem título',
      data.customerName || null,
      data.customerTaxId || null,
      data.totalValue ?? null,
      'submitted',
      null,
      data.blobPath || null,
      summary,
    ]
  );

  return getProposalById(ctx, id);
}

/** Define/atualiza o blob_path de uma proposta (após upload do arquivo). */
export async function setProposalBlobPath(ctx, proposalId, blobPath) {
  const proposal = await getProposalById(ctx, proposalId);
  if (!proposal) return null;
  await db.query(`UPDATE proposals SET blob_path = ? WHERE id = ?`, [blobPath, proposalId]);
  return getProposalById(ctx, proposalId);
}

/**
 * Lista propostas. Provider vê todas; partner só da própria empresa.
 * @param {{companyId:string, role:string}} ctx
 */
export async function listProposals(ctx, filters = {}) {
  const where = [];
  const params = [];

  if (ctx.role !== 'provider') {
    where.push('p.company_id = ?');
    params.push(ctx.companyId);
  } else if (filters.companyId) {
    where.push('p.company_id = ?');
    params.push(filters.companyId);
  }

  if (filters.status) {
    where.push('p.status = ?');
    params.push(filters.status);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const [rows] = await db.query(
    `SELECT p.id, p.company_id, p.created_by, p.analysis_type, p.title,
            p.customer_name, p.customer_tax_id, p.total_value, p.status,
            p.closed_via, p.blob_path, p.created_at, p.updated_at,
            c.name AS company_name
       FROM proposals p
       LEFT JOIN companies c ON c.id = p.company_id
       ${whereSql}
       ORDER BY p.created_at DESC`,
    params
  );
  return rows;
}

/**
 * Busca uma proposta por id, respeitando o escopo do solicitante.
 * Retorna null se não existir OU não pertencer ao escopo (evita enumeração).
 */
export async function getProposalById(ctx, id) {
  const [rows] = await db.query(
    `SELECT p.*, c.name AS company_name
       FROM proposals p
       LEFT JOIN companies c ON c.id = p.company_id
      WHERE p.id = ?`,
    [id]
  );
  const proposal = rows[0];
  if (!proposal) return null;
  if (ctx.role !== 'provider' && proposal.company_id !== ctx.companyId) {
    return null; // fora do escopo -> trata como inexistente
  }
  return proposal;
}

/**
 * Atualiza status/closed_via.
 *   - provider: pode atualizar status e closed_via de qualquer proposta.
 *   - partner: pode atualizar status apenas das propostas da própria empresa.
 */
export async function updateProposalStatus(ctx, id, { status, closedVia }) {
  const proposal = await getProposalById(ctx, id);
  if (!proposal) return null;

  if (status !== undefined && !ALLOWED_STATUS.includes(status)) {
    throw Object.assign(new Error('status inválido.'), { httpStatus: 400 });
  }
  if (
    closedVia !== undefined &&
    closedVia !== null &&
    !ALLOWED_CLOSED_VIA.includes(closedVia)
  ) {
    throw Object.assign(new Error('closed_via inválido.'), { httpStatus: 400 });
  }

  const sets = [];
  const params = [];
  if (status !== undefined) {
    sets.push('status = ?');
    params.push(status);
  }
  if (closedVia !== undefined) {
    sets.push('closed_via = ?');
    params.push(closedVia);
  }
  if (!sets.length) return proposal;

  params.push(id);
  await db.query(`UPDATE proposals SET ${sets.join(', ')} WHERE id = ?`, params);
  return getProposalById(ctx, id);
}

/** Registra um evento de uso leve (métrica de adoção). */
export async function recordUsageEvent(ctx, analysisType) {
  await db.query(
    `INSERT INTO usage_events (company_id, user_id, analysis_type) VALUES (?, ?, ?)`,
    [ctx.companyId || null, ctx.userId || null, analysisType || 'unknown']
  );
}
