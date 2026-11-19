<?php

/**
 * Family Roots - command line installer / migrator.
 *
 * Creates the database, a least-privilege application account, applies the
 * SQL migrations in order (tracked in `schema_migrations`), writes a fresh
 * `.env` when one does not exist, and optionally seeds demo data.
 *
 * Usage (from the project folder):
 *   C:\AppServ\php7\php.exe database/install.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD
 *
 * Options:
 *   --db-host=127.0.0.1   MySQL host used by this installer
 *   --db-port=3306        MySQL port
 *   --db-user=root        MySQL account that may CREATE DATABASE / CREATE USER
 *   --db-pass=...         password for that account (or env FAMILY_ROOTS_INSTALL_PASS)
 *   --db-name=family_roots              application schema
 *   --app-user=family_roots_app         least-privilege application account
 *   --app-pass=...         generated only when there is no existing .env;
 *                          a re-run reuses DB_PASS from .env so the live site
 *                          keeps working
 *   --no-seed              skip demo data
 *   --seed-only            only (re)run the demo seed
 *   --no-test-db           do not create the family_roots_test schema
 *   --fresh                DROP the application schema, app account and .env
 *                          (irreversible, requires explicit confirmation)
 *   --help
 *
 * The installer is idempotent: migrations already recorded in
 * `schema_migrations` are skipped, so it is safe to run repeatedly.
 * The administrator password is used in memory only - it is never written to
 * any file.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "The installer runs from the command line only.\n";
    echo "Run:  php database/install.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD\n";
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\Seeder;

/* ------------------------------------------------------------- arguments */
$options = [
    'db-host'   => '127.0.0.1',
    'db-port'   => '3306',
    'db-user'   => 'root',
    'db-pass'   => getenv('FAMILY_ROOTS_INSTALL_PASS') ?: '',
    'db-name'   => null,
    'app-user'  => null,
    'app-pass'  => null,
    'seed'      => 1,
    'seed-only' => 0,
    'test-db'   => 1,
    'fresh'     => 0,
    'help'      => 0,
];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = 1;
    } elseif ($arg === '--no-seed') {
        $options['seed'] = 0;
    } elseif ($arg === '--seed-only') {
        $options['seed-only'] = 1;
    } elseif ($arg === '--no-test-db') {
        $options['test-db'] = 0;
    } elseif ($arg === '--fresh') {
        $options['fresh'] = 1;
    } elseif (preg_match('/^--(db-host|db-port|db-user|db-pass|db-name|app-user|app-pass)=(.*)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

if ($options['help']) {
    $lines = file(__FILE__);
    foreach (array_slice($lines, 1, 40) as $line) {
        echo preg_replace('/^ \* ?/', '', rtrim($line, "\r\n")) . "\n";
    }
    exit(0);
}

$dbName = $options['db-name'] ?: (string) Config::get('db.name', 'family_roots');
$appUser = $options['app-user'] ?: (string) Config::get('db.user', 'family_roots_app');
$testName = $dbName . '_test';

function step($n, $total, $message)
{
    echo sprintf('[%d/%d] %s%s', $n, $total, $message, PHP_EOL);
}

function adminConfig(array $options)
{
    return [
        'host'    => $options['db-host'],
        'port'    => (int) $options['db-port'],
        'name'    => '',
        'user'    => $options['db-user'],
        'pass'    => (string) $options['db-pass'],
        'charset' => 'utf8mb4',
    ];
}

function connectAdmin(array $options)
{
    if ($options['db-pass'] === '') {
        fwrite(STDERR, "Missing administrator database password.\n");
        fwrite(STDERR, "Run:  php database/install.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD\n");
        fwrite(STDERR, "Or set the environment variable FAMILY_ROOTS_INSTALL_PASS.\n");
        exit(1);
    }
    try {
        return Database::makeConnection(adminConfig($options));
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Cannot connect to MySQL: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function quoteIdent($name)
{
    return '`' . str_replace('`', '', $name) . '`';
}

function generatePassword()
{
    return 'fr_' . substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(18))), 0, 18);
}

function generateAppKey()
{
    return base64_encode(random_bytes(32));
}

function writeEnvFile($root, array $values)
{
    $file = $root . '/.env';
    if (is_file($file)) {
        echo '     .env already exists - left untouched.' . PHP_EOL;
        return;
    }
    $example = $root . '/.env.example';
    if (!is_file($example)) {
        fwrite(STDERR, '     WARNING: .env.example missing, cannot create .env' . PHP_EOL);
        return;
    }
    $content = file_get_contents($example);
    foreach ($values as $key => $value) {
        $content = preg_replace(
            '/^' . preg_quote($key, '/') . '=.*$/m',
            $key . '=' . str_replace(['\\', '"'], ['', '\\"'], $value),
            $content
        );
    }
    file_put_contents($file, $content);
    echo '     Created .env with generated secrets (git-ignored).' . PHP_EOL;
}

/**
 * Read a single value from an existing .env, so a re-run can keep the
 * application account password that the running site already uses.
 */
function envValue($root, $key)
{
    $file = $root . '/.env';
    if (!is_file($file)) {
        return null;
    }
    foreach (file($file) as $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $m)) {
            $value = trim($m[1]);
            if (strlen($value) > 1 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = stripslashes(substr($value, 1, -1));
            }
            return $value === '' ? null : $value;
        }
    }
    return null;
}

/* ------------------------------------------------------------------- run */
$root = dirname(__DIR__);
$appPass = $options['app-pass'];
$appPassFromEnv = false;
if ($appPass === null || $appPass === '') {
    // Re-running the installer must not rotate the password the live site is
    // using: .env is never rewritten once it exists, so keep its DB_PASS.
    $existingPass = envValue($root, 'DB_PASS');
    if ($existingPass !== null) {
        $appPass = $existingPass;
        $appPassFromEnv = true;
    } else {
        $appPass = generatePassword();
    }
}
$appKey = generateAppKey();

$pdo = connectAdmin($options);

// Count the numbered stages that will actually run, plus the final "Done"
// line: four install stages (schema, account, migrations, .env), an optional
// seeding stage, or just one stage for --seed-only.
$total = $options['seed-only'] ? 2 : 5;
if (!$options['seed-only'] && $options['seed']) {
    $total++;
}
$n = 0;

if (!$options['seed-only']) {
    if ($options['fresh']) {
        step(++$n, $total, "Dropping schema {$dbName} and account {$appUser} (--fresh)");
        $pdo->exec('DROP DATABASE IF EXISTS ' . quoteIdent($dbName));
        $pdo->exec('DROP DATABASE IF EXISTS ' . quoteIdent($testName));
        $pdo->exec('DROP USER IF EXISTS ' . quoteIdent($appUser) . '@\'localhost\'');
        $pdo->exec('DROP USER IF EXISTS ' . quoteIdent($appUser) . '@\'127.0.0.1\'');
        if (is_file($root . '/.env')) {
            unlink($root . '/.env');
            echo '     Removed .env' . PHP_EOL;
        }
    }

    step(++$n, $total, "Creating schema {$dbName}");
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . quoteIdent($dbName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if ($options['test-db']) {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . quoteIdent($testName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    step(++$n, $total, "Creating least-privilege account {$appUser}");
    foreach (['localhost', '127.0.0.1'] as $host) {
        $pdo->exec('DROP USER IF EXISTS ' . quoteIdent($appUser) . '@\'' . $host . '\'');
        $pdo->exec(
            'CREATE USER ' . quoteIdent($appUser) . '@\'' . $host . '\' ' .
            'IDENTIFIED WITH mysql_native_password BY \'' . str_replace(['\\', '\''], ['', '\\\''], $appPass) . '\''
        );
        // Application privileges: data only on the live schema, plus DDL on
        // the throwaway test schema so the test harness can rebuild tables.
        $grants = [
            'SELECT, INSERT, UPDATE, DELETE, REFERENCES, INDEX, ALTER, EXECUTE, CREATE TEMPORARY TABLES ON ' . quoteIdent($dbName) . '.*',
            'SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, EXECUTE ON ' . quoteIdent($testName) . '.*',
        ];
        foreach ($grants as $grant) {
            $pdo->exec('GRANT ' . $grant . ' TO ' . quoteIdent($appUser) . '@\'' . $host . '\'');
        }
    }
    if ($appPassFromEnv) {
        echo '     Reused DB_PASS from the existing .env (the site keeps working).' . PHP_EOL;
    }
    $pdo->exec('FLUSH PRIVILEGES');

    // Migrations run with the administrator connection so the application
    // account never needs schema-management rights.
    Database::configure([
        'host'    => $options['db-host'],
        'port'    => (int) $options['db-port'],
        'name'    => $dbName,
        'user'    => $options['db-user'],
        'pass'    => (string) $options['db-pass'],
        'charset' => 'utf8mb4',
    ]);
    $app = Database::pdo();

    step(++$n, $total, 'Applying migrations');
    $result = App\Services\Migrator::migrate($app, $root . '/database/migrations');
    foreach ($result['skipped'] as $version) {
        echo "     skip  {$version} (already applied)" . PHP_EOL;
    }
    foreach ($result['applied'] as $version) {
        echo "     apply {$version}" . PHP_EOL;
    }
    if (!$result['applied']) {
        echo '     schema already up to date' . PHP_EOL;
    }

    step(++$n, $total, 'Writing .env');
    writeEnvFile($root, [
        'DB_NAME'  => $dbName,
        'DB_USER'  => $appUser,
        'DB_PASS'  => $appPass,
        'APP_KEY'  => $appKey,
        'APP_ENV'  => 'local',
        'APP_DEBUG' => 'true',
    ]);
}

if ($options['seed']) {
    step(++$n, $total, 'Seeding demo data');
    Database::configure([
        'host'    => $options['db-host'],
        'port'    => (int) $options['db-port'],
        'name'    => $dbName,
        'user'    => $options['db-user'],
        'pass'    => (string) $options['db-pass'],
        'charset' => 'utf8mb4',
    ]);
    $summary = Seeder::run();
    foreach ($summary as $line) {
        echo '     ' . $line . PHP_EOL;
    }
}

step($n + 1, $total, 'Done');

echo PHP_EOL . 'Family Roots is installed.' . PHP_EOL;
echo '  Database : ' . $dbName . PHP_EOL;
echo '  App user : ' . $appUser . '  (password stored in .env only)' . PHP_EOL;
echo '  Test db  : ' . ($options['test-db'] ? $testName : 'not created') . PHP_EOL;
echo PHP_EOL . 'Next steps:' . PHP_EOL;
echo '  1. Start the app:  http://localhost/family-root/public/' . PHP_EOL;
echo '  2. Run the tests:  ' . PHP_BINARY . ' tests/run.php --db-pass=YOUR_MYSQL_ROOT_PASSWORD' . PHP_EOL;
