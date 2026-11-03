<?php

namespace App\Services;

use App\Models\Acknowledgement;
use App\Models\ClassRecord;
use App\Models\COFCriterion;
use App\Models\Department;
use App\Models\Observation;
use App\Models\ObservationAmendment;
use App\Models\ObservationRating;
use App\Models\PredictionRun;
use App\Models\RateeComment;
use App\Models\User;

/**
 * Assembles report data from the database. CSV output is generated from the
 * same rows the printable report renders.
 */
final class ReportingService
{
    public function institution(): array
    {
        return [
            'name'     => (string) setting('institution_name', 'ACLC College Tacloban'),
            'address'  => (string) setting('institution_address', 'Tacloban City, Leyte, Philippines'),
            'system'   => (string) setting('system_full_name', 'Intelligent Classroom Observation and Teaching Analysis System'),
            'short'    => (string) setting('system_name', 'ICOTAS'),
            'generated'=> date('M j, Y g:i A'),
        ];
    }

    public function facultyObservation(int $observationId): ?array
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            return null;
        }
        $ratings = ObservationRating::detailedForObservation($observationId);
        $criteria = COFCriterion::active();

        // Use the active criteria for ordering/labels, but never lose a stored
        // rating if a criterion was deactivated later.
        $byNumber = [];
        foreach ($ratings as $rating) {
            $byNumber[(int) $rating['criterion_number']] = $rating;
        }

        $grouped = ['Teaching Competence' => [], 'Personality' => []];
        foreach ($criteria as $criterion) {
            $number = (int) $criterion['criterion_number'];
            $stored = $byNumber[$number] ?? null;
            $grouped[$criterion['category']][] = [
                'number' => $number,
                'text'   => (string) $criterion['criterion_text'],
                'score'  => $stored !== null ? (int) $stored['score'] : null,
            ];
            unset($byNumber[$number]);
        }
        foreach ($byNumber as $number => $stored) {
            $grouped[$stored['category']][] = [
                'number' => $number,
                'text'   => (string) ($stored['criterion_text'] ?? 'Criterion ' . $number),
                'score'  => (int) $stored['score'],
                'retired'=> true,
            ];
        }

        return [
            'observation' => $observation,
            'grouped'     => $grouped,
            'comments'    => RateeComment::forObservation($observationId),
            'acknowledgement' => Acknowledgement::forObservation($observationId, (int) $observation['faculty_id']),
            'amendments'  => ObservationAmendment::forObservation($observationId),
            'ratings'     => $ratings,
        ];
    }

    public function facultyHistory(int $facultyId): array
    {
        $faculty = User::findFaculty($facultyId);
        $observations = Observation::publishedForFaculty($facultyId);
        return ['faculty' => $faculty, 'observations' => $observations];
    }

    /**
     * Published observation summary for the given filters.
     */
    public function observationSummary(array $filters): array
    {
        $analytics = new AnalyticsService();
        return [
            'rows'    => Observation::allPublishedWithClass(),
            'summary' => $analytics->summary($filters),
            'by_department' => $analytics->byDepartment($filters),
            'by_period'     => $analytics->byPeriod($filters),
        ];
    }

    public function departmentSummary(array $filters = []): array
    {
        $analytics = new AnalyticsService();
        return [
            'departments' => Department::options(false),
            'rows'        => $analytics->byDepartment($filters),
        ];
    }

    public function periodSummary(array $filters = []): array
    {
        $analytics = new AnalyticsService();
        return [
            'rows' => $analytics->byPeriod($filters),
            'years'=> $analytics->byAcademicYear($filters),
        ];
    }

    public function classRecordRows(array $filters = []): array
    {
        return ClassRecord::allForExport($filters);
    }

    public function predictionRows(): array
    {
        return PredictionRun::latestPerFaculty();
    }

    public function activityRows(array $filters = []): array
    {
        return \App\Models\AuditLog::paginate($filters, 500, 0);
    }

    /**
     * @param string[] $headers
     * @param array<int,array<int,scalar|null>> $rows
     */
    public function toCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    public function safeFilename(string $prefix, string $extension = 'csv'): string
    {
        $stamp = date('Ymd-His');
        return $prefix . '-' . $stamp . '.' . $extension;
    }
}
