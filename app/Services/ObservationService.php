<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\COFCriterion;
use App\Models\Notification;
use App\Models\Observation;
use App\Models\ObservationAmendment;
use App\Models\ObservationRating;

/**
 * Observation lifecycle: Draft -> Submitted -> Published, with
 * Returned for Correction and Archived branches, and a documented
 * amendment path for published records.
 */
final class ObservationService
{
    public function __construct(private readonly ScoringService $scoring = new ScoringService())
    {
    }

    /**
     * Create a draft observation. The class must belong to the faculty member.
     */
    public function createDraft(array $data, array $dean): int
    {
        $facultyId = (int) ($data['faculty_id'] ?? 0);
        $classId = (int) ($data['class_id'] ?? 0);

        $class = \App\Models\ClassModel::findDetailed($classId);
        if ($class === null) {
            throw new \RuntimeException('The selected class does not exist.');
        }
        if ((int) $class['faculty_id'] !== $facultyId) {
            throw new \RuntimeException('The selected faculty member is not assigned to this class.');
        }
        if (($class['status'] ?? '') !== 'Active') {
            throw new \RuntimeException('Archived classes cannot be used for new observations.');
        }

        $observationId = Database::transaction(function () use ($data, $class, $dean, $facultyId): int {
            $id = Observation::create([
                'faculty_id'       => $facultyId,
                'observer_id'      => (int) $dean['user_id'],
                'class_id'         => (int) $class['class_id'],
                'period_id'        => (int) $class['period_id'],
                'academic_year'    => (string) $class['academic_year'],
                'semester'         => (string) $class['semester'],
                'observation_date' => (string) $data['observation_date'],
                'lesson_topic'     => (string) $data['lesson_topic'],
                'observer_comments'=> $this->cleanComments($data['observer_comments'] ?? null),
                'status'           => Observation::DRAFT,
                'data_origin'      => (string) ($data['data_origin'] ?? 'Production'),
            ]);

            if (!empty($data['assignment_id'])) {
                $assignment = Assignment::findDetailed((int) $data['assignment_id']);
                if ($assignment !== null && (int) $assignment['faculty_id'] === $facultyId
                    && (int) $assignment['class_id'] === (int) $class['class_id']) {
                    Assignment::markObservationCreated((int) $assignment['assignment_id'], $id);
                }
            }

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.created',
                'Classroom Observation',
                'observation:' . $id,
                'Draft created for faculty #' . $facultyId . ' in class #' . $class['class_id'] . '.'
            );

            return $id;
        });

        return $observationId;
    }

    /**
     * Persist draft ratings/comments. Partial rating sets are allowed for drafts.
     *
     * @param array<int,mixed> $ratings
     * @return array{valid:bool,errors:array,warnings:array}
     */
    public function saveDraft(int $observationId, array $ratings, ?string $comments, array $dean): array
    {
        $observation = $this->requireEditable($observationId);

        $validation = $this->scoring->validate($ratings, false);

        Database::transaction(function () use ($observationId, $validation, $comments, $dean): void {
            ObservationRating::replaceForObservation(
                $observationId,
                $validation['scores'],
                COFCriterion::TEACHING,
                COFCriterion::PERSONALITY
            );

            $summary = $this->scoring->calculate($validation['scores']);
            Observation::updateById($observationId, [
                'observer_comments'        => $this->cleanComments($comments),
                'teaching_competence_avg'  => $summary['teaching_avg'],
                'personality_avg'          => $summary['personality_avg'],
                'overall_avg'              => $summary['overall_avg'],
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.draft_saved',
                'Classroom Observation',
                'observation:' . $observationId,
                count($validation['scores']) . ' rating(s) saved in draft.'
            );
        });

        return [
            'valid'    => $validation['valid'],
            'errors'   => $validation['errors'],
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * Submit a completed observation. Requires exactly 20 valid ratings.
     *
     * @param array<int,mixed> $ratings
     * @return array{valid:bool,errors:array,summary:?array}
     */
    public function submit(int $observationId, array $ratings, ?string $comments, array $dean, bool $override = false): array
    {
        $observation = $this->requireEditable($observationId, $override);

        $validation = $this->scoring->validate($ratings, true);
        if (!$validation['valid']) {
            return ['valid' => false, 'errors' => $validation['errors'], 'summary' => null];
        }

        $summary = $this->scoring->calculate($validation['scores']);

        Database::transaction(function () use ($observationId, $validation, $summary, $comments, $dean, $observation): void {
            ObservationRating::replaceForObservation(
                $observationId,
                $validation['scores'],
                COFCriterion::TEACHING,
                COFCriterion::PERSONALITY
            );

            Observation::updateById($observationId, [
                'observer_comments'        => $this->cleanComments($comments),
                'teaching_competence_avg'  => $summary['teaching_avg'],
                'personality_avg'          => $summary['personality_avg'],
                'overall_avg'              => $summary['overall_avg'],
                'status'                   => Observation::SUBMITTED,
                'submitted_at'             => date('Y-m-d H:i:s'),
                'return_reason'            => null,
                'returned_at'              => null,
                'returned_by'              => null,
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.submitted',
                'Classroom Observation',
                'observation:' . $observationId,
                sprintf(
                    'Submitted with TC %s, Personality %s, Overall %s.',
                    $summary['teaching_avg'],
                    $summary['personality_avg'],
                    $summary['overall_avg']
                )
            );

            Notification::pushToDeans(
                'Observation submitted',
                'Observation #' . $observationId . ' for ' . $observation['faculty_name'] . ' was submitted and is ready for publication review.',
                '/dean/observations/' . $observationId,
                'success'
            );
        });

        return ['valid' => true, 'errors' => [], 'summary' => $summary];
    }

    public function publish(int $observationId, array $dean): void
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }
        if ($observation['status'] !== Observation::SUBMITTED) {
            throw new \RuntimeException('Only submitted observations can be published.');
        }

        $ratingCount = ObservationRating::forObservation($observationId);
        if (count($ratingCount) !== COFCriterion::TOTAL_COUNT) {
            throw new \RuntimeException('The observation must contain exactly 20 ratings before publication.');
        }

        $summary = $this->scoring->calculate(ObservationRating::scoreMap($observationId));

        Database::transaction(function () use ($observationId, $dean, $observation, $summary): void {
            Observation::updateById($observationId, [
                'status'                   => Observation::PUBLISHED,
                'published_at'             => date('Y-m-d H:i:s'),
                'teaching_competence_avg'  => $summary['teaching_avg'],
                'personality_avg'          => $summary['personality_avg'],
                'overall_avg'              => $summary['overall_avg'],
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.published',
                'Classroom Observation',
                'observation:' . $observationId,
                'Published to the faculty portal.'
            );

            Notification::push(
                (int) $observation['faculty_id'],
                'New evaluation published',
                'A classroom observation evaluation dated ' . $observation['observation_date'] . ' has been published to your portal.',
                '/faculty/evaluations/' . $observationId,
                'evaluation'
            );
        });
    }

    public function returnForCorrection(int $observationId, string $reason, array $dean): void
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }
        if (!in_array($observation['status'], [Observation::SUBMITTED, Observation::PUBLISHED], true)) {
            throw new \RuntimeException('Only submitted or published observations can be returned for correction.');
        }

        Database::transaction(function () use ($observationId, $reason, $dean, $observation): void {
            Observation::updateById($observationId, [
                'status'        => Observation::RETURNED,
                'return_reason' => $reason,
                'returned_at'   => date('Y-m-d H:i:s'),
                'returned_by'   => (int) $dean['user_id'],
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.returned',
                'Classroom Observation',
                'observation:' . $observationId,
                'Returned for correction. Reason: ' . $reason
            );

            Notification::push(
                (int) $observation['faculty_id'],
                'Evaluation returned for correction',
                'An evaluation record was returned for correction. Please contact the Dean if you have questions.',
                '/faculty/evaluations',
                'warning'
            );
        });
    }

    public function archive(int $observationId, array $dean): void
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }
        if ($observation['status'] === Observation::ARCHIVED) {
            return;
        }
        if (in_array($observation['status'], [Observation::DRAFT, Observation::SUBMITTED], true)) {
            throw new \RuntimeException('Only published or returned observations can be archived.');
        }

        Database::transaction(function () use ($observationId, $dean): void {
            Observation::updateById($observationId, [
                'status'      => Observation::ARCHIVED,
                'archived_at' => date('Y-m-d H:i:s'),
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.archived',
                'Classroom Observation',
                'observation:' . $observationId,
                'Archived; ratings preserved.'
            );
        });
    }

    /**
     * Exceptional amendment of a published observation. Original ratings and
     * comments are preserved in observation_amendments before changes apply.
     *
     * @param array<int,mixed> $ratings
     */
    public function amendPublished(int $observationId, array $ratings, ?string $comments, string $reason, array $dean): array
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }
        if ($observation['status'] !== Observation::PUBLISHED) {
            throw new \RuntimeException('Only published observations can be amended.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('An amendment reason is required.');
        }

        $validation = $this->scoring->validate($ratings, true);
        if (!$validation['valid']) {
            return ['valid' => false, 'errors' => $validation['errors']];
        }

        $original = [
            'status'            => $observation['status'],
            'observer_comments' => $observation['observer_comments'],
            'teaching_avg'      => $observation['teaching_competence_avg'],
            'personality_avg'   => $observation['personality_avg'],
            'overall_avg'       => $observation['overall_avg'],
            'ratings'           => ObservationRating::scoreMap($observationId),
            'captured_at'       => date('c'),
        ];

        $summary = $this->scoring->calculate($validation['scores']);

        Database::transaction(function () use ($observationId, $validation, $summary, $comments, $reason, $dean, $original, $observation): void {
            $amendmentId = ObservationAmendment::begin($observationId, (int) $dean['user_id'], $reason, $original);

            ObservationRating::replaceForObservation(
                $observationId,
                $validation['scores'],
                COFCriterion::TEACHING,
                COFCriterion::PERSONALITY
            );

            Observation::updateById($observationId, [
                'observer_comments'       => $this->cleanComments($comments),
                'teaching_competence_avg' => $summary['teaching_avg'],
                'personality_avg'         => $summary['personality_avg'],
                'overall_avg'             => $summary['overall_avg'],
            ]);

            ObservationAmendment::complete($amendmentId, [
                'observer_comments' => $this->cleanComments($comments),
                'ratings'           => $validation['scores'],
                'teaching_avg'      => $summary['teaching_avg'],
                'personality_avg'   => $summary['personality_avg'],
                'overall_avg'       => $summary['overall_avg'],
                'completed_at'      => date('c'),
            ]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.amended',
                'Classroom Observation',
                'observation:' . $observationId,
                'Published record amended. Reason: ' . $reason
            );

            Notification::push(
                (int) $observation['faculty_id'],
                'Evaluation amended',
                'A published evaluation was amended. The reason has been recorded in the audit trail.',
                '/faculty/evaluations/' . $observationId,
                'warning'
            );
        });

        return ['valid' => true, 'errors' => [], 'summary' => $summary];
    }

    public function deleteDraft(int $observationId, array $dean): void
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }
        if ($observation['status'] !== Observation::DRAFT) {
            throw new \RuntimeException('Only drafts can be discarded.');
        }

        Database::transaction(function () use ($observationId, $dean): void {
            ObservationRating::deleteForObservation($observationId);
            $stmt = Database::connection()->prepare('DELETE FROM observations WHERE observation_id = ? AND status = ?');
            $stmt->execute([$observationId, Observation::DRAFT]);

            AuditLog::record(
                (int) $dean['user_id'],
                'observation.draft_discarded',
                'Classroom Observation',
                'observation:' . $observationId,
                'Unused draft discarded.'
            );
        });
    }

    private function requireEditable(int $observationId, bool $allowPublished = false): array
    {
        $observation = Observation::findDetailed($observationId);
        if ($observation === null) {
            throw new \RuntimeException('Observation not found.');
        }

        $editable = [Observation::DRAFT, Observation::RETURNED];
        if ($allowPublished) {
            $editable[] = Observation::PUBLISHED;
        }
        if (!in_array($observation['status'], $editable, true)) {
            throw new \RuntimeException('This observation is not editable in its current status (' . $observation['status'] . ').');
        }

        return $observation;
    }

    private function cleanComments(?string $comments): ?string
    {
        $comments = trim((string) $comments);
        return $comments === '' ? null : $comments;
    }
}
