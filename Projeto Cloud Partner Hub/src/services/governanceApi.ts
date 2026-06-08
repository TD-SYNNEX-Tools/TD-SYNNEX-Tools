// ============================================================================
// services/governanceApi.ts — Cliente do Portal de Governança T2
// ----------------------------------------------------------------------------
// Injeta o access token do Entra (MSAL) no header Authorization de cada
// chamada a /api/gov. A identidade/escopo é resolvida no servidor.
// ============================================================================

import { msalInstance, loginRequest } from '../auth/msalConfig';

const getApiBaseUrl = (): string => {
  if (typeof window !== 'undefined') {
    const hostname = window.location.hostname;
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return 'http://localhost:3001/api';
    }
  }
  return '/api';
};

export interface GovUser {
  id: string;
  companyId: string | null;
  role: 'partner' | 'provider';
  email: string;
  status: 'pending' | 'active' | 'rejected' | 'disabled';
}

export interface Proposal {
  id: string;
  company_id: string;
  company_name?: string;
  created_by: string;
  analysis_type: 'financial' | 'sql';
  title: string;
  customer_name: string | null;
  customer_tax_id: string | null;
  total_value: number | null;
  status: 'draft' | 'submitted' | 'won' | 'lost';
  closed_via: 'td_synnex' | 'other_distributor' | null;
  blob_path: string | null;
  created_at: string;
  updated_at: string;
}

export interface PendingUser {
  id: string;
  email: string;
  name: string | null;
  company_id: string | null;
  company_name: string | null;
  created_at: string;
}

async function getAccessToken(): Promise<string> {
  const account = msalInstance.getActiveAccount() || msalInstance.getAllAccounts()[0];
  if (!account) throw new Error('Usuário não autenticado.');
  const result = await msalInstance.acquireTokenSilent({
    ...loginRequest,
    account,
  });
  return result.accessToken;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = await getAccessToken();
  const res = await fetch(`${getApiBaseUrl()}/gov${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      ...(init.headers || {}),
    },
  });
  if (!res.ok) {
    let msg = `Erro ${res.status}`;
    try {
      const body = await res.json();
      msg = body.error || msg;
    } catch {
      /* ignore */
    }
    throw new Error(msg);
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

export const governanceApi = {
  me: () => request<{ user: GovUser }>('/me'),

  listProposals: (params?: { status?: string; companyId?: string }) => {
    const qs = new URLSearchParams(params as Record<string, string>).toString();
    return request<{ proposals: Proposal[] }>(`/proposals${qs ? `?${qs}` : ''}`);
  },

  getProposal: (id: string) => request<{ proposal: Proposal }>(`/proposals/${id}`),

  updateStatus: (id: string, body: { status?: string; closedVia?: string | null }) =>
    request<{ proposal: Proposal }>(`/proposals/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(body),
    }),

  getDownloadUrl: (id: string) => request<{ url: string }>(`/proposals/${id}/download`),

  // Provider — fila de aprovação
  listPendingUsers: () => request<{ users: PendingUser[] }>('/admin/pending-users'),
  approveUser: (id: string, companyId?: string) =>
    request<{ user: GovUser }>(`/admin/users/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ companyId }),
    }),
  rejectUser: (id: string) =>
    request<{ ok: boolean }>(`/admin/users/${id}/reject`, { method: 'POST' }),
};
