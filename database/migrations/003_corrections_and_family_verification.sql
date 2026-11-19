-- ===========================================================================
-- Family Roots - migration 003 : correction requests and family verification
--                                 submissions
-- ===========================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------- record_corrections --
CREATE TABLE IF NOT EXISTS `record_corrections` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`             VARCHAR(20)     NOT NULL,
  `family_id`             BIGINT UNSIGNED NOT NULL,
  `ancestor_id`           BIGINT UNSIGNED NULL,
  `relationship_id`       BIGINT UNSIGNED NULL,
  `requested_by_user_id`  BIGINT UNSIGNED NOT NULL,
  `field_name`            VARCHAR(60)     NOT NULL,
  `current_value`         VARCHAR(500)    NULL,
  `proposed_value`        VARCHAR(500)    NOT NULL,
  `reason`                VARCHAR(500)    NULL,
  `status`                ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id`   BIGINT UNSIGNED NULL,
  `reviewed_at`           DATETIME        NULL,
  `review_notes`          VARCHAR(500)    NULL,
  `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corrections_public_id` (`public_id`),
  KEY `idx_corrections_family_status` (`family_id`, `status`),
  KEY `idx_corrections_ancestor` (`ancestor_id`),
  KEY `idx_corrections_requester` (`requested_by_user_id`),
  CONSTRAINT `chk_corrections_target` CHECK (`ancestor_id` IS NOT NULL OR `relationship_id` IS NOT NULL),
  CONSTRAINT `fk_corrections_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corrections_ancestor` FOREIGN KEY (`ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corrections_relationship` FOREIGN KEY (`relationship_id`) REFERENCES `family_relationships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corrections_requester` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corrections_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------- family_verifications --
-- A founder/administrator submits historical context for the family profile.
-- A platform administrator reviews it; the family profile is only marked
-- `verified` through this explicit, audited decision.
CREATE TABLE IF NOT EXISTS `family_verifications` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id`           BIGINT UNSIGNED NOT NULL,
  `submitted_by_user_id` BIGINT UNSIGNED NOT NULL,
  `summary`             TEXT            NOT NULL,
  `sources`             TEXT            NULL,
  `status`              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` BIGINT UNSIGNED NULL,
  `reviewed_at`         DATETIME        NULL,
  `review_notes`        VARCHAR(500)    NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_family_verifications_family` (`family_id`, `status`),
  CONSTRAINT `fk_family_verifications_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_family_verifications_user` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_family_verifications_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
