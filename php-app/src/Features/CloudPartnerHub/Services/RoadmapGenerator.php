<?php
/**
 * Cloud Partner Hub - Gerador de Roadmap Inteligente
 * 
 * Analisa os dados do parceiro e gera um plano de ação personalizado
 * para alcançar a designação Solutions Partner.
 * 
 * Baseado nas regras oficiais do Microsoft Partner Capability Score (PCS)
 */

declare(strict_types=1);

namespace Features\CloudPartnerHub\Services;

use Features\CloudPartnerHub\Config\Constants;

class RoadmapGenerator
{
    private array $partnerData;
    private string $solutionArea;
    private array $pcsMaxScores;
    private array $metricDetails;
    private array $skillingRules;
    
    // Pesos para priorização (quanto maior, mais prioritário)
    private const PRIORITY_WEIGHTS = [
        'skilling' => 3,      // Certificações são mais controláveis
        'performance' => 2,    // Depende de vendas/clientes
        'customerSuccess' => 1 // Depende de adoção pelos clientes
    ];
    
    // Tempo estimado em semanas para cada tipo de ação
    private const ACTION_DURATION = [
        'certification_fundamentals' => 2,
        'certification_associate' => 4,
        'certification_expert' => 6,
        'certification_specialty' => 4,
        'customer_acquisition' => 8,
        'customer_deployment' => 4,
        'usage_growth' => 12,
        'workshop' => 1,
        'bootcamp' => 1
    ];

    public function __construct(array $partnerData)
    {
        $this->partnerData = $partnerData;
        $this->solutionArea = $partnerData['selectedSolutionArea'] ?? 'Infrastructure';
        $this->pcsMaxScores = Constants::PCS_MAX_SCORES[$this->solutionArea] ?? Constants::PCS_MAX_SCORES['Infrastructure'];
        $this->metricDetails = Constants::PCS_METRIC_DETAILS[$this->solutionArea] ?? [];
        $this->skillingRules = Constants::SKILLING_RULES[$this->solutionArea] ?? [];
    }

    /**
     * Gera o roadmap completo para o parceiro
     */
    public function generate(): array
    {
        $analysis = $this->analyzeCurrentState();
        $gaps = $this->identifyGaps($analysis);
        $actions = $this->generateActions($gaps);
        $prioritizedActions = $this->prioritizeActions($actions);
        $timeline = $this->createTimeline($prioritizedActions);
        $milestones = $this->defineMilestones($timeline);
        
        return [
            'summary' => $this->generateSummary($analysis, $gaps),
            'currentState' => $analysis,
            'gaps' => $gaps,
            'actions' => $prioritizedActions,
            'timeline' => $timeline,
            'milestones' => $milestones,
            'recommendations' => $this->generateRecommendations($gaps),
            'estimatedTimeToQualify' => $this->estimateTimeToQualify($prioritizedActions),
            'quickWins' => $this->identifyQuickWins($prioritizedActions)
        ];
    }

    /**
     * Analisa o estado atual do parceiro
     */
    private function analyzeCurrentState(): array
    {
        $performance = (float)($this->partnerData['pcsPerformance'] ?? 0);
        $skilling = (float)($this->partnerData['pcsSkilling'] ?? 0);
        $customerSuccess = (float)($this->partnerData['pcsCustomerSuccess'] ?? 0);
        $total = $performance + $skilling + $customerSuccess;
        
        $certifications = $this->partnerData['certifications'] ?? [];
        $certCount = is_array($certifications) ? count($certifications) : 0;
        
        return [
            'scores' => [
                'performance' => [
                    'current' => $performance,
                    'max' => $this->pcsMaxScores['performance'],
                    'percentage' => $this->pcsMaxScores['performance'] > 0 
                        ? round(($performance / $this->pcsMaxScores['performance']) * 100) 
                        : 0
                ],
                'skilling' => [
                    'current' => $skilling,
                    'max' => $this->pcsMaxScores['skilling'],
                    'percentage' => $this->pcsMaxScores['skilling'] > 0 
                        ? round(($skilling / $this->pcsMaxScores['skilling']) * 100) 
                        : 0
                ],
                'customerSuccess' => [
                    'current' => $customerSuccess,
                    'max' => $this->pcsMaxScores['customerSuccess'],
                    'percentage' => $this->pcsMaxScores['customerSuccess'] > 0 
                        ? round(($customerSuccess / $this->pcsMaxScores['customerSuccess']) * 100) 
                        : 0
                ],
                'total' => [
                    'current' => $total,
                    'max' => 100,
                    'percentage' => round($total)
                ]
            ],
            'certifications' => [
                'count' => $certCount,
                'list' => $certifications
            ],
            'isQualified' => $this->checkQualification($performance, $skilling, $customerSuccess, $total),
            'qualificationStatus' => $this->getQualificationStatus($performance, $skilling, $customerSuccess, $total),
            'solutionArea' => $this->solutionArea,
            'track' => $this->determineTrack()
        ];
    }

    /**
     * Verifica se o parceiro está qualificado
     */
    private function checkQualification(float $perf, float $skill, float $cs, float $total): bool
    {
        return $total >= 70 && $perf > 0 && $skill > 0 && $cs > 0;
    }

    /**
     * Retorna o status detalhado de qualificação
     */
    private function getQualificationStatus(float $perf, float $skill, float $cs, float $total): array
    {
        $issues = [];
        
        if ($total < 70) {
            $issues[] = [
                'type' => 'score',
                'message' => "Score total de {$total}/100 está abaixo do mínimo de 70 pontos",
                'gap' => 70 - $total
            ];
        }
        
        if ($perf <= 0) {
            $issues[] = [
                'type' => 'metric_zero',
                'metric' => 'performance',
                'message' => 'Performance está zerada - precisa ter pelo menos 1 cliente qualificado'
            ];
        }
        
        if ($skill <= 0) {
            $issues[] = [
                'type' => 'metric_zero',
                'metric' => 'skilling',
                'message' => 'Skilling está zerado - precisa ter pelo menos 1 certificação válida'
            ];
        }
        
        if ($cs <= 0) {
            $issues[] = [
                'type' => 'metric_zero',
                'metric' => 'customerSuccess',
                'message' => 'Customer Success está zerado - precisa ter pelo menos 1 deployment qualificado'
            ];
        }
        
        return [
            'qualified' => empty($issues),
            'issues' => $issues,
            'blockers' => array_filter($issues, fn($i) => $i['type'] === 'metric_zero'),
            'scoreGap' => max(0, 70 - $total)
        ];
    }

    /**
     * Determina a trilha do parceiro (Enterprise ou SMB)
     */
    private function determineTrack(): string
    {
        $revenue = $this->partnerData['cspRevenue'] ?? '';
        $clientCount = $this->partnerData['clientCount'] ?? '';
        
        // Lógica simplificada - em produção, seria mais sofisticada
        if ($revenue === 'R$ 1M+' || $clientCount === '50+') {
            return 'Enterprise';
        }
        
        return 'SMB';
    }

    /**
     * Identifica gaps entre estado atual e requisitos
     */
    private function identifyGaps(array $analysis): array
    {
        $gaps = [];
        $scores = $analysis['scores'];
        
        // Gap de Performance
        if ($scores['performance']['current'] < $this->pcsMaxScores['performance'] * 0.5) {
            $gaps['performance'] = [
                'severity' => $this->calculateSeverity($scores['performance']['percentage']),
                'current' => $scores['performance']['current'],
                'target' => $this->pcsMaxScores['performance'] * 0.5, // 50% do máximo como meta inicial
                'gap' => max(0, ($this->pcsMaxScores['performance'] * 0.5) - $scores['performance']['current']),
                'details' => $this->getPerformanceGapDetails($scores['performance']['current'])
            ];
        }
        
        // Gap de Skilling (mais crítico pois é mais controlável)
        if ($scores['skilling']['current'] < $this->pcsMaxScores['skilling'] * 0.6) {
            $gaps['skilling'] = [
                'severity' => $this->calculateSeverity($scores['skilling']['percentage']),
                'current' => $scores['skilling']['current'],
                'target' => $this->pcsMaxScores['skilling'] * 0.6,
                'gap' => max(0, ($this->pcsMaxScores['skilling'] * 0.6) - $scores['skilling']['current']),
                'details' => $this->getSkillingGapDetails($scores['skilling']['current'], $analysis['certifications'])
            ];
        }
        
        // Gap de Customer Success
        if ($scores['customerSuccess']['current'] < $this->pcsMaxScores['customerSuccess'] * 0.4) {
            $gaps['customerSuccess'] = [
                'severity' => $this->calculateSeverity($scores['customerSuccess']['percentage']),
                'current' => $scores['customerSuccess']['current'],
                'target' => $this->pcsMaxScores['customerSuccess'] * 0.4,
                'gap' => max(0, ($this->pcsMaxScores['customerSuccess'] * 0.4) - $scores['customerSuccess']['current']),
                'details' => $this->getCustomerSuccessGapDetails($scores['customerSuccess']['current'])
            ];
        }
        
        // Gap total para qualificação
        $totalGap = max(0, 70 - $scores['total']['current']);
        if ($totalGap > 0) {
            $gaps['qualification'] = [
                'severity' => $totalGap > 30 ? 'high' : ($totalGap > 15 ? 'medium' : 'low'),
                'current' => $scores['total']['current'],
                'target' => 70,
                'gap' => $totalGap,
                'distribution' => $this->suggestGapDistribution($totalGap, $scores)
            ];
        }
        
        return $gaps;
    }

    /**
     * Calcula a severidade do gap
     */
    private function calculateSeverity(float $percentage): string
    {
        if ($percentage < 25) return 'critical';
        if ($percentage < 50) return 'high';
        if ($percentage < 75) return 'medium';
        return 'low';
    }

    /**
     * Detalhes específicos do gap de Performance
     */
    private function getPerformanceGapDetails(float $current): array
    {
        $details = $this->metricDetails['performance'] ?? [];
        $track = $this->determineTrack();
        $trackDetails = $details[$track === 'Enterprise' ? 'enterprise' : 'smb'] ?? [];
        
        $pointsPerCustomer = $trackDetails['pointsPerCustomer'] ?? 10;
        $customersNeeded = $current > 0 ? 0 : ceil(1 / $pointsPerCustomer);
        
        return [
            'metric' => $details['metric'] ?? 'Net customer adds',
            'description' => $details['description'] ?? '',
            'track' => $track,
            'customersNeeded' => max(1, $customersNeeded),
            'pointsPerCustomer' => $pointsPerCustomer,
            'workloads' => $this->getRelevantWorkloads()
        ];
    }

    /**
     * Detalhes específicos do gap de Skilling
     */
    private function getSkillingGapDetails(float $current, array $certifications): array
    {
        $intermediate = $this->skillingRules['intermediate'] ?? [];
        $advanced = $this->skillingRules['advanced'] ?? [];
        
        // Certificações recomendadas que o parceiro ainda não tem
        $recommended = [];
        $existingCerts = $certifications['list'] ?? [];
        
        foreach ($intermediate as $cert) {
            if (is_string($cert) && !in_array($cert, $existingCerts)) {
                $recommended[] = [
                    'name' => $cert,
                    'level' => 'intermediate',
                    'priority' => strpos($cert, 'pré-requisito') !== false ? 'high' : 'medium'
                ];
            }
        }
        
        foreach ($advanced as $cert) {
            if (is_string($cert) && !in_array($cert, $existingCerts)) {
                $recommended[] = [
                    'name' => $cert,
                    'level' => 'advanced',
                    'priority' => strpos($cert, 'pré-requisito') !== false ? 'high' : 'medium'
                ];
            }
        }
        
        return [
            'currentCertCount' => $certifications['count'] ?? 0,
            'recommendedCertifications' => array_slice($recommended, 0, 5), // Top 5
            'resources' => $this->skillingRules['resources'] ?? [],
            'specializations' => $this->skillingRules['specializations'] ?? []
        ];
    }

    /**
     * Detalhes específicos do gap de Customer Success
     */
    private function getCustomerSuccessGapDetails(float $current): array
    {
        $csDetails = $this->metricDetails['customerSuccess'] ?? [];
        
        return [
            'usageGrowth' => [
                'max' => $csDetails['usageGrowth']['max'] ?? 20,
                'description' => $csDetails['usageGrowth']['description'] ?? 'Usage growth across workloads'
            ],
            'deployments' => [
                'max' => $csDetails['deployments']['max'] ?? 10,
                'description' => $csDetails['deployments']['description'] ?? 'Net new deployments'
            ],
            'recommendations' => [
                'Implementar programa de adoção com clientes existentes',
                'Focar em deployments com 40%+ de uso ativo',
                'Utilizar FastTrack resources quando disponível'
            ]
        ];
    }

    /**
     * Retorna workloads relevantes para a área de solução
     */
    private function getRelevantWorkloads(): array
    {
        $workloadsByArea = [
            'Infrastructure' => ['Azure VMs', 'Azure Storage', 'Azure Networking', 'Azure Arc', 'Azure VMware Solution'],
            'Modern Work' => ['Exchange Online', 'Microsoft Teams', 'SharePoint', 'Microsoft Intune', 'Microsoft Viva'],
            'Security' => ['Microsoft Defender', 'Microsoft Sentinel', 'Entra ID', 'Purview'],
            'Data & AI' => ['Azure SQL', 'Cosmos DB', 'Azure AI Services', 'Microsoft Fabric', 'Power BI'],
            'Digital & App Innovation' => ['Azure App Service', 'Azure Kubernetes', 'Azure Functions', 'Power Platform'],
            'Business Applications' => ['Dynamics 365 Sales', 'Dynamics 365 Customer Service', 'Power Apps', 'Power Automate']
        ];
        
        return $workloadsByArea[$this->solutionArea] ?? [];
    }

    /**
     * Sugere como distribuir o gap entre as métricas
     */
    private function suggestGapDistribution(float $totalGap, array $scores): array
    {
        $distribution = [];
        
        // Priorizar skilling (mais controlável)
        $skillingRoom = $this->pcsMaxScores['skilling'] - $scores['skilling']['current'];
        $skillingContribution = min($skillingRoom, $totalGap * 0.5);
        
        if ($skillingContribution > 0) {
            $distribution['skilling'] = [
                'points' => round($skillingContribution, 1),
                'percentage' => round(($skillingContribution / $totalGap) * 100),
                'priority' => 1
            ];
        }
        
        // Performance em segundo
        $perfRoom = $this->pcsMaxScores['performance'] - $scores['performance']['current'];
        $remaining = $totalGap - $skillingContribution;
        $perfContribution = min($perfRoom, $remaining * 0.6);
        
        if ($perfContribution > 0) {
            $distribution['performance'] = [
                'points' => round($perfContribution, 1),
                'percentage' => round(($perfContribution / $totalGap) * 100),
                'priority' => 2
            ];
        }
        
        // Customer Success por último
        $csRoom = $this->pcsMaxScores['customerSuccess'] - $scores['customerSuccess']['current'];
        $csContribution = min($csRoom, $totalGap - $skillingContribution - $perfContribution);
        
        if ($csContribution > 0) {
            $distribution['customerSuccess'] = [
                'points' => round($csContribution, 1),
                'percentage' => round(($csContribution / $totalGap) * 100),
                'priority' => 3
            ];
        }
        
        return $distribution;
    }

    /**
     * Gera ações baseadas nos gaps identificados
     */
    private function generateActions(array $gaps): array
    {
        $actions = [];
        
        // Ações de Skilling
        if (isset($gaps['skilling'])) {
            $skillingDetails = $gaps['skilling']['details'] ?? [];
            $certifications = $skillingDetails['recommendedCertifications'] ?? [];
            
            foreach ($certifications as $cert) {
                $actions[] = [
                    'id' => 'cert_' . md5($cert['name']),
                    'type' => 'certification',
                    'category' => 'skilling',
                    'title' => 'Obter certificação: ' . $this->extractCertName($cert['name']),
                    'description' => $cert['name'],
                    'level' => $cert['level'],
                    'priority' => $cert['priority'],
                    'impact' => $this->estimateCertImpact($cert['level']),
                    'effort' => $cert['level'] === 'advanced' ? 'high' : 'medium',
                    'duration' => $cert['level'] === 'advanced' 
                        ? self::ACTION_DURATION['certification_expert']
                        : self::ACTION_DURATION['certification_associate'],
                    'resources' => $this->getCertificationResources($cert['name'])
                ];
            }
            
            // Adicionar workshops/bootcamps
            foreach ($skillingDetails['resources'] ?? [] as $resource) {
                $actions[] = [
                    'id' => 'resource_' . md5($resource),
                    'type' => 'training',
                    'category' => 'skilling',
                    'title' => 'Participar: ' . $resource,
                    'description' => 'Treinamento TD SYNNEX para acelerar capacitação',
                    'priority' => 'medium',
                    'impact' => 'medium',
                    'effort' => 'low',
                    'duration' => self::ACTION_DURATION['workshop']
                ];
            }
        }
        
        // Ações de Performance
        if (isset($gaps['performance'])) {
            $perfDetails = $gaps['performance']['details'] ?? [];
            
            $actions[] = [
                'id' => 'perf_customers',
                'type' => 'customer_acquisition',
                'category' => 'performance',
                'title' => 'Adicionar ' . ($perfDetails['customersNeeded'] ?? 1) . ' cliente(s) qualificado(s)',
                'description' => 'Clientes com workloads ativos em: ' . implode(', ', array_slice($perfDetails['workloads'] ?? [], 0, 3)),
                'priority' => $gaps['performance']['severity'] === 'critical' ? 'high' : 'medium',
                'impact' => 'high',
                'effort' => 'high',
                'duration' => self::ACTION_DURATION['customer_acquisition'],
                'tips' => [
                    'Foque em clientes com potencial de adoção de múltiplos workloads',
                    'Utilize campanhas de geração de demanda TD SYNNEX',
                    'Considere migração de clientes existentes para workloads qualificados'
                ]
            ];
        }
        
        // Ações de Customer Success
        if (isset($gaps['customerSuccess'])) {
            $csDetails = $gaps['customerSuccess']['details'] ?? [];
            
            $actions[] = [
                'id' => 'cs_deployments',
                'type' => 'deployment',
                'category' => 'customerSuccess',
                'title' => 'Completar deployments qualificados',
                'description' => $csDetails['deployments']['description'] ?? 'Net new deployments com uso ativo',
                'priority' => 'medium',
                'impact' => 'medium',
                'effort' => 'medium',
                'duration' => self::ACTION_DURATION['customer_deployment'],
                'tips' => $csDetails['recommendations'] ?? []
            ];
            
            $actions[] = [
                'id' => 'cs_usage',
                'type' => 'usage_growth',
                'category' => 'customerSuccess',
                'title' => 'Aumentar usage growth em clientes existentes',
                'description' => $csDetails['usageGrowth']['description'] ?? 'Growth in usage across workloads',
                'priority' => 'low',
                'impact' => 'medium',
                'effort' => 'medium',
                'duration' => self::ACTION_DURATION['usage_growth'],
                'tips' => [
                    'Implementar programa de adoção estruturado',
                    'Monitorar métricas de uso mensalmente',
                    'Oferecer treinamento end-user para clientes'
                ]
            ];
        }
        
        return $actions;
    }

    /**
     * Extrai o nome curto da certificação
     */
    private function extractCertName(string $fullName): string
    {
        // Extrai código da certificação (ex: "AZ-104" de "AZ-104: Azure Administrator")
        if (preg_match('/^([A-Z]{2,3}-\d{3})/', $fullName, $matches)) {
            return $matches[1];
        }
        return substr($fullName, 0, 30);
    }

    /**
     * Estima o impacto de uma certificação
     */
    private function estimateCertImpact(string $level): string
    {
        return match($level) {
            'advanced' => 'high',
            'intermediate' => 'medium',
            default => 'low'
        };
    }

    /**
     * Retorna recursos para obter uma certificação
     */
    private function getCertificationResources(string $certName): array
    {
        $code = $this->extractCertName($certName);
        
        return [
            [
                'type' => 'learn',
                'title' => 'Microsoft Learn Path',
                'url' => "https://learn.microsoft.com/certifications/{$code}"
            ],
            [
                'type' => 'practice',
                'title' => 'Practice Assessment',
                'url' => "https://learn.microsoft.com/certifications/{$code}/practice"
            ]
        ];
    }

    /**
     * Prioriza ações baseado em impacto, esforço e dependências
     */
    private function prioritizeActions(array $actions): array
    {
        // Scoring para priorização
        $scoredActions = array_map(function($action) {
            $score = 0;
            
            // Impact score
            $score += match($action['impact'] ?? 'medium') {
                'high' => 30,
                'medium' => 20,
                'low' => 10,
                default => 15
            };
            
            // Effort score (inverse - less effort = higher priority)
            $score += match($action['effort'] ?? 'medium') {
                'low' => 25,
                'medium' => 15,
                'high' => 5,
                default => 15
            };
            
            // Priority boost
            $score += match($action['priority'] ?? 'medium') {
                'high' => 20,
                'medium' => 10,
                'low' => 5,
                default => 10
            };
            
            // Category weight
            $categoryWeight = self::PRIORITY_WEIGHTS[$action['category'] ?? 'skilling'] ?? 1;
            $score *= $categoryWeight;
            
            $action['priorityScore'] = $score;
            return $action;
        }, $actions);
        
        // Ordenar por score (maior primeiro)
        usort($scoredActions, fn($a, $b) => $b['priorityScore'] <=> $a['priorityScore']);
        
        // Adicionar ordem de prioridade
        foreach ($scoredActions as $index => &$action) {
            $action['order'] = $index + 1;
        }
        
        return $scoredActions;
    }

    /**
     * Cria timeline com as ações
     */
    private function createTimeline(array $actions): array
    {
        $timeline = [];
        $currentWeek = 0;
        
        foreach ($actions as $action) {
            $duration = $action['duration'] ?? 4;
            $startWeek = $currentWeek;
            $endWeek = $currentWeek + $duration;
            
            $timeline[] = [
                'actionId' => $action['id'],
                'title' => $action['title'],
                'category' => $action['category'],
                'startWeek' => $startWeek,
                'endWeek' => $endWeek,
                'duration' => $duration,
                'parallel' => $action['category'] !== 'performance' // Certificações podem ser paralelas
            ];
            
            // Ações de performance são sequenciais, outras podem ser paralelas
            if ($action['category'] === 'performance' || $action['type'] === 'certification') {
                $currentWeek = $endWeek;
            }
        }
        
        return $timeline;
    }

    /**
     * Define milestones do roadmap
     */
    private function defineMilestones(array $timeline): array
    {
        $totalWeeks = max(array_column($timeline, 'endWeek')) ?: 12;
        
        $milestones = [
            [
                'id' => 'start',
                'title' => 'Início do Roadmap',
                'week' => 0,
                'type' => 'start',
                'description' => 'Kickoff do plano de qualificação'
            ]
        ];
        
        // Milestone de 25%
        if ($totalWeeks >= 4) {
            $milestones[] = [
                'id' => 'q1',
                'title' => 'Primeira Certificação',
                'week' => round($totalWeeks * 0.25),
                'type' => 'checkpoint',
                'description' => 'Completar primeira certificação pré-requisito'
            ];
        }
        
        // Milestone de 50%
        if ($totalWeeks >= 8) {
            $milestones[] = [
                'id' => 'q2',
                'title' => 'Primeiro Cliente Qualificado',
                'week' => round($totalWeeks * 0.5),
                'type' => 'checkpoint',
                'description' => 'Adicionar primeiro cliente com workload ativo'
            ];
        }
        
        // Milestone de 75%
        if ($totalWeeks >= 12) {
            $milestones[] = [
                'id' => 'q3',
                'title' => 'Todas Métricas > 0',
                'week' => round($totalWeeks * 0.75),
                'type' => 'checkpoint',
                'description' => 'Garantir pontuação em todas as métricas PCS'
            ];
        }
        
        // Milestone final
        $milestones[] = [
            'id' => 'qualification',
            'title' => 'Solutions Partner Designation',
            'week' => $totalWeeks,
            'type' => 'goal',
            'description' => 'Atingir 70+ pontos com todas métricas > 0'
        ];
        
        return $milestones;
    }

    /**
     * Gera sumário executivo
     */
    private function generateSummary(array $analysis, array $gaps): array
    {
        $isQualified = $analysis['isQualified'];
        $scoreGap = $analysis['qualificationStatus']['scoreGap'];
        $blockers = $analysis['qualificationStatus']['blockers'];
        
        if ($isQualified) {
            return [
                'status' => 'qualified',
                'headline' => 'Parabéns! Você está qualificado como Solutions Partner',
                'message' => "Seu score de {$analysis['scores']['total']['current']}/100 atende aos requisitos. Mantenha as métricas e explore especializações.",
                'nextSteps' => [
                    'Considerar especializações para diferenciar sua empresa',
                    'Aumentar pontuação para maior visibilidade no Marketplace',
                    'Explorar benefícios de go-to-market disponíveis'
                ]
            ];
        }
        
        $severity = count($blockers) > 1 ? 'critical' : ($scoreGap > 30 ? 'high' : 'medium');
        
        return [
            'status' => 'not_qualified',
            'severity' => $severity,
            'headline' => $this->generateHeadline($analysis, $gaps),
            'message' => $this->generateMessage($analysis, $gaps),
            'keyMetrics' => [
                'scoreGap' => $scoreGap,
                'blockersCount' => count($blockers),
                'strongestArea' => $this->identifyStrongestArea($analysis['scores']),
                'weakestArea' => $this->identifyWeakestArea($analysis['scores'])
            ],
            'nextSteps' => $this->generateNextSteps($gaps)
        ];
    }

    /**
     * Gera headline personalizado
     */
    private function generateHeadline(array $analysis, array $gaps): string
    {
        $scoreGap = $analysis['qualificationStatus']['scoreGap'];
        $blockers = $analysis['qualificationStatus']['blockers'];
        
        if (count($blockers) >= 2) {
            return "Você precisa ativar " . count($blockers) . " métricas e ganhar {$scoreGap} pontos";
        }
        
        if ($scoreGap <= 10) {
            return "Você está muito perto! Faltam apenas {$scoreGap} pontos";
        }
        
        if ($scoreGap <= 30) {
            return "Bom progresso! Faltam {$scoreGap} pontos para qualificar";
        }
        
        return "Vamos começar! Seu roadmap para Solutions Partner";
    }

    /**
     * Gera mensagem personalizada
     */
    private function generateMessage(array $analysis, array $gaps): string
    {
        $scores = $analysis['scores'];
        $weakest = $this->identifyWeakestArea($scores);
        
        $messages = [
            'performance' => "Foque em adicionar clientes com workloads qualificados em {$this->solutionArea}.",
            'skilling' => "Priorize as certificações - é a forma mais rápida de ganhar pontos.",
            'customerSuccess' => "Trabalhe a adoção nos clientes existentes para aumentar usage growth."
        ];
        
        return $messages[$weakest] ?? "Siga o roadmap abaixo para alcançar a designação Solutions Partner.";
    }

    /**
     * Identifica a área mais forte
     */
    private function identifyStrongestArea(array $scores): string
    {
        $areas = ['performance', 'skilling', 'customerSuccess'];
        $strongest = 'skilling';
        $highestPct = 0;
        
        foreach ($areas as $area) {
            $pct = $scores[$area]['percentage'] ?? 0;
            if ($pct > $highestPct) {
                $highestPct = $pct;
                $strongest = $area;
            }
        }
        
        return $strongest;
    }

    /**
     * Identifica a área mais fraca
     */
    private function identifyWeakestArea(array $scores): string
    {
        $areas = ['performance', 'skilling', 'customerSuccess'];
        $weakest = 'skilling';
        $lowestPct = 100;
        
        foreach ($areas as $area) {
            $pct = $scores[$area]['percentage'] ?? 0;
            if ($pct < $lowestPct) {
                $lowestPct = $pct;
                $weakest = $area;
            }
        }
        
        return $weakest;
    }

    /**
     * Gera próximos passos baseados nos gaps
     */
    private function generateNextSteps(array $gaps): array
    {
        $steps = [];
        
        // Prioridade 1: Métricas zeradas
        if (isset($gaps['skilling']) && $gaps['skilling']['current'] <= 0) {
            $steps[] = '1️⃣ Iniciar primeira certificação pré-requisito';
        }
        
        if (isset($gaps['performance']) && $gaps['performance']['current'] <= 0) {
            $steps[] = '2️⃣ Identificar primeiro cliente qualificado';
        }
        
        if (isset($gaps['customerSuccess']) && $gaps['customerSuccess']['current'] <= 0) {
            $steps[] = '3️⃣ Completar primeiro deployment qualificado';
        }
        
        // Prioridade 2: Aumentar scores
        if (empty($steps)) {
            $steps = [
                '1️⃣ Completar certificações recomendadas',
                '2️⃣ Adicionar novos clientes qualificados',
                '3️⃣ Aumentar uso em clientes existentes'
            ];
        }
        
        return $steps;
    }

    /**
     * Gera recomendações personalizadas
     */
    private function generateRecommendations(array $gaps): array
    {
        $recommendations = [];
        
        // Recomendações de Skilling
        if (isset($gaps['skilling'])) {
            $recommendations[] = [
                'category' => 'skilling',
                'title' => 'Acelere com Bootcamps TD SYNNEX',
                'description' => 'Participe dos bootcamps gratuitos para preparação de certificações',
                'action' => 'Ver calendário de treinamentos',
                'priority' => 'high'
            ];
        }
        
        // Recomendações de Performance
        if (isset($gaps['performance'])) {
            $recommendations[] = [
                'category' => 'performance',
                'title' => 'Utilize Campanhas de Geração de Demanda',
                'description' => 'Acesse materiais de marketing e listas segmentadas para prospecção',
                'action' => 'Acessar Partner Marketing Center',
                'priority' => 'medium'
            ];
        }
        
        // Recomendações de Customer Success
        if (isset($gaps['customerSuccess'])) {
            $recommendations[] = [
                'category' => 'customerSuccess',
                'title' => 'Programe Adoção Estruturada',
                'description' => 'Use frameworks de adoption para aumentar usage nos clientes',
                'action' => 'Baixar kit de adoção',
                'priority' => 'medium'
            ];
        }
        
        // Recomendação geral
        $recommendations[] = [
            'category' => 'general',
            'title' => 'Agende Consultoria com PDM',
            'description' => 'Seu Partner Development Manager pode ajudar a acelerar sua jornada',
            'action' => 'Solicitar reunião',
            'priority' => 'high'
        ];
        
        return $recommendations;
    }

    /**
     * Estima tempo total para qualificação
     */
    private function estimateTimeToQualify(array $actions): array
    {
        $totalWeeks = 0;
        $parallelWeeks = 0;
        
        foreach ($actions as $action) {
            $duration = $action['duration'] ?? 4;
            
            if ($action['category'] === 'skilling') {
                $parallelWeeks = max($parallelWeeks, $duration);
            } else {
                $totalWeeks += $duration;
            }
        }
        
        $estimatedWeeks = $totalWeeks + $parallelWeeks;
        $estimatedMonths = ceil($estimatedWeeks / 4);
        
        return [
            'weeks' => $estimatedWeeks,
            'months' => $estimatedMonths,
            'range' => [
                'optimistic' => max(4, $estimatedWeeks - 4),
                'realistic' => $estimatedWeeks,
                'conservative' => $estimatedWeeks + 8
            ],
            'factors' => [
                'Disponibilidade da equipe para certificações',
                'Pipeline atual de oportunidades',
                'Clientes existentes com potencial de ativação'
            ]
        ];
    }

    /**
     * Identifica quick wins (ações de alto impacto e baixo esforço)
     */
    private function identifyQuickWins(array $actions): array
    {
        return array_filter($actions, function($action) {
            return ($action['impact'] === 'high' || $action['impact'] === 'medium') 
                && $action['effort'] === 'low';
        });
    }
}
