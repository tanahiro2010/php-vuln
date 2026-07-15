-- ========================================================================
-- Hifumi Secure Shop -- MySQL init script (training)
--
-- Runs once on first MySQL container boot (docker-entrypoint-initdb.d).
-- Creates the training_user account and two independent databases:
--   * hifumi_vulnerable -- used by app_vulnerable.php
--   * hifumi_secure     -- used by app_secure.php
--
-- Schema and seed data are loaded per-database by init_db.php on each
-- app container's startup, so a second-order SQLi demo dirtying the
-- vulnerable DB does not contaminate the secure one.
-- ========================================================================

CREATE DATABASE IF NOT EXISTS hifumi_vulnerable
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS hifumi_secure
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Training-only account. Do NOT reuse this password anywhere real.
CREATE USER IF NOT EXISTS 'training_user'@'%' IDENTIFIED BY 'training-demo-password';

GRANT ALL PRIVILEGES ON hifumi_vulnerable.* TO 'training_user'@'%';
GRANT ALL PRIVILEGES ON hifumi_secure.*     TO 'training_user'@'%';

FLUSH PRIVILEGES;
