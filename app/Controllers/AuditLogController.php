<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\ReportingService;

/**
 * Append-only audit trail and authentication monitoring for the Dean.
 */
final class AuditLogController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'module', 'action', 'user_id', 'date_from', 'date_to',
        ]);
        $page = $this->pageNumber();
        $total = AuditLog::countFiltered($filters);
        $pagination = paginate($total, $page, 25);

        $this->render('dean/audit/index', [
            'pageTitle'    => 'Audit Trail',
            'pageSubtitle' => 'Accountable record of administrative and security events.',
            'activeNav'    => 'audit',
            'rows'         => AuditLog::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'modules'      => AuditLog::modules(),
            'actions'      => AuditLog::actions(),
            'staff'        => User::facultyOptions(false),
            'recentAttempts' => LoginAttempt::recent(15),
            'failed24h'    => LoginAttempt::countFailuresSince(date('Y-m-d H:i:s', time() - 86400)),
        ]);
    }

    public function export(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'module', 'action', 'user_id', 'date_from', 'date_to',
        ]);
        $rows = AuditLog::paginate($filters, 5000, 0);

        $reporting = new ReportingService();
        $csv = $reporting->toCsv(
            ['Timestamp', 'User', 'Role', 'Action', 'Module', 'Reference', 'Details', 'IP Address', 'User Agent'],
            array_map(static fn (array $r): array => [
                $r['created_at'],
                $r['user_name'] ?? 'System',
                $r['role'] ?? '',
                $r['action'],
                $r['module'],
                $r['record_reference'],
                $r['details'],
                $r['ip_address'],
                $r['user_agent'],
            ], $rows)
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reporting->safeFilename('icotas-audit-trail') . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }
}
