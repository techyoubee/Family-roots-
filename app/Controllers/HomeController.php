<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Models\Family;

final class HomeController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'families'   => 0,
            'verified'   => 0,
            'ancestors'  => 0,
            'members'    => 0,
        ];
        $featured = [];
        $dbReady = $this->databaseAvailable();

        if ($dbReady) {
            $stats['families'] = (int) Database::value('SELECT COUNT(*) FROM families');
            $stats['verified'] = (int) Database::value("SELECT COUNT(*) FROM families WHERE verification_status = 'verified'");
            $stats['ancestors'] = (int) Database::value('SELECT COUNT(*) FROM ancestors');
            $stats['members'] = (int) Database::value("SELECT COUNT(*) FROM family_members WHERE status = 'active'");
            $featured = Family::forDirectory(null, 3);
        }

        return $this->view('home/index', [
            'title'    => Config::get('app.name', 'Family Roots') . ' - Register your family, trace your ancestors',
            'stats'    => $stats,
            'featured' => $featured,
            'dbReady'  => $dbReady,
        ]);
    }

    /**
     * Lightweight health endpoint for monitoring. Never exposes credentials,
     * table contents or configuration.
     */
    public function health(Request $request)
    {
        $database = 'unknown';
        $applied = null;
        try {
            Database::value('SELECT 1');
            $database = 'ok';
            if (Database::tableExists('schema_migrations')) {
                $applied = (int) Database::value('SELECT COUNT(*) FROM schema_migrations');
            }
        } catch (\Throwable $e) {
            $database = 'unavailable';
        }

        $status = $database === 'ok' ? 'ok' : 'degraded';
        return $this->json([
            'status'            => $status,
            'database'          => $database,
            'migrations_applied' => $applied,
            'time'              => date('c'),
        ], $status === 'ok' ? 200 : 503);
    }
}
