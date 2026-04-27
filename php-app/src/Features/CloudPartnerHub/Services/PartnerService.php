<?php
/**
 * Cloud Partner Hub - Serviço de Parceiros
 * Gerencia os dados dos parceiros (mock + sessão)
 */

declare(strict_types=1);

namespace Features\CloudPartnerHub\Services;

require_once __DIR__ . '/../Config/constants.php';

use Features\CloudPartnerHub\Config\Constants;

class PartnerService
{
    private array $partners = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Inicializa com dados mock se não houver dados na sessão
        if (!isset($_SESSION['cloud_partner_hub_partners'])) {
            $_SESSION['cloud_partner_hub_partners'] = $this->getMockPartners();
        }

        $this->partners = $_SESSION['cloud_partner_hub_partners'];
    }

    /**
     * Retorna todos os parceiros
     */
    public function getAll(): array
    {
        return array_map([$this, 'enrichPartner'], $this->partners);
    }

    /**
     * Busca parceiro por ID
     */
    public function getById(string $id): ?array
    {
        foreach ($this->partners as $partner) {
            if ($partner['id'] === $id) {
                return $this->enrichPartner($partner);
            }
        }
        return null;
    }

    /**
     * Cria um novo parceiro
     */
    public function create(array $data): array
    {
        $id = 'partner_' . time() . '_' . bin2hex(random_bytes(4));
        
        $partner = [
            'id' => $id,
            'companyName' => $data['companyName'] ?? '',
            'contactName' => $data['contactName'] ?? '',
            'contactPhone' => $data['contactPhone'] ?? '',
            'contactRole' => $data['contactRole'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'mpnId' => $data['mpnId'] ?? '',
            'isMicrosoftPartner' => $data['isMicrosoftPartner'] ?? false,
            'isTdSynnexRegistered' => $data['isTdSynnexRegistered'] ?? false,
            'solutionArea' => $data['solutionArea'] ?? '',
            'pcsPerformance' => (int)($data['pcsPerformance'] ?? 0),
            'pcsSkilling' => (int)($data['pcsSkilling'] ?? 0),
            'pcsCustomerSuccess' => (int)($data['pcsCustomerSuccess'] ?? 0),
            'revenueM365' => (float)($data['revenueM365'] ?? 0),
            'revenueAzure' => (float)($data['revenueAzure'] ?? 0),
            'revenueSecurity' => (float)($data['revenueSecurity'] ?? 0),
            'certifications' => $data['certifications'] ?? [],
            'currentStep' => (int)($data['currentStep'] ?? 1),
            'status' => $data['status'] ?? 'In Progress',
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        $this->partners[] = $partner;
        $this->save();

        return $this->enrichPartner($partner);
    }

    /**
     * Atualiza um parceiro existente
     */
    public function update(string $id, array $data): ?array
    {
        foreach ($this->partners as &$partner) {
            if ($partner['id'] === $id) {
                // Atualiza os campos fornecidos
                foreach ($data as $key => $value) {
                    if ($key !== 'id' && array_key_exists($key, $partner)) {
                        $partner[$key] = $value;
                    }
                }
                $this->save();
                return $this->enrichPartner($partner);
            }
        }
        return null;
    }

    /**
     * Remove um parceiro
     */
    public function delete(string $id): bool
    {
        foreach ($this->partners as $index => $partner) {
            if ($partner['id'] === $id) {
                array_splice($this->partners, $index, 1);
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Busca parceiros com filtros
     */
    public function search(string $term = '', string $stage = 'all', string $area = 'all'): array
    {
        $results = $this->partners;

        // Filtro por termo de busca
        if ($term !== '') {
            $termLower = strtolower($term);
            $results = array_filter($results, function($p) use ($termLower) {
                return str_contains(strtolower($p['companyName'] ?? ''), $termLower) ||
                       str_contains(strtolower($p['email'] ?? ''), $termLower) ||
                       str_contains(strtolower($p['contactName'] ?? ''), $termLower);
            });
        }

        // Filtro por área de solução
        if ($area !== 'all') {
            $results = array_filter($results, fn($p) => ($p['solutionArea'] ?? '') === $area);
        }

        // Enriquecer dados
        $results = array_map([$this, 'enrichPartner'], $results);

        // Filtro por estágio (após enriquecimento)
        if ($stage !== 'all') {
            $results = array_filter($results, fn($p) => ($p['edgeStage'] ?? '') === $stage);
        }

        return array_values($results);
    }

    /**
     * Retorna estatísticas dos parceiros
     */
    public function getStats(): array
    {
        $enriched = array_map([$this, 'enrichPartner'], $this->partners);
        $total = count($enriched);

        if ($total === 0) {
            return [
                'totalPartners' => 0,
                'totalRevenue' => 0,
                'avgPCS' => 0,
                'byStage' => ['Engage' => 0, 'Develop' => 0, 'Growth' => 0, 'Extend' => 0],
                'byArea' => [],
                'byStatus' => ['In Progress' => 0, 'Completed' => 0, 'Stalled' => 0],
            ];
        }

        $totalRevenue = array_sum(array_map(fn($p) => $p['totalRevenue'] ?? 0, $enriched));
        $avgPCS = array_sum(array_map(fn($p) => $p['pcsScore'] ?? 0, $enriched)) / $total;

        $byStage = ['Engage' => 0, 'Develop' => 0, 'Growth' => 0, 'Extend' => 0];
        $byArea = [];
        $byStatus = ['In Progress' => 0, 'Completed' => 0, 'Stalled' => 0];

        foreach ($enriched as $p) {
            $stage = $p['edgeStage'] ?? 'Engage';
            $area = $p['solutionArea'] ?? 'Não definido';
            $status = $p['status'] ?? 'In Progress';

            if (isset($byStage[$stage])) $byStage[$stage]++;
            if (!isset($byArea[$area])) $byArea[$area] = 0;
            $byArea[$area]++;
            if (isset($byStatus[$status])) $byStatus[$status]++;
        }

        return [
            'totalPartners' => $total,
            'totalRevenue' => $totalRevenue,
            'avgPCS' => round($avgPCS, 1),
            'byStage' => $byStage,
            'byArea' => $byArea,
            'byStatus' => $byStatus,
        ];
    }

    /**
     * Enriquece os dados do parceiro com campos calculados
     */
    private function enrichPartner(array $partner): array
    {
        $pcsScore = ($partner['pcsPerformance'] ?? 0) + 
                    ($partner['pcsSkilling'] ?? 0) + 
                    ($partner['pcsCustomerSuccess'] ?? 0);

        $totalRevenue = ($partner['revenueM365'] ?? 0) + 
                        ($partner['revenueAzure'] ?? 0) + 
                        ($partner['revenueSecurity'] ?? 0);

        $partner['pcsScore'] = $pcsScore;
        $partner['edgeStage'] = Constants::calculateEdgeStage($pcsScore);
        $partner['totalRevenue'] = $totalRevenue;

        return $partner;
    }

    /**
     * Salva os dados na sessão
     */
    private function save(): void
    {
        $_SESSION['cloud_partner_hub_partners'] = $this->partners;
    }

    /**
     * Dados mock iniciais
     */
    private function getMockPartners(): array
    {
        return [
            [
                'id' => '1',
                'companyName' => 'TechSolutions Brasil',
                'contactName' => 'Carlos Silva',
                'contactPhone' => '+55 11 98765-4321',
                'contactRole' => 'Diretor Comercial',
                'email' => 'carlos@techsolutions.com.br',
                'phone' => '+55 11 98765-4321',
                'mpnId' => 'MPN123456',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => true,
                'solutionArea' => 'Azure Infra',
                'pcsPerformance' => 25,
                'pcsSkilling' => 30,
                'pcsCustomerSuccess' => 20,
                'revenueM365' => 50000,
                'revenueAzure' => 120000,
                'revenueSecurity' => 30000,
                'certifications' => ['AZ-104', 'AZ-305', 'AZ-500'],
                'currentStep' => 5,
                'status' => 'In Progress',
                'createdAt' => '2024-01-15'
            ],
            [
                'id' => '2',
                'companyName' => 'InnovaTech',
                'contactName' => 'Maria Santos',
                'contactPhone' => '+55 21 91234-5678',
                'contactRole' => 'CTO',
                'email' => 'maria@innovatech.com.br',
                'phone' => '+55 21 91234-5678',
                'mpnId' => 'MPN789012',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => true,
                'solutionArea' => 'Modern Work',
                'pcsPerformance' => 18,
                'pcsSkilling' => 22,
                'pcsCustomerSuccess' => 42,
                'revenueM365' => 180000,
                'revenueAzure' => 45000,
                'revenueSecurity' => 25000,
                'certifications' => ['MS-900', 'MS-102', 'MS-700'],
                'currentStep' => 6,
                'status' => 'Completed',
                'createdAt' => '2023-11-20'
            ],
            [
                'id' => '3',
                'companyName' => 'SecureIT Solutions',
                'contactName' => 'João Oliveira',
                'contactPhone' => '+55 11 93456-7890',
                'contactRole' => 'Gerente de Segurança',
                'email' => 'joao@secureit.com.br',
                'phone' => '+55 11 93456-7890',
                'mpnId' => 'MPN345678',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => false,
                'solutionArea' => 'Security',
                'pcsPerformance' => 18,
                'pcsSkilling' => 32,
                'pcsCustomerSuccess' => 18,
                'revenueM365' => 35000,
                'revenueAzure' => 40000,
                'revenueSecurity' => 95000,
                'certifications' => ['SC-200', 'SC-300', 'SC-400'],
                'currentStep' => 4,
                'status' => 'In Progress',
                'createdAt' => '2024-02-10'
            ],
            [
                'id' => '4',
                'companyName' => 'CloudMasters',
                'contactName' => 'Ana Costa',
                'contactPhone' => '+55 31 92345-6789',
                'contactRole' => 'Arquiteta de Soluções',
                'email' => 'ana@cloudmasters.com.br',
                'phone' => '+55 31 92345-6789',
                'mpnId' => 'MPN901234',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => true,
                'solutionArea' => 'Azure Infra',
                'pcsPerformance' => 20,
                'pcsSkilling' => 20,
                'pcsCustomerSuccess' => 15,
                'revenueM365' => 25000,
                'revenueAzure' => 65000,
                'revenueSecurity' => 15000,
                'certifications' => ['AZ-900', 'AZ-104'],
                'currentStep' => 3,
                'status' => 'In Progress',
                'createdAt' => '2024-03-05'
            ],
            [
                'id' => '5',
                'companyName' => 'BizApps Pro',
                'contactName' => 'Pedro Almeida',
                'contactPhone' => '+55 41 94567-8901',
                'contactRole' => 'Consultor Dynamics',
                'email' => 'pedro@bizappspro.com.br',
                'phone' => '+55 41 94567-8901',
                'mpnId' => 'MPN567890',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => false,
                'solutionArea' => 'Business Applications',
                'pcsPerformance' => 10,
                'pcsSkilling' => 20,
                'pcsCustomerSuccess' => 15,
                'revenueM365' => 20000,
                'revenueAzure' => 10000,
                'revenueSecurity' => 5000,
                'certifications' => ['PL-200', 'MB-210'],
                'currentStep' => 2,
                'status' => 'Stalled',
                'createdAt' => '2024-04-12'
            ],
            [
                'id' => '6',
                'companyName' => 'DataFlow Systems',
                'contactName' => 'Carla Mendes',
                'contactPhone' => '+55 51 95678-9012',
                'contactRole' => 'Head de Data',
                'email' => 'carla@dataflow.com.br',
                'phone' => '+55 51 95678-9012',
                'mpnId' => 'MPN234567',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => true,
                'solutionArea' => 'Data & AI',
                'pcsPerformance' => 28,
                'pcsSkilling' => 35,
                'pcsCustomerSuccess' => 25,
                'revenueM365' => 75000,
                'revenueAzure' => 250000,
                'revenueSecurity' => 45000,
                'certifications' => ['DP-100', 'DP-203', 'AI-102', 'AZ-305'],
                'currentStep' => 6,
                'status' => 'Completed',
                'createdAt' => '2023-09-01'
            ],
            [
                'id' => '7',
                'companyName' => 'DevOps Masters',
                'contactName' => 'Ricardo Lima',
                'contactPhone' => '+55 11 96789-0123',
                'contactRole' => 'DevOps Lead',
                'email' => 'ricardo@devopsmasters.com.br',
                'phone' => '+55 11 96789-0123',
                'mpnId' => 'MPN678901',
                'isMicrosoftPartner' => true,
                'isTdSynnexRegistered' => true,
                'solutionArea' => 'Digital & App Innovation',
                'pcsPerformance' => 26,
                'pcsSkilling' => 36,
                'pcsCustomerSuccess' => 22,
                'revenueM365' => 40000,
                'revenueAzure' => 180000,
                'revenueSecurity' => 20000,
                'certifications' => ['AZ-204', 'AZ-400', 'AZ-305'],
                'currentStep' => 5,
                'status' => 'In Progress',
                'createdAt' => '2024-01-28'
            ],
        ];
    }
}
