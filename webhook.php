<?php
/**
 * webhook.php — GitHub Webhook handler untuk EPOIN Auto-Deploy
 *
 * Cara kerja:
 * 1. GitHub mengirim POST request ke URL ini setiap ada push ke main
 * 2. Script ini memverifikasi signature dari GitHub
 * 3. Jika valid, menulis flag file di /tmp/epoin_deploy_flag
 * 4. Cron job (deploy.sh) membaca flag dan menjalankan git pull
 *
 * Setup:
 * - Tambahkan DEPLOY_WEBHOOK_SECRET=xxxxx di file .env VPS
 * - Daftarkan URL ini di GitHub: Settings → Webhooks
 */

// Load .env untuk baca DEPLOY_WEBHOOK_SECRET
require_once __DIR__ . '/includes/env.php';
epoin_load_env(__DIR__);

$secret  = epoin_env('DEPLOY_WEBHOOK_SECRET', '');
$payload = file_get_contents('php://input');

// Verifikasi signature GitHub (HMAC-SHA256)
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (
    empty($secret)
    || empty($signature)
    || !hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $signature)
) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Invalid or missing signature']);
    exit;
}

// Hanya proses push ke branch main
$data = json_decode($payload, true);
$ref  = $data['ref'] ?? '';
if ($ref !== 'refs/heads/main') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => "Ignored: push to '$ref' (not main)"]);
    exit;
}

// Tulis flag file — akan dibaca oleh deploy.sh via cron
$flagFile = '/tmp/epoin_deploy_flag';
$result   = file_put_contents($flagFile, time());

if ($result === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Failed to write deploy flag']);
    exit;
}

// Log ringkasan push
$pusher = $data['pusher']['name'] ?? 'unknown';
$commit = $data['head_commit']['message'] ?? 'no message';
$logMsg = sprintf(
    "[%s] Webhook received from '%s': %s",
    date('Y-m-d H:i:s'),
    $pusher,
    $commit
);
file_put_contents('/tmp/epoin_webhook.log', $logMsg . PHP_EOL, FILE_APPEND);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'ok'     => true,
    'msg'    => 'Deployment queued successfully',
    'pusher' => $pusher,
    'commit' => $commit,
]);
