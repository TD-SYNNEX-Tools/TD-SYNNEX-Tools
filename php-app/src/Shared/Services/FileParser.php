<?php

declare(strict_types=1);

namespace App\Shared\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Classe responsável por ler e fazer parsing de arquivos Excel e CSV
 */
class FileParser
{
    private array $allowedExtensions = ['xlsx', 'xls', 'csv'];
    private array $requiredColumns = ['Resource Type'];
    private array $optionalColumns = ['Resource Name', 'Resource Group', 'Location', 'Subscription'];
    
    /**
     * Valida o arquivo enviado
     * 
     * @param array $file Array $_FILES do upload
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateFile(array $file): array
    {
        // Verifica se o arquivo foi enviado
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Nenhum arquivo foi enviado.'];
        }

        // Verifica erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false, 
                'error' => 'Formato de arquivo não suportado. Use: ' . implode(', ', $this->allowedExtensions)
            ];
        }

        // Verifica tamanho (máximo 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'O arquivo excede o tamanho máximo permitido (10MB).'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Faz o parsing do arquivo e retorna os dados
     * 
     * @param string $filePath Caminho do arquivo
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'columns' => array]
     */
    public function parseFile(string $filePath): array
    {
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if ($extension === 'csv') {
                return $this->parseCsv($filePath);
            }
            
            return $this->parseExcel($filePath);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Erro ao processar o arquivo: ' . $e->getMessage(),
                'columns' => []
            ];
        }
    }

    /**
     * Parse de arquivo CSV
     */
    private function parseCsv(string $filePath): array
    {
        $data = [];
        $headers = [];
        $row = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            // Detectar delimitador
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = $this->detectCsvDelimiter($firstLine);

            while (($rowData = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($row === 0) {
                    // Primeira linha = cabeçalhos
                    $headers = array_map('trim', $rowData);
                    $row++;
                    continue;
                }

                if (count($rowData) === count($headers)) {
                    $data[] = array_combine($headers, array_map('trim', $rowData));
                }
                $row++;
            }
            fclose($handle);
        }

        return $this->validateAndReturnData($data, $headers);
    }

    /**
     * Parse de arquivo Excel
     */
    private function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = [];
        $headers = [];
        $row = 0;

        foreach ($worksheet->getRowIterator() as $rowIterator) {
            $cellIterator = $rowIterator->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = trim((string)$cell->getValue());
            }

            if ($row === 0) {
                $headers = $rowData;
                $row++;
                continue;
            }

            // Ignora linhas vazias
            if (count(array_filter($rowData)) === 0) {
                $row++;
                continue;
            }

            if (count($rowData) >= count($headers)) {
                $rowData = array_slice($rowData, 0, count($headers));
                $data[] = array_combine($headers, $rowData);
            }
            $row++;
        }

        return $this->validateAndReturnData($data, $headers);
    }

    /**
     * Valida os dados e retorna o resultado
     */
    private function validateAndReturnData(array $data, array $headers): array
    {
        // Verifica se há dados
        if (empty($data)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'O arquivo não contém dados.',
                'columns' => $headers
            ];
        }

        // Verifica colunas obrigatórias (aceita aliases do columnMap)
        $columnMap = [
            'resource type' => 'ResourceType',
            'resourcetype' => 'ResourceType',
            'type' => 'ResourceType',
            'resource name' => 'ResourceName',
            'resourcename' => 'ResourceName',
            'name' => 'ResourceName',
            'resource group' => 'ResourceGroup',
            'resourcegroup' => 'ResourceGroup',
            'rg' => 'ResourceGroup',
            'location' => 'Location',
            'region' => 'Location',
            'subscription' => 'Subscription',
            'subscription id' => 'SubscriptionId',
            'subscriptionid' => 'SubscriptionId',
        ];

        $missingColumns = [];
        foreach ($this->requiredColumns as $required) {
            $requiredNormalized = $columnMap[strtolower($required)] ?? $required;
            $found = false;
            foreach ($headers as $header) {
                $headerNormalized = $columnMap[strtolower(trim($header))] ?? $header;
                if ($headerNormalized === $requiredNormalized) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingColumns[] = $required;
            }
        }

        if (!empty($missingColumns)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Colunas obrigatórias não encontradas: ' . implode(', ', $missingColumns),
                'columns' => $headers
            ];
        }

        // Normaliza os nomes das colunas
        $normalizedData = $this->normalizeColumnNames($data);

        return [
            'success' => true,
            'data' => $normalizedData,
            'error' => null,
            'columns' => $headers,
            'totalRows' => count($normalizedData)
        ];
    }

    /**
     * Normaliza os nomes das colunas para um padrão
     */
    private function normalizeColumnNames(array $data): array
    {
        $columnMap = [
            'resource type' => 'ResourceType',
            'resourcetype' => 'ResourceType',
            'type' => 'ResourceType',
            'resource name' => 'ResourceName',
            'resourcename' => 'ResourceName',
            'name' => 'ResourceName',
            'resource group' => 'ResourceGroup',
            'resourcegroup' => 'ResourceGroup',
            'rg' => 'ResourceGroup',
            'location' => 'Location',
            'region' => 'Location',
            'subscription' => 'Subscription',
            'subscription id' => 'SubscriptionId',
            'subscriptionid' => 'SubscriptionId',
        ];

        $normalizedData = [];
        foreach ($data as $row) {
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $normalizedKey = $columnMap[strtolower(trim($key))] ?? $key;
                $normalizedRow[$normalizedKey] = $value;
            }
            $normalizedData[] = $normalizedRow;
        }

        return $normalizedData;
    }

    /**
     * Detecta o delimitador do CSV
     */
    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }

        return array_keys($counts, max($counts))[0];
    }

    /**
     * Retorna mensagem de erro de upload
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP impediu o upload.',
        ];

        return $errors[$errorCode] ?? 'Erro desconhecido no upload.';
    }

    /**
     * Move o arquivo para o diretório de uploads
     */
    public function moveUploadedFile(array $file, string $uploadDir): array
    {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'upload_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => true,
                'path' => $destination,
                'filename' => $filename
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha ao mover o arquivo enviado.'
        ];
    }

    /**
     * Limpa arquivos antigos do diretório de uploads
     */
    public function cleanOldFiles(string $uploadDir, int $maxAgeHours = 24): void
    {
        if (!is_dir($uploadDir)) {
            return;
        }

        $files = glob($uploadDir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $maxAgeHours * 3600) {
                    unlink($file);
                }
            }
        }
    }
}
