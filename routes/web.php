<?php

/**
 * Route table.
 *
 * Controllers are resolved as App\Controllers\<Name>. Middleware classes live in
 * App\Http\Middleware. Handlers only ever return a Response (or a string/array
 * that the router converts).
 *
 * @var App\Core\Router $router
 */

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureFamilyAdmin;
use App\Http\Middleware\EnsureFamilyMember;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\ResolveFamily;
use App\Http\Middleware\VerifyCsrfToken;

/* --------------------------------------------------------------- public site */
$router->get('/', 'HomeController@index', [], 'home');
$router->get('/about', 'AboutController@index', [], 'about');
$router->get('/privacy', 'PrivacyController@index', [], 'privacy');
$router->get('/families', 'FamilyDirectoryController@index', [], 'families.index');

/* ---------------------------------------------------- family registration */
/* Must precede /families/{family}: the router matches in registration order. */
$router->get('/families/create', 'FamilyController@create', [Authenticate::class, EnsureEmailVerified::class], 'family.register');
$router->post('/families/create', 'FamilyController@store', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'family.store');

$router->get('/families/{family}', 'FamilyDirectoryController@show', [], 'families.show');
$router->get('/health', 'HomeController@health', [], 'health');

/* ------------------------------------------------------------------- auth */
$router->get('/login', 'AuthController@showLogin', [RedirectIfAuthenticated::class], 'auth.login');
$router->post('/login', 'AuthController@login', [RedirectIfAuthenticated::class, VerifyCsrfToken::class], 'auth.attempt');
$router->get('/register', 'AuthController@showRegister', [RedirectIfAuthenticated::class], 'auth.register');
$router->post('/register', 'AuthController@register', [RedirectIfAuthenticated::class, VerifyCsrfToken::class], 'auth.store');
$router->post('/logout', 'AuthController@logout', [Authenticate::class, VerifyCsrfToken::class], 'auth.logout');

/* ------------------------------------------------------- email confirmation */
$router->get('/verify-email', 'AuthController@verifyNotice', [Authenticate::class], 'auth.verify.notice');
$router->post('/verify-email/resend', 'AuthController@resendVerification', [Authenticate::class, VerifyCsrfToken::class], 'auth.verify.resend');
$router->get('/verify-email/{token}', 'AuthController@verify', [], 'auth.verify');

/* ------------------------------------------------------------ password reset */
$router->get('/password/forgot', 'AuthController@showForgot', [RedirectIfAuthenticated::class], 'auth.password.request');
$router->post('/password/forgot', 'AuthController@sendResetLink', [RedirectIfAuthenticated::class, VerifyCsrfToken::class], 'auth.password.email');
$router->get('/password/reset/{token}', 'AuthController@showReset', [], 'auth.password.reset');
$router->post('/password/reset', 'AuthController@resetPassword', [VerifyCsrfToken::class], 'auth.password.update');

/* -------------------------------------------------------------- account */
$router->get('/dashboard', 'AccountController@dashboard', [Authenticate::class], 'dashboard');
$router->get('/settings', 'AccountController@settings', [Authenticate::class, EnsureEmailVerified::class], 'settings.index');
$router->post('/settings/profile', 'AccountController@updateProfile', [Authenticate::class, VerifyCsrfToken::class], 'settings.profile');
$router->post('/settings/password', 'AccountController@updatePassword', [Authenticate::class, VerifyCsrfToken::class], 'settings.password');
$router->post('/settings/deletion', 'AccountController@requestDeletion', [Authenticate::class, VerifyCsrfToken::class], 'settings.deletion');

/* -------------------------------------------------- platform administration */
/* Read-only oversight for platform administrators: registered accounts,
   membership requests across every family, and every family's member list. */
$router->get('/admin', 'PlatformAdminController@index', [Authenticate::class, EnsurePlatformAdmin::class], 'admin.index');
$router->get('/admin/accounts', 'PlatformAdminController@accounts', [Authenticate::class, EnsurePlatformAdmin::class], 'admin.accounts');
/* Issue a fresh email-confirmation link for an account. On a local server the
   link is shown to the administrator instead of being emailed. */
$router->post('/admin/accounts/{user}/verification', 'PlatformAdminController@resendVerification', [Authenticate::class, EnsurePlatformAdmin::class, VerifyCsrfToken::class], 'admin.accounts.verification');
$router->get('/admin/applications', 'PlatformAdminController@applications', [Authenticate::class, EnsurePlatformAdmin::class], 'admin.applications');
$router->get('/admin/families', 'PlatformAdminController@families', [Authenticate::class, EnsurePlatformAdmin::class], 'admin.families');

/* -------------------------------------------------------- notifications */
$router->get('/notifications', 'NotificationsController@index', [Authenticate::class], 'notifications.index');
$router->post('/notifications/read-all', 'NotificationsController@markAllRead', [Authenticate::class, VerifyCsrfToken::class], 'notifications.read-all');
$router->post('/notifications/{id}/read', 'NotificationsController@markRead', [Authenticate::class, VerifyCsrfToken::class], 'notifications.read');

/* ------------------------------------------------------- membership requests */
$router->get('/applications', 'MembershipApplicationController@index', [Authenticate::class], 'applications.index');
/* Must precede /applications/{application}: the router matches in order. */
$router->get('/applications/create', 'MembershipApplicationController@create', [Authenticate::class, EnsureEmailVerified::class], 'applications.create');
$router->post('/applications/{application}/claims', 'MembershipApplicationController@addClaim', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.claims.store');
$router->post('/applications/{application}/claims/{claim}/delete', 'MembershipApplicationController@removeClaim', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.claims.delete');
$router->post('/applications/{application}/submit', 'MembershipApplicationController@submit', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.submit');
$router->post('/applications/{application}/appeal', 'MembershipApplicationController@appeal', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.appeal');
$router->post('/applications/{application}/cancel', 'MembershipApplicationController@cancel', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.cancel');
$router->post('/applications/{application}', 'MembershipApplicationController@update', [Authenticate::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'applications.update');
$router->get('/applications/{application}', 'MembershipApplicationController@show', [Authenticate::class], 'applications.show');

/* ------------------------------------------------------ family management */
$router->get('/families/{family}/settings', 'FamilyController@edit', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class], 'family.settings');
$router->post('/families/{family}/settings', 'FamilyController@update', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'family.settings.update');

$router->get('/families/{family}/applications', 'MembershipApplicationController@queue', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class], 'family.applications');
$router->get('/families/{family}/applications/{application}', 'MembershipApplicationController@review', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class], 'family.applications.review');
$router->post('/families/{family}/applications/{application}', 'MembershipApplicationController@decide', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'family.applications.decide');

$router->get('/families/{family}/members', 'FamilyMemberController@index', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class], 'family.members');
$router->post('/families/{family}/members/{member}/role', 'FamilyMemberController@updateRole', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'family.members.role');
$router->post('/families/{family}/members/{member}/remove', 'FamilyMemberController@remove', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'family.members.remove');

/* ------------------------------------------------------- ancestor records */
$router->get('/families/{family}/ancestors', 'AncestorController@index', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class], 'ancestors.index');
$router->get('/families/{family}/ancestors/create', 'AncestorController@create', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class, EnsureEmailVerified::class], 'ancestors.create');
$router->post('/families/{family}/ancestors', 'AncestorController@store', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'ancestors.store');
$router->get('/families/{family}/ancestors/{ancestor}/edit', 'AncestorController@edit', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class, EnsureEmailVerified::class], 'ancestors.edit');
$router->get('/families/{family}/ancestors/{ancestor}', 'AncestorController@show', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class], 'ancestors.show');
$router->post('/families/{family}/ancestors/{ancestor}', 'AncestorController@update', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'ancestors.update');
$router->post('/families/{family}/ancestors/{ancestor}/delete', 'AncestorController@delete', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'ancestors.delete');

/* -------------------------------------------------------- relationships */
$router->get('/families/{family}/relationships', 'RelationshipController@index', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class], 'relationships.index');
$router->post('/families/{family}/relationships', 'RelationshipController@store', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class, EnsureEmailVerified::class, VerifyCsrfToken::class], 'relationships.store');
$router->post('/families/{family}/relationships/{relationship}/delete', 'RelationshipController@delete', [Authenticate::class, ResolveFamily::class, EnsureFamilyAdmin::class, VerifyCsrfToken::class], 'relationships.delete');

/* ------------------------------------------------------------ tree view */
$router->get('/families/{family}/tree', 'TreeController@show', [Authenticate::class, ResolveFamily::class, EnsureFamilyMember::class], 'tree.show');

/* ------------------------------------------------------- ancestral journey */
/* Read-only presentation of the member's recorded ancestry. The active family
   is chosen with ?family=<public_id>; the controller validates membership. */
$router->get('/journey', 'AncestralJourneyController@intro', [Authenticate::class], 'journey.intro');
$router->get('/journey/animate', 'AncestralJourneyController@animate', [Authenticate::class], 'journey.animate');
$router->get('/journey/tree', 'AncestralJourneyController@tree', [Authenticate::class], 'journey.tree');
$router->get('/journey/timeline', 'AncestralJourneyController@timeline', [Authenticate::class], 'journey.timeline');
$router->get('/journey/data', 'AncestralJourneyController@data', [Authenticate::class], 'journey.data');
$router->post('/journey/complete', 'AncestralJourneyController@complete', [Authenticate::class, VerifyCsrfToken::class], 'journey.complete');
