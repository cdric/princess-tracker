<?php

declare(strict_types=1);

function send_tracker_email(string $to, string $subject, string $body, ?string $htmlBody = null): void
{
    $method = strtolower(env_value('MAIL_METHOD', 'mail') ?? 'mail');
    $from = env_value('MAIL_FROM', 'princess-tracker@localhost');

    $headers = [
        'From: ' . $from,
        'MIME-Version: 1.0',
    ];

    $messageBody = $body;
    if ($htmlBody !== null && $htmlBody !== '') {
        $boundary = 'tracker_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $messageBody = build_multipart_email_body($boundary, $body, $htmlBody);
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }

    if ($method === 'sendmail') {
        $sendmail = env_value('SENDMAIL_PATH', '/usr/sbin/sendmail');
        $message = "To: {$to}\n";
        $message .= "Subject: {$subject}\n";
        $message .= implode("\n", $headers) . "\n\n";
        $message .= $messageBody . "\n";

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($sendmail . ' -t', $descriptorSpec, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Unable to start sendmail.');
        }
        fwrite($pipes[0], $message);
        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            throw new RuntimeException('sendmail failed: ' . $stderr);
        }
        return;
    }

    if (!mail($to, $subject, $messageBody, implode("\r\n", $headers))) {
        throw new RuntimeException('mail() failed. Check Bluehost mail configuration.');
    }
}

function build_multipart_email_body(string $boundary, string $textBody, string $htmlBody): string
{
    return '--' . $boundary . "\n"
        . "Content-Type: text/plain; charset=UTF-8\n\n"
        . $textBody . "\n\n"
        . '--' . $boundary . "\n"
        . "Content-Type: text/html; charset=UTF-8\n\n"
        . $htmlBody . "\n\n"
        . '--' . $boundary . "--";
}
