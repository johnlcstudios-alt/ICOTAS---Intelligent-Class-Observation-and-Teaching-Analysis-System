<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\PredictionRun;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\PredictionService;

/**
 * Dean-only predictive analytics. The regression is computed in native PHP
 * and is only produced when at least PredictionService::MIN_PERIODS distinct
 * published observation periods exist. No external AI service is used.
 */
final class PredictionController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $facultyOptions = User::facultyOptions(false);
        $selectedId = (int) Request::query('faculty_id', 0);

        if ($selectedId === 0 && $facultyOptions !== []) {
            $selectedId = (int) $facultyOptions[0]['user_id'];
        }

        $selectedFaculty = $selectedId > 0 ? User::findFaculty($selectedId) : null;
        $analysis = null;
        $trendLine = [];
        $history = [];

        if ($selectedFaculty !== null) {
            $service = new PredictionService();
            $analysis = $service->analyze((int) $selectedFaculty['user_id']);
            $trendLine = $service->trendLine($analysis);
            $history = PredictionRun::historyForFaculty((int) $selectedFaculty['user_id'], 8);
        }

        $this->render('dean/predictions/index', [
            'pageTitle'       => 'Predictive Analytics',
            'pageSubtitle'    => 'Simple Linear Regression over published COF period averages.',
            'activeNav'       => 'predictions',
            'facultyOptions'  => $facultyOptions,
            'selectedId'      => $selectedId,
            'selectedFaculty' => $selectedFaculty,
            'analysis'        => $analysis,
            'trendLine'       => $trendLine,
            'history'         => $history,
            'latestRuns'      => PredictionRun::latestPerFaculty(),
            'minPeriods'      => PredictionService::MIN_PERIODS,
            'modelName'       => PredictionService::MODEL_NAME,
        ]);
    }

    /**
     * Recompute and persist a prediction run. Persisting is an explicit
     * action so the audit trail shows when a forecast was produced.
     */
    public function generate(): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $facultyId = (int) Request::input('faculty_id', 0);
        $faculty = User::findFaculty($facultyId);
        if ($faculty === null) {
            Flash::error('Select a valid faculty member.');
            redirect('/dean/predictions');
        }

        $service = new PredictionService();
        $analysis = $service->analyze($facultyId);
        $runId = $service->persist($analysis, (int) $dean['user_id']);

        AuditLog::record(
            (int) $dean['user_id'],
            'prediction.generated',
            'Predictive Analytics',
            'faculty:' . $facultyId,
            $analysis['sufficient']
                ? 'Prediction generated (run #' . ($runId ?? 'n/a') . '). Periods: ' . $analysis['period_count']
                    . ', predicted: ' . $analysis['predicted_score'] . ', MAE: ' . ($analysis['mae'] ?? 'n/a')
                    . ', RMSE: ' . ($analysis['rmse'] ?? 'n/a') . '.'
                : 'Prediction requested but insufficient data: ' . $analysis['reason']
        );

        if (!$analysis['sufficient']) {
            Flash::warning('Not enough historical data to forecast: ' . $analysis['reason']);
        } else {
            Flash::success('Prediction generated and recorded in the audit trail. Review the validation error before interpreting it.');
        }

        redirect('/dean/predictions?faculty_id=' . $facultyId);
    }
}
