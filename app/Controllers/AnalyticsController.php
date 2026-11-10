<?php

namespace App\Controllers;

use App\Services\AnalyticsService;

final class AnalyticsController extends BaseController
{
    public function index(): void
    {
        $service = new AnalyticsService();
        $service->refreshOwnSessionStatuses();
        $filters = $service->buildFilters($_GET);
        $data = $service->analyticsPage($filters, $this->demo());

        $this->view('dean/analytics/index', [
            'pageTitle' => 'Teaching Analytics',
            'data' => $data,
            'filters' => $filters,
            'demo' => $this->demo(),
        ]);
    }
}
