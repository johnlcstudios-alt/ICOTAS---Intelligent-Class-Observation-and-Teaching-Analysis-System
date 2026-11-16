<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Models\User;
use App\Services\InviteService;

/**
 * Administrator (Dean / Academic Head) account provisioning.
 *
 * The HTTP authorisation rules that protect these endpoints are exercised by
 * tools/e2e.php; the tests here prove the data layer keeps administrative
 * accounts scoped, counted and enumerable without leaking across scopes.
 */

test('administrator registry lists only Dean and Academic Head accounts in scope', function (): void {
    $users = new User();
    $demoAdmins = $users->administrators(true);

    assertTrue($demoAdmins !== [], 'seeded demo data contains administrator accounts');
    foreach ($demoAdmins as $row) {
        assertTrue(in_array((string) $row['role'], ['dean', 'academic_head'], true), 'only administrative roles are listed');
        assertSame(1, (int) $row['is_demo'], 'the demo scope returns demo records only');
    }
    assertSame('dean', (string) $demoAdmins[0]['role'], 'Dean accounts are listed before Academic Heads');

    $expectedDeans = (int) Database::scalar(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'dean' AND u.is_demo = 1",
        [],
        0
    );
    assertSame($expectedDeans, $users->countAdministrators(true)['dean'], 'Dean count reconciles with the records');

    $expectedHeads = (int) Database::scalar(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'academic_head' AND u.is_demo = 1",
        [],
        0
    );
    assertSame($expectedHeads, $users->countAdministrators(true)['academic_head'], 'Academic Head count reconciles with the records');
    assertSame(count($demoAdmins), $expectedDeans + $expectedHeads, 'every administrator row is counted once');

    $instructors = (int) Database::scalar(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'instructor' AND u.is_demo = 1",
        [],
        0
    );
    assertSame($instructors, $users->countByRole('instructor', true), 'role counts are derived from users');
});

test('a directly created administrator account carries the requested role and scope', function (): void {
    $users = new User();
    $deanRoleId = $users->roleId('dean');
    assertTrue($deanRoleId !== null, 'the Dean role exists');

    $username = 'unit.admin.' . bin2hex(random_bytes(4));
    $password = Security::temporaryPassword();
    $id = $users->createAccount([
        'role_id' => $deanRoleId,
        'username' => $username,
        'email' => $username . '@example.test',
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => 'Unit',
        'last_name' => 'Administrator',
        'employee_number' => 'UNIT-' . strtoupper(bin2hex(random_bytes(3))),
        'status' => 'active',
        'is_demo' => 0,
        'must_change_password' => 1,
    ]);

    $row = Database::fetch(
        'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
        [$id]
    );
    assertTrue($row !== null, 'created administrator exists');
    assertSame('dean', (string) $row['role'], 'created account receives the Dean role');
    assertSame(0, (int) $row['is_demo'], 'a live administrator is not tagged as demo data');
    assertSame(1, (int) $row['must_change_password'], 'a generated password must be changed at first sign-in');
    assertTrue(password_verify($password, (string) $row['password_hash']), 'the generated password verifies');

    $liveAdmins = $users->administrators(false);
    $ids = array_map(static fn (array $admin): int => (int) $admin['id'], $liveAdmins);
    assertTrue(in_array($id, $ids, true), 'the live administrator appears in the live registry');
    assertFalse(in_array($id, array_map(static fn (array $admin): int => (int) $admin['id'], $users->administrators(true)), true), 'the live administrator stays out of the demo registry');

    Database::execute('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
    Database::execute('DELETE FROM users WHERE id = ?', [$id]);
});

test('dean invitations issued for the administrator module are single-purpose and hashed', function (): void {
    $service = new InviteService();
    $invite = $service->create('dean', 1, 3, 'unit administrator invitation', null);

    $stored = Database::fetch('SELECT * FROM registration_invites WHERE id = ?', [$invite['id']]);
    assertTrue($stored !== null, 'invitation row exists');
    assertSame(InviteService::hash($invite['code']), (string) $stored['code_hash'], 'only the hash of the code is stored');
    assertFalse(str_contains((string) $stored['code_hash'], InviteService::normalize($invite['code'])), 'the plaintext code is never stored');

    $deanRoleId = (int) Database::scalar('SELECT id FROM roles WHERE slug = "dean" LIMIT 1', [], 0);
    assertSame($deanRoleId, (int) $stored['role_id'], 'the invitation grants the Dean role only');

    $redeemed = Database::transaction(function () use ($service, $invite): bool {
        $locked = $service->lockRedeemable($invite['code'], 'dean');
        $service->consume((int) $locked['id']);
        return true;
    });
    assertTrue($redeemed, 'the invitation redeems inside a transaction');
    assertSame('used', InviteService::status((array) Database::fetch('SELECT * FROM registration_invites WHERE id = ?', [$invite['id']])), 'a single-use invitation is spent');

    Database::execute('DELETE FROM registration_invites WHERE id = ?', [$invite['id']]);
});
