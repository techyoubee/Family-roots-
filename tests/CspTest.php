<?php

namespace Tests;

/**
 * Guards the strict Content-Security-Policy set by SecurityHeaders:
 *
 *   default-src 'self'; style-src 'self'; script-src 'self'
 *
 * The browser silently drops inline styles, inline event handlers and inline
 * script blocks under that policy, so a violation does not look like an error
 * during development - it simply stops working in production. These checks
 * fail the suite instead.
 *
 * Inline JSON payloads (`<script type="application/json">`) are not executed
 * and are therefore allowed.
 */
final class CspTest extends TestCase
{
    private function viewFiles()
    {
        $root = dirname(__DIR__) . '/app/Views';
        $files = [];

        if (!is_dir($root)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return array<int,string> human-readable "file:line (match)" entries
     */
    private function violations($pattern)
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $hits = [];

        foreach ($this->viewFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $text = $match[0];
                $line = $match[1] > 0 ? substr_count(substr($source, 0, $match[1]), "\n") + 1 : 1;
                $hits[] = str_replace($root, '', $file) . ':' . $line . ' (' . trim((string) $text) . ')';
            }
        }

        return $hits;
    }

    private function assertNoViolations($hits, $description)
    {
        $this->assertCount(
            0,
            $hits,
            $hits
                ? $description . ' found: ' . implode('; ', array_slice($hits, 0, 12))
                : 'No ' . $description . ' in views.'
        );
    }

    public function testViewsAvoidInlineStyleAttributes()
    {
        $this->assertNoViolations(
            $this->violations('/\sstyle\s*=\s*["\']/i'),
            'inline style attributes'
        );
    }

    public function testViewsAvoidInlineEventHandlers()
    {
        $this->assertNoViolations(
            $this->violations('/\son(click|dblclick|change|submit|load|error|input|focus|blur|keyup|keydown|keypress|mouseover|mouseout|mouseenter|mouseleave|contextmenu)\s*=\s*["\']/i'),
            'inline event handlers'
        );
    }

    public function testViewsAvoidJavascriptUrls()
    {
        // Only an attribute value can navigate to a javascript: URL; prose such
        // as "usable without JavaScript:" must not trip the check.
        $this->assertNoViolations(
            $this->violations('/\b(?:href|src|action|formaction)\s*=\s*["\']\s*javascript\s*:/i'),
            'javascript: URLs'
        );
    }

    public function testViewsAvoidInlineScriptBlocks()
    {
        // Allowed: <script src="..."> and non-executable JSON payload blocks.
        $pattern = '/<script\b(?![^>]*\bsrc\s*=)(?![^>]*\btype\s*=\s*["\']application\/(?:ld\+)?json["\'])[^>]*>/i';
        $this->assertNoViolations($this->violations($pattern), 'inline <script> blocks');

        $this->assertNoViolations(
            $this->violations('/<style\b/i'),
            'inline <style> blocks'
        );
    }

    public function testViewsDoNotInjectStylesFromPhp()
    {
        $this->assertNoViolations(
            $this->violations('/\bcssText\b|setAttribute\s*\(\s*["\']style["\']/i'),
            'CSSOM style injection'
        );
    }

    public function testViewScannerActuallyFindsViews()
    {
        $files = $this->viewFiles();
        $this->assertGreaterThan(
            10,
            count($files),
            'Expected the view scanner to find the application views.'
        );
    }
}
