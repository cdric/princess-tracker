<?php

declare(strict_types=1);

function send_tracker_email(string $to, string $subject, string $body): void
{
    $method = strtolower(env_value('MAIL_METHOD', 'mail') ?? 'mail');
    $from = env_value('MAIL_FROM', 'princess-tracker@localhost');

    if ($method === 'sendmail') {
        $sendmail = env_value('SENDMAIL_PATH', '/usr/sbin/sendmail');
        $message = "To: {$to}\n";
        $message .= "From: {$from}\n";
        $message .= "Subject: {$subject}\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\n\n";
        $message .= $body . "\n";

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

    $headers = [
        'From: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
        throw new RuntimeException('mail() failed. Check Bluehost mail configuration.');
    }
}
