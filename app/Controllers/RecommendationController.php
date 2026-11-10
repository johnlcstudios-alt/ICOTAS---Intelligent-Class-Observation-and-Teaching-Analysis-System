<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Services\RecommendationService;

final class RecommendationController extends BaseController
{
    public function index(): void
    {
        $service = new RecommendationService();
        $status = $this->postString('status') ?: (string) $this->input('status', '');

        $this->view('dean/recommendations/index', [
            'pageTitle' => 'Teaching Recommendations',
            'recommendations' => $service->all($this->demo(), $status),
            'status' => $status,
            'demo' => $this->demo(),
        ]);
    }

    public function status(string $id): void
    {
        $this->requirePost();
        $status = $this->postString('status');
        $service = new RecommendationService();
        $updated = $service->updateStatus((int) $id, $status, (int) Auth::id(), true);
        if (!$updated) {
            $this->backWithError('The recommendation status could not be updated.', 'dean/recommendations');
        }
        $this->audit('recommendation.status', 'instructor_recommendation', (int) $id, 'Updated recommendation status to ' . $status);
        $this->success('Recommendation marked ' . str_replace('_', ' ', $status) . '.', 'dean/recommendations');
    }
}
