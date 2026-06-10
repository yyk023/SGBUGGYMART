-- Add datasheet PDF support to buggies and accessories
-- Run this once on your database.

ALTER TABLE `buggies`
    ADD COLUMN `datasheet_url` VARCHAR(255) NULL DEFAULT NULL AFTER `image_url`;

ALTER TABLE `accessories`
    ADD COLUMN `datasheet_url` VARCHAR(255) NULL DEFAULT NULL AFTER `image_url`;
