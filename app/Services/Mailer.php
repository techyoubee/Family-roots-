<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

/**
 * Minimal mailer that never requires a paid service.
 *
 * transport=log  writes the message to storage/logs/mail-YYYY-MM-DD.log
 *                (the safe default for XAMPP - verification links are still
 *                usable because they are written in full to the log file)
 * transport=mail hands the message to PHP's mail() function.
 *
 * Tokens are written to the log on purpose in local development, but the log
 * directory is git-ignored, outside the web root, and never logged through the
 * application logger.
 */
final class Mailer
{
    public static function send($to, $subject, $body, $html = null)
    {
        $transport = (string) Config::get('mail.transport', 'log');
        $from = (string) Config::get('mail.from', 'Family Roots <no-reply@family-roots.local>');
        $cleanBody = trim(preg_replace('/[ \t]+/', ' ', strip_tags((string) $body)));

        if ($transport === 'mail') {
            $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
            $sent = @mail($to, $subject, wordwrap($cleanBody, 72), $headers);
            if (!$sent) {
                Logger::warning('mail() transport failed; message written to log instead.', ['to' => $to, 'subject' => $subject]);
                self::writeToLog($to, $subject, $cleanBody);
            }
            return (bool) $sent;
        }

        self::writeToLog($to, $subject, $cleanBody);
        return true;
    }

    /** Send an account related message if the user exists and mail is enabled. */
    public static function sendIfConfigured($userId, $subject, $body)
    {
        $user = Database::first('SELECT email, full_name FROM users WHERE id = ?', [(int) $userId]);
        if (!$user) {
            return false;
        }
        return self::send($user['email'], $subject, $body);
    }

    private static function writeToLog($to, $subject, $body)
    {
        $dir = Logger::directory();
        $file = $dir . '/mail-' . date('Y-m-d') . '.log';
        $message = sprintf(
            "================ %s ================\nTo: %s\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );
        @file_put_contents($file, $message, FILE_APPEND | LOCK_EX);
        @chmod($file, 0600);
    }
}
