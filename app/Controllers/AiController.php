<?php

namespace App\Controllers;

use App\Services\AnalyticsService;

final class AiController extends BaseController
{
    public function index(): void
    {
        $service = new AnalyticsService();
        $filters = $service->buildFilters($_GET);
        $data = $service->aiInsights($filters, $this->demo());

        $this->view('dean/ai/index', [
            'pageTitle' => 'AI-Assisted Insights',
            'data' => $data,
            'filters' => $filters,
            'demo' => $this->demo(),
        ]);
    }
}
