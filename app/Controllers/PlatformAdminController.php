<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLog;
use App\Services\AuthService;

/**
 * Platform administration.
 *
 * Read-only oversight across every registered account, family and membership
 * request. Access is restricted to users with `is_platform_admin = 1` by the
 * EnsurePlatformAdmin middleware on each route. The single write action is
 * regenerating an email-confirmation link for an account that has not yet
 * confirmed its address; it is recorded in the audit log. Decisions on
 * membership requests still happen through the family review screen, which
 * platform administrators may open because EnsureFamilyAdmin explicitly
 * allows them.
 */
final class PlatformAdminController extends Controller
{
    private const OPEN_REQUEST_STATUSES = [
        'submitted', 'auto_matched', 'pending_review', 'more_info_required', 'appeal_requested',
    ];

    /** Overview: headline counts, newest accounts and requests awaiting review. */
    public function index(Request $request)
    {
        $stats = $this->stats();

        return $this->view('admin/index', [
            'title'         => 'Platform administration',
            'stats'         => $stats,
            'recentUsers'   => $this->recentUsers(8),
            'openRequests'  => $this->requests(['status' => 'open', 'limit' => 8]),
        ]);
    }

    /** Every registered account, searchable and filterable. */
    public function accounts(Request $request)
    {
        $q        = trim((string) $request->query('q', ''));
        $status   = (string) $request->query('status', '');
        $verified = (string) $request->query('verified', '');
        $role     = (string) $request->query('role', '');
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = 40;

        $where  = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($verified === 'yes') {
            $where[] = 'u.email_verified_at IS NOT NULL';
        } elseif ($verified === 'no') {
            $where[] = 'u.email_verified_at IS NULL';
        }
        if ($role === 'admin') {
            $where[] = 'u.is_platform_admin = 1';
        } elseif ($role === 'member') {
            $where[] = 'u.is_platform_admin = 0';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Database::value('SELECT COUNT(*) FROM users u' . $whereSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $users = Database::select(
            'SELECT u.id, u.public_id, u.email, u.full_name, u.status, u.email_verified_at,
                    u.is_platform_admin, u.last_login_at, u.created_at
               FROM users u' . $whereSql . '
              ORDER BY u.created_at DESC, u.id DESC
              LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        $this->attachMemberships($users);

        return $this->view('admin/accounts', [
            'title'   => 'Registered accounts',
            'users'   => $users,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'filters' => [
                'q' => $q, 'status' => $status, 'verified' => $verified, 'role' => $role,
            ],
            'stats'   => $this->stats(),
            // Set by resendVerification() on a local server, where mail is logged
            // to a file instead of being delivered.
            'localVerificationLink' => Session::getFlash('verification_link'),
        ]);
    }

    /**
     * Issue a fresh email-confirmation link for an account.
     *
     * With the `log` mail transport (the default on a local XAMPP install)
     * nothing is actually delivered, so the generated link is handed back to
     * the administrator to open directly.
     */
    public function resendVerification(Request $request)
    {
        $publicId = (string) $request->routeParam('user', '');
        $user = Database::first('SELECT * FROM users WHERE public_id = ?', [$publicId]);

        if (!$user) {
            Session::flash('error', 'That account no longer exists.');
            return $this->redirect('/admin/accounts');
        }
        if (!empty($user['email_verified_at'])) {
            Session::flash('info', $user['email'] . ' has already confirmed this email address.');
            return $this->redirect('/admin/accounts');
        }

        $token = AuthService::sendEmailVerification($user, $request);

        AuditLog::record('admin.verification_resent', [
            'request'     => $request,
            'entity_type' => 'user',
            'entity_id'   => (int) $user['id'],
            'summary'     => 'Platform administrator regenerated the email-confirmation link for user #' . (int) $user['id'],
        ]);

        if ($this->mailIsLogged()) {
            Session::flash('verification_link', absolute_url('/verify-email/' . $token));
            Session::flash('success', 'A fresh confirmation link was generated for ' . $user['email'] . '.');
        } else {
            Session::flash('success', 'A fresh confirmation link was emailed to ' . $user['email'] . '.');
        }

        return $this->redirect('/admin/accounts');
    }

    /** Membership requests across every family (read-only queue overview). */
    public function applications(Request $request)
    {
        $status = (string) $request->query('status', 'open');
        $q      = trim((string) $request->query('q', ''));

        $rows = $this->requests([
            'status'  => $status,
            'q'       => $q,
            'limit'   => 200,
        ]);

        $counts = [];
        foreach (Database::select('SELECT status, COUNT(*) AS total FROM membership_requests GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        $openTotal = 0;
        foreach (self::OPEN_REQUEST_STATUSES as $openStatus) {
            $openTotal += $counts[$openStatus] ?? 0;
        }

        return $this->view('admin/applications', [
            'title'     => 'Membership requests',
            'rows'      => $rows,
            'counts'    => $counts,
            'openTotal' => $openTotal,
            'filters'   => ['status' => $status, 'q' => $q],
        ]);
    }

    /** Every family, with links into its member list and review queue. */
    public function families(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $sql = 'SELECT f.id, f.public_id, f.slug, f.name, f.region, f.country,
                       f.visibility, f.verification_status, f.created_at,
                       (SELECT COUNT(*) FROM family_members m
                         WHERE m.family_id = f.id AND m.status = \'active\') AS member_count,
                       (SELECT COUNT(*) FROM ancestors a WHERE a.family_id = f.id) AS ancestor_count,
                       (SELECT COUNT(*) FROM membership_requests r
                         WHERE r.family_id = f.id AND r.status IN '
                         . $this->openStatusSql() . ') AS open_requests
                  FROM families f';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE f.name LIKE ? OR f.public_id LIKE ? OR f.country LIKE ?';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY f.name ASC LIMIT 200';

        return $this->view('admin/families', [
            'title'    => 'All families',
            'families' => Database::select($sql, $params),
            'search'   => $q,
        ]);
    }

    /* ------------------------------------------------------------- helpers */

    /** Platform-wide headline numbers. */
    private function stats()
    {
        return [
            'users'          => (int) Database::value('SELECT COUNT(*) FROM users'),
            'active'         => (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'pending'        => (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
            'restricted'     => (int) Database::value("SELECT COUNT(*) FROM users WHERE status IN ('suspended','deletion_requested')"),
            'verified'       => (int) Database::value('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL'),
            'admins'         => (int) Database::value('SELECT COUNT(*) FROM users WHERE is_platform_admin = 1'),
            'families'       => (int) Database::value('SELECT COUNT(*) FROM families'),
            'members'        => (int) Database::value("SELECT COUNT(*) FROM family_members WHERE status = 'active'"),
            'pending_requests' => (int) Database::value(
                'SELECT COUNT(*) FROM membership_requests WHERE status IN ' . $this->openStatusSql()
            ),
        ];
    }

    /** Newest accounts with their active family memberships. */
    private function recentUsers($limit = 8)
    {
        $users = Database::select(
            'SELECT id, public_id, email, full_name, status, email_verified_at,
                    is_platform_admin, last_login_at, created_at
               FROM users
              ORDER BY created_at DESC, id DESC
              LIMIT ' . max(1, min(50, (int) $limit))
        );
        $this->attachMemberships($users);
        return $users;
    }

    /**
     * Attach each user's active family memberships in one query (no N+1).
     *
     * @param array $users rows passed by reference
     */
    private function attachMemberships(array &$users)
    {
        if (!$users) {
            return;
        }
        $ids = array_map(static function ($row) { return (int) $row['id']; }, $users);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::select(
            "SELECT m.user_id, m.role, f.name, f.public_id
               FROM family_members m
               JOIN families f ON f.id = m.family_id
              WHERE m.status = 'active' AND m.user_id IN (" . $placeholders . ')
              ORDER BY f.name ASC',
            $ids
        );
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']][] = $row;
        }
        foreach ($users as &$user) {
            $user['memberships'] = $byUser[(int) $user['id']] ?? [];
        }
        unset($user);
    }

    /**
     * Membership requests with applicant and family details.
     *
     * @param array $filters status, q, limit
     */
    private function requests(array $filters = [])
    {
        $status = $filters['status'] ?? 'open';
        $q      = trim((string) ($filters['q'] ?? ''));
        $limit  = max(1, min(200, (int) ($filters['limit'] ?? 100)));

        $sql = 'SELECT r.*, f.name AS family_name, f.public_id AS family_public_id,
                       u.full_name AS applicant_name, u.email AS applicant_email
                  FROM membership_requests r
                  JOIN families f ON f.id = r.family_id
                  JOIN users u ON u.id = r.applicant_user_id';
        $where  = [];
        $params = [];

        if ($status === 'open') {
            $where[] = 'r.status IN ' . $this->openStatusSql();
        } elseif ($status !== '' && $status !== 'all') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR f.name LIKE ? OR r.public_id LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY FIELD(r.status, 'pending_review', 'auto_matched', 'appeal_requested',
                                 'more_info_required', 'submitted', 'approved', 'declined',
                                 'cancelled', 'draft'), r.created_at DESC";
        $sql .= ' LIMIT ' . $limit;

        return Database::select($sql, $params);
    }

    private function openStatusSql()
    {
        return "('" . implode("','", self::OPEN_REQUEST_STATUSES) . "')";
    }

    /** True when mail is written to storage/logs/mail-*.log instead of sent. */
    private function mailIsLogged()
    {
        return (string) Config::get('mail.transport', 'log') !== 'mail';
    }
}
