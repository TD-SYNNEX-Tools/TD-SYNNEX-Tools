<?php
/**
 * Cloud Partner Hub - API de Parceiros
 * Endpoints REST para gerenciamento de parceiros
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../src/Features/CloudPartnerHub/Services/PartnerService.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/benefits.php';
require_once __DIR__ . '/../src/Features/CloudPartnerHub/Config/constants.php';

use Features\CloudPartnerHub\Services\PartnerService;
use Features\CloudPartnerHub\Config\Benefits;
use Features\CloudPartnerHub\Config\Constants;

header('Content-Type: application/json; charset=utf-8');

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$service = new PartnerService();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

try {
    switch ($action) {
        // ========== PARCEIROS ==========
        
        case 'list':
            // GET - Listar todos os parceiros
            $search = $_GET['search'] ?? '';
            $stage = $_GET['stage'] ?? 'all';
            $area = $_GET['area'] ?? 'all';
            
            if ($search || $stage !== 'all' || $area !== 'all') {
                $partners = $service->search($search, $stage, $area);
            } else {
                $partners = $service->getAll();
            }
            
            echo json_encode(['success' => true, 'data' => $partners]);
            break;

        case 'get':
            // GET - Buscar parceiro por ID
            if (!$id) {
                throw new Exception('ID do parceiro é obrigatório');
            }
            
            $partner = $service->getById($id);
            if (!$partner) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Parceiro não encontrado']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $partner]);
            break;

        case 'create':
            // POST - Criar novo parceiro
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Dados inválidos');
            }
            
            $partner = $service->create($input);
            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $partner]);
            break;

        case 'update':
            // PUT - Atualizar parceiro
            if ($method !== 'PUT' && $method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            if (!$id) {
                throw new Exception('ID do parceiro é obrigatório');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Dados inválidos');
            }
            
            $partner = $service->update($id, $input);
            if (!$partner) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Parceiro não encontrado']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $partner]);
            break;

        case 'delete':
            // DELETE - Remover parceiro
            if ($method !== 'DELETE' && $method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            if (!$id) {
                throw new Exception('ID do parceiro é obrigatório');
            }
            
            $success = $service->delete($id);
            if (!$success) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Parceiro não encontrado']);
                exit;
            }
            
            echo json_encode(['success' => true, 'message' => 'Parceiro removido com sucesso']);
            break;

        case 'stats':
            // GET - Estatísticas dos parceiros
            $stats = $service->getStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        // ========== BENEFÍCIOS ==========

        case 'benefits':
            // GET - Listar benefícios
            $category = $_GET['category'] ?? 'all';
            $solutionArea = $_GET['solutionArea'] ?? 'all';
            $onlyUnlocked = ($_GET['onlyUnlocked'] ?? '0') === '1';
            $partnerId = $_GET['partnerId'] ?? '';
            
            $partner = null;
            if ($partnerId) {
                $partner = $service->getById($partnerId);
            }
            
            $benefits = Benefits::filterBenefits(
                $category !== 'all' ? $category : null,
                $solutionArea !== 'all' ? $solutionArea : null,
                $onlyUnlocked,
                $partner
            );
            
            // Adicionar info de desbloqueio se tiver parceiro
            if ($partner) {
                $benefits = array_map(function($b) use ($partner) {
                    $b['isUnlocked'] = Benefits::isBenefitUnlocked($b, $partner);
                    $b['progress'] = Benefits::getUnlockProgress($b, $partner);
                    return $b;
                }, $benefits);
            }
            
            echo json_encode(['success' => true, 'data' => $benefits]);
            break;

        case 'benefit-check':
            // GET - Verificar se benefício está desbloqueado
            $benefitId = $_GET['benefitId'] ?? '';
            $partnerId = $_GET['partnerId'] ?? '';
            
            if (!$benefitId || !$partnerId) {
                throw new Exception('benefitId e partnerId são obrigatórios');
            }
            
            $partner = $service->getById($partnerId);
            if (!$partner) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Parceiro não encontrado']);
                exit;
            }
            
            $benefit = null;
            foreach (Benefits::BENEFITS as $b) {
                if ($b['id'] === $benefitId) {
                    $benefit = $b;
                    break;
                }
            }
            
            if (!$benefit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Benefício não encontrado']);
                exit;
            }
            
            $isUnlocked = Benefits::isBenefitUnlocked($benefit, $partner);
            $progress = Benefits::getUnlockProgress($benefit, $partner);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'isUnlocked' => $isUnlocked,
                    'progress' => $progress
                ]
            ]);
            break;

        // ========== CONSTANTES ==========

        case 'constants':
            // GET - Retornar constantes do sistema
            echo json_encode([
                'success' => true,
                'data' => [
                    'solutionAreas' => Constants::SOLUTION_AREAS,
                    'edgeStages' => Constants::EDGE_STAGES,
                    'journeySteps' => Constants::JOURNEY_STEPS,
                    'benefitCategories' => Constants::BENEFIT_CATEGORIES,
                    'skillingRules' => Constants::SKILLING_RULES,
                    'pcsMaxScores' => Constants::PCS_MAX_SCORES,
                ]
            ]);
            break;

        case 'health':
            // GET - Health check
            echo json_encode([
                'success' => true,
                'status' => 'ok',
                'message' => 'Cloud Partner Hub API rodando',
                'timestamp' => date('c')
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ação não reconhecida',
                'availableActions' => [
                    'list', 'get', 'create', 'update', 'delete', 'stats',
                    'benefits', 'benefit-check', 'constants', 'health'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
