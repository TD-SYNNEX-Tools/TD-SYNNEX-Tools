import { PartnerData, EdgeStage } from '../types';
import html2pdf from 'html2pdf.js';

export const calculateEdgeStage = (partner: PartnerData): EdgeStage => {
  const totalRevenue = (partner.revenueM365 || 0) + (partner.revenueAzure || 0) + (partner.revenueSecurity || 0);
  const pcs = partner.pcsScore || 0;
  
  // Extend: Receita > $300k e PCS >= 70
  if (totalRevenue > 300000 && pcs >= 70) return 'Extend';
  
  // Growth: Receita > $100k e PCS >= 70
  if (totalRevenue > 100000 && pcs >= 70) return 'Growth';
  
  // Develop: Receita >= $2k e PCS >= 25
  if (totalRevenue >= 2000 && pcs >= 25) return 'Develop';
  
  // Engage: Inicial
  return 'Engage';
};

export const generatePartnerPDF = (partner: PartnerData): void => {
  // Simulação de geração de PDF
  console.log(`Gerando PDF para ${partner.companyName}...`);
  
  // Criar conteúdo do relatório
  const content = `
    Relatório do Parceiro: ${partner.companyName}
    ================================================
    
    Contato: ${partner.contactName}
    Email: ${partner.email}
    MPN ID: ${partner.mpnId || 'N/A'}
    
    Área de Solução: ${partner.solutionArea || 'N/A'}
    EDGE Stage: ${calculateEdgeStage(partner)}
    PCS Score: ${partner.pcsScore || 0}
    
    Receita:
    - Microsoft 365: $${partner.revenueM365?.toLocaleString() || 0}
    - Azure: $${partner.revenueAzure?.toLocaleString() || 0}
    - Security: $${partner.revenueSecurity?.toLocaleString() || 0}
    
    Status: ${partner.status}
    Etapa Atual: ${partner.currentStep}/6
  `;
  
  // Criar blob e download
  const blob = new Blob([content], { type: 'text/plain' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${partner.companyName.replace(/\s+/g, '_')}_Report.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
  
  console.log('PDF gerado com sucesso!');
};

// Mapeamento de certificações por área de solução
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

export const generateAssessmentPDF = (partner: PartnerData): void => {
  const totalPCS = partner.pcsScore || 0;
  const isPassing = totalPCS >= 70;
  const currentLevel = isPassing ? 'Solution Partner' : 'Registered';
  const targetLevel = 'Solution Partner';
  
  const certifications = CERTIFICATIONS_MAP[partner.solutionArea as string] || { intermediate: [], advanced: [], specializations: [] };
  
  // Criar documento HTML para conversão em PDF
  const htmlContent = `
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 40px;
            background: white;
        }
        .header { 
            text-align: center; 
            border-bottom: 4px solid #005587; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .header h1 { 
            color: #005587; 
            font-size: 28px; 
            margin-bottom: 5px; 
        }
        .header p { 
            color: #666; 
            font-size: 14px; 
        }
        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
        }
        .logo-text {
            font-size: 14px;
            font-weight: 600;
            color: #005587;
        }
        .section { 
            margin: 30px 0; 
            padding: 20px; 
            background: #f8f9fa; 
            border-left: 4px solid #005587; 
            border-radius: 4px;
        }
        .section h2 { 
            color: #005587; 
            font-size: 18px; 
            margin-bottom: 15px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section h3 { 
            color: #333; 
            font-size: 16px; 
            margin: 15px 0 10px 0; 
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 15px; 
            margin: 15px 0; 
        }
        .info-item { 
            background: white; 
            padding: 12px; 
            border-radius: 4px; 
            border: 1px solid #e0e0e0;
        }
        .info-label { 
            font-size: 11px; 
            color: #666; 
            text-transform: uppercase; 
            font-weight: 600; 
            margin-bottom: 4px;
        }
        .info-value { 
            font-size: 16px; 
            color: #333; 
            font-weight: 600; 
        }
        .pcs-score { 
            text-align: center; 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            margin: 20px 0;
            border: 2px solid ${isPassing ? '#7FBA00' : '#FFA500'};
        }
        .pcs-score .score { 
            font-size: 48px; 
            font-weight: bold; 
            color: ${isPassing ? '#7FBA00' : '#FFA500'}; 
        }
        .pcs-score .label { 
            font-size: 14px; 
            color: #666; 
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: ${isPassing ? '#7FBA00' : '#FFA500'};
            color: white;
            text-transform: uppercase;
        }
        .pcs-breakdown { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 15px; 
            margin: 20px 0; 
        }
        .pcs-item { 
            background: white; 
            padding: 15px; 
            text-align: center; 
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .pcs-item .title { 
            font-size: 11px; 
            color: #666; 
            text-transform: uppercase; 
            margin-bottom: 8px;
        }
        .pcs-item .value { 
            font-size: 24px; 
            font-weight: bold; 
            color: #005587; 
        }
        .pcs-item .max { 
            font-size: 12px; 
            color: #999; 
        }
        .cert-list { 
            list-style: none; 
            padding: 0; 
        }
        .cert-list li { 
            padding: 10px; 
            margin: 8px 0; 
            background: white; 
            border-left: 3px solid #00A4EF; 
            border-radius: 4px;
            font-size: 14px;
        }
        .action-plan {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #e0e0e0;
        }
        .action-item {
            padding: 12px;
            margin: 10px 0;
            background: #f8f9fa;
            border-left: 3px solid #7FBA00;
            border-radius: 4px;
        }
        .action-item strong {
            color: #005587;
            display: block;
            margin-bottom: 5px;
        }
        .footer { 
            margin-top: 40px; 
            padding-top: 20px; 
            border-top: 2px solid #e0e0e0; 
            text-align: center; 
            color: #666; 
            font-size: 12px; 
        }
        .highlight {
            background: #FFF9E6;
            padding: 15px;
            border-left: 3px solid #FFB900;
            border-radius: 4px;
            margin: 15px 0;
        }
        .highlight strong {
            color: #B8860B;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #005587;
            font-size: 12px;
            text-transform: uppercase;
        }
        td {
            font-size: 14px;
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
        <h2>📋 Informações do Parceiro</h2>
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
                <div class="info-value">${partner.solutionArea || 'Não selecionada'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status de Cadastro</div>
                <div class="info-value">
                    MPN ID: ${partner.mpnId || 'Não informado'}
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>📊 Partner Capability Score (PCS)</h2>
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
                <div class="value">${Math.round(totalPCS * 0.3)}</div>
                <div class="max">de 30 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Skilling</div>
                <div class="value">${Math.round(totalPCS * 0.4)}</div>
                <div class="max">de 40 pontos</div>
            </div>
            <div class="pcs-item">
                <div class="title">Customer Success</div>
                <div class="value">${Math.round(totalPCS * 0.3)}</div>
                <div class="max">de 30 pontos</div>
            </div>
        </div>

        ${!isPassing ? `
        <div class="highlight">
            <strong>⚠ Atenção:</strong> Você precisa atingir no mínimo 70 pontos para qualificar-se como Solution Partner. 
            Faltam <strong>${70 - totalPCS} pontos</strong> para alcançar este nível.
        </div>
        ` : `
        <div class="highlight" style="border-left-color: #7FBA00; background: #F0F8E8;">
            <strong>✓ Parabéns!</strong> Você atingiu a pontuação mínima para Solution Partner. 
            Continue investindo para alcançar níveis superiores (Advanced: 80+ pontos).
        </div>
        `}
    </div>

    <div class="section">
        <h2>📈 Métricas de Negócio</h2>
        <table>
            <tr>
                <th>Métrica</th>
                <th>Valor Atual</th>
            </tr>
            <tr>
                <td>Receita Microsoft 365</td>
                <td>$${(partner.revenueM365 || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
            </tr>
            <tr>
                <td>Receita Azure</td>
                <td>$${(partner.revenueAzure || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
            </tr>
            <tr>
                <td>Receita Security</td>
                <td>$${(partner.revenueSecurity || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
            </tr>
            <tr>
                <td>Nível Atual</td>
                <td><strong>${currentLevel}</strong></td>
            </tr>
            <tr>
                <td>Nível Alvo</td>
                <td><strong style="color: #7FBA00;">${targetLevel}</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>🎯 Plano de Ação para Designação ${partner.solutionArea || 'Microsoft'}</h2>
        
        <h3>1. Certificações Intermediárias Obrigatórias</h3>
        <ul class="cert-list">
            ${certifications.intermediate.map(cert => `<li>📘 ${cert}</li>`).join('')}
        </ul>

        <h3>2. Certificações Avançadas (Expert)</h3>
        <ul class="cert-list">
            ${certifications.advanced.map(cert => `<li>🎓 ${cert}</li>`).join('')}
        </ul>

        <h3>3. Especializações Recomendadas</h3>
        <ul class="cert-list">
            ${certifications.specializations.map(spec => `<li>⭐ ${spec}</li>`).join('')}
        </ul>
    </div>

    <div class="section">
        <h2>✅ Próximos Passos</h2>
        <div class="action-plan">
            <div class="action-item">
                <strong>Passo 1: Completar Registro</strong>
                ${!partner.mpnId ? 'Registre-se no Microsoft Partner Center (MPN) para obter seu MPN ID.' : `✓ MPN ID cadastrado: ${partner.mpnId}`}
            </div>
            
            <div class="action-item">
                <strong>Passo 2: Desenvolver Competências (Skilling)</strong>
                ${totalPCS < 50 ? `⚠ Prioridade Alta - Você tem ${totalPCS}/100 pontos no PCS. Foque nas certificações intermediárias primeiro.` : 
                  totalPCS < 70 ? `Você tem ${totalPCS}/100 pontos. Continue com as certificações avançadas para atingir Solution Partner (70+).` :
                  `✓ Excelente! ${totalPCS}/100 pontos. Mantenha as certificações atualizadas.`}
            </div>
            
            <div class="action-item">
                <strong>Passo 3: Aumentar Performance</strong>
                Foque em aumentar a receita CSP e base de clientes. Receita total atual: $${((partner.revenueM365 || 0) + (partner.revenueAzure || 0) + (partner.revenueSecurity || 0)).toLocaleString('pt-BR')}
            </div>
            
            <div class="action-item">
                <strong>Passo 4: Customer Success</strong>
                Implemente programas de adoção e satisfação do cliente. Use os recursos da Microsoft como FastTrack e Customer Success Manager.
            </div>

            <div class="action-item">
                <strong>Passo 5: Estratégia GTM</strong>
                Desenvolva campanhas de geração de demanda focadas em ${partner.solutionArea || 'suas áreas de solução'}. Utilize os fundos de Co-op (Marketing Development Funds) disponíveis.
            </div>
        </div>
    </div>

    <div class="section">
        <h2>💰 Benefícios da Designação</h2>
        <div style="background: white; padding: 15px; border-radius: 4px; margin-top: 15px;">
            <p><strong>Ao alcançar a designação Solution Partner, você terá acesso a:</strong></p>
            <ul style="margin: 15px 0 0 20px; line-height: 1.8;">
                <li><strong>Rebates:</strong> 4% a 10% sobre receita CSP (varia por workload)</li>
                <li><strong>Co-op Funds:</strong> 40% dos incentivos para atividades de marketing</li>
                <li><strong>Recursos Técnicos:</strong> Suporte técnico avançado e acesso a especialistas Microsoft</li>
                <li><strong>Marketing:</strong> Logotipos, materiais de co-branding e marketplace Microsoft</li>
                <li><strong>Treinamentos:</strong> Cloud Weeks, workshops e bootcamps exclusivos</li>
                <li><strong>Prioridade:</strong> Acesso preferencial a programas e incentivos especiais</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p><strong>CloudPartner HUB</strong> - Powered by TD SYNNEX & Microsoft</p>
        <p style="margin-top: 5px;">Este relatório é confidencial e destinado exclusivamente a ${partner.companyName}</p>
        <p style="margin-top: 10px; font-size: 11px;">Para mais informações, entre em contato com seu Partner Development Manager da TD SYNNEX</p>
    </div>
</body>
</html>
  `;

  // Criar elemento temporário para conversão
  const element = document.createElement('div');
  element.innerHTML = htmlContent;
  document.body.appendChild(element);

  // Configurações para geração do PDF
  const opt = {
    margin: [10, 10, 10, 10] as [number, number, number, number],
    filename: `${partner.companyName.replace(/\s+/g, '_')}_Assessment_${new Date().toISOString().split('T')[0]}.pdf`,
    image: { type: 'jpeg' as const, quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, logging: false },
    jsPDF: { unit: 'mm' as const, format: 'a4' as const, orientation: 'portrait' as const }
  };

  // Gerar PDF
  html2pdf().set(opt).from(element).save().then(() => {
    document.body.removeChild(element);
    console.log('✅ PDF gerado com sucesso!');
    alert('📄 Relatório de Assessment gerado com sucesso!\n\nO arquivo PDF foi baixado para sua pasta de Downloads.');
  }).catch((error: any) => {
    console.error('Erro ao gerar PDF:', error);
    if (element.parentNode) {
      document.body.removeChild(element);
    }
    alert('❌ Erro ao gerar PDF. Por favor, tente novamente.');
  });
};
