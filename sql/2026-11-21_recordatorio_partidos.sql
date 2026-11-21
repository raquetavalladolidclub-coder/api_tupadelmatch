-- Marca de "recordatorio 24 h enviado" por partido.
ALTER TABLE `partidos`
  ADD COLUMN `recordatorio_enviado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado`,
  ADD COLUMN `recordatorio_enviado_at` DATETIME NULL AFTER `recordatorio_enviado`;
