-- Bloodline performance indexes and query-plan diagnostics
-- Database: bloodline_db
--
-- Goal: inspect and apply indexes that support the operational dashboards,
-- request queues, and hospital lookup workflows.
-- Note: If `EXPLAIN ANALYZE` is not supported on your setup, use plain `EXPLAIN`.
-- Schema note: the final ERD is implemented in hybrid form, so request workflow
-- columns are retained even though the legacy `Supplies` relationship is removed.

-- ---------------------------------------------------------------------------
-- Helper: safely drop an index only if it exists (avoids import-stopping errors)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;
DELIMITER //
CREATE PROCEDURE sp_drop_index_if_exists(IN tbl VARCHAR(64), IN idx VARCHAR(64))
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  IF EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = tbl
      AND index_name = idx
    LIMIT 1
  ) THEN
    SET @sql = CONCAT('DROP INDEX `', REPLACE(idx, '`', '``'), '` ON `', REPLACE(tbl, '`', '``'), '`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Phase A: baseline query plans without managed performance indexes
-- ---------------------------------------------------------------------------
CALL sp_drop_index_if_exists('Blood_Request', 'idx_br_status_reqdate_reqid');
CALL sp_drop_index_if_exists('Blood_Request', 'idx_br_requestdate_status');
CALL sp_drop_index_if_exists('Hospital',      'idx_hospital_name');
CALL sp_drop_index_if_exists('Blood_Request', 'idx_br_requesterphone_reqdate');

-- 1) Simple SELECT
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
LIMIT 20;

-- 2) SELECT with WHERE clause
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
WHERE Status = 'Pending'
LIMIT 20;

-- 3) SELECT with ORDER BY
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
WHERE Status = 'Pending'
ORDER BY RequestDate DESC, ReqID DESC
LIMIT 20;

-- 4) SELECT with JOIN
EXPLAIN
SELECT br.ReqID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status, br.RequestDate,
       h.Name AS HospitalName
FROM Blood_Request br
LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
WHERE br.Status = 'Pending'
ORDER BY br.RequestDate DESC, br.ReqID DESC
LIMIT 20;

-- 5) SELECT with aggregation
EXPLAIN
SELECT Status,
       COUNT(*) AS TotalRequests,
       COALESCE(SUM(UnitsRequested), 0) AS UnitsRequested
FROM Blood_Request
WHERE RequestDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY Status
ORDER BY TotalRequests DESC;

-- ---------------------------------------------------------------------------
-- Phase B: query plans with managed performance indexes
-- ---------------------------------------------------------------------------
CREATE INDEX `idx_br_status_reqdate_reqid`
  ON `Blood_Request` (`Status`, `RequestDate`, `ReqID`);

CREATE INDEX `idx_br_requestdate_status`
  ON `Blood_Request` (`RequestDate`, `Status`);

CREATE INDEX `idx_hospital_name`
  ON `Hospital` (`Name`);

CREATE INDEX `idx_br_requesterphone_reqdate`
  ON `Blood_Request` (`RequesterPhone`, `RequestDate`);

-- Optional: update optimizer stats
ANALYZE TABLE Blood_Request, Hospital;

-- Repeat the same 5 EXPLAINs to compare access paths and row estimates.

-- 1) Simple SELECT
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
LIMIT 20;

-- 2) SELECT with WHERE clause
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
WHERE Status = 'Pending'
LIMIT 20;

-- 3) SELECT with ORDER BY
EXPLAIN
SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate
FROM Blood_Request
WHERE Status = 'Pending'
ORDER BY RequestDate DESC, ReqID DESC
LIMIT 20;

-- 4) SELECT with JOIN
EXPLAIN
SELECT br.ReqID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status, br.RequestDate,
       h.Name AS HospitalName
FROM Blood_Request br
LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
WHERE br.Status = 'Pending'
ORDER BY br.RequestDate DESC, br.ReqID DESC
LIMIT 20;

-- 5) SELECT with aggregation
EXPLAIN
SELECT Status,
       COUNT(*) AS TotalRequests,
       COALESCE(SUM(UnitsRequested), 0) AS UnitsRequested
FROM Blood_Request
WHERE RequestDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY Status
ORDER BY TotalRequests DESC;
