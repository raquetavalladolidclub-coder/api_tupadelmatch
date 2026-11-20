-- ============================================================
-- LIGA HEAD · migración inicial
-- Fecha: 2026-11-21
-- Solo crea tablas nuevas; no toca nada existente.
-- Reversible: DROP TABLE liga_head_sets, liga_head_partidos,
--            liga_head_parejas, liga_head_jornadas, ligas_head;
-- ============================================================

CREATE TABLE IF NOT EXISTS `ligas_head` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(120) NOT NULL,
  `genero`             ENUM('masculino','femenino','mixto') NOT NULL DEFAULT 'mixto',
  `categoria`          VARCHAR(20) NOT NULL,
  `precio`             DECIMAL(8,2) NOT NULL DEFAULT 20.00,
  `plazas_max`         SMALLINT UNSIGNED NOT NULL DEFAULT 16,
  `plazas_min`         SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  `inscripcion_desde`  DATE NOT NULL,
  `inscripcion_hasta`  DATE NOT NULL,
  `fecha_inicio`       DATE NOT NULL,
  `ida_vuelta`         TINYINT(1) NOT NULL DEFAULT 0,
  `jornadas`           TINYINT UNSIGNED NULL,
  `reglamento`         TEXT NULL,
  `estado`             ENUM('borrador','inscripcion','cerrada','en_curso','finalizada','cancelada')
                       NOT NULL DEFAULT 'borrador',
  `creado_por`         INT NULL,
  `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_liga_cat_genero` (`categoria`, `genero`, `estado`),
  KEY `idx_liga_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `liga_head_jornadas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`     INT UNSIGNED NOT NULL,
  `numero`      TINYINT UNSIGNED NOT NULL,
  `fecha_desde` DATE NOT NULL,
  `fecha_hasta` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jornada` (`liga_id`, `numero`),
  KEY `idx_jornada_liga` (`liga_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `liga_head_parejas` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`        INT UNSIGNED NOT NULL,
  `jugador1_id`    INT NOT NULL,
  `jugador2_id`    INT NOT NULL,
  `estado`         ENUM('pendiente','confirmada','rechazada','baja') NOT NULL DEFAULT 'pendiente',
  `confirmada_por` INT NULL,
  `confirmada_at`  DATETIME NULL,
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pareja_liga` (`liga_id`, `estado`),
  KEY `idx_pareja_j1` (`liga_id`, `jugador1_id`),
  KEY `idx_pareja_j2` (`liga_id`, `jugador2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `liga_head_partidos` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`           INT UNSIGNED NOT NULL,
  `jornada_id`        INT UNSIGNED NOT NULL,
  `pareja_a_id`       INT UNSIGNED NOT NULL,
  `pareja_b_id`       INT UNSIGNED NOT NULL,
  `fecha`             DATE NULL,
  `hora`              TIME NULL,
  `estado`            ENUM('pendiente','jugado','aplazado','no_jugado') NOT NULL DEFAULT 'pendiente',
  `propuesto_por`     INT NULL,
  `propuesto_at`      DATETIME NULL,
  `validado_rival`    TINYINT(1) NOT NULL DEFAULT 0,
  `validado_rival_at` DATETIME NULL,
  `confirmado_club`   TINYINT(1) NOT NULL DEFAULT 0,
  `confirmado_por`    INT NULL,
  `confirmado_at`     DATETIME NULL,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partido` (`liga_id`, `pareja_a_id`, `pareja_b_id`),
  KEY `idx_partido_jornada` (`jornada_id`),
  KEY `idx_partido_liga` (`liga_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `liga_head_sets` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partido_id` INT UNSIGNED NOT NULL,
  `numero`    TINYINT UNSIGNED NOT NULL,
  `games_a`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `games_b`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_set` (`partido_id`, `numero`),
  KEY `idx_set_partido` (`partido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
