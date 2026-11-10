<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Evaluation;
use App\Services\QRService;

final class SessionController extends BaseController
{
    private Evaluation $evaluations;
    private QRService $qr;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->qr = new QRService();
    }

    public function index(): void
    {
        $this->qr->refreshStatuses($this->demo());
        $filters = [
            'search' => $this->input('search', ''),
            'status' => $this->input('status', ''),
            'assignment_id' => $this->input('assignment_id', ''),
        ];
        $this->view('dean/sessions/index', [
            'pageTitle' => 'QR Evaluation Sessions',
            'sessions' => $this->evaluations->sessions($this->demo(), $filters),
            'filters' => $filters,
            'assignments' => $this->evaluations->assignmentOptions($this->demo()),
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $preselect = $this->intInput('assignment_id');
        $this->view('dean/sessions/create', [
            'pageTitle' => 'Open QR Session',
            'assignments' => $this->evaluations->assignmentOptions($this->demo()),
            'preselect' => $preselect,
            'demo' => $this->demo(),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $assignmentId = $this->intInput('assignment_id');
        $opensAt = $this->postString('opens_at');
        $expiresAt = $this->postString('expires_at');
        $result = $this->qr->createSession($assignmentId, $opensAt, $expiresAt, (int) Auth::id(), $this->demo());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/sessions/create');
        }
        $sessionId = (int) ($result[2]['id'] ?? 0);
        $this->audit('qr.create', 'evaluation_session', $sessionId, 'Created QR session ' . ($result[2]['code'] ?? ''));
        $this->success($result[1] . ' ' . (int) ($result[2]['students'] ?? 0) . ' eligible student(s) added.', 'dean/sessions/' . $sessionId);
    }

    public function show(string $id): void
    {
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $this->qr->refreshStatuses($this->demo());
        $session = $this->evaluations->getSession((int) $id);
        $this->view('dean/sessions/show', [
            'pageTitle' => 'Session ' . $session['session_code'],
            'session' => $session,
            'status' => $this->qr->effectiveStatus($session),
            'qrUrl' => $this->qr->evaluationUrl($session),
            'stats' => $this->qr->participationSummary($session),
            'demo' => $this->demo(),
        ]);
    }

    public function poster(string $id): void
    {
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $this->printView('dean/sessions/poster', [
            'pageTitle' => 'QR Poster',
            'session' => $session,
            'status' => $this->qr->effectiveStatus($session),
            'qrUrl' => $this->qr->evaluationUrl($session),
        ]);
    }

    public function regenerate(string $id): void
    {
        $this->requirePost();
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $result = $this->qr->regenerate((int) $id, (int) Auth::id());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/sessions/' . (int) $id);
        }
        $this->success($result[1], 'dean/sessions/' . (int) $id);
    }

    public function close(string $id): void
    {
        $this->requirePost();
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $result = $this->qr->close((int) $id, (int) Auth::id());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/sessions/' . (int) $id);
        }
        $this->success($result[1], 'dean/sessions/' . (int) $id);
    }

    public function archive(string $id): void
    {
        $this->requirePost();
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $result = $this->qr->archive((int) $id, (int) Auth::id());
        $this->success($result[1], 'dean/sessions/' . (int) $id);
    }

    public function revoke(string $id): void
    {
        $this->requirePost();
        $session = $this->evaluations->getSession((int) $id);
        if (!$session || !$this->inScope($session)) {
            abort(404);
        }
        $result = $this->qr->revoke((int) $id, (int) Auth::id());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/sessions/' . (int) $id);
        }
        $this->success($result[1], 'dean/sessions/' . (int) $id);
    }
}
