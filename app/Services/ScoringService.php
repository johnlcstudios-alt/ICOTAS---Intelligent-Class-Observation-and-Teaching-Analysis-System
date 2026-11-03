<?php

namespace App\Services;

use App\Models\COFCriterion;

/**
 * Authoritative COF scoring. All stored scores are produced here from the
 * individual ratings — client-submitted totals are never trusted.
 *
 * Teaching Competence = sum(13 teaching ratings) / 13
 * Personality         = sum(7 personality ratings) / 7
 * Overall             = sum(20 ratings) / 20
 */
final class ScoringService
{
    /**
     * @param array<int,int> $scores criterion_number => score (1..5)
     * @param array<int,string> $categories criterion_number => category
     * @return array{teaching_avg:?float,personality_avg:?float,overall_avg:?float,
     *               teaching_sum:int,personality_sum:int,overall_sum:int,
     *               teaching_count:int,personality_count:int,overall_count:int}
     */
    public function calculate(array $scores, array $categories = []): array
    {
        if ($categories === []) {
            $categories = $this->defaultCategories();
        }

        $teachingSum = 0;
        $teachingCount = 0;
        $personalitySum = 0;
        $personalityCount = 0;
        $overallSum = 0;
        $overallCount = 0;

        foreach ($scores as $number => $value) {
            $number = (int) $number;
            $value = (int) $value;
            if ($value < 1 || $value > 5) {
                continue;
            }
            $category = $categories[$number] ?? ($number <= COFCriterion::TEACHING_COUNT
                ? COFCriterion::TEACHING
                : COFCriterion::PERSONALITY);

            if ($category === COFCriterion::TEACHING) {
                $teachingSum += $value;
                $teachingCount++;
            } elseif ($category === COFCriterion::PERSONALITY) {
                $personalitySum += $value;
                $personalityCount++;
            }
            $overallSum += $value;
            $overallCount++;
        }

        return [
            'teaching_avg'     => $teachingCount > 0 ? round($teachingSum / $teachingCount, 2) : null,
            'personality_avg'  => $personalityCount > 0 ? round($personalitySum / $personalityCount, 2) : null,
            'overall_avg'      => $overallCount > 0 ? round($overallSum / $overallCount, 2) : null,
            'teaching_sum'     => $teachingSum,
            'personality_sum'  => $personalitySum,
            'overall_sum'      => $overallSum,
            'teaching_count'   => $teachingCount,
            'personality_count'=> $personalityCount,
            'overall_count'    => $overallCount,
        ];
    }

    /**
     * Validate a rating set against the active COF definition.
     *
     * @param array<int,mixed> $scores
     * @return array{valid:bool,errors:array<int,string>,warnings:array<int,string>,scores:array<int,int>}
     */
    public function validate(array $scores, bool $requireComplete = true): array
    {
        $criteria = COFCriterion::active();
        $categories = [];
        foreach ($criteria as $criterion) {
            $categories[(int) $criterion['criterion_number']] = (string) $criterion['category'];
        }

        $errors = [];
        $warnings = [];
        $clean = [];

        if ($criteria === []) {
            return [
                'valid'    => false,
                'errors'   => ['criteria' => 'The COF criteria have not been configured.'],
                'warnings' => [],
                'scores'   => [],
            ];
        }

        foreach ($scores as $number => $value) {
            $number = (int) $number;
            if (!isset($categories[$number])) {
                $warnings[] = "Criterion {$number} is not part of the active COF and was ignored.";
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (!is_numeric($value) || (int) $value != $value) {
                $errors[$number] = "Criterion {$number} must be a whole number from 1 to 5.";
                continue;
            }
            $value = (int) $value;
            if ($value < 1 || $value > 5) {
                $errors[$number] = "Criterion {$number} must be between 1 and 5.";
                continue;
            }
            $clean[$number] = $value;
        }

        if ($requireComplete) {
            $expected = COFCriterion::countsForValidation();
            if ($expected[COFCriterion::TEACHING] !== COFCriterion::TEACHING_COUNT
                || $expected[COFCriterion::PERSONALITY] !== COFCriterion::PERSONALITY_COUNT) {
                $errors['structure'] = 'The active COF structure must contain exactly 13 Teaching Competence and 7 Personality criteria before an observation can be submitted.';
            }

            $missing = [];
            foreach ($categories as $number => $category) {
                if (!array_key_exists($number, $clean)) {
                    $missing[] = $number;
                }
            }
            if ($missing !== []) {
                $errors['ratings'] = 'All 20 criteria must be rated before submission. Missing: ' . implode(', ', $missing) . '.';
            }

            $teaching = 0;
            $personality = 0;
            foreach ($clean as $number => $score) {
                if (($categories[$number] ?? '') === COFCriterion::TEACHING) {
                    $teaching++;
                } else {
                    $personality++;
                }
            }
            if (count($clean) === COFCriterion::TOTAL_COUNT
                && ($teaching !== COFCriterion::TEACHING_COUNT || $personality !== COFCriterion::PERSONALITY_COUNT)) {
                $errors['categories'] = 'The rating set must contain exactly 13 Teaching Competence and 7 Personality ratings.';
            }
        }

        if ($errors !== []) {
            $clean = array_filter(
                $clean,
                static fn (int $number): bool => !isset($errors[$number]),
                ARRAY_FILTER_USE_KEY
            );
        }

        ksort($clean);

        return [
            'valid'    => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
            'scores'   => $clean,
        ];
    }

    public function summaryLabel(?float $average): string
    {
        if ($average === null) {
            return 'Not rated';
        }
        return match (true) {
            $average >= 4.50 => 'Outstanding',
            $average >= 3.50 => 'Very Satisfactory',
            $average >= 2.50 => 'Satisfactory',
            $average >= 1.50 => 'Fair',
            default          => 'Needs Improvement',
        };
    }

    /** @return array<int,string> */
    private function defaultCategories(): array
    {
        $categories = [];
        for ($i = 1; $i <= COFCriterion::TOTAL_COUNT; $i++) {
            $categories[$i] = $i <= COFCriterion::TEACHING_COUNT
                ? COFCriterion::TEACHING
                : COFCriterion::PERSONALITY;
        }
        return $categories;
    }
}
