<?php

namespace Tests;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Exceptions\HttpException;
use App\Models\EmailToken;
use App\Models\User;
use App\Services\AuthService;

/**
 * Account lifecycle: registration, confirmation, sign in and password reset.
 */
final class AuthTest extends TestCase
{
    public function setUp()
    {
        Auth::logout();
        RateLimiter::flush();
    }

    public function tearDown()
    {
        Auth::logout();
        RateLimiter::flush();
    }

    private function register($email = null)
    {
        $email = $email !== null ? $email : Fixtures::unique('auth') . '@example.test';
        $user = AuthService::register([
            'full_name' => 'Auth Test Person',
            'email'     => $email,
            'password'  => 'CorrectHorse!99',
        ], Request::create('POST', '/register'));

        return $user;
    }

    public function testRegistrationCreatesAPendingAccount()
    {
        $email = Fixtures::unique('auth') . '@example.test';
        $user = $this->register($email);

        $this->assertSame('pending', $user['status'], 'A new account starts in the pending state.');
        $this->assertNull($user['email_verified_at'], 'A new account has no confirmation timestamp.');
        $this->assertEquals($email, $user['email'], 'The stored email is normalised to lower case.');
        $this->assertTrue(is_string($user['password_hash']) && strpos($user['password_hash'], '$') === 0, 'The password is stored as a hash.');

        $tokenRows = \App\Core\Database::select(
            'SELECT purpose FROM user_tokens WHERE user_id = ?',
            [(int) $user['id']]
        );
        $this->assertContains('verify_email', array_column($tokenRows, 'purpose'), 'A confirmation token is issued at registration.');
    }

    public function testEmailVerificationActivatesTheAccount()
    {
        $user = $this->register();
        $token = AuthService::sendEmailVerification(User::find($user['id']));

        $verified = AuthService::verifyEmail($token, Request::create('GET', '/verify-email/' . $token));
        $this->assertNotNull($verified, 'A valid confirmation token verifies the account.');
        $this->assertSame('active', $verified['status'], 'A verified account becomes active.');
        $this->assertNotNull($verified['email_verified_at'], 'The confirmation timestamp is recorded.');

        $again = AuthService::verifyEmail($token);
        $this->assertNull($again, 'A confirmation token can only be used once.');
    }

    public function testLoginRejectsWrongPassword()
    {
        $user = $this->register();
        $row = User::find($user['id']);

        $result = AuthService::attempt($row['email'], 'not-the-password', Request::create('POST', '/login'));
        $this->assertNull($result, 'A wrong password never signs anyone in.');
        $this->assertFalse(Auth::check(), 'The session stays signed out after a failed attempt.');

        $fresh = User::find($user['id']);
        $this->assertGreaterThan(0, (int) $fresh['failed_login_count'], 'A failed attempt is counted on the account.');
    }

    public function testLoginSucceedsWithTheCorrectPassword()
    {
        $user = $this->register();
        $row = User::find($user['id']);
        User::markEmailVerified($row['id']);

        $result = AuthService::attempt($row['email'], 'CorrectHorse!99', Request::create('POST', '/login'));

        $this->assertNotNull($result, 'The correct password signs the user in.');
        $this->assertTrue(Auth::check(), 'The session is authenticated after a successful sign in.');
        $this->assertEquals((int) $row['id'], (int) Auth::id(), 'The authenticated id matches the account.');
        $this->assertSame(0, (int) User::find($row['id'])['failed_login_count'], 'A successful sign in clears the failure counter.');
    }

    public function testRepeatedFailuresLockTheAccount()
    {
        $user = $this->register();
        $row = User::find($user['id']);

        $ip = '10.99.0.';
        for ($i = 1; $i <= 5; $i++) {
            AuthService::attempt($row['email'], 'wrong-password', Request::create('POST', '/login', [
                'server' => ['REMOTE_ADDR' => $ip . $i, 'HTTP_USER_AGENT' => 'test'],
            ]));
        }

        $locked = User::find($row['id']);
        $this->assertTrue(User::isLocked($locked), 'Five failures lock the account for a while.');

        $result = AuthService::attempt($row['email'], 'CorrectHorse!99', Request::create('POST', '/login', [
            'server' => ['REMOTE_ADDR' => $ip . '99'],
        ]));
        $this->assertNull($result, 'Even the correct password is refused while the account is locked.');
    }

    public function testPasswordResetChangesThePasswordOnce()
    {
        $user = $this->register();
        $row = User::find($user['id']);

        AuthService::requestPasswordReset($row['email'], Request::create('POST', '/password/forgot'));
        $tokenRow = \App\Core\Database::first(
            'SELECT * FROM user_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [(int) $row['id'], 'reset_password']
        );
        $this->assertNotNull($tokenRow, 'A reset token row is recorded (the plaintext only exists in the email).');

        // Recover the plaintext token from the mail log, exactly as a user would.
        $plaintext = $this->tokenFromMailLog($row['email'], '/password/reset/');
        $this->assertNotNull($plaintext, 'The reset link is written to the mail transport.');

        $updated = AuthService::resetPassword($plaintext, 'BrandNewPass!42', Request::create('POST', '/password/reset'));
        $this->assertNotNull($updated, 'A valid reset token changes the password.');
        $this->assertTrue(password_verify('BrandNewPass!42', $updated['password_hash']), 'The new password verifies against the stored hash.');

        $again = AuthService::resetPassword($plaintext, 'AnotherPass!42');
        $this->assertNull($again, 'The reset token is single use.');
    }

    public function testSuspendedAccountsAreRefused()
    {
        $user = $this->register();
        User::updateProfile($user['id'], ['status' => 'suspended']);

        $this->expectException(HttpException::class, function () use ($user) {
            AuthService::attempt($user['email'], 'CorrectHorse!99', Request::create('POST', '/login'));
        });
    }

    public function testAuthEventsAreAudited()
    {
        $user = $this->register();
        $row = User::find($user['id']);
        User::markEmailVerified($row['id']);

        AuthService::attempt($row['email'], 'CorrectHorse!99', Request::create('POST', '/login'));
        AuthService::logout(Request::create('POST', '/logout'));

        $actions = \App\Core\Database::select(
            'SELECT action FROM audit_logs WHERE actor_user_id = ? ORDER BY id',
            [(int) $row['id']]
        );
        $actions = array_column($actions, 'action');

        $this->assertContains('auth.registered', $actions, 'Registration is audited.');
        $this->assertContains('auth.login', $actions, 'Sign in is audited.');
        $this->assertContains('auth.logout', $actions, 'Sign out is audited.');
    }

    public function testEmailChangeVerificationTokensAreSeparate()
    {
        $user = $this->register();
        $verify = AuthService::sendEmailVerification(User::find($user['id']));
        $this->assertNull(EmailToken::findValid($verify, 'reset_password'), 'A confirmation token is not accepted as a reset token.');
    }

    /** Pull the last reset link that the log-transport mailer wrote. */
    private function tokenFromMailLog($email, $linkPrefix)
    {
        $dir = \App\Core\Logger::directory();
        $file = $dir . '/mail-' . date('Y-m-d') . '.log';
        if (!is_file($file)) {
            return null;
        }
        $contents = (string) file_get_contents($file);
        $blocks = preg_split('/^=+.*=+\s*$/m', $contents) ?: [];
        foreach (array_reverse($blocks) as $block) {
            if (strpos($block, 'To: ' . $email) === false) {
                continue;
            }
            if (preg_match('#' . preg_quote($linkPrefix, '#') . '([a-f0-9]{64})#', $block, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
