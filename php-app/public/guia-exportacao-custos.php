<?php
declare(strict_types=1);

/**
 * Guia de Exportação de Custos Azure – PDF
 * Gera e serve um PDF com passo a passo para extrair custos via Azure Cost Management Exports.
 * Formato: TD SYNNEX branding  |  Baseado em: https://learn.microsoft.com/en-us/azure/cost-management-billing/costs/tutorial-improved-exports
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// ── mPDF init ──────────────────────────────────────────────────
$mpdf = new Mpdf([
    'mode'           => 'utf-8',
    'format'         => 'A4',
    'margin_left'    => 15,
    'margin_right'   => 15,
    'margin_top'     => 18,
    'margin_bottom'  => 22,
    'default_font'   => 'Arial',
]);

$mpdf->SetTitle('Guia de Exportação de Custos Azure - TD SYNNEX');
$mpdf->SetAuthor('TD SYNNEX');
$mpdf->SetCreator('ArcCalculator Tools');

// ── Cores TD SYNNEX ────────────────────────────────────────────
$teal      = '#005758';
$tealDark  = '#003031';
$charcoal  = '#262626';
$gray      = '#737373';
$lightGray = '#f5f5f7';
$blue      = '#0078D4';

// ── CSS ────────────────────────────────────────────────────────
$css = <<<CSS
@page {
    margin: 0;
    padding: 0;
}
body {
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    color: #333;
    margin: 0;
    padding: 0;
}
table { border: 0 !important; border-collapse: collapse; border-spacing: 0; }
table td, table th { border: 0 !important; }

.content { padding: 40px 45px; }

.section-title {
    color: {$teal};
    font-size: 15px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid {$teal};
    padding-bottom: 8px;
    margin-top: 30px;
    margin-bottom: 15px;
}
.step-title {
    color: {$tealDark};
    font-size: 12px;
    font-weight: 700;
    margin-top: 18px;
    margin-bottom: 6px;
}
.step-num {
    display: inline-block;
    background: {$teal};
    color: #fff;
    width: 22px;
    height: 22px;
    border-radius: 11px;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    line-height: 22px;
    margin-right: 6px;
}
p, li {
    font-size: 10px;
    line-height: 1.65;
    color: #444;
}
ul, ol { margin: 4px 0 8px 0; padding-left: 18px; }
li { margin-bottom: 2px; }

.highlight-box {
    background: #e6f4f4;
    border: 2px solid {$teal};
    border-radius: 6px;
    padding: 10px 14px;
    margin: 10px 0;
    font-size: 10.5px;
    font-weight: 600;
    color: {$tealDark};
}
.highlight-box .sub {
    font-weight: 400;
    font-size: 9px;
    color: {$gray};
    display: block;
    margin-top: 3px;
}

.tip-box {
    background: #f0f7ff;
    border-left: 4px solid {$blue};
    border-radius: 4px;
    padding: 8px 12px;
    margin: 8px 0;
    font-size: 9px;
    color: #333;
}
.warn-box {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    border-radius: 4px;
    padding: 8px 12px;
    margin: 8px 0;
    font-size: 9px;
    color: #333;
}
.important-box {
    background: #f0f9f9;
    border-left: 4px solid {$teal};
    border-radius: 4px;
    padding: 8px 12px;
    margin: 8px 0;
    font-size: 9px;
    color: #333;
}

.ref-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    margin: 8px 0;
}
.ref-table th {
    background: {$teal};
    color: #fff;
    font-weight: 600;
    text-transform: uppercase;
    padding: 8px;
    text-align: left;
    font-size: 8px;
    letter-spacing: 0.5px;
}
.ref-table td {
    padding: 7px 8px;
    border-bottom: 1px solid #eee;
    color: #444;
}
.ref-table tr:nth-child(even) { background: #f8fcfd; }
.ref-table tr.rec { background: #e6f4f4; font-weight: 600; }
.ref-table tr.rec td:first-child { color: {$teal}; }

.dest-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    margin: 8px 0;
}
.dest-table td {
    padding: 6px 10px;
    background: {$lightGray};
    border-bottom: 2px solid #fff;
    vertical-align: top;
}
.dest-table .label {
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: {$teal};
    font-weight: 700;
    margin-bottom: 2px;
}

.footer-ref {
    font-size: 8px;
    color: {$gray};
    text-align: center;
    margin-top: 25px;
    border-top: 1px solid #eee;
    padding-top: 10px;
}
CSS;

$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

// ── COVER PAGE ─────────────────────────────────────────────────
$date = date('d/m/Y');
$cover = <<<HTML
<div style="position: absolute; left: 0; top: 0; width: 42px; height: 100%; background-color: {$teal};"></div>

<div style="margin-left: 70px; padding-top: 100px;">
    <div style="font-size: 36px; font-weight: bold; color: {$charcoal}; line-height: 1.15; margin-bottom: 5px;">GUIA DE</div>
    <div style="font-size: 36px; font-weight: bold; color: {$charcoal}; line-height: 1.15; margin-bottom: 25px;">EXPORTAÇÃO</div>
    <div style="font-size: 24px; font-weight: bold; color: {$teal}; margin-bottom: 8px;">Azure Cost Management</div>
    <div style="font-size: 18px; color: {$gray}; margin-bottom: 25px;">Exports</div>
    <div style="width: 170px; height: 3px; background-color: {$teal}; margin-bottom: 35px;"></div>
</div>

<div style="margin-left: 70px; margin-right: 80px;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td width="6" style="background-color: {$teal};"></td>
            <td style="padding: 25px 30px; background: {$lightGray};">
                <div style="font-size: 12px; color: {$charcoal}; line-height: 1.6; margin-bottom: 25px;">
                    Passo a passo para extrair dados de custo e uso<br>
                    do Azure Portal utilizando Cost Management Exports<br>
                    no formato <strong>Cost and usage (Actual, Amortized, FOCUS)</strong>
                </div>
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" style="vertical-align: top; padding-bottom: 20px;">
                            <div style="font-size: 10px; color: {$gray}; margin-bottom: 3px;">Data</div>
                            <div style="font-size: 11px; color: {$charcoal}; font-weight: bold;">{$date}</div>
                        </td>
                        <td width="50%" style="vertical-align: top; padding-bottom: 20px;">
                            <div style="font-size: 10px; color: {$gray}; margin-bottom: 3px;">Elaborado por</div>
                            <div style="font-size: 11px; color: {$charcoal}; font-weight: bold;">TD SYNNEX</div>
                        </td>
                    </tr>
                    <tr>
                        <td width="50%" style="vertical-align: top;">
                            <div style="font-size: 10px; color: {$gray}; margin-bottom: 3px;">Referência</div>
                            <div style="font-size: 11px; color: {$charcoal}; font-weight: bold;">Microsoft Learn</div>
                        </td>
                        <td width="50%" style="vertical-align: top;">
                            <div style="font-size: 10px; color: {$gray}; margin-bottom: 3px;">Formato Recomendado</div>
                            <div style="font-size: 11px; color: {$teal}; font-weight: bold;">FOCUS (FinOps)</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div style="position: absolute; bottom: 40px; left: 70px; right: 80px;">
    <div style="font-size: 8px; color: {$gray}; border-top: 1px solid #ddd; padding-top: 10px;">
        Documento interno TD SYNNEX &mdash; Uso exclusivo do time de vendas e engenharia cloud.<br>
        Baseado em: https://learn.microsoft.com/en-us/azure/cost-management-billing/costs/tutorial-improved-exports
    </div>
</div>
HTML;

$mpdf->WriteHTML($cover);
$mpdf->AddPage();

// ── CONTENT PAGES ──────────────────────────────────────────────
$content = <<<HTML
<div class="content">

    <div class="important-box">
        <strong>Importante:</strong> Sempre selecione o tipo de dados <strong>&ldquo;Cost and usage (Actual, Amortized, FOCUS)&rdquo;</strong> ao criar a exportação.
        Este formato combina custos reais e amortizados no padrão aberto FinOps (FOCUS), reduzindo o tempo de processamento e os custos de armazenamento.
    </div>

    <!-- PRÉ-REQUISITOS -->
    <div class="section-title">Pré-requisitos</div>
    <ul>
        <li>Acesso ao <strong>Azure Portal</strong> (portal.azure.com)</li>
        <li>Permissão de <strong>Owner</strong>, <strong>Contributor</strong> ou <strong>Reader</strong> no escopo desejado (assinatura, billing account, etc.)</li>
        <li>Uma <strong>conta de armazenamento Azure (Storage Account)</strong> configurada para blob ou file storage</li>
        <li>Tipo de contrato: <strong>Enterprise Agreement (EA)</strong>, <strong>Microsoft Customer Agreement (MCA)</strong> ou <strong>Microsoft Partner Agreement (MPA)</strong></li>
        <li>Se a Storage Account possuir firewall: role <strong>Owner</strong> ou permissões customizadas (Microsoft.Authorization/roleAssignments/write)</li>
    </ul>

    <!-- PASSO 1 -->
    <div class="section-title">Passo a Passo</div>

    <div class="step-title"><span class="step-num">1</span> Acessar o Cost Management no Azure Portal</div>
    <ol>
        <li>Acesse <strong>https://portal.azure.com</strong> e faça login com sua conta corporativa.</li>
        <li>Na barra de pesquisa superior, digite <strong>&ldquo;Cost Management&rdquo;</strong> e selecione o serviço.</li>
        <li>Selecione o <strong>escopo de cobrança</strong> (billing scope) desejado &mdash; pode ser uma assinatura, grupo de recursos, billing account ou management group.</li>
    </ol>
    <div class="tip-box">
        <strong>Dica:</strong> Para visualizar custos consolidados de múltiplas assinaturas, selecione o escopo no nível de <strong>Management Group</strong> ou <strong>Billing Account</strong>.
    </div>

    <!-- PASSO 2 -->
    <div class="step-title"><span class="step-num">2</span> Navegar até a seção Exports</div>
    <ol>
        <li>No menu lateral esquerdo do Cost Management, localize e clique em <strong>&ldquo;Exports&rdquo;</strong>.</li>
        <li>A página de exportações será exibida com a lista de exports existentes (se houver).</li>
    </ol>

    <!-- PASSO 3 -->
    <div class="step-title"><span class="step-num">3</span> Criar uma Nova Exportação</div>
    <ol>
        <li>No topo da página de Exports, clique no botão <strong>&ldquo;+ Create&rdquo;</strong>.</li>
        <li>Na aba <strong>&ldquo;Basics&rdquo;</strong>, você verá uma lista de templates pré-configurados.</li>
    </ol>

    <div class="highlight-box">
        &#10003; Selecione: Cost and usage (Actual, Amortized, FOCUS)
        <span class="sub">Este template combina custos reais e amortizados no formato FinOps FOCUS &mdash; a opção recomendada pela Microsoft.</span>
    </div>

    <p>Clique em <strong>&ldquo;Next&rdquo;</strong> para avançar para a aba de configuração dos datasets.</p>

    <div class="tip-box">
        Caso não encontre o template desejado na lista inicial, clique em <strong>&ldquo;Show more&rdquo;</strong> para ver todas as opções disponíveis.
    </div>

    <!-- PASSO 4 -->
    <div class="step-title"><span class="step-num">4</span> Configurar os Datasets</div>
    <p>Na aba <strong>&ldquo;Datasets&rdquo;</strong>, configure as opções da exportação:</p>
    <ol>
        <li>Defina um <strong>prefixo de exportação (Export prefix)</strong> para identificar facilmente os arquivos gerados.</li>
        <li>O template já pre-seleciona os três tipos de dados. Verifique se estão listados:
            <ul>
                <li><strong>Cost and usage details (Actual)</strong> &mdash; custos reais de uso e compras</li>
                <li><strong>Cost and usage details (Amortized)</strong> &mdash; custos amortizados de reservas e savings plans</li>
                <li><strong>Cost and usage details (FOCUS)</strong> &mdash; formato aberto FinOps que combina actual + amortized</li>
            </ul>
        </li>
        <li>Para cada dataset, configure a <strong>frequência (Frequency)</strong>:
            <ul>
                <li><strong>One-time export</strong> &mdash; exportação única para período específico</li>
                <li><strong>Daily export of month-to-date costs</strong> &mdash; exportação diária acumulada do mês</li>
                <li><strong>Monthly export of last month's costs</strong> &mdash; exportação mensal do mês anterior</li>
            </ul>
        </li>
        <li>Se necessário, adicione mais exports clicando em <strong>&ldquo;+ Add export&rdquo;</strong> (máximo de 10).</li>
    </ol>

    <div class="warn-box">
        <strong>Atenção:</strong> O escopo de <strong>Management Group</strong> não é suportado para exportações do tipo <strong>Cost and usage details (FOCUS)</strong>.
    </div>

    <p>Clique em <strong>&ldquo;Next&rdquo;</strong> para configurar o destino.</p>

    <!-- PASSO 5 -->
    <div class="step-title"><span class="step-num">5</span> Definir o Destino da Exportação</div>
    <p>Na aba <strong>&ldquo;Destination&rdquo;</strong>, configure onde os dados exportados serão armazenados:</p>

    <table class="dest-table">
        <tr>
            <td width="50%"><div class="label">Storage Type</div> Azure Blob Storage (padrão)</td>
            <td width="50%"><div class="label">Subscription</div> Selecione a assinatura da Storage Account</td>
        </tr>
        <tr>
            <td><div class="label">Resource Group</div> Selecione ou crie um novo</td>
            <td><div class="label">Storage Account</div> Selecione existente ou crie nova</td>
        </tr>
        <tr>
            <td><div class="label">Container</div> Nome do container de blob</td>
            <td><div class="label">Directory</div> Caminho (ex: exports/cost-data)</td>
        </tr>
    </table>

    <ol>
        <li>Escolha o <strong>formato do arquivo</strong>:
            <ul>
                <li><strong>CSV</strong> &mdash; compatível com Excel e a maioria das ferramentas</li>
                <li><strong>Parquet</strong> &mdash; recomendado para grandes volumes e integração com Power BI / Data Lake</li>
            </ul>
        </li>
        <li>Selecione o tipo de <strong>compressão</strong>:
            <ul>
                <li>CSV: <strong>None</strong> ou <strong>Gzip</strong></li>
                <li>Parquet: <strong>None</strong> ou <strong>Snappy</strong></li>
            </ul>
        </li>
        <li><strong>File partitioning</strong> está habilitado por padrão (não pode ser desativado) &mdash; divide arquivos grandes em partes menores.</li>
        <li><strong>Overwrite data</strong> está habilitado por padrão &mdash; para exports diários, o arquivo do dia anterior é substituído pelo atualizado.</li>
    </ol>

    <div class="tip-box">
        <strong>Recomendação:</strong> Para importar os dados na ferramenta de Análise Financeira do ArcCalculator, utilize o formato <strong>CSV</strong> sem compressão.
    </div>

    <p>Clique em <strong>&ldquo;Next&rdquo;</strong> para revisar a configuração.</p>

    <!-- PASSO 6 -->
    <div class="step-title"><span class="step-num">6</span> Revisar e Criar</div>
    <ol>
        <li>Na aba <strong>&ldquo;Review + create&rdquo;</strong>, revise todas as configurações de datasets e destino.</li>
        <li>Confira especialmente:
            <ul>
                <li>Os tipos de dados incluem <strong>Actual</strong>, <strong>Amortized</strong> e <strong>FOCUS</strong></li>
                <li>A Storage Account e container estão corretos</li>
                <li>A frequência e período estão de acordo com sua necessidade</li>
            </ul>
        </li>
        <li>Clique em <strong>&ldquo;Create&rdquo;</strong> para finalizar.</li>
    </ol>
    <div class="important-box">
        O processo de exportação pode levar <strong>até 24 horas</strong> para que os dados estejam disponíveis na Storage Account.
    </div>

    <!-- PASSO 7 -->
    <div class="step-title"><span class="step-num">7</span> Verificar e Baixar os Dados</div>
    <ol>
        <li>Volte para a página de <strong>Exports</strong> no Cost Management.</li>
        <li>Selecione a exportação criada para visualizar o <strong>histórico de execuções (Run history)</strong>.</li>
        <li>Clique no nome da <strong>Storage Account</strong> e use o link <strong>&ldquo;Open in Explorer&rdquo;</strong> para acessar os arquivos.</li>
        <li>Navegue até o container e diretório configurados. Você encontrará:
            <ul>
                <li>Arquivos CSV/Parquet particionados</li>
                <li>Um arquivo <strong>manifest.json</strong> com os metadados de cada partição</li>
            </ul>
        </li>
        <li>Selecione o(s) arquivo(s) e faça o <strong>download</strong>.</li>
    </ol>
    <div class="tip-box">
        <strong>Estrutura de pastas:</strong> Container / Diretório / NomeExport / [YYYYMMDD-YYYYMMDD] / [RunID] /
    </div>

    <!-- PASSO 8 -->
    <div class="step-title"><span class="step-num">8</span> Executar Export sob Demanda ou para Datas Passadas</div>
    <p>Além dos exports agendados, você pode:</p>
    <ol>
        <li><strong>Run now</strong> &mdash; Executa a exportação imediatamente, independente do agendamento.</li>
        <li><strong>Export selected dates</strong> &mdash; Reexecuta a exportação para um período histórico (até 13 meses). Selecione o export e clique nesta opção.</li>
    </ol>
    <div class="tip-box">
        Utilize o <strong>&ldquo;Export selected dates&rdquo;</strong> quando precisar de dados históricos para análise retroativa. Dados podem ser recuperados mês a mês, até o limite de 13 meses.
    </div>

    <!-- FIREWALL -->
    <div class="section-title">Storage Account com Firewall</div>
    <p>Se a Storage Account possuir firewall habilitado:</p>
    <ol>
        <li>Acesse a página de <strong>Networking</strong> da Storage Account.</li>
        <li>Habilite a opção <strong>&ldquo;Allow trusted Azure services access&rdquo;</strong>.</li>
        <li>O Cost Management criará automaticamente um <strong>managed identity</strong> com a role <strong>StorageBlobDataContributor</strong>.</li>
        <li>Após a criação/atualização do export, a role de Owner não é mais necessária para operações rotineiras.</li>
    </ol>
    <div class="warn-box">
        <strong>Nota:</strong> Firewalls são suportados apenas para Storage Accounts no mesmo tenant. Cross-tenant exports com firewall não são suportados.
    </div>

    <!-- COMPATIBILIDADE -->
    <div class="section-title">Contratos e Escopos Suportados</div>
    <table class="ref-table">
        <thead>
            <tr>
                <th>Tipo de Dados</th>
                <th>Contratos</th>
                <th>Escopos</th>
            </tr>
        </thead>
        <tbody>
            <tr class="rec">
                <td>Cost and usage (FOCUS)</td>
                <td>EA, MCA, MPA</td>
                <td>EA: Enrollment, department, account, subscription, resource group
                    MCA: Billing account, billing profile, invoice section, subscription, resource group
                    MPA: Customer, subscription, resource group
                    (Management group NÃO suportado para FOCUS)</td>
            </tr>
            <tr>
                <td>Cost and usage (Actual / Amortized)</td>
                <td>EA, MCA, MPA, Azure Internal</td>
                <td>EA: Enrollment, department, account, subscription, resource group
                    MCA: Billing account, billing profile, invoice section, subscription, resource group
                    MPA: Customer, subscription, resource group</td>
            </tr>
        </tbody>
    </table>

    <!-- FOOTER REF -->
    <div class="footer-ref">
        Documento interno TD SYNNEX &mdash; Baseado na documentação oficial Microsoft Learn<br>
        <strong>https://learn.microsoft.com/en-us/azure/cost-management-billing/costs/tutorial-improved-exports</strong>
    </div>

</div>
HTML;

$mpdf->WriteHTML($content);

// ── Output ─────────────────────────────────────────────────────
$mpdf->Output('Guia_Exportacao_Custos_Azure_TDSYNNEX.pdf', 'D');
