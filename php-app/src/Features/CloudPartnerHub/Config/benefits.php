<?php
/**
 * Cloud Partner Hub - Definição de Benefícios
 * Convertido de TypeScript para PHP
 */

declare(strict_types=1);

namespace Features\CloudPartnerHub\Config;

class Benefits
{
    /**
     * Lista completa de benefícios disponíveis
     */
    public const BENEFITS = [
        // === FERRAMENTAS ===
        [
            'id' => 'azure-migration-analyzer',
            'name' => 'Azure Migration Analyzer',
            'description' => 'Ferramenta interna para análise automatizada de migrações Azure. Gera relatórios de assessment, sizing e estimativa de custos.',
            'icon' => 'cloud',
            'category' => 'tool',
            'solutionAreas' => ['Azure Infra', 'Data & AI', 'Digital & App Innovation'],
            'requirements' => [
                'minPcsScore' => 40,
                'minStage' => 'Develop'
            ],
            'value' => 'R$ 15.000/ano',
            'isNew' => true
        ],
        [
            'id' => 'security-posture-scanner',
            'name' => 'Security Posture Scanner',
            'description' => 'Scanner de postura de segurança para ambientes Microsoft 365 e Azure. Identifica gaps e recomenda remediações.',
            'icon' => 'shield',
            'category' => 'tool',
            'solutionAreas' => ['Security'],
            'requirements' => [
                'minPcsScore' => 50,
                'minStage' => 'Develop',
                'minCertifications' => 2
            ],
            'value' => 'R$ 20.000/ano',
            'isNew' => false
        ],
        [
            'id' => 'm365-adoption-toolkit',
            'name' => 'M365 Adoption Toolkit',
            'description' => 'Kit completo para acelerar adoção de Microsoft 365 com templates, materiais de treinamento e dashboards de uso.',
            'icon' => 'users',
            'category' => 'tool',
            'solutionAreas' => ['Modern Work'],
            'requirements' => [
                'minPcsScore' => 30,
                'minStage' => 'Engage'
            ],
            'value' => 'R$ 8.000/ano',
            'isNew' => false
        ],
        [
            'id' => 'copilot-demo-environment',
            'name' => 'Copilot Demo Environment',
            'description' => 'Ambiente de demonstração totalmente configurado para showcases de Microsoft Copilot com dados fictícios realistas.',
            'icon' => 'sparkles',
            'category' => 'tool',
            'solutionAreas' => ['Modern Work', 'Data & AI'],
            'requirements' => [
                'minPcsScore' => 60,
                'minStage' => 'Growth'
            ],
            'value' => 'R$ 25.000/ano',
            'isNew' => true
        ],
        [
            'id' => 'power-platform-accelerator',
            'name' => 'Power Platform Accelerator',
            'description' => 'Biblioteca de componentes, templates e soluções pré-construídas para Power Apps, Power Automate e Power BI.',
            'icon' => 'zap',
            'category' => 'tool',
            'solutionAreas' => ['Business Applications', 'Digital & App Innovation'],
            'requirements' => [
                'minPcsScore' => 45,
                'minStage' => 'Develop'
            ],
            'value' => 'R$ 12.000/ano',
            'isNew' => false
        ],

        // === TREINAMENTOS ===
        [
            'id' => 'certification-bootcamp',
            'name' => 'Bootcamp de Certificações',
            'description' => 'Acesso a bootcamps intensivos para certificações Microsoft com instrutores especializados e vouchers inclusos.',
            'icon' => 'graduation-cap',
            'category' => 'training',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 20,
                'minStage' => 'Engage'
            ],
            'value' => 'R$ 5.000/pessoa',
            'isNew' => false
        ],
        [
            'id' => 'executive-briefing',
            'name' => 'Executive Briefing Center',
            'description' => 'Sessões exclusivas no Microsoft Technology Center com executivos e arquitetos Microsoft.',
            'icon' => 'building',
            'category' => 'training',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 70,
                'minStage' => 'Growth'
            ],
            'value' => 'Exclusivo',
            'isNew' => false
        ],
        [
            'id' => 'technical-deep-dive',
            'name' => 'Technical Deep Dive Sessions',
            'description' => 'Workshops técnicos avançados com Product Groups da Microsoft sobre roadmap e features em preview.',
            'icon' => 'code',
            'category' => 'training',
            'solutionAreas' => ['Azure Infra', 'Data & AI', 'Digital & App Innovation'],
            'requirements' => [
                'minPcsScore' => 80,
                'minStage' => 'Extend',
                'minCertifications' => 5
            ],
            'value' => 'Exclusivo',
            'isNew' => false
        ],

        // === FUNDING ===
        [
            'id' => 'poc-funding',
            'name' => 'POC Funding',
            'description' => 'Funding para provas de conceito com clientes. Até R$ 30.000 por projeto aprovado.',
            'icon' => 'dollar-sign',
            'category' => 'funding',
            'solutionAreas' => ['Azure Infra', 'Security', 'Data & AI', 'Digital & App Innovation'],
            'requirements' => [
                'minPcsScore' => 50,
                'minStage' => 'Develop'
            ],
            'value' => 'Até R$ 30.000',
            'isNew' => false
        ],
        [
            'id' => 'marketing-development-fund',
            'name' => 'Marketing Development Fund (MDF)',
            'description' => 'Verba para campanhas de marketing, eventos e geração de demanda. Matching de 50% a 100%.',
            'icon' => 'megaphone',
            'category' => 'funding',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 60,
                'minStage' => 'Growth'
            ],
            'value' => 'Até R$ 100.000/ano',
            'isNew' => false
        ],
        [
            'id' => 'solution-incentive',
            'name' => 'Solution Partner Incentive',
            'description' => 'Incentivo financeiro adicional por workloads ativados e crescimento de consumo Azure.',
            'icon' => 'trending-up',
            'category' => 'funding',
            'solutionAreas' => ['Azure Infra', 'Data & AI'],
            'requirements' => [
                'minPcsScore' => 70,
                'minStage' => 'Growth',
                'minRevenue' => 50000
            ],
            'value' => 'Até 15% extra',
            'isNew' => false
        ],

        // === SUPORTE ===
        [
            'id' => 'partner-support-priority',
            'name' => 'Suporte Prioritário',
            'description' => 'Acesso a fila prioritária de suporte técnico Microsoft com SLA reduzido.',
            'icon' => 'headphones',
            'category' => 'support',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 40,
                'minStage' => 'Develop'
            ],
            'value' => 'SLA 4h',
            'isNew' => false
        ],
        [
            'id' => 'dedicated-tam',
            'name' => 'Technical Account Manager',
            'description' => 'TAM dedicado para acompanhamento estratégico e técnico do parceiro.',
            'icon' => 'user-check',
            'category' => 'support',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 75,
                'minStage' => 'Extend'
            ],
            'value' => 'Exclusivo',
            'isNew' => false
        ],
        [
            'id' => 'presales-support',
            'name' => 'Suporte Pré-Vendas',
            'description' => 'Apoio técnico para elaboração de propostas e arquiteturas de solução.',
            'icon' => 'file-text',
            'category' => 'support',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 50,
                'minStage' => 'Develop'
            ],
            'value' => 'Incluído',
            'isNew' => false
        ],

        // === MARKETING ===
        [
            'id' => 'co-sell-program',
            'name' => 'Co-Sell Program',
            'description' => 'Programa de co-venda com equipe Microsoft para oportunidades enterprise.',
            'icon' => 'star',
            'category' => 'marketing',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 65,
                'minStage' => 'Growth'
            ],
            'value' => 'Leads qualificados',
            'isNew' => false
        ],
        [
            'id' => 'marketplace-listing',
            'name' => 'Azure Marketplace Listing',
            'description' => 'Suporte para publicação de soluções no Azure Marketplace com visibilidade global.',
            'icon' => 'external-link',
            'category' => 'marketing',
            'solutionAreas' => ['Azure Infra', 'Data & AI', 'Digital & App Innovation'],
            'requirements' => [
                'minPcsScore' => 70,
                'minStage' => 'Growth',
                'minCertifications' => 3
            ],
            'value' => 'Visibilidade global',
            'isNew' => true
        ],
        [
            'id' => 'partner-showcase',
            'name' => 'Partner Showcase',
            'description' => 'Participação em eventos Microsoft e webinars oficiais como parceiro destaque.',
            'icon' => 'video',
            'category' => 'marketing',
            'solutionAreas' => ['Azure Infra', 'Modern Work', 'Security', 'Data & AI', 'Digital & App Innovation', 'Business Applications'],
            'requirements' => [
                'minPcsScore' => 80,
                'minStage' => 'Extend'
            ],
            'value' => 'Exposição premium',
            'isNew' => false
        ]
    ];

    /**
     * Verifica se um benefício está desbloqueado para um parceiro
     */
    public static function isBenefitUnlocked(array $benefit, array $partner): bool
    {
        $requirements = $benefit['requirements'] ?? [];
        $pcsScore = $partner['pcsScore'] ?? 0;
        $stage = $partner['edgeStage'] ?? 'Engage';
        $certCount = is_array($partner['certifications'] ?? null) 
            ? count($partner['certifications']) 
            : 0;
        $totalRevenue = ($partner['revenueM365'] ?? 0) + ($partner['revenueAzure'] ?? 0) + ($partner['revenueSecurity'] ?? 0);
        $partnerArea = $partner['solutionArea'] ?? '';

        // Verificar área de solução
        if ($partnerArea && !in_array($partnerArea, $benefit['solutionAreas'])) {
            return false;
        }

        // Verificar PCS Score
        if (isset($requirements['minPcsScore']) && $pcsScore < $requirements['minPcsScore']) {
            return false;
        }

        // Verificar Stage
        if (isset($requirements['minStage'])) {
            $stageOrder = Constants::STAGE_ORDER;
            $requiredOrder = $stageOrder[$requirements['minStage']] ?? 0;
            $currentOrder = $stageOrder[$stage] ?? 0;
            if ($currentOrder < $requiredOrder) {
                return false;
            }
        }

        // Verificar certificações
        if (isset($requirements['minCertifications']) && $certCount < $requirements['minCertifications']) {
            return false;
        }

        // Verificar receita
        if (isset($requirements['minRevenue']) && $totalRevenue < $requirements['minRevenue']) {
            return false;
        }

        return true;
    }

    /**
     * Calcula o progresso para desbloquear um benefício
     */
    public static function getUnlockProgress(array $benefit, array $partner): array
    {
        $requirements = $benefit['requirements'] ?? [];
        $pcsScore = $partner['pcsScore'] ?? 0;
        $stage = $partner['edgeStage'] ?? 'Engage';
        $certCount = is_array($partner['certifications'] ?? null) 
            ? count($partner['certifications']) 
            : 0;
        $totalRevenue = ($partner['revenueM365'] ?? 0) + ($partner['revenueAzure'] ?? 0) + ($partner['revenueSecurity'] ?? 0);

        $missing = [];
        $checks = 0;
        $passed = 0;

        if (isset($requirements['minPcsScore'])) {
            $checks++;
            if ($pcsScore >= $requirements['minPcsScore']) {
                $passed++;
            } else {
                $missing[] = "PCS Score: {$pcsScore}/{$requirements['minPcsScore']}";
            }
        }

        if (isset($requirements['minStage'])) {
            $checks++;
            $stageOrder = Constants::STAGE_ORDER;
            $requiredOrder = $stageOrder[$requirements['minStage']] ?? 0;
            $currentOrder = $stageOrder[$stage] ?? 0;
            if ($currentOrder >= $requiredOrder) {
                $passed++;
            } else {
                $missing[] = "Stage: {$stage} → {$requirements['minStage']}";
            }
        }

        if (isset($requirements['minCertifications'])) {
            $checks++;
            if ($certCount >= $requirements['minCertifications']) {
                $passed++;
            } else {
                $missing[] = "Certificações: {$certCount}/{$requirements['minCertifications']}";
            }
        }

        if (isset($requirements['minRevenue'])) {
            $checks++;
            if ($totalRevenue >= $requirements['minRevenue']) {
                $passed++;
            } else {
                $formattedTotal = number_format($totalRevenue, 0, ',', '.');
                $formattedRequired = number_format($requirements['minRevenue'], 0, ',', '.');
                $missing[] = "Receita: R$ {$formattedTotal}/R$ {$formattedRequired}";
            }
        }

        $percentage = $checks > 0 ? round(($passed / $checks) * 100) : 100;

        return [
            'percentage' => $percentage,
            'missing' => $missing
        ];
    }

    /**
     * Filtra benefícios por categoria e área de solução
     */
    public static function filterBenefits(?string $category, ?string $solutionArea, bool $onlyUnlocked = false, ?array $partner = null): array
    {
        $benefits = self::BENEFITS;

        // Filtrar por categoria
        if ($category && $category !== 'all') {
            $benefits = array_filter($benefits, fn($b) => $b['category'] === $category);
        }

        // Filtrar por área de solução
        if ($solutionArea && $solutionArea !== 'all') {
            $benefits = array_filter($benefits, fn($b) => in_array($solutionArea, $b['solutionAreas']));
        }

        // Filtrar apenas desbloqueados
        if ($onlyUnlocked && $partner) {
            $benefits = array_filter($benefits, fn($b) => self::isBenefitUnlocked($b, $partner));
        }

        return array_values($benefits);
    }

    /**
     * Benefícios Oficiais do Microsoft AI Cloud Partner Program (MAICPP)
     * Fonte: Microsoft Partner Center / Solutions Partner Benefits Guide
     * Última atualização: 2025 (Benefits Refresh 2026)
     * 
     * Todos os Solutions Partner Designations (SPD) recebem:
     * - Partner Cloud Support: 50 incidentes/ano (para produtos cloud)
     * - Partner On-premises Support: 20 incidentes/ano
     * - Azure Bulk Credits: Pool organizacional (valor varia por programa)
     * - Visual Studio Enterprise IDE: Licenças para desenvolvedores
     * - GitHub Enterprise Cloud: Licenças para equipe
     * - Azure DevOps Basic + Test Plans
     * - Partner Marketing Center Pro (AI-powered)
     * - Software Benefits (on-premises): Licenças IUR
     */
    public const MICROSOFT_OFFICIAL_BENEFITS = [
        // === BENEFÍCIOS PADRÃO SPD (TODAS AS ÁREAS) ===
        'standard' => [
            'technicalSupport' => [
                'name' => 'Partner Technical Support',
                'description' => 'Suporte técnico incluído com Solutions Partner designation',
                'items' => [
                    ['name' => 'Partner Cloud Support', 'quantity' => '50 incidentes/ano', 'description' => 'Suporte técnico para produtos cloud (Azure, M365, Dynamics)'],
                    ['name' => 'Partner On-premises Support', 'quantity' => '20 incidentes/ano', 'description' => 'Suporte para produtos on-premises recentes (Windows Server, SQL Server)'],
                    ['name' => 'ISV Technical Consultation', 'quantity' => 'Disponível para ISVs', 'description' => 'Consultoria técnica para ISV Success partners']
                ]
            ],
            'azureCredits' => [
                'name' => 'Azure Bulk Credits',
                'description' => 'Créditos Azure anuais em pool organizacional',
                'items' => [
                    ['name' => 'Azure Bulk (Yearly) Credit', 'description' => 'Pool anual para uso em toda organização'],
                    ['name' => 'Copilot for Security Benefit', 'description' => 'Via Azure credits'],
                    ['name' => 'GitHub Copilot Enterprise', 'description' => 'Via Azure credits'],
                    ['name' => 'GitHub Enterprise Metered', 'description' => 'Via Azure credits']
                ]
            ],
            'developerTools' => [
                'name' => 'Developer Tools',
                'description' => 'Ferramentas de desenvolvimento incluídas',
                'items' => [
                    ['name' => 'Visual Studio Enterprise IDE', 'quantity' => '25 licenças', 'description' => 'IDE completa para desenvolvedores'],
                    ['name' => 'GitHub Enterprise Cloud', 'quantity' => '10 licenças', 'description' => 'Repositórios e CI/CD enterprise'],
                    ['name' => 'Azure DevOps Basic', 'quantity' => 'Incluído', 'description' => 'Boards, Repos, Pipelines básico'],
                    ['name' => 'Azure DevOps Test Plans', 'quantity' => 'Incluído', 'description' => 'Test Plans para QA'],
                    ['name' => 'C# Dev Kit for VS Code', 'quantity' => 'Incluído', 'description' => 'Extensão para VS Code']
                ]
            ],
            'marketing' => [
                'name' => 'Marketing Benefits',
                'description' => 'Benefícios de go-to-market incluídos',
                'items' => [
                    ['name' => 'Partner Marketing Center Pro', 'description' => 'Plataforma AI-powered para campanhas'],
                    ['name' => 'Marketplace Rewards Program', 'description' => 'Benefícios para listagens no Marketplace'],
                    ['name' => 'Co-sell Ready Status', 'description' => 'Elegibilidade para co-venda com Microsoft'],
                    ['name' => 'Azure Community Forums', 'description' => 'Acesso a fóruns de parceiros'],
                    ['name' => 'Concierge Chat Support', 'description' => 'Suporte via chat para benefícios']
                ]
            ],
            'software' => [
                'name' => 'Software Benefits (IUR)',
                'description' => 'Licenças de uso interno para demo e desenvolvimento',
                'items' => [
                    ['name' => 'Windows Server', 'description' => 'Para ambientes de teste/demo'],
                    ['name' => 'SQL Server', 'description' => 'Para desenvolvimento interno'],
                    ['name' => 'Office Professional Plus', 'description' => 'Para uso interno'],
                    ['name' => 'Dynamics Products', 'description' => 'Licenças IUR disponíveis']
                ]
            ],
            'learning' => [
                'name' => 'Learning & Community',
                'description' => 'Recursos de treinamento e comunidade',
                'items' => [
                    ['name' => 'Developer Training Resources', 'description' => 'Recursos de treinamento focados em desenvolvimento moderno'],
                    ['name' => 'Microsoft Tech Community', 'description' => 'Acesso à comunidade técnica Microsoft'],
                    ['name' => 'Visual Studio Live! Benefits', 'description' => 'Benefícios para eventos Visual Studio']
                ]
            ]
        ],
        
        // === BENEFÍCIOS ESPECÍFICOS POR ÁREA ===
        'byArea' => [
            'Infrastructure' => [
                'rebate' => '4% - 10%',
                'incentivePrograms' => [
                    'Azure Migrate & Modernize Incentive',
                    'Azure VMware Solution Incentive',
                    'SAP on Azure Incentive',
                    'Azure Arc Enabled Program'
                ],
                'additionalBenefits' => [
                    'Azure VMware Solution Demo Environment',
                    'SAP on Azure Assessment Tools',
                    'Azure Arc Demo Subscriptions'
                ],
                'specializations' => [
                    'Azure VMware Solution',
                    'SAP on Azure',
                    'Microsoft Windows Virtual Desktop',
                    'Infra and Database Migration to Azure'
                ]
            ],
            'Modern Work' => [
                'rebate' => '5% - 12%',
                'incentivePrograms' => [
                    'Teams Calling & Rooms Incentive',
                    'Microsoft 365 Copilot Adoption',
                    'FastTrack Ready Partner',
                    'Viva Suite Adoption Program'
                ],
                'additionalBenefits' => [
                    'Microsoft 365 E5 (25 licenças IUR)',
                    'Teams Demo Environments',
                    'Copilot Demo Tenant'
                ],
                'specializations' => [
                    'Adoption and Change Management',
                    'Calling for Microsoft Teams',
                    'Meetings and Meeting Rooms for Microsoft Teams',
                    'Teamwork Deployment'
                ]
            ],
            'Security' => [
                'rebate' => '6% - 15%',
                'incentivePrograms' => [
                    'Security AMMP',
                    'Sentinel Migration Incentive',
                    'Defender XDR Partner Program',
                    'Identity & Access Modernization'
                ],
                'additionalBenefits' => [
                    'Microsoft 365 E5 Security (25 licenças IUR)',
                    'Microsoft Sentinel Credits',
                    'Defender for Endpoint P2 (IUR)',
                    'Security Assessment Tools'
                ],
                'specializations' => [
                    'Cloud Security',
                    'Identity and Access Management',
                    'Threat Protection',
                    'Information Protection and Governance'
                ]
            ],
            'Data & AI' => [
                'rebate' => '5% - 12%',
                'incentivePrograms' => [
                    'AI Inner Circle Program',
                    'Microsoft Fabric Partner',
                    'Azure OpenAI Incentive',
                    'Data & AI AMMP'
                ],
                'additionalBenefits' => [
                    'Azure OpenAI Service Access',
                    'Microsoft Fabric Capacity Credits',
                    'Power BI Premium (IUR)',
                    'Copilot Studio Demo Access'
                ],
                'specializations' => [
                    'AI and Machine Learning on Azure',
                    'Analytics on Azure (Power BI)',
                    'Data Warehouse Migration to Azure',
                    'Kubernetes on Azure'
                ]
            ],
            'Digital & App Innovation' => [
                'rebate' => '4% - 10%',
                'incentivePrograms' => [
                    'App Innovation Incentive',
                    'GitHub Advanced Security',
                    'Azure Kubernetes Service',
                    'Power Platform ISV Program'
                ],
                'additionalBenefits' => [
                    'GitHub Enterprise Cloud (10 licenças)',
                    'GitHub Copilot Enterprise (via Azure credits)',
                    'App Modernization Tools',
                    'ISV Success Program Access'
                ],
                'specializations' => [
                    'DevOps with GitHub',
                    'Kubernetes on Azure',
                    'Modernize Enterprise Apps',
                    'Web Application Development'
                ]
            ],
            'Business Applications' => [
                'rebate' => '8% - 18%',
                'incentivePrograms' => [
                    'ISV Connect',
                    'Power Platform Incentive',
                    'Dynamics 365 AMMP',
                    'Business Central Embed Program'
                ],
                'additionalBenefits' => [
                    'Dynamics 365 Enterprise (25 licenças IUR)',
                    'Power Platform Premium (IUR)',
                    'Dataverse Capacity (IUR)',
                    'AppSource Featured Listing'
                ],
                'specializations' => [
                    'Low Code App Development',
                    'Small and Midsize Business Management',
                    'Intelligent Automation',
                    'Business Intelligence'
                ]
            ]
        ]
    ];

    /**
     * Retorna os benefícios oficiais da Microsoft por área de solução
     */
    public static function getOfficialBenefitsByArea(string $solutionArea): array
    {
        $standard = self::MICROSOFT_OFFICIAL_BENEFITS['standard'];
        $byArea = self::MICROSOFT_OFFICIAL_BENEFITS['byArea'][$solutionArea] ?? [];

        return [
            'standard' => $standard,
            'specific' => $byArea
        ];
    }

    /**
     * Retorna todos os benefícios padrão do Solutions Partner
     */
    public static function getStandardBenefits(): array
    {
        return self::MICROSOFT_OFFICIAL_BENEFITS['standard'];
    }
}
