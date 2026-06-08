<?php
/**
 * Cloud Partner Hub - Constantes e Configurações
 * Baseado nas regras oficiais do Microsoft Partner Capability Score (PCS)
 * Fonte: https://learn.microsoft.com/en-us/partner-center/membership/partner-capability-score
 * 
 * REGRAS GERAIS:
 * - Total máximo: 100 pontos por área de solução
 * - Mínimo para qualificar: 70 pontos
 * - TODOS os métricas devem ser > 0 para qualificar
 * - Duas trilhas: Enterprise e SMB (com thresholds diferentes)
 */

declare(strict_types=1);

namespace Features\CloudPartnerHub\Config;

class Constants
{
    // Tipos de Solution Area (6 áreas oficiais Microsoft)
    public const SOLUTION_AREAS = [
        'Modern Work',
        'Infrastructure',
        'Security',
        'Data & AI',
        'Digital & App Innovation',
        'Business Applications'
    ];

    // Estágios do Edge Program
    public const EDGE_STAGES = ['Engage', 'Develop', 'Growth', 'Extend'];

    // Ordem dos stages para comparação
    public const STAGE_ORDER = [
        'Engage' => 1,
        'Develop' => 2,
        'Growth' => 3,
        'Extend' => 4
    ];

    /**
     * PCS MAX SCORES POR SOLUTION AREA
     * Valores oficiais Microsoft Partner Center
     * 
     * Performance: Net customer adds
     * Skilling: Intermediate + Advanced certifications
     * Customer Success: Usage growth + Deployments
     */
    public const PCS_MAX_SCORES = [
        'Modern Work' => [
            'performance' => 20,           // Net customer adds
            'skilling' => 25,              // Intermediate (varies) + Advanced (varies)
            'customerSuccess' => 55,       // Usage growth (30) + Deployments (25)
            'total' => 100
        ],
        'Infrastructure' => [
            'performance' => 30,           // Net customer adds (10 pts × 3 clientes)
            'skilling' => 40,              // Intermediate (20) + Advanced (20)
            'customerSuccess' => 30,       // Usage growth (20) + Deployments (10)
            'total' => 100
        ],
        'Security' => [
            'performance' => 20,           // Net customer adds
            'skilling' => 20,              // Apenas Intermediate (Azure + M365)
            'customerSuccess' => 60,       // Usage growth (20) + Deployments (20) × Azure/M365
            'total' => 100
        ],
        'Data & AI' => [
            'performance' => 30,           // Net customer adds (10 pts × 3 clientes)
            'skilling' => 40,              // Intermediate (40) - sem Advanced
            'customerSuccess' => 30,       // Usage growth (20) + Deployments (10)
            'total' => 100
        ],
        'Digital & App Innovation' => [
            'performance' => 30,           // Net customer adds (10 pts × 3 clientes)
            'skilling' => 40,              // Intermediate (20) + Advanced (20)
            'customerSuccess' => 30,       // Usage growth (20) + Deployments (10)
            'total' => 100
        ],
        'Business Applications' => [
            'performance' => 15,           // Net customer adds (3 pts × 5 clientes)
            'skilling' => 35,              // Intermediate (20) + Advanced (15)
            'customerSuccess' => 50,       // Usage growth (30) + Deployments (20)
            'total' => 100
        ]
    ];

    /**
     * DETALHES DAS MÉTRICAS POR SOLUTION AREA
     * Thresholds oficiais Enterprise vs SMB
     */
    public const PCS_METRIC_DETAILS = [
        'Modern Work' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['threshold' => 5, 'pointsPerCustomer' => 4, 'max' => 20],
                'smb' => ['threshold' => 10, 'pointsPerCustomer' => 2, 'max' => 20],
                'description' => 'Workloads: Exchange, Teams, Intune, M365 Apps, SharePoint, Viva, etc.'
            ],
            'skilling' => [
                'intermediate' => [
                    'enterprise' => ['required' => 4, 'max' => 'varies'],
                    'smb' => ['required' => 2, 'max' => 'varies'],
                    'certifications' => ['MS-900', 'MD-102', 'MS-700', 'MS-721', 'SC-300']
                ],
                'advanced' => [
                    'enterprise' => ['required' => 2, 'max' => 'varies'],
                    'smb' => ['required' => 1, 'max' => 'varies'],
                    'certifications' => ['MS-102: Enterprise Administrator Expert']
                ]
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 30, 'description' => 'Growth in MAU across workloads'],
                'deployments' => ['max' => 25, 'description' => 'Net new deployments with 40%+ usage']
            ]
        ],
        'Infrastructure' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['acrThreshold' => 1000, 'pointsPerCustomer' => 10, 'max' => 30],
                'smb' => ['acrThreshold' => 500, 'pointsPerCustomer' => 10, 'max' => 30],
                'description' => 'Customer tenants with qualifying ACR in Azure services'
            ],
            'skilling' => [
                'intermediate' => [
                    'prerequisite' => 'AZ-104: Azure Administrator Associate (2 pessoas Enterprise / 1 SMB)',
                    'certifications' => ['AZ-700: Network Engineer', 'AZ-800: Windows Server Hybrid Admin'],
                    'enterprise' => ['max' => 20],
                    'smb' => ['max' => 20]
                ],
                'advanced' => [
                    'prerequisite' => 'AZ-305: Azure Solutions Architect Expert (2 pessoas Enterprise / 1 SMB)',
                    'certifications' => ['AZ-140: Virtual Desktop', 'AZ-120: SAP on Azure'],
                    'enterprise' => ['max' => 20],
                    'smb' => ['max' => 20]
                ]
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 20, 'description' => '1% ACR growth = 1 point'],
                'deployments' => ['max' => 10, 'description' => '5 deployments × 2 pts each']
            ]
        ],
        'Security' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['threshold' => 10, 'pointsPerCustomer' => 2, 'max' => 20],
                'smb' => ['threshold' => 5, 'pointsPerCustomer' => 4, 'max' => 20],
                'description' => 'Azure Security + Microsoft 365 Security workloads'
            ],
            'skilling' => [
                'intermediate' => [
                    'step1' => 'AZ-500: Azure Security Engineer (required)',
                    'step2' => 'SC-200: Security Operations Analyst (required)',
                    'step3' => ['SC-100: Cybersecurity Architect', 'SC-300: Identity Admin', 'SC-400: Info Protection'],
                    'enterprise' => ['max' => 20, 'pointsPerCert' => 6.67],
                    'smb' => ['max' => 20, 'step1Points' => 4, 'step2Points' => 4, 'step3Points' => 8]
                ],
                'advanced' => null // Não aplicável para Security
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 20, 'description' => 'Security ACR growth + M365 Protected Users'],
                'deployments' => ['max' => 20, 'description' => '6 deployments × 3.3 pts each']
            ]
        ],
        'Data & AI' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['acrThreshold' => 1000, 'pointsPerCustomer' => 10, 'max' => 30],
                'smb' => ['acrThreshold' => 500, 'pointsPerCustomer' => 10, 'max' => 30],
                'description' => 'Customer tenants with qualifying ACR in Data & AI services'
            ],
            'skilling' => [
                'intermediate' => [
                    'step1' => 'AZ-104: Azure Administrator (2 Enterprise / 1 SMB)',
                    'step2' => 'AZ-305: Solutions Architect (2 Enterprise / 1 SMB)',
                    'step3' => ['DP-300: Database Admin', 'AI-102: AI Engineer', 'DP-100: Data Scientist', 'DP-203: Data Engineer'],
                    'enterprise' => ['max' => 40, 'pointsPerCert' => 4],
                    'smb' => ['max' => 40, 'step1Points' => 4, 'step2Points' => 4]
                ],
                'advanced' => null // Não aplicável para Data & AI
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 20, 'description' => '1% ACR growth = 1 point'],
                'deployments' => ['max' => 10, 'description' => '5 deployments × 2 pts each']
            ]
        ],
        'Digital & App Innovation' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['acrThreshold' => 1000, 'pointsPerCustomer' => 10, 'max' => 30],
                'smb' => ['acrThreshold' => 500, 'pointsPerCustomer' => 10, 'max' => 30],
                'description' => 'Customer tenants with qualifying ACR'
            ],
            'skilling' => [
                'intermediate' => [
                    'prerequisite' => 'AZ-104: Azure Administrator (2 Enterprise / 1 SMB)',
                    'certifications' => ['AZ-204: Developer', 'PL-400: Power Platform Developer'],
                    'enterprise' => ['max' => 20],
                    'smb' => ['max' => 20]
                ],
                'advanced' => [
                    'prerequisite' => 'AZ-305: Solutions Architect (2 Enterprise / 1 SMB)',
                    'certifications' => ['AZ-400: DevOps Engineer', 'PL-600: Solution Architect'],
                    'enterprise' => ['max' => 20],
                    'smb' => ['max' => 20]
                ]
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 20, 'description' => '1% ACR growth = 1 point'],
                'deployments' => ['max' => 10, 'description' => '5 deployments × 2 pts each']
            ]
        ],
        'Business Applications' => [
            'performance' => [
                'metric' => 'Net customer adds',
                'enterprise' => ['revenueThreshold' => 1500, 'pointsPerWorkload' => 3, 'max' => 15],
                'smb' => ['revenueThreshold' => 250, 'pointsPerWorkload' => 3, 'max' => 15],
                'description' => 'Dynamics 365 + Power Platform workloads'
            ],
            'skilling' => [
                'intermediate' => [
                    'certifications' => ['MB-210', 'MB-220', 'MB-230', 'MB-800', 'PL-200', 'PL-400', 'PL-300'],
                    'enterprise' => ['required' => 20, 'pointsPerCert' => 1, 'max' => 20],
                    'smb' => ['required' => 5, 'pointsPerCert' => 4, 'max' => 20]
                ],
                'advanced' => [
                    'certifications' => ['MB-335', 'MB-700', 'PL-600', 'MB-820'],
                    'enterprise' => ['required' => 7, 'pointsPerCert' => 2.14, 'max' => 15],
                    'smb' => ['required' => 2, 'pointsPerCert' => 7.5, 'max' => 15]
                ]
            ],
            'customerSuccess' => [
                'usageGrowth' => ['max' => 30, 'description' => '1% MCV growth = 1 point', 'threshold' => '30%'],
                'deployments' => ['max' => 20, 'description' => '5 deployments × 4 pts each']
            ]
        ]
    ];

    /**
     * CERTIFICAÇÕES OBRIGATÓRIAS E RECOMENDADAS POR SOLUTION AREA
     * Baseado na documentação oficial Microsoft Partner Center
     */
    public const SKILLING_RULES = [
        'Infrastructure' => [
            'intermediate' => [
                'AZ-104: Azure Administrator Associate (pré-requisito)',
                'AZ-700: Azure Network Engineer Associate',
                'AZ-800: Windows Server Hybrid Administrator Associate'
            ],
            'advanced' => [
                'AZ-305: Azure Solutions Architect Expert (pré-requisito)',
                'AZ-140: Azure Virtual Desktop Specialty',
                'AZ-120: Azure for SAP Workloads Specialty'
            ],
            'specializations' => ['Azure Virtual Desktop', 'Azure VMware Solution', 'SAP on Azure'],
            'resources' => ['Azure Cloud Week', 'Azure Sales Bootcamp']
        ],
        'Data & AI' => [
            'intermediate' => [
                'AZ-104: Azure Administrator Associate (pré-requisito)',
                'AZ-305: Azure Solutions Architect Expert (pré-requisito)',
                'DP-300: Azure Database Administrator Associate',
                'AI-102: Azure AI Engineer Associate',
                'DP-100: Azure Data Scientist Associate',
                'DP-203: Azure Data Engineer Associate',
                'PL-300: Power BI Data Analyst Associate',
                'DP-600: Fabric Analytics Engineer Associate',
                'DP-700: Fabric Data Engineer Associate'
            ],
            'advanced' => [], // Não aplicável para Data & AI
            'specializations' => ['AI and Machine Learning', 'Analytics on Azure', 'Data Warehouse Migration'],
            'resources' => ['AI Bootcamps', 'Microsoft Fabric Workshop', 'Azure OpenAI Workshop']
        ],
        'Digital & App Innovation' => [
            'intermediate' => [
                'AZ-104: Azure Administrator Associate (pré-requisito)',
                'AZ-204: Azure Developer Associate',
                'PL-400: Power Platform Developer Associate'
            ],
            'advanced' => [
                'AZ-305: Azure Solutions Architect Expert (pré-requisito)',
                'AZ-400: Azure DevOps Engineer Expert',
                'PL-600: Power Platform Solution Architect Expert'
            ],
            'specializations' => ['DevOps with GitHub', 'Kubernetes on Azure', 'Modernize Enterprise Apps'],
            'resources' => ['Build & Modernize AI Apps Workshop', 'App Innovation Cloud Week']
        ],
        'Modern Work' => [
            'intermediate' => [
                'MS-900: Microsoft 365 Fundamentals',
                'MD-102: Microsoft 365 Endpoint Administrator Associate',
                'MS-700: Microsoft 365 Teams Administrator Associate',
                'MS-721: Microsoft 365 Collaboration Communications Systems Engineer',
                'SC-300: Microsoft Identity and Access Administrator'
            ],
            'advanced' => [
                'MS-102: Microsoft 365 Enterprise Administrator Expert',
                'Teams Meetings and Meeting Rooms Technical Assessment'
            ],
            'specializations' => ['Adoption and Change Management', 'Calling for Microsoft Teams', 'Teamwork Deployment'],
            'resources' => ['Modern Work Cloud Week', 'Copilot for M365 Sales Bootcamp', 'CSP Masters Program']
        ],
        'Security' => [
            'intermediate' => [
                'AZ-500: Azure Security Engineer Associate (obrigatório)',
                'SC-200: Microsoft Security Operations Analyst (obrigatório)',
                'SC-100: Microsoft Cybersecurity Architect Expert',
                'SC-300: Microsoft Identity and Access Administrator',
                'SC-400: Microsoft Information Protection Administrator'
            ],
            'advanced' => [], // Security usa apenas Intermediate (com steps)
            'specializations' => ['Cloud Security', 'Identity and Access Management', 'Threat Protection'],
            'resources' => ['Security Cloud Week', 'Microsoft Copilot for Security Bootcamp', 'Shadow IT Workshop']
        ],
        'Business Applications' => [
            'intermediate' => [
                'MB-210: Dynamics 365 Sales Functional Consultant',
                'MB-220: Dynamics 365 Customer Insights (Journeys)',
                'MB-230: Dynamics 365 Customer Service Functional Consultant',
                'MB-240: Dynamics 365 Field Service Functional Consultant',
                'MB-310: Dynamics 365 Finance Functional Consultant',
                'MB-330: Dynamics 365 Supply Chain Management',
                'MB-800: Dynamics 365 Business Central Functional Consultant',
                'PL-200: Power Platform Functional Consultant',
                'PL-300: Power BI Data Analyst Associate',
                'PL-400: Power Platform Developer Associate',
                'PL-500: Power Automate RPA Developer Associate'
            ],
            'advanced' => [
                'MB-335: Dynamics 365 Supply Chain Management Expert',
                'MB-700: Dynamics 365 Finance and Operations Apps Solution Architect',
                'MB-820: Dynamics 365 Business Central Developer',
                'PL-600: Power Platform Solution Architect Expert'
            ],
            'specializations' => ['Low Code App Development', 'Small and Midsize Business Management', 'Intelligent Automation'],
            'resources' => ['Business Applications Cloud Week', 'SMB Sales Bootcamp', 'Catalyst Partner Training']
        ]
    ];

    // Etapas da Jornada do Parceiro
    public const JOURNEY_STEPS = [
        ['id' => 1, 'title' => 'Welcome', 'icon' => 'rocket', 'desc' => 'Onboarding inicial'],
        ['id' => 2, 'title' => 'Triagem', 'icon' => 'shield-check', 'desc' => 'Assessment de maturidade'],
        ['id' => 3, 'title' => 'Soluções', 'icon' => 'briefcase', 'desc' => 'Definição de trilha'],
        ['id' => 4, 'title' => 'Métricas', 'icon' => 'bar-chart', 'desc' => 'Input de dados PCS'],
        ['id' => 5, 'title' => 'Plano', 'icon' => 'award', 'desc' => 'Plano de ação'],
        ['id' => 6, 'title' => 'GTM', 'icon' => 'megaphone', 'desc' => 'Estratégia de mercado'],
    ];

    // Categorias de benefícios
    public const BENEFIT_CATEGORIES = [
        'tool' => 'Ferramentas',
        'training' => 'Treinamentos',
        'funding' => 'Funding',
        'support' => 'Suporte',
        'marketing' => 'Marketing'
    ];

    // Cores das categorias
    public const CATEGORY_COLORS = [
        'tool' => '#0078D4',
        'training' => '#8B5CF6',
        'funding' => '#16A34A',
        'support' => '#EA580C',
        'marketing' => '#DB2777'
    ];

    /**
     * REGRAS DE QUALIFICAÇÃO MICROSOFT PCS
     * Para se qualificar como Solutions Partner:
     * - Score total >= 70 pontos
     * - TODOS os métricas individuais > 0
     */
    public const QUALIFICATION_RULES = [
        'minTotalScore' => 70,
        'maxTotalScore' => 100,
        'allMetricsMustBePositive' => true,
        'qualificationWindowMonths' => 6,  // Janela de elegibilidade: 6 meses
        'renewalGracePeriodDays' => 30
    ];

    /**
     * Calcula o estágio Edge baseado no PCS Score
     */
    public static function calculateEdgeStage(int $pcsScore): string
    {
        if ($pcsScore >= 80) return 'Extend';
        if ($pcsScore >= 60) return 'Growth';
        if ($pcsScore >= 40) return 'Develop';
        return 'Engage';
    }

    /**
     * Calcula o PCS total
     */
    public static function calculateTotalPcs(int $performance, int $skilling, int $customerSuccess): int
    {
        return $performance + $skilling + $customerSuccess;
    }

    /**
     * Verifica se o parceiro está qualificado para Solutions Partner
     * 
     * @param int $performance Score de Performance
     * @param int $skilling Score de Skilling  
     * @param int $customerSuccess Score de Customer Success
     * @return array ['qualified' => bool, 'totalScore' => int, 'issues' => array]
     */
    public static function checkQualification(int $performance, int $skilling, int $customerSuccess): array
    {
        $totalScore = self::calculateTotalPcs($performance, $skilling, $customerSuccess);
        $issues = [];
        
        // Verificar se todos os métricas são > 0
        if ($performance <= 0) {
            $issues[] = 'Performance deve ser maior que 0';
        }
        if ($skilling <= 0) {
            $issues[] = 'Skilling deve ser maior que 0';
        }
        if ($customerSuccess <= 0) {
            $issues[] = 'Customer Success deve ser maior que 0';
        }
        
        // Verificar score total
        if ($totalScore < self::QUALIFICATION_RULES['minTotalScore']) {
            $issues[] = 'Score total deve ser pelo menos ' . self::QUALIFICATION_RULES['minTotalScore'] . ' pontos';
        }
        
        return [
            'qualified' => empty($issues),
            'totalScore' => $totalScore,
            'issues' => $issues,
            'stage' => self::calculateEdgeStage($totalScore)
        ];
    }

    /**
     * Retorna os scores máximos para uma área de solução
     */
    public static function getMaxScores(string $solutionArea): array
    {
        return self::PCS_MAX_SCORES[$solutionArea] ?? [
            'performance' => 30,
            'skilling' => 40,
            'customerSuccess' => 30,
            'total' => 100
        ];
    }

    /**
     * Retorna os detalhes das métricas para uma área de solução
     */
    public static function getMetricDetails(string $solutionArea): ?array
    {
        return self::PCS_METRIC_DETAILS[$solutionArea] ?? null;
    }

    /**
     * Retorna as certificações necessárias para uma área
     */
    public static function getSkillingRules(string $solutionArea): ?array
    {
        return self::SKILLING_RULES[$solutionArea] ?? null;
    }
}
