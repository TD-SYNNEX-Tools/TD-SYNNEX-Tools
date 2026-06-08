<?php
/**
 * Gerador de PDF - Guia de Onboarding CSP TD SYNNEX
 * Documento executivo com design profissional
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// Configuração do mPDF
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 10,
    'margin_footer' => 10,
    'default_font' => 'dejavusans',
]);

// Metadados do PDF
$mpdf->SetTitle('Guia de Onboarding CSP - TD SYNNEX');
$mpdf->SetAuthor('TD SYNNEX Brasil');
$mpdf->SetCreator('TD SYNNEX Cloud Tools');
$mpdf->SetSubject('Microsoft CSP Onboarding Guide');

// CSS para o PDF
$css = '
<style>
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 10pt;
        line-height: 1.6;
        color: #333;
    }
    
    /* ══════════════════════════════════════════════════════════
       COVER PAGE - Executive Minimal Design
       ══════════════════════════════════════════════════════════ */
    .cover-page {
        background: #fff;
        margin: -20px -15px 0 -15px;
        padding: 60px 50px;
        height: 267mm;
        color: #262626;
    }
    
    .cover-brand {
        font-size: 12pt;
        font-weight: bold;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: #005758;
        margin-bottom: 100px;
    }
    
    .cover-line {
        width: 50px;
        height: 2px;
        background: #005758;
        margin-bottom: 25px;
    }
    
    .cover-tag {
        font-size: 9pt;
        font-weight: 500;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #737373;
        margin-bottom: 12px;
    }
    
    .cover-title {
        font-size: 36pt;
        font-weight: bold;
        line-height: 1.15;
        margin: 0 0 18px 0;
        color: #003031;
    }
    
    .cover-subtitle {
        font-size: 14pt;
        font-weight: normal;
        color: #005758;
        margin: 0 0 35px 0;
    }
    
    .cover-description {
        font-size: 10pt;
        line-height: 1.7;
        color: #737373;
        max-width: 400px;
        margin-bottom: 80px;
    }
    
    .cover-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 80px;
    }
    
    .cover-meta-table td {
        padding: 14px 0;
        border-top: 1px solid #005758;
        vertical-align: top;
    }
    
    .cover-meta-label {
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #737373;
        margin-bottom: 4px;
    }
    
    .cover-meta-value {
        font-size: 10pt;
        color: #003031;
        font-weight: 500;
    }
    
    /* Section Title */
    .section-title {
        font-size: 14pt;
        font-weight: bold;
        color: #003031;
        border-bottom: 1px solid #005758;
        padding-bottom: 10px;
        margin: 30px 0 20px 0;
        page-break-after: avoid;
    }
    
    /* Overview Table - Executive Style */
    .overview-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 9pt;
    }
    
    .overview-table tr {
        border-bottom: 1px solid #eee;
    }
    
    .overview-table tr:last-child {
        border-bottom: none;
    }
    
    .overview-label {
        width: 120px;
        padding: 12px 15px;
        background: #f5f5f7;
        color: #003031;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        vertical-align: top;
        border-right: 1px solid #005758;
    }
    
    .overview-value {
        padding: 12px 15px;
        background: #fff;
        color: #262626;
        line-height: 1.5;
        vertical-align: top;
    }
    
    /* Subsection */
    .subsection {
        background: #f5f5f7;
        border-left: 2px solid #005758;
        padding: 12px 15px;
        margin: 15px 0;
    }
    
    .subsection h3 {
        font-size: 11pt;
        font-weight: bold;
        color: #333;
        margin: 0 0 8px 0;
    }
    
    .badge {
        display: inline-block;
        background: #005758;
        color: #fff;
        padding: 2px 8px;
        font-size: 8pt;
        font-weight: bold;
        margin-right: 8px;
    }
    
    /* Step Card - Executive Style */
    .step-card {
        background: #fff;
        border: 1px solid #ddd;
        margin: 0 0 12px 0;
        page-break-inside: avoid;
    }
    
    .step-header {
        background: #003031;
        color: #fff;
        padding: 10px 15px;
        display: table;
        width: 100%;
    }
    
    .step-number {
        display: table-cell;
        width: 35px;
        font-size: 14pt;
        font-weight: bold;
        color: #999;
        vertical-align: middle;
    }
    
    .step-title {
        display: table-cell;
        font-size: 10pt;
        font-weight: bold;
        vertical-align: middle;
    }
    
    .step-body {
        padding: 12px 15px;
        background: #fafafa;
    }
    
    .step-body ol {
        margin: 0;
        padding-left: 18px;
    }
    
    .step-body ol li {
        margin-bottom: 6px;
        color: #444;
        line-height: 1.5;
        font-size: 9pt;
    }
    
    .step-body ol li:last-child {
        margin-bottom: 0;
    }
    
    /* Warning Box */
    .warning-box {
        background: #f5f5f7;
        border-left: 2px solid #005758;
        padding: 10px 12px;
        margin: 12px 0;
        page-break-inside: avoid;
    }
    
    .warning-box h4 {
        color: #003031;
        font-size: 9pt;
        font-weight: bold;
        margin: 0 0 5px 0;
    }
    
    .warning-box p, .warning-box ul {
        color: #737373;
        font-size: 9pt;
        margin: 5px 0;
    }
    
    .warning-box ul {
        padding-left: 16px;
    }
    
    /* Alert Box */
    .alert-box {
        background: #f5f5f5;
        border-left: 2px solid #666;
        padding: 8px 12px;
        margin: 8px 0;
        font-size: 8pt;
        color: #555;
        font-style: italic;
    }
    
    /* Success Box */
    .success-box {
        background: #f5f5f5;
        border-left: 2px solid #666;
        padding: 8px 12px;
        margin: 8px 0;
        font-size: 8pt;
        color: #333;
        font-weight: 500;
    }
    
    /* Callout */
    .callout {
        background: #003031;
        color: #fff;
        padding: 12px 15px;
        margin: 15px 0;
        page-break-inside: avoid;
    }
    
    .callout p {
        margin: 0;
        font-size: 9pt;
    }
    
    /* Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
        font-size: 9pt;
    }
    
    .data-table thead {
        background: #003031;
        color: #fff;
    }
    
    .data-table th {
        padding: 8px 10px;
        text-align: left;
        font-weight: bold;
        font-size: 7pt;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
        vertical-align: top;
    }
    
    .data-table tr:nth-child(even) {
        background: #fafafa;
    }
    
    .error-text {
        color: #900;
        font-weight: 600;
    }
    
    .warn-text {
        color: #960;
        font-weight: 600;
    }
    
    /* Support Grid */
    .support-card {
        background: #f5f5f7;
        border: 1px solid #ddd;
        border-top: 2px solid #005758;
        padding: 10px 12px;
        margin: 8px 0;
        page-break-inside: avoid;
    }
    
    .support-card h5 {
        font-size: 9pt;
        font-weight: bold;
        color: #333;
        margin: 0 0 4px 0;
    }
    
    .support-card .desc {
        font-size: 8pt;
        color: #777;
        margin-bottom: 6px;
    }
    
    .support-card ul {
        margin: 0;
        padding-left: 14px;
        font-size: 8pt;
    }
    
    .support-card ul li {
        margin-bottom: 3px;
        color: #444;
    }
    
    /* Glossary */
    .glossary-item {
        background: #f5f5f7;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-left: 2px solid #005758;
        margin: 6px 0;
    }
    
    .glossary-item dt {
        font-weight: bold;
        color: #003031;
        font-size: 9pt;
        margin-bottom: 2px;
    }
    
    .glossary-item dd {
        color: #737373;
        font-size: 8pt;
        margin: 0;
    }
    
    /* Checklist */
    .checklist-table {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
        font-size: 8pt;
    }
    
    .checklist-table th {
        background: #003031;
        color: #fff;
        padding: 8px;
        text-align: left;
        font-size: 7pt;
        text-transform: uppercase;
    }
    
    .checklist-table td {
        padding: 8px;
        border-bottom: 1px solid #eee;
    }
    
    .checklist-table tr:nth-child(even) {
        background: #fafafa;
    }
    
    .checkbox {
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 1px solid #005758;
        margin-right: 6px;
        vertical-align: middle;
    }
    
    /* Scenario Table - Executive Style */
    .scenario-table {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0;
        font-size: 8pt;
    }
    
    .scenario-table tr {
        border-bottom: 1px solid #ddd;
    }
    
    .scenario-table tr:last-child {
        border-bottom: none;
    }
    
    .scenario-label {
        width: 100px;
        padding: 10px;
        background: #003031;
        color: #fff;
        font-size: 7pt;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        line-height: 1.4;
    }
    
    .scenario-label span {
        font-weight: normal;
        font-size: 6pt;
        opacity: 0.7;
    }
    
    .scenario-steps {
        padding: 10px 12px;
        background: #f5f5f7;
        color: #262626;
        line-height: 1.6;
        vertical-align: top;
    }
    
    /* Page Break */
    .page-break {
        page-break-before: always;
    }
    
    /* Footer */
    .footer-info {
        font-size: 8pt;
        color: #777;
        text-align: center;
        margin-top: 25px;
        padding-top: 12px;
        border-top: 1px solid #ddd;
    }
    
    /* Lists */
    ul, ol {
        margin: 6px 0;
        padding-left: 18px;
    }
    
    li {
        margin-bottom: 3px;
    }
    
    /* Code */
    code {
        background: #f0f0f0;
        padding: 1px 4px;
        font-family: monospace;
        font-size: 8pt;
        color: #333;
    }
    
    .toc-item {
        padding: 10px 12px;
        border-bottom: 1px solid #eee;
        background: #f5f5f7;
        margin-bottom: 3px;
    }
    
    .toc-item a {
        color: #262626;
        text-decoration: none;
        font-weight: 500;
    }
    
    .toc-number {
        display: inline-block;
        color: #005758;
        width: 22px;
        text-align: center;
        font-size: 11pt;
        font-weight: bold;
        margin-right: 10px;
    }
    
    .toc-title {
        font-size: 14pt;
        font-weight: bold;
        color: #003031;
        margin: 0 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #005758;
    }
</style>
';

// Conteúdo do PDF
$html = $css . '
<!-- ═══════════════════════════════════════════════════════════
     CAPA - Clean Text-Only Design
     ═══════════════════════════════════════════════════════════ -->
<div class="cover-page">
    <div class="cover-brand">TD SYNNEX</div>
    
    <div class="cover-line"></div>
    
    <div class="cover-tag">Microsoft Cloud Solution Provider</div>
    
    <h1 class="cover-title">Guia de Onboarding</h1>
    
    <p class="cover-subtitle">Indirect Reseller — Passo a Passo</p>
    
    <p class="cover-description">
        Manual executivo para habilitação de revendas no programa Microsoft CSP. 
        Aprenda a transacionar licenças NCE e Azure Plan através da TD SYNNEX.
    </p>
    
    <table class="cover-meta-table">
        <tr>
            <td width="33%">
                <div class="cover-meta-label">Versão</div>
                <div class="cover-meta-value">1.0</div>
            </td>
            <td width="33%">
                <div class="cover-meta-label">Público-Alvo</div>
                <div class="cover-meta-value">Revendas Parceiras</div>
            </td>
            <td width="34%">
                <div class="cover-meta-label">Departamento</div>
                <div class="cover-meta-value">Cloud Enablement</div>
            </td>
        </tr>
    </table>
</div>

<div class="page-break"></div>

<!-- SUMÁRIO -->
<h2 class="toc-title">Sumário</h2>

<div class="toc-item"><span class="toc-number">1</span> Resumo Executivo</div>
<div class="toc-item"><span class="toc-number">2</span> Visão Geral do Processo</div>
<div class="toc-item"><span class="toc-number">3</span> Passo a Passo Operacional</div>
<div class="toc-item"><span class="toc-number">4</span> Erros Comuns e Troubleshooting</div>
<div class="toc-item"><span class="toc-number">5</span> SLAs e Canais de Suporte</div>
<div class="toc-item"><span class="toc-number">6</span> Glossário</div>
<div class="toc-item"><span class="toc-number">7</span> Checklist de Onboarding</div>

<div class="page-break"></div>

<!-- 1. RESUMO EXECUTIVO -->
<h2 class="section-title">1. Resumo Executivo</h2>

<p>Bem-vindo à parceria Microsoft CSP com a <strong>TD SYNNEX</strong>! Este kit foi desenhado para guiar sua revenda nos primeiros passos como um <strong>Indirect Reseller</strong>, garantindo autonomia operacional para transacionar licenças (NCE) e consumo (Azure Plan).</p>

<p>Aqui você aprenderá como:</p>
<ul>
    <li>Acessar o portal <strong>CloudSolv/ECExpress</strong></li>
    <li>Oficializar o vínculo com a TD SYNNEX no Microsoft Partner Center</li>
    <li>Realizar o cadastro de clientes (com e sem tenant prévio)</li>
    <li>Colocar seu primeiro pedido</li>
    <li>Acionar as trilhas de capacitação comercial e técnica</li>
</ul>

<div class="callout">
    <p><strong>Objetivo:</strong> Acelerar seu <em>time-to-market</em> e garantir receita recorrente sem atritos.</p>
</div>

<!-- 2. VISÃO GERAL -->
<h2 class="section-title">2. Visão Geral do Processo</h2>

<table class="overview-table">
    <tr>
        <td class="overview-label">O QUE É</td>
        <td class="overview-value">Habilitação de revendas para comercializar Microsoft Cloud (M365, Azure, Office 365) via TD SYNNEX.</td>
    </tr>
    <tr>
        <td class="overview-label">QUANDO USAR</td>
        <td class="overview-value">Imediatamente após assinatura do contrato de parceria.</td>
    </tr>
    <tr>
        <td class="overview-label">PRÉ-REQUISITOS</td>
        <td class="overview-value">
            <strong>1.</strong> PartnerID (MPN ID) ativo na Microsoft<br>
            <strong>2.</strong> Cadastro aprovado no ECExpress TD SYNNEX
        </td>
    </tr>
    <tr>
        <td class="overview-label">ENTRADA</td>
        <td class="overview-value">MPN ID da revenda + Dados do cliente (CNPJ, Endereço)</td>
    </tr>
    <tr>
        <td class="overview-label">SAÍDA</td>
        <td class="overview-value">CloudSolv liberado + Licenças ativas + Faturamento automatizado</td>
    </tr>
</table>

<div class="page-break"></div>

<!-- 3. PASSO A PASSO -->
<h2 class="section-title">3. Passo a Passo Operacional</h2>

<div class="step-card">
    <div class="step-header">
        <span class="step-number">01</span>
        <span class="step-title">Aceite de Parceria (Vínculo Microsoft & TD SYNNEX)</span>
    </div>
    <div class="step-body">
        <div class="alert-box">Sem este aceite, a Microsoft não reconhece a TD SYNNEX como seu provedor indireto.</div>
        <ol>
            <li>Acesse o <strong>Microsoft Partner Center</strong> com usuário Global Admin</li>
            <li>Clique no link de Aceite de Parceria (aparecerá como "Westcon Brasil Ltda")</li>
            <li>Marque concordância e clique em <strong>"Autorizar fornecedor indireto"</strong></li>
        </ol>
        <div class="success-box">Critério de Sucesso: TD SYNNEX listada na aba "Indirect Providers"</div>
    </div>
</div>

<div class="step-card">
    <div class="step-header">
        <span class="step-number">02</span>
        <span class="step-title">Acesso ao ECExpress e CloudSolv</span>
    </div>
    <div class="step-body">
        <ol>
            <li>Acesse <strong>ECExpress</strong> com suas credenciais (1º acesso: "Esqueci minha senha" ou sscloud@tdsynnex.com)</li>
            <li>Clique no botão <strong>CLOUDSolv</strong> no canto superior direito</li>
            <li>Navegue em <strong>Portfólio > Microsoft</strong> para explorar produtos</li>
        </ol>
    </div>
</div>

<div class="step-card">
    <div class="step-header">
        <span class="step-number">03</span>
        <span class="step-title">Cadastro do Cliente Final</span>
    </div>
    <div class="step-body">
        <table class="scenario-table">
            <tr>
                <td class="scenario-label">CLIENTE NOVO<br><span>(Sem Tenant)</span></td>
                <td class="scenario-steps">
                    <strong>1.</strong> CloudSolv > Clientes > Criar Cliente<br>
                    <strong>2.</strong> Insira CNPJ e clique "Validar"<br>
                    <strong>3.</strong> Deixe "Microsoft Account" em branco > Gravar
                </td>
            </tr>
            <tr>
                <td class="scenario-label">CLIENTE EXISTENTE<br><span>(Com Tenant)</span></td>
                <td class="scenario-steps">
                    <strong>1.</strong> Partner Center: gere link "Solicitar relacionamento"<br>
                    <strong>2.</strong> CloudSolv: CNPJ + Validar<br>
                    <strong>3.</strong> Preencha ID do Tenant + e-mail Admin > Gravar
                </td>
            </tr>
        </table>
        <div class="success-box">Critério de Sucesso: Status do cliente muda de HOLD para VALID</div>
    </div>
</div>

<div class="step-card">
    <div class="step-header">
        <span class="step-number">04</span>
        <span class="step-title">Colocando o Primeiro Pedido</span>
    </div>
    <div class="step-body">
        <ol>
            <li><strong>Licenças NCE:</strong> Produto + Faturamento (Mensal/Anual) + Quantidade</li>
            <li><strong>Azure Plan:</strong> Standard (8% comissão) ou Premium (5% + serviços gerenciados)</li>
            <li>Checkout: Markup + Nº PO (ou "NA") + Condição de pagamento</li>
            <li>Confirme e ative</li>
        </ol>
    </div>
</div>

<div class="step-card">
    <div class="step-header">
        <span class="step-number">05</span>
        <span class="step-title">Capacitação (Enablement)</span>
    </div>
    <div class="step-body">
        <ol>
            <li><strong>Microsoft Learn:</strong> Trilhas de certificação (Fundamentals, Associate)</li>
            <li><strong>TD SYNNEX:</strong> Microsoft Brazil Sales Academy + Workshops M365</li>
        </ol>
    </div>
</div>

<div class="page-break"></div>

<!-- 4. TROUBLESHOOTING -->
<h2 class="section-title">4. Erros Comuns e Troubleshooting</h2>

<p>Esta seção reúne os principais problemas encontrados durante o onboarding e suas soluções. Mantenha-a como referência rápida para resolver bloqueios operacionais.</p>

<table class="data-table">
    <thead>
        <tr>
            <th width="30%">Sintoma / Erro</th>
            <th width="35%">Causa Provável</th>
            <th width="35%">Como Resolver</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><span class="error-text">Status LOCK</span></td>
            <td>Dados cadastrais incorretos (CEP/Endereço) não batem com o Sintegra ou cadastro desatualizado há > 6 meses.</td>
            <td>Editar os dados no CloudSolv (ícone do lápis) e corrigir. Se persistir, abrir chamado via <strong>Jira</strong>.</td>
        </tr>
        <tr>
            <td><span class="warn-text">Status HOLD</span></td>
            <td>Cliente na fila de validação da mesa de crédito/cadastro da TD SYNNEX.</td>
            <td>Aguardar. O pedido só processa em <code>VALID</code>. Se urgência, acionar e-mail de suporte a vendas.</td>
        </tr>
        <tr>
            <td>Erro ao faturar tenant existente</td>
            <td>O cliente final não deu o "Aceite Delegado" (Relacionamento de Revendedor) no Partner Center.</td>
            <td>Solicitar ao Global Admin do cliente que clique no link gerado no seu Partner Center e aceite o relacionamento.</td>
        </tr>
        <tr>
            <td>Não sei o custo final do Azure</td>
            <td>Falta de visibilidade de precificação no portal do cliente.</td>
            <td>Solicitar a ativação do recurso <strong>Cost Management</strong> via chamado Jira, informando CNPJ da revenda, cliente e ID da assinatura.</td>
        </tr>
    </tbody>
</table>

<div class="warning-box">
    <h4>Atenção: Lacuna de Informação</h4>
    <p>Se a sua revenda ainda não possui o PartnerID (MPN ID), você deve criá-lo primeiro no portal da Microsoft (Partner Network). Sem ele, é impossível aceitar o contrato de provedor indireto.</p>
    <ul>
        <li><strong>Opção 1:</strong> Já tem MPN ID? Siga para o Passo 1.</li>
        <li><strong>Opção 2:</strong> Não tem? Acesse <code>partner.microsoft.com</code> e realize o registro como parceiro antes de iniciar o onboarding na TD SYNNEX.</li>
    </ul>
</div>

<div class="callout">
    <p><strong>Dica:</strong> A maioria dos erros de cadastro são resolvidos em até 24h. Para casos urgentes, sempre mencione o CNPJ do cliente e ID do pedido.</p>
</div>

<!-- 5. SLAs E SUPORTE -->
<h2 class="section-title">5. SLAs e Canais de Suporte</h2>

<p>A TD SYNNEX oferece múltiplos canais de atendimento para garantir que sua operação não pare. Utilize o canal adequado conforme o tipo de demanda:</p>

<div class="support-card">
    <h5>Suporte Técnico Cloud (24x7)</h5>
    <p class="desc">Atendimento em PT, EN e ES para incidentes técnicos no ambiente do cliente.</p>
    <ul>
        <li><strong>E-mail:</strong> wcbrsuporte@tdsynnex.com</li>
        <li><strong>Telefone:</strong> 0800-940-2910 ou (11) 5186-4350</li>
        <li><strong>Evidências necessárias:</strong> ID do Tenant, print do erro, nome do cliente.</li>
    </ul>
</div>

<div class="support-card">
    <h5>Suporte Operacional (CS)</h5>
    <p class="desc">Erros de faturamento, dúvidas de plataforma Stellr/CloudSolv.</p>
    <ul>
        <li><strong>Acesso:</strong> Via abertura de Ticket no <strong>Jira</strong> (Portal exclusivo).</li>
    </ul>
</div>

<div class="support-card">
    <h5>Suporte Comercial / Pré-vendas</h5>
    <p class="desc">Dúvidas sobre M365, Azure Sizing, etc.</p>
    <ul>
        <li><strong>Acesso:</strong> Envie a demanda detalhada para o Gerente de Contas (acionará cloudprevendasbr@...).</li>
        <li><strong>Apoio a Vendas:</strong> salescloudbr@tdsynnex.com / sscloud@tdsynnex.com</li>
    </ul>
</div>

<div class="callout">
    <p><strong>Importante:</strong> Para atendimento mais ágil, sempre tenha em mãos: CNPJ da revenda, ID do cliente e descrição detalhada do problema.</p>
</div>

<div class="page-break"></div>

<!-- 6. GLOSSÁRIO -->
<h2 class="section-title">6. Glossário</h2>

<p>Termos técnicos e siglas utilizados neste documento e no ecossistema Microsoft CSP:</p>

<div class="glossary-item">
    <dt>Indirect Reseller</dt>
    <dd>Você, a revenda que vende Microsoft utilizando um Distribuidor.</dd>
</div>

<div class="glossary-item">
    <dt>Indirect Provider</dt>
    <dd>A TD SYNNEX (Westcon Brasil Ltda), o distribuidor oficial.</dd>
</div>

<div class="glossary-item">
    <dt>CloudSolv / ECExpress</dt>
    <dd>Plataformas de E-commerce e Gestão Cloud da TD SYNNEX.</dd>
</div>

<div class="glossary-item">
    <dt>NCE (New Commerce Experience)</dt>
    <dd>Novo modelo de licenciamento e faturamento da Microsoft (contratos mensais e anuais estritos).</dd>
</div>

<div class="glossary-item">
    <dt>Aceite Delegado</dt>
    <dd>Permissão que o cliente final concede à revenda para administrar seu tenant Microsoft.</dd>
</div>

<div class="glossary-item">
    <dt>Cost Management</dt>
    <dd>Ferramenta do Azure para visibilidade de consumo e custos FOB.</dd>
</div>

<div class="callout">
    <p><strong>Referência:</strong> Para dúvidas sobre termos não listados, consulte o glossário oficial da Microsoft em <code>learn.microsoft.com</code>.</p>
</div>

<!-- 7. CHECKLIST -->
<h2 class="section-title">7. Checklist de Onboarding</h2>

<p>Utilize esta tabela para acompanhar a prontidão da sua revenda:</p>

<table class="checklist-table">
    <thead>
        <tr>
            <th width="5%"></th>
            <th width="30%">Etapa</th>
            <th width="20%">Responsável</th>
            <th width="45%">Critério de Sucesso</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>1. Cadastro ECExpress</strong></td>
            <td>Revenda</td>
            <td>Login realizado com sucesso.</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>2. Aceite Provedor Indireto</strong></td>
            <td>Revenda</td>
            <td>"Westcon Brasil Ltda" listada no Partner Center.</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>3. Cadastro 1º Cliente</strong></td>
            <td>Revenda</td>
            <td>Cliente com status VALID no CloudSolv.</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>4. Aceite Delegado</strong></td>
            <td>Cliente Final</td>
            <td>Cliente clicou no link e aceitou relacionamento.</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>5. Pedido / Provisionamento</strong></td>
            <td>Revenda</td>
            <td>Licença ativa no portal do cliente / Tenant criado.</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span></td>
            <td><strong>6. Treinamento de Vendas</strong></td>
            <td>Revenda</td>
            <td>Inscrição no "Microsoft Brazil Sales Academy".</td>
        </tr>
    </tbody>
</table>

<div class="callout">
    <p><strong>Parabéns!</strong> Ao completar todos os itens acima, sua revenda estará pronta para operar no programa Microsoft CSP via TD SYNNEX.</p>
</div>

<div class="footer-info">
    <p><strong>TD SYNNEX Brasil</strong> — Cloud Division<br>
    Versão 1.0 | ' . date('Y') . '</p>
</div>
';

// Gerar o PDF
$mpdf->WriteHTML($html);

// Output do PDF
$filename = 'Guia_Onboarding_CSP_TDSYNNEX_' . date('Ymd') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
