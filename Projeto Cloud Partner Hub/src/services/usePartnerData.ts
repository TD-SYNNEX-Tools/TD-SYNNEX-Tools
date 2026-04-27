import { useState, useEffect, useCallback } from 'react';
import apiService from './apiService';

interface PartnerData {
  id?: string;
  companyName: string;
  contactName?: string;
  email: string;
  phone?: string;
  mpnId?: string;
  isMicrosoftPartner?: boolean;
  isTdSynnexRegistered?: boolean;
  partnerTypeInterest?: string;
  selectedSolutionArea?: string;
  cspRevenue?: string;
  clientCount?: string;
  pcsPerformance?: number;
  pcsSkilling?: number;
  pcsCustomerSuccess?: number;
  currentStep?: number;
  status?: string;
  certifications?: Record<string, number>;
}

// Função auxiliar para dados padrão
function getDefaultPartnerData(): PartnerData {
  return {
    id: Date.now().toString(),
    companyName: '',
    contactName: '',
    email: '',
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
  };
}

export function usePartnerData(partnerId?: string) {
  const [partner, setPartner] = useState<PartnerData | null>(() => {
    // Inicializar do localStorage ou com dados padrão
    const localData = localStorage.getItem('cloudpartner_formdata');
    if (localData) {
      try {
        return JSON.parse(localData);
      } catch (err) {
        console.error('Erro ao carregar localStorage:', err);
      }
    }
    return getDefaultPartnerData();
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Carregar dados do parceiro
  const loadPartner = useCallback(async (id: string) => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiService.getPartnerById(id);
      setPartner(data);
      return data;
    } catch (err: any) {
      setError(err.message);
      // Fallback para localStorage se API falhar
      const localData = localStorage.getItem('cloudpartner_formdata');
      if (localData) {
        const parsed = JSON.parse(localData);
        setPartner(parsed);
        return parsed;
      }
    } finally {
      setLoading(false);
    }
  }, []);

  // Salvar parceiro (criar ou atualizar)
  const savePartner = useCallback(async (data: PartnerData) => {
    // Validação básica: não tentar salvar sem dados essenciais
    if (!data || !data.email) {
      console.warn('⚠️  savePartner: dados insuficientes (sem email)');
      throw new Error('Email é obrigatório');
    }
    
    if (!data.companyName || data.companyName.trim() === '') {
      console.warn('⚠️  savePartner: dados insuficientes (sem companyName)');
      throw new Error('Nome da empresa é obrigatório');
    }
    
    setLoading(true);
    setError(null);
    try {
      if (data.id && data.email) {
        await apiService.updatePartner(data.id, data);
      } else if (data.email) {
        const newId = `partner_${Date.now()}`;
        data.id = newId;
        await apiService.createPartner(data);
      }
      setPartner(data);
      // Backup no localStorage
      localStorage.setItem('cloudpartner_formdata', JSON.stringify(data));
      return data;
    } catch (err: any) {
      console.error('❌ Erro ao salvar na API:', err);
      setError(err.message);
      throw err; // Propagar erro para o auto-save capturar
    } finally {
      setLoading(false);
    }
  }, []);

  // Atualizar campo específico
  const updateField = useCallback(async (field: string, value: any) => {
    if (!partner) return;
    
    const updated = { ...partner, [field]: value };
    return savePartner(updated);
  }, [partner, savePartner]);

  // Carregar na montagem se partnerId fornecido
  useEffect(() => {
    if (partnerId) {
      loadPartner(partnerId);
    }
  }, [partnerId, loadPartner]);

  return {
    partner,
    loading,
    error,
    loadPartner,
    savePartner,
    updateField,
    setPartner
  };
}

export default usePartnerData;
