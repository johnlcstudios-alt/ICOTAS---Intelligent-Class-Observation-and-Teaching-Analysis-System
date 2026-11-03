<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\QRToken;
use App\Models\User;
use App\Services\QRCodeService;

final class QRController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $faculty = User::facultyOptions(false);
        $tokensByFaculty = [];
        foreach ($faculty as $member) {
            $tokensByFaculty[(int) $member['user_id']] = QRToken::forFaculty((int) $member['user_id']);
        }

        $this->render('qr/index', [
            'pageTitle'    => 'QR Credential Management',
            'pageSubtitle' => 'Issue, revoke and monitor faculty QR access credentials. Only token hashes are stored.',
            'activeNav'    => 'qr',
            'faculty'      => $faculty,
            'tokensByFaculty' => $tokensByFaculty,
            'activeTokens' => QRToken::allWithFaculty(),
            'expiryDays'   => (int) setting('qr_expiry_days', 365),
        ]);
    }

    public function generate(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $facultyId = (int) Request::input('faculty_id', 0);
        $expiryDays = (int) Request::input('expiry_days', (int) setting('qr_expiry_days', 365));

        try {
            $result = (new QRCodeService())->generate($facultyId, $dean, $expiryDays);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/qr');
        }

        // The raw token is held only in the server-side session until the
        // preview renders. It is never written to the database.
        Session::put('qr_preview', [
            'raw_token'  => $result['raw_token'],
            'payload'    => $result['payload'],
            'expires_at' => $result['expires_at'],
            'token_id'   => $result['token_id'],
            'faculty'    => [
                'user_id'   => (int) $result['faculty']['user_id'],
                'full_name' => $result['faculty']['full_name'],
                'employee_no' => $result['faculty']['employee_no'] ?? null,
                'email'     => $result['faculty']['email'],
            ],
        ]);

        Flash::success('QR credential generated. Print or download it now — the raw token is shown only on this preview page.');
        redirect('/dean/qr/preview');
    }

    public function preview(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $preview = Session::get('qr_preview');
        if (!is_array($preview)) {
            Flash::warning('No QR preview is available. Generate a credential to view it.');
            redirect('/dean/qr');
        }

        $this->render('qr/preview', [
            'pageTitle'    => 'QR Credential Preview',
            'pageSubtitle' => 'Show this once to the faculty member. The token cannot be recovered later.',
            'activeNav'    => 'qr',
            'preview'      => $preview,
        ]);
    }

    public function revoke(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $facultyId = (int) Request::input('faculty_id', 0);
        try {
            $revoked = (new QRCodeService())->revoke($facultyId, $dean);
            Flash::success($revoked ? 'QR credential revoked.' : 'No active QR credential was present.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        redirect('/dean/qr');
    }

    /**
     * Landing point after scanning a QR code.
     * The token in the URL is exchanged for a session verification and then
     * immediately removed from the address bar by redirecting.
     */
    public function access(): void
    {
        $token = trim((string) Request::query('token', ''));

        if (!Auth::check()) {
            Session::put('qr_pending_token', $token);
            Session::put('intended_url', '/qr/access?token=' . rawurlencode($token));
            Flash::info('Sign in with your faculty account to verify this QR credential.');
            redirect('/login');
        }

        $user = Auth::user();
        if (($user['role'] ?? '') !== 'Faculty') {
            AuditLog::record((int) $user['user_id'], 'qr.verify_denied', 'QR Verification', 'qr', 'Non-faculty account attempted QR verification.');
            Flash::error('QR credentials are only valid for faculty accounts.');
            redirect('/dean/qr');
        }

        Session::forget('intended_url');

        if ((new QRCodeService())->attemptsExceeded()) {
            Flash::error('Too many verification attempts. Please wait a few minutes and try again.');
            redirect('/faculty/evaluations');
        }

        $service = new QRCodeService();
        $result = $service->verify($token);

        if (!$result['ok']) {
            $service->recordFailure($result['reason'], (int) $user['user_id']);
            Flash::error($result['message']);
            redirect('/faculty/qr');
        }

        if ((int) $result['faculty']['user_id'] !== (int) $user['user_id']) {
            $service->recordFailure('ownership', (int) $user['user_id']);
            AuditLog::record((int) $user['user_id'], 'qr.ownership_denied', 'QR Verification', 'token:' . $result['record']['token_id'], 'Faculty account attempted to use another faculty member\'s QR credential.');
            Flash::error('This QR credential belongs to a different faculty account. It cannot be used while signed in as ' . $user['full_name'] . '.');
            redirect('/faculty/qr');
        }

        $service->recordSuccess((int) $user['user_id'], (int) $result['record']['token_id']);
        Session::put('qr_verified_at', time());
        Session::put('qr_verified_faculty', (int) $user['user_id']);
        Session::forget('qr_pending_token');

        Flash::success('QR credential verified. Welcome to your secured evaluation results.');
        redirect('/faculty/evaluations?verified=1');
    }

    /**
     * Scanner endpoint. Accepts form or JSON token submissions.
     */
    public function verify(): void
    {
        $user = Auth::user();
        if ($user === null) {
            json_response(['success' => false, 'message' => 'Sign in to verify a QR credential.'], 401);
        }
        if (($user['role'] ?? '') !== 'Faculty') {
            json_response(['success' => false, 'message' => 'QR credentials are only valid for faculty accounts.'], 403);
        }

        $token = trim((string) (Request::input('token', '') ?: Request::query('token', '')));

        if ((new QRCodeService())->attemptsExceeded()) {
            json_response(['success' => false, 'message' => 'Too many verification attempts. Please wait and try again.'], 429);
        }

        $service = new QRCodeService();
        $result = $service->verify($token);

        if (!$result['ok']) {
            $service->recordFailure($result['reason'], (int) $user['user_id']);
            json_response(['success' => false, 'reason' => $result['reason'], 'message' => $result['message']], 422);
        }

        if ((int) $result['faculty']['user_id'] !== (int) $user['user_id']) {
            $service->recordFailure('ownership', (int) $user['user_id']);
            json_response([
                'success' => false,
                'reason'  => 'ownership',
                'message' => 'This credential belongs to a different faculty account.',
            ], 403);
        }

        $service->recordSuccess((int) $user['user_id'], (int) $result['record']['token_id']);
        Session::put('qr_verified_at', time());
        Session::put('qr_verified_faculty', (int) $user['user_id']);

        json_response([
            'success' => true,
            'message' => 'QR credential verified.',
            'data'    => ['redirect' => url('/faculty/evaluations?verified=1')],
        ]);
    }

    public function scanner(): void
    {
        (new \App\Services\AuthorizationService())->requireFaculty();

        $this->render('qr/scanner', [
            'pageTitle'    => 'Scan QR Credential',
            'pageSubtitle' => 'Use your device camera or paste the credential URL.',
            'activeNav'    => 'qr',
            'hasActiveToken' => QRToken::activeForFaculty((int) Auth::id()) !== null,
            'activeToken'  => QRToken::activeForFaculty((int) Auth::id()),
            'tokenHistory' => QRToken::forFaculty((int) Auth::id()),
        ]);
    }
}
