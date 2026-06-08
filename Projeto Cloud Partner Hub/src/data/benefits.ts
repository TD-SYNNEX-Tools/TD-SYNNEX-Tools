import { SolutionArea, EdgeStage } from '../types';

export interface Benefit {
  id: string;
  name: string;
  description: string;
  icon: string; // lucide icon name
  category: 'tool' | 'training' | 'funding' | 'support' | 'marketing';
  solutionAreas: SolutionArea[]; // áreas que podem desbloquear
  requirements: {
    minPcsScore?: number;
    minStage?: EdgeStage;
    minCertifications?: number;
    specificCertifications?: string[];
    minRevenue?: number;
  };
  value?: string; // valor estimado do benefício
  link?: string; // link externo se houver
  isNew?: boolean;
}

// Ordem de stages para comparação
export const STAGE_ORDER: Record<EdgeStage, number> = {
  'Engage': 1,
  'Develop': 2,
  'Growth': 3,
  'Extend': 4
};

export const BENEFITS: Benefit[] = [
  // === FERRAMENTAS ===
  {
    id: 'azure-migration-analyzer',
    name: 'Azure Migration Analyzer',
    description: 'Ferramenta interna para análise automatizada de migrações Azure. Gera relatórios de assessment, sizing e estimativa de custos.',
    icon: 'Cloud',
    category: 'tool',
    solutionAreas: ['Azure Infra', 'Data & AI', 'Digital & App Innovation'],
    requirements: {
      minPcsScore: 40,
      minStage: 'Develop'
    },
    value: 'R$ 15.000/ano',
    isNew: true
  },
  {
    id: 'security-posture-scanner',
    name: 'Security Posture Scanner',
    description: 'Scanner de postura de segurança para ambientes Microsoft 365 e Azure. Identifica gaps e recomenda remediações.',
    icon: 'Shield',
    category: 'tool',
    solutionAreas: ['Security'],
    requirements: {
      minPcsScore: 50,
      minStage: 'Develop',
      minCertifications: 2
    },
    value: 'R$ 20.000/ano'
  },
  {
    id: 'm365-adoption-toolkit',
    name: 'M365 Adoption Toolkit',
    description: 'Kit completo para acelerar adoção de Microsoft 365 com templates, materiais de treinamento e dashboards de uso.',
    icon: 'Users',
    category: 'tool',
    solutionAreas: ['Modern Work'],
    requirements: {
      minPcsScore: 30,
      minStage: 'Engage'
    },
    value: 'R$ 8.000/ano'
  },
  {
    id: 'copilot-demo-environment',
    name: 'Copilot Demo Environment',
    description: 'Ambiente de demonstração totalmente configurado para showcases de Microsoft Copilot com dados fictícios realistas.',
    icon: 'Sparkles',
    category: 'tool',
    solutionAreas: ['Modern Work', 'Data & AI'],
    requirements: {
      minPcsScore: 60,
      minStage: 'Growth'
    },
    value: 'R$ 25.000/ano',
    isNew: true
  },
  {
    id: 'power-platform-accelerator',
    name: 'Power Platform Accelerator',
    description: 'Biblioteca de componentes, templates e soluções pré-construídas para Power Apps, Power Automate e Power BI.',
    icon: 'Zap',
    category: 'tool',
    solutionAreas: ['Business Applications', 'Digital & App Innovation'],
    requirements: {
      minPcsScore: 45,
      minStage: 'Develop'
    },
    value: 'R$ 12.000/ano'
  },

  // === TREINAMENTOS ===
  {
    id: 'certification-bootcamp',
    name: 'Bootcamp de Certificações',
    description: 'Acesso a bootcamps intensivos para certificações Microsoft com instrutores especializados e vouchers inclusos.',
    icon: 'GraduationCap',
    category: 'training',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 20,
      minStage: 'Engage'
    },
    value: 'R$ 5.000/pessoa'
  },
  {
    id: 'executive-briefing',
    name: 'Executive Briefing Center',
    description: 'Sessões exclusivas no Microsoft Technology Center com executivos e arquitetos Microsoft.',
    icon: 'Building2',
    category: 'training',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 70,
      minStage: 'Growth'
    },
    value: 'Exclusivo'
  },
  {
    id: 'technical-deep-dive',
    name: 'Technical Deep Dive Sessions',
    description: 'Workshops técnicos avançados com Product Groups da Microsoft sobre roadmap e features em preview.',
    icon: 'Code',
    category: 'training',
    solutionAreas: ['Azure Infra', 'Data & AI', 'Digital & App Innovation'],
    requirements: {
      minPcsScore: 80,
      minStage: 'Extend',
      minCertifications: 5
    },
    value: 'Exclusivo'
  },

  // === FUNDING ===
  {
    id: 'poc-funding',
    name: 'POC Funding',
    description: 'Funding para provas de conceito com clientes. Até R$ 30.000 por projeto aprovado.',
    icon: 'DollarSign',
    category: 'funding',
    solutionAreas: ['Azure Infra', 'Security', 'Data & AI', 'Digital & App Innovation'],
    requirements: {
      minPcsScore: 50,
      minStage: 'Develop'
    },
    value: 'Até R$ 30.000'
  },
  {
    id: 'marketing-development-fund',
    name: 'Marketing Development Fund (MDF)',
    description: 'Verba para campanhas de marketing, eventos e geração de demanda. Matching de 50% a 100%.',
    icon: 'Megaphone',
    category: 'funding',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 60,
      minStage: 'Growth'
    },
    value: 'Até R$ 100.000/ano'
  },
  {
    id: 'solution-incentive',
    name: 'Solution Partner Incentive',
    description: 'Incentivo financeiro adicional por workloads ativados e crescimento de consumo Azure.',
    icon: 'TrendingUp',
    category: 'funding',
    solutionAreas: ['Azure Infra', 'Data & AI'],
    requirements: {
      minPcsScore: 70,
      minStage: 'Growth',
      minRevenue: 50000
    },
    value: 'Até 15% extra'
  },

  // === SUPORTE ===
  {
    id: 'partner-support-priority',
    name: 'Suporte Prioritário',
    description: 'Acesso a fila prioritária de suporte técnico Microsoft com SLA reduzido.',
    icon: 'Headphones',
    category: 'support',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 40,
      minStage: 'Develop'
    },
    value: 'SLA 4h'
  },
  {
    id: 'dedicated-tam',
    name: 'Technical Account Manager',
    description: 'TAM dedicado para acompanhamento estratégico e técnico dos seus projetos.',
    icon: 'UserCheck',
    category: 'support',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 80,
      minStage: 'Extend'
    },
    value: 'Dedicado'
  },
  {
    id: 'presales-support',
    name: 'Suporte Pré-Vendas',
    description: 'Arquitetos de solução Microsoft disponíveis para apoiar propostas e RFPs.',
    icon: 'FileText',
    category: 'support',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 55,
      minStage: 'Growth'
    },
    value: 'Ilimitado'
  },

  // === MARKETING ===
  {
    id: 'partner-showcase',
    name: 'Partner Showcase',
    description: 'Destaque no portal de parceiros Microsoft e eventos regionais.',
    icon: 'Star',
    category: 'marketing',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 70,
      minStage: 'Growth'
    },
    value: 'Visibilidade'
  },
  {
    id: 'case-study-production',
    name: 'Produção de Case Study',
    description: 'Produção profissional de case study com cliente para uso em marketing.',
    icon: 'Video',
    category: 'marketing',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 60,
      minStage: 'Growth'
    },
    value: 'R$ 15.000'
  },
  {
    id: 'logo-usage-rights',
    name: 'Direitos de Uso de Logo',
    description: 'Autorização para uso de logos Microsoft Partner em materiais de marketing.',
    icon: 'Award',
    category: 'marketing',
    solutionAreas: ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
    requirements: {
      minPcsScore: 30,
      minStage: 'Engage'
    },
    value: 'Incluído'
  }
];

export const CATEGORY_LABELS: Record<Benefit['category'], string> = {
  tool: 'Ferramentas',
  training: 'Treinamentos',
  funding: 'Funding',
  support: 'Suporte',
  marketing: 'Marketing'
};

export const CATEGORY_COLORS: Record<Benefit['category'], string> = {
  tool: 'bg-purple-100 text-purple-700 border-purple-200',
  training: 'bg-blue-100 text-blue-700 border-blue-200',
  funding: 'bg-green-100 text-green-700 border-green-200',
  support: 'bg-orange-100 text-orange-700 border-orange-200',
  marketing: 'bg-pink-100 text-pink-700 border-pink-200'
};
