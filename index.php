<?php
/**
 * CloudflareSub PHP — 入口路由
 *
 * 路由规则:
 *   GET  /              → 前端页面
 *   POST /api/generate  → 生成订阅
 *   GET  /sub/{id}      → 获取订阅内容
 *   OPTIONS *           → CORS 预检
 */

// ── 错误处理：API 请求时抑制 warning/notice 输出，避免破坏 JSON ──
$isApiRequest = (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '') === '/api/generate'
) || (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && preg_match('#^/sub/#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '')
);

if ($isApiRequest) {
    ob_start();
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
}

$config = require __DIR__ . '/src/config.php';

// ── CORS 预检 ──
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: content-type');
    http_response_code(204);
    exit;
}

// ── 路由解析 ──
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// POST /api/generate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/api/generate') {
    require_once __DIR__ . '/src/api.php';
    try {
        handleGenerate($config);
    } catch (Throwable $e) {
        jsonResponse(['ok' => false, 'error' => '服务器内部错误: ' . $e->getMessage()], 500);
    }
}

// GET /sub/{id}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/sub/([A-Za-z0-9]+)#', $path, $m)) {
    require_once __DIR__ . '/src/api.php';
    try {
        handleSub($m[1], $config);
    } catch (Throwable $e) {
        textResponse('服务器内部错误: ' . $e->getMessage(), 500);
    }
}

// CLI: php index.php cleanup  (定时清理)
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'cleanup') {
    require_once __DIR__ . '/src/api.php';
    handleCleanup($config);
    exit;
}

// ── 静态文件 ──
$staticDir = __DIR__ . '/public';
$filePath = $path === '/' ? '/index.html' : $path;
$fullPath = realpath($staticDir . $filePath);

// 安全检查：防止目录遍历
if ($fullPath && str_starts_with($fullPath, realpath($staticDir)) && is_file($fullPath)) {
    $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'ico'  => 'image/x-icon',
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($fullPath);
    exit;
}

// SPA 回退
if (file_exists($staticDir . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($staticDir . '/index.html');
    exit;
}

http_response_code(404);
echo 'Not Found';
