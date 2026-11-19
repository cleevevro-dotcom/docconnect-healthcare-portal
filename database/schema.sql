-- ============================================================================
-- DocConnect / DocCenter Healthcare Portal - Database Schema
-- ----------------------------------------------------------------------------
-- This schema was reverse-engineered from the SQL statements found in the PHP
-- application code (the original DBconnect.php and any SQL dump were missing).
-- Database name: docconnect
-- Engine: InnoDB, Charset: utf8mb4
--
-- Source files that were inspected (vendor/ intentionally ignored):
--   Root:    index.php, signup.php, reset_password.php
--   Admins:  dashboard.php, ManagePeople.php, Appointments.php,
--            Process/Delete.php, Process/DoctorAddProcess.php,
--            Process/DoctorEditProcess.php, Process/PatientAddProcess.php,
--            Process/PatientEditProcess.php
--   Doctors: Home.php, Appointment.php, Patients.php, Message.php,
--            Process/PatientProfile.php, Process/fetch_appointments.php,
--            Process/fetch_pending.php, Process/update_appointment_status.php,
--            Process/save_opening_hours.php, Process/fetch_doctor_schedule.php,
--            Process/save_doctor_schedule.php, Process/delete_doctor_schedule.php,
--            Process/edit_doctor_profile.php
--   Patients: Home.php, Appointment.php, Records.php, PatientMessage.php and all
--            files under Process/ (block_user, cancel_appointment, clear_chat,
--            delete_immunization, delete_lab_result, delete_medical_history,
--            doctor_view_ajax, download_lab_result, fetch_available_slots,
--            get_available_schedule, process_edit_appointments,
--            process_edit_immunization, process_edit_lifestyle,
--            process_edit_medical_history, process_edit_profile,
--            process_log_wellness, request_appointment_model,
--            request_appointment_submit, save_doctor_review, send_message,
--            submit_appointment, update_patient_process, uploads_lab_result,
--            view_lab_result)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS docconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE docconnect;

-- ----------------------------------------------------------------------------
-- Table: users
-- Source: index.php, signup.php, reset_password.php, header.php,
--         Admins/Process/DoctorAddProcess.php, DoctorEditProcess.php,
--         PatientAddProcess.php, Doctors/Process/edit_doctor_profile.php,
--         Patients/Process/process_edit_profile.php, update_patient_process.php
-- Notes: role is one of 'admin', 'doctor', 'patient'. gender typically
--        'male'/'female'/'other'. email must be unique (checked on signup and
--        on profile/doctor edits).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id        INT NOT NULL AUTO_INCREMENT,
  first_name     VARCHAR(100) NOT NULL,
  last_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(255) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  gender         VARCHAR(20)  DEFAULT NULL,
  role           VARCHAR(20)  NOT NULL DEFAULT 'patient',
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: password_resets
-- Source: index.php (forgot password, rate-limit count, ON DUPLICATE KEY
--         UPDATE), reset_password.php (token lookup/expiry, delete after use)
-- Notes: email is UNIQUE because index.php uses INSERT ... ON DUPLICATE KEY
--        UPDATE keyed on email (one active token per email).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT NOT NULL AUTO_INCREMENT,
  email       VARCHAR(255) NOT NULL,
  token       VARCHAR(255) NOT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_email (email),
  KEY idx_password_resets_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: patients
-- Source: Patients/Home.php, Patients/Records.php, Patients/Process/*,
--         Admins/Process/PatientAddProcess.php, PatientEditProcess.php,
--         Admins/Process/Delete.php, Doctors/Process/PatientProfile.php
-- Notes: user_id links to users.user_id (one patient row per patient user).
--        height_cm/weight_kg/bmi are numeric and may be NULL until set
--        (Patients/Home.php and update_patient_process.php insert NULLs).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patients (
  patient_id       INT NOT NULL AUTO_INCREMENT,
  user_id          INT NOT NULL,
  date_of_birth    DATE         DEFAULT NULL,
  height_cm        DECIMAL(5,2) DEFAULT NULL,
  weight_kg        DECIMAL(5,2) DEFAULT NULL,
  blood_type       VARCHAR(5)   DEFAULT NULL,
  bmi              DECIMAL(5,2) DEFAULT NULL,
  profile_picture  VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (patient_id),
  UNIQUE KEY uq_patients_user_id (user_id),
  KEY idx_patients_user_id (user_id),
  CONSTRAINT fk_patients_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: doctors
-- Source: Doctors/Home.php, Doctors/Appointment.php, Doctors/Message.php,
--         Patients/Appointment.php, Patients/PatientMessage.php,
--         Patients/Process/doctor_view_ajax.php, save_doctor_review.php,
--         Admins/ManagePeople.php, Admins/Process/DoctorAddProcess.php,
--         DoctorEditProcess.php, Delete.php,
--         Doctors/Process/edit_doctor_profile.php, PatientProfile.php
-- Notes: user_id links to users.user_id (one doctor row per doctor user).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors (
  doctor_id        INT NOT NULL AUTO_INCREMENT,
  user_id          INT NOT NULL,
  specialty        VARCHAR(255) DEFAULT NULL,
  years_experience INT          DEFAULT NULL,
  clinic_address   VARCHAR(255) DEFAULT NULL,
  profile_picture  VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (doctor_id),
  UNIQUE KEY uq_doctors_user_id (user_id),
  KEY idx_doctors_user_id (user_id),
  CONSTRAINT fk_doctors_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: doctors_clinic
-- Source: Doctors/Home.php, Doctors/Process/edit_doctor_profile.php,
--         Patients/Process/doctor_view_ajax.php
-- Notes: Extra clinic details per doctor (one row per doctor). 'review' column
--        is referenced in the INSERT in edit_doctor_profile.php.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors_clinic (
  clinic_id     INT NOT NULL AUTO_INCREMENT,
  doctor_id     INT NOT NULL,
  clinic_name   VARCHAR(255) DEFAULT NULL,
  short_bio     VARCHAR(500) DEFAULT NULL,
  description   TEXT         DEFAULT NULL,
  clinic_image  VARCHAR(255) DEFAULT NULL,
  review        TEXT         DEFAULT NULL,
  PRIMARY KEY (clinic_id),
  KEY idx_doctors_clinic_doctor_id (doctor_id),
  CONSTRAINT fk_doctors_clinic_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: doctor_schedule
-- Source: Doctors/Process/save_opening_hours.php, fetch_doctor_schedule.php,
--         save_doctor_schedule.php, delete_doctor_schedule.php,
--         Patients/Process/submit_appointment.php, fetch_available_slots.php
-- Notes: Primary key column is named `id` (as used by the save/fetch/delete
--        handlers, NOT schedule_id). Weekly opening hours use day_of_week with
--        schedule_date = NULL; specific-date overrides set schedule_date.
--        day_of_week follows PHP date('w'): 0 (Sun) - 6 (Sat).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctor_schedule (
  id             INT NOT NULL AUTO_INCREMENT,
  doctor_id      INT NOT NULL,
  day_of_week    TINYINT      DEFAULT NULL,
  start_time     TIME         DEFAULT NULL,
  end_time       TIME         DEFAULT NULL,
  is_off         TINYINT(1)   NOT NULL DEFAULT 0,
  schedule_date  DATE         DEFAULT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doctor_schedule_doctor_id (doctor_id),
  CONSTRAINT fk_doctor_schedule_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: appointments
-- Source: Admins/dashboard.php, Admins/Appointments.php,
--         Admins/Process/Delete.php, Doctors/Home.php, Doctors/Patients.php,
--         Doctors/Process/fetch_appointments.php, fetch_pending.php,
--         update_appointment_status.php, Patients/Home.php, Patients/Records.php,
--         Patients/Appointment.php, Patients/Process/cancel_appointment.php,
--         process_edit_appointments.php, request_appointment_submit.php,
--         submit_appointment.php, fetch_available_slots.php,
--         get_available_schedule.php
-- Notes: status values observed: 'pending', 'confirmed', 'completed',
--        'cancelled', 'rejected' (and 'scheduled'/'approved' in a couple of
--        patient queries - see ambiguity notes). Default 'pending'.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
  appointment_id        INT NOT NULL AUTO_INCREMENT,
  patient_id            INT NOT NULL,
  doctor_id             INT NOT NULL,
  appointment_datetime  DATETIME     NOT NULL,
  reason                TEXT         DEFAULT NULL,
  status                VARCHAR(20)  NOT NULL DEFAULT 'pending',
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (appointment_id),
  KEY idx_appointments_patient_id (patient_id),
  KEY idx_appointments_doctor_id (doctor_id),
  KEY idx_appointments_datetime (appointment_datetime),
  CONSTRAINT fk_appointments_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id),
  CONSTRAINT fk_appointments_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: doctor_notes
-- Source: Doctors/Process/PatientProfile.php (insert/update/select),
--         Patients/Records.php, Patients/Home.php
-- Notes: appointment_id may be NULL (LEFT JOIN used in PatientProfile.php).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctor_notes (
  note_id        INT NOT NULL AUTO_INCREMENT,
  patient_id     INT NOT NULL,
  doctor_id      INT NOT NULL,
  appointment_id INT          DEFAULT NULL,
  note_text      TEXT         NOT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (note_id),
  KEY idx_doctor_notes_patient_id (patient_id),
  KEY idx_doctor_notes_doctor_id (doctor_id),
  KEY idx_doctor_notes_appointment_id (appointment_id),
  CONSTRAINT fk_doctor_notes_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id),
  CONSTRAINT fk_doctor_notes_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id),
  CONSTRAINT fk_doctor_notes_appointment
    FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: doctor_reviews
-- Source: Patients/Process/save_doctor_review.php (INSERT ... rating, comment,
--         created_at), Patients/Process/doctor_view_ajax.php (SELECT rating,
--         comment, created_at), Admins/Process/Delete.php (DELETE by
--         patient_id / doctor_id)
-- Notes: rating stored as a decimal (values 1.0 - 5.0 in 0.5 steps).
--        Primary key name review_id is NOT referenced in code (guessed).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctor_reviews (
  review_id   INT NOT NULL AUTO_INCREMENT,
  doctor_id   INT NOT NULL,
  patient_id  INT NOT NULL,
  rating      DECIMAL(2,1) NOT NULL,
  comment     TEXT         NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  KEY idx_doctor_reviews_doctor_id (doctor_id),
  KEY idx_doctor_reviews_patient_id (patient_id),
  CONSTRAINT fk_doctor_reviews_doctor
    FOREIGN KEY (doctor_id) REFERENCES doctors (doctor_id),
  CONSTRAINT fk_doctor_reviews_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: medical_history
-- Source: Patients/Records.php, Patients/Process/process_edit_medical_history.php,
--         delete_medical_history.php, Doctors/Process/PatientProfile.php,
--         Admins/Process/Delete.php
-- Notes: diagnosis_date / recovery_date may be NULL.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medical_history (
  history_id      INT NOT NULL AUTO_INCREMENT,
  patient_id      INT NOT NULL,
  condition_name  VARCHAR(255) NOT NULL,
  diagnosis_date  DATE         DEFAULT NULL,
  recovery_date   DATE         DEFAULT NULL,
  notes           TEXT         DEFAULT NULL,
  PRIMARY KEY (history_id),
  KEY idx_medical_history_patient_id (patient_id),
  CONSTRAINT fk_medical_history_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: immunizations
-- Source: Patients/Records.php, Patients/Process/process_edit_immunization.php,
--         delete_immunization.php, Doctors/Process/PatientProfile.php,
--         Admins/Process/Delete.php
-- Notes: date_administered may be NULL.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS immunizations (
  immunization_id   INT NOT NULL AUTO_INCREMENT,
  patient_id        INT NOT NULL,
  vaccine_name      VARCHAR(255) NOT NULL,
  date_administered DATE         DEFAULT NULL,
  status            VARCHAR(50)  DEFAULT NULL,
  notes             TEXT         DEFAULT NULL,
  PRIMARY KEY (immunization_id),
  KEY idx_immunizations_patient_id (patient_id),
  CONSTRAINT fk_immunizations_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: lab_results
-- Source: Patients/Process/uploads_lab_result.php (INSERT),
--         download_lab_result.php (SELECT file_path, file_type),
--         delete_lab_result.php, view_lab_result.php, Patients/Records.php,
--         Doctors/Process/PatientProfile.php, Admins/Process/Delete.php
-- Notes: uploaded_at defaults to the insert time (Records.php orders by it).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lab_results (
  lab_result_id   INT NOT NULL AUTO_INCREMENT,
  patient_id      INT NOT NULL,
  test_name       VARCHAR(255) NOT NULL,
  result_summary  TEXT         DEFAULT NULL,
  result_date     DATE         DEFAULT NULL,
  file_path       VARCHAR(255) DEFAULT NULL,
  file_type       VARCHAR(100) DEFAULT NULL,
  uploaded_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lab_result_id),
  KEY idx_lab_results_patient_id (patient_id),
  CONSTRAINT fk_lab_results_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: lifestyle_recommendations
-- Source: Patients/Records.php, Patients/Home.php,
--         Patients/Process/process_edit_lifestyle.php,
--         Doctors/Process/PatientProfile.php, Admins/Process/Delete.php
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lifestyle_recommendations (
  recommendation_id INT NOT NULL AUTO_INCREMENT,
  patient_id        INT NOT NULL,
  recommendation    TEXT         NOT NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (recommendation_id),
  KEY idx_lifestyle_recommendations_patient_id (patient_id),
  CONSTRAINT fk_lifestyle_recommendations_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: patient_daily_wellness
-- Source: Patients/Process/process_log_wellness.php (SELECT id, INSERT, UPDATE),
--         Patients/Home.php, Doctors/Process/PatientProfile.php
-- Notes: Primary key column is `id` (used by process_log_wellness.php). One row
--        per patient per day (patient_id + log_date). mood values include
--        'Feeling Good', 'Feeling Bad', 'Pain', 'Other'; exercise_type values
--        include 'Gym', 'Walk', 'Other', 'N/A'.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patient_daily_wellness (
  id                INT NOT NULL AUTO_INCREMENT,
  patient_id        INT NOT NULL,
  log_date          DATE         NOT NULL,
  water_liters      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  mood              VARCHAR(50)  DEFAULT NULL,
  sleep_hours       DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  exercise_type     VARCHAR(50)  DEFAULT NULL,
  exercise_minutes  INT          NOT NULL DEFAULT 0,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_daily_wellness_patient_id (patient_id),
  KEY idx_patient_daily_wellness_log_date (log_date),
  CONSTRAINT fk_patient_daily_wellness_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: inherited_conditions
-- Source: Doctors/Process/PatientProfile.php (SELECT inherited_id,
--         condition_name, gene_name, diagnosis_date, notes)
-- Notes: Not in the original expected list, but explicitly referenced in code.
--        Insert/update columns are not present in the code, so the writable
--        surface is unknown (columns below are all read in the SELECT).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inherited_conditions (
  inherited_id    INT NOT NULL AUTO_INCREMENT,
  patient_id      INT NOT NULL,
  condition_name  VARCHAR(255) DEFAULT NULL,
  gene_name       VARCHAR(255) DEFAULT NULL,
  diagnosis_date  DATE         DEFAULT NULL,
  notes           TEXT         DEFAULT NULL,
  PRIMARY KEY (inherited_id),
  KEY idx_inherited_conditions_patient_id (patient_id),
  CONSTRAINT fk_inherited_conditions_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: messages
-- Source: Doctors/Message.php, Patients/PatientMessage.php,
--         Patients/Process/send_message.php, clear_chat.php,
--         Admins/Process/Delete.php (DELETE by sender_id/receiver_id)
-- Notes: sender_id and receiver_id reference users.user_id (messaging is
--        between a patient user and a doctor user). message_type is 'text' or
--        'image' (send_message.php). file_path holds an uploaded attachment.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  message_id    INT NOT NULL AUTO_INCREMENT,
  sender_id     INT           NOT NULL,
  receiver_id   INT           NOT NULL,
  message_text  TEXT          DEFAULT NULL,
  message_type  VARCHAR(20)   NOT NULL DEFAULT 'text',
  file_path     VARCHAR(255)  DEFAULT NULL,
  sent_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read       TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (message_id),
  KEY idx_messages_sender_id (sender_id),
  KEY idx_messages_receiver_id (receiver_id),
  CONSTRAINT fk_messages_sender
    FOREIGN KEY (sender_id) REFERENCES users (user_id),
  CONSTRAINT fk_messages_receiver
    FOREIGN KEY (receiver_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Table: blocked_users
-- Source: Patients/Process/block_user.php (SELECT block_id, INSERT blocker_id,
--         blocked_id, blocked_at)
-- Notes: blocker_id / blocked_id reference users.user_id.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocked_users (
  block_id    INT NOT NULL AUTO_INCREMENT,
  blocker_id  INT NOT NULL,
  blocked_id  INT NOT NULL,
  blocked_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (block_id),
  KEY idx_blocked_users_blocker_id (blocker_id),
  KEY idx_blocked_users_blocked_id (blocked_id),
  CONSTRAINT fk_blocked_users_blocker
    FOREIGN KEY (blocker_id) REFERENCES users (user_id),
  CONSTRAINT fk_blocked_users_blocked
    FOREIGN KEY (blocked_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
