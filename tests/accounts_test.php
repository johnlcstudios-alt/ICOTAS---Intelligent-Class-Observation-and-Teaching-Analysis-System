<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Models\User;
use App\Services\InviteService;

test('temporary instructor passwords satisfy the password policy', function (): void {
    $seen = [];
    for ($i = 0; $i < 5; $i++) {
        $password = Security::temporaryPassword();
        assertSame(null, Security::passwordProblem($password), 'generated password must satisfy the application policy');
        assertTrue(strlen($password) >= 12, 'generated password is long enough');
        assertTrue((bool) preg_match('/[A-Z]/', $password), 'generated password has an uppercase letter');
        assertTrue((bool) preg_match('/[a-z]/', $password), 'generated password has a lowercase letter');
        assertTrue((bool) preg_match('/[0-9]/', $password), 'generated password has a digit');
        assertTrue((bool) preg_match('/[^A-Za-z0-9]/', $password), 'generated password has a symbol');
        $seen[$password] = true;
    }
    assertSame(5, count($seen), 'generated passwords are not repeated');
});

test('invitation codes are hashed, single-use and revocable', function (): void {
    $service = new InviteService();
    $invite = $service->create('dean', 1, 2, 'unit-test invitation');

    assertTrue(str_starts_with($invite['code'], 'DEAN-'), 'display code carries the Dean prefix');
    assertSame(12, strlen(InviteService::normalize($invite['code'])), 'normalisation returns the twelve-character body');

    $stored = Database::fetch('SELECT * FROM registration_invites WHERE id = ?', [$invite['id']]);
    assertTrue($stored !== null, 'invitation row exists');
    assertSame(InviteService::hash($invite['code']), $stored['code_hash'], 'only the hash matches the code');
    assertFalse(str_contains((string) $stored['code_hash'], InviteService::normalize($invite['code'])), 'plaintext code is not stored');
    assertSame('active', InviteService::status($stored));

    $redeemed = Database::transaction(function () use ($service, $invite): int {
        $locked = $service->lockRedeemable($invite['code'], 'dean');
        $service->consume((int) $locked['id']);
        return (int) $locked['id'];
    });
    assertSame((int) $invite['id'], $redeemed);

    $used = Database::fetch('SELECT * FROM registration_invites WHERE id = ?', [$invite['id']]);
    assertSame(1, (int) $used['used_count']);
    assertSame('used', InviteService::status($used));

    $reuseRejected = false;
    try {
        $service->lockRedeemable($invite['code'], 'dean');
    } catch (InvalidArgumentException $e) {
        $reuseRejected = true;
    }
    assertTrue($reuseRejected, 'a consumed single-use code cannot be redeemed again');

    $revokedInvite = $service->create('dean', 2, 2, 'unit-test revoke');
    assertTrue($service->revoke((int) $revokedInvite['id']), 'active code can be revoked');
    $revoked = Database::fetch('SELECT * FROM registration_invites WHERE id = ?', [$revokedInvite['id']]);
    assertSame('revoked', InviteService::status($revoked));

    $revokedRejected = false;
    try {
        $service->lockRedeemable($revokedInvite['code'], 'dean');
    } catch (InvalidArgumentException $e) {
        $revokedRejected = true;
    }
    assertTrue($revokedRejected, 'a revoked code cannot be redeemed');

    Database::execute('DELETE FROM registration_invites WHERE note IN (?, ?)', ['unit-test invitation', 'unit-test revoke']);
});

test('temporary account passwords set and clear the forced-change flag', function (): void {
    $roleId = (int) Database::scalar('SELECT id FROM roles WHERE slug = "instructor" LIMIT 1', [], 0);
    assertTrue($roleId > 0, 'instructor role exists');

    $username = 'test.temp.' . bin2hex(random_bytes(4));
    $password = Security::temporaryPassword();
    $id = (new User())->createAccount([
        'role_id' => $roleId,
        'username' => $username,
        'email' => $username . '@example.test',
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => 'Temporary',
        'last_name' => 'Instructor',
        'status' => 'active',
        'is_demo' => 0,
        'must_change_password' => 1,
    ]);

    $row = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    assertSame(1, (int) $row['must_change_password'], 'temporary password requires a change');
    assertTrue(password_verify($password, $row['password_hash']), 'temporary password hash verifies');

    $newPassword = 'ReplacedPass123';
    (new User())->changePassword($id, $newPassword);
    $row = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    assertSame(0, (int) $row['must_change_password'], 'changing the password clears the forced-change flag');
    assertTrue(password_verify($newPassword, $row['password_hash']), 'new password hash verifies');
    assertFalse(password_verify($password, $row['password_hash']), 'old temporary password no longer verifies');

    Database::execute('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
    Database::execute('DELETE FROM users WHERE id = ?', [$id]);
});
