<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Evaluation;
use App\Services\EvaluationService;

final class EvaluationController extends BaseController
{
    private Evaluation $evaluations;
    private EvaluationService $service;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->service = new EvaluationService();
    }

    public function show(string $token): void
    {
        $session = $this->evaluations->resolveByToken($token);
        if (!$session) {
            abort(404, 'This evaluation link is invalid.');
        }
        $user = Auth::user();
        if (!$user || (int) $session['is_demo'] !== (int) $user['is_demo']) {
            abort(404);
        }
        $state = $this->service->accessState($session, (int) Auth::id());
        $form = $state['state'] === 'available' ? $this->service->formData($session) : ['version' => null, 'rating_labels' => []];

        $this->view('student/evaluate', [
            'pageTitle' => 'Course Evaluation',
            'session' => $session,
            'state' => $state,
            'version' => $form['version'],
            'ratingLabels' => $form['rating_labels'],
            'token' => $token,
        ], 'layouts/public');
    }

    public function submit(string $token): void
    {
        $this->requirePost();
        $session = $this->evaluations->resolveByToken($token);
        if (!$session) {
            abort(404, 'This evaluation link is invalid.');
        }
        $user = Auth::user();
        if (!$user || (int) $session['is_demo'] !== (int) $user['is_demo']) {
            abort(404);
        }
        $answers = $this->postArray('answers');
        $result = $this->service->submit($session, (int) Auth::id(), $answers);
        if (!$result[0]) {
            $this->backWithError($result[1], 'evaluate/' . urlencode($token));
        }
        $this->audit('evaluation.submit', 'evaluation_session', (int) $session['id'], 'Anonymous evaluation submitted by an eligible student');
        $this->success($result[1] . ' Your identity is not stored with the response.', 'student/dashboard');
    }
}
