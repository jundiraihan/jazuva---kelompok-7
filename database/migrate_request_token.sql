USE `jazuva_db`;

ALTER TABLE `orders`
  ADD COLUMN `request_token` CHAR(36) NULL UNIQUE AFTER `final_amount`;

UPDATE `restaurant_tables`
SET `token` = SHA2(CONCAT(UUID(), RAND(), `id`, NOW(6)), 256);
