<?php

namespace App\Core;

final class Response
{
    /** @var int */
    private $status;
    /** @var array */
    private $headers;
    /** @var string */
    private $body;
    /** @var callable|null */
    private $streamer;

    public function __construct($body = '', $status = 200, array $headers = [], callable $streamer = null)
    {
        $this->body = (string) $body;
        $this->status = (int) $status;
        $this->headers = $headers;
        $this->streamer = $streamer;
    }

    public static function html($body, $status = 200)
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json($data, $status = 200)
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($body === false ? '{}' : $body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect($url, $status = 302)
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function noContent()
    {
        return new self('', 204);
    }

    public static function stream(callable $writer, array $headers = [], $status = 200)
    {
        return new self('', $status, $headers, $writer);
    }

    public function withHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function status()
    {
        return $this->status;
    }

    public function body()
    {
        return $this->body;
    }

    public function headers()
    {
        return $this->headers;
    }

    public function send()
    {
        if (headers_sent() === false) {
            // http_response_code() falls back to 500 on this PHP 7.3 build
            // whenever the status has no built-in reason phrase (419, 418...),
            // so send an explicit status line with a known phrase instead.
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] !== ''
                ? $_SERVER['SERVER_PROTOCOL']
                : 'HTTP/1.1';
            header($protocol . ' ' . $this->status . ' ' . self::reasonPhrase($this->status), true, $this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->streamer !== null) {
            call_user_func($this->streamer);
            return;
        }
        echo $this->body;
    }

    /** Reason phrases for every status this application can send. */
    private static function reasonPhrase($status)
    {
        $map = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            419 => 'Session Expired',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            429 => 'Too Many Requests',
            499 => 'Client Closed Request',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];
        return isset($map[$status]) ? $map[$status] : 'Unknown';
    }
}
