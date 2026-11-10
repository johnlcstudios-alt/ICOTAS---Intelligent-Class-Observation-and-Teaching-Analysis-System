<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\UAT;
use App\Services\UatService;

final class UatController extends BaseController
{
    private UAT $uat;
    private UatService $service;

    public function __construct()
    {
        $this->uat = new UAT();
        $this->service = new UatService();
    }

    public function index(): void
    {
        $this->view('dean/uat/index', [
            'pageTitle' => 'User Acceptance Testing',
            'surveys' => $this->uat->surveys($this->demo()),
            'references' => $this->uat->referenceResults(),
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $this->view('dean/uat/create', [
            'pageTitle' => 'New UAT Survey',
            'characteristics' => UAT::CHARACTERISTICS,
            'demo' => $this->demo(),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $result = $this->service->createSurvey([
            'title' => $this->postString('title'),
            'description' => $this->postString('description'),
            'standard_questions' => $this->input('standard_questions') ? 1 : 0,
        ], (int) Auth::id(), $this->demo());
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/create');
        }
        $this->success($result[1], 'dean/uat/' . (int) $result[2]);
    }

    public function show(string $id): void
    {
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $respondents = $this->uat->respondents((int) $id);
        $links = [];
        foreach ($respondents as $respondent) {
            $link = $this->service->respondentLink($respondent);
            if ($link) {
                $links[(int) $respondent['id']] = $link;
            }
        }
        $this->view('dean/uat/show', [
            'pageTitle' => $survey['title'],
            'survey' => $survey,
            'questions' => $this->uat->questions((int) $id),
            'respondents' => $respondents,
            'links' => $links,
            'summary' => $this->uat->resultSummary((int) $id),
            'references' => $this->uat->referenceResults(),
            'characteristics' => UAT::CHARACTERISTICS,
            'latestLink' => Session::pull('_uat_link', ''),
            'demo' => $this->demo(),
        ]);
    }

    public function addQuestion(string $id): void
    {
        $this->requirePost();
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->addQuestion(
            (int) $id,
            $this->postString('characteristic'),
            $this->postString('question_text'),
            $this->intInput('order_index', 1)
        );
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/' . (int) $id);
        }
        $this->audit('uat.question_add', 'uat_survey', (int) $id, 'Added a UAT statement');
        $this->success($result[1], 'dean/uat/' . (int) $id);
    }

    public function deleteQuestion(string $id, string $questionId): void
    {
        $this->requirePost();
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->deleteQuestion((int) $id, (int) $questionId);
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/' . (int) $id);
        }
        $this->success($result[1], 'dean/uat/' . (int) $id);
    }

    public function status(string $id): void
    {
        $this->requirePost();
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->setStatus((int) $id, $this->postString('status'));
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/' . (int) $id);
        }
        $this->audit('uat.status', 'uat_survey', (int) $id, 'Changed UAT status');
        $this->success($result[1], 'dean/uat/' . (int) $id);
    }

    public function dates(string $id): void
    {
        $this->requirePost();
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->updateDates(
            (int) $id,
            $this->postString('opens_at') ?: null,
            $this->postString('closes_at') ?: null
        );
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/' . (int) $id);
        }
        $this->success($result[1], 'dean/uat/' . (int) $id);
    }

    public function addRespondent(string $id): void
    {
        $this->requirePost();
        $survey = $this->uat->findSurvey((int) $id);
        if (!$survey || !$this->inScope($survey)) {
            abort(404);
        }
        $result = $this->service->addRespondent(
            (int) $id,
            $this->postString('name'),
            $this->postString('respondent_type', 'IT Professional'),
            null
        );
        if (!$result[0]) {
            $this->backWithError($result[1], 'dean/uat/' . (int) $id);
        }
        $respondent = $this->uat->respondents((int) $id);
        $last = end($respondent);
        $link = $last ? $this->service->respondentLink($last) : null;
        if ($link) {
            Session::set('_uat_link', $link);
        }
        $this->audit('uat.respondent_add', 'uat_survey', (int) $id, 'Added UAT respondent invitation');
        $this->success($result[1] . ' Share the single-use invitation link shown below.', 'dean/uat/' . (int) $id);
    }

    public function respond(string $token): void
    {
        $respondent = $this->service->respondentByToken($token);
        if (!$respondent) {
            $this->view('uat/respond', [
                'pageTitle' => 'UAT Invitation',
                'respondent' => null,
                'questions' => [],
                'error' => 'This UAT invitation link is invalid or has expired.',
                'token' => $token,
            ], 'layouts/public');
            return;
        }
        $questions = $this->uat->questions((int) $respondent['uat_survey_id']);
        $closed = $respondent['survey_status'] !== 'active'
            || ($respondent['opens_at'] && strtotime((string) $respondent['opens_at']) > time())
            || ($respondent['closes_at'] && strtotime((string) $respondent['closes_at']) < time());

        $this->view('uat/respond', [
            'pageTitle' => 'UAT — ' . $respondent['survey_title'],
            'respondent' => $respondent,
            'questions' => $questions,
            'error' => $closed ? 'This UAT survey is not currently accepting responses.' : '',
            'already' => $respondent['status'] === 'submitted',
            'token' => $token,
        ], 'layouts/public');
    }

    public function submitResponse(string $token): void
    {
        $this->requirePost();
        $ratings = $this->postArray('ratings');
        $result = $this->service->submitByToken($token, $ratings);
        if (!$result[0]) {
            $this->backWithError($result[1], 'uat/respond/' . urlencode($token));
        }
        $this->success($result[1], 'uat/respond/' . urlencode($token));
    }
}
