<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\AuditLog;

final class AuditLogController extends BaseController
{
    public function index(): void
    {
        $filters = [
            'action' => $this->input('action', ''),
            'user_id' => $this->input('user_id', ''),
            'from' => $this->input('from', ''),
            'to' => $this->input('to', ''),
        ];
        $pagination = (new AuditLog())->paginate($filters, $this->page(), 30);
        $this->view('dean/audit/index', [
            'pageTitle' => 'Audit Logs',
            'pagination' => $pagination,
            'filters' => $filters,
            'users' => Database::fetchAll('SELECT id, first_name, last_name, username FROM users ORDER BY last_name, first_name'),
        ]);
    }
}
