-- Migration 1.3: Add 'growth' to assessment_sessions phase enum (schema 2.4)

ALTER TABLE assessment_sessions
    MODIFY COLUMN `phase` ENUM('profile','floor','ceiling','zone','growth','score') NOT NULL DEFAULT 'profile';
