<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Settings;
use App\Models\AuditLog;
use App\Services\AuthorizationService;
use App\Services\AuthenticationService;

/**
 * Dean-only system settings. Institution-specific grading rules are
 * configurable but the defaults are documented as pending institutional
 * verification in the documentation.
 */
final class SettingsController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $this->render('dean/settings/index', [
            'pageTitle'    => 'System Settings',
            'pageSubtitle' => 'Institution identity, security, COF rating labels and class-record grading rules.',
            'activeNav'    => 'settings',
            'settings'     => Settings::all(),
        ]);
    }

    public function update(): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $input = [
            'institution_name'     => trim((string) Request::input('institution_name', '')),
            'institution_address'  => trim((string) Request::input('institution_address', '')),
            'system_name'          => trim((string) Request::input('system_name', '')),
            'system_full_name'     => trim((string) Request::input('system_full_name', '')),
            'timezone'             => trim((string) Request::input('timezone', 'Asia/Manila')),
            'maintenance_mode'     => Request::input('maintenance_mode', '0') === '1' ? '1' : '0',
            'session_timeout_minutes' => trim((string) Request::input('session_timeout_minutes', '30')),
            'session_absolute_minutes'=> trim((string) Request::input('session_absolute_minutes', '480')),
            'qr_expiry_days'       => trim((string) Request::input('qr_expiry_days', '365')),
            'login_max_attempts'   => trim((string) Request::input('login_max_attempts', '5')),
            'login_lockout_minutes'=> trim((string) Request::input('login_lockout_minutes', '15')),
            'rating_label_1'       => trim((string) Request::input('rating_label_1', '')),
            'rating_label_2'       => trim((string) Request::input('rating_label_2', '')),
            'rating_label_3'       => trim((string) Request::input('rating_label_3', '')),
            'rating_label_4'       => trim((string) Request::input('rating_label_4', '')),
            'rating_label_5'       => trim((string) Request::input('rating_label_5', '')),
            'grading_min'          => trim((string) Request::input('grading_min', '0')),
            'grading_max'          => trim((string) Request::input('grading_max', '100')),
            'passing_grade'        => trim((string) Request::input('passing_grade', '75')),
            'class_record_require_complete' => Request::input('class_record_require_complete', '0') === '1' ? '1' : '0',
        ];

        $validated = $this->validate($input, [
            'institution_name'      => 'required|max:150',
            'institution_address'   => 'required|max:255',
            'system_name'           => 'required|max:60',
            'system_full_name'      => 'required|max:200',
            'timezone'              => 'required|max:64',
            'session_timeout_minutes' => 'required|integer|min:5|max:240',
            'session_absolute_minutes'=> 'required|integer|min:30|max:1440',
            'qr_expiry_days'        => 'required|integer|min:1|max:3650',
            'login_max_attempts'    => 'required|integer|min:3|max:20',
            'login_lockout_minutes' => 'required|integer|min:1|max:120',
            'rating_label_1'        => 'required|max:60',
            'rating_label_2'        => 'required|max:60',
            'rating_label_3'        => 'required|max:60',
            'rating_label_4'        => 'required|max:60',
            'rating_label_5'        => 'required|max:60',
            'grading_min'           => 'required|numeric',
            'grading_max'           => 'required|numeric',
            'passing_grade'         => 'required|numeric',
        ], [
            'institution_name'      => 'Institution name',
            'institution_address'   => 'Institution address',
            'system_name'           => 'System short name',
            'system_full_name'      => 'System full name',
            'timezone'              => 'Timezone',
            'session_timeout_minutes' => 'Idle session timeout',
            'session_absolute_minutes'=> 'Absolute session timeout',
            'qr_expiry_days'        => 'QR validity (days)',
            'login_max_attempts'    => 'Login attempt limit',
            'login_lockout_minutes' => 'Lockout window',
            'rating_label_1'        => 'Rating label 1',
            'rating_label_2'        => 'Rating label 2',
            'rating_label_3'        => 'Rating label 3',
            'rating_label_4'        => 'Rating label 4',
            'rating_label_5'        => 'Rating label 5',
            'grading_min'           => 'Minimum grade',
            'grading_max'           => 'Maximum grade',
            'passing_grade'         => 'Passing grade',
        ], '/dean/settings');

        if (!in_array($validated['timezone'], timezone_identifiers_list(), true)) {
            Flash::withErrors(['timezone' => 'Select a valid PHP timezone identifier.'], $input);
            Flash::error('The timezone is not recognised.');
            redirect('/dean/settings');
        }

        $min = (float) $validated['grading_min'];
        $max = (float) $validated['grading_max'];
        $passing = (float) $validated['passing_grade'];

        if ($min >= $max) {
            Flash::withErrors(['grading_min' => 'The minimum grade must be lower than the maximum grade.'], $input);
            Flash::error('The grading range is invalid.');
            redirect('/dean/settings');
        }
        if ($passing < $min || $passing > $max) {
            Flash::withErrors(['passing_grade' => 'The passing grade must fall within the configured grading range.'], $input);
            Flash::error('The passing grade is outside the grading range.');
            redirect('/dean/settings');
        }
        if ((int) $validated['session_timeout_minutes'] > (int) $validated['session_absolute_minutes']) {
            Flash::withErrors(['session_timeout_minutes' => 'The idle timeout cannot exceed the absolute session lifetime.'], $input);
            Flash::error('The session timeout configuration is inconsistent.');
            redirect('/dean/settings');
        }

        Settings::setMany([
            'institution_name'        => $validated['institution_name'],
            'institution_address'     => $validated['institution_address'],
            'system_name'             => $validated['system_name'],
            'system_full_name'        => $validated['system_full_name'],
            'timezone'                => $validated['timezone'],
            'maintenance_mode'        => $validated['maintenance_mode'],
            'session_timeout_minutes' => $validated['session_timeout_minutes'],
            'session_absolute_minutes'=> $validated['session_absolute_minutes'],
            'qr_expiry_days'          => $validated['qr_expiry_days'],
            'login_max_attempts'      => $validated['login_max_attempts'],
            'login_lockout_minutes'   => $validated['login_lockout_minutes'],
            'rating_label_1'          => $validated['rating_label_1'],
            'rating_label_2'          => $validated['rating_label_2'],
            'rating_label_3'          => $validated['rating_label_3'],
            'rating_label_4'          => $validated['rating_label_4'],
            'rating_label_5'          => $validated['rating_label_5'],
            'grading_min'             => number_format($min, 2, '.', ''),
            'grading_max'             => number_format($max, 2, '.', ''),
            'passing_grade'           => number_format($passing, 2, '.', ''),
            'class_record_require_complete' => $validated['class_record_require_complete'],
        ], (int) $dean['user_id'], 'settings');

        AuditLog::record(
            (int) $dean['user_id'],
            'settings.updated',
            'System Settings',
            'settings',
            'Institution, security, rating-label and grading settings updated.'
        );

        Flash::success('Settings saved. Changes apply to new requests immediately.');
        redirect('/dean/settings');
    }
}
