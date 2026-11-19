<?php

namespace Tests;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Str;
use App\Core\Validator;
use App\Exceptions\NotFoundException;
use App\Http\Middleware\Middleware;

/**
 * Framework-level behaviour: string helpers used by the matching engine,
 * validation, routing, middleware and the rate limiter.
 */
final class RecordingMiddleware implements Middleware
{
    public static $calls = [];
    public static $append = null;

    public function handle($request, callable $next)
    {
        self::$calls[] = 'before';
        if (self::$append !== null) {
            self::$append[] = 'middleware';
        }
        $response = $next($request);
        self::$calls[] = 'after';
        return $response;
    }
}

final class CoreTest extends TestCase
{
    public function testNameNormalisationHandlesAccentsAndSpacing()
    {
        $this->assertSame('jose muller', Str::normalizeName('José  MÜller'));
        $this->assertSame('karma dorji', Str::normalizeName('  Karma   Dorji '));
        $this->assertSame('pema wangmo', Str::normalizeName('Pema- Wangmo!'));
    }

    public function testNameSimilarityDetectsSpellingVariants()
    {
        $variant = Str::nameSimilarity('Karma Dorji', 'Karma Dorjii');
        $unrelated = Str::nameSimilarity('Karma Dorji', 'Bakari Hakim');
        $this->assertGreaterThan(0.5, $variant);
        $this->assertLessThan(0.4, $unrelated);
    }

    public function testNameSimilarityNeverClaimsIdentityForSharedSurnameOnly()
    {
        // A shared surname with nothing else in common must stay low.
        $shared = Str::nameSimilarity('Karma Dorji', 'Lhamo Dorji');
        $this->assertLessThan(0.8, $shared);
    }

    public function testSlugAndUuidHelpers()
    {
        $this->assertSame('bh-kiru', Str::slug('BH KIRU'));
        $this->assertSame('e-muller-family', Str::slug('É Müller Family'));
        $uuid = Str::uuid4();
        $this->assertTrue((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid));
        $this->assertNotEquals($uuid, Str::uuid4());
    }

    public function testLivingPersonNameMasking()
    {
        $masked = Str::maskName('Tenpa Dorji');
        $this->assertNotContains('Tenpa', $masked);
        $this->assertNotContains('Dorji', $masked);
        $this->assertContains('T', $masked);
    }

    public function testConfigDotNotation()
    {
        $this->assertSame('Family Roots', Config::get('app.name'));
        $this->assertSame('members', Config::get('privacy.default_visibility'));
        $this->assertNull(Config::get('does.not.exist'));
        $this->assertSame('fallback', Config::get('does.not.exist', 'fallback'));
    }

    public function testValidatorRules()
    {
        $validator = Validator::make(
            ['email' => 'not-an-email', 'name' => 'ab', 'password' => 'secret123', 'password_confirmation' => 'other'],
            [
                'email'    => 'required|email',
                'name'     => 'required|min:3',
                'password' => 'required|min:8|confirmed',
            ]
        );
        $this->assertTrue($validator->fails());
        $this->assertCount(3, $validator->errors());

        $valid = Validator::make(
            ['email' => 'person@example.test', 'name' => 'Valid Name'],
            ['email' => 'required|email', 'name' => 'required|min:3']
        );
        $this->assertFalse($valid->fails());
        $this->assertSame('Valid Name', $valid->value('name'));
    }

    public function testValidatorUniqueAndExistsUseTheDatabase()
    {
        $userId = Fixtures::user();
        $user = \App\Models\User::find($userId);

        $duplicate = Validator::make(['email' => $user['email']], ['email' => 'unique:users,email']);
        $this->assertTrue($duplicate->fails());

        $unique = Validator::make(['email' => Fixtures::unique('new') . '@example.test'], ['email' => 'unique:users,email']);
        $this->assertFalse($unique->fails());

        $exists = Validator::make(['user_id' => $userId], ['user_id' => 'exists:users,id']);
        $this->assertFalse($exists->fails());

        $missing = Validator::make(['user_id' => 999999999], ['user_id' => 'exists:users,id']);
        $this->assertTrue($missing->fails());
    }

    public function testRouterDispatchesAndGeneratesNamedUrls()
    {
        $router = new Router();
        $router->get('/hello/{name}', static function (Request $request) {
            return Response::html('Hello ' . $request->routeParam('name'));
        }, [], 'hello');

        $response = $router->dispatch(Request::create('GET', '/hello/Karma'));
        $this->assertSame(200, $response->status());
        $this->assertContains('Hello Karma', $response->body());
        $this->assertSame('/hello/Karma', $router->url('hello', ['name' => 'Karma']));
    }

    public function testRouterMatchesNestedAndNumericParameters()
    {
        $router = new Router();
        $router->get('/families/{family}', static function (Request $request) {
            return Response::html('family:' . $request->routeParam('family'));
        });
        $router->get('/notifications/{id}/read', static function (Request $request) {
            return Response::html('notification:' . $request->routeParam('id'));
        });

        $family = $router->dispatch(Request::create('GET', '/families/FAM-77E493'));
        $this->assertSame(200, $family->status());
        $this->assertContains('family:FAM-77E493', $family->body());

        $notification = $router->dispatch(Request::create('GET', '/notifications/42/read'));
        $this->assertSame(200, $notification->status());
        $this->assertContains('notification:42', $notification->body());

        $this->expectException(NotFoundException::class, static function () use ($router) {
            $router->dispatch(Request::create('GET', '/notifications/not-a-number/read'));
        });
    }

    public function testRouterReturnsNotFoundForUnknownPaths()
    {
        $router = new Router();
        $router->get('/', static function () {
            return Response::html('home');
        });

        $this->expectException(NotFoundException::class, static function () use ($router) {
            $router->dispatch(Request::create('GET', '/missing-page'));
        });
    }

    public function testMiddlewareRunsAroundTheHandler()
    {
        RecordingMiddleware::$calls = [];
        $order = [];

        $router = new Router();
        $router->get('/guarded', static function () use (&$order) {
            $order[] = 'handler';
            return Response::html('ok');
        }, [RecordingMiddleware::class]);

        $router->dispatch(Request::create('GET', '/guarded'));
        $this->assertSame(['before', 'after'], RecordingMiddleware::$calls);
        $this->assertSame(['handler'], $order);
    }

    public function testCsrfTokenIsStableAndValidated()
    {
        $token = Csrf::token();
        $this->assertSame($token, Csrf::token());
        $this->assertTrue(Csrf::check($token));
        $this->assertFalse(Csrf::check('not-the-token'));
        $this->assertFalse(Csrf::check(''));
    }

    public function testRateLimiterBlocksAfterTheLimit()
    {
        RateLimiter::flush();
        $key = Fixtures::unique('bucket');
        $this->assertTrue(RateLimiter::attempt('login', $key, 3, 60));
        $this->assertTrue(RateLimiter::attempt('login', $key, 3, 60));
        $this->assertTrue(RateLimiter::attempt('login', $key, 3, 60));
        $this->assertFalse(RateLimiter::attempt('login', $key, 3, 60));
        $this->assertSame(0, RateLimiter::remaining('login', $key, 3, 60));

        RateLimiter::clear('login', $key);
        $this->assertTrue(RateLimiter::attempt('login', $key, 3, 60));
    }

    public function testLoggerRedactsSensitiveContext()
    {
        $redacted = \App\Core\Logger::redact([
            'password' => 'super-secret',
            'email'    => 'person@example.test',
            'nested'   => ['token_hash' => 'abc', 'name' => 'Pema'],
        ]);
        $this->assertSame('***redacted***', $redacted['password']);
        $this->assertSame('person@example.test', $redacted['email']);
        $this->assertSame('***redacted***', $redacted['nested']['token_hash']);
        $this->assertSame('Pema', $redacted['nested']['name']);
    }

    public function testSlowQueryLogNamesTheStatementButNotTheBindValues()
    {
        $logFile = \App\Core\Logger::directory() . '/app-' . date('Y-m-d') . '.log';
        $offset = is_file($logFile) ? (int) filesize($logFile) : 0;
        $previous = Config::get('db.slow_query_ms');
        $secret = 'secret-bind-' . Fixtures::unique('value');

        Config::set('db.slow_query_ms', 0.001);
        try {
            \App\Core\Database::first('SELECT :value AS bound_value', ['value' => $secret]);
        } finally {
            Config::set('db.slow_query_ms', $previous);
        }

        $written = is_file($logFile) ? (string) file_get_contents($logFile, false, null, $offset) : '';
        $this->assertContains('Slow database query', $written);
        $this->assertContains('SELECT :value AS bound_value', $written);
        // Personal data must never leak into the diagnostics log.
        $this->assertNotContains($secret, $written);
    }
}
