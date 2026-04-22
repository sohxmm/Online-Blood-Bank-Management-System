USE bloodline_db;

CREATE TABLE IF NOT EXISTS Request_Transaction_Log (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    ReqID INT NOT NULL,
    EventType VARCHAR(40) NOT NULL,
    PreviousStatus ENUM('Pending','Fulfilled','Cancelled') NULL,
    NewStatus ENUM('Pending','Fulfilled','Cancelled') NULL,
    BloodGroup ENUM('O+','A+','B+','AB+','O-','A-','B-','AB-') NOT NULL,
    UnitsBefore INT NOT NULL,
    UnitsAfter INT NOT NULL,
    InventoryBefore INT NULL,
    InventoryAfter INT NULL,
    ActorName VARCHAR(120) NOT NULL,
    Notes VARCHAR(255) NULL,
    LoggedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_transaction_req (ReqID),
    INDEX idx_request_transaction_event (EventType),
    INDEX idx_request_transaction_logged (LoggedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

START TRANSACTION;

UPDATE Blood_Request
SET Status = 'Fulfilled'
WHERE ReqID = 1 AND Status = 'Pending';

SAVEPOINT after_request_status_change;

UPDATE Blood_Inventory bi
JOIN Blood_Request br ON br.BloodGroup = bi.BloodGroup
SET bi.UnitsAvailable = bi.UnitsAvailable - br.UnitsRequested
WHERE br.ReqID = 1;

INSERT INTO Request_Transaction_Log
    (ReqID, EventType, PreviousStatus, NewStatus, BloodGroup, UnitsBefore, UnitsAfter, ActorName, Notes)
SELECT ReqID, 'MANUAL_SQL_DEMO', 'Pending', 'Fulfilled', BloodGroup, UnitsRequested, UnitsRequested,
       'sql-demo', 'Manual TCL demo for the Bloodline writeup.'
FROM Blood_Request
WHERE ReqID = 1;

COMMIT;

CREATE ROLE IF NOT EXISTS `bloodline_request_operator`, `bloodline_auditor`;

GRANT SELECT, INSERT, UPDATE ON bloodline_db.Blood_Request TO `bloodline_request_operator`;
GRANT SELECT, UPDATE ON bloodline_db.Blood_Inventory TO `bloodline_request_operator`;
GRANT SELECT, INSERT ON bloodline_db.Request_Transaction_Log TO `bloodline_request_operator`;
GRANT SELECT ON bloodline_db.Hospital TO `bloodline_request_operator`;

GRANT SELECT ON bloodline_db.Blood_Request TO `bloodline_auditor`;
GRANT SELECT ON bloodline_db.Blood_Inventory TO `bloodline_auditor`;
GRANT SELECT ON bloodline_db.Request_Transaction_Log TO `bloodline_auditor`;

CREATE USER IF NOT EXISTS 'bloodline_ops'@'localhost' IDENTIFIED BY 'Bloodline@123';
CREATE USER IF NOT EXISTS 'bloodline_audit'@'localhost' IDENTIFIED BY 'Bloodline@123';

GRANT `bloodline_request_operator` TO 'bloodline_ops'@'localhost';
GRANT `bloodline_auditor` TO 'bloodline_audit'@'localhost';

REVOKE DELETE ON bloodline_db.Blood_Request FROM `bloodline_request_operator`;
REVOKE INSERT, UPDATE, DELETE ON bloodline_db.Blood_Inventory FROM `bloodline_auditor`;

FLUSH PRIVILEGES;
