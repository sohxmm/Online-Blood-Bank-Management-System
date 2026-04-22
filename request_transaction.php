<?php

require_once 'auth.php';
require_once 'db.php';

function ensure_request_transaction_log(mysqli $db): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $db->query(
        'CREATE TABLE IF NOT EXISTS Request_Transaction_Log (
            LogID INT AUTO_INCREMENT PRIMARY KEY,
            ReqID INT NOT NULL,
            EventType VARCHAR(40) NOT NULL,
            PreviousStatus ENUM("Pending","Fulfilled","Cancelled") NULL,
            NewStatus ENUM("Pending","Fulfilled","Cancelled") NULL,
            BloodGroup ENUM("O+","A+","B+","AB+","O-","A-","B-","AB-") NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $ensured = true;
}

function request_transaction_actor(): string
{
    boot_session();

    $actorName = trim((string) ($_SESSION['donor_name'] ?? ''));
    if ($actorName !== '') {
        return $actorName;
    }

    $donorId = (int) ($_SESSION['donor_id'] ?? 0);

    return $donorId > 0 ? 'Donor #' . $donorId : 'Bloodline Operator';
}

function fetch_request_for_update(mysqli $db, int $requestId): array
{
    $stmt = $db->prepare(
        'SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status
         FROM Blood_Request
         WHERE ReqID = ?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    if (!$request) {
        throw new RuntimeException('The selected blood request was not found.');
    }

    return $request;
}

function fetch_inventory_for_update(mysqli $db, string $bloodGroup): array
{
    $stmt = $db->prepare(
        'SELECT BloodGroup, UnitsAvailable
         FROM Blood_Inventory
         WHERE BloodGroup = ?
         FOR UPDATE'
    );
    $stmt->bind_param('s', $bloodGroup);
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    if (!$inventory) {
        throw new RuntimeException('No inventory row exists for blood group ' . $bloodGroup . '.');
    }

    return $inventory;
}

function update_inventory_units(mysqli $db, string $bloodGroup, int $unitsAvailable): void
{
    $stmt = $db->prepare(
        'UPDATE Blood_Inventory
         SET UnitsAvailable = ?
         WHERE BloodGroup = ?'
    );
    $stmt->bind_param('is', $unitsAvailable, $bloodGroup);
    $stmt->execute();
    $stmt->close();
}

function insert_request_transaction_log(
    mysqli $db,
    int $requestId,
    string $eventType,
    ?string $previousStatus,
    ?string $newStatus,
    string $bloodGroup,
    int $unitsBefore,
    int $unitsAfter,
    ?int $inventoryBefore,
    ?int $inventoryAfter,
    string $actorName,
    string $notes
): void {
    ensure_request_transaction_log($db);

    $stmt = $db->prepare(
        'INSERT INTO Request_Transaction_Log
         (ReqID, EventType, PreviousStatus, NewStatus, BloodGroup, UnitsBefore, UnitsAfter,
          InventoryBefore, InventoryAfter, ActorName, Notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param(
        'issssiiiiss',
        $requestId,
        $eventType,
        $previousStatus,
        $newStatus,
        $bloodGroup,
        $unitsBefore,
        $unitsAfter,
        $inventoryBefore,
        $inventoryAfter,
        $actorName,
        $notes
    );
    $stmt->execute();
    $stmt->close();
}

function update_request_transactionally(
    mysqli $db,
    int $requestId,
    int $unitsRequested,
    string $urgency,
    string $newStatus,
    string $actorName
): string {
    $db->query('SET TRANSACTION READ WRITE');
    $db->begin_transaction();

    try {
        $request = fetch_request_for_update($db, $requestId);
        $db->query('SAVEPOINT request_snapshot');

        $bloodGroup = (string) $request['BloodGroup'];
        $previousStatus = (string) $request['Status'];
        $previousUnits = (int) $request['UnitsRequested'];
        $inventoryBefore = null;
        $inventoryAfter = null;
        $noteParts = [];

        if ($previousStatus !== 'Fulfilled' && $newStatus === 'Fulfilled') {
            $inventory = fetch_inventory_for_update($db, $bloodGroup);
            $inventoryBefore = (int) $inventory['UnitsAvailable'];

            if ($inventoryBefore < $unitsRequested) {
                $db->query('ROLLBACK TO SAVEPOINT request_snapshot');
                throw new RuntimeException(
                    'Not enough ' . $bloodGroup . ' units are available to fulfill this request.'
                );
            }

            $inventoryAfter = $inventoryBefore - $unitsRequested;
            update_inventory_units($db, $bloodGroup, $inventoryAfter);
            $noteParts[] = 'Inventory deducted after fulfillment.';
        } elseif ($previousStatus === 'Fulfilled' && $newStatus !== 'Fulfilled') {
            $inventory = fetch_inventory_for_update($db, $bloodGroup);
            $inventoryBefore = (int) $inventory['UnitsAvailable'];
            $inventoryAfter = $inventoryBefore + $previousUnits;
            update_inventory_units($db, $bloodGroup, $inventoryAfter);
            $noteParts[] = 'Inventory restored because fulfillment was reverted.';
        } elseif ($previousStatus === 'Fulfilled' && $newStatus === 'Fulfilled' && $previousUnits !== $unitsRequested) {
            $inventory = fetch_inventory_for_update($db, $bloodGroup);
            $inventoryBefore = (int) $inventory['UnitsAvailable'];
            $delta = $unitsRequested - $previousUnits;

            if ($delta > 0 && $inventoryBefore < $delta) {
                $db->query('ROLLBACK TO SAVEPOINT request_snapshot');
                throw new RuntimeException(
                    'Only ' . $inventoryBefore . ' extra ' . $bloodGroup . ' units are available for this update.'
                );
            }

            $inventoryAfter = $inventoryBefore - $delta;
            update_inventory_units($db, $bloodGroup, $inventoryAfter);
            $noteParts[] = 'Inventory rebalanced for the fulfilled request.';
        }

        $stmt = $db->prepare(
            'UPDATE Blood_Request
             SET UnitsRequested = ?, Urgency = ?, Status = ?
             WHERE ReqID = ?'
        );
        $stmt->bind_param('issi', $unitsRequested, $urgency, $newStatus, $requestId);
        $stmt->execute();
        $stmt->close();

        $noteParts[] = 'Request updated through TCL-aware workflow.';
        insert_request_transaction_log(
            $db,
            $requestId,
            'UPDATE_REQUEST',
            $previousStatus,
            $newStatus,
            $bloodGroup,
            $previousUnits,
            $unitsRequested,
            $inventoryBefore,
            $inventoryAfter,
            $actorName,
            implode(' ', $noteParts)
        );

        $db->commit();

        if ($previousStatus !== $newStatus && $newStatus === 'Fulfilled') {
            return 'Blood request fulfilled and inventory updated successfully.';
        }

        if ($previousStatus === 'Fulfilled' && $newStatus !== 'Fulfilled') {
            return 'Blood request updated and inventory restored successfully.';
        }

        return 'Blood request updated successfully.';
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function delete_request_transactionally(mysqli $db, int $requestId, string $actorName): string
{
    $db->query('SET TRANSACTION READ WRITE');
    $db->begin_transaction();

    try {
        $request = fetch_request_for_update($db, $requestId);
        $db->query('SAVEPOINT request_snapshot');

        $bloodGroup = (string) $request['BloodGroup'];
        $previousStatus = (string) $request['Status'];
        $previousUnits = (int) $request['UnitsRequested'];
        $inventoryBefore = null;
        $inventoryAfter = null;
        $notes = 'Request deleted from the manage requests console.';

        if ($previousStatus === 'Fulfilled') {
            $inventory = fetch_inventory_for_update($db, $bloodGroup);
            $inventoryBefore = (int) $inventory['UnitsAvailable'];
            $inventoryAfter = $inventoryBefore + $previousUnits;
            update_inventory_units($db, $bloodGroup, $inventoryAfter);
            $notes = 'Fulfilled request deleted and inventory restored before removal.';
        }

        insert_request_transaction_log(
            $db,
            $requestId,
            'DELETE_REQUEST',
            $previousStatus,
            null,
            $bloodGroup,
            $previousUnits,
            0,
            $inventoryBefore,
            $inventoryAfter,
            $actorName,
            $notes
        );

        $stmt = $db->prepare('DELETE FROM Blood_Request WHERE ReqID = ?');
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows < 1) {
            $db->query('ROLLBACK TO SAVEPOINT request_snapshot');
            throw new RuntimeException('No blood request was deleted.');
        }

        $db->commit();

        return $previousStatus === 'Fulfilled'
            ? 'Blood request deleted and inventory restored successfully.'
            : 'Blood request deleted successfully.';
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
