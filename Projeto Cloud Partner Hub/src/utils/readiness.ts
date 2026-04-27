export type ReadinessBadgeVariant = 'emerald' | 'amber' | 'slate' | 'red' | 'sky' | 'violet';

export interface ReadinessBadge {
  label: string;
  variant: ReadinessBadgeVariant;
}

export interface ReadinessItem {
  key: string;
  label: string;
}

export interface ReadinessResult {
  score: number; // 0..100
  badges: ReadinessBadge[];
  missingTop: ReadinessItem[]; // ordered, max 3
  nextAction: string;
}

export interface ReadinessInput {
  companyName?: string;
  email?: string;
  selectedSolutionArea?: string;
  cspRevenue?: string | number;
  clientCount?: string | number;
  pcsPerformance?: number;
  pcsSkilling?: number;
  pcsCustomerSuccess?: number;
  pcsScore?: number;
  certifications?: Record<string, number> | string[];
  status?: string;
  currentStep?: number;
}

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return 0;
    const n = Number(trimmed);
    return Number.isFinite(n) ? n : 0;
  }
  return 0;
};

const isValidEmail = (email: string | undefined): boolean => {
  if (!email) return false;
  // Simple and sufficient for UX gating
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

const countCertifications = (certs: ReadinessInput['certifications']): number => {
  if (!certs) return 0;
  if (Array.isArray(certs)) return certs.length;
  if (typeof certs === 'object') {
    return Object.values(certs).reduce((sum, qty) => sum + (Number(qty) || 0), 0);
  }
  return 0;
};

export const calculateReadiness = (input: ReadinessInput): ReadinessResult => {
  const companyOk = Boolean(input.companyName && input.companyName.trim().length >= 2);
  const emailOk = isValidEmail(input.email);
  const areaOk = Boolean(input.selectedSolutionArea && String(input.selectedSolutionArea).trim().length > 0);

  const revenue = toNumber(input.cspRevenue);
  const revenueOk = revenue > 0;

  const clients = toNumber(input.clientCount);
  const clientsOk = clients > 0;

  const pcsPerformance = Number(input.pcsPerformance ?? 0) || 0;
  const pcsSkilling = Number(input.pcsSkilling ?? 0) || 0;
  const pcsCustomerSuccess = Number(input.pcsCustomerSuccess ?? 0) || 0;
  const totalPCS = (pcsPerformance || pcsSkilling || pcsCustomerSuccess)
    ? pcsPerformance + pcsSkilling + pcsCustomerSuccess
    : (Number(input.pcsScore ?? 0) || 0);

  const pcsOk = totalPCS > 0;
  const pcsReady = totalPCS >= 70;

  const certCount = countCertifications(input.certifications);
  const certsOk = certCount > 0;

  // Score: biased towards completion + readiness, not “game points”
  let score = 0;
  score += companyOk ? 15 : 0;
  score += emailOk ? 15 : 0;
  score += areaOk ? 10 : 0;
  score += revenueOk ? 10 : 0;
  score += clientsOk ? 10 : 0;
  score += pcsOk ? 10 : 0;
  score += certsOk ? 15 : 0;

  // PCS progress (up to +30)
  const pcsProgress = Math.max(0, Math.min(1, totalPCS / 70));
  score += Math.round(pcsProgress * 30);

  score = Math.max(0, Math.min(100, score));

  const missing: ReadinessItem[] = [];
  if (!companyOk) missing.push({ key: 'companyName', label: 'Preencher razão social' });
  if (!emailOk) missing.push({ key: 'email', label: 'Informar email corporativo válido' });
  if (!areaOk) missing.push({ key: 'selectedSolutionArea', label: 'Definir Solution Area' });
  if (!revenueOk) missing.push({ key: 'cspRevenue', label: 'Informar receita CSP (mensal)' });
  if (!clientsOk) missing.push({ key: 'clientCount', label: 'Informar número de clientes ativos' });
  if (!pcsOk) missing.push({ key: 'pcs', label: 'Preencher métricas de PCS (Performance/Skilling/Customer Success)' });
  if (!certsOk) missing.push({ key: 'certifications', label: 'Cadastrar certificações (quantidade por exame)' });

  const badges: ReadinessBadge[] = [];

  const planClosed = input.status === 'Completed' || (Number(input.currentStep ?? 0) || 0) >= 6;
  if (planClosed) badges.push({ label: 'Plano Fechado', variant: 'emerald' });

  if (pcsReady) badges.push({ label: 'PCS Ready (70+)', variant: 'emerald' });
  else if (totalPCS > 0) badges.push({ label: `PCS em progresso (${totalPCS}/70)`, variant: 'amber' });

  if (revenueOk && clientsOk) badges.push({ label: 'Pipeline Definido', variant: 'sky' });

  // Next best action
  let nextAction = 'Revise os itens pendentes e conclua o plano.';
  if (!areaOk) {
    nextAction = 'Defina a Solution Area para focar o plano.';
  } else if (!pcsReady) {
    const gap = Math.max(0, 70 - totalPCS);
    // Pick the weakest dimension (simple heuristic)
    const dims = [
      { key: 'Performance', value: pcsPerformance },
      { key: 'Skilling', value: pcsSkilling },
      { key: 'Customer Success', value: pcsCustomerSuccess },
    ];
    const weakest = dims.sort((a, b) => a.value - b.value)[0]?.key;
    nextAction = gap > 0
      ? `Eleve o PCS em ${gap} pts priorizando ${weakest}.`
      : 'Eleve o PCS para atingir 70+.';
  } else if (!revenueOk) {
    nextAction = 'Inclua a receita CSP mensal para dimensionar o plano.';
  } else if (!certsOk) {
    nextAction = 'Cadastre certificações para validar o Skilling.';
  }

  return {
    score,
    badges: badges.slice(0, 3),
    missingTop: missing.slice(0, 3),
    nextAction,
  };
};
