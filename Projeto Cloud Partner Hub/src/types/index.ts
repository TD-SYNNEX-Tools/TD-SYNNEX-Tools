export type SolutionArea = 'Modern Work' | 'Azure Infra' | 'Security' | 'Data & AI' | 'Digital & App Innovation' | 'Business Applications';
export type EdgeStage = 'Engage' | 'Develop' | 'Growth' | 'Extend';

export interface PartnerData {
  id: string;
  companyName: string;
  contactName: string;
  contactRole?: string;
  contactPhone?: string;
  email: string;
  phone?: string;
  mpnId?: string;
  isMicrosoftPartner?: boolean;
  isTdSynnexRegistered?: boolean;
  solutionArea?: SolutionArea;
  pcsScore?: number;
  revenueM365?: number;
  revenueAzure?: number;
  revenueSecurity?: number;
  certifications?: string[];
  createdAt?: string;
  currentStep: number;
  status: 'In Progress' | 'Completed' | 'Stalled';
}
