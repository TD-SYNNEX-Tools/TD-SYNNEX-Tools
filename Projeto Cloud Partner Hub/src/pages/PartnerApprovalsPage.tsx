// ============================================================================
// pages/PartnerApprovalsPage.tsx — Fila de aprovação de usuários T2 (provider)
// ----------------------------------------------------------------------------
// Lista usuários pendentes (B2B Guest que logaram pela 1ª vez). O provider
// aprova (opcionalmente informando a empresa) ou rejeita. Domínios públicos
// chegam aqui sem empresa sugerida e exigem associação manual.
// ============================================================================

import React, { useEffect, useState } from 'react';
import { Check, X, RefreshCw, UserCheck } from 'lucide-react';
import { Card, Button } from '../components/ui';
import { governanceApi, type PendingUser } from '../services/governanceApi';

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('pt-BR');
  } catch {
    return iso;
  }
}

const PartnerApprovalsPage: React.FC = () => {
  const [users, setUsers] = useState<PendingUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [companyInput, setCompanyInput] = useState<Record<string, string>>({});

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const { users: list } = await governanceApi.listPendingUsers();
      setUsers(list);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar pendências.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, []);

  async function handleApprove(u: PendingUser) {
    setBusyId(u.id);
    setError(null);
    try {
      const companyId = companyInput[u.id] || u.company_id || undefined;
      if (!companyId) {
        setError('Informe a empresa (companyId) para aprovar este usuário.');
        setBusyId(null);
        return;
      }
      await governanceApi.approveUser(u.id, companyId);
      setUsers((prev) => prev.filter((x) => x.id !== u.id));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao aprovar.');
    } finally {
      setBusyId(null);
    }
  }

  async function handleReject(u: PendingUser) {
    setBusyId(u.id);
    setError(null);
    try {
      await governanceApi.rejectUser(u.id);
      setUsers((prev) => prev.filter((x) => x.id !== u.id));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao rejeitar.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <UserCheck className="w-6 h-6 text-blue-700" /> Aprovação de Parceiros
          </h1>
          <p className="text-sm text-gray-500">
            Novos usuários T2 aguardando liberação de acesso.
          </p>
        </div>
        <Button variant="ghost" onClick={load}>
          <RefreshCw className="w-4 h-4" /> Atualizar
        </Button>
      </div>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-400">Carregando…</div>
      ) : users.length === 0 ? (
        <Card className="p-10 text-center text-gray-500">
          Nenhum usuário pendente. Tudo em dia. ✅
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-left px-4 py-3 font-medium">E-mail</th>
                <th className="text-left px-4 py-3 font-medium">Nome</th>
                <th className="text-left px-4 py-3 font-medium">Empresa (sugerida/ID)</th>
                <th className="text-left px-4 py-3 font-medium">Solicitado em</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {users.map((u) => (
                <tr key={u.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-900">{u.email}</td>
                  <td className="px-4 py-3 text-gray-600">{u.name || '—'}</td>
                  <td className="px-4 py-3">
                    <input
                      type="text"
                      defaultValue={u.company_id || ''}
                      placeholder={u.company_name || 'ID da empresa'}
                      onChange={(e) =>
                        setCompanyInput((prev) => ({ ...prev, [u.id]: e.target.value }))
                      }
                      className="border border-gray-300 rounded-lg px-2 py-1 text-sm w-56"
                    />
                    {u.company_name && (
                      <div className="text-xs text-gray-400 mt-1">
                        sugerida por domínio: {u.company_name}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3 text-gray-500">{formatDate(u.created_at)}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 justify-end">
                      <Button
                        variant="primary"
                        onClick={() => handleApprove(u)}
                        disabled={busyId === u.id}
                      >
                        <Check className="w-4 h-4" /> Aprovar
                      </Button>
                      <Button
                        variant="ghost"
                        onClick={() => handleReject(u)}
                        disabled={busyId === u.id}
                      >
                        <X className="w-4 h-4" /> Rejeitar
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
};

export default PartnerApprovalsPage;
