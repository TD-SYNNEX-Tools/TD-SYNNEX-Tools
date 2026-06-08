<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Guards: arquivos grandes (chunked) podem demorar para ser concatenados.
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '600');
set_time_limit(600);

/**
 * upload-chunk.php — Recebe pedaços de arquivo via fetch()
 *
 * Cada request traz:
 *   - chunk       (file)   pedaço binário
 *   - chunkIndex  (int)    índice 0-based
 *   - totalChunks (int)    total de pedaços
 *   - uploadId    (string) identificador único do upload (gerado no JS)
 *   - fileName    (string) nome original do arquivo
 *
 * Retorna JSON:
 *   { ok: true, chunkIndex: N }                       em cada pedaço
 *   { ok: true, complete: true, uploadId: "xxx" }     no último pedaço
 */

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- Validar request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

$chunkIndex  = filter_input(INPUT_POST, 'chunkIndex',  FILTER_VALIDATE_INT);
$totalChunks = filter_input(INPUT_POST, 'totalChunks', FILTER_VALIDATE_INT);
$uploadId    = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['uploadId'] ?? ''));
$fileName    = (string)($_POST['fileName'] ?? 'upload.csv');

if ($chunkIndex === false || $totalChunks === false || $uploadId === '' || $totalChunks < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametros invalidos']);
    exit;
}

if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Chunk nao recebido (error=' . ($_FILES['chunk']['error'] ?? 'missing') . ')']);
    exit;
}

// --- Salvar chunk temporário ---
$chunkDir = $uploadDir . '/chunks_' . $uploadId;
if (!is_dir($chunkDir)) {
    mkdir($chunkDir, 0755, true);
}

$chunkPath = $chunkDir . '/chunk_' . str_pad((string)$chunkIndex, 5, '0', STR_PAD_LEFT);
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao salvar chunk']);
    exit;
}

// --- Verificar se todos os chunks chegaram ---
$receivedChunks = count(glob($chunkDir . '/chunk_*'));

if ($receivedChunks >= $totalChunks) {
    // Montar arquivo final
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        // Limpar chunks
        array_map('unlink', glob($chunkDir . '/chunk_*'));
        @rmdir($chunkDir);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Apenas arquivos CSV sao suportados']);
        exit;
    }

    $finalPath = $uploadDir . '/cost_' . $uploadId . '.csv';
    $finalFh   = fopen($finalPath, 'wb');

    if (!$finalFh) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Falha ao criar arquivo final']);
        exit;
    }

    // Concatenar chunks em ordem (stream copy — sem carregar cada chunk em RAM)
    for ($i = 0; $i < $totalChunks; $i++) {
        $cp = $chunkDir . '/chunk_' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
        if (!file_exists($cp)) {
            fclose($finalFh);
            @unlink($finalPath);
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Chunk {$i} ausente"]);
            exit;
        }
        $cfh = fopen($cp, 'rb');
        if ($cfh === false) {
            fclose($finalFh);
            @unlink($finalPath);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => "Falha ao ler chunk {$i}"]);
            exit;
        }
        stream_copy_to_stream($cfh, $finalFh);
        fclose($cfh);
        @unlink($cp);
    }
    fclose($finalFh);
    @rmdir($chunkDir);

    // Guardar referência na sessão para o analyze usar
    $_SESSION['chunkedUpload'] = [
        'uploadId' => $uploadId,
        'filePath' => $finalPath,
        'fileName' => $fileName,
        'fileSize' => filesize($finalPath),
    ];

    echo json_encode([
        'ok'       => true,
        'complete' => true,
        'uploadId' => $uploadId,
        'fileSize' => filesize($finalPath),
    ]);
    exit;
}

// Chunk recebido mas ainda faltam outros
echo json_encode([
    'ok'         => true,
    'chunkIndex' => $chunkIndex,
    'received'   => $receivedChunks,
    'total'      => $totalChunks,
]);
