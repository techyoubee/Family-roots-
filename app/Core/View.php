<?php

namespace App\Core;

use App\Exceptions\AppException;

/**
 * Minimal template renderer: plain PHP files under app/Views.
 *
 * All output must be escaped with e(). The renderer never evaluates user
 * supplied strings as templates.
 */
final class View
{
    /** @var array */
    private static $shared = [];

    public static function share($key, $value = null)
    {
        if (is_array($key)) {
            self::$shared = array_merge(self::$shared, $key);
            return;
        }
        self::$shared[$key] = $value;
    }

    public static function exists($template)
    {
        return is_file(self::path($template));
    }

    public static function path($template)
    {
        $template = str_replace(['..', "\0"], '', (string) $template);
        $template = str_replace('\\', '/', trim($template, '/'));
        return rtrim((string) Config::get('app.views', dirname(__DIR__) . '/Views'), '/') . '/' . $template . '.php';
    }

    /** Render a template and return the HTML string. */
    public static function render($template, array $data = [], $layout = null)
    {
        $content = self::capture($template, $data);
        if ($layout === false || $layout === null) {
            return $content;
        }
        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    private static function capture($template, array $data)
    {
        $file = self::path($template);
        if (!is_file($file)) {
            throw new AppException('View not found: ' . $template);
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
