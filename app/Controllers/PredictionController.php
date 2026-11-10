<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\User;
use App\Services\PredictionService;
use App\Services\RecommendationService;

final class PredictionController extends BaseController
{
    public function index(): void
    {
        $service = new PredictionService();
        $instructors = (new User())->instructors($this->demo(), false);

        $this->view('dean/predictions/index', [
            'pageTitle' => 'Predictive Analytics',
            'config' => $service->config(),
            'predictions' => $service->allLatest($this->demo()),
            'instructors' => $instructors,
            'demo' => $this->demo(),
        ]);
    }

    public function generate(): void
    {
        $this->requirePost();
        $service = new PredictionService();
        $instructorId = $this->intInput('instructor_id');

        if ($instructorId > 0) {
            $instructor = Database::fetch(
                'SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? AND r.slug = "instructor" AND u.is_demo = ?',
                [$instructorId, $this->demo() ? 1 : 0]
            );
            if (!$instructor) {
                $this->backWithError('Instructor not found in the active data scope.', 'dean/predictions');
            }
            $result = $service->generate($instructorId, $this->demo());
            (new RecommendationService())->generateForInstructor($instructorId, $this->demo());
            $this->audit('prediction.generate', 'instructor', $instructorId, 'Generated prediction indicator for ' . trim($instructor['first_name'] . ' ' . $instructor['last_name']));
            $message = $result['data_status'] === 'sufficient'
                ? 'Prediction indicator generated for ' . trim($instructor['first_name'] . ' ' . $instructor['last_name']) . '.'
                : 'Prediction request recorded; the data is insufficient for an estimate. See the explanation shown.';
            $this->success($message, 'dean/predictions');
        }

        $results = $service->generateForAll($this->demo());
        $created = (new RecommendationService())->generateAll($this->demo());
        $this->audit('prediction.generate_all', 'prediction', null, 'Generated prediction indicators for ' . count($results) . ' instructor(s)');
        $this->success(count($results) . ' instructor prediction indicator(s) refreshed. ' . $created . ' new recommendation(s) created.', 'dean/predictions');
    }
}
