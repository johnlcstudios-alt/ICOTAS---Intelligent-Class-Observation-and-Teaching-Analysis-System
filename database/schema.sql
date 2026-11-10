-- ============================================================
-- ICOTAS - Intelligent Class Observation & Teaching Analytics System
-- ACLC College Tacloban City
-- MySQL / MariaDB schema for InfinityFree shared hosting
-- Import through phpMyAdmin. UTF8MB4. InnoDB. No server-side extras.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  head_user_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_code (code),
  KEY idx_departments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(180) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_programs_code (code),
  KEY idx_programs_department (department_id),
  CONSTRAINT fk_programs_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_years (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(30) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_academic_years_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semesters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  sequence TINYINT UNSIGNED NOT NULL DEFAULT 1,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_semesters_period (academic_year_id, name),
  CONSTRAINT fk_semesters_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NULL,
  code VARCHAR(30) NOT NULL,
  title VARCHAR(180) NOT NULL,
  units DECIMAL(4,1) NOT NULL DEFAULT 3.0,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_code (code),
  KEY idx_courses_department (department_id),
  KEY idx_courses_program (program_id),
  CONSTRAINT fk_courses_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_courses_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NULL,
  year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  capacity INT UNSIGNED NOT NULL DEFAULT 50,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sections_code (code),
  KEY idx_sections_program (program_id),
  CONSTRAINT fk_sections_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  suffix VARCHAR(20) NULL,
  employee_number VARCHAR(40) NULL,
  student_number VARCHAR(40) NULL,
  department_id BIGINT UNSIGNED NULL,
  program_id BIGINT UNSIGNED NULL,
  section_id BIGINT UNSIGNED NULL,
  year_level TINYINT UNSIGNED NULL,
  phone VARCHAR(30) NULL,
  avatar VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_employee (employee_number),
  UNIQUE KEY uq_users_student (student_number),
  KEY idx_users_role (role_id),
  KEY idx_users_department (department_id),
  KEY idx_users_program (program_id),
  KEY idx_users_section (section_id),
  KEY idx_users_status (status),
  KEY idx_users_demo (is_demo),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_users_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_users_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE departments
  ADD CONSTRAINT fk_departments_head FOREIGN KEY (head_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_sessions_hash (session_hash),
  KEY idx_user_sessions_user (user_id),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_reset_hash (token_hash),
  KEY idx_password_reset_user (user_id),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(190) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_lookup (username, ip_hash, attempted_at),
  KEY idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_time (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id BIGINT UNSIGNED NOT NULL,
  section_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  semester_id BIGINT UNSIGNED NOT NULL,
  day_of_week VARCHAR(15) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_schedules_course (course_id),
  KEY idx_schedules_section (section_id),
  KEY idx_schedules_instructor (instructor_id),
  KEY idx_schedules_period (academic_year_id, semester_id),
  CONSTRAINT fk_schedules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_schedules_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_schedules_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_schedules_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_schedules_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instructor_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instructor_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  section_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  semester_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_instructor_assignment (instructor_id, course_id, section_id, academic_year_id, semester_id),
  KEY idx_ia_instructor (instructor_id),
  KEY idx_ia_period (academic_year_id, semester_id),
  CONSTRAINT fk_ia_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ia_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ia_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ia_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ia_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ia_schedule FOREIGN KEY (schedule_id) REFERENCES class_schedules (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_enrollments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  section_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  semester_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'enrolled',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_enrollment (student_id, course_id, section_id, academic_year_id, semester_id),
  KEY idx_enrollments_student (student_id),
  KEY idx_enrollments_section (section_id),
  KEY idx_enrollments_period (academic_year_id, semester_id),
  CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS surveys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Teaching Effectiveness',
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_template TINYINT(1) NOT NULL DEFAULT 0,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_surveys_status (status),
  KEY idx_surveys_template (is_template),
  CONSTRAINT fk_surveys_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  instructions TEXT NULL,
  rating_labels TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_survey_version (survey_id, version_number),
  KEY idx_survey_versions_status (status),
  CONSTRAINT fk_survey_versions_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_survey_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_version_id BIGINT UNSIGNED NOT NULL,
  question_text VARCHAR(500) NOT NULL,
  description VARCHAR(500) NULL,
  question_type VARCHAR(30) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  order_index INT UNSIGNED NOT NULL DEFAULT 1,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  min_value DECIMAL(4,1) NULL,
  max_value DECIMAL(4,1) NULL,
  min_label VARCHAR(80) NULL,
  max_label VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_questions_version (survey_version_id, order_index),
  CONSTRAINT fk_questions_version FOREIGN KEY (survey_version_id) REFERENCES survey_versions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id BIGINT UNSIGNED NOT NULL,
  option_label VARCHAR(180) NOT NULL,
  option_value VARCHAR(180) NOT NULL,
  order_index INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_options_question (question_id, order_index),
  CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES survey_questions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Teaching Effectiveness',
  survey_id BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_templates_survey (survey_id),
  CONSTRAINT fk_templates_survey FOREIGN KEY (survey_id) REFERENCES surveys (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  survey_version_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  section_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  semester_id BIGINT UNSIGNED NOT NULL,
  evaluation_type VARCHAR(40) NOT NULL DEFAULT 'student_feedback',
  instructions TEXT NULL,
  opens_at DATETIME NOT NULL,
  closes_at DATETIME NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assignments_instructor (instructor_id),
  KEY idx_assignments_section (section_id),
  KEY idx_assignments_period (academic_year_id, semester_id),
  KEY idx_assignments_status (status),
  CONSTRAINT fk_assignments_version FOREIGN KEY (survey_version_id) REFERENCES survey_versions (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  session_code VARCHAR(12) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_cipher TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  opens_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sessions_code (session_code),
  UNIQUE KEY uq_sessions_token (token_hash),
  KEY idx_sessions_assignment (assignment_id),
  KEY idx_sessions_status (status, expires_at),
  CONSTRAINT fk_sessions_assignment FOREIGN KEY (assignment_id) REFERENCES evaluation_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sessions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_eligibility_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eligibility_student (evaluation_session_id, student_id),
  UNIQUE KEY uq_eligibility_token (token_hash),
  KEY idx_eligibility_student (student_id),
  CONSTRAINT fk_eligibility_session FOREIGN KEY (evaluation_session_id) REFERENCES evaluation_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eligibility_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_participation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participation_student (evaluation_session_id, student_id),
  KEY idx_participation_status (evaluation_session_id, status),
  KEY idx_participation_student (student_id),
  CONSTRAINT fk_participation_session FOREIGN KEY (evaluation_session_id) REFERENCES evaluation_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_participation_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anonymous responses: intentionally no student_id or participation_id column.
CREATE TABLE IF NOT EXISTS evaluation_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_session_id BIGINT UNSIGNED NOT NULL,
  survey_version_id BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NOT NULL,
  submitted_on DATE NOT NULL,
  response_status VARCHAR(20) NOT NULL DEFAULT 'submitted',
  sentiment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  source VARCHAR(20) NOT NULL DEFAULT 'live',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_responses_session (evaluation_session_id),
  KEY idx_responses_version (survey_version_id),
  KEY idx_responses_date (submitted_on),
  CONSTRAINT fk_responses_session FOREIGN KEY (evaluation_session_id) REFERENCES evaluation_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_responses_version FOREIGN KEY (survey_version_id) REFERENCES survey_versions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS response_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  answer_value LONGTEXT NULL,
  numeric_value DECIMAL(6,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_answers_response (response_id),
  KEY idx_answers_question (question_id),
  CONSTRAINT fk_answers_response FOREIGN KEY (response_id) REFERENCES evaluation_responses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES survey_questions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sentiment_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id BIGINT UNSIGNED NOT NULL,
  sentiment_label VARCHAR(20) NOT NULL,
  sentiment_score DECIMAL(6,3) NOT NULL DEFAULT 0,
  positive_score DECIMAL(6,3) NOT NULL DEFAULT 0,
  negative_score DECIMAL(6,3) NOT NULL DEFAULT 0,
  confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
  engine_version VARCHAR(30) NOT NULL DEFAULT 'rule-v1.0',
  excerpt TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sentiment_response (response_id),
  KEY idx_sentiment_label (sentiment_label),
  CONSTRAINT fk_sentiment_response FOREIGN KEY (response_id) REFERENCES evaluation_responses (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_themes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id BIGINT UNSIGNED NOT NULL,
  theme VARCHAR(80) NOT NULL,
  polarity VARCHAR(20) NOT NULL DEFAULT 'neutral',
  weight DECIMAL(6,3) NOT NULL DEFAULT 1,
  matched_keywords VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_themes_response (response_id),
  KEY idx_themes_theme (theme, polarity),
  CONSTRAINT fk_themes_response FOREIGN KEY (response_id) REFERENCES evaluation_responses (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope_type VARCHAR(40) NOT NULL,
  scope_id BIGINT UNSIGNED NULL,
  period_key VARCHAR(60) NOT NULL,
  metric_key VARCHAR(80) NOT NULL,
  metric_value DECIMAL(10,3) NOT NULL,
  meta LONGTEXT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_snapshot_metric (scope_type, scope_id, period_key, metric_key),
  KEY idx_snapshots_scope (scope_type, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instructor_recommendations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instructor_id BIGINT UNSIGNED NOT NULL,
  survey_version_id BIGINT UNSIGNED NULL,
  evaluation_session_id BIGINT UNSIGNED NULL,
  theme VARCHAR(80) NOT NULL,
  concern TEXT NOT NULL,
  supporting_metric VARCHAR(255) NULL,
  recommendation TEXT NOT NULL,
  reason TEXT NOT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'medium',
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  source VARCHAR(30) NOT NULL DEFAULT 'rule_based',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_recommendations_instructor (instructor_id, status),
  KEY idx_recommendations_theme (theme),
  CONSTRAINT fk_recommendations_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_version FOREIGN KEY (survey_version_id) REFERENCES survey_versions (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_session FOREIGN KEY (evaluation_session_id) REFERENCES evaluation_sessions (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prediction_configurations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(60) NOT NULL,
  setting_value VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prediction_config (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prediction_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instructor_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NULL,
  semester_id BIGINT UNSIGNED NULL,
  scope VARCHAR(40) NOT NULL DEFAULT 'instructor_overall',
  method VARCHAR(60) NOT NULL,
  risk_level VARCHAR(30) NOT NULL,
  current_average DECIMAL(5,2) NULL,
  forecast_average DECIMAL(5,2) NULL,
  slope DECIMAL(7,4) NULL,
  record_count INT UNSIGNED NOT NULL DEFAULT 0,
  response_count INT UNSIGNED NOT NULL DEFAULT 0,
  explanation TEXT NOT NULL,
  supporting_metrics LONGTEXT NULL,
  threshold_config LONGTEXT NULL,
  data_status VARCHAR(30) NOT NULL DEFAULT 'sufficient',
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_predictions_instructor (instructor_id, generated_at),
  KEY idx_predictions_risk (risk_level),
  CONSTRAINT fk_predictions_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_predictions_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_predictions_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_observations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NULL,
  observer_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  section_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  semester_id BIGINT UNSIGNED NOT NULL,
  observation_date DATE NOT NULL,
  observation_type VARCHAR(50) NOT NULL DEFAULT 'classroom_observation',
  strengths TEXT NULL,
  areas_for_improvement TEXT NULL,
  recommendations TEXT NULL,
  overall_rating DECIMAL(4,2) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_observations_instructor (instructor_id),
  KEY idx_observations_date (observation_date),
  CONSTRAINT fk_observations_assignment FOREIGN KEY (assignment_id) REFERENCES evaluation_assignments (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_observations_observer FOREIGN KEY (observer_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_observations_instructor FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_observations_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_observations_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_observations_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_observations_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'system',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  requested_by BIGINT UNSIGNED NULL,
  report_type VARCHAR(60) NOT NULL,
  title VARCHAR(200) NOT NULL,
  filters LONGTEXT NULL,
  format VARCHAR(20) NOT NULL DEFAULT 'html',
  file_path VARCHAR(255) NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_user (requested_by),
  KEY idx_reports_type (report_type),
  CONSTRAINT fk_reports_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_surveys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  opens_at DATETIME NULL,
  closes_at DATETIME NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_uat_surveys_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uat_survey_id BIGINT UNSIGNED NOT NULL,
  characteristic VARCHAR(60) NOT NULL,
  question_text VARCHAR(500) NOT NULL,
  order_index INT UNSIGNED NOT NULL DEFAULT 1,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_uat_questions_survey (uat_survey_id, order_index),
  CONSTRAINT fk_uat_questions_survey FOREIGN KEY (uat_survey_id) REFERENCES uat_surveys (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_respondents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uat_survey_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  respondent_type VARCHAR(80) NOT NULL DEFAULT 'IT Professional',
  access_token_hash CHAR(64) NOT NULL,
  access_token_cipher TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'invited',
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uat_respondent_token (access_token_hash),
  KEY idx_uat_respondents_survey (uat_survey_id),
  CONSTRAINT fk_uat_respondents_survey FOREIGN KEY (uat_survey_id) REFERENCES uat_surveys (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_uat_respondents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uat_survey_id BIGINT UNSIGNED NOT NULL,
  uat_respondent_id BIGINT UNSIGNED NULL,
  respondent_name VARCHAR(150) NULL,
  respondent_type VARCHAR(80) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source VARCHAR(20) NOT NULL DEFAULT 'live',
  PRIMARY KEY (id),
  KEY idx_uat_responses_survey (uat_survey_id),
  CONSTRAINT fk_uat_responses_survey FOREIGN KEY (uat_survey_id) REFERENCES uat_surveys (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_uat_responses_respondent FOREIGN KEY (uat_respondent_id) REFERENCES uat_respondents (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uat_response_id BIGINT UNSIGNED NOT NULL,
  uat_question_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_uat_answers_response (uat_response_id),
  KEY idx_uat_answers_question (uat_question_id),
  CONSTRAINT fk_uat_answers_response FOREIGN KEY (uat_response_id) REFERENCES uat_responses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_uat_answers_question FOREIGN KEY (uat_question_id) REFERENCES uat_questions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uat_reference_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  characteristic VARCHAR(60) NOT NULL,
  mean_score DECIMAL(4,2) NOT NULL,
  sd_score DECIMAL(4,2) NULL,
  interpretation VARCHAR(40) NOT NULL,
  source_label VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket VARCHAR(80) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_limit (bucket, identifier_hash),
  KEY idx_rate_limit_time (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Base reference data -------------------------------------------------------
INSERT INTO roles (slug, name, description) VALUES
  ('dean', 'Dean', 'College-wide administration, governance and evaluation management'),
  ('academic_head', 'Academic Head', 'Department-level administration and evaluation management'),
  ('instructor', 'Instructor', 'Private personal teaching performance and evaluation access'),
  ('student', 'Student', 'Eligible student evaluation respondent')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO roles (slug, name, description) VALUES
  ('academic_head', 'Academic Head', 'Department-level administration and evaluation management');

INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES
  ('institution_name', 'ACLC College Tacloban City', 'general'),
  ('system_name', 'ICOTAS', 'general'),
  ('system_tagline', 'Intelligent Class Observation & Teaching Analytics System', 'general'),
  ('logo_path', '', 'general'),
  ('current_academic_year_id', '', 'academic'),
  ('current_semester_id', '', 'academic'),
  ('demo_mode', '0', 'demo'),
  ('privacy_min_responses', '3', 'privacy'),
  ('privacy_show_comments', '1', 'privacy'),
  ('prediction_min_responses', '5', 'prediction'),
  ('prediction_min_periods', '2', 'prediction'),
  ('prediction_window', '8', 'prediction'),
  ('prediction_improving_slope', '0.08', 'prediction'),
  ('prediction_declining_slope', '-0.08', 'prediction'),
  ('prediction_negative_ratio', '0.30', 'prediction'),
  ('uat_very_satisfactory_min', '4.50', 'uat'),
  ('uat_satisfactory_min', '3.50', 'uat'),
  ('uat_fair_min', '2.50', 'uat'),
  ('uat_poor_min', '1.50', 'uat'),
  ('allow_student_self_scan', '1', 'evaluation'),
  ('default_session_minutes', '120', 'evaluation')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO prediction_configurations (setting_key, setting_value, description) VALUES
  ('min_responses', '5', 'Minimum submitted responses before a prediction is displayed'),
  ('min_periods', '2', 'Minimum comparable academic periods with recorded averages'),
  ('window_periods', '8', 'Maximum recent academic periods used in trend calculation'),
  ('improving_slope', '0.08', 'Linear slope at or above this value is treated as improving'),
  ('declining_slope', '-0.08', 'Linear slope at or below this value is treated as needing attention'),
  ('negative_ratio', '0.30', 'Negative sentiment ratio that triggers a warning'),
  ('forecast_change', '0.10', 'Forecast change threshold for improving / declining classification')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description);
