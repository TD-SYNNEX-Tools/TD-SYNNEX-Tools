// ============================================================================
// pages/MyAnalysesPage.tsx — "Minhas Análises" (parceiro T2 e provider)
// ----------------------------------------------------------------------------
// Lista as propostas salvas, permite revisitar e baixar o arquivo (SAS).
// Partner vê as da própria empresa; provider vê todas (com nome da empresa).
// ============================================================================

import React, { useEffect, useMemo, useState } from 'react';
import { Download, RefreshCw, FileSpreadsheet, Database, Filter, ChevronLeft } from 'lucide-react';
import { Card, Button, Badge } from '../components/ui';
import { governanceApi, type Proposal, type GovUser } from '../services/governanceApi';

const STATUS_LABELS: Record<Proposal['status'], string> = {
  draft: 'Rascunho',
  submitted: 'Enviada',
  won: 'Ganha',
  lost: 'Perdida',
};

const STATUS_VARIANT: Record<Proposal['status'], 'slate' | 'sky' | 'emerald' | 'red'> = {
  draft: 'slate',
  submitted: 'sky',
  won: 'emerald',
  lost: 'red',
};

const CLOSED_VIA_LABELS: Record<string, string> = {
  td_synnex: 'TD SYNNEX',
  other_distributor: 'Outro distribuidor',
};

const TYPE_META: Record<Proposal['analysis_type'], { label: string; icon: React.ReactNode }> = {
  financial: { label: 'Análise Financeira', icon: <FileSpreadsheet className="w-4 h-4" /> },
  sql: { label: 'SQL Licensing', icon: <Database className="w-4 h-4" /> },
};

function formatCurrency(value: number | null): string {
  if (value === null || value === undefined) return '—';
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString('pt-BR');
  } catch {
    return iso;
  }
}

const MyAnalysesPage: React.FC<{ goBack?: () => void }> = ({ goBack }) => {
  const [user, setUser] = useState<GovUser | null>(null);
  const [proposals, setProposals] = useState<Proposal[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const isProvider = user?.role === 'provider';

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const [{ user: me }, { proposals: list }] = await Promise.all([
        governanceApi.me(),
        governanceApi.listProposals(statusFilter ? { status: statusFilter } : undefined),
      ]);
      setUser(me);
      setProposals(list);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar análises.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter]);

  async function handleDownload(id: string) {
    setDownloadingId(id);
    try {
      const { url } = await governanceApi.getDownloadUrl(id);
      window.open(url, '_blank', 'noopener');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao gerar link de download.');
    } finally {
      setDownloadingId(null);
    }
  }

  const summary = useMemo(() => {
    const total = proposals.length;
    const won = proposals.filter((p) => p.status === 'won').length;
    const viaTd = proposals.filter((p) => p.closed_via === 'td_synnex').length;
    return { total, won, viaTd };
  }, [proposals]);

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          {goBack && (
            <button onClick={goBack} className="text-gray-400 hover:text-gray-700 transition-colors" aria-label="Voltar">
              <ChevronLeft size={24} />
            </button>
          )}
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Minhas Análises</h1>
            <p className="text-sm text-gray-500">
              {isProvider
                ? 'Visão de governança — todas as empresas parceiras.'
                : 'Suas propostas salvas. Revisite ou baixe a qualquer momento.'}
            </p>
          </div>
        </div>
        <Button variant="ghost" onClick={load}>
          <RefreshCw className="w-4 h-4" /> Atualizar
        </Button>
      </div>

      {/* Resumo */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <Card className="p-4">
          <div className="text-xs text-gray-500 uppercase tracking-wide">Total</div>
          <div className="text-2xl font-bold text-gray-900">{summary.total}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs text-gray-500 uppercase tracking-wide">Ganhas</div>
          <div className="text-2xl font-bold text-green-600">{summary.won}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs text-gray-500 uppercase tracking-wide">Via TD SYNNEX</div>
          <div className="text-2xl font-bold text-blue-700">{summary.viaTd}</div>
        </Card>
      </div>

      {/* Filtro */}
      <div className="flex items-center gap-2 mb-4">
        <Filter className="w-4 h-4 text-gray-400" />
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
        >
          <option value="">Todos os status</option>
          <option value="submitted">Enviada</option>
          <option value="won">Ganha</option>
          <option value="lost">Perdida</option>
          <option value="draft">Rascunho</option>
        </select>
      </div>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-400">Carregando…</div>
      ) : proposals.length === 0 ? (
        <Card className="p-10 text-center text-gray-500">
          Nenhuma análise salva ainda. Ao concluir uma análise na ferramenta, use
          “Salvar como proposta”.
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-left px-4 py-3 font-medium">Título</th>
                {isProvider && <th className="text-left px-4 py-3 font-medium">Empresa</th>}
                <th className="text-left px-4 py-3 font-medium">Cliente</th>
                <th className="text-left px-4 py-3 font-medium">Tipo</th>
                <th className="text-right px-4 py-3 font-medium">Valor</th>
                <th className="text-left px-4 py-3 font-medium">Status</th>
                <th className="text-left px-4 py-3 font-medium">Fechamento</th>
                <th className="text-left px-4 py-3 font-medium">Data</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {proposals.map((p) => (
                <tr key={p.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-900">{p.title}</td>
                  {isProvider && (
                    <td className="px-4 py-3 text-gray-600">{p.company_name || '—'}</td>
                  )}
                  <td className="px-4 py-3 text-gray-600">{p.customer_name || '—'}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center gap-1 text-gray-700">
                      {TYPE_META[p.analysis_type]?.icon}
                      {TYPE_META[p.analysis_type]?.label || p.analysis_type}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-gray-700">
                    {formatCurrency(p.total_value)}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={STATUS_VARIANT[p.status]}>{STATUS_LABELS[p.status]}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {p.closed_via ? CLOSED_VIA_LABELS[p.closed_via] : '—'}
                  </td>
                  <td className="px-4 py-3 text-gray-500">{formatDate(p.created_at)}</td>
                  <td className="px-4 py-3 text-right">
                    {p.blob_path ? (
                      <Button
                        variant="ghost"
                        onClick={() => handleDownload(p.id)}
                        disabled={downloadingId === p.id}
                      >
                        <Download className="w-4 h-4" />
                        {downloadingId === p.id ? '…' : 'Baixar'}
                      </Button>
                    ) : (
                      <span className="text-xs text-gray-400">sem arquivo</span>
                    )}
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

export default MyAnalysesPage;
