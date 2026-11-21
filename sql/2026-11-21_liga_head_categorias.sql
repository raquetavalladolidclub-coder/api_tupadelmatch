-- Liga Head: una liga puede jugarse en varias categorías.
-- Cada categoría tiene su propio grupo de parejas, calendario y clasificación.

CREATE TABLE IF NOT EXISTS `liga_head_categorias` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liga_id`     INT UNSIGNED NOT NULL,
  `categoria`   VARCHAR(20) NOT NULL,
  `plazas_max`  SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liga_categoria` (`liga_id`, `categoria`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `liga_head_parejas`
  ADD COLUMN `categoria` VARCHAR(20) NULL AFTER `liga_id`,
  ADD KEY `idx_pareja_categoria` (`liga_id`, `categoria`);

ALTER TABLE `liga_head_jornadas`
  ADD COLUMN `categoria` VARCHAR(20) NULL AFTER `liga_id`;

-- Migración de los datos existentes (una categoría por liga).
INSERT IGNORE INTO `liga_head_categorias` (`liga_id`, `categoria`)
SELECT `id`, `categoria` FROM `ligas_head`
WHERE `categoria` IS NOT NULL AND `categoria` <> '';

UPDATE `liga_head_parejas` p
  JOIN `ligas_head` l ON l.id = p.liga_id
  SET p.categoria = l.categoria
  WHERE p.categoria IS NULL;

UPDATE `liga_head_jornadas` j
  JOIN `ligas_head` l ON l.id = j.liga_id
  SET j.categoria = l.categoria
  WHERE j.categoria IS NULL;
