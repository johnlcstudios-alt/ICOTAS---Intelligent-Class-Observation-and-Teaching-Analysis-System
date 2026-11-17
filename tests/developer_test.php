<?php

declare(strict_types=1);

use App\Services\DeveloperDataService;

/**
 * The developer console sits outside user RBAC and is protected only by the
 * high-entropy URL key. These tests focus on the safety boundary that matters
 * most in code: schema-derived identifiers, masked secrets, prepared values
 * and the one-statement SQL guardrails.
 */

test('developer schema metadata exposes tables but masks protected columns', function (): void {
    $service = new DeveloperDataService();

    $tables = array_column($service->tables(), 'name');
    assertTrue(in_array('users', $tables, true), 'the active database tables are enumerable');
    assertTrue($service->tableExists('system_settings'), 'settings are available to the browser');
    assertFalse($service->tableExists('information_schema.tables'), 'other schemas are not whitelisted');

    $columns = [];
    foreach ($service->columns('users') as $column) {
        $columns[$column['name']] = $column;
    }

    assertTrue(isset($columns['password_hash']), 'the users table has a password hash column');
    assertTrue($columns['password_hash']['is_sensitive'], 'password hashes are marked sensitive');
    assertFalse($columns['password_hash']['editable'], 'password hashes cannot be edited in the generic browser');
    assertSame('readonly', $columns['password_hash']['input_type'], 'sensitive columns render read-only');
    assertFalse($columns['id']['editable'], 'auto-increment primary keys are not editable');

    assertSame('foreign', $columns['role_id']['input_type'], 'foreign keys render as lookups');
    $options = $service->lookupOptions($columns['role_id']);
    assertTrue($options !== [], 'foreign lookups return options');
    assertTrue(isset($options[0]['value'], $options[0]['label']), 'lookup options carry a value and label');

    assertSame('id', $service->primaryKey('users'), 'the single primary key is detected');
});

test('developer data browser creates, updates and deletes a row with prepared statements', function (): void {
    $service = new DeveloperDataService();
    $key = 'unit_dev_' . bin2hex(random_bytes(5));

    $created = $service->create('system_settings', [
        'setting_key' => $key,
        'setting_value' => 'alpha',
        'setting_group' => 'unit_test',
        'id' => 999999,
    ]);
    assertTrue($created['ok'], 'a valid row can be inserted: ' . ($created['error'] ?? ''));
    $id = (int) $created['id'];
    assertTrue($id > 0, 'the database assigns an auto-increment id');

    $row = $service->find('system_settings', (string) $id);
    assertTrue($row !== null, 'the inserted row can be read back');
    assertSame($key, (string) $row['setting_key'], 'the supplied value is stored');
    assertSame('alpha', (string) $row['setting_value'], 'the initial value is stored');
    assertNotSame(999999, $id, 'a supplied primary key is ignored for auto-increment tables');

    $updated = $service->update('system_settings', (string) $id, ['setting_value' => 'beta']);
    assertTrue($updated['ok'], 'an editable value can be updated: ' . ($updated['error'] ?? ''));
    $row = $service->find('system_settings', (string) $id);
    assertSame('beta', (string) $row['setting_value'], 'the update is persisted');

    $invalid = $service->create('system_settings', ['setting_key' => ['not', 'a', 'string']]);
    assertFalse($invalid['ok'], 'array values are rejected before SQL execution');

    $deleted = $service->delete('system_settings', (string) $id);
    assertTrue($deleted['ok'], 'the row can be deleted: ' . ($deleted['error'] ?? ''));
    assertSame(null, $service->find('system_settings', (string) $id), 'the deleted row is gone');
});

test('developer SQL console allows reads and one guarded write', function (): void {
    $service = new DeveloperDataService();

    $read = $service->executeSql('SELECT 1 AS one');
    assertTrue($read['ok'], 'a simple read succeeds: ' . ($read['error'] ?? ''));
    assertSame('1', (string) $read['rows'][0]['one'], 'the read returns its row');
    assertTrue($read['read'], 'the result is classified as a read');

    $key = 'unit_sql_' . bin2hex(random_bytes(5));
    $created = $service->create('system_settings', [
        'setting_key' => $key,
        'setting_value' => 'before',
        'setting_group' => 'unit_test',
    ]);
    assertTrue($created['ok'], 'a temporary setting is prepared for the write test');
    $id = (int) $created['id'];

    $write = $service->executeSql(
        'UPDATE system_settings SET setting_value = "after" WHERE id = ' . $id,
        '1'
    );
    assertTrue($write['ok'], 'a confirmed UPDATE with WHERE succeeds: ' . ($write['error'] ?? ''));
    assertSame(1, (int) $write['affected'], 'one row is affected');
    assertSame('after', (string) \App\Core\Database::scalar('SELECT setting_value FROM system_settings WHERE id = ?', [$id]), 'the write is persisted');

    $cleanup = $service->executeSql('DELETE FROM system_settings WHERE id = ' . $id, '1');
    assertTrue($cleanup['ok'], 'the temporary row can be removed with confirmed SQL: ' . ($cleanup['error'] ?? ''));
});

test('developer SQL console refuses unsafe statements', function (): void {
    $service = new DeveloperDataService();

    $multiple = $service->executeSql('SELECT 1; SELECT 2');
    assertFalse($multiple['ok'], 'multiple statements are rejected');
    assertContains('one statement', (string) ($multiple['error'] ?? ''));

    $blocked = $service->executeSql('SET @unit = 1');
    assertFalse($blocked['ok'], 'server-level SET statements are rejected');

    $outfile = $service->executeSql('SELECT 1 INTO OUTFILE "/tmp/icotas-unit.txt"');
    assertFalse($outfile['ok'], 'file-output statements are rejected');
    assertContains('server-level', (string) ($outfile['error'] ?? ''));

    $schema = $service->executeSql('SELECT * FROM mysql.user');
    assertFalse($schema['ok'], 'cross-schema reads are rejected');
    assertContains('active application database', (string) ($schema['error'] ?? ''));

    $unboundedUpdate = $service->executeSql('UPDATE system_settings SET setting_value = "x"', '1');
    assertFalse($unboundedUpdate['ok'], 'UPDATE without WHERE is rejected');
    assertContains('WHERE', (string) ($unboundedUpdate['error'] ?? ''));

    $unconfirmed = $service->executeSql('UPDATE system_settings SET setting_value = "x" WHERE id = 1');
    assertFalse($unconfirmed['ok'], 'writes need explicit confirmation');
    assertContains('Confirm', (string) ($unconfirmed['error'] ?? ''));

    $destructive = $service->executeSql('TRUNCATE system_settings', '1', '');
    assertFalse($destructive['ok'], 'destructive DDL needs the typed confirmation phrase');
    assertContains('CONFIRM', (string) ($destructive['error'] ?? ''));
});

test('developer data browser paginates and searches without altering records', function (): void {
    $service = new DeveloperDataService();
    $before = (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM roles', [], 0);

    $page = $service->paginate('roles', 1, 25);
    assertSame($before, (int) $page['total'], 'pagination reports the full row count');
    assertSame(25, (int) $page['per_page'], 'the requested page size is applied');
    assertSame($before, count($page['rows']), 'every role fits on the first page');

    $search = $service->paginate('roles', 1, 25, 'Dean');
    assertGreaterThan(0, (int) $search['total'], 'the safe text search finds the Dean role');
    foreach ($search['rows'] as $row) {
        assertTrue(str_contains(strtolower((string) $row['name'] . ' ' . (string) $row['slug']), 'dean'), 'search results match the term');
    }

    assertSame($before, (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM roles', [], 0), 'reading does not alter the table');
    assertSame('icotas_test', (string) $service->databaseStats()['name'], 'stats are scoped to the test database');
});
