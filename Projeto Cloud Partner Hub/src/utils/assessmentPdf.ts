export interface AssessmentPartnerData {
  id: string;
  companyName: string;
  email: string;
  selectedSolutionArea?: string;
  isTdSynnexRegistered?: boolean;
  isMicrosoftPartner?: boolean;
  cspRevenue?: string;
  clientCount?: string;
  pcsPerformance?: number;
  pcsSkilling?: number;
  pcsCustomerSuccess?: number;
  certifications?: Record<string, number>;
}

export const generateAssessmentPDF = (partner: AssessmentPartnerData): void => {
  const pcsPerformance = Number(partner.pcsPerformance || 0) || 0;
  const pcsSkilling = Number(partner.pcsSkilling || 0) || 0;
  const pcsCustomerSuccess = Number(partner.pcsCustomerSuccess || 0) || 0;

  const totalPCS = pcsPerformance + pcsSkilling + pcsCustomerSuccess;
  const isPassing = totalPCS >= 70;
  const currentLevel = isPassing ? 'Solution Partner' : 'Registered';
  const targetLevel = 'Solution Partner';

  const CERTIFICATIONS_MAP: Record<string, { intermediate: string[]; advanced: string[]; specializations: string[] }> = {
    'Azure Infra': {
      intermediate: ['AZ-104: Azure Administrator Associate'],
      advanced: ['AZ-305: Azure Solutions Architect Expert'],
      specializations: ['Azure Virtual Desktop', 'Azure VMware Solution', 'SAP on Azure'],
    },
    'Data & AI': {
      intermediate: [
        'AZ-104',
        'DP-300 (Database)',
        'AI-102 (AI Engineer)',
        'DP-100 (Data Scientist)',
        'DP-203 (Data Engineer)',
      ],
      advanced: ['AZ-305: Azure Solutions Architect Expert'],
      specializations: ['AI and Machine Learning', 'Analytics on Azure', 'Data Warehouse Migration'],
    },
    'Digital & App Innovation': {
      intermediate: ['AZ-104', 'AZ-204 (Developer)', 'PL-400 (Power Platform Dev)'],
      advanced: ['AZ-305', 'AZ-400 (DevOps Engineer)'],
      specializations: ['DevOps with GitHub', 'Kubernetes on Azure', 'Modernize Enterprise Apps'],
    },
    'Modern Work': {
      intermediate: [
        'MS-900 (Fundamentals)',
        'MD-102 (Modern Desktop)',
        'MS-700 (Teams)',
        'MS-721 (Collab Engineer)',
        'SC-300 (Identity)',
      ],
      advanced: ['MS-102: Enterprise Administrator Expert'],
      specializations: ['Adoption and Change Management', 'Calling for Microsoft Teams', 'Teamwork Deployment'],
    },
    Security: {
      intermediate: ['AZ-500 (Azure Security)', 'SC-200 (Ops Analyst)', 'SC-300 (Identity)', 'SC-400 (Info Protection)'],
      advanced: ['SC-100: Cybersecurity Architect Expert'],
      specializations: ['Cloud Security', 'Identity and Access Management', 'Threat Protection'],
    },
    'Business Applications': {
      intermediate: [
        'MB-210 (Sales)',
        'MB-220 (Customer Insights)',
        'MB-230 (Service)',
        'MB-800 (Business Central)',
        'PL-200 (Power Platform)',
      ],
      advanced: ['MB-335 (Supply Chain Expert)', 'MB-700 (Finance Architect)', 'PL-600 (Solution Architect)'],
      specializations: ['Low Code App Development', 'Small and Midsize Business Management', 'Intelligent Automation'],
    },
  };

  const selectedSolutionArea = partner.selectedSolutionArea || '';
  const certifications = CERTIFICATIONS_MAP[selectedSolutionArea] || { intermediate: [], advanced: [], specializations: [] };

  const totalCertifications = Object.values(partner.certifications || {}).reduce(
    (sum: number, qty: any) => sum + (Number(qty) || 0),
    0
  );

  let certificationPoints = 0;
  if (selectedSolutionArea === 'Modern Work') {
    const avgPointsModernWork = 0.7 * 2.5 + 0.3 * 7.5;
    certificationPoints = totalCertifications * avgPointsModernWork;
  } else if (selectedSolutionArea === 'Azure Infra') {
    certificationPoints = totalCertifications * 4;
  } else if (selectedSolutionArea === 'Security') {
    certificationPoints = totalCertifications * 6.67;
  } else if (selectedSolutionArea === 'Digital & App Innovation') {
    certificationPoints = totalCertifications * 4;
  } else {
    certificationPoints = totalCertifications * 4;
  }

  const skillingSelected = pcsSkilling;
  const certificationGap = skillingSelected - certificationPoints;

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
                <div class="info-value">${partner.companyName || ''}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Email Corporativo</div>
                <div class="info-value">${partner.email || ''}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Área de Solução</div>
                <div class="info-value">${selectedSolutionArea || 'Não selecionada'}</div>
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
                <div class="value">${pcsPerformance}</div>
                <div class="max">de 30 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Skilling</div>
                <div class="value">${pcsSkilling}</div>
                <div class="max">de 40 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Customer Success</div>
                <div class="value">${pcsCustomerSuccess}</div>
                <div class="max">de 30 pontos</div>
            </div>
        </div>

        <div class="highlight" style="background: #f0f7ff; border-left: 4px solid #0078d4; margin-top: 20px;">
            <strong>Requisitos para Designação Solution Partner (${selectedSolutionArea}):</strong>
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
                    ${selectedSolutionArea === 'Azure Infra' ? '(4 pts por certificação)' : 
                      selectedSolutionArea === 'Modern Work' ? '(2.5 pts intermediária, 7.5 pts avançada)' : 
                      selectedSolutionArea === 'Security' ? '(6.67 pts por certificação intermediária)' :
                      selectedSolutionArea === 'Digital & App Innovation' ? '(4 pts por certificação)' :
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
                ${selectedSolutionArea === 'Azure Infra' ? 
                  `(${totalCertifications} certificações × 4 pontos cada)` :
                  selectedSolutionArea === 'Modern Work' ? 
                  `(Intermediárias: 2.5 pts | Avançadas: 7.5 pts)` :
                  selectedSolutionArea === 'Security' ?
                  `(${totalCertifications} certificações intermediárias × 6.67 pontos cada)` :
                  selectedSolutionArea === 'Digital & App Innovation' ?
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
            ${Object.entries(partner.certifications || {})
              .map(([certCode, qty]: [string, any]) => {
                const quantity = Number(qty) || 0;
                if (quantity === 0) return '';
                return `
              <tr>
                  <td>${certCode}</td>
                  <td style="text-align: center;">${quantity}</td>
                  <td style="text-align: center; font-weight: 600; color: #005587;">${quantity * 4} pts</td>
              </tr>
              `;
              })
              .filter(row => row)
              .join('')}
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
                <td>$$${parseFloat(partner.cspRevenue || '0').toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
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
        <h2>PLANO DE AÇÃO: ${selectedSolutionArea}</h2>
        
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
                ${pcsSkilling < 25 ? `Prioridade Crítica: ${pcsSkilling}/40 pontos. Foco em certificações intermediárias.` : 
                  pcsSkilling < 35 ? `${pcsSkilling}/40 pontos. Expandir para certificações avançadas.` :
                  `${pcsSkilling}/40 pontos. Manter atualização de certificações.`}
            </div>
            
            <div class="action-item">
                <strong>3. Performance e Receita</strong>
                ${pcsPerformance < 15 ? `Prioridade Alta: Expandir receita CSP e base de clientes. Meta sugerida: $$${(parseFloat(partner.cspRevenue || '0') * 2).toLocaleString('pt-BR')}/mês.` :
                  `Manter crescimento de receita recorrente. Atual: $$${parseFloat(partner.cspRevenue || '0').toLocaleString('pt-BR')}/mês`}
            </div>
            
            <div class="action-item">
                <strong>4. Customer Success</strong>
                Implementar programas de adoção e satisfação. Utilizar recursos Microsoft FastTrack e Customer Success Manager.
            </div>

            <div class="action-item">
                <strong>5. Estratégia Go-to-Market</strong>
                Desenvolver campanhas de geração de demanda para ${selectedSolutionArea}. Alavancar Marketing Development Funds (MDF) disponíveis.
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
        <p style="margin-top: 8px;">Documento confidencial | ${partner.companyName || ''}</p>
        <p style="margin-top: 10px; font-size: 10px;">Partner Development Manager | TD SYNNEX</p>
    </div>
</body>
</html>
  `;

  const printWindow = window.open('', '_blank');
  if (printWindow) {
    const safeCompanyName = String(partner.companyName || 'Partner').replace(/\s+/g, '_');
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
                filename: '${safeCompanyName}_Assessment_${new Date().toISOString().split('T')[0]}.pdf',
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

  // eslint-disable-next-line no-console
  console.log('Gerando PDF do relatório...');
};
