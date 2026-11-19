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
        // Department scope: Dean/Academic Head can only see their department's analytics.
        $deptId = $this->scopeDepartmentId();
        if ($deptId !== null) {
            $filters['department_id'] = $deptId;
        }
        $data = $service->analyticsPage($filters, $this->demo());

        $this->view('dean/analytics/index', [
            'pageTitle' => 'Teaching Analytics',
            'data' => $data,
            'filters' => $filters,
            'demo' => $this->demo(),
        ]);
    }
}
