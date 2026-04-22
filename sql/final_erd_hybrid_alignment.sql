USE bloodline_db;

DROP PROCEDURE IF EXISTS sp_apply_final_erd_hybrid_alignment;
DELIMITER $$
CREATE PROCEDURE sp_apply_final_erd_hybrid_alignment()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'supplies'
    ) THEN
        DROP TABLE `supplies`;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'blood_request'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'blood_request'
              AND column_name = 'OwnerName'
        ) THEN
            ALTER TABLE `blood_request`
                ADD COLUMN `OwnerName` VARCHAR(120) NULL AFTER `RequesterName`;
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'blood_request'
              AND column_name = 'ReqDate'
        ) THEN
            ALTER TABLE `blood_request`
                ADD COLUMN `ReqDate` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `RequestDate`;
        END IF;

        UPDATE `blood_request`
        SET `OwnerName` = COALESCE(`OwnerName`, `RequesterName`),
            `ReqDate` = COALESCE(`ReqDate`, `RequestDate`);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'regular_donor'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'regular_donor'
              AND column_name = 'LastDonation'
        ) THEN
            ALTER TABLE `regular_donor`
                ADD COLUMN `LastDonation` DATE NULL AFTER `TotalDonations`;
        END IF;

        UPDATE `regular_donor`
        SET `LastDonation` = COALESCE(`LastDonation`, `LastDonationDate`);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'donates'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'donates'
              AND column_name = 'Date'
        ) THEN
            ALTER TABLE `donates`
                ADD COLUMN `Date` DATE NULL AFTER `DonationID`;
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'donates'
              AND column_name = 'Quantity'
        ) THEN
            ALTER TABLE `donates`
                ADD COLUMN `Quantity` TINYINT UNSIGNED NULL AFTER `Date`;
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'donates'
              AND column_name = 'BloodGroup'
        ) THEN
            ALTER TABLE `donates`
                ADD COLUMN `BloodGroup` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL AFTER `Quantity`;
        END IF;

        UPDATE `donates` dn
        JOIN `blood_donations` bd ON bd.`DonationID` = dn.`DonationID`
        SET dn.`Date` = COALESCE(dn.`Date`, bd.`DonationDate`),
            dn.`Quantity` = COALESCE(dn.`Quantity`, bd.`Quantity`),
            dn.`BloodGroup` = COALESCE(dn.`BloodGroup`, bd.`BloodGroup`);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'private_bloodbank'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'private_bloodbank'
              AND column_name = 'LicenseNo'
        ) THEN
            ALTER TABLE `private_bloodbank`
                ADD COLUMN `LicenseNo` VARCHAR(50) NULL AFTER `BloodBankID`;
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'private_bloodbank'
              AND index_name = 'uk_private_bloodbank_licenseno'
        ) THEN
            ALTER TABLE `private_bloodbank`
                ADD UNIQUE KEY `uk_private_bloodbank_licenseno` (`LicenseNo`);
        END IF;
    END IF;
END $$
DELIMITER ;

CALL sp_apply_final_erd_hybrid_alignment();
DROP PROCEDURE IF EXISTS sp_apply_final_erd_hybrid_alignment;
