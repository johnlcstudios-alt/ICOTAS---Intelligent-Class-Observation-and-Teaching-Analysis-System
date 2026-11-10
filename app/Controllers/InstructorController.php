<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Setting;
use App\Models\Analytics;
use App\Models\Evaluation;
use App\Services\AnalyticsService;
use App\Services\PredictionService;
use App\Services\RecommendationService;

final class InstructorController extends BaseController
{
    private Evaluation $evaluations;
    private AnalyticsService $analytics;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->analytics = new AnalyticsService();
    }

    public function evaluations(): void
    {
        $sessions = $this->evaluations->sessionsForInstructor((int) Auth::id(), $this->demo());
        foreach ($sessions as &$session) {
            $session['effective_status'] = $this->analytics->effectiveSessionStatus($session);
        }
        unset($session);

        $this->view('instructor/evaluations', [
            'pageTitle' => 'My Evaluations',
            'sessions' => $sessions,
            'demo' => $this->demo(),
        ]);
    }

    public function evaluation(string $id): void
    {
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session) || (int) $session['instructor_id'] !== (int) Auth::id()) {
            abort(404);
        }
        $filters = ['session_id' => (int) $session['id'], 'instructor_id' => (int) Auth::id()];
        $model = new Analytics();
        $counts = $model->filteredCounts($filters, $this->demo());
        $rating = $model->filteredRating($filters, $this->demo());
        $categories = $model->categoryAverages($filters, $this->demo());
        $sentiment = $model->sentimentDistribution($filters, $this->demo());
        $themes = $model->themeCounts($filters, $this->demo());
        $threshold = Setting::privacyThreshold();
        $comments = ((int) $counts['responses'] >= $threshold && (string) Setting::get('privacy_show_comments', '1') === '1')
            ? $model->comments($filters, $this->demo(), 30)
            : [];

        $this->view('instructor/evaluation', [
            'pageTitle' => 'Evaluation Results',
            'session' => $session,
            'status' => $this->analytics->effectiveSessionStatus($session),
            'counts' => $counts,
            'rating' => $rating,
            'categories' => $categories,
            'sentiment' => $sentiment,
            'themes' => $themes,
            'comments' => $comments,
            'questions' => $model->questionStats((int) $session['survey_version_id'], (int) $session['id']),
            'privacyThreshold' => $threshold,
            'demo' => $this->demo(),
        ]);
    }

    public function analytics(): void
    {
        $data = $this->analytics->instructorDashboard((int) Auth::id(), $this->demo());
        $this->view('instructor/analytics', [
            'pageTitle' => 'My Teaching Analytics',
            'data' => $data,
            'demo' => $this->demo(),
        ]);
    }

    public function predictions(): void
    {
        $service = new PredictionService();
        $this->view('instructor/predictions', [
            'pageTitle' => 'My Trend Indicators',
            'prediction' => $service->latest((int) Auth::id()),
            'periods' => $service->periods((int) Auth::id(), $this->demo()),
            'config' => $service->config(),
            'demo' => $this->demo(),
        ]);
    }

    public function generatePrediction(): void
    {
        $this->requirePost();
        $service = new PredictionService();
        $result = $service->generate((int) Auth::id(), $this->demo());
        (new RecommendationService())->generateForInstructor((int) Auth::id(), $this->demo());
        $this->audit('prediction.generate_own', 'instructor', (int) Auth::id(), 'Generated own teaching trend indicator');
        $message = $result['data_status'] === 'sufficient'
            ? 'Your trend indicator has been recalculated.'
            : 'Not enough comparable history for an estimate yet. The explanation shows exactly what is missing.';
        $this->success($message, 'instructor/predictions');
    }

    public function recommendations(): void
    {
        $service = new RecommendationService();
        $this->view('instructor/recommendations', [
            'pageTitle' => 'My Teaching Recommendations',
            'recommendations' => $service->forInstructor((int) Auth::id()),
            'demo' => $this->demo(),
        ]);
    }

    public function recommendationStatus(string $id): void
    {
        $this->requirePost();
        $status = $this->postString('status');
        $updated = (new RecommendationService())->updateStatus((int) $id, $status, (int) Auth::id(), false);
        if (!$updated) {
            $this->backWithError('The recommendation could not be updated.', 'instructor/recommendations');
        }
        $this->audit('recommendation.own_status', 'instructor_recommendation', (int) $id, 'Instructor updated recommendation status');
        $this->success('Recommendation marked ' . str_replace('_', ' ', $status) . '.', 'instructor/recommendations');
    }
}
