-- Adds the nuclear column, needed before importing history: Germany ran
-- reactors until 15th April 2023, and without them the pre-2023 mix would
-- be understated by around a tenth.

ALTER TABLE `past_quarter_hours` ADD COLUMN `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `hydro`;
ALTER TABLE `past_days` ADD COLUMN `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `hydro`;
ALTER TABLE `past_weeks` ADD COLUMN `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `hydro`;
ALTER TABLE `past_years` ADD COLUMN `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `hydro`;
