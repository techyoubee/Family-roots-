-- ===========================================================================
-- Family Roots - migration 002 : membership applications, ancestry claims,
--                                 matching results and verification evidence
-- ===========================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------ membership_requests --
CREATE TABLE IF NOT EXISTS `membership_requests` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`         VARCHAR(20)     NOT NULL,
  `family_id`         BIGINT UNSIGNED NOT NULL,
  `applicant_user_id` BIGINT UNSIGNED NOT NULL,
  `applicant_ancestor_id` BIGINT UNSIGNED NULL,
  `family_member_id`  BIGINT UNSIGNED NULL,
  `status`            ENUM('draft','submitted','auto_matched','pending_review','more_info_required','approved','declined','appeal_requested','cancelled') NOT NULL DEFAULT 'draft',
  `claimed_relationship` VARCHAR(150) NULL,
  `applicant_message`    TEXT         NULL,
  `match_score`       DECIMAL(5,2)    NULL,
  `match_summary`     JSON            NULL,
  `auto_decision`     ENUM('none','auto_approved','needs_review') NOT NULL DEFAULT 'none',
  `decision_reason`   VARCHAR(1000)   NULL,
  `decline_reason`    VARCHAR(1000)   NULL,
  `more_info_request` TEXT            NULL,
  `reviewer_user_id`  BIGINT UNSIGNED NULL,
  `decided_by_user_id` BIGINT UNSIGNED NULL,
  `decided_at`        DATETIME        NULL,
  `submitted_at`      DATETIME        NULL,
  `last_reviewed_at`  DATETIME        NULL,
  `appeal_reason`     TEXT            NULL,
  `appealed_at`       DATETIME        NULL,
  -- Exactly one *open* request per applicant per family. MySQL allows many
  -- NULLs in a unique index, so closed requests fall out of the constraint.
  `open_flag`         TINYINT         GENERATED ALWAYS AS (
                        CASE WHEN `status` IN ('draft','submitted','auto_matched','pending_review','more_info_required','appeal_requested')
                             THEN 1 ELSE NULL END
                      ) STORED,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_requests_public_id` (`public_id`),
  UNIQUE KEY `uq_requests_open` (`family_id`, `applicant_user_id`, `open_flag`),
  KEY `idx_requests_family_status` (`family_id`, `status`, `created_at`),
  KEY `idx_requests_applicant` (`applicant_user_id`, `status`),
  KEY `idx_requests_reviewer` (`reviewer_user_id`),
  CONSTRAINT `fk_requests_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_applicant` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_applicant_ancestor` FOREIGN KEY (`applicant_ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_family_member` FOREIGN KEY (`family_member_id`) REFERENCES `family_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_reviewer` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_decided_by` FOREIGN KEY (`decided_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------ membership_request_claims --
-- The ancestry the applicant declares: parents, grandparents and older
-- generations plus any records the engine matched against the family tree.
CREATE TABLE IF NOT EXISTS `membership_request_claims` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`          BIGINT UNSIGNED NOT NULL,
  `relationship`        ENUM('self','father','mother','parent','grandfather','grandmother','great_grandfather','great_grandmother','other') NOT NULL DEFAULT 'other',
  `generation`          SMALLINT        NOT NULL DEFAULT 1,
  `given_names`         VARCHAR(150)    NOT NULL,
  `surname`             VARCHAR(100)    NULL,
  `alt_names`           VARCHAR(500)    NULL,
  `name_normalized`     VARCHAR(255)    NULL,
  `sex`                 ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown',
  `is_living`           TINYINT(1)      NOT NULL DEFAULT 0,
  `birth_date_text`     VARCHAR(60)     NULL,
  `death_date_text`     VARCHAR(60)     NULL,
  `birth_place`         VARCHAR(160)    NULL,
  `notes`               VARCHAR(500)    NULL,
  `matched_ancestor_id` BIGINT UNSIGNED NULL,
  `match_confidence`    DECIMAL(5,2)    NULL,
  `match_explanation`   VARCHAR(1000)   NULL,
  `is_confirmed_match`  TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_claims_request` (`request_id`, `generation`),
  KEY `idx_claims_ancestor` (`matched_ancestor_id`),
  KEY `idx_claims_norm` (`name_normalized`),
  CONSTRAINT `fk_claims_request` FOREIGN KEY (`request_id`) REFERENCES `membership_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claims_ancestor` FOREIGN KEY (`matched_ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------ verification_evidence --
CREATE TABLE IF NOT EXISTS `verification_evidence` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`          CHAR(36)        NOT NULL,
  `family_id`          BIGINT UNSIGNED NULL,
  `membership_request_id` BIGINT UNSIGNED NULL,
  `ancestor_id`        BIGINT UNSIGNED NULL,
  `uploaded_by_user_id` BIGINT UNSIGNED NOT NULL,
  `title`              VARCHAR(150)    NOT NULL,
  `description`        VARCHAR(500)    NULL,
  `original_filename`  VARCHAR(255)    NOT NULL,
  `stored_name`        VARCHAR(160)    NOT NULL,
  `mime_type`          VARCHAR(120)    NOT NULL,
  `byte_size`          INT UNSIGNED    NOT NULL,
  `sha256`             CHAR(64)        NOT NULL,
  `status`             ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `is_private`         TINYINT(1)      NOT NULL DEFAULT 1,
  `reviewed_by_user_id` BIGINT UNSIGNED NULL,
  `reviewed_at`        DATETIME        NULL,
  `review_notes`       VARCHAR(500)    NULL,
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evidence_public_id` (`public_id`),
  UNIQUE KEY `uq_evidence_stored_name` (`stored_name`),
  KEY `idx_evidence_family` (`family_id`, `created_at`),
  KEY `idx_evidence_request` (`membership_request_id`, `status`),
  KEY `idx_evidence_ancestor` (`ancestor_id`),
  CONSTRAINT `chk_evidence_target` CHECK (`family_id` IS NOT NULL OR `membership_request_id` IS NOT NULL OR `ancestor_id` IS NOT NULL),
  CONSTRAINT `fk_evidence_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidence_request` FOREIGN KEY (`membership_request_id`) REFERENCES `membership_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidence_ancestor` FOREIGN KEY (`ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidence_uploader` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evidence_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
