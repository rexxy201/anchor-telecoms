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

// Honeypot: a field hidden from real visitors via CSS. Bots that fill in
// every input will trip this; humans never see or touch it.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Pretend success so the bot doesn't learn to skip the field.
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

$host = $_SERVER['HTTP_HOST'] ?? '';
$isDevHost = (bool)preg_match('/^dev\./i', $host);

$subjectPrefix = $isDevHost ? '[DEV TEST] ' : '';
$subject = $subjectPrefix . "New website enquiry from {$name}";
$body = "Name: {$name}\n"
    . "Email: {$email}\n"
    . 'Company: ' . ($company !== '' ? $company : 'N/A') . "\n\n"
    . "Message:\n{$message}\n";

$headers = [
    "From: {$siteName} <{$fromAddress}>",
    "Reply-To: {$email}",
    'Content-Type: text/plain; charset=UTF-8',
];

// Never send real enquiry email from the dev/test subdomain — avoids
// polluting the inbox with test submissions during QA. Report success so
// the form's UX can still be tested end-to-end.
$sent = $isDevHost ? true : mail($toAddress, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
}

echo json_encode(['ok' => true]);
