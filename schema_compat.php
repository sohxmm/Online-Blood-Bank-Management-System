<?php

function schema_query(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException('Schema compatibility update failed: ' . $db->error);
    }
}

function schema_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function schema_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function schema_index_exists(mysqli $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?
         LIMIT 1'
    );
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function ensure_hybrid_schema(mysqli $db): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    if (schema_table_exists($db, 'supplies')) {
        schema_query($db, 'DROP TABLE `supplies`');
    }

    if (schema_table_exists($db, 'blood_request')) {
        if (!schema_column_exists($db, 'blood_request', 'OwnerName')) {
            schema_query(
                $db,
                'ALTER TABLE `blood_request`
                 ADD COLUMN `OwnerName` VARCHAR(120) NULL AFTER `RequesterName`'
            );
        }

        if (!schema_column_exists($db, 'blood_request', 'ReqDate')) {
            schema_query(
                $db,
                'ALTER TABLE `blood_request`
                 ADD COLUMN `ReqDate` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `RequestDate`'
            );
        }

        schema_query(
            $db,
            'UPDATE `blood_request`
             SET `OwnerName` = COALESCE(`OwnerName`, `RequesterName`),
                 `ReqDate` = COALESCE(`ReqDate`, `RequestDate`)'
        );
    }

    if (schema_table_exists($db, 'regular_donor')) {
        if (!schema_column_exists($db, 'regular_donor', 'LastDonation')) {
            schema_query(
                $db,
                'ALTER TABLE `regular_donor`
                 ADD COLUMN `LastDonation` DATE NULL AFTER `TotalDonations`'
            );
        }

        schema_query(
            $db,
            'UPDATE `regular_donor`
             SET `LastDonation` = COALESCE(`LastDonation`, `LastDonationDate`)'
        );
    }

    if (schema_table_exists($db, 'donates')) {
        if (!schema_column_exists($db, 'donates', 'Date')) {
            schema_query(
                $db,
                'ALTER TABLE `donates`
                 ADD COLUMN `Date` DATE NULL AFTER `DonationID`'
            );
        }

        if (!schema_column_exists($db, 'donates', 'Quantity')) {
            schema_query(
                $db,
                'ALTER TABLE `donates`
                 ADD COLUMN `Quantity` TINYINT UNSIGNED NULL AFTER `Date`'
            );
        }

        if (!schema_column_exists($db, 'donates', 'BloodGroup')) {
            schema_query(
                $db,
                'ALTER TABLE `donates`
                 ADD COLUMN `BloodGroup` ENUM("A+","A-","B+","B-","AB+","AB-","O+","O-") NULL AFTER `Quantity`'
            );
        }

        schema_query(
            $db,
            'UPDATE `donates` dn
             JOIN `blood_donations` bd ON bd.`DonationID` = dn.`DonationID`
             SET dn.`Date` = COALESCE(dn.`Date`, bd.`DonationDate`),
                 dn.`Quantity` = COALESCE(dn.`Quantity`, bd.`Quantity`),
                 dn.`BloodGroup` = COALESCE(dn.`BloodGroup`, bd.`BloodGroup`)'
        );
    }

    if (schema_table_exists($db, 'private_bloodbank')) {
        if (!schema_column_exists($db, 'private_bloodbank', 'LicenseNo')) {
            schema_query(
                $db,
                'ALTER TABLE `private_bloodbank`
                 ADD COLUMN `LicenseNo` VARCHAR(50) NULL AFTER `BloodBankID`'
            );
        }

        if (!schema_index_exists($db, 'private_bloodbank', 'uk_private_bloodbank_licenseno')) {
            schema_query(
                $db,
                'ALTER TABLE `private_bloodbank`
                 ADD UNIQUE KEY `uk_private_bloodbank_licenseno` (`LicenseNo`)'
            );
        }
    }

    $ensured = true;
}
