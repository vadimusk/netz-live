-- Migrates an existing database to the SMARD source categories.
-- SMARD reports a single hydro figure rather than splitting run-of-river from
-- reservoirs, and groups the smaller sources into renewable and conventional
-- remainders, so the historic columns are merged to match.

ALTER TABLE `past_quarter_hours`
  ADD COLUMN `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `wind_offshore`,
  ADD COLUMN `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `pumped_consumption`,
  ADD COLUMN `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `other_renewable`;
UPDATE `past_quarter_hours` SET
  `hydro`              = `hydro_run_of_river` + `hydro_reservoir`,
  `other_renewable`    = `waste` + `geothermal`,
  `other_conventional` = `coal_gas` + `oil` + `other`;
ALTER TABLE `past_quarter_hours`
  DROP COLUMN `coal_gas`, DROP COLUMN `oil`, DROP COLUMN `waste`,
  DROP COLUMN `geothermal`, DROP COLUMN `other`,
  DROP COLUMN `hydro_run_of_river`, DROP COLUMN `hydro_reservoir`;

ALTER TABLE `past_days`
  ADD COLUMN `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `wind_offshore`,
  ADD COLUMN `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `pumped_consumption`,
  ADD COLUMN `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `other_renewable`;
UPDATE `past_days` SET
  `hydro`              = `hydro_run_of_river` + `hydro_reservoir`,
  `other_renewable`    = `waste` + `geothermal`,
  `other_conventional` = `coal_gas` + `oil` + `other`;
ALTER TABLE `past_days`
  DROP COLUMN `coal_gas`, DROP COLUMN `oil`, DROP COLUMN `waste`,
  DROP COLUMN `geothermal`, DROP COLUMN `other`,
  DROP COLUMN `hydro_run_of_river`, DROP COLUMN `hydro_reservoir`;

ALTER TABLE `past_weeks`
  ADD COLUMN `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `wind_offshore`,
  ADD COLUMN `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `pumped_consumption`,
  ADD COLUMN `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `other_renewable`;
UPDATE `past_weeks` SET
  `hydro`              = `hydro_run_of_river` + `hydro_reservoir`,
  `other_renewable`    = `waste` + `geothermal`,
  `other_conventional` = `coal_gas` + `oil` + `other`;
ALTER TABLE `past_weeks`
  DROP COLUMN `coal_gas`, DROP COLUMN `oil`, DROP COLUMN `waste`,
  DROP COLUMN `geothermal`, DROP COLUMN `other`,
  DROP COLUMN `hydro_run_of_river`, DROP COLUMN `hydro_reservoir`;

ALTER TABLE `past_years`
  ADD COLUMN `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `wind_offshore`,
  ADD COLUMN `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `pumped_consumption`,
  ADD COLUMN `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00 AFTER `other_renewable`;
UPDATE `past_years` SET
  `hydro`              = `hydro_run_of_river` + `hydro_reservoir`,
  `other_renewable`    = `waste` + `geothermal`,
  `other_conventional` = `coal_gas` + `oil` + `other`;
ALTER TABLE `past_years`
  DROP COLUMN `coal_gas`, DROP COLUMN `oil`, DROP COLUMN `waste`,
  DROP COLUMN `geothermal`, DROP COLUMN `other`,
  DROP COLUMN `hydro_run_of_river`, DROP COLUMN `hydro_reservoir`;
