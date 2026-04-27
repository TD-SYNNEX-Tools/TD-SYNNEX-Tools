import React, { useEffect, useMemo, useState } from 'react';
import { useMsal } from '@azure/msal-react';
import { 
  ChevronLeft, Users, TrendingUp, Award, DollarSign, Target, 
  Search, Filter, Download, Eye, X, Building2, Mail, Phone, 
  Calendar, BarChart3, Shield, Briefcase, Star, CheckCircle2, 
  AlertCircle, Clock, ArrowUpRight, ArrowDownRight, GraduationCap,
  FileText, Zap, Flag, PlayCircle, PauseCircle, CheckCircle, 
  XCircle, ArrowRight, Rocket, ShieldCheck, Megaphone
} from 'lucide-react';
import { PartnerData, EdgeStage, SolutionArea } from '../types';
import { calculateEdgeStage } from '../utils/helpers';
import { generateAssessmentPDF } from '../utils/assessmentPdf';
import { calculateReadiness } from '../utils/readiness';
import { SponsorLogos, Card, Button, Badge } from '../components/ui';
import EdgePipeline from '../components/EdgePipeline';
import apiService from '../services/apiService';

interface AdminPageProps {
  goBack: () => void;
}

// Journey Step Configuration
const JOURNEY_STEPS = [
  { id: 1, title: 'Welcome', icon: Rocket, desc: 'Onboarding inicial' },
  { id: 2, title: 'Triagem', icon: ShieldCheck, desc: 'Assessment de maturidade' },
  { id: 3, title: 'Soluções', icon: Briefcase, desc: 'Definição de trilha' },
  { id: 4, title: 'Métricas', icon: BarChart3, desc: 'Input de dados PCS' },
  { id: 5, title: 'Plano', icon: Award, desc: 'Plano de ação' },
  { id: 6, title: 'GTM', icon: Megaphone, desc: 'Estratégia de mercado' },
];

const AdminPage = ({ goBack }: AdminPageProps) => {
  const { accounts } = useMsal();
  const [partners, setPartners] = useState<PartnerData[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterStage, setFilterStage] = useState<EdgeStage | 'all'>('all');
  const [filterArea, setFilterArea] = useState<SolutionArea | 'all'>('all');
  const [selectedPartner, setSelectedPartner] = useState<PartnerData | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [loadingPartnerDetails, setLoadingPartnerDetails] = useState(false);
  const [selectedPartnerCertifications, setSelectedPartnerCertifications] = useState<Record<string, number> | null>(null);

  const adaptPartnerFromApi = (p: any): PartnerData => {
    const pcsPerformance = Number(p?.pcsPerformance ?? p?.pcs_performance ?? 0) || 0;
    const pcsSkilling = Number(p?.pcsSkilling ?? p?.pcs_skilling ?? 0) || 0;
    const pcsCustomerSuccess = Number(p?.pcsCustomerSuccess ?? p?.pcs_customer_success ?? 0) || 0;
    const pcsScore = Number(p?.pcsScore ?? p?.pcs_score ?? p?.total_pcs ?? (pcsPerformance + pcsSkilling + pcsCustomerSuccess)) || 0;

    const certificationsRaw = p?.certifications;
    const certifications = Array.isArray(certificationsRaw)
      ? certificationsRaw
      : certificationsRaw && typeof certificationsRaw === 'object'
        ? Object.keys(certificationsRaw)
        : undefined;

    const solutionArea = (p?.solutionArea ?? p?.selectedSolutionArea ?? p?.selected_solution_area) as SolutionArea | undefined;

    return {
      id: String(p?.id ?? ''),
      companyName: String(p?.companyName ?? p?.company_name ?? ''),
      contactName: String(p?.contactName ?? p?.contact_name ?? ''),
      email: String(p?.email ?? ''),
      phone: p?.phone ?? undefined,
      mpnId: p?.mpnId ?? p?.mpn_id ?? undefined,
      isMicrosoftPartner: p?.isMicrosoftPartner ?? p?.is_microsoft_partner ?? undefined,
      isTdSynnexRegistered: p?.isTdSynnexRegistered ?? p?.is_td_synnex_registered ?? undefined,
      solutionArea,
      pcsScore,
      revenueM365: Number(p?.revenueM365 ?? 0) || 0,
      revenueAzure: Number(p?.revenueAzure ?? p?.cspRevenue ?? p?.csp_revenue ?? 0) || 0,
      revenueSecurity: Number(p?.revenueSecurity ?? 0) || 0,
      certifications,
      createdAt: p?.createdAt ?? p?.created_at ?? undefined,
      currentStep: Number(p?.currentStep ?? p?.current_step ?? 1) || 1,
      status: (p?.status ?? 'In Progress') as PartnerData['status'],
    };
  };

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      setLoading(true);
      setLoadError(null);
      try {
        const raw = (await apiService.getAllPartners()) as any[];
        if (cancelled) return;
        setPartners((raw || []).map(adaptPartnerFromApi));
      } catch (err: any) {
        if (cancelled) return;
        setLoadError(err?.message || 'Erro ao carregar parceiros do banco');
        setPartners([]);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    load();
    return () => {
      cancelled = true;
    };
  }, []);

  // Derived data
  const enrichedPartners = useMemo(() => {
    return partners.map(p => ({
      ...p,
      edgeStage: calculateEdgeStage(p),
      totalRevenue: (p.revenueM365 || 0) + (p.revenueAzure || 0) + (p.revenueSecurity || 0),
    }));
  }, [partners]);

  const filteredPartners = useMemo(() => {
    return enrichedPartners.filter(p => {
      const matchSearch = p.companyName.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         p.email.toLowerCase().includes(searchTerm.toLowerCase());
      const matchStage = filterStage === 'all' || p.edgeStage === filterStage;
      const matchArea = filterArea === 'all' || p.solutionArea === filterArea;
      return matchSearch && matchStage && matchArea;
    });
  }, [enrichedPartners, searchTerm, filterStage, filterArea]);

  // KPIs
  const kpis = useMemo(() => {
    const total = enrichedPartners.length;
    const totalRevenue = enrichedPartners.reduce((sum, p) => sum + p.totalRevenue, 0);
    const avgPCS = total > 0 ? (enrichedPartners.reduce((sum, p) => sum + (p.pcsScore || 0), 0) / total) : 0;
    const extendCount = enrichedPartners.filter(p => p.edgeStage === 'Extend').length;
    const growthCount = enrichedPartners.filter(p => p.edgeStage === 'Growth').length;

    return {
      totalPartners: total,
      totalRevenue,
      avgPCS: avgPCS.toFixed(0),
      premierCount: extendCount,
      advancedCount: growthCount,
      conversionRate: total > 0 ? (((extendCount + growthCount) / total) * 100).toFixed(1) : '0.0',
    };
  }, [enrichedPartners]);

  // Distribution by solution area
  const areaDistribution = useMemo(() => {
    const areas: SolutionArea[] = ['Modern Work', 'Azure Infra', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'];
    return areas.map(area => ({
      area,
      count: enrichedPartners.filter(p => p.solutionArea === area).length,
      revenue: enrichedPartners.filter(p => p.solutionArea === area).reduce((sum, p) => sum + p.totalRevenue, 0),
    }));
  }, [enrichedPartners]);

  // Journey Analytics
  const journeyAnalytics = useMemo(() => {
    const stepDistribution = JOURNEY_STEPS.map(step => ({
      step: step.id,
      title: step.title,
      count: partners.filter(p => p.currentStep === step.id).length,
      partners: partners.filter(p => p.currentStep === step.id)
    }));

    const stalledPartners = partners.filter(p => p.status === 'Stalled');
    const completedPartners = partners.filter(p => p.status === 'Completed');
    const inProgressPartners = partners.filter(p => p.status === 'In Progress');

    // Calcular tempo médio por etapa (simulado)
    const avgTimePerStep = {
      1: '2 dias',
      2: '3 dias',
      3: '5 dias',
      4: '4 dias',
      5: '3 dias',
      6: '7 dias',
    };

    return {
      stepDistribution,
      stalledPartners,
      completedPartners,
      inProgressPartners,
      avgTimePerStep,
      completionRate: ((completedPartners.length / partners.length) * 100).toFixed(1),
    };
  }, [partners]);

  const handlePipelineStageClick = (stage: EdgeStage) => {
    setFilterStage(filterStage === stage ? 'all' : stage);
  };

  const closeDetailModal = () => {
    setShowDetailModal(false);
    setSelectedPartnerCertifications(null);
    setLoadingPartnerDetails(false);
  };

  const handleViewPartner = async (partner: PartnerData & { edgeStage: EdgeStage; totalRevenue: number }) => {
    setSelectedPartner(partner);
    setShowDetailModal(true);
    setSelectedPartnerCertifications(null);
    setLoadingPartnerDetails(true);

    try {
      const raw: any = await apiService.getPartnerById(String(partner.id));
      const certMap: Record<string, number> =
        raw?.certifications && typeof raw.certifications === 'object' && !Array.isArray(raw.certifications)
          ? raw.certifications
          : {};

      setSelectedPartnerCertifications(certMap);
      setSelectedPartner(prev => prev ? ({ ...prev, certifications: Object.keys(certMap) }) : prev);
    } catch (err) {
      console.error('Erro ao carregar detalhes do parceiro:', err);
    } finally {
      setLoadingPartnerDetails(false);
    }
  };

  const handleDownloadPartnerPDF = async (partner: PartnerData) => {
    try {
      // Busca o parceiro completo (inclui `certifications` com quantidade) para manter
      // o mesmo PDF formatado do fluxo do usuário.
      const raw: any = await apiService.getPartnerById(partner.id);

      const adapted = {
        id: String(raw?.id ?? partner.id ?? ''),
        companyName: String(raw?.companyName ?? raw?.company_name ?? partner.companyName ?? ''),
        email: String(raw?.email ?? partner.email ?? ''),
        selectedSolutionArea: String(raw?.selectedSolutionArea ?? raw?.selected_solution_area ?? partner.solutionArea ?? ''),
        isTdSynnexRegistered: Boolean(
          raw?.isTdSynnexRegistered ??
            raw?.is_td_synnex_registered ??
            partner.isTdSynnexRegistered ??
            false
        ),
        isMicrosoftPartner: Boolean(
          raw?.isMicrosoftPartner ?? raw?.is_microsoft_partner ?? partner.isMicrosoftPartner ?? false
        ),
        cspRevenue: String(raw?.cspRevenue ?? raw?.csp_revenue ?? ''),
        clientCount: String(raw?.clientCount ?? raw?.client_count ?? ''),
        pcsPerformance: Number(raw?.pcsPerformance ?? raw?.pcs_performance ?? 0) || 0,
        pcsSkilling: Number(raw?.pcsSkilling ?? raw?.pcs_skilling ?? 0) || 0,
        pcsCustomerSuccess: Number(raw?.pcsCustomerSuccess ?? raw?.pcs_customer_success ?? 0) || 0,
        certifications:
          raw?.certifications && typeof raw.certifications === 'object' && !Array.isArray(raw.certifications)
            ? raw.certifications
            : {},
      };

      generateAssessmentPDF(adapted);
    } catch (err) {
      console.error('Erro ao gerar PDF do parceiro:', err);
      alert('Erro ao gerar PDF. Verifique se o servidor está rodando e tente novamente.');
    }
  };

  const handleExportReport = () => {
    // Gerar CSV com dados dos parceiros
    const headers = ['Empresa', 'Contato', 'Email', 'Telefone', 'MPN ID', 'Área de Solução', 'PCS Score', 'Stage EDGE', 'Receita Total', 'Etapa Atual', 'Status', 'Data de Criação'];
    
    const csvData = enrichedPartners.map(p => [
      p.companyName,
      p.contactName,
      p.email,
      p.phone || '',
      p.mpnId || '',
      p.solutionArea || '',
      p.pcsScore || 0,
      p.edgeStage,
      `$${p.totalRevenue.toLocaleString('pt-BR')}`,
      `${p.currentStep}/6`,
      p.status,
      p.createdAt || ''
    ]);

    const csvContent = [
      headers.join(','),
      ...csvData.map(row => row.map(cell => `"${cell}"`).join(','))
    ].join('\n');

    // Criar e baixar arquivo
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `CloudPartner_Report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const getStageColor = (stage: EdgeStage) => {
    switch (stage) {
      case 'Extend': return 'slate';
      case 'Growth': return 'slate';
      case 'Develop': return 'slate';
      case 'Engage': return 'slate';
      default: return 'slate';
    }
  };

  const areaIcons: Record<SolutionArea, React.ReactNode> = {
    'Modern Work': <Briefcase size={14} />,
    'Security': <Shield size={14} />,
    'Azure Infra': <Target size={14} />,
    'Business Applications': <BarChart3 size={14} />,
    'Data & AI': <BarChart3 size={14} />,
    'Digital & App Innovation': <Zap size={14} />,
  };

  const activeAccount = accounts?.[0];
  const userName = activeAccount?.name || activeAccount?.username || 'Administrador';

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button onClick={goBack} className="text-gray-400 hover:text-gray-700 transition-colors">
              <ChevronLeft size={24} />
            </button>
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-gray-800 flex items-center justify-center text-white font-bold text-sm">CP</div>
              <div>
                <h1 className="text-lg font-bold text-gray-900">CloudPartner Hub</h1>
                <p className="text-xs text-gray-500">Admin Dashboard</p>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-full bg-[#005587] flex items-center justify-center text-white font-semibold text-sm">
              {userName.charAt(0).toUpperCase()}
            </div>
            <div className="hidden sm:block">
              <p className="text-sm font-semibold text-gray-900">{userName}</p>
              <div className="flex items-center gap-1.5 text-xs text-gray-500">
                <div className="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                <span>Admin</span>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
        {/* Page Title */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Visão 360° - Parceiros</h1>
            <p className="text-sm text-gray-600">Acompanhe a jornada de cada parceiro na designação CSP.</p>
          </div>
          <Button variant="ghost" className="flex-shrink-0" onClick={handleExportReport}>
            <Download size={16} /> Exportar Relatório
          </Button>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">Parceiros</span>
              <Users size={16} className="text-gray-600" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{kpis.totalPartners}</p>
            <div className="flex items-center gap-1 mt-1 text-xs text-green-700">
              <ArrowUpRight size={12} /> +12% mês
            </div>
          </Card>

          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">Em Progresso</span>
              <PlayCircle size={16} className="text-blue-700" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{journeyAnalytics.inProgressPartners.length}</p>
            <p className="text-xs text-gray-500 mt-1">na jornada</p>
          </Card>

          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">Completados</span>
              <CheckCircle size={16} className="text-green-700" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{journeyAnalytics.completedPartners.length}</p>
            <p className="text-xs text-gray-500 mt-1">{journeyAnalytics.completionRate}% taxa</p>
          </Card>

          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">Estagnados</span>
              <AlertCircle size={16} className="text-orange-600" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{journeyAnalytics.stalledPartners.length}</p>
            <p className="text-xs text-orange-700 mt-1">precisam atenção</p>
          </Card>

          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">PCS Médio</span>
              <TrendingUp size={16} className="text-gray-600" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{kpis.avgPCS}</p>
            <div className="flex items-center gap-1 mt-1 text-xs text-red-700">
              <ArrowDownRight size={12} /> -2 pts
            </div>
          </Card>

          <Card className="p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-500 uppercase">Premier</span>
              <Star size={16} className="text-blue-700" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{kpis.premierCount}</p>
            <p className="text-xs text-gray-500 mt-1">parceiros elite</p>
          </Card>
        </div>

        {/* Journey Pipeline Visualization */}
        <Card className="p-6">
          <h3 className="font-bold text-gray-900 mb-6 flex items-center gap-2">
            <Flag size={20} className="text-gray-700" />
            Funil da Jornada de Designação
          </h3>
          
          <div className="grid grid-cols-6 gap-3 mb-8">
            {journeyAnalytics.stepDistribution.map((step, index) => {
              const stepConfig = JOURNEY_STEPS[index];
              const Icon = stepConfig.icon;
              const maxCount = Math.max(...journeyAnalytics.stepDistribution.map(s => s.count));
              const heightPercent = step.count > 0 ? (step.count / maxCount) * 100 : 5;
              
              return (
                <div key={step.step} className="flex flex-col items-center">
                  {/* Bar */}
                  <div className="w-full h-32 flex flex-col justify-end mb-3">
                    <div 
                      className="w-full bg-gradient-to-t from-blue-600 to-blue-700 rounded-t-lg transition-all hover:from-blue-700 hover:to-blue-800 cursor-pointer relative group"
                      style={{ height: `${heightPercent}%`, minHeight: '20px' }}
                    >
                      <div className="absolute inset-0 flex items-center justify-center">
                        <span className="text-white font-bold text-lg">{step.count}</span>
                      </div>
                      
                      {/* Tooltip on hover */}
                      <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block">
                        <div className="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 whitespace-nowrap">
                          <p className="font-medium">{step.count} parceiros</p>
                          <p className="text-gray-300">Tempo médio: {journeyAnalytics.avgTimePerStep[step.step as keyof typeof journeyAnalytics.avgTimePerStep]}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  {/* Icon & Label */}
                  <div className="flex flex-col items-center gap-2">
                    <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-700">
                      <Icon size={18} />
                    </div>
                    <div className="text-center">
                      <p className="text-xs font-medium text-gray-700">{step.title}</p>
                      <p className="text-xs text-gray-500">{stepConfig.desc}</p>
                    </div>
                  </div>
                  
                  {/* Arrow between steps */}
                  {index < JOURNEY_STEPS.length - 1 && (
                    <ArrowRight size={16} className="text-gray-300 absolute" style={{ left: `${((index + 1) / 6) * 100 - 3}%`, top: '50%' }} />
                  )}
                </div>
              );
            })}
          </div>

          {/* Quick Stats */}
          <div className="grid grid-cols-3 gap-4 pt-6 border-t">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center">
                <CheckCircle size={20} className="text-green-700" />
              </div>
              <div>
                <p className="text-2xl font-bold text-gray-900">{journeyAnalytics.completionRate}%</p>
                <p className="text-xs text-gray-600">Taxa de Conclusão</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                <Clock size={20} className="text-blue-700" />
              </div>
              <div>
                <p className="text-2xl font-bold text-gray-900">24</p>
                <p className="text-xs text-gray-600">dias (tempo médio)</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center">
                <Zap size={20} className="text-gray-700" />
              </div>
              <div>
                <p className="text-2xl font-bold text-gray-900">{kpis.conversionRate}%</p>
                <p className="text-xs text-gray-600">Taxa Advanced+</p>
              </div>
            </div>
          </div>
        </Card>

        {/* Pipeline & Charts Row */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* EDGE Pipeline - takes 2 columns */}
          <Card className="p-5 lg:col-span-2">
            <EdgePipeline 
              partners={enrichedPartners} 
              onStageClick={handlePipelineStageClick}
              selectedStage={filterStage !== 'all' ? filterStage : null}
            />
          </Card>

          {/* Distribution by Area */}
          <Card className="p-5">
            <h4 className="font-bold text-gray-900 mb-4 flex items-center gap-2">
              <BarChart3 size={16} className="text-gray-700" />
              Distribuição por Área
            </h4>
            <div className="space-y-3">
              {areaDistribution.map(item => (
                <div key={item.area} className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-700">
                    {areaIcons[item.area]}
                  </div>
                  <div className="flex-1">
                    <div className="flex justify-between text-sm mb-1">
                      <span className="text-gray-700">{item.area}</span>
                      <span className="font-medium text-gray-900">{item.count}</span>
                    </div>
                    <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-gradient-to-r from-blue-600 to-blue-700 rounded-full"
                        style={{ width: `${(item.count / enrichedPartners.length) * 100}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>

        {/* Filters & Search */}
        <Card className="p-4">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Buscar por nome ou email..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
                value={searchTerm}
                onChange={e => setSearchTerm(e.target.value)}
              />
            </div>
            <div className="flex gap-3">
              <div className="flex items-center gap-2">
                <Filter size={14} className="text-gray-500" />
                <select
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
                  value={filterStage}
                  onChange={e => setFilterStage(e.target.value as EdgeStage | 'all')}
                >
                  <option value="all">Todos os Stages</option>
                  <option value="Engage">Engage</option>
                  <option value="Develop">Develop</option>
                  <option value="Growth">Growth</option>
                  <option value="Extend">Extend</option>
                </select>
              </div>
              <select
                className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
                value={filterArea}
                onChange={e => setFilterArea(e.target.value as SolutionArea | 'all')}
              >
                <option value="all">Todas as Áreas</option>
                <option value="Modern Work">Modern Work</option>
                <option value="Azure Infra">Azure Infra</option>
                <option value="Security">Security</option>
                <option value="Data & AI">Data & AI</option>
                <option value="Digital & App Innovation">Digital & App Innovation</option>
                <option value="Business Applications">Business Applications</option>
              </select>
            </div>
          </div>
        </Card>

        {/* Partners Table */}
        <Card className="overflow-hidden">
          <div className="p-4 bg-gray-50 border-b flex items-center justify-between">
            <h3 className="font-bold text-gray-900">Parceiros na Jornada</h3>
            <Badge variant="slate" className="text-xs">
              {filteredPartners.length} resultados
            </Badge>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 border-b">
                <tr>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Parceiro</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Etapa Atual</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Progresso</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Stage EDGE</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">PCS</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                  <th className="text-left px-4 py-3 font-medium text-gray-600">Próxima Ação</th>
                  <th className="text-center px-4 py-3 font-medium text-slate-600">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {filteredPartners.map(partner => {
                  const currentStepConfig = JOURNEY_STEPS.find(s => s.id === partner.currentStep);
                  const CurrentStepIcon = currentStepConfig?.icon || Briefcase;
                  const progressPercent = ((partner.currentStep || 0) / JOURNEY_STEPS.length) * 100;
                  
                  // Determinar próxima ação recomendada
                  let nextAction = 'Aguardando input';
                  if (partner.status === 'Stalled') {
                    nextAction = 'Reativar parceiro';
                  } else if (partner.currentStep < JOURNEY_STEPS.length) {
                    nextAction = `Avançar para ${JOURNEY_STEPS[partner.currentStep]?.title || 'próxima etapa'}`;
                  } else if (partner.status === 'Completed') {
                    nextAction = 'Acompanhamento';
                  }
                  
                  return (
                    <tr key={partner.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-gray-200 flex items-center justify-center text-blue-800 font-bold text-sm">
                            {partner.companyName.charAt(0)}
                          </div>
                          <div>
                            <p className="font-medium text-gray-900">{partner.companyName}</p>
                            <p className="text-xs text-gray-500">{partner.email}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-800">
                            <CurrentStepIcon size={16} />
                          </div>
                          <div>
                            <p className="text-sm font-medium text-gray-900">{currentStepConfig?.title || 'N/A'}</p>
                            <p className="text-xs text-gray-500">Etapa {partner.currentStep}/{JOURNEY_STEPS.length}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden min-w-[80px]">
                            <div 
                              className="h-full bg-blue-700 rounded-full transition-all"
                              style={{ width: `${progressPercent}%` }}
                            />
                          </div>
                          <span className="text-xs font-medium text-gray-700">{Math.round(progressPercent)}%</span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant={getStageColor(partner.edgeStage) as any}>
                          {partner.edgeStage}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                              className={`h-full rounded-full ${
                                (partner.pcsScore || 0) >= 80 ? 'bg-green-700' :
                                (partner.pcsScore || 0) >= 65 ? 'bg-blue-700' :
                                (partner.pcsScore || 0) >= 50 ? 'bg-orange-600' : 'bg-red-600'
                              }`}
                              style={{ width: `${partner.pcsScore || 0}%` }}
                            />
                          </div>
                          <span className="text-gray-900 font-medium">{partner.pcsScore || 0}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        {partner.status === 'Completed' ? (
                          <span className="flex items-center gap-1 text-xs text-green-700">
                            <CheckCircle2 size={12} /> Concluído
                          </span>
                        ) : partner.status === 'Stalled' ? (
                          <span className="flex items-center gap-1 text-xs text-red-600">
                            <XCircle size={12} /> Estagnado
                          </span>
                        ) : (
                          <span className="flex items-center gap-1 text-xs text-blue-700">
                            <PlayCircle size={12} /> Em progresso
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1 text-xs text-gray-600">
                          <ArrowRight size={12} className="text-gray-400" />
                          {nextAction}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-center gap-2">
                          <button 
                            onClick={() => handleViewPartner(partner)}
                            className="p-1.5 text-gray-400 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors"
                            title="Ver detalhes"
                          >
                            <Eye size={16} />
                          </button>
                          <button 
                            onClick={() => handleDownloadPartnerPDF(partner)}
                            className="p-1.5 text-gray-400 hover:text-green-700 hover:bg-green-50 rounded transition-colors"
                            title="Baixar PDF"
                          >
                            <FileText size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {filteredPartners.length === 0 && (
            <div className="text-center py-12 text-slate-400">
              <Users size={40} className="mx-auto mb-2 opacity-30" />
              <p>Nenhum parceiro encontrado com os filtros aplicados.</p>
            </div>
          )}
        </Card>
      </main>

      {/* Partner Detail Modal */}
      {showDetailModal && selectedPartner && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={closeDetailModal}>
          <Card className="w-full max-w-4xl max-h-[90vh] overflow-y-auto" onClick={(e: React.MouseEvent<HTMLDivElement>) => e.stopPropagation()}>
            {/* Modal Header */}
            <div className="sticky top-0 bg-white border-b p-4 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-100 to-gray-200 flex items-center justify-center text-blue-800 font-bold text-lg">
                  {selectedPartner.companyName.charAt(0)}
                </div>
                <div>
                  <h3 className="font-bold text-gray-900">{selectedPartner.companyName}</h3>
                  <div className="flex flex-wrap items-center gap-2 mt-1">
                    <Badge variant={getStageColor(calculateEdgeStage(selectedPartner)) as any}>
                      {calculateEdgeStage(selectedPartner)}
                    </Badge>
                    {(() => {
                      const readiness = calculateReadiness({
                        companyName: selectedPartner.companyName,
                        email: selectedPartner.email,
                        selectedSolutionArea: selectedPartner.solutionArea,
                        // Admin usa revenueAzure como receita CSP aproximada para prontidão
                        cspRevenue: selectedPartner.revenueAzure ?? 0,
                        pcsScore: selectedPartner.pcsScore ?? 0,
                        certifications: selectedPartner.certifications,
                        status: selectedPartner.status,
                        currentStep: selectedPartner.currentStep,
                      });

                      return (
                        <>
                          <Badge variant="violet">Prontidão: {readiness.score}/100</Badge>
                          {readiness.badges.map(b => (
                            <Badge key={b.label} variant={b.variant as any}>{b.label}</Badge>
                          ))}
                        </>
                      );
                    })()}
                  </div>
                </div>
              </div>
              <button onClick={closeDetailModal} className="text-gray-400 hover:text-gray-700">
                <X size={20} />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-6 space-y-6">
              {/* Journey Progress Timeline */}
              <div className="border rounded-lg p-5 bg-gradient-to-br from-gray-50 to-white">
                <h4 className="font-bold text-gray-900 mb-5 flex items-center gap-2">
                  <Flag size={16} className="text-gray-700" />
                  Progresso na Jornada de Designação
                </h4>
                
                <div className="relative">
                  {/* Progress Line */}
                  <div className="absolute top-8 left-0 right-0 h-1 bg-gray-200">
                    <div 
                      className="h-full bg-blue-700 transition-all"
                      style={{ width: `${((selectedPartner.currentStep || 0) / JOURNEY_STEPS.length) * 100}%` }}
                    />
                  </div>
                  
                  {/* Steps */}
                  <div className="grid grid-cols-6 gap-2 relative">
                    {JOURNEY_STEPS.map((step) => {
                      const StepIcon = step.icon;
                      const isCompleted = (selectedPartner.currentStep || 0) > step.id;
                      const isCurrent = selectedPartner.currentStep === step.id;
                      const isPending = (selectedPartner.currentStep || 0) < step.id;
                      
                      return (
                        <div key={step.id} className="flex flex-col items-center">
                          <div className={`
                            w-16 h-16 rounded-full border-4 flex items-center justify-center mb-2 transition-all relative z-10
                            ${isCompleted ? 'bg-green-700 border-green-700 text-white' : ''}
                            ${isCurrent ? 'bg-blue-700 border-blue-700 text-white animate-pulse' : ''}
                            ${isPending ? 'bg-white border-gray-300 text-gray-400' : ''}
                          `}>
                            {isCompleted ? (
                              <CheckCircle size={24} />
                            ) : (
                              <StepIcon size={24} />
                            )}
                          </div>
                          <p className={`text-xs font-medium text-center ${isCurrent ? 'text-blue-800' : 'text-gray-700'}`}>
                            {step.title}
                          </p>
                          <p className="text-xs text-gray-500 text-center">{step.desc}</p>
                        </div>
                      );
                    })}
                  </div>
                </div>
                
                {/* Current Status */}
                <div className="mt-6 p-4 bg-white rounded-lg border flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    {selectedPartner.status === 'Completed' ? (
                      <CheckCircle className="text-green-700" size={24} />
                    ) : selectedPartner.status === 'Stalled' ? (
                      <PauseCircle className="text-orange-600" size={24} />
                    ) : (
                      <PlayCircle className="text-blue-700" size={24} />
                    )}
                    <div>
                      <p className="font-medium text-gray-900">
                        {selectedPartner.status === 'Completed' ? 'Jornada Concluída!' : 
                         selectedPartner.status === 'Stalled' ? 'Parceiro Estagnado' :
                         'Em Progresso'}
                      </p>
                      <p className="text-sm text-gray-600">
                        {selectedPartner.status === 'Completed' ? 'Todas as etapas foram completadas' :
                         selectedPartner.status === 'Stalled' ? 'Requer atenção imediata' :
                         `Atualmente na etapa: ${JOURNEY_STEPS.find(s => s.id === selectedPartner.currentStep)?.title || 'N/A'}`}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-bold text-gray-900">
                      {Math.round(((selectedPartner.currentStep || 0) / JOURNEY_STEPS.length) * 100)}%
                    </p>
                    <p className="text-xs text-gray-600">completo</p>
                  </div>
                </div>
              </div>

              {/* TD SYNNEX Association Alert */}
              {selectedPartner.isMicrosoftPartner && !selectedPartner.isTdSynnexRegistered && (
                <div className="border-l-4 border-blue-600 bg-blue-50 p-4 rounded-lg">
                  <div className="flex items-start gap-3">
                    <AlertCircle size={20} className="text-blue-600 mt-0.5" />
                    <div className="flex-1">
                      <h5 className="font-bold text-blue-900 mb-2">Associe-se à TD SYNNEX</h5>
                      <p className="text-sm text-blue-800 mb-3">
                        Este parceiro possui MPN ID mas não está associado à TD SYNNEX. 
                        Complete a associação para desbloquear benefícios exclusivos, suporte técnico e incentivos financeiros.
                      </p>
                      <a 
                        href="https://www.tdsynnex.com/br/parceiro-microsoft/" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                      >
                        <ArrowRight size={16} />
                        Associar-se à TD SYNNEX
                      </a>
                    </div>
                  </div>
                </div>
              )}

              {/* Contact Info */}
              <div className="grid grid-cols-2 gap-4">
                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Mail size={16} className="text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">Email</p>
                    <p className="text-sm font-medium text-gray-900">{selectedPartner.email}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Phone size={16} className="text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">Telefone</p>
                    <p className="text-sm font-medium text-gray-900">{selectedPartner.phone || 'Não informado'}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Building2 size={16} className="text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">MPN ID</p>
                    <p className="text-sm font-medium text-gray-900">{selectedPartner.mpnId || 'Não informado'}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Calendar size={16} className="text-gray-500" />
                  <div>
                    <p className="text-xs text-gray-500">Data de Cadastro</p>
                    <p className="text-sm font-medium text-gray-900">
                      {selectedPartner.createdAt ? new Date(selectedPartner.createdAt).toLocaleDateString('pt-BR') : 'N/A'}
                    </p>
                  </div>
                </div>
              </div>

              {/* PCS Score Section */}
              <div className="border rounded-lg p-4">
                <h4 className="font-bold text-gray-900 mb-4 flex items-center gap-2">
                  <TrendingUp size={16} className="text-gray-700" />
                  Partner Capability Score (PCS)
                </h4>
                <div className="flex items-center gap-4 mb-4">
                  <div className="w-20 h-20 rounded-full border-4 border-gray-700 flex items-center justify-center">
                    <span className="text-2xl font-bold text-gray-900">{selectedPartner.pcsScore || 0}</span>
                  </div>
                  <div className="flex-1">
                    <div className="h-3 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-gradient-to-r from-blue-700 to-gray-700 rounded-full transition-all"
                        style={{ width: `${selectedPartner.pcsScore || 0}%` }}
                      />
                    </div>
                    <div className="flex justify-between text-xs text-gray-500 mt-1">
                      <span>0</span>
                      <span>Authorized (50)</span>
                      <span>Advanced (65)</span>
                      <span>Premier (80)</span>
                      <span>100</span>
                    </div>
                  </div>
                </div>

                {/* PCS Breakdown */}
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div className="p-2 bg-blue-50 rounded">
                    <span className="text-blue-800">Performance:</span> 
                    <span className="font-medium ml-1">{Math.round((selectedPartner.pcsScore || 0) * 0.4)}/40</span>
                  </div>
                  <div className="p-2 bg-gray-100 rounded">
                    <span className="text-gray-800">Skilling:</span> 
                    <span className="font-medium ml-1">{Math.round((selectedPartner.pcsScore || 0) * 0.25)}/25</span>
                  </div>
                  <div className="p-2 bg-green-50 rounded">
                    <span className="text-green-800">Customer Success:</span> 
                    <span className="font-medium ml-1">{Math.round((selectedPartner.pcsScore || 0) * 0.2)}/20</span>
                  </div>
                  <div className="p-2 bg-orange-50 rounded">
                    <span className="text-orange-800">Innovation:</span> 
                    <span className="font-medium ml-1">{Math.round((selectedPartner.pcsScore || 0) * 0.15)}/15</span>
                  </div>
                </div>
              </div>

              {/* Revenue Section */}
              <div className="border rounded-lg p-4">
                <h4 className="font-bold text-gray-900 mb-4 flex items-center gap-2">
                  <DollarSign size={16} className="text-green-700" />
                  Receita por Workload
                </h4>
                <div className="space-y-3">
                  {[
                    { label: 'Microsoft 365', value: selectedPartner.revenueM365 || 0, color: 'blue' },
                    { label: 'Azure', value: selectedPartner.revenueAzure || 0, color: 'gray' },
                    { label: 'Security', value: selectedPartner.revenueSecurity || 0, color: 'green' },
                  ].map(item => (
                    <div key={item.label}>
                      <div className="flex justify-between text-sm mb-1">
                        <span className="text-gray-700">{item.label}</span>
                        <span className="font-medium text-gray-900">${item.value.toLocaleString()}</span>
                      </div>
                      <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div 
                          className={`h-full bg-${item.color}-500 rounded-full`}
                          style={{ width: `${Math.min(100, (item.value / 100000) * 100)}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
                <div className="mt-4 pt-3 border-t flex justify-between">
                  <span className="font-medium text-gray-700">Total</span>
                  <span className="font-bold text-lg text-green-700">
                    ${((selectedPartner.revenueM365 || 0) + (selectedPartner.revenueAzure || 0) + (selectedPartner.revenueSecurity || 0)).toLocaleString()}
                  </span>
                </div>
              </div>

              {/* Skilling Section */}
              <div className="border rounded-lg p-4">
                <h4 className="font-bold text-gray-900 mb-4 flex items-center gap-2">
                  <GraduationCap size={16} className="text-gray-700" />
                  Certificações ({selectedPartner.solutionArea})
                </h4>
                <div className="grid grid-cols-1 gap-2">
                  {loadingPartnerDetails ? (
                    <p className="text-sm text-gray-500 italic">Carregando certificações...</p>
                  ) : (selectedPartnerCertifications && Object.keys(selectedPartnerCertifications).length > 0) ? (
                    Object.entries(selectedPartnerCertifications)
                      .filter(([, qty]) => (Number(qty) || 0) > 0)
                      .map(([cert, qty]) => (
                        <div key={cert} className="flex items-center justify-between gap-2 p-2 bg-green-50 rounded">
                          <div className="flex items-center gap-2">
                            <CheckCircle2 size={14} className="text-green-700" />
                            <span className="text-sm text-gray-900">{cert}</span>
                          </div>
                          <Badge variant="emerald">x{Number(qty) || 0}</Badge>
                        </div>
                      ))
                  ) : selectedPartner.certifications?.length ? (
                    selectedPartner.certifications.map((cert, i) => (
                      <div key={i} className="flex items-center gap-2 p-2 bg-green-50 rounded">
                        <CheckCircle2 size={14} className="text-green-700" />
                        <span className="text-sm text-gray-900">{cert}</span>
                      </div>
                    ))
                  ) : (
                    <p className="text-sm text-gray-500 italic">Nenhuma certificação registrada.</p>
                  )}
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-4 border-t">
                <Button variant="primary" className="flex-1" onClick={() => handleDownloadPartnerPDF(selectedPartner)}>
                  <FileText size={16} /> Baixar Plano PDF
                </Button>
                <Button variant="ghost" onClick={closeDetailModal}>
                  Fechar
                </Button>
              </div>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};

export default AdminPage;
