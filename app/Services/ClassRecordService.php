<?php

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\ClassRecord;
use App\Models\Notification;

/**
 * Class Record validation and persistence.
 *
 * Institution-specific grading rules (passing grade, grade bounds and the
 * completion rule for finalisation) are configurable in System Settings and
 * are marked as pending institutional verification in the documentation.
 */
final class ClassRecordService
{
    /**
     * @return array{valid:bool,errors:array<string,string>,data:array<string,mixed>}
     */
    public function validate(array $input): array
    {
        $errors = [];

        $classId = (int) ($input['class_id'] ?? 0);
        $periodId = (int) ($input['period_id'] ?? 0);
        $class = ClassModel::findDetailed($classId);
        if ($class === null) {
            $errors['class_id'] = 'Select a valid class.';
        }
        $period = \App\Models\AcademicPeriod::find($periodId);
        if ($period === null) {
            $errors['period_id'] = 'Select a valid academic period.';
        }
        if ($class !== null && $period !== null && (int) $class['period_id'] !== $periodId) {
            $errors['period_id'] = 'The selected period does not match the class period.';
        }

        $total = $this->parseCount($input['total_students'] ?? null, $errors, 'total_students', 'Total students');
        $passed = $this->parseCount($input['passed_count'] ?? null, $errors, 'passed_count', 'Passed count');
        $failed = $this->parseCount($input['failed_count'] ?? null, $errors, 'failed_count', 'Failed count');

        if ($total !== null && $passed !== null && $passed > $total) {
            $errors['passed_count'] = 'Passed count cannot exceed total students.';
        }
        if ($total !== null && $failed !== null && $failed > $total) {
            $errors['failed_count'] = 'Failed count cannot exceed total students.';
        }
        if ($total !== null && $passed !== null && $failed !== null && ($passed + $failed) > $total) {
            $errors['failed_count'] = 'Passed and failed counts cannot exceed total students.';
        }

        $averageGrade = null;
        $gradeRaw = trim((string) ($input['average_grade'] ?? ''));
        if ($gradeRaw !== '') {
            if (!is_numeric($gradeRaw)) {
                $errors['average_grade'] = 'Average grade must be a number.';
            } else {
                $averageGrade = round((float) $gradeRaw, 2);
                $min = (float) setting('grading_min', 0);
                $max = (float) setting('grading_max', 100);
                if ($averageGrade < $min || $averageGrade > $max) {
                    $errors['average_grade'] = "Average grade must be between {$min} and {$max}.";
                }
            }
        }

        $recordStatus = (string) ($input['record_status'] ?? 'Draft');
        if (!in_array($recordStatus, ['Draft', 'Finalized'], true)) {
            $errors['record_status'] = 'Select a valid record status.';
        }

        if ($recordStatus === 'Finalized') {
            if ((string) setting('class_record_require_complete', '1') === '1') {
                if ($total !== null && $passed !== null && $failed !== null && ($passed + $failed) !== $total) {
                    $errors['record_status'] = 'A finalized record must account for every student (passed + failed must equal total students). Adjust the counts or save as Draft.';
                }
            }
            if ($averageGrade === null) {
                $errors['average_grade'] = 'A finalized record requires an average grade.';
            }
        }

        $remarks = trim((string) ($input['remarks'] ?? ''));

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
            'data'   => [
                'class_id'       => $classId,
                'period_id'      => $periodId,
                'total_students' => $total ?? 0,
                'passed_count'   => $passed ?? 0,
                'failed_count'   => $failed ?? 0,
                'average_grade'  => $averageGrade,
                'remarks'        => $remarks === '' ? null : $remarks,
                'record_status'  => $recordStatus,
            ],
        ];
    }

    public function create(array $data, array $dean): int
    {
        if (ClassRecord::findForClassPeriod((int) $data['class_id'], (int) $data['period_id']) !== null) {
            throw new \RuntimeException('A class record already exists for this class and period. Edit the existing record instead.');
        }

        return Database::transaction(function () use ($data, $dean): int {
            $id = ClassRecord::create(array_merge($data, [
                'created_by'  => (int) $dean['user_id'],
                'data_origin' => (string) ($data['data_origin'] ?? 'Production'),
            ]));

            ClassRecord::recordRevision($id, $data, (int) $dean['user_id'], 'Record created');

            AuditLog::record(
                (int) $dean['user_id'],
                'class_record.created',
                'Class Records',
                'class_record:' . $id,
                'Created for class #' . $data['class_id'] . ' / period #' . $data['period_id'] . '.'
            );

            return $id;
        });
    }

    public function update(int $recordId, array $data, array $dean): void
    {
        $existing = ClassRecord::find($recordId);
        if ($existing === null) {
            throw new \RuntimeException('Class record not found.');
        }
        if ($existing['record_status'] === 'Finalized' && $data['record_status'] === 'Finalized') {
            // permitted: revisions are snapshotted below
        }

        Database::transaction(function () use ($recordId, $data, $dean, $existing): void {
            ClassRecord::updateById($recordId, array_merge($data, [
                'updated_by' => (int) $dean['user_id'],
            ]));

            ClassRecord::recordRevision($recordId, $existing, (int) $dean['user_id'], 'Previous values before edit');

            AuditLog::record(
                (int) $dean['user_id'],
                'class_record.updated',
                'Class Records',
                'class_record:' . $recordId,
                'Record updated (status: ' . $data['record_status'] . ').'
            );

            if ($existing['record_status'] !== 'Finalized' && $data['record_status'] === 'Finalized') {
                try {
                    Notification::pushToDeans(
                        'Class record finalized',
                        'Class record #' . $recordId . ' has been finalized.',
                        '/dean/class-records/' . $recordId,
                        'success'
                    );
                } catch (\Throwable $e) {
                    app_log('warning', 'Class record notification failed: ' . $e->getMessage());
                }
            }
        });
    }

    public function delete(int $recordId, array $dean): void
    {
        $existing = ClassRecord::find($recordId);
        if ($existing === null) {
            throw new \RuntimeException('Class record not found.');
        }

        Database::transaction(function () use ($recordId, $dean): void {
            $stmt = Database::connection()->prepare("DELETE FROM class_records WHERE record_id = ? AND record_status = 'Draft'");
            $stmt->execute([$recordId]);
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('Only draft class records can be deleted. Finalized records are preserved.');
            }
            AuditLog::record(
                (int) $dean['user_id'],
                'class_record.deleted',
                'Class Records',
                'class_record:' . $recordId,
                'Draft class record deleted.'
            );
        });
    }

    private function parseCount(mixed $value, array &$errors, string $field, string $label): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            $errors[$field] = $label . ' is required.';
            return null;
        }
        if (!preg_match('/^\d+$/', $raw)) {
            $errors[$field] = $label . ' must be a non-negative whole number.';
            return null;
        }
        return (int) $raw;
    }
}
