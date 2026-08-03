-- Migration: add banned column to signup table
-- Safe: this file only contains the ALTER statement; the provided PHP runner will check existence before running.

ALTER TABLE `signup` ADD COLUMN `banned` TINYINT(1) NOT NULL DEFAULT 0;