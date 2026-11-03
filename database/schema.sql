-- =====================================================================
-- ICOTAS 1.0 — Database Schema (MySQL 8 / MariaDB 10.4+)
-- Intelligent Classroom Observation and Teaching Analysis System
-- ACLC College Tacloban
--
-- This file is non-destructive (CREATE TABLE IF NOT EXISTS) and is
-- intended to run against an EMPTY, dedicated ICOTAS database.
--   CREATE DATABASE icotas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE icotas_db;
--   SOURCE database/schema.sql;
-- The bundled installer (tools/install.php) performs these steps safely.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ---------------------------------------------------------------------
-- 1. departments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    department_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(150) NOT NULL,
    department_code VARCHAR(20)  NOT NULL,
    description     TEXT NULL,
    status          ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (department_id),
    UNIQUE KEY uq_departments_code (department_code),
    UNIQUE KEY uq_departments_name (department_name),
    KEY idx_departments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. users  (exactly two roles: Dean, Faculty)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name           VARCHAR(150) NOT NULL,
    username            VARCHAR(60)  NOT NULL,
    email               VARCHAR(190) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    role                ENUM('Dean','Faculty') NOT NULL,
    department_id       INT UNSIGNED NULL,
    employee_no         VARCHAR(40) NULL,
    contact_no          VARCHAR(30) NULL,
    account_status      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at       DATETIME NULL,
    password_changed_at DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- one and only one Dean may exist in the system
    dean_slot           TINYINT GENERATED ALWAYS AS (IF(role = 'Dean', 1, NULL)) VIRTUAL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_single_dean (dean_slot),
    KEY idx_users_role (role),
    KEY idx_users_department (department_id),
    KEY idx_users_status (account_status),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id)
        REFERENCES departments (department_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. academic_periods
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_periods (
    period_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year  VARCHAR(20) NOT NULL,
    semester       ENUM('First Semester','Second Semester','Summer') NOT NULL,
    period_start   DATE NOT NULL,
    period_end     DATE NOT NULL,
    status         ENUM('Upcoming','Current','Closed') NOT NULL DEFAULT 'Upcoming',
    notes          VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    current_slot   TINYINT GENERATED ALWAYS AS (IF(status = 'Current', 1, NULL)) VIRTUAL,
    PRIMARY KEY (period_id),
    UNIQUE KEY uq_period_year_sem (academic_year, semester),
    UNIQUE KEY uq_period_single_current (current_slot),
    KEY idx_period_status (status),
    CONSTRAINT chk_period_dates CHECK (period_end >= period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. classes and subjects (class offering = subject + section + faculty)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS classes (
    class_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_code   VARCHAR(30)  NOT NULL,
    subject_name   VARCHAR(150) NOT NULL,
    section_name   VARCHAR(60)  NOT NULL,
    department_id  INT UNSIGNED NOT NULL,
    faculty_id     INT UNSIGNED NOT NULL,
    period_id      INT UNSIGNED NOT NULL,
    academic_year  VARCHAR(20)  NOT NULL,
    semester       ENUM('First Semester','Second Semester','Summer') NOT NULL,
    status         ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id),
    UNIQUE KEY uq_class_offering (period_id, subject_code, section_name),
    KEY idx_classes_faculty (faculty_id),
    KEY idx_classes_department (department_id),
    KEY idx_classes_period (period_id),
    KEY idx_classes_status (status),
    CONSTRAINT fk_classes_department FOREIGN KEY (department_id)
        REFERENCES departments (department_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_classes_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_classes_period FOREIGN KEY (period_id)
        REFERENCES academic_periods (period_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. cof_criteria — configurable 20-item official COF
--    NOTE: seeded wording is PROVISIONAL until verified by the institution.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cof_criteria (
    criterion_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    criterion_number TINYINT UNSIGNED NOT NULL,
    category         ENUM('Teaching Competence','Personality') NOT NULL,
    criterion_text   TEXT NOT NULL,
    display_order    INT NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    source_note      VARCHAR(255) NOT NULL DEFAULT 'PROVISIONAL — pending institutional verification',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (criterion_id),
    UNIQUE KEY uq_cof_number (criterion_number),
    KEY idx_cof_category (category, display_order),
    CONSTRAINT chk_cof_number CHECK (criterion_number BETWEEN 1 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. observation_assignments — Dean-planned classroom observations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS observation_assignments (
    assignment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    faculty_id        INT UNSIGNED NOT NULL,
    observer_id       INT UNSIGNED NOT NULL,
    class_id          INT UNSIGNED NOT NULL,
    assigned_date     DATE NOT NULL,
    due_date          DATE NOT NULL,
    assignment_status ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    notes             TEXT NULL,
    observation_id    INT UNSIGNED NULL,
    created_by        INT UNSIGNED NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (assignment_id),
    KEY idx_assign_faculty (faculty_id),
    KEY idx_assign_status (assignment_status),
    KEY idx_assign_due (due_date),
    CONSTRAINT fk_assign_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assign_observer FOREIGN KEY (observer_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assign_class FOREIGN KEY (class_id)
        REFERENCES classes (class_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assign_creator FOREIGN KEY (created_by)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_assign_dates CHECK (due_date >= assigned_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. observations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS observations (
    observation_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    faculty_id          INT UNSIGNED NOT NULL,
    observer_id         INT UNSIGNED NOT NULL,
    class_id            INT UNSIGNED NOT NULL,
    period_id           INT UNSIGNED NOT NULL,
    academic_year       VARCHAR(20) NOT NULL,
    semester            ENUM('First Semester','Second Semester','Summer') NOT NULL,
    observation_date    DATE NOT NULL,
    lesson_topic        VARCHAR(255) NOT NULL,
    observer_comments   TEXT NULL,
    status              ENUM('Draft','Submitted','Published','Returned for Correction','Archived')
                            NOT NULL DEFAULT 'Draft',
    teaching_competence_avg DECIMAL(4,2) NULL,
    personality_avg         DECIMAL(4,2) NULL,
    overall_avg             DECIMAL(4,2) NULL,
    submitted_at        DATETIME NULL,
    published_at        DATETIME NULL,
    archived_at         DATETIME NULL,
    return_reason       TEXT NULL,
    returned_at         DATETIME NULL,
    returned_by         INT UNSIGNED NULL,
    data_origin         ENUM('Production','Test') NOT NULL DEFAULT 'Production',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (observation_id),
    KEY idx_obs_faculty_status (faculty_id, status),
    KEY idx_obs_date (observation_date),
    KEY idx_obs_class (class_id),
    KEY idx_obs_period (period_id),
    KEY idx_obs_observer (observer_id),
    KEY idx_obs_status (status),
    CONSTRAINT fk_obs_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_obs_observer FOREIGN KEY (observer_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_obs_class FOREIGN KEY (class_id)
        REFERENCES classes (class_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_obs_period FOREIGN KEY (period_id)
        REFERENCES academic_periods (period_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_obs_returned_by FOREIGN KEY (returned_by)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. observation_ratings — exactly one rating per criterion per observation
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS observation_ratings (
    rating_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    observation_id   INT UNSIGNED NOT NULL,
    criterion_number TINYINT UNSIGNED NOT NULL,
    category         ENUM('Teaching Competence','Personality') NOT NULL,
    score            TINYINT UNSIGNED NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rating_id),
    UNIQUE KEY uq_rating_observation_criterion (observation_id, criterion_number),
    KEY idx_rating_criterion (criterion_number),
    CONSTRAINT fk_ratings_observation FOREIGN KEY (observation_id)
        REFERENCES observations (observation_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_rating_score CHECK (score BETWEEN 1 AND 5),
    CONSTRAINT chk_rating_criterion CHECK (criterion_number BETWEEN 1 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. class_records — academic tallies, independent from COF scores
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS class_records (
    record_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id       INT UNSIGNED NOT NULL,
    period_id      INT UNSIGNED NOT NULL,
    total_students INT UNSIGNED NOT NULL DEFAULT 0,
    passed_count   INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count   INT UNSIGNED NOT NULL DEFAULT 0,
    average_grade  DECIMAL(6,2) NULL,
    remarks        TEXT NULL,
    record_status  ENUM('Draft','Finalized') NOT NULL DEFAULT 'Draft',
    data_origin    ENUM('Production','Test') NOT NULL DEFAULT 'Production',
    created_by     INT UNSIGNED NOT NULL,
    updated_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    UNIQUE KEY uq_class_record_period (class_id, period_id),
    KEY idx_records_period (period_id),
    KEY idx_records_status (record_status),
    CONSTRAINT fk_records_class FOREIGN KEY (class_id)
        REFERENCES classes (class_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_period FOREIGN KEY (period_id)
        REFERENCES academic_periods (period_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_creator FOREIGN KEY (created_by)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_records_updater FOREIGN KEY (updated_by)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_records_total CHECK (total_students >= 0),
    CONSTRAINT chk_records_passed CHECK (passed_count <= total_students),
    CONSTRAINT chk_records_failed CHECK (failed_count <= total_students),
    CONSTRAINT chk_records_sum CHECK (passed_count + failed_count <= total_students),
    CONSTRAINT chk_records_grade CHECK (average_grade IS NULL OR average_grade >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable snapshots supporting class record history / audit
CREATE TABLE IF NOT EXISTS class_record_revisions (
    revision_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_id    INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    changed_by   INT UNSIGNED NULL,
    reason       VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (revision_id),
    KEY idx_revision_record (record_id, created_at),
    CONSTRAINT fk_revision_record FOREIGN KEY (record_id)
        REFERENCES class_records (record_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_revision_user FOREIGN KEY (changed_by)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. faculty_qr_tokens — only hashes of tokens are stored
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faculty_qr_tokens (
    token_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    faculty_id   INT UNSIGNED NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    issued_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL,
    last_used_at DATETIME NULL,
    token_status ENUM('Active','Revoked','Expired') NOT NULL DEFAULT 'Active',
    created_by   INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active_faculty_slot INT UNSIGNED GENERATED ALWAYS AS (IF(token_status = 'Active', faculty_id, NULL)) VIRTUAL,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_qr_hash (token_hash),
    UNIQUE KEY uq_qr_active_faculty (active_faculty_slot),
    KEY idx_qr_faculty (faculty_id),
    KEY idx_qr_status (token_status),
    CONSTRAINT fk_qr_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_qr_creator FOREIGN KEY (created_by)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_qr_expiry CHECK (expires_at > issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. faculty_acknowledgements
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faculty_acknowledgements (
    acknowledgement_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    observation_id          INT UNSIGNED NOT NULL,
    faculty_id              INT UNSIGNED NOT NULL,
    acknowledgement_status  VARCHAR(30) NOT NULL DEFAULT 'Acknowledged',
    acknowledgement_remarks TEXT NULL,
    acknowledged_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (acknowledgement_id),
    UNIQUE KEY uq_ack_observation_faculty (observation_id, faculty_id),
    KEY idx_ack_faculty (faculty_id),
    CONSTRAINT fk_ack_observation FOREIGN KEY (observation_id)
        REFERENCES observations (observation_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ack_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. ratee_comments — append-only faculty comment history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ratee_comments (
    comment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    observation_id INT UNSIGNED NOT NULL,
    faculty_id     INT UNSIGNED NOT NULL,
    comment_text   TEXT NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id),
    KEY idx_comment_observation (observation_id, created_at),
    KEY idx_comment_faculty (faculty_id),
    CONSTRAINT fk_comment_observation FOREIGN KEY (observation_id)
        REFERENCES observations (observation_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_comment_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. observation_amendments — preserves original published values
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS observation_amendments (
    amendment_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    observation_id           INT UNSIGNED NOT NULL,
    changed_by               INT UNSIGNED NOT NULL,
    reason                   TEXT NOT NULL,
    original_data_reference  LONGTEXT NOT NULL,
    amended_data_reference   LONGTEXT NULL,
    amendment_status         ENUM('In Progress','Completed','Cancelled') NOT NULL DEFAULT 'In Progress',
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at             DATETIME NULL,
    PRIMARY KEY (amendment_id),
    KEY idx_amendment_observation (observation_id, created_at),
    CONSTRAINT fk_amendment_observation FOREIGN KEY (observation_id)
        REFERENCES observations (observation_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_amendment_user FOREIGN KEY (changed_by)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. audit_logs — append-only administrative trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NULL,
    action           VARCHAR(80) NOT NULL,
    module           VARCHAR(60) NOT NULL,
    record_reference VARCHAR(80) NULL,
    details          TEXT NULL,
    ip_address       VARCHAR(45) NULL,
    user_agent       VARCHAR(255) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_module (module),
    KEY idx_audit_created (created_at),
    KEY idx_audit_action (action),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. login_attempts — authentication rate limiting and monitoring
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_identifier VARCHAR(190) NOT NULL,
    success_status     TINYINT(1) NOT NULL DEFAULT 0,
    ip_address         VARCHAR(45) NULL,
    user_agent         VARCHAR(255) NULL,
    attempted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY idx_attempt_identifier (account_identifier, attempted_at),
    KEY idx_attempt_ip (ip_address, attempted_at),
    KEY idx_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. prediction_runs — persisted Simple Linear Regression results
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prediction_runs (
    prediction_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    faculty_id                 INT UNSIGNED NOT NULL,
    model_name                 VARCHAR(60) NOT NULL DEFAULT 'Simple Linear Regression v1',
    training_observation_count INT UNSIGNED NOT NULL DEFAULT 0,
    historical_period_count    INT UNSIGNED NOT NULL DEFAULT 0,
    validation_period_count    INT UNSIGNED NOT NULL DEFAULT 0,
    slope                      DECIMAL(10,4) NULL,
    intercept                  DECIMAL(10,4) NULL,
    predicted_score            DECIMAL(4,2) NULL,
    mae                        DECIMAL(7,4) NULL,
    rmse                       DECIMAL(7,4) NULL,
    model_status               ENUM('Sufficient','Insufficient Data') NOT NULL,
    generated_by               INT UNSIGNED NOT NULL,
    generated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (prediction_id),
    KEY idx_prediction_faculty (faculty_id, generated_at),
    CONSTRAINT fk_prediction_faculty FOREIGN KEY (faculty_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_prediction_user FOREIGN KEY (generated_by)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_prediction_score CHECK (predicted_score IS NULL OR (predicted_score BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. settings — institution-configurable values
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    KEY idx_settings_group (setting_group),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. notifications — event-driven in-system notices
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    link            VARCHAR(255) NULL,
    type            ENUM('info','success','warning','evaluation') NOT NULL DEFAULT 'info',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Baseline settings (non-destructive)
-- =====================================================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
    ('institution_name', 'ACLC College Tacloban', 'general'),
    ('institution_address', 'Tacloban City, Leyte, Philippines', 'general'),
    ('system_name', 'ICOTAS', 'general'),
    ('system_full_name', 'Intelligent Classroom Observation and Teaching Analysis System', 'general'),
    ('timezone', 'Asia/Manila', 'general'),
    ('maintenance_mode', '0', 'general'),
    ('seed_mode', 'production', 'general'),
    ('session_timeout_minutes', '30', 'security'),
    ('session_absolute_minutes', '480', 'security'),
    ('qr_expiry_days', '365', 'security'),
    ('login_max_attempts', '5', 'security'),
    ('login_lockout_minutes', '15', 'security'),
    ('cof_verified', '0', 'cof'),
    ('cof_verified_by', '', 'cof'),
    ('cof_verified_at', '', 'cof'),
    ('cof_source_note', 'Provisional wording — official ACLC Classroom Observation Form (Version 1, Series 2012) text must be verified by the institution.', 'cof'),
    ('rating_label_1', 'Needs Improvement', 'cof'),
    ('rating_label_2', 'Fair', 'cof'),
    ('rating_label_3', 'Satisfactory', 'cof'),
    ('rating_label_4', 'Very Satisfactory', 'cof'),
    ('rating_label_5', 'Outstanding', 'cof'),
    ('grading_min', '0', 'grading'),
    ('grading_max', '100', 'grading'),
    ('passing_grade', '75', 'grading'),
    ('class_record_require_complete', '1', 'grading')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================================
-- Provisional 20-item COF structure (13 Teaching Competence + 7 Personality)
-- These are configuration defaults, NOT verified official text. Each row is
-- explicitly marked provisional and the Dean must verify wording in-system
-- before the institution relies on it. Do not describe these as official.
-- =====================================================================
INSERT INTO cof_criteria (criterion_number, category, criterion_text, display_order, is_active, source_note) VALUES
    (1,  'Teaching Competence', 'Demonstrates mastery and accurate understanding of the subject matter.', 1, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (2,  'Teaching Competence', 'States learning objectives clearly and relates them to the lesson.', 2, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (3,  'Teaching Competence', 'Organizes lesson content logically and sequentially.', 3, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (4,  'Teaching Competence', 'Uses appropriate and updated instructional materials.', 4, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (5,  'Teaching Competence', 'Integrates technology effectively to support learning.', 5, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (6,  'Teaching Competence', 'Uses varied teaching strategies suited to learners'' needs.', 6, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (7,  'Teaching Competence', 'Encourages active participation and critical thinking.', 7, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (8,  'Teaching Competence', 'Asks purposeful questions and provides constructive feedback.', 8, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (9,  'Teaching Competence', 'Manages classroom time and routines efficiently.', 9, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (10, 'Teaching Competence', 'Maintains a positive and inclusive learning environment.', 10, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (11, 'Teaching Competence', 'Assesses learning outcomes aligned with the stated objectives.', 11, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (12, 'Teaching Competence', 'Provides clear explanations, examples, and demonstrations.', 12, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (13, 'Teaching Competence', 'Connects lesson content to real-life and professional practice.', 13, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (14, 'Personality', 'Demonstrates professionalism and integrity.', 14, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (15, 'Personality', 'Shows enthusiasm and commitment to teaching.', 15, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (16, 'Personality', 'Communicates respectfully and courteously.', 16, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (17, 'Personality', 'Maintains poise, confidence, and self-control.', 17, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (18, 'Personality', 'Exhibits empathy and fairness toward learners.', 18, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (19, 'Personality', 'Collaborates positively with colleagues and stakeholders.', 19, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)'),
    (20, 'Personality', 'Observes proper grooming and professional decorum.', 20, 1, 'PROVISIONAL — pending verification against official ACLC COF (Version 1, Series 2012)')
ON DUPLICATE KEY UPDATE criterion_number = criterion_number;
