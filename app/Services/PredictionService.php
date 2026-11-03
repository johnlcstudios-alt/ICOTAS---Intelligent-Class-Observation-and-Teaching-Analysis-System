<?php

namespace App\Services;

use App\Models\Observation;

/**
 * Native-PHP Simple Linear Regression for faculty performance forecasting.
 *
 * Model: Y = mX + b, where X is the chronological observation-period index
 * (1..n) and Y is the period-level overall observation average. Multiple
 * observations inside the same academic period are averaged first, so a
 * period with three observations does not outweigh a period with one.
 *
 * Minimum: 5 distinct published periods. Otherwise the model is reported as
 * insufficient and no prediction is produced.
 */
final class PredictionService
{
    public const MIN_PERIODS = 5;
    public const MODEL_NAME = 'Simple Linear Regression v1';

    /**
     * Full analysis for one faculty member.
     *
     * @return array{
     *   faculty_id:int, model_name:string, status:string, sufficient:bool,
     *   reason:?string, periods:array, points:array, period_count:int,
     *   observation_count:int, slope:?float, intercept:?float, next_period:int,
     *   predicted_score:?float, clamped:bool, mae:?float, rmse:?float,
     *   validation_period_count:int, generated_at:string, disclaimer:string
     * }
     */
    public function analyze(int $facultyId): array
    {
        $rows = Observation::periodAveragesForFaculty($facultyId);

        $points = [];
        $observationCount = 0;
        $index = 1;
        foreach ($rows as $row) {
            $overall = $row['overall_avg'];
            if ($overall === null || !is_numeric($overall)) {
                continue;
            }
            $points[] = [
                'index'             => $index,
                'period_key'        => (string) $row['period_key'],
                'academic_year'     => (string) $row['academic_year'],
                'semester'          => (string) $row['semester'],
                'label'             => $row['academic_year'] . ' · ' . semester_abbr((string) $row['semester']),
                'overall_avg'       => round((float) $overall, 2),
                'teaching_avg'      => $row['teaching_avg'] !== null ? round((float) $row['teaching_avg'], 2) : null,
                'personality_avg'   => $row['personality_avg'] !== null ? round((float) $row['personality_avg'], 2) : null,
                'observation_count' => (int) $row['observation_count'],
                'period_start'      => (string) $row['period_start'],
            ];
            $observationCount += (int) $row['observation_count'];
            $index++;
        }

        $periodCount = count($points);
        $sufficient = $periodCount >= self::MIN_PERIODS;

        $result = [
            'faculty_id'               => $facultyId,
            'model_name'               => self::MODEL_NAME,
            'status'                   => $sufficient ? 'Sufficient' : 'Insufficient Data',
            'sufficient'               => $sufficient,
            'reason'                   => $sufficient
                ? null
                : 'At least ' . self::MIN_PERIODS . ' distinct published observation periods are required. Only ' . $periodCount . ' available.',
            'periods'                  => $points,
            'points'                   => $points,
            'period_count'             => $periodCount,
            'observation_count'        => $observationCount,
            'slope'                    => null,
            'intercept'                => null,
            'next_period'              => $periodCount + 1,
            'predicted_score'          => null,
            'clamped'                  => false,
            'mae'                      => null,
            'rmse'                     => null,
            'validation_period_count'  => 0,
            'generated_at'             => date('Y-m-d H:i:s'),
            'disclaimer'               => 'Predictions are estimates based on historical evaluation data, not guarantees. They must be interpreted by the Dean alongside professional judgement and must never be used to penalise faculty automatically.',
        ];

        if (!$sufficient) {
            return $result;
        }

        $xs = array_map(static fn (array $p): float => (float) $p['index'], $points);
        $ys = array_map(static fn (array $p): float => (float) $p['overall_avg'], $points);

        $model = $this->fit($xs, $ys);
        if ($model === null) {
            $result['sufficient'] = false;
            $result['status'] = 'Insufficient Data';
            $result['reason'] = 'The historical averages do not vary enough to fit a regression line.';
            return $result;
        }

        $rawPrediction = ($model['slope'] * ($periodCount + 1)) + $model['intercept'];
        $clamped = $rawPrediction < 1.0 || $rawPrediction > 5.0;

        // Chronological expanding-window validation. For n periods we train on
        // the first k (k >= 3) and predict period k+1, for k = 3..n-1.
        $errors = [];
        for ($k = 3; $k < $periodCount; $k++) {
            $trainX = array_slice($xs, 0, $k);
            $trainY = array_slice($ys, 0, $k);
            $fold = $this->fit($trainX, $trainY);
            if ($fold === null) {
                continue;
            }
            $predicted = ($fold['slope'] * ($k + 1)) + $fold['intercept'];
            $errors[] = $predicted - $ys[$k];
        }

        $mae = null;
        $rmse = null;
        if ($errors !== []) {
            $abs = array_map(static fn (float $e): float => abs($e), $errors);
            $squares = array_map(static fn (float $e): float => $e * $e, $errors);
            $mae = round(array_sum($abs) / count($abs), 4);
            $rmse = round(sqrt(array_sum($squares) / count($squares)), 4);
        }

        $result['slope'] = round($model['slope'], 4);
        $result['intercept'] = round($model['intercept'], 4);
        $result['predicted_score'] = round(max(1.0, min(5.0, $rawPrediction)), 2);
        $result['clamped'] = $clamped;
        $result['mae'] = $mae;
        $result['rmse'] = $rmse;
        $result['validation_period_count'] = count($errors);

        return $result;
    }

    /**
     * Least-squares fit. Returns null when the slope is undefined.
     *
     * @param float[] $xs
     * @param float[] $ys
     * @return array{slope:float,intercept:float}|null
     */
    public function fit(array $xs, array $ys): ?array
    {
        $n = count($xs);
        if ($n < 2 || $n !== count($ys)) {
            return null;
        }

        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;

        $numerator = 0.0;
        $denominator = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $meanX;
            $numerator += $dx * ($ys[$i] - $meanY);
            $denominator += $dx * $dx;
        }

        if (abs($denominator) < 1e-12) {
            return null; // constant X values: slope undefined
        }

        $slope = $numerator / $denominator;
        $intercept = $meanY - ($slope * $meanX);

        return ['slope' => $slope, 'intercept' => $intercept];
    }

    /**
     * Persist a completed analysis for auditability.
     */
    public function persist(array $analysis, int $generatedBy): ?int
    {
        try {
            return \App\Models\PredictionRun::saveRun([
                'faculty_id'                 => $analysis['faculty_id'],
                'model_name'                 => $analysis['model_name'],
                'training_observation_count' => $analysis['observation_count'],
                'historical_period_count'    => $analysis['period_count'],
                'validation_period_count'    => $analysis['validation_period_count'],
                'slope'                      => $analysis['slope'],
                'intercept'                  => $analysis['intercept'],
                'predicted_score'            => $analysis['predicted_score'],
                'mae'                        => $analysis['mae'],
                'rmse'                       => $analysis['rmse'],
                'model_status'               => $analysis['status'],
                'generated_by'               => $generatedBy,
            ]);
        } catch (\Throwable $e) {
            app_log('warning', 'Prediction run persistence failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Linear interpolation values for charting the regression line.
     *
     * @return array<int,array{x:float,y:float}>
     */
    public function trendLine(array $analysis): array
    {
        if ($analysis['slope'] === null || $analysis['intercept'] === null) {
            return [];
        }

        $line = [];
        $last = $analysis['period_count'] + 1;
        for ($i = 1; $i <= $last; $i++) {
            $line[] = [
                'x' => (float) $i,
                'y' => round(max(1.0, min(5.0, ($analysis['slope'] * $i) + $analysis['intercept'])), 3),
            ];
        }
        return $line;
    }
}
