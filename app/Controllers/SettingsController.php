<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Setting;

final class SettingsController extends BaseController
{
    private const DEFINITIONS = [
        'institution_name' => ['group' => 'general', 'type' => 'text', 'label' => 'Institution name', 'max' => 180],
        'system_name' => ['group' => 'general', 'type' => 'text', 'label' => 'System name', 'max' => 60],
        'system_tagline' => ['group' => 'general', 'type' => 'text', 'label' => 'System tagline', 'max' => 180],
        'privacy_min_responses' => ['group' => 'privacy', 'type' => 'int', 'label' => 'Minimum responses before comments are shown', 'min' => 1, 'max' => 50],
        'privacy_show_comments' => ['group' => 'privacy', 'type' => 'bool', 'label' => 'Allow anonymized comments above the privacy threshold'],
        'prediction_min_responses' => ['group' => 'prediction', 'type' => 'int', 'label' => 'Minimum responses for a prediction', 'min' => 1, 'max' => 10000],
        'prediction_min_periods' => ['group' => 'prediction', 'type' => 'int', 'label' => 'Minimum comparable academic periods', 'min' => 1, 'max' => 20],
        'prediction_window' => ['group' => 'prediction', 'type' => 'int', 'label' => 'Recent periods used in the trend window', 'min' => 1, 'max' => 30],
        'prediction_improving_slope' => ['group' => 'prediction', 'type' => 'decimal', 'label' => 'Improving slope threshold', 'min' => 0, 'max' => 1],
        'prediction_declining_slope' => ['group' => 'prediction', 'type' => 'decimal', 'label' => 'Declining slope threshold', 'min' => -1, 'max' => 0],
        'prediction_negative_ratio' => ['group' => 'prediction', 'type' => 'decimal', 'label' => 'Negative sentiment ratio threshold', 'min' => 0, 'max' => 1],
        'prediction_forecast_change' => ['group' => 'prediction', 'type' => 'decimal', 'label' => 'Forecast change threshold', 'min' => 0, 'max' => 2],
        'allow_student_self_scan' => ['group' => 'evaluation', 'type' => 'bool', 'label' => 'Allow students to open an active evaluation from their portal'],
        'default_session_minutes' => ['group' => 'evaluation', 'type' => 'int', 'label' => 'Default QR session length (minutes)', 'min' => 5, 'max' => 43200],
        'current_academic_year_id' => ['group' => 'academic', 'type' => 'reference', 'label' => 'Current academic year', 'table' => 'academic_years'],
        'current_semester_id' => ['group' => 'academic', 'type' => 'reference', 'label' => 'Current semester', 'table' => 'semesters'],
    ];

    public function index(): void
    {
        $settings = Setting::all();
        $this->view('dean/settings/index', [
            'pageTitle' => 'System Settings',
            'settings' => $settings,
            'definitions' => self::DEFINITIONS,
            'years' => Database::fetchAll('SELECT id, name FROM academic_years ORDER BY start_date DESC'),
            'semesters' => Database::fetchAll('SELECT s.id, s.name, ay.name AS year FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.sequence'),
            'demo' => $this->demo(),
        ]);
    }

    public function update(): void
    {
        $this->requirePost();
        $userId = (int) Auth::id();
        $updates = 0;
        $errors = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $submitted = $this->input($key);
            if ($definition['type'] === 'bool') {
                Setting::set($key, $submitted ? '1' : '0', $definition['group'], $userId);
                $updates++;
                continue;
            }
            if ($submitted === null) {
                continue;
            }
            $value = trim((string) $submitted);
            if ($definition['type'] === 'int') {
                if ($value === '' || !preg_match('/^\d+$/', $value) || (int) $value < $definition['min'] || (int) $value > $definition['max']) {
                    $errors[] = $definition['label'] . ' must be a whole number between ' . $definition['min'] . ' and ' . $definition['max'] . '.';
                    continue;
                }
                $value = (string) (int) $value;
            } elseif ($definition['type'] === 'decimal') {
                if ($value === '' || !is_numeric($value) || (float) $value < $definition['min'] || (float) $value > $definition['max']) {
                    $errors[] = $definition['label'] . ' must be between ' . $definition['min'] . ' and ' . $definition['max'] . '.';
                    continue;
                }
                $value = (string) round((float) $value, 4);
            } elseif ($definition['type'] === 'reference') {
                if ($value !== '') {
                    $exists = Database::scalar('SELECT COUNT(*) FROM ' . $definition['table'] . ' WHERE id = ?', [(int) $value], 0);
                    if ((int) $exists === 0) {
                        $errors[] = 'The selected ' . strtolower($definition['label']) . ' no longer exists.';
                        continue;
                    }
                }
            } elseif (isset($definition['max']) && mb_strlen($value) > $definition['max']) {
                $errors[] = $definition['label'] . ' is too long.';
                continue;
            }
            Setting::set($key, $value, $definition['group'], $userId);
            $updates++;
        }

        $predictionMap = [
            'prediction_min_responses' => 'min_responses',
            'prediction_min_periods' => 'min_periods',
            'prediction_window' => 'window_periods',
            'prediction_improving_slope' => 'improving_slope',
            'prediction_declining_slope' => 'declining_slope',
            'prediction_negative_ratio' => 'negative_ratio',
            'prediction_forecast_change' => 'forecast_change',
        ];
        foreach ($predictionMap as $settingKey => $configKey) {
            $value = Setting::get($settingKey);
            if ($value !== null) {
                Database::execute(
                    'INSERT INTO prediction_configurations (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$configKey, (string) $value]
                );
            }
        }

        Setting::clearCache();
        $this->audit('settings.update', 'system_setting', null, 'Updated ' . $updates . ' system setting(s)');

        if (!empty($errors)) {
            flash('warning', 'Settings saved with ' . count($errors) . ' problem(s): ' . implode(' ', array_slice($errors, 0, 3)));
            redirect('dean/settings');
        }
        $this->success('System settings saved.', 'dean/settings');
    }
}
