<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Setting;
use App\Models\Evaluation;
use App\Services\AnalyticsService;
use App\Services\EvaluationService;
use App\Services\NotificationService;
use App\Services\QRService;

final class StudentController extends BaseController
{
    private Evaluation $evaluations;
    private EvaluationService $service;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->service = new EvaluationService();
    }

    public function dashboard(): void
    {
        $studentId = (int) Auth::id();
        $data = (new AnalyticsService())->studentDashboard($studentId, $this->demo());

        $this->view('student/dashboard', [
            'pageTitle' => 'My Evaluation Portal',
            'data' => $data,
            'unread' => (new NotificationService())->unreadCount($studentId),
            'selfScan' => (string) Setting::get('allow_student_self_scan', '1') === '1',
            'demo' => $this->demo(),
        ]);
    }

    public function scan(): void
    {
        $this->view('student/scan', [
            'pageTitle' => 'Scan Evaluation QR',
            'selfScan' => (string) Setting::get('allow_student_self_scan', '1') === '1',
        ]);
    }

    public function join(): void
    {
        $this->requirePost();
        $code = strtoupper($this->postString('session_code'));
        if ($code === '') {
            $this->backWithError('Enter the session code shown by your instructor.', 'student/scan');
        }
        $session = $this->evaluations->resolveByCode($code);
        if (!$session) {
            $this->backWithError('No evaluation session was found for that code. Check the code and try again.', 'student/scan');
        }
        if ((int) $session['is_demo'] !== ($this->demo() ? 1 : 0)) {
            $this->backWithError('That evaluation session is outside your active data scope.', 'student/scan');
        }
        $state = $this->service->accessState($session, (int) Auth::id());
        if ($state['state'] !== 'available') {
            $this->backWithError($state['message'], 'student/dashboard');
        }
        $token = (new QRService())->tokenForDisplay($session);
        if (!$token) {
            $this->backWithError('This session link is unavailable. Ask your instructor to regenerate the QR code.', 'student/dashboard');
        }
        redirect('evaluate/' . $token);
    }

    public function open(string $id): void
    {
        if ((string) Setting::get('allow_student_self_scan', '1') !== '1') {
            $this->backWithError('Portal quick-open is disabled. Scan the QR code or enter the session code provided by your instructor.', 'student/scan');
        }
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || (int) $session['is_demo'] !== ($this->demo() ? 1 : 0)) {
            abort(404);
        }
        $state = $this->service->accessState($session, (int) Auth::id());
        if ($state['state'] !== 'available') {
            $this->backWithError($state['message'], 'student/dashboard');
        }
        $token = (new QRService())->tokenForDisplay($session);
        if (!$token) {
            $this->backWithError('This session link is unavailable. Ask your instructor to regenerate the QR code.', 'student/dashboard');
        }
        redirect('evaluate/' . $token);
    }

    public function history(): void
    {
        $history = $this->evaluations->studentHistory((int) Auth::id(), $this->demo());
        $this->view('student/history', [
            'pageTitle' => 'My Evaluation History',
            'history' => $history,
            'unread' => (new NotificationService())->unreadCount((int) Auth::id()),
        ]);
    }
}
