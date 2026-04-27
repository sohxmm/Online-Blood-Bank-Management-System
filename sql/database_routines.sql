-- Bloodline production database routines
-- Project: Bloodline (bloodline_db)
-- DB: MariaDB/MySQL (XAMPP)
--
-- How to run:
-- 1) Open phpMyAdmin -> bloodline_db -> SQL tab
-- 2) Paste this file and Run (or Import the file)
--
-- Notes:
-- - MySQL/MariaDB cursors exist only inside stored programs (procedures/functions).
-- - These routines provide dashboard metrics, low-stock reporting, and inventory-safe
--   request fulfillment for the running Bloodline service.

DELIMITER $$

-- -----------------------------
-- Functions
-- -----------------------------

DROP FUNCTION IF EXISTS fn_donor_count $$
CREATE FUNCTION fn_donor_count()
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_records INT DEFAULT 0;
    SELECT COUNT(*) INTO total_records FROM Donor;
    RETURN total_records;
END $$

DROP FUNCTION IF EXISTS fn_pending_request_count $$
CREATE FUNCTION fn_pending_request_count()
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_records INT DEFAULT 0;
    SELECT COUNT(*) INTO total_records
    FROM Blood_Request
    WHERE Status = 'Pending';
    RETURN total_records;
END $$

DROP FUNCTION IF EXISTS fn_total_units_available $$
CREATE FUNCTION fn_total_units_available()
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_units INT DEFAULT 0;
    SELECT COALESCE(SUM(UnitsAvailable), 0) INTO total_units
    FROM Blood_Inventory;
    RETURN total_units;
END $$

-- -----------------------------
-- Cursor-based procedure
-- -----------------------------

DROP PROCEDURE IF EXISTS sp_low_stock_report $$
CREATE PROCEDURE sp_low_stock_report(IN p_threshold INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_blood_group VARCHAR(5);
    DECLARE v_units_available INT;

    DECLARE cur_inventory CURSOR FOR
        SELECT BloodGroup, UnitsAvailable
        FROM Blood_Inventory
        ORDER BY FIELD(BloodGroup, 'O+','A+','B+','AB+','O-','A-','B-','AB-');

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_low_stock;
    CREATE TEMPORARY TABLE tmp_low_stock (
        BloodGroup VARCHAR(5) NOT NULL,
        UnitsAvailable INT NOT NULL
    );

    OPEN cur_inventory;

    read_loop: LOOP
        FETCH cur_inventory INTO v_blood_group, v_units_available;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        IF v_units_available <= p_threshold THEN
            INSERT INTO tmp_low_stock (BloodGroup, UnitsAvailable)
            VALUES (v_blood_group, v_units_available);
        END IF;
    END LOOP;

    CLOSE cur_inventory;

    SELECT BloodGroup, UnitsAvailable
    FROM tmp_low_stock
    ORDER BY UnitsAvailable ASC, BloodGroup ASC;
END $$

-- -----------------------------
-- Transactional procedure (transfer-like)
-- -----------------------------

DROP PROCEDURE IF EXISTS sp_fulfill_request $$
CREATE PROCEDURE sp_fulfill_request(IN p_req_id INT)
main: BEGIN
    DECLARE v_blood_group VARCHAR(5);
    DECLARE v_units_requested INT;
    DECLARE v_status VARCHAR(20);
    DECLARE v_units_available INT DEFAULT 0;
    DECLARE v_not_found INT DEFAULT 0;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_not_found = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT
            0 AS success,
            'Database error. Transaction rolled back.' AS message,
            p_req_id AS request_id;
    END;

    START TRANSACTION;

    -- Lock the request row.
    SET v_not_found = 0;
    SELECT BloodGroup, UnitsRequested, Status
      INTO v_blood_group, v_units_requested, v_status
    FROM Blood_Request
    WHERE ReqID = p_req_id
    FOR UPDATE;

    IF v_not_found = 1 THEN
        ROLLBACK;
        SELECT 0 AS success, 'Request not found.' AS message, p_req_id AS request_id;
        LEAVE main;
    END IF;

    IF v_status <> 'Pending' THEN
        ROLLBACK;
        SELECT
            0 AS success,
            CONCAT('Request is not Pending (current status: ', v_status, ').') AS message,
            p_req_id AS request_id,
            v_status AS status;
        LEAVE main;
    END IF;

    -- Lock the inventory row for the requested blood group.
    SET v_not_found = 0;
    SELECT UnitsAvailable
      INTO v_units_available
    FROM Blood_Inventory
    WHERE BloodGroup = v_blood_group
    FOR UPDATE;

    IF v_not_found = 1 THEN
        SET v_units_available = 0;
    END IF;

    IF v_units_available < v_units_requested THEN
        ROLLBACK;
        SELECT
            0 AS success,
            CONCAT(
                'Insufficient inventory for ', v_blood_group,
                '. Available: ', v_units_available,
                ', requested: ', v_units_requested, '.'
            ) AS message,
            p_req_id AS request_id,
            v_blood_group AS blood_group,
            v_units_requested AS units_requested,
            v_units_available AS units_available;
        LEAVE main;
    END IF;

    -- Perform the "transfer-like" update: subtract from inventory + mark request fulfilled.
    UPDATE Blood_Inventory
    SET UnitsAvailable = UnitsAvailable - v_units_requested
    WHERE BloodGroup = v_blood_group;

    UPDATE Blood_Request
    SET Status = 'Fulfilled'
    WHERE ReqID = p_req_id;

    COMMIT;

    SELECT
        1 AS success,
        'Request fulfilled and inventory updated.' AS message,
        p_req_id AS request_id,
        v_blood_group AS blood_group,
        v_units_requested AS units_requested,
        v_units_available AS units_available_before,
        (v_units_available - v_units_requested) AS units_available_after,
        'Fulfilled' AS status;
END $$

DELIMITER ;

-- -----------------------------
-- Example calls (run after install)
-- -----------------------------
-- SELECT fn_donor_count() AS donor_count;
-- SELECT fn_pending_request_count() AS pending_requests;
-- SELECT fn_total_units_available() AS total_units;
-- CALL sp_low_stock_report(4);
-- CALL sp_fulfill_request(1);
