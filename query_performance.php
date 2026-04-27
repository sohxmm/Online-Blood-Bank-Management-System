<?php
// Query performance console for managed database indexes.

require_once 'auth.php';
require_once 'db.php';

require_login();
$db = getDB();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function index_exists(mysqli $db, string $table, string $indexName): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $indexName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $exists;
}

function run_explain(mysqli $db, string $sql): array
{
    $attempts = [
        ['label' => 'EXPLAIN ANALYZE', 'prefix' => 'EXPLAIN ANALYZE '],
        ['label' => 'EXPLAIN', 'prefix' => 'EXPLAIN '],
        ['label' => 'EXPLAIN FORMAT=JSON', 'prefix' => 'EXPLAIN FORMAT=JSON '],
    ];

    $lastError = '';

    foreach ($attempts as $attempt) {
        try {
            $res = $db->query($attempt['prefix'] . $sql);
        } catch (mysqli_sql_exception $e) {
            $lastError = $e->getMessage();
            continue;
        }

        if (!$res instanceof mysqli_result) {
            $lastError = $db->error ?: $lastError;
            continue;
        }

        $fields = $res->fetch_fields();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();

        if (count($fields) === 1) {
            $col = $fields[0]->name ?? '';
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = (string) ($row[$col] ?? reset($row) ?? '');
            }
            return [
                'kind' => 'text',
                'label' => $attempt['label'],
                'value' => implode("\n", $lines),
            ];
        }

        return [
            'kind' => 'table',
            'label' => $attempt['label'],
            'fields' => array_map(fn($f) => $f->name, $fields),
            'rows' => $rows,
        ];
    }

    return [
        'kind' => 'error',
        'label' => 'EXPLAIN',
        'value' => $lastError ?: ($db->error ?: 'Unable to generate an EXPLAIN plan for this query.'),
    ];
}

function fetch_performance_index_details(mysqli $db): array
{
    $stmt = $db->prepare(
        "SELECT table_name AS TableName,
                index_name AS IndexName,
                seq_in_index AS SeqInIndex,
                column_name AS ColumnName,
                non_unique AS NonUnique
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND index_name IN (
               'idx_br_status_reqdate_reqid',
               'idx_br_requestdate_status',
               'idx_hospital_name',
               'idx_br_requesterphone_reqdate'
           )
         ORDER BY table_name, index_name, seq_in_index"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $rows;
}

$performanceIndexes = [
    [
        'table' => 'Blood_Request',
        'name' => 'idx_br_status_reqdate_reqid',
        'purpose' => 'WHERE Status + ORDER BY RequestDate/ReqID',
        'create_sql' => 'CREATE INDEX `idx_br_status_reqdate_reqid` ON `Blood_Request` (`Status`, `RequestDate`, `ReqID`)',
        'drop_sql' => 'DROP INDEX `idx_br_status_reqdate_reqid` ON `Blood_Request`',
    ],
    [
        'table' => 'Blood_Request',
        'name' => 'idx_br_requestdate_status',
        'purpose' => 'Date range + GROUP BY Status',
        'create_sql' => 'CREATE INDEX `idx_br_requestdate_status` ON `Blood_Request` (`RequestDate`, `Status`)',
        'drop_sql' => 'DROP INDEX `idx_br_requestdate_status` ON `Blood_Request`',
    ],
    [
        'table' => 'Hospital',
        'name' => 'idx_hospital_name',
        'purpose' => 'Lookup hospital by name',
        'create_sql' => 'CREATE INDEX `idx_hospital_name` ON `Hospital` (`Name`)',
        'drop_sql' => 'DROP INDEX `idx_hospital_name` ON `Hospital`',
    ],
    [
        'table' => 'Blood_Request',
        'name' => 'idx_br_requesterphone_reqdate',
        'purpose' => 'Dashboard-style WHERE RequesterPhone',
        'create_sql' => 'CREATE INDEX `idx_br_requesterphone_reqdate` ON `Blood_Request` (`RequesterPhone`, `RequestDate`)',
        'drop_sql' => 'DROP INDEX `idx_br_requesterphone_reqdate` ON `Blood_Request`',
    ],
];

$queries = [
    'Simple SELECT' => [
        'sql' => 'SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate FROM Blood_Request LIMIT 20',
        'note' => 'Baseline query without filters.',
    ],
    'SELECT with WHERE clause' => [
        'sql' => "SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate\nFROM Blood_Request\nWHERE Status = 'Pending'\nLIMIT 20",
        'note' => "Uses a filter on Status (benefits from idx_br_status_reqdate_reqid).",
    ],
    'SELECT with ORDER BY' => [
        'sql' => "SELECT ReqID, BloodGroup, UnitsRequested, Urgency, Status, RequestDate\nFROM Blood_Request\nWHERE Status = 'Pending'\nORDER BY RequestDate DESC, ReqID DESC\nLIMIT 20",
        'note' => 'Filter + order by date/id.',
    ],
    'SELECT with JOIN' => [
        'sql' => "SELECT br.ReqID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status, br.RequestDate,\n       h.Name AS HospitalName\nFROM Blood_Request br\nLEFT JOIN Hospital h ON h.HospitalID = br.HospitalID\nWHERE br.Status = 'Pending'\nORDER BY br.RequestDate DESC, br.ReqID DESC\nLIMIT 20",
        'note' => 'JOIN query (request â†” hospital).',
    ],
    'SELECT with Aggregation' => [
        'sql' => "SELECT Status,\n       COUNT(*) AS TotalRequests,\n       COALESCE(SUM(UnitsRequested), 0) AS UnitsRequested\nFROM Blood_Request\nWHERE RequestDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)\nGROUP BY Status\nORDER BY TotalRequests DESC",
        'note' => 'Aggregation over last 30 days.',
    ],
];

$flash = pull_flash('flash_error');
$notice = '';
$planResults = [];
$ddlResults = [];
$performanceIndexDetails = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token($_POST['csrf_token'] ?? null, 'query_performance.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'install_indexes' || $action === 'remove_indexes') {
        foreach ($performanceIndexes as $idx) {
            $exists = index_exists($db, $idx['table'], $idx['name']);

            if ($action === 'install_indexes') {
                if ($exists) {
                    $ddlResults[] = ['ok' => true, 'msg' => "Already present: {$idx['name']} on {$idx['table']}"];
                    continue;
                }

                try {
                    if ($db->query($idx['create_sql'])) {
                        $ddlResults[] = ['ok' => true, 'msg' => "Created: {$idx['name']} on {$idx['table']}"];
                    } else {
                        $ddlResults[] = ['ok' => false, 'msg' => "Create failed ({$idx['name']}): " . $db->error];
                    }
                } catch (mysqli_sql_exception $e) {
                    $ddlResults[] = ['ok' => false, 'msg' => "Create failed ({$idx['name']}): " . $e->getMessage()];
                }
            }

            if ($action === 'remove_indexes') {
                if (!$exists) {
                    $ddlResults[] = ['ok' => true, 'msg' => "Already absent: {$idx['name']} on {$idx['table']}"];
                    continue;
                }

                try {
                    if ($db->query($idx['drop_sql'])) {
                        $ddlResults[] = ['ok' => true, 'msg' => "Dropped: {$idx['name']} on {$idx['table']}"];
                    } else {
                        $ddlResults[] = ['ok' => false, 'msg' => "Drop failed ({$idx['name']}): " . $db->error];
                    }
                } catch (mysqli_sql_exception $e) {
                    $ddlResults[] = ['ok' => false, 'msg' => "Drop failed ({$idx['name']}): " . $e->getMessage()];
                }
            }
        }

        $notice = $action === 'install_indexes'
            ? 'Performance indexes applied.'
            : 'Performance indexes removed.';
    }

    if ($action === 'run_plans') {
        foreach ($queries as $title => $q) {
            $planResults[$title] = run_explain($db, $q['sql']);
        }
        $notice = 'Plans generated. Scroll down to see the EXPLAIN output for each query.';
    }
}

$performanceIndexDetails = fetch_performance_index_details($db);

function pill(bool $ok): string
{
    $bg = $ok ? 'rgba(16,185,129,.12)' : 'rgba(244,63,94,.12)';
    $fg = $ok ? '#065f46' : '#9f1239';

    return 'style="display:inline-flex;align-items:center;gap:8px;padding:7px 10px;border-radius:999px;'
        . 'border:1px solid rgba(26,10,13,.08);background:' . $bg . ';color:' . $fg . ';font-size:.72rem;'
        . 'letter-spacing:.1em;text-transform:uppercase"';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Query Performance â€” Indexing & Query Processing</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{
      --cream:#faf7f4;--cream-dark:#f0ebe5;--black:#1a0a0d;--red:#b5121f;
      --gray:#7a6065;--gray-light:#c9b8bc;--serif:'Cormorant Garamond',Georgia,serif;--sans:'DM Sans',sans-serif;
    }
    body{font-family:var(--sans);background:var(--cream);color:var(--black);padding:48px 24px}
    .wrap{max-width:1120px;margin:0 auto}
    .top{display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap;margin-bottom:24px}
    h1{font-family:var(--serif);font-size:clamp(2.1rem,4vw,3.2rem);line-height:1}
    h1 em{color:var(--red);font-style:italic}
    .sub{max-width:620px;color:var(--gray);line-height:1.7}
    .card{background:#fff;border:1px solid var(--gray-light);border-radius:22px;box-shadow:0 10px 30px rgba(26,10,13,.04);padding:22px;margin-top:16px}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--black);padding:12px 16px;border-radius:999px;background:var(--black);color:var(--cream);text-decoration:none;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}
    .btn.secondary{background:transparent;color:var(--black);border-color:var(--gray-light)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:14px 12px;border-bottom:1px solid #eee7e2;text-align:left;vertical-align:top}
    th{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray);background:#fffaf7}
    code,pre{background:rgba(26,10,13,.06);border-radius:12px}
    code{padding:2px 6px}
    pre{padding:14px;overflow:auto;white-space:pre-wrap;line-height:1.5}
    .msg{margin-top:12px;color:#991b1b}
    .notice{margin-top:12px;color:#0f5132}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:12px}
    .idx{display:flex;flex-direction:column;gap:10px}
    .idx-item{display:flex;justify-content:space-between;gap:10px;align-items:start;padding:12px;border:1px solid rgba(26,10,13,.08);border-radius:16px;background:var(--cream)}
    .idx-item .meta{display:flex;flex-direction:column;gap:6px}
    .idx-item .name{font-family:var(--serif);font-size:1.15rem}
    .idx-item .purpose{color:var(--gray);font-size:.92rem;line-height:1.5}
    @media (max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1>Query <em>Performance</em> â€” Indexing & Query Processing</h1>
        <p class="sub">
          Demonstrates how indexes change query plans using <code>EXPLAIN</code> / <code>EXPLAIN ANALYZE</code>.
          This project runs on MariaDB (XAMPP). If <code>EXPLAIN ANALYZE</code> is unavailable, the page falls back to
          <code>EXPLAIN FORMAT=JSON</code> or plain <code>EXPLAIN</code>.
        </p>
      </div>
      <div class="row">
        <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
      </div>
    </div>

    <?php if ($flash !== ''): ?>
      <div class="card"><div class="msg"><?= h($flash) ?></div></div>
    <?php endif; ?>

    <?php if ($notice !== ''): ?>
      <div class="card"><div class="notice"><?= h($notice) ?></div></div>
    <?php endif; ?>

    <div class="card">
      <div class="grid">
        <div>
          <h2 style="font-family:var(--serif);margin-bottom:8px">Performance Indexes</h2>
          <p class="sub" style="margin-bottom:10px">
            These managed indexes support dashboard filters, request queues, and hospital lookups.
          </p>
          <form method="POST" class="row" style="margin-bottom:12px">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button class="btn" type="submit" name="action" value="install_indexes">Install Performance Indexes</button>
            <button class="btn secondary" type="submit" name="action" value="remove_indexes">Remove Performance Indexes</button>
          </form>

          <?php if ($ddlResults): ?>
            <div style="display:flex;flex-direction:column;gap:8px">
              <?php foreach ($ddlResults as $r): ?>
                <div <?= pill((bool) $r['ok']) ?>><?= h((string) $r['msg']) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <h3 style="margin-top:16px;font-size:.82rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray)">Index Evidence (DB)</h3>
          <?php if (!$performanceIndexDetails): ?>
            <p class="sub">No managed performance indexes are currently present in the database.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>Table</th>
                  <th>Index</th>
                  <th>Seq</th>
                  <th>Column</th>
                  <th>Unique?</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($performanceIndexDetails as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['TableName'] ?? '')) ?></td>
                    <td><?= h((string) ($row['IndexName'] ?? '')) ?></td>
                    <td><?= (int) ($row['SeqInIndex'] ?? 0) ?></td>
                    <td><?= h((string) ($row['ColumnName'] ?? '')) ?></td>
                    <td><?= ((int) ($row['NonUnique'] ?? 1)) === 0 ? 'Yes' : 'No' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="idx">
          <?php foreach ($performanceIndexes as $idx): ?>
            <?php $present = index_exists($db, $idx['table'], $idx['name']); ?>
            <div class="idx-item">
              <div class="meta">
                <div class="name"><?= h($idx['name']) ?></div>
                <div class="purpose"><?= h($idx['table'] . ' â€” ' . $idx['purpose']) ?></div>
              </div>
              <div <?= pill($present) ?>><?= $present ? 'Present' : 'Missing' ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 style="font-family:var(--serif);margin-bottom:8px">Generate Query Plans</h2>
      <p class="sub" style="margin-bottom:10px">
        Use this console to compare query plans before and after applying the managed performance indexes.
      </p>
      <form method="POST" class="row">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button class="btn" type="submit" name="action" value="run_plans">Run EXPLAIN for All Queries</button>
      </form>
    </div>

    <?php foreach ($queries as $title => $q): ?>
      <?php $res = $planResults[$title] ?? null; ?>
      <div class="card">
        <h2 style="font-family:var(--serif);margin-bottom:6px"><?= h($title) ?></h2>
        <p class="sub"><?= h($q['note']) ?></p>
        <h3 style="margin-top:14px;font-size:.82rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray)">SQL</h3>
        <pre><?= h($q['sql']) ?></pre>

        <h3 style="margin-top:14px;font-size:.82rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray)">Plan</h3>
        <?php if (!$res): ?>
          <p class="sub">Click â€œRun EXPLAIN for All Queriesâ€ to generate the plan.</p>
        <?php elseif ($res['kind'] === 'text'): ?>
          <div class="sub" style="margin-bottom:8px">Method: <code><?= h($res['label']) ?></code></div>
          <pre><?= h($res['value']) ?></pre>
        <?php elseif ($res['kind'] === 'table'): ?>
          <div class="sub" style="margin-bottom:8px">Method: <code><?= h($res['label']) ?></code></div>
          <table>
            <thead>
              <tr>
                <?php foreach (($res['fields'] ?? []) as $f): ?>
                  <th><?= h((string) $f) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($res['rows'] ?? []) as $row): ?>
                <tr>
                  <?php foreach (($res['fields'] ?? []) as $f): ?>
                    <td><?= h((string) ($row[$f] ?? '')) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="msg">EXPLAIN failed: <?= h((string) ($res['value'] ?? '')) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
