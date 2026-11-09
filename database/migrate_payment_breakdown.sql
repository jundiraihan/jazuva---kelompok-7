USE `jazuva_db`;

ALTER TABLE `payments`
  ADD COLUMN `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER `payment_method`,
  ADD COLUMN `service_charge_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
  ADD COLUMN `tax_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER `service_charge_amount`;

UPDATE `payments`
SET `subtotal` = `total_bill`,
    `service_charge_amount` = 0.00,
    `tax_amount` = 0.00;
