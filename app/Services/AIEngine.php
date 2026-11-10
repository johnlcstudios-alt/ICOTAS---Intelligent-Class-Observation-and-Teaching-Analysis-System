<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;

/**
 * Rule-Based AI-Assisted Sentiment and Theme Analysis.
 *
 * This is a transparent, deterministic lexicon and clause-scoring engine. It is
 * not a trained transformer, deep-learning model, or external AI service. The
 * interface is intentionally narrow so a future trained model can be adapted
 * behind analyze() without changing the rest of ICOTAS.
 */
final class AIEngine
{
    public const VERSION = 'rule-v1.1';

    private const POSITIVE = [
        'clear' => 1.0, 'clearly' => 1.0, 'explains' => 0.9, 'explained' => 0.9, 'explanation' => 0.7,
        'helpful' => 1.0, 'help' => 0.7, 'helps' => 0.7, 'engaging' => 1.1, 'engaged' => 0.8,
        'knowledgeable' => 1.0, 'patient' => 0.9, 'approachable' => 1.0, 'organized' => 0.9,
        'encouraging' => 1.0, 'encourages' => 0.9, 'excellent' => 1.3, 'great' => 1.1, 'good' => 0.8,
        'amazing' => 1.3, 'effective' => 1.0, 'understandable' => 1.0, 'interactive' => 0.9,
        'fair' => 0.9, 'inspiring' => 1.1, 'supportive' => 1.0, 'well-prepared' => 1.0, 'prepared' => 0.7,
        'lively' => 0.8, 'fun' => 0.8, 'concise' => 0.8, 'thorough' => 0.8, 'respectful' => 1.0,
        'accessible' => 0.8, 'responsive' => 1.0, 'practical' => 0.8, 'easy' => 0.8, 'learned' => 0.8,
        'love' => 1.1, 'like' => 0.5, 'appreciate' => 0.9, 'commend' => 1.0, 'outstanding' => 1.4,
        'perfect' => 1.3, 'strong' => 0.8, 'smooth' => 0.7, 'balanced' => 0.7, 'friendly' => 0.9,
        'kind' => 0.8, 'motivating' => 1.0, 'informative' => 0.9, 'flexible' => 0.8, 'dedicated' => 1.0,
        'comfortable' => 0.7, 'enjoy' => 0.9, 'enjoyed' => 0.9, 'best' => 1.2, 'nice' => 0.7,
        'bright' => 0.5, 'talented' => 0.9, 'confident' => 0.7, 'professional' => 0.8, 'attentive' => 0.9,
        'accommodating' => 0.9, 'generous' => 0.7, 'passionate' => 0.9, 'inclusive' => 0.8,
        'magaling' => 1.2, 'mahusay' => 1.2, 'malinaw' => 1.0, 'mabait' => 0.9, 'matulungin' => 1.0,
        'masaya' => 0.7, 'nakakaengganyo' => 1.1, 'naiintindihan' => 1.0, 'madaling' => 0.7,
        'maayos' => 0.8, 'organisado' => 0.9, 'patas' => 0.9, 'aktibo' => 0.7, 'buhay' => 0.6,
        'makatarungan' => 0.9, 'maganda' => 0.9, 'mabuti' => 0.8, 'malaki' => 0.4,
    ];

    private const NEGATIVE = [
        'confusing' => 1.2, 'confused' => 1.1, 'unclear' => 1.2, 'boring' => 1.2, 'bored' => 1.1,
        'fast' => 0.7, 'slow' => 0.8, 'rushed' => 1.0, 'rush' => 0.8, 'disorganized' => 1.2,
        'unprepared' => 1.2, 'monotonous' => 1.1, 'strict' => 0.5, 'unfair' => 1.2, 'difficult' => 0.8,
        'hard' => 0.6, 'inconsistent' => 1.0, 'late' => 0.9, 'absent' => 1.0, 'unresponsive' => 1.2,
        'rude' => 1.3, 'dismissive' => 1.2, 'intimidating' => 1.1, 'vague' => 1.0, 'repetitive' => 0.9,
        'outdated' => 0.9, 'unhelpful' => 1.2, 'poor' => 1.1, 'bad' => 1.0, 'worst' => 1.4,
        'dislike' => 1.0, 'hate' => 1.3, 'frustrating' => 1.1, 'struggle' => 0.9, 'struggles' => 0.9,
        'fail' => 0.9, 'failed' => 0.9, 'lacking' => 0.9, 'noisy' => 0.7, 'chaotic' => 1.1,
        'tedious' => 0.9, 'complicated' => 0.9, 'unexplained' => 1.1, 'skipped' => 1.0,
        'ignores' => 1.1, 'ignored' => 1.1, 'unapproachable' => 1.2, 'biased' => 1.2,
        'favoritism' => 1.3, 'delay' => 0.7, 'delayed' => 0.7, 'mahirap' => 0.9, 'magulo' => 1.1,
        'mabagal' => 0.9, 'nakakainip' => 1.2, 'nakakalito' => 1.2, 'hindi' => 0.0, 'walang' => 0.7,
        'ulyo' => 1.0, 'saway' => 0.7, 'mabilis' => 0.6,
    ];

    private const PHRASES_POSITIVE = [
        'very clear' => 1.5, 'really clear' => 1.5, 'clear examples' => 1.2, 'easily understood' => 1.3,
        'well organized' => 1.2, 'well prepared' => 1.2, 'hands-on' => 1.0, 'one on one' => 0.8,
        'makes me understand' => 1.2, 'easy to understand' => 1.3, 'willing to help' => 1.1,
        'answers questions' => 1.0, 'encourages participation' => 1.1,
    ];

    private const PHRASES_NEGATIVE = [
        'too fast' => -1.2, 'moves too fast' => -1.3, 'not clear' => -1.4, 'not explained' => -1.3,
        'does not explain' => -1.3, 'lack of examples' => -1.1, 'no examples' => -1.0,
        'reading from slides' => -1.0, 'not helpful' => -1.2, 'not fair' => -1.2,
        'does not respond' => -1.2, 'hard to follow' => -1.1, 'too difficult' => -1.0,
        'hindi malinaw' => -1.4, 'hindi maintindihan' => -1.4, 'hindi patas' => -1.2,
        'masyadong mabilis' => -1.2, 'masyadong mahirap' => -1.0, 'walang paliwanag' => -1.2,
    ];

    private const NEGATORS = ['not', 'no', 'never', 'hardly', 'rarely', 'barely', "isn't", "wasn't", "doesn't", "didn't", "don't", "can't", 'cannot', 'without', 'hindi', 'wala', 'walang'];

    private const INTENSIFIERS = ['very', 'really', 'extremely', 'so', 'too', 'highly', 'super', 'incredibly', 'quite', 'somewhat', 'masyado', 'napaka'];

    private const CONTRAST = ['but', 'however', 'although', 'though', 'yet', 'except', 'while', 'pero', 'ngunit', 'bagaman'];

    private const THEME_KEYWORDS = [
        'Teaching Clarity' => ['clear', 'clarity', 'explain', 'explains', 'explained', 'explanation', 'example', 'examples', 'understand', 'understood', 'understandable', 'concept', 'concepts', 'demonstrate', 'illustrate', 'simplify', 'straightforward', 'detailed', 'malinaw', 'paliwanag', 'naiintindihan'],
        'Communication' => ['communicate', 'communication', 'speak', 'speaks', 'speaking', 'voice', 'tone', 'accent', 'loud', 'soft', 'articulate', 'instruction', 'instructions', 'language', 'english', 'filipino', 'pronounce', 'message', 'messages'],
        'Classroom Engagement' => ['engage', 'engaging', 'engaged', 'interactive', 'activity', 'activities', 'participate', 'participation', 'motivate', 'motivating', 'interest', 'interesting', 'fun', 'lively', 'discussion', 'group', 'collaborative', 'hands-on', 'nakakaengganyo', 'aktibo'],
        'Lesson Pacing' => ['pace', 'pacing', 'fast', 'slow', 'rushed', 'time', 'timing', 'quick', 'quickly', 'hurried', 'lag', 'delay', 'overtime', 'mabilis', 'mabagal'],
        'Teaching Methodology' => ['method', 'methodology', 'strategy', 'approach', 'teaching style', 'lecture', 'discussion', 'demonstration', 'module', 'lesson plan', 'structure', 'structured', 'organized', 'pedagogy', 'activity', 'activities'],
        'Classroom Interaction' => ['ask', 'asks', 'question', 'questions', 'answer', 'answers', 'interact', 'interaction', 'approachable', 'friendly', 'available', 'consultation', 'consult', 'feedback', 'encourage', 'encourages', 'rapport', 'respect', 'listen', 'listens'],
        'Learning Materials' => ['material', 'materials', 'handout', 'handouts', 'slide', 'slides', 'module', 'modules', 'visual', 'visuals', 'resource', 'resources', 'textbook', 'notes', 'pdf', 'worksheet'],
        'Assessment Methods' => ['exam', 'exams', 'quiz', 'quizzes', 'test', 'tests', 'assessment', 'grade', 'grading', 'requirement', 'requirements', 'project', 'rubric', 'score', 'scores', 'deadline', 'deadlines'],
        'Instructor Responsiveness' => ['responsive', 'response', 'reply', 'replies', 'available', 'availability', 'consultation', 'message', 'email', 'approachable', 'help', 'helps', 'assist', 'attentive', 'listen', 'listens', 'concern', 'concerns'],
        'Subject Comprehension' => ['learn', 'learned', 'learning', 'understand', 'knowledge', 'mastery', 'subject', 'topic', 'concept', 'grasp', 'improve', 'skill', 'skills', 'lesson', 'lessons', 'naiintindihan'],
        'Overall Satisfaction' => ['overall', 'satisfied', 'satisfaction', 'recommend', 'experience', 'class', 'course', 'teacher', 'instructor'],
    ];

    public function analyze(string $text): array
    {
        $original = Security::cleanText($text, 5000);
        $normalized = mb_strtolower($original);
        $excerpt = Security::anonymizeComment(str_limit($original, 300));
        $base = [
            'label' => 'neutral',
            'score' => 0.0,
            'positive_score' => 0.0,
            'negative_score' => 0.0,
            'confidence' => 0,
            'themes' => [],
            'positive_themes' => [],
            'negative_themes' => [],
            'keywords' => [],
            'excerpt' => $excerpt,
            'engine_version' => self::VERSION,
        ];

        if ($normalized === '' || !preg_match('/[a-z]/i', $normalized)) {
            return $base;
        }

        $clauses = $this->clauses($normalized);
        $positive = 0.0;
        $negative = 0.0;
        $hits = 0;
        $keywords = [];
        $themeMap = [];

        foreach ($clauses as $clause) {
            [$clausePos, $clauseNeg, $clauseKeywords] = $this->scoreClause($clause);
            $positive += $clausePos;
            $negative += $clauseNeg;
            if ($clausePos > 0 || $clauseNeg > 0) {
                $hits += max(1, (int) ceil(($clausePos + $clauseNeg) / 1.5));
            }
            foreach ($clauseKeywords as $keyword) {
                $keywords[$keyword] = ($keywords[$keyword] ?? 0) + 1;
            }

            $polarity = $clausePos > $clauseNeg ? 'positive' : ($clauseNeg > $clausePos ? 'negative' : 'neutral');
            $matchedThemes = $this->themesIn($clause);
            foreach ($matchedThemes as $theme => $themeKeywords) {
                $key = $theme . '|' . $polarity;
                if (!isset($themeMap[$key])) {
                    $themeMap[$key] = ['theme' => $theme, 'polarity' => $polarity, 'weight' => 0.0, 'keywords' => []];
                }
                $weight = max(0.2, ($clausePos + $clauseNeg) / max(1, count($matchedThemes)));
                $themeMap[$key]['weight'] += $weight;
                $themeMap[$key]['keywords'] = array_values(array_unique(array_merge($themeMap[$key]['keywords'], $themeKeywords)));
            }
        }

        $positive = round($positive, 2);
        $negative = round($negative, 2);
        $label = 'neutral';
        if ($positive >= 0.75 && $negative >= 0.75) {
            $label = 'mixed';
        } elseif ($positive > 0.0 && $positive >= $negative) {
            $label = 'positive';
        } elseif ($negative > 0.0) {
            $label = 'negative';
        } elseif ($positive > 0.0) {
            $label = 'positive';
        }

        $total = $positive + $negative;
        $score = $total > 0 ? round(($positive - $negative) / $total, 3) : 0.0;
        $confidence = $total > 0 ? min(95, 40 + (int) round(min(40, $total * 8)) + min(15, $hits * 2)) : 0;

        $themes = array_values($themeMap);
        usort($themes, static fn ($a, $b) => $b['weight'] <=> $a['weight']);
        $positiveThemes = [];
        $negativeThemes = [];
        foreach ($themes as $theme) {
            if ($theme['polarity'] === 'positive') {
                $positiveThemes[$theme['theme']] = round($theme['weight'], 2);
            } elseif ($theme['polarity'] === 'negative') {
                $negativeThemes[$theme['theme']] = round($theme['weight'], 2);
            }
        }
        arsort($keywords);

        return array_merge($base, [
            'label' => $label,
            'score' => $score,
            'positive_score' => $positive,
            'negative_score' => $negative,
            'confidence' => $confidence,
            'themes' => $themes,
            'positive_themes' => $positiveThemes,
            'negative_themes' => $negativeThemes,
            'keywords' => array_keys(array_slice($keywords, 0, 8, true)),
        ]);
    }

    public function analyzeAndStore(int $responseId, string $text): array
    {
        $result = $this->analyze($text);
        Database::transaction(function () use ($responseId, $text, $result) {
            Database::execute(
                'INSERT INTO sentiment_results
                 (response_id, sentiment_label, sentiment_score, positive_score, negative_score, confidence, engine_version, excerpt, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE sentiment_label = VALUES(sentiment_label), sentiment_score = VALUES(sentiment_score),
                   positive_score = VALUES(positive_score), negative_score = VALUES(negative_score),
                   confidence = VALUES(confidence), engine_version = VALUES(engine_version), excerpt = VALUES(excerpt)',
                [$responseId, $result['label'], $result['score'], $result['positive_score'], $result['negative_score'], $result['confidence'], self::VERSION, $result['excerpt']]
            );
            Database::execute('DELETE FROM feedback_themes WHERE response_id = ?', [$responseId]);
            foreach ($result['themes'] as $theme) {
                Database::execute(
                    'INSERT INTO feedback_themes (response_id, theme, polarity, weight, matched_keywords, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())',
                    [$responseId, $theme['theme'], $theme['polarity'], round((float) $theme['weight'], 3), str_limit(implode(', ', $theme['keywords']), 490)]
                );
            }
            Database::execute('UPDATE evaluation_responses SET sentiment_status = "analyzed" WHERE id = ?', [$responseId]);
        });
        return $result;
    }

    /**
     * Build a readable, evidence-bound executive summary. No claim is made when
     * the database does not contain supporting responses.
     */
    public function summarize(array $sentimentDistribution, array $themeRows, array $categoryAverages, int $responseCount, ?float $comparison = null): array
    {
        $total = array_sum(array_map(static fn ($row) => (int) ($row['count'] ?? 0), $sentimentDistribution));
        $counts = ['positive' => 0, 'neutral' => 0, 'negative' => 0, 'mixed' => 0];
        foreach ($sentimentDistribution as $row) {
            $label = strtolower((string) ($row['label'] ?? 'neutral'));
            if (isset($counts[$label])) {
                $counts[$label] = (int) ($row['count'] ?? 0);
            }
        }
        $dominant = 'neutral';
        $max = -1;
        foreach ($counts as $label => $count) {
            if ($count > $max) {
                $max = $count;
                $dominant = $label;
            }
        }
        if ($counts['mixed'] > 0 && $counts['negative'] > 0 && $counts['positive'] > 0) {
            $dominant = 'mixed';
        }

        $strengths = [];
        $concerns = [];
        foreach ($themeRows as $row) {
            $theme = (string) $row['theme'];
            $polarity = (string) $row['polarity'];
            $count = (int) ($row['count'] ?? 0);
            if ($polarity === 'positive') {
                $strengths[$theme] = ($strengths[$theme] ?? 0) + $count;
            } elseif ($polarity === 'negative') {
                $concerns[$theme] = ($concerns[$theme] ?? 0) + $count;
            }
        }
        arsort($strengths);
        arsort($concerns);

        $lowCategories = [];
        foreach ($categoryAverages as $row) {
            $avg = isset($row['average']) ? (float) $row['average'] : null;
            $category = (string) ($row['category'] ?? 'General');
            if ($avg !== null && $avg < 3.5) {
                $lowCategories[$category] = round($avg, 2);
            }
        }
        arsort($lowCategories);

        $recommendations = [];
        foreach ($concerns as $theme => $count) {
            if ($count < 1) {
                continue;
            }
            $playbook = $this->playbook($theme);
            $recommendations[] = [
                'theme' => $theme,
                'concern' => $playbook['concern'],
                'supporting_metric' => $count . ' negative theme mention' . ($count === 1 ? '' : 's'),
                'recommendation' => $playbook['recommendation'],
                'reason' => $playbook['reason'],
                'priority' => $count >= 3 || ($lowCategories[$theme] ?? 5) < 3.0 ? 'high' : 'medium',
            ];
        }
        foreach ($lowCategories as $category => $average) {
            if (isset($concerns[$category])) {
                continue;
            }
            $playbook = $this->playbook($category);
            $recommendations[] = [
                'theme' => $category,
                'concern' => $playbook['concern'],
                'supporting_metric' => 'Category average ' . number_format($average, 2) . ' out of 5.00',
                'recommendation' => $playbook['recommendation'],
                'reason' => $playbook['reason'],
                'priority' => $average < 3.0 ? 'high' : 'medium',
            ];
        }
        $recommendations = array_slice($recommendations, 0, 5);

        $confidence = $this->aggregateConfidence($counts, $total);
        $minResponses = \App\Core\Setting::privacyThreshold();
        $narrative = '';
        if ($responseCount === 0 || $total === 0) {
            $narrative = 'No written feedback has been analyzed yet. Sentiment and theme statements will appear once anonymous comments are submitted.';
        } else {
            $narrative = 'Based on ' . $total . ' written response' . ($total === 1 ? '' : 's') . ', feedback is predominantly '
                . $dominant . '. '
                . (!empty($strengths) ? 'Recurring strengths: ' . implode(', ', array_slice(array_keys($strengths), 0, 3)) . '. ' : '')
                . (!empty($concerns) ? 'Recurring concerns: ' . implode(', ', array_slice(array_keys($concerns), 0, 3)) . '. ' : '');
            if ($comparison !== null) {
                $direction = $comparison > 0.05 ? 'improved' : ($comparison < -0.05 ? 'declined' : 'remained stable');
                $narrative .= 'Compared with the preceding period, the rating has ' . $direction . ' by ' . number_format(abs($comparison), 2) . ' points. ';
            }
            $narrative .= 'These findings are rule-assisted signals, not a substitute for academic judgment.';
        }
        if ($responseCount < $minResponses) {
            $narrative .= ' The response count is below the institutional privacy threshold; interpret with caution.';
        }

        return [
            'dominant_sentiment' => $dominant,
            'distribution' => $counts,
            'strengths' => $strengths,
            'concerns' => $concerns,
            'frequent_themes' => array_slice(array_keys($strengths + $concerns), 0, 5),
            'low_categories' => $lowCategories,
            'recommendations' => $recommendations,
            'narrative' => $narrative,
            'confidence' => $confidence,
            'response_count' => $responseCount,
            'analyzed_comments' => $total,
            'thresholds' => ['privacy_min_responses' => $minResponses],
            'engine' => 'Rule-Based AI-Assisted Sentiment and Theme Analysis (' . self::VERSION . ')',
        ];
    }

    public function playbook(string $theme): array
    {
        $map = [
            'Teaching Clarity' => [
                'concern' => 'Students may benefit from clearer explanations of complex concepts.',
                'recommendation' => 'Add concrete examples, simplify multi-step explanations, and end major topics with a short summary or comprehension check.',
                'reason' => 'Clarity-related feedback indicates that explanation quality is influencing student understanding.',
            ],
            'Communication' => [
                'concern' => 'Instructional communication may not always be reaching every student.',
                'recommendation' => 'State activity instructions in both spoken and written form, check for understanding, and invite clarifying questions.',
                'reason' => 'Communication themes recur in written feedback and can affect access to the lesson.',
            ],
            'Classroom Engagement' => [
                'concern' => 'Student engagement can be strengthened with more active participation.',
                'recommendation' => 'Introduce short collaborative exercises, think-pair-share prompts, or quick polls to increase active learning.',
                'reason' => 'Engagement themes and lower engagement ratings indicate room for more interactive activities.',
            ],
            'Lesson Pacing' => [
                'concern' => 'Lesson pacing may be too fast or uneven for some learners.',
                'recommendation' => 'Allocate additional time to difficult concepts, pause after dense segments, and use short comprehension checks before moving on.',
                'reason' => 'Pacing concerns can produce gaps in understanding even when the content is well prepared.',
            ],
            'Teaching Methodology' => [
                'concern' => 'Instructional strategies may need more variety.',
                'recommendation' => 'Blend direct instruction with guided practice, worked examples, and student-led discussion; align activities to lesson outcomes.',
                'reason' => 'Methodology feedback suggests that the balance of instructional approaches can be improved.',
            ],
            'Classroom Interaction' => [
                'concern' => 'Classroom interaction can be made more inclusive and responsive.',
                'recommendation' => 'Use open questions, allow wait time, invite quieter students, and make consultation times explicit.',
                'reason' => 'Interaction themes reflect how comfortable students feel asking questions and participating.',
            ],
            'Learning Materials' => [
                'concern' => 'Learning materials may not fully support the lesson.',
                'recommendation' => 'Provide structured handouts or slides with key points, worked examples, and references; remove overcrowded text from visual aids.',
                'reason' => 'Material-related feedback points to a practical resource gap that is straightforward to address.',
            ],
            'Assessment Methods' => [
                'concern' => 'Assessment expectations or feedback could be clearer.',
                'recommendation' => 'Share rubrics before tasks, explain grading criteria, and return feedback with one concrete next step.',
                'reason' => 'Assessment clarity affects students\' sense of fairness and their ability to improve.',
            ],
            'Instructor Responsiveness' => [
                'concern' => 'Students would value more timely or accessible instructor support.',
                'recommendation' => 'Publish consultation hours, set a response window for messages, and address recurring questions in class.',
                'reason' => 'Responsiveness themes indicate that accessibility of support can be improved.',
            ],
            'Subject Comprehension' => [
                'concern' => 'Students may be leaving lessons with uneven subject mastery.',
                'recommendation' => 'Use retrieval practice and brief formative checks, revisit prerequisite concepts, and provide targeted practice.',
                'reason' => 'Comprehension themes and ratings suggest knowledge gaps that formative checks can surface early.',
            ],
            'Overall Satisfaction' => [
                'concern' => 'Overall satisfaction can be improved through consistent follow-through.',
                'recommendation' => 'Review the lowest-rated category, select one improvement action, and track participation and comments over the next evaluation period.',
                'reason' => 'Overall satisfaction is a composite signal; a focused improvement target is more actionable than multiple simultaneous changes.',
            ],
            'General' => [
                'concern' => 'General feedback indicates an opportunity to strengthen teaching practice.',
                'recommendation' => 'Review the anonymized themes and ratings, choose one measurable change, and monitor the next evaluation cycle.',
                'reason' => 'A specific, measurable change is easier to evaluate than a broad set of changes.',
            ],
        ];
        return $map[$theme] ?? $map['General'];
    }

    public function themeList(): array
    {
        return array_keys(self::THEME_KEYWORDS);
    }

    private function clauses(string $text): array
    {
        $text = str_replace(["\n", ';', ',', '!', '?'], '.', $text);
        $text = preg_replace('/\b(' . implode('|', array_map('preg_quote', self::CONTRAST)) . ')\b/iu', '.', $text) ?? $text;
        $parts = preg_split('/\.+/', $text) ?: [];
        $clauses = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $clauses[] = $part;
            }
        }
        return $clauses;
    }

    private function scoreClause(string $clause): array
    {
        $lower = ' ' . preg_replace('/\s+/', ' ', $clause) . ' ';
        $positive = 0.0;
        $negative = 0.0;
        $keywords = [];

        $phraseHits = [];
        foreach ([self::PHRASES_POSITIVE, self::PHRASES_NEGATIVE] as $phraseSet) {
            foreach ($phraseSet as $phrase => $weight) {
                if (strpos($lower, ' ' . $phrase . ' ') !== false) {
                    if ($weight > 0) {
                        $positive += $weight;
                    } else {
                        $negative += abs($weight);
                    }
                    $keywords[$phrase] = true;
                    $phraseHits = array_merge($phraseHits, explode(' ', $phrase));
                }
            }
        }

        if (!preg_match_all("/[A-Za-z]+(?:'[A-Za-z]+)?/", $lower, $matches)) {
            return [$positive, $negative, array_keys($keywords)];
        }
        $tokens = $matches[0];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (in_array($token, $phraseHits, true)) {
                continue;
            }
            $weight = 0.0;
            $direction = 0;
            if (isset(self::POSITIVE[$token])) {
                $weight = self::POSITIVE[$token];
                $direction = 1;
            } elseif (isset(self::NEGATIVE[$token])) {
                $weight = self::NEGATIVE[$token];
                $direction = -1;
            }
            if ($direction === 0 || $weight <= 0) {
                continue;
            }

            $multiplier = 1.0;
            for ($j = max(0, $i - 2); $j < $i; $j++) {
                $previous = $tokens[$j];
                if (in_array($previous, self::NEGATORS, true)) {
                    $direction *= -1;
                }
                if (in_array($previous, self::INTENSIFIERS, true)) {
                    $multiplier *= 1.5;
                }
            }
            $weight *= $multiplier;
            if ($direction > 0) {
                $positive += $weight;
            } else {
                $negative += $weight;
            }
            $keywords[$token] = true;
        }

        return [round($positive, 3), round($negative, 3), array_keys($keywords)];
    }

    private function themesIn(string $clause): array
    {
        $lower = ' ' . preg_replace('/\s+/', ' ', $clause) . ' ';
        $found = [];
        foreach (self::THEME_KEYWORDS as $theme => $keywords) {
            $matched = [];
            foreach ($keywords as $keyword) {
                $needle = ' ' . $keyword . ' ';
                if (strpos($lower, $needle) !== false
                    || strpos($lower, ' ' . $keyword . 's ') !== false
                    || strpos($lower, ' ' . $keyword . 'd ') !== false) {
                    $matched[] = $keyword;
                }
            }
            if (!empty($matched)) {
                $found[$theme] = array_slice(array_values(array_unique($matched)), 0, 6);
            }
        }
        return $found;
    }

    private function aggregateConfidence(array $counts, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }
        $dominant = max($counts);
        $agreement = $dominant / max(1, $total);
        return (int) min(95, round(35 + ($agreement * 40) + min(20, $total * 1.5)));
    }
}
