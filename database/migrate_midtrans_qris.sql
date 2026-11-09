USE `jazuva_db`;

ALTER TABLE `payments`
  ADD COLUMN `midtrans_transaction_id` VARCHAR(100) NULL AFTER `tax_amount`,
  ADD COLUMN `qris_qr_url` TEXT NULL AFTER `midtrans_transaction_id`;
