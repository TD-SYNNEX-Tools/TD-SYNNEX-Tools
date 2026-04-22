document.addEventListener('DOMContentLoaded', () => {
    // Carregar a logo como base64 a partir do arquivo
    let logoDataUrl = null;
    
    // Função para converter imagem para base64
    const loadLogoAsBase64 = () => {
        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                logoDataUrl = canvas.toDataURL('image/png');
                // Expor no escopo global para generateAdvisorPDF
                window._advisorLogoDataUrl = logoDataUrl;
                resolve(logoDataUrl);
            };
            img.onerror = function() {
                console.log('Não foi possível carregar a logo');
                resolve(null);
            };
            img.src = 'logo.png';
        });
    };
    
    // Carregar logo ao iniciar
    loadLogoAsBase64();
    
    // Event delegation for PDF button
    document.addEventListener('click', async (e) => {
        const pdfButton = e.target.closest('#generate-pdf');
        if (pdfButton) {
            e.preventDefault(); // Previne comportamento padrão se houver
            
            // Verificação robusta do jsPDF
            let jsPDF;
            if (window.jspdf && window.jspdf.jsPDF) {
                jsPDF = window.jspdf.jsPDF;
            } else if (window.jsPDF) {
                jsPDF = window.jsPDF;
            } else {
                console.error('Biblioteca jsPDF não encontrada');
                alert('Erro: Biblioteca de PDF não carregada corretamente. Por favor, recarregue a página.');
                return;
            }

            if (!window.calculatorData) {
                console.error('Dados da calculadora não encontrados');
                alert('Erro: Dados para o relatório não disponíveis. Tente realizar o cálculo novamente.');
                return;
            }

            try {
                const doc = new jsPDF();
                const data = window.calculatorData;
                const estimate = data.estimate;
                const result = data.result;
                
                const pageWidth = doc.internal.pageSize.width;
                const pageHeight = doc.internal.pageSize.height;

            // --- Paleta de Cores TD SYNNEX ---
            const colors = {
                teal: [0, 87, 88],        // #005758
                tealDark: [0, 48, 49],    // #003031
                charcoal: [38, 38, 38],   // #262626
                gray: [115, 115, 115],    // #737373
                lightGray: [245, 245, 247], // #f5f5f7
                white: [255, 255, 255],
                blue: [0, 120, 212]       // Azure Blue
            };

            // Logo TD SYNNEX em Base64
            const logoBase64 = 'data:image/png;base64,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';

            // --- Helpers ---
            const formatCurrency = (value) => {
                const symbol = estimate.currency === 'BRL' ? 'R$' : '$';
                return `${symbol} ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            };

            const drawHeader = (title) => {
                // Barra superior
                doc.setFillColor(...colors.lightGray);
                doc.rect(0, 0, pageWidth, 25, 'F');
                
                // Título do Relatório
                doc.setFontSize(10);
                doc.setTextColor(...colors.gray);
                doc.setFont('helvetica', 'normal');
                doc.text('Azure ARC vs SPLA Calculator', 15, 17);

                // Data
                doc.text(new Date().toLocaleDateString('pt-BR'), pageWidth - 15, 17, { align: 'right' });
            };

            const drawFooter = (pageNumber) => {
                const footerY = pageHeight - 10;
                doc.setDrawColor(200, 200, 200);
                doc.line(15, footerY - 5, pageWidth - 15, footerY - 5);
                
                doc.setFontSize(8);
                doc.setTextColor(...colors.gray);
                doc.text('Documento Confidencial', 15, footerY);
                doc.text(`Página ${pageNumber}`, pageWidth - 15, footerY, { align: 'right' });
            };

            // ================= PÁGINA 1: CAPA =================
            // Fundo lateral
            doc.setFillColor(...colors.teal);
            doc.rect(0, 0, 15, pageHeight, 'F');

            // Logo TD SYNNEX na Capa
            if (logoDataUrl) {
                try {
                    doc.addImage(logoDataUrl, 'PNG', 40, 32, 65, 12);
                } catch (e) {
                    console.log('Erro ao carregar logo na capa:', e);
                }
            }

            // Título Principal
            doc.setFontSize(36);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'bold');
            doc.text('RELATÓRIO', 40, 60);
            doc.text('EXECUTIVO', 40, 75);

            doc.setFontSize(24);
            doc.setTextColor(...colors.teal);
            doc.text('Azure ARC', 40, 95);
            doc.setFontSize(18);
            doc.setTextColor(...colors.gray);
            doc.setFont('helvetica', 'normal');
            doc.text('vs SPLA', 40, 105);

            // Linha decorativa
            doc.setDrawColor(...colors.teal);
            doc.setLineWidth(1);
            doc.line(40, 115, 100, 115);

            // Box de Informações
            const boxY = 125;
            doc.setFillColor(...colors.lightGray);
            doc.rect(40, boxY, 140, 85, 'F');
            doc.setDrawColor(...colors.teal);
            doc.rect(40, boxY, 2, 85, 'F'); // Detalhe lateral do box

            // Texto dentro do box
            doc.setFontSize(12);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');
            doc.text('Estudo Comparativo de Custos e', 50, boxY + 12);
            doc.text('Modernização do modelo de licenciamento', 50, boxY + 20);

            doc.setFontSize(10);
            doc.setTextColor(...colors.gray);
            doc.text('Data de Emissão', 50, boxY + 38);
            doc.text('Moeda', 120, boxY + 38);

            doc.setFontSize(11);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'bold');
            doc.text(new Date().toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' }), 50, boxY + 45);
            doc.text(estimate.currency, 120, boxY + 45);

            // ================= PÁGINA 2: RESUMO EXECUTIVO =================
            doc.addPage();
            drawHeader();
            
            let y = 40;
            doc.setFontSize(18);
            doc.setTextColor(...colors.teal);
            doc.setFont('helvetica', 'bold');
            doc.text('Resumo Executivo', 15, y);

            y += 15;
            doc.setFontSize(10);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');
            doc.text('A seguir, apresentamos um comparativo financeiro objetivo entre o modelo tradicional de licenciamento (SPLA) e o modelo moderno baseado em consumo por meio do Azure Arc. Esta análise é especificamente focada no SQL Server, considerando os impactos financeiros, operacionais e de modelo de licenciamento associado a essa abordagem.', 15, y, { maxWidth: 180 });

            // Tabela de Resultados
            y += 20;

            if (window.comparisonData && window.comparisonData.length > 0) {
                // MODO COMPARAÇÃO MULTI-MODELO
                const col1 = 15;
                const col2 = 85;
                const col3 = 135;
                const col4 = 180;

                // Header
                doc.setFillColor(...colors.teal);
                doc.rect(15, y, pageWidth - 30, 10, 'F');
                doc.setTextColor(...colors.white);
                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.text('MODELO', col1 + 2, y + 6);
                doc.text('TIPO', col2, y + 6);
                doc.text('MENSALIDADE (MÉDIA)', col3, y + 6, { align: 'right' });
                doc.text('TOTAL PERÍODO', col4, y + 6, { align: 'right' });

                // Dados
                const periodKey = (estimate.months <= 12) ? 12 : ((estimate.months <= 36) ? 36 : 60);
                
                // Ordenar por custo total (menor para maior)
                const sortedModels = [...window.comparisonData].sort((a, b) => a.costs[periodKey].total - b.costs[periodKey].total);

                sortedModels.forEach((model, index) => {
                    y += 10;
                    if (index % 2 === 0) doc.setFillColor(...colors.lightGray);
                    else doc.setFillColor(255, 255, 255);
                    
                    doc.rect(15, y, pageWidth - 30, 10, 'F');
                    doc.setTextColor(...colors.charcoal);
                    doc.setFont('helvetica', 'normal');
                    
                    // Highlight best option
                    if (index === 0) {
                         doc.setFont('helvetica', 'bold');
                         doc.setTextColor(...colors.teal);
                    }

                    doc.text(model.name, col1 + 2, y + 7);
                    
                    let billingType = model.billing_type === 'monthly' ? 'Mensal' : (model.billing_type === 'perpetual' ? 'Perpétuo' : 'Contrato');
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(...colors.gray);
                    doc.text(billingType, col2, y + 7);

                    doc.setTextColor(...colors.charcoal);
                    if (index === 0) doc.setTextColor(0, 150, 0); // Green for best price

                    doc.text(formatCurrency(model.costs[periodKey].monthly_average), col3, y + 7, { align: 'right' });
                    doc.text(formatCurrency(model.costs[periodKey].total), col4, y + 7, { align: 'right' });
                });

                // Calculo de economia (Melhor vs Pior selecionado)
                const best = sortedModels[0];
                const worst = sortedModels[sortedModels.length - 1];
                const savingsVal = worst.costs[periodKey].total - best.costs[periodKey].total;

                // Totalizador
                y += 20;
                doc.setDrawColor(...colors.teal);
                doc.setLineWidth(0.5);
                doc.line(15, y, pageWidth - 15, y);
                
                y += 10;
                doc.setFontSize(12);
                doc.setTextColor(...colors.charcoal);
                doc.text(`Economia Potencial (${best.name} vs ${worst.name}):`, 15, y);
                doc.setFontSize(14);
                doc.setTextColor(...colors.teal);
                doc.text(formatCurrency(savingsVal), pageWidth - 15, y, { align: 'right' });

            } else {
                // MODO PADRÃO (ARC vs SPLA)
                const col1 = 15;
                const col2 = 60;
                const col3 = 100;
                const col4 = 140;
                const col5 = 180;

                // Header da Tabela
                doc.setFillColor(...colors.teal);
                doc.rect(15, y, pageWidth - 30, 10, 'F');
                doc.setTextColor(...colors.white);
                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.text('ITEM', col1 + 2, y + 6);
                doc.text('CAPACIDADE', col2, y + 6);
                doc.text('AZURE ARC', col3, y + 6);
                doc.text('SPLA', col4, y + 6);
                doc.text('ECONOMIA', col5, y + 6, { align: 'right' });

                // Linha 1
                y += 10;
                doc.setFillColor(...colors.lightGray);
                doc.rect(15, y, pageWidth - 30, 12, 'F');
                doc.setTextColor(...colors.charcoal);
                doc.setFont('helvetica', 'normal');
                doc.text(`SQL Server ${estimate.edition}`, col1 + 2, y + 8);
                doc.text(`${estimate.vCores} vCores`, col2, y + 8);
                doc.text(formatCurrency(result.arc.finalMonthly), col3, y + 8);
                doc.text(formatCurrency(result.spla.monthly), col4, y + 8);
                
                doc.setTextColor(0, 150, 0); // Green for savings
                doc.setFont('helvetica', 'bold');
                doc.text(formatCurrency(result.savings.annual), col5, y + 8, { align: 'right' });

                // Totalizador
                y += 20;
                doc.setDrawColor(...colors.teal);
                doc.setLineWidth(0.5);
                doc.line(15, y, pageWidth - 15, y);
                
                y += 10;
                doc.setFontSize(12);
                doc.setTextColor(...colors.charcoal);
                doc.text('Economia Total Projetada (Anual):', 15, y);
                doc.setFontSize(14);
                doc.setTextColor(...colors.teal);
                doc.text(formatCurrency(result.savings.annual), pageWidth - 15, y, { align: 'right' });
            }


            // Detalhes Técnicos
            y += 20;
            const modelsCount = (window.comparisonData && window.comparisonData.length > 0) ? window.comparisonData.length : 2;

            doc.setFillColor(240, 250, 255);
            doc.rect(15, y, pageWidth - 30, 40, 'F');
            doc.setDrawColor(...colors.blue);
            doc.rect(15, y, 2, 40, 'F');

            doc.setFontSize(10);
            doc.setTextColor(...colors.blue);
            doc.setFont('helvetica', 'bold');
            doc.text('Detalhes da Configuração', 25, y + 10);
            
            doc.setFontSize(9);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');
            doc.text(`• Edição: SQL Server ${estimate.edition}`, 25, y + 20);
            doc.text(`• Carga Horária: ${estimate.hoursPerMonth} horas/mês`, 25, y + 28);
            doc.text(`• Modelos Comparados: ${modelsCount}`, 100, y + 20);
            doc.text(`• Taxa de Câmbio: ${estimate.currency === 'BRL' ? 'Aplicada' : 'N/A'}`, 100, y + 28);

            drawFooter(2);

            // ================= PÁGINA 3: ANÁLISE ESTRATÉGICA & ROI =================
            doc.addPage();
            drawHeader();

            y = 40;
            doc.setFontSize(18);
            doc.setTextColor(...colors.teal);
            doc.setFont('helvetica', 'bold');
            doc.text('Análise Estratégica & ROI', 15, y);

            y += 15;
            doc.setFontSize(10);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');
            doc.text('Projeção de economia acumulada ao longo de 36 meses (Ciclo típico de contrato).', 15, y);

            // Gráfico Simulado (Barras de progressão)
            y += 20;
            const chartHeight = 100;
            const chartWidth = pageWidth - 30;
            const months = [12, 24, 36];

            let maxSavings, scale;
            let sortedModels = [];
            const periodKey = (estimate.months <= 12) ? 12 : ((estimate.months <= 36) ? 36 : 60);

            if (window.comparisonData && window.comparisonData.length > 0) {
                 // Usa o melhor e o pior para calcular a economia máxima possivel nos 3 anos
                sortedModels = [...window.comparisonData].sort((a, b) => a.costs[36].total - b.costs[36].total);
                const best = sortedModels[0];
                const worst = sortedModels[sortedModels.length - 1];
                maxSavings = worst.costs[36].total - best.costs[36].total;
            } else {
                maxSavings = result.savings.annual * 3;
            }
            
            // Ajuste de escala (evitar divisão por zero)
            scale = (maxSavings > 0) ? (chartHeight - 20) / maxSavings : 0;
            
            // Eixo Y e X
            doc.setDrawColor(200, 200, 200);
            doc.line(25, y, 25, y + chartHeight); // Y
            doc.line(25, y + chartHeight, 25 + chartWidth, y + chartHeight); // X

            // Títulos dos Eixos
            doc.setFontSize(9);
            doc.setTextColor(...colors.gray);
            doc.text('Economia Acumulada', 10, y + (chartHeight/2), { angle: 90, align: 'center' }); // Y Axis Title
            doc.text('Tempo de Contrato', 25 + (chartWidth/2), y + chartHeight + 25, { align: 'center' }); // X Axis Title

            // Ticks do Eixo Y (Valores de referência)
            doc.setFontSize(7);
            // 0
            doc.text(estimate.currency === 'BRL' ? 'R$ 0' : '$ 0', 23, y + chartHeight, { align: 'right' });
            
            // 50%
            const midVal = maxSavings / 2;
            const midY = y + chartHeight - (midVal * scale);
            doc.text(formatCurrency(midVal), 23, midY + 2, { align: 'right' });
            doc.line(24, midY, 25, midY); // Tick line


            // 100%
            const maxY = y + chartHeight - (maxSavings * scale);
            doc.text(formatCurrency(maxSavings), 23, maxY + 2, { align: 'right' });
            doc.line(24, maxY, 25, maxY); // Tick line

            // Legenda do Gráfico
            const legendX = 25 + chartWidth - 50;
            const legendY = y + 5;
            doc.setFillColor(...colors.teal);
            doc.rect(legendX, legendY, 4, 4, 'F');
            doc.setFontSize(8);
            doc.setTextColor(...colors.charcoal);
            doc.text('Economia Acumulada', legendX + 6, legendY + 3);

            const barWidth = 30;

            if (window.comparisonData && window.comparisonData.length > 0) {
                 // COMPARATIVO: MOSTRAR ECONOMIA DO MELHOR VS PIOR
                 // sortedModels[0] é o mais barato
                 // sortedModels[last] é o mais caro

                const best = sortedModels[0];
                const worst = sortedModels[sortedModels.length - 1];

                months.forEach((month, index) => {
                    // Tenta pegar o custo exato do periodo se existir, senão projeta
                    let bestCost, worstCost;
                    
                    if (best.costs[month]) {
                        bestCost = best.costs[month].total;
                        worstCost = worst.costs[month].total;
                    } else if (month === 24 && best.costs[12] && best.costs[36]) {
                        // Interpolação simples para 24 meses (entre 12 e 36)
                        bestCost = (best.costs[12].total + best.costs[36].total) / 2;
                        worstCost = (worst.costs[12].total + worst.costs[36].total) / 2;
                    } else {
                        // Fallback melhor esforço
                        const refMonth = best.costs[12] ? 12 : (best.costs[36] ? 36 : 60);
                        const ratio = month / refMonth;
                        bestCost = best.costs[refMonth].total * ratio;
                        worstCost = worst.costs[refMonth].total * ratio;
                    }

                    const savings = worstCost - bestCost;
                    // scale calculada com base no maxSavings (36 meses)
                    // savings de 12 meses será menor, então a barra será menor proporcionalmente.
                    // Mas precisamos garantir que a barra não estoure o topo se savings > maxSavings (improvável aqui pois maxSavings é o de 36m)
                    
                    const barHeight = savings * scale; 
                    const finalHeight = Math.max(0, barHeight); 
                    const yPos = y + chartHeight - finalHeight;

                    // Barra (Cor baseada no sinal)
                    if (savings >= 0) {
                         doc.setFillColor(...colors.teal);
                    } else {
                         doc.setFillColor(200, 50, 50);
                    }
                    doc.rect(xPos, yPos, barWidth, finalHeight, 'F');

                    // Label Valor
                    doc.setFontSize(9);
                    doc.setTextColor(...colors.charcoal);
                    doc.text(formatCurrency(savings), xPos + (barWidth/2), yPos - 5, { align: 'center' });

                    // Label Meses
                    doc.text(`${month} Meses`, xPos + (barWidth/2), y + chartHeight + 10, { align: 'center' });
                });

                // Legenda extra
                doc.setFontSize(8);
                doc.setTextColor(...colors.gray);
                doc.text(`* Economia comparando ${best.name} vs ${worst.name}`, 25, y + chartHeight + 25);


            } else {
                months.forEach((month, index) => {
                    const savings = result.savings.annual * (month / 12);
                    const barHeight = savings * scale;
                    const xPos = 50 + (index * 60);
                    const yPos = y + chartHeight - barHeight;

                    // Barra
                    doc.setFillColor(...colors.teal);
                    doc.rect(xPos, yPos, barWidth, barHeight, 'F');

                    // Label Valor
                    doc.setFontSize(9);
                    doc.setTextColor(...colors.charcoal);
                    doc.text(formatCurrency(savings), xPos + (barWidth/2), yPos - 5, { align: 'center' });

                    // Label Meses
                    doc.text(`${month} Meses`, xPos + (barWidth/2), y + chartHeight + 10, { align: 'center' });
                });
            }

            drawFooter(3);

            // ================= PÁGINA 4: COMPARATIVO MENSAL & CONCLUSÃO =================
            doc.addPage();
            drawHeader();
            y = 40;

            doc.setFontSize(16);
            doc.setTextColor(...colors.teal);
            doc.setFont('helvetica', 'bold');
            doc.text('Comparativo de Custos Mensais', 15, y);

            y += 10;
            doc.setFontSize(10);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');
            
            if (window.comparisonData && window.comparisonData.length > 0) {
                const modelCount = window.comparisonData.length;
                doc.text(`Comparação direta do custo mensal médio entre os ${modelCount} modelos de licenciamento selecionados para análise.`, 15, y);
            } else {
                doc.text('Comparação direta do custo mensal entre o licenciamento SPLA atual e o modelo Azure ARC.', 15, y);
            }

            y += 20;

            // Gráfico Comparativo
            const compChartHeight = 100;
            let compMaxVal, compScale;

            if (window.comparisonData && window.comparisonData.length > 0) {
                 // Multi-comparativo
                 compMaxVal = Math.max(...sortedModels.map(m => m.costs[periodKey].monthly_average)) * 1.2;
                 compScale = compChartHeight / compMaxVal;
                 
                 // Eixos
                 doc.setDrawColor(200, 200, 200);
                 doc.line(25, y, 25, y + compChartHeight); // Y
                 doc.line(25, y + compChartHeight, 25 + chartWidth, y + compChartHeight); // X

                 // Legendas Dinamicas
                 const modelsCount = sortedModels.length;
                 const barWidth = Math.min(50, (chartWidth - 50) / modelsCount) - 10;
                 let xPos = 40;

                 sortedModels.forEach((model, idx) => {
                     const monthlyCost = model.costs[periodKey].monthly_average;
                     const barHeight = monthlyCost * compScale;
                     
                     // Cor baseada no ranking (modelo 0 é o melhor/mais barato -> Verde ou Teal)
                     if (idx === 0) doc.setFillColor(...colors.teal);
                     else if (idx === modelsCount - 1) doc.setFillColor(160, 160, 160); // Pior = Cinza
                     else doc.setFillColor(100, 180, 240); // Intermediario = Azul claro

                     doc.rect(xPos, y + compChartHeight - barHeight, barWidth, barHeight, 'F');

                     // Label Valor
                     doc.setFontSize(9);
                     doc.setTextColor(...colors.charcoal);
                     doc.setFont('helvetica', 'bold');
                     doc.text(formatCurrency(monthlyCost), xPos + (barWidth/2), y + compChartHeight - barHeight - 5, { align: 'center' });

                     // Label Nome (quebra de linha se necessario)
                     doc.setFontSize(8);
                     doc.setFont('helvetica', 'normal');
                     const nameLines = doc.splitTextToSize(model.name, barWidth + 5);
                     doc.text(nameLines, xPos + (barWidth/2), y + compChartHeight + 10, { align: 'center' });

                     xPos += barWidth + 10;
                 });

            } else {

                // PADRAO
                compMaxVal = Math.max(result.spla.monthly, result.arc.finalMonthly) * 1.2;
                compScale = compChartHeight / compMaxVal;
                
                // Eixos
                doc.setDrawColor(200, 200, 200);
                doc.line(25, y, 25, y + compChartHeight); // Y
                doc.line(25, y + compChartHeight, 25 + chartWidth, y + compChartHeight); // X

                // Títulos Eixos
                doc.setFontSize(9);
                doc.setTextColor(...colors.gray);
                doc.text('Custo Mensal', 10, y + (compChartHeight/2), { angle: 90, align: 'center' });

                // Legenda do Gráfico Comparativo
                const compLegendX = 25 + chartWidth - 80;
                const compLegendY = y + 5;
                
                // Legenda SPLA
                doc.setFillColor(160, 160, 160);
                doc.rect(compLegendX, compLegendY, 4, 4, 'F');
                doc.setFontSize(8);
                doc.setTextColor(...colors.charcoal);
                doc.setFont('helvetica', 'normal');
                doc.text('SPLA (Licenciamento)', compLegendX + 6, compLegendY + 3);
                
                // Legenda Azure ARC
                doc.setFillColor(...colors.teal);
                doc.rect(compLegendX, compLegendY + 8, 4, 4, 'F');
                doc.text('Azure ARC (PAYG)', compLegendX + 6, compLegendY + 11);

                // Barras
                const compBarWidth = 50;
                
                // SPLA Bar
                const splaHeight = result.spla.monthly * compScale;
                doc.setFillColor(160, 160, 160); // Cinza para SPLA (Legado)
                doc.rect(60, y + compChartHeight - splaHeight, compBarWidth, splaHeight, 'F');
                
                doc.setFontSize(10);
                doc.setTextColor(...colors.charcoal);
                doc.text('SPLA (Atual)', 60 + (compBarWidth/2), y + compChartHeight + 15, { align: 'center' });
                doc.setFont('helvetica', 'bold');
                doc.text(formatCurrency(result.spla.monthly), 60 + (compBarWidth/2), y + compChartHeight - splaHeight - 5, { align: 'center' });

                // ARC Bar
                const arcHeight = result.arc.finalMonthly * compScale;
                doc.setFillColor(...colors.teal); // Teal para ARC (Novo)
                doc.rect(140, y + compChartHeight - arcHeight, compBarWidth, arcHeight, 'F');
                
                doc.setFont('helvetica', 'normal');
                doc.text('Azure ARC', 140 + (compBarWidth/2), y + compChartHeight + 15, { align: 'center' });
                doc.setFont('helvetica', 'bold');
                doc.text(formatCurrency(result.arc.finalMonthly), 140 + (compBarWidth/2), y + compChartHeight - arcHeight - 5, { align: 'center' });
            }

            // Conclusão Financeira
            y += compChartHeight + 40;
            doc.setFontSize(11);
            doc.setTextColor(...colors.teal);
            doc.setFont('helvetica', 'bold');
            doc.text('Conclusão Financeira', 15, y);
            
            y += 8;
            doc.setFontSize(10);
            doc.setTextColor(...colors.charcoal);
            doc.setFont('helvetica', 'normal');

            if (window.comparisonData && window.comparisonData.length > 0) {
                 // Conclusão Dinâmica
                 const bestModel = sortedModels[0];
                 const worstModel = sortedModels[sortedModels.length - 1];
                 const annualSavings = (worstModel.costs[12].total - bestModel.costs[12].total);
                 const economyPercent = ((annualSavings / worstModel.costs[12].total) * 100).toFixed(1);

                 doc.text(`Com base nos modelos selecionados, a opção mais econômica é o ${bestModel.name}. A escolha deste modelo representa uma redução de custos de aproximadamente ${economyPercent}% em relação à opção de maior custo (${worstModel.name}).`, 15, y, { maxWidth: 180 });
                 
                 y += 20;
                 doc.text(`Em 3 anos, a economia estimada ao optar pelo modelo mais eficiente é de ${formatCurrency(annualSavings * 3)}.`, 15, y);
            } else {
                 // Conclusão Padrão (ARC vs SPLA)
                 const percentage = result.savings.percentage.toFixed(1);
                 doc.text(`A migração para o modelo de SQL Server habilitado pelo Azure Arc, no formato Pay-As-You-Go, representa uma redução significativa de custos, estimada em aproximadamente ${percentage}%, quando comparada aos modelos tradicionais de licenciamento.`, 15, y, { maxWidth: 180 });
                 
                 y += 25;
                 doc.text(`Em 3 anos, a economia total estimada é de ${formatCurrency(result.savings.annual * 3)}.`, 15, y);
            }

            drawFooter(4);
            
            // ================= PÁGINA 5: COMPARAÇÃO DE MODELOS (SE DISPONÍVEL) =================
            if (window.comparisonData && window.comparisonData.length > 0) {
                doc.addPage();
                drawHeader();
                
                let yComp = 40;
                doc.setFontSize(18);
                doc.setTextColor(...colors.teal);
                doc.setFont('helvetica', 'bold');
                doc.text('Comparação de Modelos de Licenciamento', 15, yComp);
                
                yComp += 10;
                doc.setFontSize(9);
                doc.setTextColor(...colors.gray);
                doc.setFont('helvetica', 'normal');
                doc.text(`Análise comparativa de ${window.comparisonData.length} modelos para ${estimate.vCores} vCores ao longo de ${estimate.months || 1} mês(es).`, 15, yComp);
                
                yComp += 12;
                
                // Ordenar por ranking
                const sortedData = [...window.comparisonData].sort((a, b) => {
                    const periodKey = (estimate.months <= 12) ? 12 : ((estimate.months <= 36) ? 36 : 60);
                    return a.ranking[periodKey] - b.ranking[periodKey];
                });
                
                // Tabela de comparação
                const tableHeaders = ['#', 'Modelo', 'Tipo', 'Total', 'Média/Mês'];
                const colWidths = [12, 55, 35, 40, 35];
                const rowHeight = 8;
                
                // Cabeçalho da tabela
                doc.setFillColor(...colors.teal);
                doc.rect(15, yComp, colWidths.reduce((a,b)=>a+b, 0), rowHeight, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(8);
                doc.setFont('helvetica', 'bold');
                
                let xPos = 15;
                tableHeaders.forEach((header, i) => {
                    doc.text(header, xPos + 2, yComp + 6);
                    xPos += colWidths[i];
                });
                
                yComp += rowHeight;
                
                // Linhas da tabela
                doc.setFont('helvetica', 'normal');
                const periodKey = (estimate.months <= 12) ? 12 : ((estimate.months <= 36) ? 36 : 60);
                
                sortedData.forEach((model, index) => {
                    if (yComp > pageHeight - 40) {
                        doc.addPage();
                        drawHeader();
                        yComp = 40;
                    }
                    
                    const cost = model.costs[periodKey];
                    const rank = model.ranking[periodKey];
                    
                    // Cor de fundo alternada
                    if (index % 2 === 0) {
                        doc.setFillColor(250, 250, 250);
                        doc.rect(15, yComp, colWidths.reduce((a,b)=>a+b, 0), rowHeight, 'F');
                    }
                    
                    // Destaque para top 3
                    if (rank <= 3) {
                        const color = rank === 1 ? [16, 185, 129] : (rank === 2 ? [59, 130, 246] : [249, 115, 22]);
                        doc.setTextColor(...color);
                        doc.setFont('helvetica', 'bold');
                    } else {
                        doc.setTextColor(...colors.charcoal);
                        doc.setFont('helvetica', 'normal');
                    }
                    
                    xPos = 15;
                    doc.text(`#${rank}`, xPos + 2, yComp + 6);
                    xPos += colWidths[0];
                    
                    doc.text(model.name.substring(0, 25), xPos + 2, yComp + 6);
                    xPos += colWidths[1];
                    
                    const typeText = model.billing_type === 'monthly' ? 'Mensal' : 
                                    (model.billing_type === 'perpetual' ? 'Perpétuo' : 'Contrato');
                    doc.text(typeText, xPos + 2, yComp + 6);
                    xPos += colWidths[2];
                    
                    doc.text(formatCurrency(cost.total), xPos + 2, yComp + 6);
                    xPos += colWidths[3];
                    
                    doc.text(formatCurrency(cost.monthly_average), xPos + 2, yComp + 6);
                    
                    yComp += rowHeight;
                });
                
                // Observação
                yComp += 10;
                doc.setFontSize(8);
                doc.setTextColor(...colors.gray);
                doc.setFont('helvetica', 'italic');
                doc.text('Esta comparação considera os parâmetros configurados e pode variar conforme condições contratuais específicas.', 15, yComp, { maxWidth: 180 });
                
                drawFooter(5);
            }

            // Salvar
            doc.save('Relatorio_Tecnico_ARC_TD_SYNNEX.pdf');
            } catch (error) {
                console.error('Erro ao gerar PDF:', error);
                alert('Ocorreu um erro ao gerar o PDF. Verifique o console para mais detalhes.');
            }
        }
    });
    
    // ============================================================
    // FUNCIONALIDADES DE COMPARAÇÃO DE MODELOS DE LICENCIAMENTO
    // ============================================================
    
    const enableComparisonCheckbox = document.getElementById('enableComparison');
    const comparisonOptions = document.getElementById('comparisonOptions');
    const selectAllBtn = document.getElementById('selectAll');
    const selectNoneBtn = document.getElementById('selectNone');
    const compareModelsBtn = document.getElementById('compareModels');
    
    // Toggle para mostrar/ocultar opções de comparação
    if (enableComparisonCheckbox) {
        enableComparisonCheckbox.addEventListener('change', function() {
            if (this.checked) {
                comparisonOptions.style.display = 'block';
                comparisonOptions.classList.add('animate-fadeIn');
            } else {
                comparisonOptions.style.display = 'none';
            }
        });
    }
    
    // Botão Selecionar Todos
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="models[]"]');
            checkboxes.forEach(cb => cb.checked = true);
        });
    }
    
    // Botão Limpar Seleção
    if (selectNoneBtn) {
        selectNoneBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="models[]"]');
            checkboxes.forEach(cb => cb.checked = false);
        });
    }
    
    // Botão Comparar Modelos
    if (compareModelsBtn) {
        compareModelsBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('input[name="models[]"]:checked');
            
            if (checkedBoxes.length < 2) {
                alert('⚠️ Por favor, selecione pelo menos 2 modelos para comparar.');
                return;
            }
            
            // Marcar o checkbox de comparação como ativo
            enableComparisonCheckbox.checked = true;
            
            // Submeter o formulário
            const form = document.getElementById('calculator-form');
            if (form) {
                form.submit();
            }
        });
    }
});

// ============================================================
// FUNÇÕES AUXILIARES PARA EXPORTAÇÃO
// ============================================================

/**
 * Exporta comparação para Excel (CSV)
 */
function exportComparisonToExcel() {
    const table = document.querySelector('.comparison-table');
    if (!table) {
        alert('Nenhuma comparação disponível para exportar.');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            // Remove formatação e mantém apenas texto
            let text = col.textContent.trim();
            // Escapa aspas duplas
            text = text.replace(/"/g, '""');
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'Comparacao_Licenciamento_' + new Date().toISOString().slice(0,10) + '.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Compartilha comparação (copia dados para clipboard)
 */
function shareComparison() {
    const summaryCards = document.querySelectorAll('.summary-card');
    let shareText = '📊 Comparativo de Modelos de Licenciamento SQL Server\n\n';
    
    summaryCards.forEach((card, index) => {
        const period = card.querySelector('h4').textContent;
        const model = card.querySelector('.best-option strong').textContent;
        const price = card.querySelector('.best-option .price').textContent;
        
        shareText += `${period}:\n`;
        shareText += `Melhor opção: ${model}\n`;
        shareText += `Custo: ${price}\n\n`;
    });
    
    shareText += '---\nGerado pela Calculadora ARC - TD SYNNEX';
    
    // Copiar para clipboard
    navigator.clipboard.writeText(shareText).then(() => {
        // Mostrar feedback visual
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Copiado!
        `;
        
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Não foi possível copiar. Por favor, selecione o texto manualmente.');
    });
}


// ============================================================
// GERAÇÃO DE PDF — SQL LICENSING ADVISOR (5 PÁGINAS)
// ============================================================

/**
 * Gera proposta comercial em PDF com branding TD SYNNEX para o SQL Licensing Advisor.
 * @param {Object} data - Resultado do LicensingAdvisor::compareAll()
 */
function generateAdvisorPDF(data) {
    let jsPDF;
    if (window.jspdf && window.jspdf.jsPDF) {
        jsPDF = window.jspdf.jsPDF;
    } else if (window.jsPDF) {
        jsPDF = window.jsPDF;
    } else {
        alert('Erro: Biblioteca jsPDF não carregada. Verifique sua conexão com a internet.');
        return;
    }

    try {

    const doc = new jsPDF();
    const pw = doc.internal.pageSize.width;   // 210
    const ph = doc.internal.pageSize.height;  // 297
    const mx = 20; // margin x

    // ── TD SYNNEX Brand Colors ──
    const C = {
        teal:      [0, 87, 88],
        tealDark:  [0, 48, 49],
        tealLight: [0, 128, 130],
        charcoal:  [38, 38, 38],
        gray:      [115, 115, 115],
        grayLight: [160, 165, 170],
        bg:        [245, 247, 250],
        white:     [255, 255, 255],
        blue:      [0, 120, 212],
        green:     [16, 185, 129],
        greenDark: [5, 150, 105],
        red:       [220, 38, 38],
        accent:    [0, 87, 88],      // same as teal — primary accent
        rowEven:   [248, 250, 252],
        border:    [226, 232, 240],
    };

    const params = data.params || {};
    const cs = params.currency === 'BRL' ? 'R$' : '$';
    const today = new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const todayFull = new Date().toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' });

    // ── Helpers ──
    const s = (v) => (v === null || v === undefined) ? '-' : String(v);
    const fmt = (v) => {
        const n = parseFloat(v) || 0;
        return cs + ' ' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    };
    const fmtPct = (v) => (parseFloat(v) || 0).toFixed(1).replace('.', ',') + '%';

    // Model ordering
    const otherKeys = ['spla', 'csp1y', 'csp3y', 'perpetual', 'ovs'];
    const otherModels = otherKeys
        .map(k => ({ key: k, ...data[k] }))
        .sort((a, b) => a.monthly - b.monthly);
    const allModels = [{ key: 'arc', ...data.arc }].concat(otherModels);

    const savKeyMap = { spla:'vsSpla', csp1y:'vsCsp1y', csp3y:'vsCsp3y', perpetual:'vsPerpetual', ovs:'vsOvs' };

    // ── Reusable drawing primitives ──

    // Brand bar at top of every page : thin teal line
    const drawBrandBar = () => {
        doc.setFillColor(...C.teal);
        doc.rect(0, 0, pw, 4, 'F');
    };

    // Page header (pages 2-4)
    const drawPageHeader = () => {
        drawBrandBar();
        // Logo or text
        const _logo = window._advisorLogoDataUrl || null;
        if (_logo) {
            try { doc.addImage(_logo, 'PNG', mx, 10, 36, 7.3); } catch(e) {}
        } else {
            doc.setFontSize(10); doc.setTextColor(...C.tealDark);
            doc.setFont('helvetica', 'bold'); doc.text('TD SYNNEX', mx, 16);
        }
        // Right side: date + page title
        doc.setFontSize(7.5); doc.setTextColor(...C.grayLight);
        doc.setFont('helvetica', 'normal');
        doc.text(s(today), pw - mx, 16, { align: 'right' });
        // Separator
        doc.setDrawColor(...C.border); doc.setLineWidth(0.3);
        doc.line(mx, 21, pw - mx, 21);
    };

    // Page footer
    const drawPageFooter = (num, total) => {
        const fy = ph - 14;
        doc.setDrawColor(...C.border); doc.setLineWidth(0.3);
        doc.line(mx, fy, pw - mx, fy);
        doc.setFontSize(6.5); doc.setTextColor(...C.grayLight);
        doc.setFont('helvetica', 'normal');
        doc.text('TD SYNNEX  |  Documento Confidencial  |  Valores estimados sujeitos a alteração', mx, fy + 5);
        doc.text(num + ' / ' + total, pw - mx, fy + 5, { align: 'right' });
    };

    // Section title with teal accent line
    const drawSection = (title, yPos) => {
        doc.setFontSize(18); doc.setTextColor(...C.charcoal);
        doc.setFont('helvetica', 'bold'); doc.text(title, mx, yPos);
        doc.setFillColor(...C.teal); doc.rect(mx, yPos + 3, 30, 1.8, 'F');
        return yPos + 14;
    };

    // ════════════════════════════════════════════════════════
    //  PAGE 1 — CAPA
    // ════════════════════════════════════════════════════════
    // Full white background
    doc.setFillColor(...C.white); doc.rect(0, 0, pw, ph, 'F');
    drawBrandBar();

    // Logo
    const _logoUrl = window._advisorLogoDataUrl || null;
    if (_logoUrl) {
        try { doc.addImage(_logoUrl, 'PNG', mx, 18, 50, 10.2); } catch(e) {}
    } else {
        doc.setFontSize(16); doc.setTextColor(...C.tealDark);
        doc.setFont('helvetica', 'bold'); doc.text('TD SYNNEX', mx, 26);
    }

    // Teal accent block (side decoration)
    doc.setFillColor(...C.teal);
    doc.rect(0, 80, 6, 90, 'F');

    // Title area
    let cy = 100;
    doc.setFontSize(11); doc.setTextColor(...C.tealLight);
    doc.setFont('helvetica', 'bold');
    doc.text('PROPOSTA COMERCIAL', mx + 8, cy);

    cy += 14;
    doc.setFontSize(32); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'bold');
    doc.text('Licenciamento', mx + 8, cy);
    cy += 13;
    doc.text('SQL Server 2022', mx + 8, cy);

    cy += 10;
    doc.setFontSize(13); doc.setTextColor(...C.gray);
    doc.setFont('helvetica', 'normal');
    doc.text('Análise Comparativa de Modelos de Custo', mx + 8, cy);

    // Client name highlight
    cy += 40;
    doc.setFillColor(...C.bg);
    doc.roundedRect(mx + 8, cy - 8, pw - mx * 2 - 8, 28, 3, 3, 'F');
    doc.setFillColor(...C.teal); doc.rect(mx + 8, cy - 8, 3, 28, 'F');

    doc.setFontSize(8); doc.setTextColor(...C.tealLight);
    doc.setFont('helvetica', 'bold');
    doc.text('PREPARADO PARA', mx + 18, cy);
    doc.setFontSize(18); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'bold');
    const clientName = s(params.clientName || 'Cliente');
    doc.text(clientName.substring(0, 45), mx + 18, cy + 12);

    // Bottom metadata bar
    const metaY = ph - 55;
    doc.setDrawColor(...C.border); doc.setLineWidth(0.3);
    doc.line(mx, metaY, pw - mx, metaY);

    const metaCols = [
        { label: 'DATA',      value: s(todayFull) },
        { label: 'VENDEDOR',  value: s(params.vendorName || '-') },
    ];
    const colW = (pw - mx * 2) / metaCols.length;
    metaCols.forEach((col, i) => {
        const cx = mx + i * colW;
        doc.setFontSize(7); doc.setTextColor(...C.tealLight);
        doc.setFont('helvetica', 'bold'); doc.text(col.label, cx, metaY + 8);
        doc.setFontSize(10); doc.setTextColor(...C.charcoal);
        doc.setFont('helvetica', 'normal');
        let val = col.value;
        if (doc.getTextWidth(val) > colW - 5) val = val.substring(0, 20) + '…';
        doc.text(val, cx, metaY + 16);
    });

    // Bottom note
    doc.setFontSize(7); doc.setTextColor(...C.grayLight);
    doc.setFont('helvetica', 'italic');
    doc.text('Documento Confidencial — TD SYNNEX Brasil', mx, ph - 18);

    // ════════════════════════════════════════════════════════
    //  PAGE 2 — PERFIL & RESUMO EXECUTIVO
    // ════════════════════════════════════════════════════════
    doc.addPage();
    drawPageHeader();

    let y = 30;
    y = drawSection('Resumo Executivo', y);

    // Params card
    doc.setFillColor(...C.bg); doc.setDrawColor(...C.border);
    doc.roundedRect(mx, y, pw - mx * 2, 42, 2, 2, 'FD');
    doc.setFillColor(...C.teal); doc.rect(mx, y, 3, 42, 'F');

    const paramPairs = [
        ['Cliente',    s(params.clientName || '-')],
        ['Vendedor',   s(params.vendorName || '-')],
        ['vCores',     String(params.vCores || 4)],
        ['Edição',     s(params.edition || 'Standard')],
        ['Horas/Mês',  String(params.hoursPerMonth || 730)],
        ['Moeda',      s(params.currency || 'USD')],
    ];
    paramPairs.forEach((p, i) => {
        const col = i < 3 ? 0 : 1;
        const row = i % 3;
        const tx = mx + 12 + col * 82;
        const ty = y + 11 + row * 12;
        doc.setFontSize(8.5); doc.setTextColor(...C.gray);
        doc.setFont('helvetica', 'normal'); doc.text(s(p[0]), tx, ty);
        doc.setTextColor(...C.charcoal); doc.setFont('helvetica', 'bold');
        doc.text(s(p[1]), tx + 38, ty);
    });

    y += 52;

    // Billing dynamics section
    doc.setFontSize(12); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'bold'); doc.text('Dinâmica de Faturamento', mx, y);
    y += 8;

    const billingRows = [
        ['Azure ARC',     'Pay-As-You-Go mensal por vCore/hora consumido'],
        ['SPLA',          'Faturamento pós-pago mensal recorrente (packs de 2 cores)'],
        ['CSP 1/3 Anos',  'Pagamento antecipado (upfront) pelo período contratado'],
        ['Perpétuo + SA', 'Aquisição definitiva (CapEx) + renovação anual de SA'],
        ['OVS',           'Assinatura anualizada (OpEx) com SA incluso'],
    ];
    billingRows.forEach((row, i) => {
        const ry = y + i * 9;
        if (i % 2 === 0) { doc.setFillColor(...C.bg); doc.rect(mx, ry - 3, pw - mx * 2, 9, 'F'); }
        doc.setFontSize(8); doc.setFont('helvetica', 'bold');
        doc.setTextColor(...(i === 0 ? C.teal : C.charcoal));
        doc.text(s(row[0]), mx + 4, ry + 3);
        doc.setFont('helvetica', 'normal'); doc.setTextColor(...C.gray);
        doc.text(s(row[1]), mx + 40, ry + 3);
    });

    drawPageFooter('02', '04');

    // ════════════════════════════════════════════════════════
    //  PAGE 3 — TABELA COMPARATIVA
    // ════════════════════════════════════════════════════════
    doc.addPage();
    drawPageHeader();

    y = 30;
    y = drawSection('Comparativo de Custos', y);

    // Table — columns redistributed for pw=210, mx=20 → usable 170mm
    const colDefs = [
        { label: 'MODELO',         x: mx,   w: 34, align: 'left'   },
        { label: 'PACKS',          x: 55,   w: 12, align: 'center' },
        { label: 'MENSAL',         x: 90,   w: 24, align: 'right'  },
        { label: 'ANUAL',          x: 116,  w: 24, align: 'right'  },
        { label: 'TOTAL 3 ANOS',   x: 146,  w: 28, align: 'right'  },
        { label: 'ECONOMIA',       x: 174,  w: 16, align: 'center' },
    ];

    // Header row
    const thH = 10;
    doc.setFillColor(...C.teal);
    doc.rect(mx, y, pw - mx * 2, thH, 'F');
    doc.setFontSize(7); doc.setTextColor(...C.white);
    doc.setFont('helvetica', 'bold');
    colDefs.forEach(c => {
        const tx = c.align === 'right' ? c.x : (c.align === 'center' ? c.x + c.w / 2 : c.x + 3);
        doc.text(c.label, tx, y + 6.5, { align: c.align === 'left' ? undefined : c.align });
    });
    y += thH;

    // Data rows
    const rowH = 12;
    allModels.forEach((m, i) => {
        const isArc = m.key === 'arc';

        if (isArc) {
            doc.setFillColor(...C.tealDark);
            doc.rect(mx, y, pw - mx * 2, rowH, 'F');
            doc.setTextColor(...C.white);
        } else {
            doc.setFillColor(...(i % 2 === 0 ? C.rowEven : C.white));
            doc.rect(mx, y, pw - mx * 2, rowH, 'F');
            doc.setTextColor(...C.charcoal);
        }

        doc.setFontSize(7.5);
        doc.setFont('helvetica', isArc ? 'bold' : 'normal');

        // Name
        let lbl = s(m.label || m.key || '');
        // ARC row — no extra label
        doc.text(lbl.substring(0, 35), mx + 3, y + 7.5);

        // Packs
        doc.text(isArc ? '—' : String(m.packs || '-'), colDefs[1].x + colDefs[1].w / 2, y + 7.5, { align: 'center' });

        // Monthly
        doc.setFont('helvetica', 'bold');
        doc.text(fmt(m.monthly), colDefs[2].x, y + 7.5, { align: 'right' });

        // Annual
        doc.setFont('helvetica', 'normal');
        doc.text(fmt(m.annual), colDefs[3].x, y + 7.5, { align: 'right' });

        // Total 3y
        doc.text(fmt(m.total3y), colDefs[4].x, y + 7.5, { align: 'right' });

        // Savings (VS ARC) — centered in column
        const vsX = colDefs[5].x + colDefs[5].w / 2;
        if (isArc) {
            doc.text('—', vsX, y + 7.5, { align: 'center' });
        } else {
            const sav = data.savings[savKeyMap[m.key]] || { pct: 0 };
            if (sav.pct > 0) doc.setTextColor(...(isArc ? C.white : C.green));
            else if (sav.pct < 0) doc.setTextColor(...C.red);
            doc.setFont('helvetica', 'bold');
            doc.text((sav.pct > 0 ? '+' : '') + fmtPct(sav.pct), vsX, y + 7.5, { align: 'center' });
        }

        y += rowH;
    });

    // Bottom border on table
    doc.setDrawColor(...C.border); doc.setLineWidth(0.5);
    doc.line(mx, y, pw - mx, y);

    // ── Bar Chart ──
    y += 14;
    doc.setFontSize(12); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'bold');
    doc.text('Custo Mensal por Modelo de Licenciamento', mx, y);
    y += 12;

    const chartW = pw - mx * 2 - 30; // leave space for Y-axis labels
    const chartX = mx + 30;           // offset for Y-axis labels
    const chartH = 60;
    const maxVal = Math.max(...allModels.map(m => m.monthly)) * 1.18;
    const barCount = allModels.length;
    const barW = Math.min(20, (chartW - 20) / barCount - 6);
    const gap = (chartW - barW * barCount) / (barCount + 1);
    const gridSteps = 5;

    // Y-axis grid lines + value labels
    for (let g = 0; g <= gridSteps; g++) {
        const gy = y + chartH - (chartH * g / gridSteps);
        const gv = (maxVal * g / gridSteps);
        // Grid line
        doc.setDrawColor(235, 238, 242); doc.setLineWidth(0.2);
        doc.line(chartX, gy, chartX + chartW, gy);
        // Y-axis label
        doc.setFontSize(6); doc.setTextColor(...C.grayLight);
        doc.setFont('helvetica', 'normal');
        const yLabel = cs + ' ' + Math.round(gv).toLocaleString('pt-BR');
        doc.text(yLabel, chartX - 3, gy + 1.5, { align: 'right' });
    }

    // Y-axis line
    doc.setDrawColor(...C.border); doc.setLineWidth(0.3);
    doc.line(chartX, y, chartX, y + chartH);
    // X-axis line
    doc.line(chartX, y + chartH, chartX + chartW, y + chartH);

    // Bars
    allModels.forEach((m, i) => {
        const bh = (m.monthly / maxVal) * (chartH - 8);
        const bx = chartX + gap + i * (barW + gap);
        const by = y + chartH - bh;

        if (m.key === 'arc') {
            doc.setFillColor(...C.teal);
        } else {
            doc.setFillColor(200, 210, 220);
        }

        // Rounded top bar effect
        doc.roundedRect(bx, by, barW, bh, 2, 2, 'F');

        // Value on top of bar
        doc.setFontSize(6.5); doc.setTextColor(...C.charcoal);
        doc.setFont('helvetica', 'bold');
        doc.text(fmt(m.monthly), bx + barW / 2, by - 3, { align: 'center' });

        // Label below axis
        doc.setFontSize(5.5); doc.setTextColor(...C.gray);
        doc.setFont('helvetica', 'normal');
        const nameLines = doc.splitTextToSize(s(m.label || m.key || ''), barW + 10);
        doc.text(nameLines, bx + barW / 2, y + chartH + 4, { align: 'center' });
    });

    // Legend
    const legendY = y + chartH + 18;
    // ARC legend
    doc.setFillColor(...C.teal);
    doc.roundedRect(chartX, legendY, 8, 4, 1, 1, 'F');
    doc.setFontSize(7); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'bold');
    doc.text('Azure ARC (Recomendado)', chartX + 11, legendY + 3.2);

    // Others legend
    const leg2X = chartX + 75;
    doc.setFillColor(200, 210, 220);
    doc.roundedRect(leg2X, legendY, 8, 4, 1, 1, 'F');
    doc.setFontSize(7); doc.setTextColor(...C.charcoal);
    doc.setFont('helvetica', 'normal');
    doc.text('Outros Modelos', leg2X + 11, legendY + 3.2);

    drawPageFooter('03', '04');

    // ════════════════════════════════════════════════════════
    //  PAGE 4 — POR QUE AZURE ARC + CONCLUSÃO
    // ════════════════════════════════════════════════════════
    doc.addPage();
    drawPageHeader();

    y = 30;
    y = drawSection('Por que Azure ARC?', y);

    // Benefits
    const benefits = [
        { title: 'Pay-As-You-Go',         desc: 'Pague apenas pelo consumo real. Sem desperdício de licenças ociosas ou over-provisioning.' },
        { title: 'Flexibilidade Total',    desc: 'Escale para cima ou para baixo a qualquer momento, sem compromisso contratual de longo prazo.' },
        { title: 'Execução On-Premises',   desc: 'Funciona na sua infraestrutura local (VMs, bare metal) com gestão centralizada pelo Azure.' },
        { title: 'Segurança Avançada',     desc: 'Patches de segurança e atualizações automatizadas pela Microsoft para todo o parque SQL.' },
        { title: 'Governança Unificada',   desc: 'Visibilidade completa pelo portal Azure: inventário, compliance e monitoramento em tempo real.' },
    ];

    benefits.forEach((b, i) => {
        const cy2 = y + i * 22;
        // Card
        doc.setFillColor(...C.white); doc.setDrawColor(...C.border);
        doc.roundedRect(mx, cy2, pw - mx * 2, 18, 2, 2, 'FD');
        // Accent
        doc.setFillColor(...C.teal); doc.rect(mx, cy2, 3, 18, 'F');
        // Number badge
        doc.setFillColor(...C.teal);
        doc.circle(mx + 12, cy2 + 9, 4, 'F');
        doc.setFontSize(8); doc.setTextColor(...C.white);
        doc.setFont('helvetica', 'bold'); doc.text(String(i + 1), mx + 12, cy2 + 11, { align: 'center' });

        // Title
        doc.setFontSize(10); doc.setTextColor(...C.tealDark);
        doc.setFont('helvetica', 'bold'); doc.text(s(b.title), mx + 22, cy2 + 7.5);
        // Desc
        doc.setFontSize(8); doc.setTextColor(...C.gray);
        doc.setFont('helvetica', 'normal'); doc.text(s(b.desc), mx + 22, cy2 + 14, { maxWidth: pw - mx * 2 - 30 });
    });

    // ── Disclaimer / Nota Legal ──
    y += benefits.length * 22 + 14;
    doc.setFillColor(245, 245, 247); doc.setDrawColor(...C.border);
    doc.setLineWidth(0.3);
    const disclaimerItems = [
        'Os valores apresentados nesta proposta são estimativas baseadas nos preços públicos vigentes da Microsoft e na taxa de câmbio de referência (R$ 5,39/USD) informada no momento da simulação. Preços sujeitos a alteração sem aviso prévio.',
        'Esta proposta tem validade de 30 (trinta) dias corridos a partir da data de emissão. Após este período, valores e condições devem ser revalidados.',
        'A taxa de câmbio utilizada é meramente referencial. O valor final será calculado com base na cotação vigente na data de faturamento.',
        'A TD SYNNEX atua como distribuidora autorizada Microsoft. A contratação está sujeita à análise de crédito e aos termos e condições comerciais vigentes.'
    ];
    const discLineH = 4;
    // Pre-calculate total height
    let discTotalH = 0;
    const discRendered = disclaimerItems.map((item, i) => {
        const wrapped = doc.splitTextToSize((i + 1) + '. ' + item, pw - mx * 2 - 16);
        discTotalH += wrapped.length * discLineH + 3;
        return wrapped;
    });
    const discBoxH = discTotalH + 16;
    doc.roundedRect(mx, y, pw - mx * 2, discBoxH, 2, 2, 'FD');

    doc.setFontSize(8); doc.setTextColor(...C.gray);
    doc.setFont('helvetica', 'bold');
    doc.text('NOTA LEGAL', mx + 8, y + 8);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.5); doc.setTextColor(...C.grayLight);
    let discY = y + 14;
    discRendered.forEach(lines => {
        doc.text(lines, mx + 8, discY);
        discY += lines.length * discLineH + 3;
    });

    drawPageFooter('04', '04');

    // ── Salvar ──
    const slug = (params.clientName || 'Proposta').replace(/[^a-zA-Z0-9]/g, '_').substring(0, 30);
    const filename = 'TD_SYNNEX_SQL_Licensing_' + slug + '.pdf';

    try {
        const blobUrl = doc.output('bloburl');
        const newTab = window.open(blobUrl, '_blank');
        if (!newTab) { doc.save(filename); }
    } catch (e) {
        try { doc.save(filename); } catch (e2) {
            const dataUri = doc.output('datauristring');
            const link = document.createElement('a');
            link.href = dataUri; link.download = filename;
            document.body.appendChild(link); link.click();
            setTimeout(() => document.body.removeChild(link), 100);
        }
    }

    } catch (err) {
        console.error('Erro ao gerar PDF do Advisor:', err);
        alert('Erro ao gerar o PDF: ' + err.message);
    }
}

