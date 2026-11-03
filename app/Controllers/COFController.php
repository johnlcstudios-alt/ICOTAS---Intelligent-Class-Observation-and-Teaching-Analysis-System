<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Settings;
use App\Models\AuditLog;
use App\Models\COFCriterion;

/**
 * Dean-only management of the configurable 20-item COF.
 *
 * The seeded wording is marked PROVISIONAL. The institution must verify it
 * against the official ACLC Classroom Observation Form (Version 1, Series
 * 2012) and then explicitly mark the form verified in-system.
 */
final class COFController extends Controller
{
    public function index(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $grouped = COFCriterion::byCategory();
        $this->render('dean/cof/index', [
            'pageTitle'    => 'COF Configuration',
            'pageSubtitle' => 'Inspect and verify the 20-item Classroom Observation Form structure.',
            'activeNav'    => 'cof',
            'criteria'     => COFCriterion::allOrdered(),
            'grouped'      => $grouped,
            'structure'    => COFCriterion::structureReport(),
            'verified'     => (string) setting('cof_verified', '0') === '1',
            'verifiedBy'   => (string) setting('cof_verified_by', ''),
            'verifiedAt'   => (string) setting('cof_verified_at', ''),
            'sourceNote'   => (string) setting('cof_source_note', ''),
            'labels'       => rating_labels(),
            'dean'         => $dean,
        ]);
    }

    public function updateCriterion(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $criterion = COFCriterion::find((int) $id);
        if ($criterion === null) {
            abort(404, 'Criterion not found.');
        }

        $data = [
            'criterion_text' => trim((string) Request::input('criterion_text', '')),
            'category'       => (string) Request::input('category', $criterion['category']),
            'display_order'  => trim((string) Request::input('display_order', (string) $criterion['display_order'])),
            'is_active'      => Request::input('is_active', '0') === '1' ? '1' : '0',
        ];

        $validated = $this->validate($data, [
            'criterion_text' => 'required|max:2000',
            'category'       => 'required|in:Teaching Competence,Personality',
            'display_order'  => 'required|integer|min:0|max:999',
            'is_active'      => 'required|boolean',
        ], [
            'criterion_text' => 'Criterion text',
            'category'       => 'Category',
            'display_order'  => 'Display order',
            'is_active'      => 'Active',
        ], '/dean/cof');

        $textChanged = $validated['criterion_text'] !== $criterion['criterion_text'];
        $categoryChanged = $validated['category'] !== $criterion['category'];

        COFCriterion::updateCriterion((int) $criterion['criterion_id'], [
            'criterion_text' => $validated['criterion_text'],
            'category'       => $validated['category'],
            'display_order'  => (int) $validated['display_order'],
            'is_active'      => (int) $validated['is_active'],
            'source_note'    => $textChanged
                ? 'EDITED IN-SYSTEM — requires re-verification against the official ACLC COF'
                : $criterion['source_note'],
        ]);

        if ($textChanged || $categoryChanged) {
            Settings::setMany([
                'cof_verified'    => '0',
                'cof_verified_by' => '',
                'cof_verified_at' => '',
            ], (int) $dean['user_id'], 'cof');
            Flash::warning('Criterion updated. Because the form content changed, the COF verification status was reset.');
        } else {
            Flash::success('Criterion updated.');
        }

        AuditLog::record(
            (int) $dean['user_id'],
            'cof.criterion_updated',
            'COF Configuration',
            'criterion:' . $criterion['criterion_id'],
            'Criterion #' . $criterion['criterion_number'] . ' updated.'
        );

        redirect('/dean/cof');
    }

    public function verify(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $structure = COFCriterion::structureReport();
        if (!$structure['valid']) {
            Flash::error('The active COF must contain exactly 13 Teaching Competence and 7 Personality criteria (20 unique numbers) before it can be verified.');
            redirect('/dean/cof');
        }

        $note = trim((string) Request::input('source_reference', ''));
        if (strlen($note) < 5) {
            Flash::error('Record the source or reference used to verify the official COF wording.');
            redirect('/dean/cof');
        }

        Settings::setMany([
            'cof_verified'    => '1',
            'cof_verified_by' => $dean['full_name'],
            'cof_verified_at' => date('Y-m-d H:i:s'),
            'cof_source_note' => $note,
        ], (int) $dean['user_id'], 'cof');

        AuditLog::record(
            (int) $dean['user_id'],
            'cof.verified',
            'COF Configuration',
            'cof',
            'COF wording verified against: ' . $note
        );

        Flash::success('The COF has been marked verified against the recorded source.');
        redirect('/dean/cof');
    }
}
