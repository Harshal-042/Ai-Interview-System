<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';


function failProcessing(
    PDO $pdo,
    int $sessionId,
    string $message,
    int $code = 500
): void {

    $pdo->prepare(
        "UPDATE interview_sessions
         SET status = 'Processing Failed'
         WHERE id = ?"
    )->execute([$sessionId]);

    http_response_code($code);

    echo json_encode([
        'success' => false,
        'error' => $message,
        'session_id' => $sessionId
    ]);

    exit;
}


function commandExists(string $command): bool {

    if (stripos(PHP_OS, 'WIN') === 0) {

        $out = shell_exec(
            'where ' . escapeshellarg($command) . ' 2>NUL'
        );

    } else {

        $out = shell_exec(
            'command -v ' .
            escapeshellarg($command) .
            ' 2>/dev/null'
        );
    }

    return !empty(trim((string)$out));
}


function runCommand(
    string $command,
    ?int &$exitCode = null
): string {

    $output = [];

    $exitCode = 1;

    exec(
        $command . ' 2>&1',
        $output,
        $exitCode
    );

    return implode("\n", $output);
}


function clampScore($value): int {

    return max(
        0,
        min(
            100,
            (int)round((float)$value)
        )
    );
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'error' => 'Method not allowed'
    ]);

    exit;
}


$sessionId = (int)($_POST['session_id'] ?? 0);


if (!$sessionId) {

    http_response_code(400);

    echo json_encode([
        'error' => 'Valid session_id is required'
    ]);

    exit;
}


$pdo = getDbConnection();


$stmt = $pdo->prepare(
    "SELECT s.*, i.title AS interview_title
     FROM interview_sessions s
     JOIN interviews i
     ON s.interview_id = i.id
     WHERE s.id = ?"
);

$stmt->execute([$sessionId]);

$session = $stmt->fetch();


if (!$session) {

    http_response_code(404);

    echo json_encode([
        'error' => 'Session not found'
    ]);

    exit;
}


// Get admin-defined questions

$qStmt = $pdo->prepare(
    "SELECT question
     FROM questions
     WHERE interview_id = ?
     ORDER BY question_order ASC"
);

$qStmt->execute([
    $session['interview_id']
]);

$questions = $qStmt->fetchAll(PDO::FETCH_COLUMN);


// Project root

$root = realpath(__DIR__ . '/..');


// Interview video

$videoPath = (string)($session['video_path'] ?? '');

$videoAbs = $videoPath
    ? realpath(
        $root .
        DIRECTORY_SEPARATOR .
        str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            ltrim($videoPath, '/\\')
        )
    )
    : false;


if (!$videoAbs || !is_file($videoAbs)) {

    failProcessing(
        $pdo,
        $sessionId,
        'Recorded interview video was not found.',
        422
    );
}


// =================================================
// FFMPEG
// =================================================

$ffmpeg = getenv('FFMPEG_BIN') ?: 'ffmpeg';

$ffprobe = getenv('FFPROBE_BIN') ?: 'ffprobe';


if (
    !commandExists($ffmpeg) ||
    !commandExists($ffprobe)
) {

    failProcessing(
        $pdo,
        $sessionId,
        'FFmpeg or FFprobe is not available in PATH.'
    );
}


// Processing directory

$workDir =
    $root .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'processing';


if (!is_dir($workDir)) {

    mkdir(
        $workDir,
        0775,
        true
    );
}


// Extract audio

$audioPath =
    $workDir .
    DIRECTORY_SEPARATOR .
    'session_' .
    $sessionId .
    '_' .
    time() .
    '.wav';


$exitCode = 0;


$ffmpegCommand =
    escapeshellcmd($ffmpeg) .
    ' -y -i ' .
    escapeshellarg($videoAbs) .
    ' -vn -ac 1 -ar 16000 -c:a pcm_s16le ' .
    escapeshellarg($audioPath);


runCommand(
    $ffmpegCommand,
    $exitCode
);


if (
    $exitCode !== 0 ||
    !is_file($audioPath)
) {

    failProcessing(
        $pdo,
        $sessionId,
        'FFmpeg could not extract audio.'
    );
}


// =================================================
// VIDEO METADATA
// =================================================

$probeCmd =
    escapeshellcmd($ffprobe) .
    ' -v error ' .
    ' -select_streams v:0 ' .
    ' -show_entries stream=width,height,avg_frame_rate ' .
    ' -show_entries format=duration ' .
    ' -of json ' .
    escapeshellarg($videoAbs);


$probeExit = 0;

$probeRaw = runCommand(
    $probeCmd,
    $probeExit
);


$probe = json_decode(
    $probeRaw,
    true
) ?: [];


$stream = $probe['streams'][0] ?? [];


$fps = null;


if (
    !empty($stream['avg_frame_rate']) &&
    str_contains(
        $stream['avg_frame_rate'],
        '/'
    )
) {

    [$n, $d] =
        array_map(
            'floatval',
            explode(
                '/',
                $stream['avg_frame_rate'],
                2
            )
        );

    if ($d != 0) {

        $fps = round(
            $n / $d,
            2
        );
    }
}


$videoMetrics = [

    'analysis_engine' =>
        'FFprobe actual media metadata',

    'resolution' =>
        (!empty($stream['width']) &&
        !empty($stream['height']))
        ? $stream['width'] .
          'x' .
          $stream['height']
        : null,

    'fps' => $fps,

    'duration_seconds' =>
        isset($probe['format']['duration'])
        ? round(
            (float)$probe['format']['duration'],
            2
        )
        : null,

    'video_file' =>
        basename($videoAbs)
];


// =================================================
// FASTER-WHISPER
// =================================================

// Python command

$python = getenv('PYTHON_BIN') ?: 'python';


if (!commandExists($python)) {

    failProcessing(
        $pdo,
        $sessionId,
        'Python is not available. Configure PYTHON_BIN.'
    );
}


// Model from .env

$whisperModel =
    getenv('WHISPER_MODEL')
    ?: 'base';


// Run faster-whisper directly using Python

$pythonCode =
    'from faster_whisper import WhisperModel; ' .
    '$model=WhisperModel("' .
    addslashes($whisperModel) .
    '", compute_type="int8"); ' .
    '$segments,$info=$model.transcribe(r"' .
    addslashes(str_replace('\\', '\\\\', $audioPath)) .
    '"); ' .
    'print("\\n".join([segment.text for segment in $segments]))';


// Use temporary Python file instead of command-line
// to avoid Windows quoting problems

$pythonScript =
    $workDir .
    DIRECTORY_SEPARATOR .
    'transcribe_' .
    $sessionId .
    '_' .
    time() .
    '.py';


$audioForPython =
    str_replace(
        '\\',
        '\\\\',
        $audioPath
    );


$pythonFileCode = <<<PYTHON
from faster_whisper import WhisperModel

audio_path = r"$audioForPython"

model = WhisperModel(
    "$whisperModel",
    compute_type="int8"
)

segments, info = model.transcribe(audio_path)

for segment in segments:
    print(segment.text.strip())
PYTHON;


file_put_contents(
    $pythonScript,
    $pythonFileCode
);


$transcribeCommand =
    escapeshellcmd($python) .
    ' ' .
    escapeshellarg($pythonScript);


$transcribeExit = 0;


$transcriptOutput =
    runCommand(
        $transcribeCommand,
        $transcribeExit
    );


if ($transcribeExit !== 0) {

    failProcessing(
        $pdo,
        $sessionId,
        'Local faster-whisper transcription failed: ' .
        substr(
            $transcriptOutput,
            0,
            1000
        )
    );
}


$transcript = trim($transcriptOutput);


if ($transcript === '') {

    failProcessing(
        $pdo,
        $sessionId,
        'faster-whisper returned an empty transcript.'
    );
}


// =================================================
// OLLAMA AI EVALUATION
// =================================================

$ollamaHost = rtrim(
    getenv('OLLAMA_HOST')
    ?: 'http://127.0.0.1:11434',
    '/'
);


$ollamaModel =
    getenv('OLLAMA_MODEL')
    ?: 'llama3.2:3b';


// Admin-defined questions only

$questionText = '';


foreach ($questions as $i => $question) {

    $questionText .=
        ($i + 1) .
        '. ' .
        $question .
        "\n";
}


$prompt =
"You are evaluating a candidate interview.

Use ONLY the questions supplied by the administrator
and the candidate transcript.

Do not invent questions.

Return ONLY valid JSON.

Questions:
$questionText

Candidate Transcript:
$transcript

Required JSON keys:

technical_score
relevance_score
communication_score
completeness_score
overall_score
rating
feedback

Scores must be integers from 0 to 100.";


// Ollama request

$ch = curl_init(
    $ollamaHost .
    '/api/generate'
);


if (!$ch) {

    failProcessing(
        $pdo,
        $sessionId,
        'Unable to connect to local Ollama.'
    );
}


curl_setopt_array(
    $ch,
    [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS =>
            json_encode(
                [
                    'model' => $ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json'
                ]
            ),

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 180
    ]
);


$response = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode =
    (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


curl_close($ch);


if (
    $httpCode !== 200 ||
    !$response
) {

    failProcessing(
        $pdo,
        $sessionId,
        'Local Ollama evaluation failed: ' .
        (
            $curlError
            ?: 'HTTP ' . $httpCode
        )
    );
}


$outer =
    json_decode(
        $response,
        true
    );


$aiAnalysis =
    !empty($outer['response'])
    ? json_decode(
        $outer['response'],
        true
    )
    : null;


$required = [

    'technical_score',
    'relevance_score',
    'communication_score',
    'completeness_score',
    'overall_score',
    'rating',
    'feedback'
];


if (
    !is_array($aiAnalysis) ||
    array_diff(
        $required,
        array_keys($aiAnalysis)
    )
) {

    failProcessing(
        $pdo,
        $sessionId,
        'Ollama returned invalid evaluation JSON.'
    );
}


foreach (

    [
        'technical_score',
        'relevance_score',
        'communication_score',
        'completeness_score',
        'overall_score'
    ]

    as $key
) {

    $aiAnalysis[$key] =
        clampScore(
            $aiAnalysis[$key]
        );
}


$aiAnalysis['rating'] =
    trim(
        (string)$aiAnalysis['rating']
    );


$aiAnalysis['feedback'] =
    trim(
        (string)$aiAnalysis['feedback']
    );


// =================================================
// SAVE RESULTS
// =================================================

try {

    $pdo->beginTransaction();


    $pdo->prepare(
        "UPDATE interview_sessions
         SET transcript = ?,
             status = 'Completed',
             completed_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    )->execute(
        [
            $transcript,
            $sessionId
        ]
    );


    $pdo->prepare(
        "DELETE FROM interview_analysis
         WHERE session_id = ?"
    )->execute(
        [
            $sessionId
        ]
    );


    $analysisStmt =
        $pdo->prepare(
            "INSERT INTO interview_analysis
            (
                session_id,
                technical_score,
                relevance_score,
                communication_score,
                completeness_score,
                overall_score,
                rating,
                ai_feedback,
                video_metrics
            )
            VALUES (?,?,?,?,?,?,?,?,?)"
        );


    $analysisStmt->execute(
        [
            $sessionId,

            $aiAnalysis['technical_score'],

            $aiAnalysis['relevance_score'],

            $aiAnalysis['communication_score'],

            $aiAnalysis['completeness_score'],

            $aiAnalysis['overall_score'],

            $aiAnalysis['rating'],

            $aiAnalysis['feedback'],

            json_encode($videoMetrics)
        ]
    );


    $pdo->commit();


    echo json_encode(
        [

            'success' => true,

            'message' =>
                'Interview processed successfully using local FFmpeg, faster-whisper and Ollama.',

            'session_id' =>
                $sessionId,

            'overall_score' =>
                $aiAnalysis['overall_score'],

            'rating' =>
                $aiAnalysis['rating']
        ]
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    failProcessing(
        $pdo,
        $sessionId,
        'Database update error: ' .
        $e->getMessage()
    );
}