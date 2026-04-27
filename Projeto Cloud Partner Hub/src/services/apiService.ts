// Detecta se está em desenvolvimento ou produção em RUNTIME
// Produção: usa URL relativa /api (mesmo servidor)
// Desenvolvimento: usa localhost:3001
// IMPORTANTE: Chamada em cada request para garantir detecção em runtime
const getApiBaseUrl = (): string => {
  if (typeof window !== 'undefined') {
    const hostname = window.location.hostname;
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return 'http://localhost:3001/api';
    }
  }
  return '/api';
};

const toFriendlyApiError = (err: unknown): Error => {
  const message = err instanceof Error ? err.message : String(err);
  if (/Failed to fetch/i.test(message) || /NetworkError/i.test(message)) {
    return new Error(
      `Não foi possível conectar à API (${getApiBaseUrl()}). Verifique se o backend está disponível.`
    );
  }
  return err instanceof Error ? err : new Error(message);
};

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

class ApiService {
  // Listar todos os parceiros
  async getAllPartners(): Promise<PartnerData[]> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/partners`);
      if (!response.ok) throw new Error('Erro ao buscar parceiros');
      return response.json();
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }

  // Buscar parceiro por ID
  async getPartnerById(id: string): Promise<PartnerData> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/partners/${id}`);
      if (!response.ok) throw new Error('Parceiro não encontrado');
      return response.json();
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }

  // Criar novo parceiro
  async createPartner(data: PartnerData): Promise<{ id: string }> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/partners`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      
      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Erro ao criar parceiro');
      }
      
      return response.json();
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }

  // Atualizar parceiro
  async updatePartner(id: string, data: PartnerData): Promise<void> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/partners/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      
      if (!response.ok) {
        throw new Error('Erro ao atualizar parceiro');
      }
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }

  // Deletar parceiro
  async deletePartner(id: string): Promise<void> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/partners/${id}`, {
        method: 'DELETE'
      });
      
      if (!response.ok) {
        throw new Error('Erro ao deletar parceiro');
      }
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }

  // Health check
  async healthCheck(): Promise<{ status: string }> {
    try {
      const response = await fetch(`${getApiBaseUrl()}/health`);
      return response.json();
    } catch (err) {
      throw toFriendlyApiError(err);
    }
  }
}

export const apiService = new ApiService();
export default apiService;
