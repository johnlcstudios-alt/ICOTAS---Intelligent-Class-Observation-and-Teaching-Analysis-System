<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Survey;
use App\Services\SurveyService;

final class SurveyController extends BaseController
{
    private Survey $surveys;
    private SurveyService $service;

    public function __construct()
    {
        $this->surveys = new Survey();
        $this->service = new SurveyService();
    }

    public function index(): void
    {
        $this->view('dean/surveys/index', [
            'pageTitle' => 'Survey Builder',
            'surveys' => $this->surveys->listing($this->demo()),
            'templateCount' => count($this->surveys->templates()),
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $this->view('dean/surveys/create', [
            'pageTitle' => 'New Survey',
            'categories' => $this->categories(),
            'demo' => $this->demo(),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $title = $this->postString('title');
        if ($title === '') {
            $this->backWithError('Survey title is required.', 'dean/surveys/create');
        }
        [$surveyId, $versionId] = $this->service->createSurvey(
            $title,
            $this->postString('description') ?: null,
            $this->postString('category', 'Teaching Effectiveness'),
            (int) Auth::id(),
            $this->demo()
        );
        $this->audit('survey.create', 'survey', $surveyId, 'Created survey ' . $title);
        $this->success('Survey created. Add questions, then publish.', 'dean/surveys/' . $surveyId . '/builder?version_id=' . $versionId);
    }

    public function templates(): void
    {
        $this->view('dean/surveys/templates', [
            'pageTitle' => 'Survey Templates',
            'templates' => $this->surveys->templates(),
            'demo' => $this->demo(),
        ]);
    }

    public function cloneTemplate(string $id): void
    {
        $this->requirePost();
        $result = $this->service->cloneTemplate((int) $id, (int) Auth::id(), $this->demo());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/surveys/templates');
        }
        $this->audit('survey.template_clone', 'survey', (int) ($result[2] ?? 0), 'Cloned a survey template');
        $this->success($result[1], 'dean/surveys/' . (int) $result[2] . '/builder');
    }

    public function builder(string $id): void
    {
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $versions = $this->surveys->versions((int) $id);
        $requested = $this->intInput('version_id');
        $version = null;
        if ($requested > 0) {
            $candidate = $this->surveys->version($requested);
            if ($candidate && (int) $candidate['survey_id'] === (int) $id) {
                $version = $candidate;
            }
        }
        if (!$version) {
            $version = $this->surveys->draftVersion((int) $id)
                ?? $this->surveys->publishedVersion((int) $id)
                ?? $this->surveys->latestVersion((int) $id);
        }
        if (!$version) {
            $this->backWithError('This survey has no questionnaire version.', 'dean/surveys');
        }
        $questions = $this->surveys->questions((int) $version['id']);

        $this->view('dean/surveys/builder', [
            'pageTitle' => 'Survey Builder — ' . $survey['title'],
            'survey' => $survey,
            'versions' => $versions,
            'version' => $version,
            'questions' => $questions,
            'ratingLabels' => $this->service->ratingLabels($version),
            'questionTypes' => SurveyService::QUESTION_TYPES,
            'themes' => SurveyService::THEMES,
            'categories' => $this->categories(),
            'demo' => $this->demo(),
        ]);
    }

    public function saveBuilder(string $id): void
    {
        $this->requirePost();
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $payloadRaw = $this->postString('payload');
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $this->backWithError('The builder data could not be read. Save again.', 'dean/surveys/' . (int) $id . '/builder');
        }
        $versionId = $this->intInput('version_id');
        $version = $this->surveys->version($versionId);
        if (!$version || (int) $version['survey_id'] !== (int) $id) {
            $this->backWithError('Survey version not found.', 'dean/surveys/' . (int) $id . '/builder');
        }
        $payload['title'] = $payload['title'] ?? $survey['title'];
        $payload['description'] = $payload['description'] ?? $survey['description'];
        $payload['category'] = $payload['category'] ?? $survey['category'];

        $result = $this->service->saveBuilder((int) $id, $versionId, $payload, (int) Auth::id());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/surveys/' . (int) $id . '/builder?version_id=' . $versionId);
        }
        $this->audit('survey.builder_save', 'survey_version', $versionId, 'Saved survey draft');
        $this->success($result[1], 'dean/surveys/' . (int) $id . '/builder?version_id=' . $versionId);
    }

    public function publish(string $id): void
    {
        $this->requirePost();
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $versionId = $this->intInput('version_id');
        if ($versionId <= 0) {
            $draft = $this->surveys->draftVersion((int) $id);
            $versionId = $draft ? (int) $draft['id'] : 0;
        }
        $result = $this->service->publish((int) $id, $versionId);
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/surveys/' . (int) $id . '/builder');
        }
        $this->audit('survey.publish', 'survey_version', $versionId, 'Published a survey version');
        $this->success($result[1], 'dean/surveys/' . (int) $id . '/builder?version_id=' . $versionId);
    }

    public function newVersion(string $id): void
    {
        $this->requirePost();
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->newVersion((int) $id, (int) Auth::id());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/surveys');
        }
        $versionId = (int) ($result[2] ?? 0);
        $this->audit('survey.new_version', 'survey', (int) $id, 'Created a new draft version');
        $this->success($result[1], 'dean/surveys/' . (int) $id . '/builder' . ($versionId ? '?version_id=' . $versionId : ''));
    }

    public function duplicate(string $id): void
    {
        $this->requirePost();
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->duplicate((int) $id, (int) Auth::id(), $this->demo());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/surveys');
        }
        $newId = (int) ($result[2] ?? 0);
        $this->audit('survey.duplicate', 'survey', $newId, 'Duplicated survey #' . (int) $id);
        $this->success($result[1], $newId ? 'dean/surveys/' . $newId . '/builder' : 'dean/surveys');
    }

    public function archive(string $id): void
    {
        $this->requirePost();
        $survey = $this->surveys->findDetailed((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->archive((int) $id);
        $this->audit('survey.archive', 'survey', (int) $id, 'Archived survey');
        $this->success($result[1], 'dean/surveys');
    }

    private function categories(): array
    {
        return [
            'Teaching Effectiveness',
            'Course Delivery',
            'Learning Environment',
            'Instructor Support',
            'Assessment and Feedback',
            'Laboratory / Practicum',
            'Institutional Services',
        ];
    }
}
