<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Models\User;

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
