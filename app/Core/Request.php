<?php

namespace App\Core;

/**
 * Immutable-ish HTTP request wrapper. Tests build requests with
 * Request::create() so controllers can be exercised without a web server.
 */
final class Request
{
    /** @var string */
    private $method;
    /** @var string */
    private $path;
    /** @var array */
    private $query;
    /** @var array */
    private $post;
    /** @var array */
    private $files;
    /** @var array */
    private $cookies;
    /** @var array */
    private $server;
    /** @var array */
    private $json;
    /** @var array */
    private $routeParams = [];
    /** @var array */
    private $attributes = [];
    /** @var string */
    private $rawBody = '';

    public function __construct($method, $path, array $query = [], array $post = [], array $files = [], array $cookies = [], array $server = [], $rawBody = '')
    {
        $this->method = strtoupper($method);
        $this->path = '/' . ltrim($path === '' ? '/' : $path, '/');
        if ($this->path !== '/' ) {
            $this->path = rtrim($this->path, '/');
        }
        if ($this->path === '') {
            $this->path = '/';
        }
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->rawBody = (string) $rawBody;
        $this->json = [];
        if ($this->rawBody !== '' && $this->isJson()) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) {
                $this->json = $decoded;
            }
        }
    }

    public static function capture()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Support the fallback router used when Apache cannot rewrite URLs:
        // index.php?r=/ancestors/12
        $query = $_GET;
        if (isset($_GET['r'])) {
            $r = (string) $_GET['r'];
            // Tolerate legacy links that embedded a query inside r=
            // (…?r=/notifications?filter=unread): split it back out.
            $qPos = strpos($r, '?');
            if ($qPos !== false) {
                parse_str(substr($r, $qPos + 1), $inline);
                $query = array_merge($inline, $query);
                $r = substr($r, 0, $qPos);
            }
            $path = '/' . ltrim($r, '/');
        }
        $path = self::stripBasePath($path);

        $rawBody = file_get_contents('php://input') ?: '';

        return new self($method, $path, $query, $_POST, $_FILES, $_COOKIE, $_SERVER, $rawBody);
    }

    public static function create($method, $path, array $options = [])
    {
        return new self(
            $method,
            $path,
            $options['query'] ?? [],
            $options['post'] ?? [],
            $options['files'] ?? [],
            $options['cookies'] ?? [],
            $options['server'] ?? ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'test'],
            $options['body'] ?? ''
        );
    }

    /**
     * Remove the mount point of the app from the path. Family Roots may live
     * under /family-roots/public when the document root is not dedicated.
     */
    public static function stripBasePath($path)
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }
        return $path;
    }

    public static function basePath()
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $base === '/' ? '' : $base;
    }

    public function method()
    {
        return $this->method;
    }

    public function path()
    {
        return $this->path;
    }

    public function isMethod($method)
    {
        return $this->method === strtoupper($method);
    }

    public function isPost()
    {
        return $this->method === 'POST';
    }

    public function isJson()
    {
        $contentType = $this->header('Content-Type', '');
        return stripos((string) $contentType, 'application/json') !== false;
    }

    public function wantsJson()
    {
        $accept = (string) $this->header('Accept', '');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        return strpos($this->path, '/api/') === 0;
    }

    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return array_key_exists($key, $this->post) ? $this->post[$key] : $default;
    }

    public function json($key = null, $default = null)
    {
        if ($key === null) {
            return $this->json;
        }
        return array_key_exists($key, $this->json) ? $this->json[$key] : $default;
    }

    /** Body value regardless of encoding (POST form, JSON body or query). */
    public function input($key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    public function all()
    {
        return array_merge($this->query, $this->json, $this->post);
    }

    /** Trimmed scalar input - arrays are returned untouched. */
    public function trimmed($key, $default = null)
    {
        $value = $this->input($key, $default);
        return is_string($value) ? trim($value) : $value;
    }

    public function any(array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->json) || array_key_exists($key, $this->query);
    }

    public function file($key)
    {
        return $this->files[$key] ?? null;
    }

    public function header($name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }
        if (strcasecmp($name, 'Content-Type') === 0 && isset($this->server['CONTENT_TYPE'])) {
            return $this->server['CONTENT_TYPE'];
        }
        if (strcasecmp($name, 'Content-Length') === 0 && isset($this->server['CONTENT_LENGTH'])) {
            return $this->server['CONTENT_LENGTH'];
        }
        return $default;
    }

    public function ip()
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent()
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isSecure()
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        return strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function setRouteParams(array $params)
    {
        $this->routeParams = $params;
    }

    public function routeParam($key, $default = null)
    {
        return array_key_exists($key, $this->routeParams) ? $this->routeParams[$key] : $default;
    }

    public function routeParams()
    {
        return $this->routeParams;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function attribute($key, $default = null)
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    public function csrfToken()
    {
        return $this->input(Csrf::FIELD, $this->header(Csrf::HEADER));
    }
}
