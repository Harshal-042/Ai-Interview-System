<?php

// Local Piper TTS using Python package:
// python -m piper --model MODEL --output_file FILE

$text = trim($_GET['text'] ?? $_POST['text'] ?? '');

if ($text === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'No text specified'
    ]);
    exit;
}

// Python executable
$python = 'python';

// Piper voice model
$model = 'C:/piper/voices/en_US-lessac-medium.onnx';

if ($model === '') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Piper model is not configured. Set PIPER_MODEL.'
    ]);
    exit;
}

if (!is_file($model)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Piper voice model was not found: ' . $model
    ]);
    exit;
}

// Check Python
if (stripos(PHP_OS, 'WIN') === 0) {
    $pythonExists = trim((string)shell_exec(
        'where ' . escapeshellarg($python) . ' 2>NUL'
    )) !== '';
} else {
    $pythonExists = trim((string)shell_exec(
        'command -v ' . escapeshellarg($python) . ' 2>/dev/null'
    )) !== '';
}

if (!$pythonExists) {
    http_response_code(503);
    header('Content-Type: application/json');

    echo json_encode([
        'error' => 'Python is not available. Configure PYTHON_BIN.'
    ]);
    exit;
}

// Create TTS directory
$dir = __DIR__ . '/../uploads/tts';

if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

// Same question generates the same cached audio file
$key = hash('sha256', $text . '|' . $model);

$wav = $dir . '/' . $key . '.wav';

if (!is_file($wav)) {

    // Windows-compatible command
    $cmd =
        'echo ' . escapeshellarg($text) .
        ' | ' . escapeshellcmd($python) .
        ' -m piper' .
        ' --model ' . escapeshellarg($model) .
        ' --output_file ' . escapeshellarg($wav);

    $output = [];
    $exitCode = 1;

    exec($cmd . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0 || !is_file($wav)) {

        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => 'Piper failed',
            'details' => implode("\n", $output)
        ]);

        exit;
    }
}

// Return WAV audio
header('Content-Type: audio/wav');
header('Content-Length: ' . filesize($wav));

readfile($wav);
exit;