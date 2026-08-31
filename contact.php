<?php
declare(strict_types=1);

// Keep the response body pure JSON even if PHP emits a warning/notice
// (e.g. mail() failing) — display_errors can leak HTML into the output
// on a misconfigured server and break the client's res.json() parsing.
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$toAddress = 'anchorng@anchortelecoms.com';
$fromAddress = 'no-reply@anchortelecoms.com';
$siteName = 'Anchor Telecoms Website';

/**
 * Site is proxied through Cloudflare, so REMOTE_ADDR is Cloudflare's edge
 * IP, not the visitor's. CF-Connecting-IP carries the real client IP on
 * proxied requests; only trust it because Cloudflare sits in front of
 * every request to this app (there's no way to reach origin directly).
 */
function client_ip(): string
{
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Lightweight per-IP flood control: refuse a second submission from the
 * same visitor within COOLDOWN_SECONDS. A plain temp-file timestamp is
 * enough here — this isn't guarding a login endpoint, just discouraging
 * naive submit-spam, and avoids needing a database for a static site.
 */
function rate_limited(string $ip, int $cooldownSeconds): bool
{
    $lockFile = sys_get_temp_dir() . '/anchor_contact_rl_' . md5($ip);
    $last = @file_get_contents($lockFile);
    $now = time();
    if ($last !== false && ($now - (int)$last) < $cooldownSeconds) {
        return true;
    }
    @file_put_contents($lockFile, (string)$now, LOCK_EX);
    return false;
}

$ip = client_ip();
if (rate_limited($ip, 20)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
    exit;
}

// Honeypot: a field hidden from real visitors via CSS. Bots that fill in
// every input will trip this; humans never see or touch it.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Pretend success so the bot doesn't learn to skip the field.
    echo json_encode(['ok' => true]);
    exit;
}

// Timing check: the form stamps a hidden "loaded_at" timestamp on render
// (see main.js). A real visitor needs at least a couple of seconds to
// read the form and type; near-instant submission is a strong bot signal.
$loadedAt = (int)($_POST['loaded_at'] ?? 0);
if ($loadedAt > 0 && (time() - $loadedAt) < 2) {
    echo json_encode(['ok' => true]);
    exit;
}

function clean_line(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

$name = clean_line((string)($_POST['name'] ?? ''));
$email = clean_line((string)($_POST['email'] ?? ''));
$company = clean_line((string)($_POST['company'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) > 200) {
    $errors[] = 'name';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    $errors[] = 'email';
}
if (mb_strlen($company) > 200) {
    $errors[] = 'company';
}
if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'message';
}

if ($errors !== []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_input', 'fields' => $errors]);
    exit;
}

$subject = "New website enquiry from {$name}";
$body = "Name: {$name}\n"
    . "Email: {$email}\n"
    . 'Company: ' . ($company !== '' ? $company : 'N/A') . "\n"
    . "IP: {$ip}\n\n"
    . "Message:\n{$message}\n";

$headers = [
    "From: {$siteName} <{$fromAddress}>",
    "Reply-To: {$email}",
    'Content-Type: text/plain; charset=UTF-8',
];

$sent = mail($toAddress, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
}

echo json_encode(['ok' => true]);
