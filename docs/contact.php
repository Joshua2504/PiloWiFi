<?php
declare(strict_types=1);

// ========================================
// Helper Functions
// ========================================

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'fetch' || $requestedWith === 'xmlhttprequest';
}

function send_json(int $status, bool $ok, string $message, ?string $error = null, array $extra = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    $payload = ['ok' => $ok, 'message' => $message];
    if ($error !== null) {
        $payload['error'] = $error;
    }
    foreach ($extra as $key => $value) {
        $payload[$key] = $value;
    }
    echo json_encode($payload);
    exit;
}

function respond(int $code, bool $ok, string $message, ?string $error = null, array $extra = []): void
{
    if (wants_json()) {
        send_json($code, $ok, $message, $error, $extra);
    }
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function validate_field(string $value, string $message, array $extra = []): void
{
    if ($value === '') {
        respond(400, false, $message, null, $extra);
    }
}

function sanitize_single_line(string $value): string
{
    $value = trim($value);
    return preg_replace('/[\r\n]+/', ' ', $value);
}

function value_or_na(string $value): string
{
    return $value !== '' ? $value : 'n/a';
}

function smtp_expect($socket, int $code)

{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    if (strpos($response, (string) $code) !== 0) {
        return $response !== '' ? $response : 'SMTP error';
    }

    return true;
}

function smtp_command($socket, string $command, int $expect)
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expect);
}

function smtp_send(
    string $host,
    int $port,
    string $user,
    string $pass,
    string $from,
    string $to,
    string $subject,
    string $body,
    bool $useTls
): array {
    $errno = 0;
    $errstr = '';
    $socket = fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        return [false, "SMTP connect failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, 10);
    $result = smtp_expect($socket, 220);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    $helo = env_value('SMTP_HELO', 'localhost');
    $result = smtp_command($socket, "EHLO {$helo}", 250);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    if ($useTls) {
        $result = smtp_command($socket, 'STARTTLS', 220);
        if ($result !== true) {
            fclose($socket);
            return [false, $result];
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return [false, 'Failed to enable TLS'];
        }

        $result = smtp_command($socket, "EHLO {$helo}", 250);
        if ($result !== true) {
            fclose($socket);
            return [false, $result];
        }
    }

    if ($user !== '' && $pass !== '') {
        $result = smtp_command($socket, 'AUTH LOGIN', 334);
        if ($result !== true) {
            fclose($socket);
            return [false, $result];
        }

        $result = smtp_command($socket, base64_encode($user), 334);
        if ($result !== true) {
            fclose($socket);
            return [false, $result];
        }

        $result = smtp_command($socket, base64_encode($pass), 235);
        if ($result !== true) {
            fclose($socket);
            return [false, $result];
        }
    }

    $result = smtp_command($socket, "MAIL FROM:<{$from}>", 250);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    $result = smtp_command($socket, "RCPT TO:<{$to}>", 250);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    $result = smtp_command($socket, 'DATA', 354);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    $headers = [
        "From: {$from}",
        "To: {$to}",
        "Subject: {$subject}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
    $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody);
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
    fwrite($socket, $data . "\r\n.\r\n");

    $result = smtp_expect($socket, 250);
    if ($result !== true) {
        fclose($socket);
        return [false, $result];
    }

    smtp_command($socket, 'QUIT', 221);
    fclose($socket);
    return [true, ''];
}

// ========================================
// Main Request Handler
// ========================================

$smtpPass = env_value('SMTP_PASS', '');
$smtpDebug = [
    'smtp_pass_set' => $smtpPass !== '',
    'smtp_pass_len' => strlen($smtpPass),
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Method not allowed.', null, $smtpDebug);
}

$honeypot = trim((string) ($_POST['website'] ?? ''));
if ($honeypot !== '') {
    respond(400, false, 'Invalid submission.', null, $smtpDebug);
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

validate_field($email, 'Please provide an email address.', $smtpDebug);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'Please provide a valid email address.', null, $smtpDebug);
}

validate_field($notes, 'Please provide a message.', $smtpDebug);

$subjectName = sanitize_single_line($fullName);
$subjectEmail = sanitize_single_line($email);
$subject = 'JarnoWiFi contact request';
if ($subjectName !== '') {
    $subject .= ' - ' . $subjectName;
} elseif ($subjectEmail !== '') {
    $subject .= ' - ' . $subjectEmail;
}

$bodyLines = [
    'New contact request',
    '',
    'Name: ' . value_or_na($fullName),
    'Email: ' . value_or_na($email),
    'Message: ' . value_or_na($notes),
    '',
    'Submitted: ' . date('c'),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
];

$body = implode("\n", $bodyLines);

$host = 'mail.treudler.net';
$user = 'system@treudler.net';
$from = 'system@treudler.net';
$secure = 'tls';
$useTls = true;
$port = 587;
$pass = $smtpPass;
$to = 'joshua@treudler.net';

[$ok, $error] = smtp_send($host, $port, $user ?? '', $pass ?? '', $from ?? 'system@jarnowifi.net', $to, $subject, $body, $useTls);

if (!$ok) {
    respond(500, false, 'Email sending failed.', $error, $smtpDebug);
}

$lang = sanitize_single_line((string) ($_POST['lang'] ?? ''));
if (!in_array($lang, ['nl', 'en', 'de'], true)) {
    $lang = 'nl';
}

if (wants_json()) {
    send_json(200, true, 'Message sent.', null, $smtpDebug);
}

header('Location: /?lang=' . $lang . '#contact');
header('Content-Type: text/plain; charset=UTF-8');
echo 'Message sent.';
