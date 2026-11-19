-- ===========================================================================
-- Family Roots - migration 001 : core identity, families, ancestry graph
-- MySQL 8.0+ / MariaDB 10.4+  (InnoDB, utf8mb4)
--
-- Design notes
--  * A person's login account (`users`) is deliberately separate from their
--    genealogy record (`ancestors`). Many relatives reference one ancestor row;
--    an ancestor may be linked to at most one user account.
--  * Parent/child links are stored one row per edge in `family_relationships`
--    so a person can have more than two documented parents (biological,
--    adoptive, step) without duplicating people.
--  * Nothing about a family is "verified" because it was created: verification
--    is a separate, auditable status.
-- ===========================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------ users --
CREATE TABLE IF NOT EXISTS `users` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`            CHAR(36)        NOT NULL,
  `email`                VARCHAR(190)    NOT NULL,
  `email_verified_at`    DATETIME        NULL,
  `password_hash`        VARCHAR(255)    NOT NULL,
  `full_name`            VARCHAR(150)    NOT NULL,
  `display_name`         VARCHAR(80)     NULL,
  `phone`                VARCHAR(40)     NULL,
  `is_platform_admin`    TINYINT(1)      NOT NULL DEFAULT 0,
  `status`               ENUM('pending','active','suspended','deletion_requested') NOT NULL DEFAULT 'pending',
  `locale`               VARCHAR(10)     NOT NULL DEFAULT 'en',
  `timezone`             VARCHAR(64)     NOT NULL DEFAULT 'UTC',
  `last_login_at`        DATETIME        NULL,
  `failed_login_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `locked_until`         DATETIME        NULL,
  `deletion_requested_at` DATETIME       NULL,
  `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_public_id` (`public_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_platform_admin` (`is_platform_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------- one-time action tokens --
CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `purpose`    ENUM('verify_email','reset_password','change_email','delete_account') NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `payload`    VARCHAR(255)    NULL,
  `expires_at` DATETIME        NOT NULL,
  `used_at`    DATETIME        NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_tokens_hash` (`token_hash`),
  KEY `idx_user_tokens_user` (`user_id`, `purpose`),
  CONSTRAINT `fk_user_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- families --
CREATE TABLE IF NOT EXISTS `families` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`            VARCHAR(20)     NOT NULL,
  `slug`                 VARCHAR(190)    NOT NULL,
  `name`                 VARCHAR(150)    NOT NULL,
  `description`          TEXT            NULL,
  `history`              TEXT            NULL,
  `origins`              VARCHAR(255)    NULL,
  `founding_note`        TEXT            NULL,
  `region`               VARCHAR(120)    NULL,
  `country`              VARCHAR(120)    NULL,
  `founder_user_id`      BIGINT UNSIGNED NOT NULL,
  `verification_status`  ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `verification_note`    VARCHAR(500)    NULL,
  `verified_at`          DATETIME        NULL,
  `verified_by_user_id`  BIGINT UNSIGNED NULL,
  `visibility`           ENUM('private','members','directory') NOT NULL DEFAULT 'members',
  `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_families_slug` (`slug`),
  UNIQUE KEY `uq_families_public_id` (`public_id`),
  KEY `idx_families_name` (`name`),
  KEY `idx_families_verification` (`verification_status`),
  KEY `idx_families_founder` (`founder_user_id`),
  CONSTRAINT `fk_families_founder` FOREIGN KEY (`founder_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_families_verified_by` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ancestors --
CREATE TABLE IF NOT EXISTS `ancestors` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id`       BIGINT UNSIGNED NOT NULL,
  `given_names`     VARCHAR(150)    NOT NULL,
  `surname`         VARCHAR(100)    NULL,
  `alt_names`       VARCHAR(500)    NULL,
  `name_normalized` VARCHAR(255)    NULL,
  `sex`             ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown',
  `is_living`       TINYINT(1)      NOT NULL DEFAULT 0,
  `birth_date`      DATE            NULL,
  `birth_date_text` VARCHAR(60)     NULL,
  `death_date`      DATE            NULL,
  `death_date_text` VARCHAR(60)     NULL,
  `birth_place`     VARCHAR(160)    NULL,
  `death_place`     VARCHAR(160)    NULL,
  `generation`      SMALLINT        NOT NULL DEFAULT 0,
  `branch`          VARCHAR(120)    NULL,
  `notes`           TEXT            NULL,
  `evidence_source` TEXT            NULL,
  `record_status`   ENUM('confirmed','proposed','unknown','disputed') NOT NULL DEFAULT 'proposed',
  `visibility`      ENUM('public','members','admins') NOT NULL DEFAULT 'members',
  `linked_user_id`  BIGINT UNSIGNED NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ancestors_family_generation` (`family_id`, `generation`),
  KEY `idx_ancestors_family_surname` (`family_id`, `surname`),
  KEY `idx_ancestors_family_norm` (`family_id`, `name_normalized`),
  KEY `idx_ancestors_linked_user` (`linked_user_id`),
  CONSTRAINT `fk_ancestors_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ancestors_linked_user` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ancestors_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- family_members --
CREATE TABLE IF NOT EXISTS `family_members` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id`          BIGINT UNSIGNED NOT NULL,
  `user_id`            BIGINT UNSIGNED NOT NULL,
  `ancestor_id`        BIGINT UNSIGNED NULL,
  `role`               ENUM('founder','admin','member','viewer') NOT NULL DEFAULT 'member',
  `status`             ENUM('active','removed') NOT NULL DEFAULT 'active',
  `joined_at`          DATETIME        NULL,
  `approved_by_user_id` BIGINT UNSIGNED NULL,
  `approved_at`        DATETIME        NULL,
  `notes`              VARCHAR(255)    NULL,
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_family_members_user` (`family_id`, `user_id`),
  KEY `idx_family_members_user` (`user_id`, `status`),
  KEY `idx_family_members_ancestor` (`ancestor_id`),
  CONSTRAINT `fk_family_members_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_family_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_family_members_ancestor` FOREIGN KEY (`ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_family_members_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------- family_relationships --
CREATE TABLE IF NOT EXISTS `family_relationships` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id`         BIGINT UNSIGNED NOT NULL,
  `relationship_kind` ENUM('parent_of','spouse_of') NOT NULL DEFAULT 'parent_of',
  `from_ancestor_id`  BIGINT UNSIGNED NOT NULL,
  `to_ancestor_id`    BIGINT UNSIGNED NOT NULL,
  `parent_role`       ENUM('father','mother','parent','unknown') NOT NULL DEFAULT 'unknown',
  `relationship_type` ENUM('biological','adopted','step','foster','unknown') NOT NULL DEFAULT 'unknown',
  `status`            ENUM('confirmed','proposed','disputed') NOT NULL DEFAULT 'proposed',
  `union_year`        SMALLINT        NULL,
  `notes`             VARCHAR(500)    NULL,
  `evidence_source`   TEXT            NULL,
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_relationships_edge` (`family_id`, `relationship_kind`, `from_ancestor_id`, `to_ancestor_id`),
  KEY `idx_relationships_from` (`from_ancestor_id`),
  KEY `idx_relationships_to` (`to_ancestor_id`),
  KEY `idx_relationships_status` (`family_id`, `status`),
  CONSTRAINT `chk_relationships_no_self` CHECK (`from_ancestor_id` <> `to_ancestor_id`),
  CONSTRAINT `fk_relationships_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_relationships_from` FOREIGN KEY (`from_ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_relationships_to` FOREIGN KEY (`to_ancestor_id`) REFERENCES `ancestors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_relationships_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- audit_logs --
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id`     BIGINT UNSIGNED NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `action`        VARCHAR(80)     NOT NULL,
  `entity_type`   VARCHAR(60)     NULL,
  `entity_id`     BIGINT UNSIGNED NULL,
  `summary`       VARCHAR(500)    NULL,
  `before_json`   JSON            NULL,
  `after_json`    JSON            NULL,
  `ip_address`    VARCHAR(45)     NULL,
  `user_agent`    VARCHAR(255)    NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_family` (`family_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_actor` (`actor_user_id`, `created_at`),
  CONSTRAINT `fk_audit_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ notifications --
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `family_id`  BIGINT UNSIGNED NULL,
  `type`       VARCHAR(60)     NOT NULL,
  `title`      VARCHAR(150)    NOT NULL,
  `body`       VARCHAR(1000)   NULL,
  `link_path`  VARCHAR(255)    NULL,
  `read_at`    DATETIME        NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`, `read_at`, `created_at`),
  KEY `idx_notifications_family` (`family_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- rate_limit_hits --
CREATE TABLE IF NOT EXISTS `rate_limit_hits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key` CHAR(64)        NOT NULL,
  `action`     VARCHAR(40)     NOT NULL,
  `created_at` DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_limit_lookup` (`action`, `bucket_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
