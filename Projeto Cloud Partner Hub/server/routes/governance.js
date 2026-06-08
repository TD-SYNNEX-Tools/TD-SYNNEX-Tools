// ============================================================================
// routes/governance.js — Endpoints do Portal de Governança T2
// ----------------------------------------------------------------------------
// Dois grupos de autenticação:
//   • Browser (token Entra via requireAuth):
//       GET    /api/gov/me
//       POST   /api/gov/php-token
//       GET    /api/gov/proposals
//       GET    /api/gov/proposals/:id
//       PATCH  /api/gov/proposals/:id/status
//       GET    /api/gov/proposals/:id/download
//       GET    /api/gov/admin/pending-users          (provider)
//       POST   /api/gov/admin/users/:id/approve       (provider)
//       POST   /api/gov/admin/users/:id/reject        (provider)
//   • php-app (token interno via requireInternalToken):
//       POST   /api/gov/proposals                     (criar)
//       POST   /api/gov/proposals/:id/file            (upload arquivo)
//       POST   /api/gov/usage                         (evento de uso)
// ============================================================================

import express from 'express';
import multer from 'multer';
import { requireAuth, requireActive, requireRole } from '../auth/middleware.js';
import { requireInternalToken } from '../auth/internalAuth.js';
import { issueInternalToken } from '../auth/internalToken.js';
import {
  createProposal,
  setProposalBlobPath,
  listProposals,
  getProposalById,
  updateProposalStatus,
  recordUsageEvent,
} from '../services/proposalService.js';
import {
  listPendingUsers,
  approveUser,
  rejectUser,
} from '../services/userService.js';
import { isBlobEnabled, uploadBuffer, getDownloadUrl } from '../services/blobService.js';

const router = express.Router();
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 50 * 1024 * 1024 }, // 50 MB por arquivo
});

function handleError(res, err) {
  const status = err.httpStatus || 500;
  if (status >= 500) console.error('❌ [governance]', err);
  return res.status(status).json({ error: err.message });
}

// ---------------------------------------------------------------------------
// BROWSER (Entra)
// ---------------------------------------------------------------------------

// Identidade do usuário logado
router.get('/me', requireAuth, (req, res) => {
  res.json({ user: req.user });
});

// Emite token interno para abrir uma ferramenta do php-app
router.post('/php-token', requireAuth, requireActive, (req, res) => {
  try {
    const analysisType = req.body?.analysisType || null;
    const token = issueInternalToken({
      userId: req.user.id,
      companyId: req.user.companyId,
      role: req.user.role,
      email: req.user.email,
      analysisType,
    });
    res.json({ token });
  } catch (err) {
    handleError(res, err);
  }
});

// Lista propostas (partner: da empresa; provider: todas)
router.get('/proposals', requireAuth, requireActive, async (req, res) => {
  try {
    const rows = await listProposals(req.user, {
      companyId: req.query.companyId,
      status: req.query.status,
    });
    res.json({ proposals: rows });
  } catch (err) {
    handleError(res, err);
  }
});

router.get('/proposals/:id', requireAuth, requireActive, async (req, res) => {
  try {
    const proposal = await getProposalById(req.user, req.params.id);
    if (!proposal) return res.status(404).json({ error: 'Proposta não encontrada.' });
    res.json({ proposal });
  } catch (err) {
    handleError(res, err);
  }
});

// Atualiza status/closed_via
router.patch('/proposals/:id/status', requireAuth, requireActive, async (req, res) => {
  try {
    // closed_via só pode ser definido pelo provider (governança)
    const payload = { status: req.body.status };
    if (req.user.role === 'provider') payload.closedVia = req.body.closedVia;

    const updated = await updateProposalStatus(req.user, req.params.id, payload);
    if (!updated) return res.status(404).json({ error: 'Proposta não encontrada.' });
    res.json({ proposal: updated });
  } catch (err) {
    handleError(res, err);
  }
});

// Gera link de download (SAS) do arquivo da proposta
router.get('/proposals/:id/download', requireAuth, requireActive, async (req, res) => {
  try {
    const proposal = await getProposalById(req.user, req.params.id);
    if (!proposal) return res.status(404).json({ error: 'Proposta não encontrada.' });
    if (!proposal.blob_path) return res.status(404).json({ error: 'Sem arquivo associado.' });
    if (!isBlobEnabled()) return res.status(503).json({ error: 'Blob não configurado.' });

    const url = await getDownloadUrl(proposal.blob_path);
    res.json({ url });
  } catch (err) {
    handleError(res, err);
  }
});

// --- Provider: fila de aprovação de usuários ---
router.get('/admin/pending-users', requireAuth, requireActive, requireRole('provider'), async (req, res) => {
  try {
    const users = await listPendingUsers();
    res.json({ users });
  } catch (err) {
    handleError(res, err);
  }
});

router.post('/admin/users/:id/approve', requireAuth, requireActive, requireRole('provider'), async (req, res) => {
  try {
    const user = await approveUser(req.params.id, req.body.companyId);
    res.json({ user });
  } catch (err) {
    handleError(res, err);
  }
});

router.post('/admin/users/:id/reject', requireAuth, requireActive, requireRole('provider'), async (req, res) => {
  try {
    await rejectUser(req.params.id);
    res.json({ ok: true });
  } catch (err) {
    handleError(res, err);
  }
});

// ---------------------------------------------------------------------------
// php-app (token interno)
// ---------------------------------------------------------------------------

// Cria proposta (chamado pelo php-app ao "Salvar como proposta")
router.post('/proposals', requireInternalToken, async (req, res) => {
  try {
    const proposal = await createProposal(req.internal, req.body);
    res.status(201).json({ proposal });
  } catch (err) {
    handleError(res, err);
  }
});

// Upload do arquivo da proposta (XLSX/PDF/JSON)
router.post('/proposals/:id/file', requireInternalToken, upload.single('file'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'Arquivo ausente.' });
    if (!isBlobEnabled()) return res.status(503).json({ error: 'Blob não configurado.' });

    // Confere escopo: a proposta deve pertencer à empresa do token
    const proposal = await getProposalById(req.internal, req.params.id);
    if (!proposal) return res.status(404).json({ error: 'Proposta não encontrada.' });

    const safeName = (req.file.originalname || 'arquivo.bin').replace(/[^\w.\-]/g, '_');
    const blobName = `${proposal.company_id}/${proposal.id}/${Date.now()}_${safeName}`;
    await uploadBuffer(blobName, req.file.buffer, req.file.mimetype);
    const updated = await setProposalBlobPath(req.internal, proposal.id, blobName);
    res.json({ proposal: updated });
  } catch (err) {
    handleError(res, err);
  }
});

// Evento de uso (gravado em toda execução de análise)
router.post('/usage', requireInternalToken, async (req, res) => {
  try {
    await recordUsageEvent(req.internal, req.body.analysisType);
    res.status(202).json({ ok: true });
  } catch (err) {
    handleError(res, err);
  }
});

export default router;
