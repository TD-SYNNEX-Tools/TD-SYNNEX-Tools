import React, { useState, useEffect, useCallback } from 'react';
import { 
  CheckCircle, 
  ChevronRight, 
  BarChart3, 
  Users, 
  Award, 
  Briefcase, 
  FileText,
  Lock,
  ArrowRight,
  TrendingUp,
  ShieldCheck,
  Trophy,
  Rocket,
  Shield,
  Zap,
  User,
  AlertCircle,
  Filter,
  BookOpen,
  DollarSign,
  X,
  Calculator,
  Target,
  Megaphone,
  GraduationCap,
  Flag,
  ExternalLink,
  LogOut,
  Settings,
  Phone,
  ChevronDown,
  Gift
} from 'lucide-react';
import AdminPage from './src/pages/AdminPage';
import BenefitsPage from './src/pages/BenefitsPage';
import { usePartnerData } from './src/services/usePartnerData';
import { Badge } from './src/components/ui';
import { calculateReadiness } from './src/utils/readiness';
import { useIsAuthenticated, useMsal } from '@azure/msal-react';
import { loginRequest, isEntraConfigured } from './src/auth/msalConfig';

// Logo Microsoft Azure customizado
const AzureLogo = ({ size = 24 }: { size?: number }) => (
  <svg width={size} height={size} viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M5.33492 1.37491C5.44717 1.04229 5.75909 0.818359 6.11014 0.818359H11.25L5.91513 16.6255C5.80287 16.9581 5.49095 17.182 5.13991 17.182H1.13968C0.579936 17.182 0.185466 16.6325 0.364461 16.1022L5.33492 1.37491Z" fill="url(#paint0_linear_6102_134469)"/>
    <path d="M13.5517 11.4546H5.45126C5.1109 11.4546 4.94657 11.8715 5.19539 12.1037L10.4005 16.9618C10.552 17.1032 10.7515 17.1819 10.9587 17.1819H15.5453L13.5517 11.4546Z" fill="#0078D4"/>
    <path d="M6.11014 0.818359C5.75909 0.818359 5.44717 1.04229 5.33492 1.37491L0.364461 16.1022C0.185466 16.6325 0.579936 17.182 1.13968 17.182H5.13991C5.49095 17.182 5.80287 16.9581 5.91513 16.6255L6.90327 13.6976L10.4005 16.9617C10.552 17.1032 10.7515 17.1818 10.9588 17.1818H15.5454L13.5517 11.4545H7.66032L11.25 0.818359H6.11014Z" fill="url(#paint1_linear_6102_134469)"/>
    <path d="M12.665 1.37478C12.5528 1.04217 12.2409 0.818237 11.8898 0.818237H6.13629H6.16254C6.51358 0.818237 6.82551 1.04217 6.93776 1.37478L11.9082 16.1021C12.0872 16.6324 11.6927 17.1819 11.133 17.1819H11.0454H16.8603C17.42 17.1819 17.8145 16.6324 17.6355 16.1021L12.665 1.37478Z" fill="url(#paint2_linear_6102_134469)"/>
    <defs>
      <linearGradient id="paint0_linear_6102_134469" x1="6.07512" y1="1.38476" x2="0.738178" y2="17.1514" gradientUnits="userSpaceOnUse">
        <stop stopColor="#114A8B"/>
        <stop offset="1" stopColor="#0669BC"/>
      </linearGradient>
      <linearGradient id="paint1_linear_6102_134469" x1="10.3402" y1="11.4564" x2="9.107" y2="11.8734" gradientUnits="userSpaceOnUse">
        <stop stopOpacity="0.3"/>
        <stop offset="0.0711768" stopOpacity="0.2"/>
        <stop offset="0.321031" stopOpacity="0.1"/>
        <stop offset="0.623053" stopOpacity="0.05"/>
        <stop offset="1" stopOpacity="0"/>
      </linearGradient>
      <linearGradient id="paint2_linear_6102_134469" x1="9.45858" y1="1.38467" x2="15.3168" y2="16.9926" gradientUnits="userSpaceOnUse">
        <stop stopColor="#3CCBF4"/>
        <stop offset="1" stopColor="#2892DF"/>
      </linearGradient>
    </defs>
  </svg>
);

// Logo Microsoft Security customizado
const SecurityLogo = ({ size = 24 }: { size?: number }) => (
  <svg width={size} height={size} viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="security-gradient" x1="9" y1="16.795" x2="9" y2="1.205" gradientUnits="userSpaceOnUse">
        <stop offset="0" stopColor="#0078d4"/>
        <stop offset="0.064" stopColor="#0a7cd7"/>
        <stop offset="0.338" stopColor="#2e8ce1"/>
        <stop offset="0.594" stopColor="#4897e9"/>
        <stop offset="0.822" stopColor="#589eed"/>
        <stop offset="1" stopColor="#5ea0ef"/>
      </linearGradient>
    </defs>
    <path d="M15.5,8.485c0,4.191-5.16,7.566-6.282,8.25a.412.412,0,0,1-.428,0C7.664,16.051,2.5,12.676,2.5,8.485V3.441a.4.4,0,0,1,.4-.4C6.916,2.935,5.992,1.205,9,1.205s2.084,1.73,6.1,1.837a.4.4,0,0,1,.4.4Z" fill="url(#security-gradient)"/>
  </svg>
);

// Logo Microsoft 365 (Modern Work)
const ModernWorkLogo = ({ size = 24 }: { size?: number }) => (
  <img 
    src="https://upload.wikimedia.org/wikipedia/commons/thumb/0/0e/Microsoft_365_%282022%29.svg/1862px-Microsoft_365_%282022%29.svg.png" 
    alt="Microsoft 365" 
    width={size} 
    height={size}
    className="object-contain"
  />
);

// Logo Business Applications
const BusinessAppLogo = ({ size = 24 }: { size?: number }) => (
  <img 
    src="https://venturesystemsgroup.com/wp-content/uploads/2024/10/VEN_icon_Microsoft365.webp" 
    alt="Business Applications" 
    width={size} 
    height={size}
    className="object-contain"
  />
);

// Logo Data & AI
const DataAILogo = ({ size = 24 }: { size?: number }) => (
  <img 
    src="https://cdn-icons-png.flaticon.com/512/7911/7911546.png" 
    alt="Data & AI" 
    width={size} 
    height={size}
    className="object-contain"
  />
);

// --- Types & Interfaces ---

type SolutionArea = 'Modern Work' | 'Azure Infra' | 'Security' | 'Data & AI' | 'Digital & App Innovation' | 'Business Applications';
type GTMCategory = 'Digital' | 'Eventos' | 'Capacitação';
type GTMSolutionFocus = 'AI Business Solutions' | 'Cloud AI Platform' | 'Security';

interface SkillingRule {
  intermediate: string[];
  advanced: string[];
  specializations: string[];
  resources: string[];
}

interface PCSMaxScores {
  performance: number;
  skilling: number;
  customerSuccess: number;
}

const PCS_MAX_SCORES: Record<SolutionArea, PCSMaxScores> = {
  'Modern Work': {
    performance: 20,
    skilling: 25,
    customerSuccess: 55
  },
  'Azure Infra': {
    performance: 30,
    skilling: 40,
    customerSuccess: 30
  },
  'Security': {
    performance: 20,
    skilling: 40,
    customerSuccess: 40
  },
  'Data & AI': {
    performance: 30,
    skilling: 40,
    customerSuccess: 30
  },
  'Digital & App Innovation': {
    performance: 30,
    skilling: 40,
    customerSuccess: 30
  },
  'Business Applications': {
    performance: 15,
    skilling: 35,
    customerSuccess: 50
  }
};

const SKILLING_RULES: Record<SolutionArea, SkillingRule> = {
  'Azure Infra': {
    intermediate: ['AZ-104: Azure Administrator Associate'],
    advanced: ['AZ-305: Azure Solutions Architect Expert'],
    specializations: ['Azure Virtual Desktop', 'Azure VMware Solution', 'SAP on Azure'],
    resources: ['Azure Cloud Week', 'Migrate & Secure Workshop', 'Azure Sales Bootcamp']
  },
  'Data & AI': {
    intermediate: ['AZ-104', 'DP-300 (Database)', 'AI-102 (AI Engineer)', 'DP-100 (Data Scientist)', 'DP-203 (Data Engineer)'],
    advanced: ['AZ-305: Azure Solutions Architect Expert'],
    specializations: ['AI and Machine Learning', 'Analytics on Azure', 'Data Warehouse Migration'],
    resources: ['AI Bootcamps', 'Microsoft Fabric Workshop', 'Azure OpenAI Workshop']
  },
  'Digital & App Innovation': {
    intermediate: ['AZ-104', 'AZ-204 (Developer)', 'PL-400 (Power Platform Dev)'],
    advanced: ['AZ-305', 'AZ-400 (DevOps Engineer)'],
    specializations: ['DevOps with GitHub', 'Kubernetes on Azure', 'Modernize Enterprise Apps'],
    resources: ['Build & Modernize AI Apps Workshop', 'App Innovation Cloud Week']
  },
  'Modern Work': {
    intermediate: ['MS-900 (Fundamentals)', 'MD-102 (Modern Desktop)', 'MS-700 (Teams)', 'MS-721 (Collab Engineer)', 'SC-300 (Identity)'],
    advanced: ['MS-102: Enterprise Administrator Expert'],
    specializations: ['Adoption and Change Management', 'Calling for Microsoft Teams', 'Teamwork Deployment'],
    resources: ['Modern Work Cloud Week', 'Copilot for M365 Sales Bootcamp', 'CSP Masters Program']
  },
  'Security': {
    intermediate: ['AZ-500 (Azure Security)', 'SC-200 (Ops Analyst)', 'SC-300 (Identity)', 'SC-400 (Info Protection)'],
    advanced: ['SC-100: Cybersecurity Architect Expert'],
    specializations: ['Cloud Security', 'Identity and Access Management', 'Threat Protection'],
    resources: ['Security Cloud Week', 'Microsoft Copilot for Security Bootcamp', 'Shadow IT Workshop']
  },
  'Business Applications': {
    intermediate: ['MB-210 (Sales)', 'MB-220 (Customer Insights)', 'MB-230 (Service)', 'MB-800 (Business Central)', 'PL-200 (Power Platform)'],
    advanced: ['MB-335 (Supply Chain Expert)', 'MB-700 (Finance Architect)', 'PL-600 (Solution Architect)'],
    specializations: ['Low Code App Development', 'Small and Midsize Business Management', 'Intelligent Automation'],
    resources: ['Business Applications Cloud Week', 'SMB Sales Bootcamp', 'Catalyst Partner Training']
  }
};

interface PartnerData {
  id: string;
  companyName: string;
  contactName: string;
  email: string;
  isTdSynnexRegistered: boolean;
  isMicrosoftPartner: boolean;
  partnerTypeInterest: string; 
  selectedSolutionArea: SolutionArea | '';
  cspRevenue: string; 
  clientCount: string; 
  services: string[];
  pcsPerformance: number;
  pcsSkilling: number;
  pcsCustomerSuccess: number;
  certifications: { [key: string]: number }; // { "AZ-104": 3, "AZ-305": 1 }
  currentStep: number;
  status: 'In Progress' | 'Completed' | 'Stalled';
  createdAt: string;
}

interface GTMActivity {
  id: string;
  category: GTMCategory;
  focus: GTMSolutionFocus;
  name: string;
  investment: number;
  roiMultiplier: number; 
  pipelineGenerated: number;
}

// Importar a função de geração de PDF do assessment
const generateAssessmentPDF = (partner: PartnerData): void => {
  const totalPCS = partner.pcsPerformance + partner.pcsSkilling + partner.pcsCustomerSuccess;
  const isPassing = totalPCS >= 70;
  const currentLevel = isPassing ? 'Solution Partner' : 'Registered';
  const targetLevel = 'Solution Partner';
  
  const CERTIFICATIONS_MAP: Record<string, { intermediate: string[], advanced: string[], specializations: string[] }> = {
    'Azure Infra': {
      intermediate: ['AZ-104: Azure Administrator Associate'],
      advanced: ['AZ-305: Azure Solutions Architect Expert'],
      specializations: ['Azure Virtual Desktop', 'Azure VMware Solution', 'SAP on Azure']
    },
    'Data & AI': {
      intermediate: ['AZ-104', 'DP-300 (Database)', 'AI-102 (AI Engineer)', 'DP-100 (Data Scientist)', 'DP-203 (Data Engineer)'],
      advanced: ['AZ-305: Azure Solutions Architect Expert'],
      specializations: ['AI and Machine Learning', 'Analytics on Azure', 'Data Warehouse Migration']
    },
    'Digital & App Innovation': {
      intermediate: ['AZ-104', 'AZ-204 (Developer)', 'PL-400 (Power Platform Dev)'],
      advanced: ['AZ-305', 'AZ-400 (DevOps Engineer)'],
      specializations: ['DevOps with GitHub', 'Kubernetes on Azure', 'Modernize Enterprise Apps']
    },
    'Modern Work': {
      intermediate: ['MS-900 (Fundamentals)', 'MD-102 (Modern Desktop)', 'MS-700 (Teams)', 'MS-721 (Collab Engineer)', 'SC-300 (Identity)'],
      advanced: ['MS-102: Enterprise Administrator Expert'],
      specializations: ['Adoption and Change Management', 'Calling for Microsoft Teams', 'Teamwork Deployment']
    },
    'Security': {
      intermediate: ['AZ-500 (Azure Security)', 'SC-200 (Ops Analyst)', 'SC-300 (Identity)', 'SC-400 (Info Protection)'],
      advanced: ['SC-100: Cybersecurity Architect Expert'],
      specializations: ['Cloud Security', 'Identity and Access Management', 'Threat Protection']
    },
    'Business Applications': {
      intermediate: ['MB-210 (Sales)', 'MB-220 (Customer Insights)', 'MB-230 (Service)', 'MB-800 (Business Central)', 'PL-200 (Power Platform)'],
      advanced: ['MB-335 (Supply Chain Expert)', 'MB-700 (Finance Architect)', 'PL-600 (Solution Architect)'],
      specializations: ['Low Code App Development', 'Small and Midsize Business Management', 'Intelligent Automation']
    }
  };
  
  const certifications = CERTIFICATIONS_MAP[partner.selectedSolutionArea as string] || { intermediate: [], advanced: [], specializations: [] };
  
  // Calcular pontos de certificações baseado na Solution Area
  // Azure Infra: 4 pontos por certificação
  // Modern Work: 2.5 pontos (intermediária) e 7.5 pontos (avançada)
  // Security: 6.67 pontos por certificação intermediária (somente intermediárias)
  // Digital & App Innovation: 4 pontos por certificação (intermediária e avançada)
  // Outras: 4 pontos por certificação (padrão)
  const totalCertifications = Object.values(partner.certifications || {}).reduce((sum: number, qty: any) => sum + (Number(qty) || 0), 0);
  
  let certificationPoints = 0;
  if (partner.selectedSolutionArea === 'Modern Work') {
    // Para Modern Work, precisamos calcular separadamente intermediárias e avançadas
    // Como não temos essa separação nos dados, vamos usar uma estimativa conservadora
    // Assumindo proporção 70% intermediárias (2.5pts) e 30% avançadas (7.5pts)
    const avgPointsModernWork = (0.7 * 2.5) + (0.3 * 7.5); // = 4 pontos em média
    certificationPoints = totalCertifications * avgPointsModernWork;
  } else if (partner.selectedSolutionArea === 'Azure Infra') {
    certificationPoints = totalCertifications * 4;
  } else if (partner.selectedSolutionArea === 'Security') {
    certificationPoints = totalCertifications * 6.67; // Somente intermediárias
  } else if (partner.selectedSolutionArea === 'Digital & App Innovation') {
    certificationPoints = totalCertifications * 4; // 4 pontos para intermediárias e avançadas
  } else {
    certificationPoints = totalCertifications * 4; // Outras áreas: 4 pontos por padrão
  }
  
  const skillingSelected = partner.pcsSkilling;
  const certificationGap = skillingSelected - certificationPoints;
  
  // Criar documento HTML para conversão em PDF
  const htmlContent = `
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif; 
            line-height: 1.5; 
            color: #2c2c2c; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 50px 40px;
            background: #ffffff;
        }
        .header { 
            text-align: left; 
            border-bottom: 1px solid #d0d0d0; 
            padding-bottom: 25px; 
            margin-bottom: 40px; 
        }
        .header h1 { 
            color: #1a1a1a; 
            font-size: 24px; 
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .header p { 
            color: #6a6a6a; 
            font-size: 13px; 
        }
        .logos {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .logo-text {
            font-size: 12px;
            font-weight: 600;
            color: #4a4a4a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section { 
            margin: 35px 0; 
            padding: 0; 
            background: transparent;
        }
        .section h2 { 
            color: #1a1a1a; 
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e8e8e8;
        }
        .section h3 { 
            color: #2c2c2c; 
            font-size: 14px;
            font-weight: 600;
            margin: 20px 0 12px 0; 
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            margin: 20px 0; 
        }
        .info-item { 
            background: #fafafa; 
            padding: 16px; 
            border-radius: 2px; 
            border: 1px solid #e5e5e5;
        }
        .info-label { 
            font-size: 10px; 
            color: #6a6a6a; 
            text-transform: uppercase; 
            font-weight: 600; 
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .info-value { 
            font-size: 15px; 
            color: #2c2c2c; 
            font-weight: 500; 
        }
        .pcs-score { 
            text-align: center; 
            background: #fafafa; 
            padding: 30px; 
            border-radius: 2px; 
            margin: 25px 0;
            border: 1px solid #d0d0d0;
        }
        .pcs-score .score { 
            font-size: 42px; 
            font-weight: 600; 
            color: #2c2c2c; 
        }
        .pcs-score .label { 
            font-size: 12px; 
            color: #6a6a6a; 
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 2px;
            font-size: 11px;
            font-weight: 600;
            background: ${isPassing ? '#5a5a5a' : '#7a7a7a'};
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pcs-breakdown { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 20px; 
            margin: 25px 0; 
        }
        .pcs-item { 
            background: #fafafa; 
            padding: 20px; 
            text-align: center; 
            border-radius: 2px;
            border: 1px solid #e5e5e5;
        }
        .pcs-item .title { 
            font-size: 10px; 
            color: #6a6a6a; 
            text-transform: uppercase; 
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .pcs-item .value { 
            font-size: 26px; 
            font-weight: 600; 
            color: #2c2c2c; 
        }
        .pcs-item .max { 
            font-size: 11px; 
            color: #8a8a8a; 
        }
        .cert-list { 
            list-style: none; 
            padding: 0; 
        }
        .cert-list li { 
            padding: 12px 16px; 
            margin: 10px 0; 
            background: #fafafa; 
            border-left: 2px solid #7a7a7a; 
            border-radius: 2px;
            font-size: 13px;
            color: #2c2c2c;
        }
        .action-plan {
            background: transparent;
            padding: 0;
            margin: 20px 0;
        }
        .action-item {
            padding: 16px;
            margin: 12px 0;
            background: #fafafa;
            border-left: 2px solid #7a7a7a;
            border-radius: 2px;
        }
        .action-item strong {
            color: #2c2c2c;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .footer { 
            margin-top: 50px; 
            padding-top: 25px; 
            border-top: 1px solid #d0d0d0; 
            text-align: center; 
            color: #6a6a6a; 
            font-size: 11px; 
        }
        .highlight {
            background: #f5f5f5;
            padding: 18px;
            border-left: 2px solid #8a8a8a;
            border-radius: 2px;
            margin: 20px 0;
        }
        .highlight strong {
            color: #2c2c2c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border: 1px solid #e5e5e5;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e8e8e8;
        }
        th {
            background: #fafafa;
            font-weight: 600;
            color: #2c2c2c;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            font-size: 13px;
            color: #4a4a4a;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logos">
            <span class="logo-text">TD SYNNEX</span>
            <span style="color: #ccc;">|</span>
            <span class="logo-text">Microsoft</span>
        </div>
        <h1>Relatório de Assessment</h1>
        <p>CloudPartner HUB - Partner Designation Journey</p>
        <p style="margin-top: 10px; font-size: 12px; color: #999;">Gerado em: ${new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}</p>
    </div>

    <div class="section">
        <h2>INFORMAÇÕES DO PARCEIRO</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Razão Social</div>
                <div class="info-value">${partner.companyName}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Email Corporativo</div>
                <div class="info-value">${partner.email}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Área de Solução</div>
                <div class="info-value">${partner.selectedSolutionArea || 'Não selecionada'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status de Cadastro</div>
                <div class="info-value">
                    ${partner.isTdSynnexRegistered ? '✓' : '✗'} TD SYNNEX | 
                    ${partner.isMicrosoftPartner ? '✓' : '✗'} Microsoft Partner
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>PARTNER CAPABILITY SCORE (PCS)</h2>
        <div class="pcs-score">
            <div class="score">${totalPCS} / 100</div>
            <div class="label">Pontuação Total</div>
            <div style="margin-top: 15px;">
                <span class="status-badge">${isPassing ? '✓ Apto para Designação' : '⚠ Desenvolvimento Necessário'}</span>
            </div>
        </div>

        <div class="pcs-breakdown">
            <div class="pcs-item">
                <div class="title">Performance</div>
                <div class="value">${partner.pcsPerformance}</div>
                <div class="max">de 30 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Skilling</div>
                <div class="value">${partner.pcsSkilling}</div>
                <div class="max">de 40 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Customer Success</div>
                <div class="value">${partner.pcsCustomerSuccess}</div>
                <div class="max">de 30 pontos</div>
            </div>
        </div>

        <div class="highlight" style="background: #f0f7ff; border-left: 4px solid #0078d4; margin-top: 20px;">
            <strong>Requisitos para Designação Solution Partner (${partner.selectedSolutionArea}):</strong>
            <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                <li>Pontuação mínima: <strong>70 pontos</strong> no total</li>
                <li>Obrigatório ter <strong>pelo menos 1 ponto</strong> em cada uma das 5 métricas:
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>Desempenho: Ganhos líquidos de clientes (Net Customer Adds)</li>
                        <li>Skilling: Certificações intermediárias</li>
                        <li>Skilling: Certificações avançadas</li>
                        <li>Sucesso do Cliente: Crescimento de uso (Usage Growth)</li>
                        <li>Sucesso do Cliente: Implantações (Deployments)</li>
                    </ul>
                </li>
            </ul>
        </div>

        ${!isPassing ? `
        <div class="highlight">
            <strong>Gap Identificado:</strong> Necessário atingir 70 pontos para qualificação Solution Partner. 
            Déficit atual: <strong>${70 - totalPCS} pontos</strong>.
        </div>
        ` : `
        <div class="highlight">
            <strong>Status:</strong> Pontuação mínima atingida para Solution Partner. 
            Oportunidade de evolução para nível Advanced (80+ pontos).
        </div>
        `}

        <h3 style="margin-top: 30px; color: #1a1a1a; font-size: 15px; font-weight: 600;">Detalhamento das Métricas PCS</h3>
        
        <div style="margin-top: 20px;">
            <h4 style="color: #0078d4; font-size: 13px; margin-bottom: 10px;">📊 1. DESEMPENHO (Performance) - Máximo: 30 pontos</h4>
            <div style="background: #fafafa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0; font-weight: 600;">Ganhos Líquidos de Clientes (Net Customer Adds)</p>
                <p style="margin: 0 0 8px 0; line-height: 1.6; color: #4a4a4a;">
                    • <strong>Pontuação:</strong> Cada novo cliente líquido = 10 pontos (máximo 30 pontos = 3 clientes)<br>
                    • <strong>Cálculo:</strong> (Clientes qualificados mês passado) - (Clientes qualificados mesmo mês ano passado)<br>
                    • <strong>Clientes Qualificados Enterprise:</strong> Locatários que contribuem com pelo menos USD 1.000 em ACR nos últimos 2 meses<br>
                    • <strong>Clientes Qualificados SMB:</strong> Locatários que contribuem com pelo menos USD 500 em ACR nos últimos 2 meses<br>
                    • <strong>Tipos de associação:</strong> CSP (Tier 1 e 2), DPOR, PAL
                </p>
            </div>

            <h4 style="color: #0078d4; font-size: 13px; margin-bottom: 10px;">🎓 2. SKILLING (Certificações) - Máximo: 40 pontos</h4>
            <div style="background: #fafafa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0; font-weight: 600;">2.1 Certificações Intermediárias (até 40 pontos)</p>
                <p style="margin: 0 0 15px 0; line-height: 1.6; color: #4a4a4a;">
                    <strong>Enterprise:</strong><br>
                    • Passo 1 (obrigatório): Pelo menos 2 pessoas com AZ-104 (Azure Administrator)<br>
                    • Passo 2 (obrigatório): Pelo menos 2 pessoas com AZ-305 (Solutions Architect Expert)<br>
                    • Passo 3: Cada pessoa certificada em DP-300, AI-102, DP-100, DP-203 ou outras = 4 pontos (máximo 10 pessoas)<br><br>
                    
                    <strong>SMB:</strong><br>
                    • Passo 1 (obrigatório): Pelo menos 1 pessoa com AZ-104 = 4 pontos<br>
                    • Passo 2 (obrigatório): Pelo menos 1 pessoa com AZ-305 = 4 pontos<br>
                    • Passo 3: Cada pessoa adicional certificada = 4 pontos (máximo 8 pessoas)<br>
                    • <strong>Total máximo:</strong> 40 pontos
                </p>

                <p style="margin: 0 0 10px 0; font-weight: 600;">2.2 Certificações Avançadas (Não aplicável para Data & AI)</p>
                <p style="margin: 0; line-height: 1.6; color: #4a4a4a;">
                    Para Data & AI: Certificações avançadas não são aplicáveis<br>
                    Para outras áreas: Consultar requisitos específicos de cada solution area
                </p>
            </div>

            <h4 style="color: #0078d4; font-size: 13px; margin-bottom: 10px;">✅ 3. SUCESSO DO CLIENTE (Customer Success) - Máximo: 30 pontos</h4>
            <div style="background: #fafafa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0; font-weight: 600;">3.1 Crescimento de Uso (Usage Growth) - até 20 pontos</p>
                <p style="margin: 0 0 15px 0; line-height: 1.6; color: #4a4a4a;">
                    • <strong>Pontuação:</strong> Cada 1% de crescimento no ACR = 1 ponto (máximo 20 pontos)<br>
                    • <strong>Fórmula:</strong> [(ACR mês passado - ACR mesmo mês ano passado) × 100] / ACR mesmo mês ano passado<br>
                    • <strong>ACR mínimo:</strong> USD 1.000 para se qualificar<br>
                    • <strong>Serviços elegíveis:</strong> Todos os serviços Azure de Nível 2<br>
                    • <strong>Tipos de associação:</strong> CSP Tier 1 e 2, DPOR, PAL
                </p>

                <p style="margin: 0 0 10px 0; font-weight: 600;">3.2 Implantações (Deployments) - até 10 pontos</p>
                <p style="margin: 0; line-height: 1.6; color: #4a4a4a;">
                    • <strong>Pontuação:</strong> Cada serviço Azure único implantado = 2 pontos (máximo 5 serviços)<br>
                    • <strong>Definição:</strong> Número de serviços exclusivos do Azure (Nível 2) com ACR > 0 nos últimos 12 meses<br>
                    • <strong>Serviços qualificados:</strong> Todos os serviços de Nível 2 exceto VMs e licenças de VMs<br>
                    • <strong>Atualização:</strong> Dados atualizados por volta do dia 20 de cada mês
                </p>
            </div>

            <div style="background: #fff9e6; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <p style="margin: 0; font-weight: 600; color: #856404;">
                    📌 Importante: Faixa de Qualificação (Track)
                </p>
                <p style="margin: 10px 0 0 0; line-height: 1.6; color: #4a4a4a;">
                    • <strong>SMB:</strong> ACR total < USD 1 milhão/ano E mais de 80% dos clientes no segmento SMB<br>
                    • <strong>Enterprise:</strong> Caso contrário<br>
                    • Os limites de pontuação e requisitos variam conforme a faixa<br>
                    • A faixa de inscrição é determinada na renovação anual
                </p>
            </div>
        </div>

        <div style="margin-top: 25px; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">
            <p style="margin: 0 0 10px 0; font-weight: 600; color: #2e7d32;">
                📚 Recursos Oficiais Microsoft
            </p>
            <p style="margin: 0; line-height: 1.8;">
                • <a href="https://learn.microsoft.com/pt-br/partner-center/membership/solutions-partner-azure" target="_blank" style="color: #1976d2; text-decoration: underline;">Documentação Oficial - Solutions Partner for Azure</a><br>
                • <a href="https://learn.microsoft.com/pt-br/partner-center/membership/partner-capability-score" target="_blank" style="color: #1976d2; text-decoration: underline;">Partner Capability Score - Guia Completo</a><br>
                • <a href="https://learn.microsoft.com/pt-br/partner-center/membership/ms-learn-associate" target="_blank" style="color: #1976d2; text-decoration: underline;">Como Associar Certificações ao Partner Center</a>
            </p>
        </div>
    </div>

    <div class="section">
        <h2>ANÁLISE: CERTIFICAÇÕES vs SKILLING</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Total de Certificações Informadas</div>
                <div class="info-value">${totalCertifications} certificações</div>
            </div>
            <div class="info-item">
                <div class="info-label">Pontos Potenciais de Certificações</div>
                <div class="info-value">${certificationPoints.toFixed(1)} pontos</div>
                <div style="font-size: 11px; color: #666; margin-top: 4px;">
                    ${partner.selectedSolutionArea === 'Azure Infra' ? '(4 pts por certificação)' : 
                      partner.selectedSolutionArea === 'Modern Work' ? '(2.5 pts intermediária, 7.5 pts avançada)' : 
                      partner.selectedSolutionArea === 'Security' ? '(6.67 pts por certificação intermediária)' :
                      partner.selectedSolutionArea === 'Digital & App Innovation' ? '(4 pts por certificação)' :
                      '(4 pts por certificação)'}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Skilling Atual Selecionado</div>
                <div class="info-value">${skillingSelected} pontos</div>
            </div>
            <div class="info-item">
                <div class="info-label">Diferença (Gap)</div>
                <div class="info-value" style="color: ${certificationGap > 0 ? '#FFB900' : '#7FBA00'}">
                    ${certificationGap > 0 ? '+' : ''}${certificationGap} pontos
                </div>
            </div>
        </div>

        ${certificationGap > 0 ? `
        <div class="highlight">
            <strong>Discrepância Identificada</strong>
            <p style="margin-top: 10px; line-height: 1.6;">
                Skilling declarado: <strong>${skillingSelected} pontos</strong> | 
                Potencial calculado: <strong>${certificationPoints.toFixed(1)} pontos</strong><br>
                ${partner.selectedSolutionArea === 'Azure Infra' ? 
                  `(${totalCertifications} certificações × 4 pontos cada)` :
                  partner.selectedSolutionArea === 'Modern Work' ? 
                  `(Intermediárias: 2.5 pts | Avançadas: 7.5 pts)` :
                  partner.selectedSolutionArea === 'Security' ?
                  `(${totalCertifications} certificações intermediárias × 6.67 pontos cada)` :
                  partner.selectedSolutionArea === 'Digital & App Innovation' ?
                  `(${totalCertifications} certificações × 4 pontos cada)` :
                  `(${totalCertifications} certificações × 4 pontos cada)`
                }<br><br>
                
                <strong>Gap: ${certificationGap} pontos</strong><br><br>
                
                <strong>Ação Requerida:</strong> Verificar associação de perfis Microsoft Learn dos ${totalCertifications} profissionais certificados no Partner Center. 
                Certificações não associadas à organização não são contabilizadas para a designação.<br><br>
                
                <strong>Como associar perfis:</strong><br>
                Acesse o guia oficial Microsoft: <a href="https://docs.microsoft.com/partner-center/ms-learn-associate" style="color: #2c2c2c; text-decoration: underline;">docs.microsoft.com/partner-center/ms-learn-associate</a>
            </p>
        </div>
        ` : certificationGap < 0 ? `
        <div class="highlight">
            <strong>Status</strong>
            <p style="margin-top: 10px; line-height: 1.6;">
                Skilling declarado (${skillingSelected} pontos) inferior ao potencial calculado (${certificationPoints.toFixed(1)} pontos). 
                Recomenda-se revisão da associação de perfis no Microsoft Learn.<br><br>
                
                <strong>Como associar perfis:</strong><br>
                Acesse o guia oficial Microsoft: <a href="https://docs.microsoft.com/partner-center/ms-learn-associate" style="color: #2c2c2c; text-decoration: underline;">docs.microsoft.com/partner-center/ms-learn-associate</a>
            </p>
        </div>
        ` : `
        <div class="highlight">
            <strong>Status</strong>
            <p style="margin-top: 10px; line-height: 1.6;">
                Certificações (${totalCertifications}) alinhadas com Skilling declarado (${skillingSelected} pontos). 
                Perfis corretamente associados à organização.
            </p>
        </div>
        `}

        <h3 style="margin-top: 25px; color: #1a1a1a; font-size: 14px; font-weight: 600;">Detalhamento das Certificações Informadas</h3>
        ${totalCertifications > 0 ? `
        <table style="margin-top: 15px;">
            <tr>
                <th>Tipo de Certificação</th>
                <th style="text-align: center;">Quantidade</th>
                <th style="text-align: center;">Pontos Potenciais</th>
            </tr>
            ${Object.entries(partner.certifications || {}).map(([certCode, qty]: [string, any]) => {
              const quantity = Number(qty) || 0;
              if (quantity === 0) return '';
              return `
              <tr>
                  <td>${certCode}</td>
                  <td style="text-align: center;">${quantity}</td>
                  <td style="text-align: center; font-weight: 600; color: #005587;">${quantity * 4} pts</td>
              </tr>
              `;
            }).filter(row => row).join('')}
            <tr style="background: #f8f9fa; font-weight: 600;">
                <td>TOTAL</td>
                <td style="text-align: center;">${totalCertifications}</td>
                <td style="text-align: center; color: #005587;">${certificationPoints} pts</td>
            </tr>
        </table>
        ` : `
        <p style="margin-top: 10px; color: #666; font-style: italic;">Nenhuma certificação foi informada no cadastro.</p>
        `}
    </div>

    <div class="section">
        <h2>MÉTRICAS DE NEGÓCIO</h2>
        <table>
            <tr>
                <th>Métrica</th>
                <th>Valor Atual</th>
            </tr>
            <tr>
                <td>Receita CSP (mensal)</td>
                <td>$${parseFloat(partner.cspRevenue || '0').toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
            </tr>
            <tr>
                <td>Número de Clientes</td>
                <td>${partner.clientCount || 0} clientes ativos</td>
            </tr>
            <tr>
                <td>Nível Atual</td>
                <td><strong>${currentLevel}</strong></td>
            </tr>
            <tr>
                <td>Nível Alvo</td>
                <td><strong>${targetLevel}</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>PLANO DE AÇÃO: ${partner.selectedSolutionArea}</h2>
        
        <h3>1. Certificações Intermediárias Obrigatórias</h3>
        <ul class="cert-list">
            ${certifications.intermediate.map(cert => `<li>${cert}</li>`).join('')}
        </ul>

        <h3>2. Certificações Avançadas (Expert)</h3>
        <ul class="cert-list">
            ${certifications.advanced.map(cert => `<li>${cert}</li>`).join('')}
        </ul>

        <h3>3. Especializações Recomendadas</h3>
        <ul class="cert-list">
            ${certifications.specializations.map(spec => `<li>${spec}</li>`).join('')}
        </ul>
    </div>

    <div class="section">
        <h2>PRÓXIMOS PASSOS</h2>
        <div class="action-plan">
            <div class="action-item">
                <strong>1. Registro e Cadastramento</strong>
                ${!partner.isTdSynnexRegistered ? 'Pendente: Cadastro no portal TD SYNNEX' : 'Concluído: Cadastro TD SYNNEX'}
                <br>
                ${!partner.isMicrosoftPartner ? 'Pendente: Registro no Microsoft Partner Center (MPN)' : 'Concluído: Microsoft Partner Center'}
            </div>
            
            <div class="action-item">
                <strong>2. Desenvolvimento de Competências (Skilling)</strong>
                ${partner.pcsSkilling < 25 ? `Prioridade Crítica: ${partner.pcsSkilling}/40 pontos. Foco em certificações intermediárias.` : 
                  partner.pcsSkilling < 35 ? `${partner.pcsSkilling}/40 pontos. Expandir para certificações avançadas.` :
                  `${partner.pcsSkilling}/40 pontos. Manter atualização de certificações.`}
            </div>
            
            <div class="action-item">
                <strong>3. Performance e Receita</strong>
                ${partner.pcsPerformance < 15 ? `Prioridade Alta: Expandir receita CSP e base de clientes. Meta sugerida: $${(parseFloat(partner.cspRevenue || '0') * 2).toLocaleString('pt-BR')}/mês.` :
                  `Manter crescimento de receita recorrente. Atual: $${parseFloat(partner.cspRevenue || '0').toLocaleString('pt-BR')}/mês`}
            </div>
            
            <div class="action-item">
                <strong>4. Customer Success</strong>
                Implementar programas de adoção e satisfação. Utilizar recursos Microsoft FastTrack e Customer Success Manager.
            </div>

            <div class="action-item">
                <strong>5. Estratégia Go-to-Market</strong>
                Desenvolver campanhas de geração de demanda para ${partner.selectedSolutionArea}. Alavancar Marketing Development Funds (MDF) disponíveis.
            </div>
        </div>
    </div>

    <div class="section">
        <h2>BENEFÍCIOS DA DESIGNAÇÃO</h2>
        <div style="background: #fafafa; padding: 18px; border-radius: 2px; margin-top: 15px; border: 1px solid #e5e5e5;">
            <p style="margin-bottom: 15px; color: #2c2c2c;"><strong>Benefícios Solution Partner:</strong></p>
            <ul style="margin: 0 0 0 20px; line-height: 2; color: #4a4a4a;">
                <li>Incentivos financeiros: 4% a 10% sobre receita CSP</li>
                <li>Marketing Development Funds: 40% dos incentivos para co-marketing</li>
                <li>Suporte técnico avançado e acesso a especialistas Microsoft</li>
                <li>Materiais de co-branding e marketplace Microsoft</li>
                <li>Treinamentos exclusivos e recursos de capacitação</li>
                <li>Priorização em programas e iniciativas Microsoft</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p><strong>CloudPartner HUB</strong> | TD SYNNEX & Microsoft</p>
        <p style="margin-top: 8px;">Documento confidencial | ${partner.companyName}</p>
        <p style="margin-top: 10px; font-size: 10px;">Partner Development Manager | TD SYNNEX</p>
    </div>
</body>
</html>
  `;

  // Usar html2pdf para gerar PDF diretamente com formatação completa
  const printWindow = window.open('', '_blank');
  if (printWindow) {
    printWindow.document.write(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body style="margin: 0; padding: 20px;">
    <div id="content" style="background: white;">
        ${htmlContent.split('<body>')[1].split('</body>')[0]}
    </div>
    <style>
        ${htmlContent.split('<style>')[1].split('</style>')[0]}
    </style>
    <script>
        window.onload = function() {
            const element = document.getElementById('content');
            const opt = {
                margin: [10, 10, 10, 10],
                filename: '${partner.companyName.replace(/\s+/g, '_')}_Assessment_${new Date().toISOString().split('T')[0]}.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true,
                    letterRendering: true,
                    logging: false
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                setTimeout(() => window.close(), 1000);
            });
        };
    </script>
</body>
</html>
    `);
    printWindow.document.close();
  }
  
  console.log('Gerando PDF do relatório...');
};

// --- Mock Data (Reserved for future use) ---
// const MOCK_DB: PartnerData[] = [
//   {
//     id: '1',
//     companyName: 'TechSolutions Br',
//     contactName: 'Carlos Silva',
//     email: 'carlos@techsolutions.com.br',
//     isTdSynnexRegistered: true,
//     isMicrosoftPartner: true,
//     partnerTypeInterest: 'Resell',
//     selectedSolutionArea: 'Azure Infra',
//     cspRevenue: "2500", 
//     clientCount: "12",
//     services: ['Implementação', 'Suporte'],
//     pcsPerformance: 10,
//     pcsSkilling: 20,
//     pcsCustomerSuccess: 5,
//     currentStep: 6,
//     status: 'In Progress',
//     createdAt: '2023-10-15'
//   },
//   {
//     id: '2',
//     companyName: 'Inova Soft',
//     contactName: 'Mariana Costa',
//     email: 'mari@inovasoft.com',
//     isTdSynnexRegistered: true,
//     isMicrosoftPartner: false,
//     partnerTypeInterest: 'ISV',
//     selectedSolutionArea: '',
//     cspRevenue: "0",
//     clientCount: "0",
//     services: [],
//     pcsPerformance: 0,
//     pcsSkilling: 0,
//     pcsCustomerSuccess: 0,
//     currentStep: 2,
//     status: 'In Progress',
//     createdAt: '2023-10-20'
//   }
// ];

const TIMELINE_STEPS = [
  { id: 1, title: 'Welcome', subtitle: 'Onboarding', icon: Rocket, desc: 'Visão geral do ecossistema e cadastro inicial.' },
  { id: 2, title: 'Triagem', subtitle: 'Assessment', icon: ShieldCheck, desc: 'Verificação de maturidade e status atual.' },
  { id: 3, title: 'Soluções', subtitle: 'Definição', icon: Briefcase, desc: 'Escolha da trilha de especialização.' },
  { id: 4, title: 'Métricas', subtitle: 'PCS Score', icon: BarChart3, desc: 'Input de dados de performance e skilling.' },
  { id: 5, title: 'Plano', subtitle: 'Resultado', icon: Award, desc: 'Plano de ação e calculadora de incentivos.' },
  { id: 6, title: 'GTM', subtitle: 'Estratégia', icon: Megaphone, desc: 'Planejamento de geração de demanda.' },
];

// --- Branding & UI Components ---

const SponsorLogos = () => (
  <div className="flex items-center gap-4 select-none opacity-90">
    <div>
      <span className="text-lg tracking-wider text-[#005587]">TD SYNNEX</span>
    </div>
    <div className="h-6 w-px bg-gray-300"></div>
    <div className="flex items-center gap-2">
      <div className="grid grid-cols-2 gap-0.5">
        <div className="w-1.5 h-1.5 bg-[#F25022]"></div>
        <div className="w-1.5 h-1.5 bg-[#7FBA00]"></div>
        <div className="w-1.5 h-1.5 bg-[#00A4EF]"></div>
        <div className="w-1.5 h-1.5 bg-[#FFB900]"></div>
      </div>
      <span className="font-semibold text-base text-gray-500 tracking-tight">Microsoft</span>
    </div>
  </div>
);

const ProgramIdentity = ({ size = "large" }: { size?: "small" | "large" }) => {
  if (size === "small") {
    return (
      <div className="flex flex-col leading-none select-none">
        <span className="font-black text-xl text-slate-800 tracking-tight">
          CloudPartner <span className="text-[#00A4EF]">HUB</span>
        </span>
      </div>
    );
  }
  return (
    <div className="flex flex-col items-start gap-6 select-none">
      <SponsorLogos />
      <div className="relative">
        <h1 className="text-6xl font-black tracking-tight text-slate-900 leading-[0.9]">
          CloudPartner<br />
          <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#005587] to-[#00A4EF]">HUB</span>
        </h1>
      </div>
    </div>
  );
};

const Card = ({ children, className = "", onClick }: { children: React.ReactNode, className?: string, onClick?: () => void }) => (
  <div onClick={onClick} className={`bg-white rounded-lg shadow-sm border border-gray-200 ${className}`}>
    {children}
  </div>
);

const Button = ({ onClick, disabled, variant = "primary", children, className = "" }: any) => {
  const baseStyle = "px-6 py-2.5 rounded-md font-medium transition-all duration-200 flex items-center justify-center gap-2 text-sm";
  const variants: any = {
    primary: "bg-[#005587] text-white hover:bg-[#00446b] disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow",
    outline: "border border-gray-300 text-gray-700 hover:border-[#005587] hover:text-[#005587] bg-white",
    success: "bg-[#7FBA00] text-white hover:bg-[#6da100] shadow-sm",
    ghost: "bg-transparent text-gray-500 hover:bg-gray-50 px-3",
  };
  return (
    <button onClick={onClick} disabled={disabled} className={`${baseStyle} ${variants[variant]} ${className}`}>
      {children}
    </button>
  );
};

// --- GTM Planner Component ---

const GTMPlanner = () => {
  const [activeTab, setActiveTab] = useState<GTMCategory>('Digital');
  const [budget, setBudget] = useState<number>(5000);
  const [selectedFocus, setSelectedFocus] = useState<GTMSolutionFocus>('Cloud AI Platform');
  const [activities, setActivities] = useState<GTMActivity[]>([]);

  // Simulation rates based on market averages
  const CONVERSION_RATES = {
    'Digital': { roi: 5, desc: 'Alto volume, baixo custo (Leads Frios)' }, // $1 investido = $5 pipeline
    'Eventos': { roi: 12, desc: 'Alto impacto, networking (Leads Quentes)' }, // $1 investido = $12 pipeline
    'Capacitação': { roi: 2, desc: 'Investimento em Skill (Retorno Indireto)' } // $1 investido = $2 pipeline (longo prazo)
  };

  const addActivity = () => {
    const newActivity: GTMActivity = {
      id: Date.now().toString(),
      category: activeTab,
      focus: selectedFocus,
      name: `${activeTab} Campaign - ${selectedFocus}`,
      investment: budget,
      roiMultiplier: CONVERSION_RATES[activeTab].roi,
      pipelineGenerated: budget * CONVERSION_RATES[activeTab].roi
    };
    setActivities([...activities, newActivity]);
  };

  const totalInvestment = activities.reduce((acc, curr) => acc + curr.investment, 0);
  const totalPipeline = activities.reduce((acc, curr) => acc + curr.pipelineGenerated, 0);

  // Group by Focus for Funnel
  const pipelineByFocus = activities.reduce((acc, curr) => {
    acc[curr.focus] = (acc[curr.focus] || 0) + curr.pipelineGenerated;
    return acc;
  }, {} as Record<GTMSolutionFocus, number>);

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mt-8 animate-in slide-in-from-right duration-500">
      <div className="bg-slate-50 border-b border-gray-200 p-6">
        <div className="flex items-center gap-2 text-[#005587] mb-1">
          <Megaphone size={20} />
          <h3 className="font-bold text-lg">Planejador de Go-To-Market (GTM)</h3>
        </div>
        <p className="text-xs text-gray-500">Defina suas estratégias de geração de demanda e visualize o ROI estimado para o semestre.</p>
      </div>

      <div className="p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Left Column: Input & Controls */}
        <div className="lg:col-span-5 space-y-6">
          
          {/* Tabs */}
          <div className="flex p-1 bg-gray-100 rounded-lg">
            {['Digital', 'Eventos', 'Capacitação'].map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab as GTMCategory)}
                className={`flex-1 py-2 text-xs font-semibold rounded-md transition-all ${activeTab === tab ? 'bg-white text-[#005587] shadow-sm' : 'text-gray-500'}`}
              >
                {tab}
              </button>
            ))}
          </div>

          {/* Configuration */}
          <div className="bg-slate-50 p-4 rounded-lg border border-slate-200">
            <h4 className="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
              <Target size={16} className="text-[#005587]"/> Configurar Atividade
            </h4>
            
            <div className="space-y-4">
              <div>
                <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Foco da Solução</label>
                <select 
                  value={selectedFocus} 
                  onChange={(e) => setSelectedFocus(e.target.value as GTMSolutionFocus)}
                  className="w-full p-2 border border-gray-300 rounded text-sm outline-none focus:border-[#005587]"
                >
                  <option value="AI Business Solutions">AI Business Solutions (BizApps)</option>
                  <option value="Cloud AI Platform">Cloud AI Platform (Azure/Data)</option>
                  <option value="Security">Security</option>
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Investimento Estimado (USD)</label>
                <div className="relative">
                  <DollarSign size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/>
                  <input 
                    type="number" 
                    value={budget}
                    onChange={(e) => setBudget(parseFloat(e.target.value) || 0)}
                    className="w-full pl-8 p-2 border border-gray-300 rounded text-sm outline-none focus:border-[#005587]"
                  />
                </div>
              </div>

              <div className="bg-white p-3 rounded border border-gray-200 text-xs">
                <span className="font-bold text-gray-700">Insight TD SYNNEX:</span>
                <p className="text-gray-500 mt-1">
                  {activeTab === 'Digital' && "Campanhas digitais (SDR, Webinars) geram alto volume. Recomendado para topo de funil."}
                  {activeTab === 'Eventos' && "Eventos presenciais (Jantares, Feiras) têm custo maior, mas convertem leads executivos."}
                  {activeTab === 'Capacitação' && "Treinar o time técnico/comercial não gera leads diretos, mas aumenta a taxa de fechamento."}
                </p>
                <div className="mt-2 text-[#005587] font-semibold flex items-center gap-1">
                  <TrendingUp size={12}/> ROI Estimado: {CONVERSION_RATES[activeTab].roi}x
                </div>
              </div>

              <Button onClick={addActivity} variant="primary" className="w-full h-10 text-xs">
                Adicionar ao Plano <ArrowRight size={14}/>
              </Button>
            </div>
          </div>
        </div>

        {/* Right Column: Visualization & Pipeline */}
        <div className="lg:col-span-7 flex flex-col gap-6">
          
          {/* Summary Cards */}
          <div className="grid grid-cols-2 gap-4">
            <div className="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
              <p className="text-xs text-gray-500 font-semibold uppercase">Investimento Total</p>
              <h3 className="text-2xl font-bold text-gray-800">${totalInvestment.toLocaleString()}</h3>
            </div>
            <div className="p-4 bg-[#005587] text-white rounded-lg shadow-sm">
              <p className="text-xs text-sky-100 font-semibold uppercase">Pipeline Projetado</p>
              <h3 className="text-2xl font-bold">${totalPipeline.toLocaleString()}</h3>
            </div>
          </div>

          {/* Pipeline Funnel Visual */}
          <div className="border border-gray-200 rounded-lg p-4 bg-white flex-1">
            <h4 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
              <Filter size={16} className="text-[#005587]"/> Funil de Pipeline Gerado
            </h4>
            
            {activities.length === 0 ? (
              <div className="h-40 flex flex-col items-center justify-center text-gray-400 text-xs border-2 border-dashed border-gray-100 rounded">
                <p>Nenhuma atividade planejada.</p>
                <p>Adicione estratégias para ver a projeção.</p>
              </div>
            ) : (
              <div className="space-y-3">
                {/* Funnel Rows */}
                {Object.entries(pipelineByFocus).map(([focus, value]) => (
                  <div key={focus} className="relative group">
                    <div className="flex justify-between text-xs mb-1 font-medium text-gray-600">
                      <span>{focus}</span>
                      <span>${value.toLocaleString()}</span>
                    </div>
                    <div className="w-full bg-gray-100 rounded-full h-4 overflow-hidden">
                      <div 
                        className={`h-full rounded-full transition-all duration-500 ${
                          focus === 'Security' ? 'bg-red-500' : 
                          focus === 'Cloud AI Platform' ? 'bg-blue-500' : 'bg-purple-500'
                        }`}
                        style={{ width: `${(value / totalPipeline) * 100}%` }}
                      ></div>
                    </div>
                  </div>
                ))}
                
                {/* Simulated Funnel Stages */}
                <div className="mt-6 pt-4 border-t border-gray-100 grid grid-cols-3 text-center gap-2">
                   <div className="p-2 bg-gray-50 rounded">
                      <p className="text-[10px] text-gray-500 uppercase">Leads (MQL)</p>
                      <p className="font-bold text-gray-800 text-sm">{Math.floor(totalPipeline / 5000)}</p>
                   </div>
                   <div className="p-2 bg-gray-50 rounded">
                      <p className="text-[10px] text-gray-500 uppercase">Opps (SQL)</p>
                      <p className="font-bold text-gray-800 text-sm">{Math.floor((totalPipeline / 5000) * 0.3)}</p>
                   </div>
                   <div className="p-2 bg-green-50 rounded border border-green-100">
                      <p className="text-[10px] text-green-600 uppercase">Wins Est.</p>
                      <p className="font-bold text-green-700 text-sm">{Math.floor((totalPipeline / 5000) * 0.3 * 0.4)}</p>
                   </div>
                </div>
              </div>
            )}
          </div>

          {/* Activities List */}
          {activities.length > 0 && (
            <div className="border-t border-gray-100 pt-4">
              <h5 className="text-xs font-bold text-gray-500 uppercase mb-2">Atividades Planejadas</h5>
              <div className="flex flex-wrap gap-2">
                {activities.map((act) => (
                  <div key={act.id} className="text-[10px] bg-gray-50 border border-gray-200 px-2 py-1 rounded-md flex items-center gap-2">
                    <span className={`w-2 h-2 rounded-full ${
                      act.category === 'Digital' ? 'bg-blue-400' : act.category === 'Eventos' ? 'bg-amber-400' : 'bg-purple-400'
                    }`}></span>
                    <span className="font-medium text-gray-700 truncate max-w-[100px]">{act.focus}</span>
                    <span className="text-gray-400">|</span>
                    <span className="text-gray-500">${act.investment}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

        </div>
      </div>
    </div>
  );
};


// --- Calculator Component ---

const IncentiveCalculator = ({ initialSolutionArea, initialRevenue }: { initialSolutionArea: string, initialRevenue: number }) => {
  const [selectedArea, setSelectedArea] = useState(initialSolutionArea || 'Modern Work');
  const [revenues, setRevenues] = useState({
    core: initialRevenue || 10000,
    tier1: (initialRevenue || 10000) * 0.3,
    tier2: (initialRevenue || 10000) * 0.1,
    growth: 2000
  });

  const INCENTIVE_STRUCTURE: any = {
    'Modern Work': [
      { name: 'M365 CSP Core', rate: 0.0375, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Billed Revenue', type: 'core' },
      { name: 'Strategic Acc. Tier 1', rate: 0.04, rebateSplit: 0.60, coopSplit: 0.40, desc: 'BP, M365 E3', type: 'tier1' },
      { name: 'Strategic Acc. Tier 2', rate: 0.07, rebateSplit: 0.60, coopSplit: 0.40, desc: 'M365 E5, Copilot', type: 'tier2' },
      { name: 'M365 Growth Acc.', rate: 0.075, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Incremental Growth', type: 'growth' }
    ],
    'Azure Infra': [
      { name: 'Azure Consumption', rate: 0.03, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Pay-as-you-go', type: 'core' },
      { name: 'Reservations & SP', rate: 0.03, rebateSplit: 0.60, coopSplit: 0.40, desc: 'RI / SP Cons.', type: 'tier1' },
      { name: 'Azure Growth Acc.', rate: 0.075, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Incremental Growth', type: 'growth' }
    ],
    'Security': [
      { name: 'Security Core', rate: 0.0375, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Base Revenue', type: 'core' },
      { name: 'Strategic Tier 2', rate: 0.07, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Security E5', type: 'tier2' },
      { name: 'Growth Accelerator', rate: 0.075, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Incremental', type: 'growth' }
    ],
    'Business Applications': [
      { name: 'D365 CSP Core', rate: 0.04, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Base Incentive', type: 'core' },
      { name: 'Strategic Tier 1', rate: 0.07, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Finance & SC', type: 'tier1' },
      { name: 'Strategic Tier 2', rate: 0.08, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Biz Central', type: 'tier2' },
      { name: 'Growth Accelerator', rate: 0.075, rebateSplit: 0.60, coopSplit: 0.40, desc: 'Incremental', type: 'growth' }
    ]
  };

  const mapAreaToBucket = (area: string) => {
    if (area === 'Data & AI' || area === 'Digital & App Innovation') return 'Azure Infra';
    return area;
  };

  const activeRules = INCENTIVE_STRUCTURE[mapAreaToBucket(selectedArea)] || INCENTIVE_STRUCTURE['Modern Work'];

  let totalRebate = 0;
  let totalCoop = 0;
  
  const calculatedLines = activeRules.map((rule: any) => {
    const revenueBase = revenues[rule.type as keyof typeof revenues] || 0;
    const amount = revenueBase * rule.rate;
    const rebateAmt = amount * rule.rebateSplit;
    const coopAmt = amount * rule.coopSplit;
    totalRebate += rebateAmt;
    totalCoop += coopAmt;
    return { ...rule, amount, rebateAmt, coopAmt, revenueBase };
  }).filter(Boolean);

  const grandTotal = totalRebate + totalCoop;

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mt-8">
      <div className="bg-slate-50 border-b border-gray-200 p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div className="flex items-center gap-2 text-[#005587] mb-1">
            <Calculator size={20} />
            <h3 className="font-bold text-lg">Simulador de Incentivos MCI (FY26)</h3>
          </div>
          <p className="text-xs text-gray-500">Regras Indirect Reseller atualizadas (Dez/2025). Simulação por produto.</p>
        </div>
        <div className="flex gap-2 flex-wrap">
          {['Modern Work', 'Azure Infra', 'Security', 'Business Applications'].map(area => (
            <button key={area} onClick={() => setSelectedArea(area)} className={`px-3 py-1.5 text-xs font-semibold rounded-full border transition-all ${selectedArea === area ? 'bg-[#005587] text-white border-[#005587]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#005587]'}`}>{area}</button>
          ))}
        </div>
      </div>
      <div className="p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div className="lg:col-span-4 space-y-6">
          <div>
            <label className="block text-xs font-bold text-gray-500 uppercase mb-3">Receitas por Categoria (USD)</label>
            <div className="space-y-3">
              <div>
                <label className="text-[10px] text-gray-500 font-semibold">Receita Core (Base)</label>
                <div className="relative group"><DollarSign size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/><input type="number" value={revenues.core} onChange={(e) => setRevenues({...revenues, core: parseFloat(e.target.value) || 0})} className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded text-sm focus:border-[#005587] outline-none"/></div>
              </div>
              <div>
                <label className="text-[10px] text-gray-500 font-semibold">Receita Tier 1 (Strategic 1)</label>
                <div className="relative group"><DollarSign size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/><input type="number" value={revenues.tier1} onChange={(e) => setRevenues({...revenues, tier1: parseFloat(e.target.value) || 0})} className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded text-sm focus:border-[#005587] outline-none"/></div>
              </div>
              <div>
                <label className="text-[10px] text-gray-500 font-semibold">Receita Tier 2 (Strategic 2)</label>
                <div className="relative group"><DollarSign size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/><input type="number" value={revenues.tier2} onChange={(e) => setRevenues({...revenues, tier2: parseFloat(e.target.value) || 0})} className="w-full pl-9 pr-3 py-2 border border-gray-200 rounded text-sm focus:border-[#005587] outline-none"/></div>
              </div>
              <div className="pt-2 border-t border-dashed">
                <label className="text-[10px] text-green-600 font-bold">Crescimento Incremental (Growth)</label>
                <div className="relative group"><TrendingUp size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-green-500"/><input type="number" value={revenues.growth} onChange={(e) => setRevenues({...revenues, growth: parseFloat(e.target.value) || 0})} className="w-full pl-9 pr-3 py-2 border border-green-200 bg-green-50 rounded text-sm focus:border-green-500 outline-none text-green-800"/></div>
              </div>
            </div>
          </div>
        </div>
        <div className="lg:col-span-8 flex flex-col justify-between">
          <div className="overflow-hidden border border-gray-100 rounded-lg mb-4">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 text-xs uppercase font-semibold">
                <tr><th className="px-4 py-3 text-left">Incentivo</th><th className="px-4 py-3 text-left">Base Calc.</th><th className="px-4 py-3 text-center">Taxa</th><th className="px-4 py-3 text-right">Estimado</th></tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {calculatedLines.map((line: any, idx: number) => (
                  <tr key={idx} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3"><div className="font-medium text-gray-700">{line.name}</div><div className="text-[10px] text-gray-400">{line.desc}</div></td>
                    <td className="px-4 py-3 text-gray-500 text-xs">${line.revenueBase.toLocaleString()}</td>
                    <td className="px-4 py-3 text-center text-[#005587] font-semibold">{(line.rate * 100).toFixed(2)}%</td>
                    <td className="px-4 py-3 text-right font-bold text-gray-800">${line.amount.toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="bg-[#005587] text-white rounded-xl p-5 shadow-lg relative overflow-hidden">
            <div className="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
            <div className="grid grid-cols-3 gap-4 divide-x divide-white/20 relative z-10">
              <div className="px-2"><p className="text-[10px] uppercase tracking-wider opacity-80 mb-1">Cash Back (60%)</p><div className="flex items-baseline gap-1"><span className="text-xl font-bold">${totalRebate.toFixed(2)}</span><span className="text-[10px] opacity-70">Rebate</span></div></div>
              <div className="px-4"><p className="text-[10px] uppercase tracking-wider opacity-80 mb-1">Marketing Funds (40%)</p><div className="flex items-baseline gap-1"><span className="text-xl font-bold">${totalCoop.toFixed(2)}</span><span className="text-[10px] opacity-70">Co-op</span></div></div>
              <div className="px-4"><p className="text-[10px] uppercase tracking-wider font-bold text-green-300 mb-1">Total Estimado</p><div className="flex items-baseline gap-1"><span className="text-3xl font-bold">${grandTotal.toFixed(2)}</span></div></div>
            </div>
          </div>
          <div className="flex items-center gap-2 mt-3 justify-center text-xs text-gray-400"><AlertCircle size={12}/><p>Valores em USD. Co-op aplicado em ganhos {'>'}$10k/semestre.</p></div>
        </div>
      </div>
    </div>
  );
};

const HorizontalTimeline = ({ currentStepIndex }: { currentStepIndex: number }) => {
  return (
    <div className="w-full py-6">
      <div className="relative flex items-center justify-between w-full">
        <div className="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 -z-10 rounded"></div>
        <div className="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-[#005587] -z-10 rounded transition-all duration-700" style={{ width: `${((currentStepIndex - 1) / (TIMELINE_STEPS.length - 1)) * 100}%` }}></div>
        {TIMELINE_STEPS.map((step) => {
          const isCompleted = step.id < currentStepIndex;
          const isActive = step.id === currentStepIndex;
          return (
            <div key={step.id} className="flex flex-col items-center group cursor-default relative">
              <div className={`w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 z-10 bg-white ${isCompleted ? 'border-[#7FBA00] text-[#7FBA00]' : isActive ? 'border-[#005587] text-[#005587] shadow-[0_0_0_4px_rgba(0,85,135,0.1)] scale-110' : 'border-gray-300 text-gray-300'}`}>
                {isCompleted ? <CheckCircle size={20} /> : <step.icon size={18} />}
              </div>
              <div className="absolute top-14 w-32 text-center hidden md:block">
                <p className={`text-[10px] font-bold uppercase tracking-wider mb-1 ${isActive ? 'text-[#005587]' : 'text-gray-400'}`}>Passo {step.id}</p>
                <h4 className={`text-xs font-semibold leading-tight ${isActive || isCompleted ? 'text-gray-800' : 'text-gray-400'}`}>{step.title}</h4>
                {step.subtitle && (
                  <p className={`text-[10px] font-semibold mt-1 ${isActive || isCompleted ? 'text-gray-500' : 'text-gray-300'}`}>{step.subtitle}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
      <div className="h-16 hidden md:block"></div> 
    </div>
  );
};

// --- User Header Component (para Wizard e Admin) ---
const UserHeader = React.memo(() => {
  const { accounts } = useMsal();
  const activeAccount = accounts?.[0];
  const userName = activeAccount?.name || activeAccount?.username || 'Usuário';
  
  if (!activeAccount) return null;
  
  return (
    <div className="bg-white border-b border-gray-200 shadow-sm">
      <div className="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-full bg-[#005587] flex items-center justify-center text-white font-semibold text-sm">
            {userName.charAt(0).toUpperCase()}
          </div>
          <div>
            <p className="text-sm font-semibold text-gray-900">{userName}</p>
            <p className="text-xs text-gray-500">{activeAccount.username}</p>
          </div>
        </div>
        <div className="flex items-center gap-2 text-xs text-gray-500">
          <div className="w-2 h-2 rounded-full bg-green-500"></div>
          <span>Sessão ativa</span>
        </div>
      </div>
    </div>
  );
});
UserHeader.displayName = 'UserHeader';

// --- Main App Component ---

const LandingViewComponent = React.memo(({ formData, handleInputChange, startJourney }: any) => {
  const isAuthenticated = useIsAuthenticated();
  const { instance, accounts } = useMsal();
  const [isLoggingIn, setIsLoggingIn] = React.useState(false);
  const [guestMode, setGuestMode] = React.useState(false);

  // Detectar se está acessando via URL externa (ngrok, etc.)
  const isExternalAccess = typeof window !== 'undefined' && 
    !['localhost', '127.0.0.1'].includes(window.location.hostname);

  // Email vem automaticamente do login Microsoft ou em guest mode é manual
  const canSubmit = formData.companyName && (isAuthenticated || (guestMode && formData.email));

  const activeAccount = accounts?.[0];
  const signedInLabel = activeAccount?.username || activeAccount?.name;

  const handleLogin = async () => {
    if (!isEntraConfigured) return;
    setIsLoggingIn(true);
    try {
      const response = await instance.loginPopup(loginRequest);
      if (response?.account) {
        instance.setActiveAccount(response.account);
        // Marcar para redirecionar após o estado atualizar
        sessionStorage.setItem('cloudpartner_login_redirect', 'pending');
      }
    } catch (error: any) {
      console.error('Erro no login:', error);
      // Se o usuário cancelou, não é erro
      if (error?.errorCode === 'user_cancelled') {
        // Usuário fechou o popup, não fazer nada
      } else if (error?.errorCode === 'popup_window_error') {
        alert('Popup bloqueado! Por favor, permita popups para este site e tente novamente.');
      } else if (error?.errorCode === 'interaction_in_progress') {
        alert('Já existe um login em andamento. Aguarde ou recarregue a página.');
      } else {
        // Mostrar erro detalhado para debug
        const errorMsg = error?.errorMessage || error?.message || 'Erro desconhecido';
        const errorCode = error?.errorCode || 'N/A';
        console.error('Detalhes do erro:', { errorCode, errorMsg, error });
        alert(`Erro no login (${errorCode}): ${errorMsg}`);
      }
    } finally {
      setIsLoggingIn(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-slate-50">
        <div className="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
          <div className="space-y-8 animate-in slide-in-from-left duration-700">
            <ProgramIdentity />
            <div className="space-y-4">
              <h2 className="text-2xl font-light text-slate-700">
                O ecossistema definitivo para <strong className="font-bold text-[#005587]">aceleração de negócios.</strong>
              </h2>
              <p className="text-slate-600 leading-relaxed">
                Centralize sua jornada com a Microsoft e TD SYNNEX. Do onboarding aos incentivos avançados, tenha visibilidade completa da sua evolução no programa CSP.
              </p>
            </div>
            <div className="flex gap-4 pt-2">
                <div className="flex items-center gap-3 text-sm text-slate-600">
                  <div className="w-8 h-8 rounded bg-sky-50 flex items-center justify-center text-[#005587]"><Shield size={16}/></div>
                  <span>Compliance Simplificado</span>
                </div>
                <div className="flex items-center gap-3 text-sm text-slate-600">
                  <div className="w-8 h-8 rounded bg-emerald-50 flex items-center justify-center text-emerald-600"><DollarSign size={16}/></div>
                  <span>Gestão de Incentivos</span>
              </div>
          </div>
        </div>

        <Card className="p-8 border-t-4 border-t-[#005587] shadow-xl animate-in slide-in-from-right duration-700">
          <div className="mb-6">
            <h3 className="text-xl font-bold text-slate-800">Application de Parceria</h3>
            <p className="text-sm text-gray-500">Inicie seu cadastro no CloudPartner HUB.</p>
          </div>
          <div className="space-y-5">
            <div className="flex items-center justify-between gap-3 p-3 rounded-lg border bg-white">
              <div>
                <p className="text-xs font-bold text-slate-500 uppercase tracking-wide">Identidade</p>
                <p className="text-sm text-slate-700">
                  {isAuthenticated ? (
                    <span>
                      Conectado como <span className="font-semibold">{signedInLabel}</span>
                    </span>
                  ) : (
                    <span>Conecte-se com sua conta Microsoft (Entra ID) para iniciar.</span>
                  )}
                </p>
                {!isEntraConfigured && (
                  <p className="mt-1 text-xs text-red-600">
                    Configuração pendente: defina <strong>VITE_AAD_CLIENT_ID</strong> no .env
                  </p>
                )}
              </div>
              <div className="flex items-center gap-2">
                {isAuthenticated ? (
                  <button
                    className="px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    onClick={() => instance.logoutRedirect()}
                  >
                    Sair
                  </button>
                ) : (
                  <button
                    className={`px-3 py-2 rounded-lg text-sm font-medium ${
                      isEntraConfigured && !isLoggingIn ? 'bg-blue-700 text-white hover:bg-blue-800' : 'bg-gray-200 text-gray-500 cursor-not-allowed'
                    }`}
                    disabled={!isEntraConfigured || isLoggingIn}
                    onClick={handleLogin}
                  >
                    {isLoggingIn ? 'Entrando...' : 'Entrar com Microsoft'}
                  </button>
                )}
              </div>
            </div>

            {!isAuthenticated ? (
              <div className="pt-2">
                <div className="p-4 rounded-lg bg-slate-50 border border-gray-200">
                  <p className="text-sm text-gray-600">
                    Faça login para iniciar o cadastro do parceiro. Em seguida, vamos coletar a razão social e confirmar os dados.
                  </p>
                  {isExternalAccess && !guestMode && (
                    <button
                      onClick={() => setGuestMode(true)}
                      className="mt-3 text-sm text-blue-600 hover:text-blue-800 underline"
                    >
                      Continuar sem login Microsoft (modo visitante)
                    </button>
                  )}
                </div>
                {guestMode && (
                  <div className="mt-4 space-y-4">
                    <div className="p-3 rounded-lg bg-amber-50 border border-amber-200">
                      <p className="text-xs text-amber-700">
                        ⚠️ Modo visitante: os dados serão salvos, mas sem autenticação Microsoft.
                      </p>
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Email Corporativo</label>
                      <input 
                        type="email" 
                        required
                        className="w-full px-4 py-2.5 border border-gray-300 rounded text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none transition-all" 
                        placeholder="seu@email.com" 
                        value={formData.email || ''} 
                        onChange={e => handleInputChange('email', e.target.value)} 
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Razão Social</label>
                      <input 
                        type="text" 
                        required
                        className="w-full px-4 py-2.5 border border-gray-300 rounded text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none transition-all" 
                        placeholder="Nome legal da empresa" 
                        value={formData.companyName || ''} 
                        onChange={e => handleInputChange('companyName', e.target.value)} 
                      />
                    </div>
                    <Button 
                      onClick={startJourney} 
                      disabled={!canSubmit}
                      variant="primary" 
                      className="w-full h-11 text-base"
                    >
                      Iniciar Onboarding <ChevronRight size={18} />
                    </Button>
                  </div>
                )}
              </div>
            ) : (
              <>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Razão Social</label>
                  <input 
                    key="companyName-input"
                    required 
                    type="text" 
                    className="w-full px-4 py-2.5 border border-gray-300 rounded text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none transition-all" 
                    placeholder="Nome legal da empresa" 
                    value={formData.companyName} 
                    onChange={e => handleInputChange('companyName', e.target.value)} 
                  />
                  <p className="mt-1.5 text-xs text-gray-500">
                    Email: <span className="font-medium text-gray-700">{formData.email || activeAccount?.username}</span>
                  </p>
                </div>
                <div className="pt-4">
                  <Button 
                    onClick={startJourney} 
                    disabled={!canSubmit}
                    variant="primary" 
                    className="w-full h-11 text-base"
                  >
                    Iniciar Onboarding <ChevronRight size={18} />
                  </Button>
                  <p className="text-center text-[11px] text-gray-400 mt-4 leading-tight">
                    Ao prosseguir, você concorda com a Política de Privacidade e os Termos de Uso do HUB.
                  </p>
                </div>
              </>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
});
LandingViewComponent.displayName = 'LandingViewComponent';

const WizardViewComponent = React.memo(({ formData, handleInputChange, step, nextStep, prevStep, totalScore, isPassing, savePartner, isLoggingOutRef }: any) => {
  const { instance, accounts } = useMsal();
  const activeAccount = accounts?.[0];
  const userName = activeAccount?.name || activeAccount?.username || 'Usuário';
  
  const [showUserMenu, setShowUserMenu] = React.useState(false);
  const [showAccountSettings, setShowAccountSettings] = React.useState(false);
  const [showBenefits, setShowBenefits] = React.useState(false);

  const handleSignOut = () => {
    // Marcar que estamos fazendo logout para desabilitar auto-save
    if (isLoggingOutRef) {
      isLoggingOutRef.current = true;
    }
    
    // Limpar dados locais antes do logout
    localStorage.removeItem('cloudpartner_formdata');
    localStorage.removeItem('cloudpartner_step');
    localStorage.removeItem('cloudpartner_view');
    sessionStorage.clear();
    
    // Resetar flag após limpar
    setTimeout(() => {
      if (isLoggingOutRef) {
        isLoggingOutRef.current = false;
      }
    }, 100);
    
    instance.logoutPopup().catch((e) => {
      console.error('Erro ao fazer logout:', e);
    });
  };

  // Fechar menu ao clicar fora
  React.useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (!target.closest('.user-menu-container')) {
        setShowUserMenu(false);
      }
    };
    if (showUserMenu) {
      document.addEventListener('click', handleClickOutside);
      return () => document.removeEventListener('click', handleClickOutside);
    }
  }, [showUserMenu]);
  
  return (
  <div className="min-h-screen bg-slate-50 flex flex-col">
     {/* Modal de Configurações da Conta */}
     {showAccountSettings && (
       <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={() => setShowAccountSettings(false)}>
         <div className="bg-white rounded-xl p-6 max-w-lg w-full shadow-2xl" onClick={(e) => e.stopPropagation()}>
           <div className="flex items-center justify-between mb-6">
             <div className="flex items-center gap-3">
               <div className="w-12 h-12 rounded-lg bg-[#005587] flex items-center justify-center text-white">
                 <Settings size={24} />
               </div>
               <div>
                 <h2 className="text-xl font-bold text-gray-900">Configurações da Conta</h2>
                 <p className="text-sm text-gray-500">Atualize suas informações</p>
               </div>
             </div>
             <button onClick={() => setShowAccountSettings(false)} className="text-gray-400 hover:text-gray-700">
               <X size={20} />
             </button>
           </div>
           
           <div className="space-y-4">
             <div className="grid grid-cols-2 gap-4">
               <div>
                 <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Nome do Contato</label>
                 <input 
                   type="text" 
                   className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                   placeholder="Seu nome" 
                   value={formData.contactName || ''} 
                   onChange={e => handleInputChange('contactName', e.target.value)} 
                 />
               </div>
               <div>
                 <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Cargo</label>
                 <input 
                   type="text" 
                   className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                   placeholder="Ex: Diretor Comercial" 
                   value={formData.contactRole || ''} 
                   onChange={e => handleInputChange('contactRole', e.target.value)} 
                 />
               </div>
             </div>
             
             <div>
               <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Telefone</label>
               <input 
                 type="tel" 
                 className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                 placeholder="(11) 99999-9999" 
                 value={formData.contactPhone || ''} 
                 onChange={e => handleInputChange('contactPhone', e.target.value)} 
               />
             </div>
             
             <div>
               <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">MPN ID (Microsoft Partner Network)</label>
               <input 
                 type="text" 
                 className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                 placeholder="Ex: 1234567" 
                 value={formData.mpnId || ''} 
                 onChange={e => handleInputChange('mpnId', e.target.value)} 
               />
               <p className="mt-1 text-xs text-gray-400">Encontre seu MPN ID no Partner Center da Microsoft</p>
             </div>
             
             <div>
               <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Email</label>
               <input 
                 type="email" 
                 className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-600" 
                 value={formData.email || activeAccount?.username || ''} 
                 disabled
               />
               <p className="mt-1 text-xs text-gray-400">Email vinculado à conta Microsoft</p>
             </div>
             
             <div className="pt-4 flex justify-end gap-3">
               <button 
                 onClick={() => setShowAccountSettings(false)}
                 className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
               >
                 Cancelar
               </button>
               <button 
                 onClick={() => {
                   if (!formData?.companyName?.trim()) {
                     alert('Por favor, preencha a Razão Social antes de salvar.');
                     return;
                   }
                   savePartner(formData);
                   setShowAccountSettings(false);
                 }}
                 className="px-4 py-2 text-sm font-medium text-white bg-[#005587] hover:bg-[#00446b] rounded-lg transition-colors"
               >
                 Salvar Alterações
               </button>
             </div>
           </div>
         </div>
       </div>
     )}

     <header className="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
      <div className="max-w-7xl mx-auto px-4 py-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-8">
              <div className="flex items-center gap-3">
                <div className="bg-[#005587] text-white p-2 rounded">
                  <Briefcase size={20} />
                </div>
                <div className="flex flex-col">
                  <span className="text-[10px] font-bold tracking-widest text-gray-400 uppercase">Portal do Parceiro</span>
                  <span className="font-bold text-lg text-slate-800 leading-none">CloudPartner <span className="text-[#00A4EF]">HUB</span></span>
                </div>
              </div>
              <div className="hidden md:flex h-8 w-px bg-gray-200"></div>
              
              {/* Links de navegação */}
              <nav className="hidden md:flex items-center gap-6">
                <a 
                  href="https://learn.microsoft.com/pt-br/credentials/browse/?credential_types=certification"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-2 text-sm text-gray-600 hover:text-[#005587] transition-colors group"
                >
                  <GraduationCap size={16} className="text-gray-400 group-hover:text-[#005587]" />
                  <span>Certificações</span>
                  <ExternalLink size={12} className="opacity-0 group-hover:opacity-100 transition-opacity" />
                </a>
                <button 
                  onClick={() => setShowBenefits(true)}
                  className="flex items-center gap-2 text-sm text-gray-600 hover:text-purple-600 transition-colors group"
                >
                  <Gift size={16} className="text-gray-400 group-hover:text-purple-600" />
                  <span>Benefícios</span>
                </button>
              </nav>
              
              <div className="hidden md:flex h-8 w-px bg-gray-200"></div>
              <div className="hidden md:block">
                <p className="text-xs text-gray-400 font-semibold uppercase">Empresa</p>
                <p className="text-sm font-bold text-gray-700">{formData.companyName}</p>
              </div>
          </div>
          
          {/* User Menu Dropdown */}
          <div className="relative user-menu-container">
            <button 
              onClick={() => setShowUserMenu(!showUserMenu)}
              className="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <div className="w-9 h-9 rounded-full bg-[#005587] flex items-center justify-center text-white font-semibold text-sm">
                {userName.charAt(0).toUpperCase()}
              </div>
              <div className="hidden sm:block text-left">
                <p className="text-sm font-semibold text-gray-900">{userName}</p>
                <div className="flex items-center gap-1.5 text-xs text-gray-500">
                  <div className="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                  <span>Online</span>
                </div>
              </div>
              <ChevronDown size={16} className={`text-gray-400 transition-transform ${showUserMenu ? 'rotate-180' : ''}`} />
            </button>
            
            {/* Dropdown Menu */}
            {showUserMenu && (
              <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                <div className="px-4 py-2 border-b border-gray-100">
                  <p className="text-sm font-semibold text-gray-900">{userName}</p>
                  <p className="text-xs text-gray-500 truncate">{activeAccount?.username}</p>
                </div>
                
                <button 
                  onClick={() => {
                    setShowUserMenu(false);
                    setShowAccountSettings(true);
                  }}
                  className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <Settings size={16} className="text-gray-400" />
                  Configurações da Conta
                </button>
                
                <div className="border-t border-gray-100 mt-1 pt-1">
                  <button 
                    onClick={handleSignOut}
                    className="w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                  >
                    <LogOut size={16} />
                    Sair da Conta
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </header>

    <main className="max-w-6xl mx-auto px-4 py-8 w-full flex-1">
      <HorizontalTimeline currentStepIndex={step} />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
           
           <div className="lg:col-span-2 space-y-6">
              <Card className="p-8 border-t-4 border-t-[#005587]">
                
                {/* STEP 2: TRIAGEM */}
                {step === 2 && (
                  <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <h3 className="text-xl font-bold text-slate-800 mb-2">Triagem Inicial</h3>
                    <p className="text-gray-500 text-sm mb-6">Vamos validar seus cadastros fundamentais.</p>
                    <div className="space-y-4">
                      <div onClick={() => handleInputChange('isTdSynnexRegistered', !formData.isTdSynnexRegistered)} className={`flex items-center justify-between p-4 border rounded-lg cursor-pointer transition-colors ${formData.isTdSynnexRegistered ? 'bg-sky-50 border-sky-200' : 'hover:bg-gray-50'}`}>
                         <div className="flex items-center gap-3">
                           <div className={`w-6 h-6 rounded border flex items-center justify-center ${formData.isTdSynnexRegistered ? 'bg-[#005587] border-[#005587]' : 'border-gray-300'}`}>{formData.isTdSynnexRegistered && <CheckCircle size={14} className="text-white" />}</div>
                           <span className="font-medium text-slate-700">Cadastro Ativo TD SYNNEX</span>
                         </div>
                      </div>
                      <div onClick={() => handleInputChange('isMicrosoftPartner', !formData.isMicrosoftPartner)} className={`flex items-center justify-between p-4 border rounded-lg cursor-pointer transition-colors ${formData.isMicrosoftPartner ? 'bg-sky-50 border-sky-200' : 'hover:bg-gray-50'}`}>
                         <div className="flex items-center gap-3">
                           <div className={`w-6 h-6 rounded border flex items-center justify-center ${formData.isMicrosoftPartner ? 'bg-[#005587] border-[#005587]' : 'border-gray-300'}`}>{formData.isMicrosoftPartner && <CheckCircle size={14} className="text-white" />}</div>
                           <span className="font-medium text-slate-700">Parceiro Microsoft (MPN ID)</span>
                         </div>
                      </div>
                    </div>
                    
                    {/* Alerta TD SYNNEX para parceiros Microsoft */}
                    {formData.isMicrosoftPartner && !formData.isTdSynnexRegistered && (
                      <div className="mt-6 bg-blue-50 p-4 rounded-lg border-l-4 border-blue-600">
                        <div className="flex items-start gap-3">
                          <AlertCircle size={20} className="text-blue-600 mt-0.5 flex-shrink-0" />
                          <div className="flex-1">
                            <h4 className="font-bold text-blue-900 text-sm mb-2">Associe-se à TD SYNNEX</h4>
                            <p className="text-xs text-blue-800 mb-3 leading-relaxed">
                              Você possui MPN ID mas não está associado à TD SYNNEX. Complete a associação para desbloquear benefícios exclusivos, 
                              suporte técnico especializado e incentivos financeiros para acelerar sua jornada de designação.
                            </p>
                            <a 
                              href="https://www.tdsynnex.com/br/parceiro-microsoft/" 
                              target="_blank" 
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                              <ArrowRight size={14} />
                              Associar-se à TD SYNNEX
                            </a>
                          </div>
                        </div>
                      </div>
                    )}
                    
                    {!formData.isMicrosoftPartner && (
                       <div className="mt-6 bg-amber-50 p-4 rounded-lg border border-amber-100">
                          <h4 className="font-bold text-amber-800 text-sm flex items-center gap-2 mb-2"><FileText size={16}/> Material de Apoio</h4>
                          <p className="text-xs text-amber-700 mb-3">Selecione seu perfil para receber o guia de cadastro:</p>
                          <div className="flex gap-2">
                            {['Revenda CSP', 'ISV / Dev', 'Services'].map(opt => (
                              <button key={opt} onClick={() => handleInputChange('partnerTypeInterest', opt)} className={`px-3 py-1.5 text-xs rounded border ${formData.partnerTypeInterest === opt ? 'bg-amber-200 border-amber-400 text-amber-900' : 'bg-white border-amber-200'}`}>{opt}</button>
                            ))}
                          </div>
                       </div>
                    )}
                    
                    {/* Validação e botão de continuar */}
                    <div className="mt-8">
                      {(!formData.isTdSynnexRegistered && !formData.isMicrosoftPartner) && (
                        <div className="mb-4 bg-red-50 p-3 rounded-lg border border-red-200">
                          <p className="text-xs text-red-700 flex items-center gap-2">
                            <AlertCircle size={14} />
                            <span>Por favor, selecione pelo menos uma das opções acima para continuar.</span>
                          </p>
                        </div>
                      )}
                      <div className="flex justify-end">
                        <Button 
                          onClick={nextStep} 
                          variant="primary"
                          disabled={!formData.isTdSynnexRegistered && !formData.isMicrosoftPartner}
                        >
                          Continuar <ArrowRight size={16} />
                        </Button>
                      </div>
                    </div>
                  </div>
                )}

                {/* STEP 3: SOLUÇÕES */}
                {step === 3 && (
                  <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <h3 className="text-xl font-bold text-slate-800 mb-2">Trilha de Solução</h3>
                    <p className="text-gray-500 text-sm mb-6">Em qual área você deseja focar sua Designação?</p>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                      {[
                        { id: 'Modern Work', icon: ModernWorkLogo },
                        { id: 'Azure Infra', icon: AzureLogo },
                        { id: 'Security', icon: SecurityLogo },
                        { id: 'Data & AI', icon: DataAILogo },
                        { id: 'Digital & App Innovation', icon: Zap },
                        { id: 'Business Applications', icon: BusinessAppLogo },
                      ].map((item) => (
                        <div key={item.id} onClick={() => handleInputChange('selectedSolutionArea', item.id)} className={`cursor-pointer p-4 rounded-xl border-2 transition-all duration-200 flex flex-col items-center justify-center gap-2 text-center h-28 ${formData.selectedSolutionArea === item.id ? 'border-[#005587] bg-sky-50 text-[#005587]' : 'border-gray-100 bg-gray-50 text-gray-500 hover:border-sky-200 hover:bg-white'}`}>
                          <item.icon size={24} />
                          <span className="font-semibold text-xs leading-tight">{item.id}</span>
                        </div>
                      ))}
                    </div>
                    {formData.selectedSolutionArea && (
                      <div className="bg-gray-50 p-5 rounded-lg border border-gray-100 mb-6">
                        <h4 className="font-bold text-gray-700 text-sm mb-4">Métricas Atuais de {formData.selectedSolutionArea}</h4>
                        <div className="grid grid-cols-2 gap-4">
                          <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Receita Média Mensal (USD)</label>
                            <div className="relative">
                              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium">$</span>
                              <input 
                                key="cspRevenue-input"
                                type="text" 
                                className="w-full mt-1 p-2 pl-7 border border-gray-300 rounded text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                                placeholder="0.00" 
                                value={formData.cspRevenue || ''} 
                                onChange={e => {
                                  const value = e.target.value.replace(/[^0-9.]/g, '');
                                  if (value === '' || /^\d*\.?\d{0,2}$/.test(value)) {
                                    handleInputChange('cspRevenue', value);
                                  }
                                }} 
                              />
                            </div>
                          </div>
                          <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Clientes Ativos</label>
                            <input 
                              key="clientCount-input"
                              type="number" 
                              className="w-full mt-1 p-2 border border-gray-300 rounded text-sm focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none" 
                              placeholder="0" 
                              value={formData.clientCount} 
                              onChange={e => handleInputChange('clientCount', e.target.value)} 
                            />
                          </div>
                        </div>
                      </div>
                    )}
                    <div className="flex justify-between mt-8">
                       <Button onClick={prevStep} variant="ghost">Voltar</Button>
                       <Button onClick={nextStep} disabled={!formData.selectedSolutionArea} variant="primary">Continuar <ArrowRight size={16} /></Button>
                    </div>
                  </div>
                )}

                {/* STEP 4: MÉTRICAS (PCS) & CERTIFICAÇÕES */}
                {step === 4 && (
                  <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <h3 className="text-xl font-bold text-slate-800 mb-2">Partner Capability Score (PCS)</h3>
                    <p className="text-gray-500 text-sm mb-6">Simule sua pontuação oficial e informe as certificações do seu time.</p>
                    
                    <div className="space-y-6">
                      {/* Performance */}
                      <div className="bg-white p-4 rounded border border-gray-200 shadow-sm">
                        <div className="flex justify-between mb-2">
                          <span className="font-bold text-gray-700 flex items-center gap-2"><TrendingUp size={16} className="text-[#005587]"/> Performance</span>
                          <span className="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600 font-mono">Max: {formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].performance : 30}</span>
                        </div>
                        <input type="range" min="0" max={formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].performance : 30} className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#005587]" value={formData.pcsPerformance} onChange={e => handleInputChange('pcsPerformance', parseInt(e.target.value))} />
                        <div className="text-right font-bold text-[#005587] mt-1">{formData.pcsPerformance} pts</div>
                      </div>

                      {/* Skilling */}
                      <div className="bg-white p-4 rounded border border-gray-200 shadow-sm">
                        <div className="flex justify-between mb-2">
                          <span className="font-bold text-gray-700 flex items-center gap-2"><Award size={16} className="text-[#7FBA00]"/> Skilling</span>
                          <span className="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600 font-mono">Max: {formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].skilling : 40}</span>
                        </div>
                        <input 
                          type="range" 
                          min="0" 
                          max={formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].skilling : 40} 
                          step={
                            formData.selectedSolutionArea === 'Azure Infra' ? 4 : 
                            formData.selectedSolutionArea === 'Modern Work' ? 2.5 : 
                            formData.selectedSolutionArea === 'Security' ? 6.67 : 
                            formData.selectedSolutionArea === 'Digital & App Innovation' ? 4 : 1
                          }
                          className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#7FBA00]" 
                          value={formData.pcsSkilling} 
                          onChange={e => handleInputChange('pcsSkilling', parseFloat(e.target.value))} 
                        />
                        <div className="text-right font-bold text-[#7FBA00] mt-1">{formData.pcsSkilling} pts</div>
                        
                        {/* Dynamic Skilling Tips based on PDF */}
                        {formData.selectedSolutionArea && formData.selectedSolutionArea in SKILLING_RULES && (
                          <div className="mt-4 p-3 bg-purple-50 rounded border border-purple-100 text-xs">
                            <div className="font-semibold text-purple-800 mb-1">Requisitos para {formData.selectedSolutionArea}:</div>
                            <ul className="list-disc pl-4 space-y-1 text-purple-700">
                              <li><span className="font-semibold">Intermediário:</span> {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].intermediate.join(", ")}</li>
                              <li><span className="font-semibold">Avançado:</span> {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].advanced.join(", ")}</li>
                            </ul>
                          </div>
                        )}
                      </div>

                      {/* Certificações do Time - Logo abaixo de Skilling */}
                      {formData.selectedSolutionArea && formData.selectedSolutionArea in SKILLING_RULES && (
                        <div className="bg-gradient-to-br from-purple-50 to-blue-50 p-5 rounded-lg border-2 border-purple-200 shadow-sm">
                          <div className="flex items-center gap-2 mb-4">
                            <GraduationCap size={22} className="text-purple-600" />
                            <div>
                              <h4 className="font-bold text-gray-800">Certificações do Time</h4>
                              <p className="text-xs text-gray-600">Informe quantos colaboradores possuem cada certificação</p>
                            </div>
                          </div>
                          
                          <div className="space-y-4">
                            {/* Certificações Intermediárias */}
                            <div>
                              <div className="text-xs font-bold text-blue-700 mb-2 uppercase tracking-wide">Intermediárias (Associate-level)</div>
                              <div className="space-y-2">
                                {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].intermediate.map((cert) => {
                                  const certKey = cert.split(':')[0].trim();
                                  const certName = cert;
                                  return (
                                    <div key={certKey} className="flex items-center gap-3 p-2.5 bg-white rounded border border-blue-100">
                                      <div className="flex-1">
                                        <div className="font-medium text-xs text-gray-800">{certName}</div>
                                      </div>
                                      <div className="flex items-center gap-2">
                                        <label className="text-xs text-gray-500">Qtd:</label>
                                        <input 
                                          type="number" 
                                          min="0" 
                                          max="99"
                                          value={formData.certifications?.[certKey] || 0}
                                          onChange={(e) => {
                                            const newCerts = { ...formData.certifications, [certKey]: parseInt(e.target.value) || 0 };
                                            handleInputChange('certifications', newCerts);
                                          }}
                                          className="w-14 px-2 py-1 border border-gray-300 rounded text-xs text-center focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none"
                                        />
                                      </div>
                                    </div>
                                  );
                                })}
                              </div>
                            </div>

                            {/* Certificações Avançadas */}
                            <div>
                              <div className="text-xs font-bold text-purple-700 mb-2 uppercase tracking-wide">Avançadas (Expert-level)</div>
                              <div className="space-y-2">
                                {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].advanced.map((cert) => {
                                  const certKey = cert.split(':')[0].trim();
                                  const certName = cert;
                                  return (
                                    <div key={certKey} className="flex items-center gap-3 p-2.5 bg-white rounded border border-purple-100">
                                      <div className="flex-1">
                                        <div className="font-medium text-xs text-gray-800">{certName}</div>
                                      </div>
                                      <div className="flex items-center gap-2">
                                        <label className="text-xs text-gray-500">Qtd:</label>
                                        <input 
                                          type="number" 
                                          min="0" 
                                          max="99"
                                          value={formData.certifications?.[certKey] || 0}
                                          onChange={(e) => {
                                            const newCerts = { ...formData.certifications, [certKey]: parseInt(e.target.value) || 0 };
                                            handleInputChange('certifications', newCerts);
                                          }}
                                          className="w-14 px-2 py-1 border border-gray-300 rounded text-xs text-center focus:border-[#005587] focus:ring-1 focus:ring-[#005587] outline-none"
                                        />
                                      </div>
                                    </div>
                                  );
                                })}
                              </div>
                            </div>

                            {/* Resumo */}
                            <div className="flex items-center justify-between p-3 bg-white/80 rounded border border-green-200">
                              <div className="flex items-center gap-2">
                                <CheckCircle size={16} className="text-green-600" />
                                <span className="text-xs font-semibold text-gray-700">Total de Certificações:</span>
                              </div>
                              <span className="text-lg font-bold text-green-700">
                                {Object.values(formData.certifications || {}).reduce((sum: number, val: any) => sum + val, 0)}
                              </span>
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Customer Success */}
                      <div className="bg-white p-4 rounded border border-gray-200 shadow-sm">
                        <div className="flex justify-between mb-2">
                          <span className="font-bold text-gray-700 flex items-center gap-2"><Users size={16} className="text-[#00A4EF]"/> Customer Success</span>
                          <span className="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600 font-mono">Max: {formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].customerSuccess : 30}</span>
                        </div>
                        <input type="range" min="0" max={formData.selectedSolutionArea ? PCS_MAX_SCORES[formData.selectedSolutionArea as SolutionArea].customerSuccess : 30} className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#00A4EF]" value={formData.pcsCustomerSuccess} onChange={e => handleInputChange('pcsCustomerSuccess', parseInt(e.target.value))} />
                        <div className="text-right font-bold text-[#00A4EF] mt-1">{formData.pcsCustomerSuccess} pts</div>
                      </div>
                    </div>

                    {/* Score Preview */}
                    <div className="mt-6 p-4 bg-gradient-to-r from-blue-50 to-sky-50 rounded-lg border border-blue-200">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Trophy size={20} className="text-[#005587]" />
                          <span className="font-semibold text-gray-700">Score Total:</span>
                        </div>
                        <span className="text-2xl font-bold text-[#005587]">{totalScore} pts</span>
                      </div>
                    </div>

                    <div className="flex justify-between mt-8">
                      <Button onClick={prevStep} variant="ghost">Voltar</Button>
                      <Button onClick={nextStep} variant="primary">Calcular Resultado <ArrowRight size={16} /></Button>
                    </div>
                  </div>
                )}

                {/* STEP 5: RESULTADO & PLANO FINANCEIRO */}
                {step === 5 && (
                  <div className="animate-in fade-in zoom-in-95 duration-500">
                    <div className="text-center mb-8">
                      <div className="inline-block p-4 rounded-full bg-slate-50 border-4 border-[#005587] mb-4">
                        <Trophy size={48} className="text-[#005587]" />
                      </div>
                      <h2 className="text-3xl font-black text-slate-800">{totalScore} <span className="text-xl text-gray-400 font-medium">/ 100</span></h2>
                      <p className="text-gray-500 font-medium mt-1">Seu Partner Capability Score</p>
                      <div className={`mt-3 inline-block px-4 py-1 rounded-full text-xs font-bold uppercase tracking-wide ${isPassing ? 'bg-[#7FBA00]/10 text-[#7FBA00]' : 'bg-amber-100 text-amber-700'}`}>
                         {isPassing ? 'Elegível para Designação' : 'Em Desenvolvimento'}
                      </div>
                    </div>

                    {/* Plano Sugerido (Enriched from PDF) */}
                    <div className="bg-slate-50 rounded-xl p-6 border border-gray-200 mb-6">
                      <h4 className="font-bold text-gray-800 mb-4 text-sm uppercase tracking-wide">Plano de Desenvolvimento: {formData.selectedSolutionArea}</h4>
                      <div className="space-y-4">
                         {formData.pcsSkilling < 20 && formData.selectedSolutionArea && formData.selectedSolutionArea in SKILLING_RULES && (
                           <div className="flex gap-3 items-start text-sm p-4 bg-white rounded border border-l-4 border-l-purple-500 border-gray-200">
                             <Award size={18} className="text-purple-500 shrink-0 mt-0.5"/>
                             <div>
                               <span className="font-bold text-gray-800">Skilling Gap Identificado</span>
                               <p className="text-gray-500 text-xs mt-1 mb-2">Para atingir a pontuação máxima em Skilling, você precisa certificar seu time nas seguintes tecnologias:</p>
                               <div className="text-xs bg-purple-50 p-2 rounded text-purple-800 font-mono mb-2">
                                 {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].intermediate.concat(SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].advanced).join(" • ")}
                               </div>
                               <p className="text-xs font-semibold text-gray-600">Recursos Recomendados (Playbook Oficial):</p>
                               <ul className="list-disc pl-4 mt-1 text-xs text-gray-500">
                                 {SKILLING_RULES[formData.selectedSolutionArea as SolutionArea].resources.map((res: string) => (
                                   <li key={res}>{res}</li>
                                 ))}
                               </ul>
                             </div>
                           </div>
                         )}
                      </div>
                    </div>
                    
                    {/* Calculadora de Incentivos */}
                    {formData.selectedSolutionArea && (
                      <IncentiveCalculator initialSolutionArea={formData.selectedSolutionArea} initialRevenue={parseFloat(formData.cspRevenue) || 0} />
                    )}

                    <div className="flex justify-between mt-10">
                       <Button onClick={prevStep} variant="ghost">Voltar</Button>
                       <Button onClick={nextStep} variant="primary" className="px-8 py-3 text-sm">
                         Definir Estratégia GTM <ArrowRight size={16} />
                       </Button>
                    </div>
                  </div>
                )}

                {/* STEP 6: GTM PLANNER */}
                {step === 6 && (
                  <div className="animate-in fade-in slide-in-from-right duration-500">
                    <div className="text-center mb-8">
                      <div className="inline-block p-4 rounded-full bg-slate-50 border-4 border-[#005587] mb-4">
                        <Flag size={48} className="text-[#005587]" />
                      </div>
                      <h2 className="text-3xl font-black text-slate-800">Estratégia de Go-to-Market</h2>
                      <p className="text-gray-500 font-medium mt-1">Planeje suas ações de geração de demanda e capacitação.</p>
                    </div>

                    {formData && (
                      <Card className="p-6 mb-6 border-l-4 border-l-[#005587] bg-white">
                        {(() => {
                          const readiness = calculateReadiness({
                            companyName: formData.companyName,
                            email: formData.email,
                            selectedSolutionArea: formData.selectedSolutionArea,
                            cspRevenue: formData.cspRevenue,
                            clientCount: formData.clientCount,
                            pcsPerformance: formData.pcsPerformance,
                            pcsSkilling: formData.pcsSkilling,
                            pcsCustomerSuccess: formData.pcsCustomerSuccess,
                            certifications: formData.certifications,
                            status: formData.status,
                            currentStep: formData.currentStep,
                          });

                          return (
                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                              <div className="lg:col-span-4">
                                <p className="text-xs font-bold text-gray-500 uppercase tracking-wide">Prontidão para Execução</p>
                                <div className="mt-2 flex items-end gap-2">
                                  <span className="text-4xl font-black text-slate-800">{readiness.score}</span>
                                  <span className="text-sm font-semibold text-gray-400">/ 100</span>
                                </div>
                                <p className="mt-2 text-sm text-gray-600">{readiness.nextAction}</p>
                              </div>

                              <div className="lg:col-span-8 space-y-4">
                                <div className="flex flex-wrap gap-2">
                                  {readiness.badges.length > 0 ? (
                                    readiness.badges.map((b) => (
                                      <Badge key={b.label} variant={b.variant as any}>{b.label}</Badge>
                                    ))
                                  ) : (
                                    <Badge variant="slate">Inicie o preenchimento para gerar o resumo</Badge>
                                  )}
                                </div>

                                <div className="bg-slate-50 border border-gray-200 rounded-lg p-4">
                                  <p className="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Checklist executivo (top 3)</p>
                                  <ul className="space-y-2 text-sm">
                                    {readiness.missingTop.length > 0 ? (
                                      readiness.missingTop.map((m) => (
                                        <li key={m.key} className="flex items-center gap-2 text-gray-700">
                                          <div className="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-gray-400">
                                            <CheckCircle size={14} />
                                          </div>
                                          <span>{m.label}</span>
                                        </li>
                                      ))
                                    ) : (
                                      <li className="flex items-center gap-2 text-gray-700">
                                        <div className="w-5 h-5 rounded-full bg-green-50 border border-green-200 flex items-center justify-center text-green-700">
                                          <CheckCircle size={14} />
                                        </div>
                                        <span>Tudo pronto. Você pode concluir e publicar o plano.</span>
                                      </li>
                                    )}
                                  </ul>
                                </div>
                              </div>
                            </div>
                          );
                        })()}
                      </Card>
                    )}

                    <GTMPlanner />

                    <div className="flex justify-between mt-8">
                       <Button onClick={prevStep} variant="ghost">Voltar para Incentivos</Button>
                       <Button onClick={async () => {
                         try {
                           // Salvar dados no banco antes de gerar o PDF
                           if (formData && formData.email && formData.companyName?.trim()) {
                             const updatedData = { 
                               ...formData, 
                               status: 'Completed',
                               currentStep: 6
                             };
                             await savePartner(updatedData);
                             console.log('Dados salvos com sucesso no banco!');
                             alert('Planejamento concluído e salvo com sucesso!');
                           } else {
                             alert('Preencha a Razão Social e Email antes de concluir.');
                             return;
                           }
                           // Gerar PDF
                           generateAssessmentPDF(formData);
                         } catch (err: any) {
                           console.error('Erro ao salvar:', err);
                           const msg = err?.message ? String(err.message) : String(err);
                           alert('Erro ao salvar dados: ' + msg);
                         }
                       }} variant="success" className="px-8 py-3 text-sm">
                         Concluir Planejamento <CheckCircle size={16} />
                       </Button>
                    </div>
                  </div>
                )}

              </Card>
           </div>

           {/* Sidebar Informativa */}
           <div className="space-y-6">
              <Card className="p-6">
                <h4 className="font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <Award className="text-[#005587]" size={18} /> 
                  Status da Parceria
                </h4>
                <div className="space-y-4">
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-gray-500">Nível Atual</span>
                      <span className="font-bold text-[#005587]">{isPassing ? 'Solution Partner' : 'Register'}</span>
                    </div>
                    <div className="w-full h-px bg-gray-100 my-2"></div>
                    <div className="bg-sky-50 p-3 rounded border border-sky-100">
                      <p className="text-xs text-[#005587] leading-relaxed">
                        Complete sua qualificação para desbloquear rebates de até <strong>4% a 10%</strong> dependendo da carga de trabalho.
                      </p>
                    </div>
                </div>
              </Card>

              {/* Card de Benefícios */}
              <Card className="p-6 bg-gradient-to-br from-purple-50 to-indigo-50 border-purple-100">
                <h4 className="font-bold text-gray-800 mb-3 flex items-center gap-2">
                  <Gift className="text-purple-600" size={18} /> 
                  Benefícios Exclusivos
                </h4>
                <p className="text-sm text-gray-600 mb-4">
                  Desbloqueie ferramentas, treinamentos e recursos conforme avança na jornada de parceria.
                </p>
                <button
                  onClick={() => setShowBenefits(true)}
                  className="w-full py-2.5 px-4 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2"
                >
                  Ver Meus Benefícios
                  <ChevronRight size={16} />
                </button>
              </Card>

              <Card className="p-6">
                <h4 className="font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <BookOpen className="text-[#005587]" size={18} /> 
                  Recursos Úteis
                </h4>
                <ul className="space-y-3 text-sm">
                  {['Guia de Skilling (PDF)', 'Tabela de Rebates FY25', 'Suporte Partner Center'].map((item, i) => (
                    <li key={i}>
                      <a href="#" className="flex items-center justify-between text-gray-600 hover:text-[#005587] group">
                        <span>{item}</span>
                        <ExternalLink size={14} className="opacity-0 group-hover:opacity-100 transition-opacity" />
                      </a>
                    </li>
                  ))}
                </ul>
              </Card>
           </div>
        </div>
      </main>

      {/* Modal de Benefícios */}
      {showBenefits && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={() => setShowBenefits(false)}>
          <div className="bg-white rounded-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b bg-gradient-to-r from-purple-600 to-indigo-600">
              <h2 className="text-xl font-bold text-white flex items-center gap-2">
                <Gift size={24} />
                Central de Benefícios
              </h2>
              <button onClick={() => setShowBenefits(false)} className="text-white/80 hover:text-white p-1">
                <X size={24} />
              </button>
            </div>
            <div className="overflow-y-auto max-h-[calc(90vh-80px)]">
              <BenefitsPage partnerData={formData} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
});
WizardViewComponent.displayName = 'WizardViewComponent';

export default function App() {
  const isMicrosoftAuthenticated = useIsAuthenticated();
  const { accounts } = useMsal();
  
  // Rastrear último email conhecido para detectar mudança real de usuário
  const lastKnownEmailRef = React.useRef<string | null>(null);
  
  // Flag para desabilitar auto-save durante logout
  const isLoggingOutRef = React.useRef(false);

  // Sistema de autenticação
  const [isAuthenticated, setIsAuthenticated] = useState(() => {
    const saved = sessionStorage.getItem('cloudpartner_admin_auth');
    return saved === 'true';
  });
  
  // Recuperar rota da URL ou localStorage
  const getInitialView = (): 'landing' | 'wizard' | 'admin' => {
    const path = window.location.pathname;
    if (path === '/admin' || path === '/admin/') return 'admin';
    if (path === '/wizard' || path === '/wizard/' || path.startsWith('/wizard/')) return 'wizard';
    return 'landing';
  };
  
  const [view, setView] = useState<'landing' | 'wizard' | 'admin'>(getInitialView);
  
  const [step, setStep] = useState(() => {
    const saved = localStorage.getItem('cloudpartner_step');
    return saved ? parseInt(saved, 10) : 1;
  });
  
  const [showLoginModal, setShowLoginModal] = useState(false);
  const [loginPassword, setLoginPassword] = useState('');
  const [loginError, setLoginError] = useState('');
  
  // Hook integrado com MySQL API
  const { 
    partner: formData, 
    setPartner: setFormData, 
    savePartner, 
    loading: savingData,
    error: saveError 
  } = usePartnerData();

  // Prefill de identidade a partir do Entra ID (sem sobrescrever valores já preenchidos)
  useEffect(() => {
    if (!isMicrosoftAuthenticated) return;
    const account = accounts?.[0];
    if (!account?.username) return;

    // Detectar mudança de conta (trocar de usuário)
    const currentEmail = account.username;
    const isAccountChange = lastKnownEmailRef.current !== null && 
                           lastKnownEmailRef.current !== currentEmail;

    if (isAccountChange) {
      console.log('🔄 Mudança de conta detectada:', lastKnownEmailRef.current, '→', currentEmail);
      localStorage.removeItem('cloudpartner_formdata');
      localStorage.removeItem('cloudpartner_step');
      
      setFormData({
        id: Date.now().toString(),
        companyName: '',
        contactName: account.name || '',
        email: currentEmail,
        isTdSynnexRegistered: false,
        isMicrosoftPartner: false,
        partnerTypeInterest: '',
        selectedSolutionArea: '',
        cspRevenue: '',
        clientCount: '',
        pcsPerformance: 0,
        pcsSkilling: 0,
        pcsCustomerSuccess: 0,
        currentStep: 1,
        status: 'In Progress',
        certifications: {}
      });
    } else if (!lastKnownEmailRef.current) {
      // Primeiro login - apenas preencher dados vazios
      setFormData((prev: any) => {
        if (!prev) return prev;
        const next = { ...prev };
        if (!next.email && currentEmail) next.email = currentEmail;
        if (!next.contactName && account.name) next.contactName = account.name;
        return next;
      });
    }

    // Atualizar ref com email atual
    lastKnownEmailRef.current = currentEmail;
  }, [isMicrosoftAuthenticated, accounts, setFormData]);

  // Após login, permanecer na landing para preencher Razão Social
  // (o redirecionamento para wizard acontece quando clica em "Iniciar Onboarding")
  useEffect(() => {
    if (!isMicrosoftAuthenticated || view !== 'landing') return;

    const hasJustLoggedIn = sessionStorage.getItem('cloudpartner_login_redirect');
    if (hasJustLoggedIn === 'pending') {
      sessionStorage.removeItem('cloudpartner_login_redirect');
      // Permanecer na landing - usuário preencherá Razão Social e clicará em Iniciar Onboarding
    }
  }, [isMicrosoftAuthenticated, view]);

  // Sincronizar view com URL
  useEffect(() => {
    const path = view === 'admin' ? '/admin' : view === 'wizard' ? `/wizard/${step}` : '/';
    if (window.location.pathname !== path) {
      window.history.pushState({}, '', path);
    }
    localStorage.setItem('cloudpartner_view', view);
  }, [view, step]);

  // Listener para botão voltar/avançar do navegador
  useEffect(() => {
    const handlePopState = () => {
      const newView = getInitialView();
      setView(newView);
    };
    
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  useEffect(() => {
    localStorage.setItem('cloudpartner_step', step.toString());
  }, [step]);

  // Salvar dados no MySQL automaticamente quando formData mudar (com debounce)
  useEffect(() => {
    // Não auto-salvar se estiver fazendo logout
    if (isLoggingOutRef.current) {
      console.log('⏸️  Auto-save desabilitado: logout em andamento');
      return;
    }
    
    // Não auto-salvar na página admin
    if (view === 'admin') {
      console.log('⏸️  Auto-save desabilitado: view admin');
      return;
    }
    
    // Verificar se o usuário está autenticado (no wizard)
    if (!isMicrosoftAuthenticated) {
      console.log('⏸️  Auto-save desabilitado: usuário não autenticado');
      return;
    }
    
    // Verificar se formData existe e tem ID válido
    if (!formData || !formData.id) {
      console.log('⏸️  Auto-save desabilitado: formData sem ID');
      return;
    }
    
    // Só auto-salvar se tiver dados mínimos preenchidos (não apenas após login)
    if (formData.email && formData.companyName && formData.companyName.trim() !== '') {
      const timer = setTimeout(() => {
        console.log('💾 Auto-salvando dados...', { 
          id: formData.id, 
          company: formData.companyName, 
          email: formData.email 
        });
        savePartner(formData).then(() => {
          console.log('✅ Dados salvos automaticamente');
        }).catch(err => {
          console.error('❌ Erro ao auto-salvar:', err.message);
          // Não mostrar erro ao usuário no auto-save
        });
      }, 2000); // Debounce de 2 segundos
      
      return () => clearTimeout(timer);
    } else {
      console.log('⏸️  Auto-save desabilitado: dados incompletos', {
        hasEmail: !!formData.email,
        hasCompany: !!formData.companyName,
        companyEmpty: formData.companyName?.trim() === ''
      });
    }
  }, [formData, savePartner, isMicrosoftAuthenticated, view]);

  // Sistema de autenticação simples
  const handleAdminLogin = () => {
    // Senha padrão: admin123 (em produção, use autenticação real)
    if (loginPassword === 'admin123') {
      setIsAuthenticated(true);
      sessionStorage.setItem('cloudpartner_admin_auth', 'true');
      setShowLoginModal(false);
      setLoginError('');
      setLoginPassword('');
      navigateTo('admin');
    } else {
      setLoginError('Senha incorreta. Tente novamente.');
    }
  };

  const handleAdminLogout = () => {
    // Marcar que estamos fazendo logout para desabilitar auto-save
    isLoggingOutRef.current = true;
    
    setIsAuthenticated(false);
    // Limpar todos os dados armazenados
    localStorage.removeItem('cloudpartner_formdata');
    localStorage.removeItem('cloudpartner_step');
    localStorage.removeItem('cloudpartner_view');
    sessionStorage.clear();
    
    // Resetar flag após limpar
    setTimeout(() => {
      isLoggingOutRef.current = false;
    }, 100);
    
    navigateTo('landing');
  };

  const navigateTo = (targetView: 'landing' | 'wizard' | 'admin') => {
    if (targetView === 'admin' && !isAuthenticated) {
      setShowLoginModal(true);
      return;
    }
    setView(targetView);
  };

  const totalScore = (formData?.pcsPerformance || 0) + (formData?.pcsSkilling || 0) + (formData?.pcsCustomerSuccess || 0);
  const isPassing = totalScore >= 70;
  
  const handleInputChange = useCallback((field: keyof PartnerData, value: any) => {
    setFormData(prev => {
      if (!prev) return prev;
      const newData = { ...prev, [field]: value };
      
      // Se mudou a Solution Area, ajustar os valores de PCS para respeitar os novos máximos
      if (field === 'selectedSolutionArea' && value) {
        const maxScores = PCS_MAX_SCORES[value as SolutionArea];
        if ((newData.pcsPerformance || 0) > maxScores.performance) {
          newData.pcsPerformance = maxScores.performance;
        }
        if ((newData.pcsSkilling || 0) > maxScores.skilling) {
          newData.pcsSkilling = maxScores.skilling;
        }
        if ((newData.pcsCustomerSuccess || 0) > maxScores.customerSuccess) {
          newData.pcsCustomerSuccess = maxScores.customerSuccess;
        }
      }
      
      return newData;
    });
  }, []);

  const startJourney = () => {
    if (!isMicrosoftAuthenticated) {
      // Marca que o usuário quer ir para o wizard após login
      sessionStorage.setItem('cloudpartner_login_redirect', 'pending');
      return;
    }
    navigateTo('wizard');
    setStep(2); 
    setFormData(prev => prev ? ({ ...prev, currentStep: 2 }) : prev);
  };

  const nextStep = () => {
    setStep(prev => prev + 1);
    setFormData(prev => prev ? ({ ...prev, currentStep: (prev.currentStep || 1) + 1 }) : prev);
  };
  const prevStep = () => setStep(prev => prev - 1);

  return (
    <div className="relative font-sans text-slate-800 bg-white">
      {/* Status de Salvamento */}
      {savingData && (
        <div className="fixed top-4 right-4 z-50 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
          <span className="text-sm font-medium">Salvando...</span>
        </div>
      )}
      
      {saveError && (
        <div className="fixed top-4 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
          <AlertCircle size={16} />
          <span className="text-sm font-medium">Erro ao salvar: {saveError}</span>
        </div>
      )}
      
      {/* Floating Admin Button */}
      <div className="fixed bottom-6 right-6 z-50 flex flex-col gap-2">
        <button 
          onClick={() => {
             if (view === 'admin') {
               handleAdminLogout();
             } else if (view === 'wizard') {
               navigateTo('admin');
             } else {
               navigateTo('admin');
             }
          }}
          className="flex items-center gap-2 bg-[#005587] text-white px-4 py-3 rounded-full shadow-2xl hover:bg-[#00446b] transition-all transform hover:scale-105 border border-[#003355]"
        >
          {view === 'admin' ? <User size={18} /> : <Lock size={18} />}
          <span className="font-semibold text-sm">
            {view === 'admin' ? 'Sair Admin' : 'Admin'}
          </span>
        </button>
      </div>

      {/* Login Modal */}
      {showLoginModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={() => setShowLoginModal(false)}>
          <div className="bg-white rounded-xl p-8 max-w-md w-full shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-lg bg-[#005587] flex items-center justify-center text-white">
                  <Lock size={24} />
                </div>
                <div>
                  <h2 className="text-xl font-bold text-gray-900">Acesso Admin</h2>
                  <p className="text-sm text-gray-500">Área restrita</p>
                </div>
              </div>
              <button onClick={() => setShowLoginModal(false)} className="text-gray-400 hover:text-gray-700">
                <X size={20} />
              </button>
            </div>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Senha de Acesso</label>
                <input
                  type="password"
                  value={loginPassword}
                  onChange={(e) => {
                    setLoginPassword(e.target.value);
                    setLoginError('');
                  }}
                  onKeyPress={(e) => e.key === 'Enter' && handleAdminLogin()}
                  placeholder="Digite a senha..."
                  className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#005587] focus:border-[#005587]"
                  autoFocus
                />
                {loginError && (
                  <p className="mt-2 text-sm text-red-600 flex items-center gap-1">
                    <AlertCircle size={14} />
                    {loginError}
                  </p>
                )}
              </div>
              
              <Button 
                onClick={handleAdminLogin}
                disabled={!loginPassword}
                className="w-full"
              >
                <Lock size={16} />
                Acessar Dashboard
              </Button>
              
              <p className="text-xs text-gray-400 text-center">
                Apenas usuários autorizados podem acessar esta área.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Views */}
      {view === 'landing' && <LandingViewComponent formData={formData} handleInputChange={handleInputChange} startJourney={startJourney} />}
      {view === 'wizard' && <WizardViewComponent formData={formData} handleInputChange={handleInputChange} step={step} nextStep={nextStep} prevStep={prevStep} totalScore={totalScore} isPassing={isPassing} savePartner={savePartner} isLoggingOutRef={isLoggingOutRef} />}
      {view === 'admin' && isAuthenticated && <AdminPage goBack={() => navigateTo('landing')} />}
      {view === 'admin' && !isAuthenticated && <LandingViewComponent formData={formData} handleInputChange={handleInputChange} startJourney={startJourney} />}
    </div>
  );
}