<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Evaluation;
use App\Services\QRService;

test('QR session creation stores only a hash and an encrypted display copy', function (): void {
    $assignment = Database::fetch(
        'SELECT * FROM evaluation_assignments WHERE is_demo = 1 AND status = "active" ORDER BY id LIMIT 1'
    );
    assertTrue($assignment !== null, 'expected a demo assignment');

    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();
    $opens = date('Y-m-d H:i:s', time() - 60);
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $result = $service->createSession((int) $assignment['id'], $opens, $expires, (int) $dean['id'], true);

    assertTrue($result[0], $result[1]);
    $token = $result[2]['token'];
    $session = (new Evaluation())->getSession((int) $result[2]['id']);

    assertSame(hash('sha256', $token), $session['token_hash'], 'lookup hash must be SHA-256 of the token');
    assertNotSame($token, $session['token_cipher'], 'plaintext token must not be stored');
    assertSame($token, $service->tokenForDisplay($session), 'encrypted copy must decrypt for authorized display');
    assertContains($token, $service->evaluationUrl($session));
    assertSame('active', $service->effectiveStatus($session));
    assertGreaterThan(0, $result[2]['students']);
});

test('QR regeneration invalidates the previous printed code', function (): void {
    $assignment = Database::fetch(
        'SELECT * FROM evaluation_assignments WHERE is_demo = 1 ORDER BY id DESC LIMIT 1'
    );
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();
    $created = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    assertTrue($created[0], $created[1]);
    $oldToken = $created[2]['token'];
    $sessionId = (int) $created[2]['id'];

    $regenerated = $service->regenerate($sessionId, (int) $dean['id']);
    assertTrue($regenerated[0], $regenerated[1]);
    $newToken = $regenerated[2];
    assertNotSame($oldToken, $newToken);

    $session = (new Evaluation())->getSession($sessionId);
    assertSame(hash('sha256', $newToken), $session['token_hash']);
    assertSame(null, (new Evaluation())->resolveByToken($oldToken), 'old token must no longer resolve');
    assertTrue((new Evaluation())->resolveByToken($newToken) !== null, 'new token must resolve');
});

test('closing and archiving stop access and block regeneration', function (): void {
    $assignment = Database::fetch('SELECT * FROM evaluation_assignments WHERE is_demo = 1 ORDER BY id LIMIT 1');
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();
    $created = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    $sessionId = (int) $created[2]['id'];

    $closed = $service->close($sessionId, (int) $dean['id']);
    assertTrue($closed[0]);
    $session = (new Evaluation())->getSession($sessionId);
    assertSame('closed', $service->effectiveStatus($session));
    assertFalse($service->isOpen($session));

    $regen = $service->regenerate($sessionId, (int) $dean['id']);
    assertFalse($regen[0], 'closed sessions must not be regenerated');
    assertContains('cannot be regenerated', $regen[1]);

    $archived = $service->archive($sessionId, (int) $dean['id']);
    assertTrue($archived[0]);
    assertSame('archived', $service->effectiveStatus((new Evaluation())->getSession($sessionId)));

    $closedArchive = $service->close($sessionId, (int) $dean['id']);
    assertFalse($closedArchive[0], 'archived sessions must not be reopened by close');
    assertContains('cannot be closed', $closedArchive[1]);
});

test('QR session time validation rejects impossible schedules', function (): void {
    $assignment = Database::fetch('SELECT * FROM evaluation_assignments WHERE is_demo = 1 ORDER BY id LIMIT 1');
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();

    $expired = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 7200),
        date('Y-m-d H:i:s', time() - 3600),
        (int) $dean['id'],
        true
    );
    assertFalse($expired[0], 'an expiry in the past must be rejected');

    $reversed = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() + 3600),
        date('Y-m-d H:i:s', time() + 60),
        (int) $dean['id'],
        true
    );
    assertFalse($reversed[0]);
    assertContains('expiry must be after', $reversed[1]);

    $tooLong = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time()),
        date('Y-m-d H:i:s', time() + (31 * 86400)),
        (int) $dean['id'],
        true
    );
    assertFalse($tooLong[0]);
    assertContains('at most 30 days', $tooLong[1]);

    $opened = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    assertTrue($opened[0], $opened[1]);
    $expiredId = (int) $opened[2]['id'];
    Database::execute(
        'UPDATE evaluation_sessions SET status = "active", expires_at = ? WHERE id = ?',
        [date('Y-m-d H:i:s', time() - 30), $expiredId]
    );
    $regenerated = $service->regenerate($expiredId, (int) $dean['id']);
    assertFalse($regenerated[0], 'expired sessions must not be regenerated');
    assertContains('cannot be regenerated', $regenerated[1]);
});

test('revoking a token closes the session and makes the old link unresolvable', function (): void {
    $assignment = Database::fetch('SELECT * FROM evaluation_assignments WHERE is_demo = 1 ORDER BY id LIMIT 1');
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();
    $created = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() - 60),
        date('Y-m-d H:i:s', time() + 3600),
        (int) $dean['id'],
        true
    );
    assertTrue($created[0], $created[1]);
    $sessionId = (int) $created[2]['id'];
    $oldToken = $created[2]['token'];

    $revoked = $service->revoke($sessionId, (int) $dean['id']);
    assertTrue($revoked[0], $revoked[1]);
    assertContains('no longer works', $revoked[1]);

    $session = (new Evaluation())->getSession($sessionId);
    assertSame('closed', $service->effectiveStatus($session));
    assertSame(null, (new Evaluation())->resolveByToken($oldToken), 'revoked token must not resolve');

    Database::execute('UPDATE evaluation_sessions SET status = "archived" WHERE id = ?', [$sessionId]);
    $archived = $service->revoke($sessionId, (int) $dean['id']);
    assertFalse($archived[0]);
    assertContains('Archived sessions cannot be revoked', $archived[1]);
});

test('scheduled sessions become active only inside their window', function (): void {
    $assignment = Database::fetch('SELECT * FROM evaluation_assignments WHERE is_demo = 1 ORDER BY id LIMIT 1');
    $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
    $service = new QRService();
    $created = $service->createSession(
        (int) $assignment['id'],
        date('Y-m-d H:i:s', time() + 3600),
        date('Y-m-d H:i:s', time() + 7200),
        (int) $dean['id'],
        true
    );
    assertTrue($created[0], $created[1]);
    $session = (new Evaluation())->getSession((int) $created[2]['id']);
    assertSame('scheduled', $service->effectiveStatus($session));
    assertFalse($service->isOpen($session));
    assertTrue($service->evaluationUrl($session) !== '', 'scheduled sessions still hold a display token');
});
