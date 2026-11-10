<?php

declare(strict_types=1);

use App\Services\AIEngine;

test('AI engine labels clearly positive feedback', function (): void {
    $result = (new AIEngine())->analyze('The instructor explains clearly, is very helpful, and the activities are engaging.');
    assertSame('positive', $result['label']);
    assertGreaterThan(0, $result['positive_score']);
    assertSame(0.0, (float) $result['negative_score']);
    assertGreaterThan(0, $result['confidence']);
});

test('AI engine labels clearly negative feedback', function (): void {
    $result = (new AIEngine())->analyze('The lectures are confusing, the pace is too fast, and the slides are disorganized.');
    assertSame('negative', $result['label']);
    assertGreaterThan(0, $result['negative_score']);
    assertSame(0.0, (float) $result['positive_score']);
});

test('AI engine detects mixed sentiment instead of averaging it away', function (): void {
    $result = (new AIEngine())->analyze(
        'The instructor explains clearly and is very helpful, but the pace is too fast and the slides are disorganized.'
    );
    assertSame('mixed', $result['label']);
    assertTrue($result['positive_score'] >= 0.75, 'positive score should reach the mixed threshold');
    assertTrue($result['negative_score'] >= 0.75, 'negative score should reach the mixed threshold');
    assertTrue(isset($result['positive_themes']['Teaching Clarity']), 'expected a positive clarity theme');
    assertTrue(isset($result['negative_themes']['Lesson Pacing']), 'expected a negative pacing theme');
});

test('AI engine handles neutral text and empty input safely', function (): void {
    $engine = new AIEngine();
    $neutral = $engine->analyze('The class meets on Monday and Thursday.');
    assertSame('neutral', $neutral['label']);
    assertSame(0.0, (float) $neutral['score']);

    $empty = $engine->analyze('   ');
    assertSame('neutral', $empty['label']);
    assertSame('', $empty['excerpt']);
    assertSame([], $empty['themes']);

    $symbols = $engine->analyze('!!! 12345 ???');
    assertSame('neutral', $symbols['label']);
});

test('AI engine exposes its transparent rule-based version', function (): void {
    $result = (new AIEngine())->analyze('Very clear discussion.');
    assertSame(AIEngine::VERSION, $result['engine_version']);
    assertSame('rule-v1.1', $result['engine_version']);
    assertTrue($result['confidence'] <= 95, 'confidence must stay capped at 95');
});

test('AI engine recognizes Filipino sentiment terms', function (): void {
    $result = (new AIEngine())->analyze('Magaling at malinaw magturo ang guro, napaka-approachable pa.');
    assertSame('positive', $result['label']);
    assertGreaterThan(0, $result['positive_score']);
});
