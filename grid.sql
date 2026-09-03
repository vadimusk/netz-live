CREATE TABLE `errors` (
  `action` varchar(32) NOT NULL,
  `error` varchar(128) NOT NULL,
  `count` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`action`,`error`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `past_quarter_hours` (
  `time` datetime NOT NULL,
  `lignite` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hard_coal` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `gas` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `biomass` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `solar` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_onshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_offshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_generation` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_consumption` decimal(5,2) NOT NULL DEFAULT 0.00,
  `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `austria` decimal(5,2) NOT NULL DEFAULT 0.00,
  `belgium` decimal(5,2) NOT NULL DEFAULT 0.00,
  `czech_republic` decimal(5,2) NOT NULL DEFAULT 0.00,
  `denmark` decimal(5,2) NOT NULL DEFAULT 0.00,
  `france` decimal(5,2) NOT NULL DEFAULT 0.00,
  `luxembourg` decimal(5,2) NOT NULL DEFAULT 0.00,
  `netherlands` decimal(5,2) NOT NULL DEFAULT 0.00,
  `norway` decimal(5,2) NOT NULL DEFAULT 0.00,
  `poland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sweden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `switzerland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `emissions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `past_days` (
  `time` datetime NOT NULL,
  `lignite` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hard_coal` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `gas` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `biomass` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `solar` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_onshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_offshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_generation` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_consumption` decimal(5,2) NOT NULL DEFAULT 0.00,
  `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `austria` decimal(5,2) NOT NULL DEFAULT 0.00,
  `belgium` decimal(5,2) NOT NULL DEFAULT 0.00,
  `czech_republic` decimal(5,2) NOT NULL DEFAULT 0.00,
  `denmark` decimal(5,2) NOT NULL DEFAULT 0.00,
  `france` decimal(5,2) NOT NULL DEFAULT 0.00,
  `luxembourg` decimal(5,2) NOT NULL DEFAULT 0.00,
  `netherlands` decimal(5,2) NOT NULL DEFAULT 0.00,
  `norway` decimal(5,2) NOT NULL DEFAULT 0.00,
  `poland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sweden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `switzerland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `emissions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `past_weeks` (
  `time` datetime NOT NULL,
  `lignite` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hard_coal` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `gas` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `biomass` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `solar` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_onshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_offshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_generation` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_consumption` decimal(5,2) NOT NULL DEFAULT 0.00,
  `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `austria` decimal(5,2) NOT NULL DEFAULT 0.00,
  `belgium` decimal(5,2) NOT NULL DEFAULT 0.00,
  `czech_republic` decimal(5,2) NOT NULL DEFAULT 0.00,
  `denmark` decimal(5,2) NOT NULL DEFAULT 0.00,
  `france` decimal(5,2) NOT NULL DEFAULT 0.00,
  `luxembourg` decimal(5,2) NOT NULL DEFAULT 0.00,
  `netherlands` decimal(5,2) NOT NULL DEFAULT 0.00,
  `norway` decimal(5,2) NOT NULL DEFAULT 0.00,
  `poland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sweden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `switzerland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `emissions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `past_years` (
  `time` datetime NOT NULL,
  `lignite` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hard_coal` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `gas` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `biomass` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `solar` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_onshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_offshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `hydro` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `nuclear` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_generation` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `pumped_consumption` decimal(5,2) NOT NULL DEFAULT 0.00,
  `other_renewable` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `other_conventional` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `austria` decimal(5,2) NOT NULL DEFAULT 0.00,
  `belgium` decimal(5,2) NOT NULL DEFAULT 0.00,
  `czech_republic` decimal(5,2) NOT NULL DEFAULT 0.00,
  `denmark` decimal(5,2) NOT NULL DEFAULT 0.00,
  `france` decimal(5,2) NOT NULL DEFAULT 0.00,
  `luxembourg` decimal(5,2) NOT NULL DEFAULT 0.00,
  `netherlands` decimal(5,2) NOT NULL DEFAULT 0.00,
  `norway` decimal(5,2) NOT NULL DEFAULT 0.00,
  `poland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sweden` decimal(5,2) NOT NULL DEFAULT 0.00,
  `switzerland` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `emissions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forecast generation for the weather-driven sources. Kept apart from the
-- measured quarter hours so that a forecast can never overwrite a confirmed
-- figure: the two are different kinds of number and only one of them is a
-- record of what happened.
CREATE TABLE `forecast_quarter_hours` (
  `time` datetime NOT NULL,
  `solar` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_onshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `wind_offshore` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  -- the day-ahead demand forecast, zero where it could not be read
  `load` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wind_records` (
  `value` decimal(5,2) UNSIGNED NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`value`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
