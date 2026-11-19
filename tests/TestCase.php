<?php

namespace Tests;

/**
 * Very small test case base class.
 *
 * The project deliberately ships without external PHP dependencies, so the
 * suite is a self-contained runner instead of PHPUnit. It supports the handful
 * of assertions these tests actually need.
 */
abstract class TestCase
{
    /** @var array<int,array{name:string,passed:bool,message:string}> */
    public $results = [];

    /** @var array */
    protected $context = [];

    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    public function name()
    {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /** Execute every public test* method on the case. */
    public function run()
    {
        $methods = array_filter(get_class_methods($this), static function ($method) {
            return strpos($method, 'test') === 0;
        });
        sort($methods);

        $all = [];
        foreach ($methods as $method) {
            $this->results = [];
            try {
                $this->setUp();
                $this->{$method}();
                $this->tearDown();
            } catch (\Throwable $e) {
                if (!$this->hasFailure()) {
                    $this->results[] = ['name' => $method, 'passed' => false, 'message' => 'Unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()];
                }
                try {
                    $this->tearDown();
                } catch (\Throwable $ignored) {
                }
            }
            if (!$this->hasFailure()) {
                $this->results[] = ['name' => $method, 'passed' => true, 'message' => 'ok'];
            }
            $all = array_merge($all, $this->results);
            $this->context = [];
        }
        $this->results = $all;
        return $all;
    }

    private function hasFailure()
    {
        foreach ($this->results as $result) {
            if (!$result['passed']) {
                return true;
            }
        }
        return false;
    }

    /* ------------------------------------------------------------ assertions */

    protected function assertTrue($condition, $message = 'Expected condition to be true.')
    {
        $this->record((bool) $condition, $message);
    }

    protected function assertFalse($condition, $message = 'Expected condition to be false.')
    {
        $this->record(!$condition, $message);
    }

    protected function assertSame($expected, $actual, $message = '')
    {
        $ok = $expected === $actual;
        $this->record($ok, $message !== '' ? $message : sprintf('Expected %s, got %s.', $this->describe($expected), $this->describe($actual)));
    }

    protected function assertEquals($expected, $actual, $message = '')
    {
        $ok = $expected == $actual;
        $this->record($ok, $message !== '' ? $message : sprintf('Expected %s, got %s.', $this->describe($expected), $this->describe($actual)));
    }

    protected function assertNotEquals($expected, $actual, $message = '')
    {
        $this->record($expected != $actual, $message !== '' ? $message : 'Expected values to differ.');
    }

    protected function assertNull($value, $message = 'Expected null.')
    {
        $this->record($value === null, $message);
    }

    protected function assertNotNull($value, $message = 'Expected a value, got null.')
    {
        $this->record($value !== null, $message);
    }

    protected function assertCount($expected, $actual, $message = '')
    {
        $this->record(is_array($actual) && count($actual) === (int) $expected, $message !== '' ? $message : sprintf('Expected %d items, got %d.', (int) $expected, is_array($actual) ? count($actual) : -1));
    }

    protected function assertContains($needle, $haystack, $message = '')
    {
        $ok = is_array($haystack) ? in_array($needle, $haystack, true) : (is_string($haystack) && strpos($haystack, (string) $needle) !== false);
        $this->record($ok, $message !== '' ? $message : sprintf('Expected to find %s.', $this->describe($needle)));
    }

    protected function assertNotContains($needle, $haystack, $message = '')
    {
        $ok = is_array($haystack) ? !in_array($needle, $haystack, true) : (is_string($haystack) && strpos($haystack, (string) $needle) === false);
        $this->record($ok, $message !== '' ? $message : sprintf('Did not expect to find %s.', $this->describe($needle)));
    }

    protected function assertGreaterThan($expected, $actual, $message = '')
    {
        $this->record($actual > $expected, $message !== '' ? $message : sprintf('Expected a value greater than %s, got %s.', $this->describe($expected), $this->describe($actual)));
    }

    protected function assertLessThan($expected, $actual, $message = '')
    {
        $this->record($actual < $expected, $message !== '' ? $message : sprintf('Expected a value less than %s, got %s.', $this->describe($expected), $this->describe($actual)));
    }

    protected function assertInstanceOf($class, $value, $message = '')
    {
        $this->record($value instanceof $class, $message !== '' ? $message : sprintf('Expected an instance of %s.', $class));
    }

    protected function fail($message)
    {
        $this->record(false, $message);
    }

    /**
     * @return \Throwable|null
     */
    protected function expectException($class, callable $fn)
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                $this->record(true, 'ok');
                return $e;
            }
            $this->record(false, 'Expected ' . $class . ' but caught ' . get_class($e) . ': ' . $e->getMessage());
            return $e;
        }
        $this->record(false, 'Expected ' . $class . ' but nothing was thrown.');
        return null;
    }

    private function record($ok, $message)
    {
        $this->results[] = ['name' => $this->currentTestName(), 'passed' => (bool) $ok, 'message' => $message];
    }

    private function currentTestName()
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            if (isset($frame['function']) && strpos($frame['function'], 'test') === 0) {
                return $frame['function'];
            }
        }
        return 'test';
    }

    private function describe($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_string($value) && strlen($value) > 80) {
            return '"' . substr($value, 0, 77) . '..."';
        }
        return var_export($value, true);
    }
}
