<?php
// Operations console page for production database routines.

require_once 'auth.php';
require_once 'db.php';

require_login();
$db = getDB();

function drain_mysqli_results(mysqli $db): void
{
    while ($db->more_results()) {
        $db->next_result();
        $res = $db->use_result();
        if ($res instanceof mysqli_result) {
            $res->free();
        }
    }
}

function try_scalar(mysqli $db, string $sql, string $key): ?int
{
    $res = $db->query($sql);
    if (!$res) {
        return null;
    }

    $row = $res->fetch_assoc() ?: [];
    $res->free();

    return isset($row[$key]) ? (int) $row[$key] : null;
}

$installHint = 'Install the production database routines first by running '
    . 'sql/database_routines.sql in phpMyAdmin (bloodline_db).';

$donorCount = try_scalar($db, 'SELECT fn_donor_count() AS cnt', 'cnt');
if ($donorCount === null) {
    $donorCount = try_scalar($db, 'SELECT COUNT(*) AS cnt FROM Donor', 'cnt') ?? 0;
}

$pendingCount = try_scalar($db, 'SELECT fn_pending_request_count() AS cnt', 'cnt');
if ($pendingCount === null) {
    $pendingCount = try_scalar($db, "SELECT COUNT(*) AS cnt FROM Blood_Request WHERE Status='Pending'", 'cnt') ?? 0;
}

$totalUnits = try_scalar($db, 'SELECT fn_total_units_available() AS total', 'total');
if ($totalUnits === null) {
    $totalUnits = try_scalar($db, 'SELECT COALESCE(SUM(UnitsAvailable), 0) AS total FROM Blood_Inventory', 'total') ?? 0;
}

$pendingList = [];
$pendingStmt = $db->prepare(
    "SELECT ReqID, BloodGroup, UnitsRequested, Urgency, RequestDate
     FROM Blood_Request
     WHERE Status = 'Pending'
     ORDER BY RequestDate DESC, ReqID DESC
     LIMIT 10"
);
if ($pendingStmt && $pendingStmt->execute()) {
    $pendingList = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pendingStmt->close();
}

$lowStockThreshold = 4;
$lowStockRows = [];
$lowStockError = '';

$fulfillRequestId = 0;
$fulfillResult = null;
$fulfillError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['csrf_token'] ?? null, 'operations_console.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'low_stock') {
        $lowStockThreshold = max(0, (int) ($_POST['threshold'] ?? 4));
        $stmt = $db->prepare('CALL sp_low_stock_report(?)');

        if (!$stmt) {
            $lowStockError = $installHint;
        } else {
            $stmt->bind_param('i', $lowStockThreshold);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $lowStockRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) {
                    $res->free();
                }
            } else {
                $lowStockError = $installHint;
            }
            $stmt->close();
            drain_mysqli_results($db);
        }
    }

    if ($action === 'fulfill_request') {
        $fulfillRequestId = (int) ($_POST['request_id'] ?? 0);
        $stmt = $db->prepare('CALL sp_fulfill_request(?)');

        if (!$stmt) {
            $fulfillError = $installHint;
        } else {
            $stmt->bind_param('i', $fulfillRequestId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $fulfillResult = $res ? ($res->fetch_assoc() ?: null) : null;
                if ($res) {
                    $res->free();
                }
            } else {
                $fulfillError = $installHint;
            }
            $stmt->close();
            drain_mysqli_results($db);
        }
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operations Console</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{
      --cream:#faf7f4;--cream-dark:#f0ebe5;--black:#1a0a0d;--red:#b5121f;
      --gray:#7a6065;--gray-light:#c9b8bc;--serif:'Cormorant Garamond',Georgia,serif;--sans:'DM Sans',sans-serif;
    }
    body{font-family:var(--sans);background:var(--cream);color:var(--black);padding:48px 24px}
    .wrap{max-width:1100px;margin:0 auto}
    .top{display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap;margin-bottom:24px}
    h1{font-family:var(--serif);font-size:clamp(2.1rem,4vw,3.2rem);line-height:1}
    h1 em{color:var(--red);font-style:italic}
    .sub{max-width:520px;color:var(--gray);line-height:1.7}
    .card{background:#fff;border:1px solid var(--gray-light);border-radius:22px;box-shadow:0 10px 30px rgba(26,10,13,.04);padding:22px;margin-top:16px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .metric{background:var(--cream);border:1px solid rgba(181,18,31,.08);border-radius:18px;padding:16px}
    .metric .val{font-family:var(--serif);font-size:2rem;line-height:1.1}
    .metric .lbl{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray);margin-top:6px}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
    label{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray)}
    input{border:1px solid var(--gray-light);border-radius:14px;padding:12px 14px;font:inherit;background:var(--cream);min-width:200px}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--black);padding:12px 16px;border-radius:999px;background:var(--black);color:var(--cream);text-decoration:none;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}
    .btn.secondary{background:transparent;color:var(--black);border-color:var(--gray-light)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:14px 12px;border-bottom:1px solid #eee7e2;text-align:left;vertical-align:top}
    th{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray);background:#fffaf7}
    .msg{margin-top:12px;color:#991b1b}
    code{background:rgba(26,10,13,.06);padding:2px 6px;border-radius:8px}
    @media (max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1><em>Operations</em> Console</h1>
        <p class="sub">
          Operational database routine console backed by <code>sql/database_routines.sql</code>.
          If a routine is missing, run the SQL file in phpMyAdmin (database: <code>bloodline_db</code>).
        </p>
      </div>
      <div class="row">
        <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <div class="card">
      <h2 style="font-family:var(--serif);margin-bottom:10px">Operational Metrics</h2>
      <div class="grid">
        <div class="metric">
          <div class="val"><?= (int) $donorCount ?></div>
          <div class="lbl">Total Donors</div>
        </div>
        <div class="metric">
          <div class="val"><?= (int) $pendingCount ?></div>
          <div class="lbl">Pending Requests</div>
        </div>
        <div class="metric">
          <div class="val"><?= (int) $totalUnits ?></div>
          <div class="lbl">Total Units Available</div>
        </div>
      </div>
      <p class="sub" style="margin-top:12px">
        Metric routines: <code>SELECT fn_donor_count()</code>,
        <code>SELECT fn_pending_request_count()</code>,
        <code>SELECT fn_total_units_available()</code>.
      </p>
    </div>

    <div class="card">
      <h2 style="font-family:var(--serif);margin-bottom:10px">Low Stock Report</h2>
      <form method="POST" class="row">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="low_stock">
        <div style="display:flex;flex-direction:column;gap:8px">
          <label for="threshold">Threshold (<=)</label>
          <input id="threshold" type="number" min="0" max="999" name="threshold" value="<?= (int) $lowStockThreshold ?>">
        </div>
        <button class="btn" type="submit">Generate Report</button>
      </form>

      <?php if ($lowStockError !== ''): ?>
        <div class="msg"><?= h($lowStockError) ?></div>
      <?php endif; ?>

      <?php if ($lowStockRows): ?>
        <table>
          <thead>
            <tr>
              <th>Blood Group</th>
              <th>Units Available</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lowStockRows as $row): ?>
              <tr>
                <td><?= h((string) ($row['BloodGroup'] ?? '')) ?></td>
                <td><?= (int) ($row['UnitsAvailable'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'low_stock' && $lowStockError === ''): ?>
        <p class="sub" style="margin-top:12px">No blood groups are at or below the threshold.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="font-family:var(--serif);margin-bottom:10px">Request Fulfillment Routine</h2>
      <p class="sub">
        Runs <code>CALL sp_fulfill_request(req_id)</code>. It runs a transaction that locks the request + inventory row,
        checks available units, then subtracts units and sets status to <code>Fulfilled</code>.
      </p>
      <form method="POST" class="row" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="fulfill_request">
        <div style="display:flex;flex-direction:column;gap:8px">
          <label for="request_id">Request ID</label>
          <input id="request_id" type="number" min="1" name="request_id" value="<?= (int) $fulfillRequestId ?>" required>
        </div>
        <button class="btn" type="submit">Fulfill</button>
      </form>

      <?php if ($fulfillError !== ''): ?>
        <div class="msg"><?= h($fulfillError) ?></div>
      <?php endif; ?>

      <?php if (is_array($fulfillResult)): ?>
        <table>
          <thead>
            <tr>
              <th>Success</th>
              <th>Message</th>
              <th>Request</th>
              <th>Blood Group</th>
              <th>Units</th>
              <th>Before</th>
              <th>After</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= (int) ($fulfillResult['success'] ?? 0) ?></td>
              <td><?= h((string) ($fulfillResult['message'] ?? '')) ?></td>
              <td><?= (int) ($fulfillResult['request_id'] ?? 0) ?></td>
              <td><?= h((string) ($fulfillResult['blood_group'] ?? '')) ?></td>
              <td><?= (int) ($fulfillResult['units_requested'] ?? 0) ?></td>
              <td><?= isset($fulfillResult['units_available_before']) ? (int) $fulfillResult['units_available_before'] : 'â€”' ?></td>
              <td><?= isset($fulfillResult['units_available_after']) ? (int) $fulfillResult['units_available_after'] : 'â€”' ?></td>
              <td><?= h((string) ($fulfillResult['status'] ?? '')) ?></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>

      <h3 style="font-family:var(--serif);margin-top:18px">Latest Pending Requests</h3>
      <?php if (!$pendingList): ?>
        <p class="sub" style="margin-top:8px">No pending requests found.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ReqID</th>
              <th>Blood Group</th>
              <th>Units</th>
              <th>Urgency</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingList as $row): ?>
              <tr>
                <td><?= (int) ($row['ReqID'] ?? 0) ?></td>
                <td><?= h((string) ($row['BloodGroup'] ?? '')) ?></td>
                <td><?= (int) ($row['UnitsRequested'] ?? 0) ?></td>
                <td><?= h((string) ($row['Urgency'] ?? '')) ?></td>
                <td><?= h(date('d M Y, h:i A', strtotime((string) ($row['RequestDate'] ?? 'now')))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

