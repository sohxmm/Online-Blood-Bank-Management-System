<?php
require_once 'auth.php';
require_once 'db.php';

require_login();

$db = getDB();
$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['Pending', 'Fulfilled', 'Cancelled'];
$filterActive = in_array($statusFilter, $allowedStatuses, true);

$flashSuccess = pull_flash('flash_success');
$flashError = pull_flash('flash_error');

// READ query 
if ($filterActive) {
    $stmt = $db->prepare(
        'SELECT br.ReqID AS RequestID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status,
                COALESCE(br.ReqDate, br.RequestDate) AS RequestDate,
                COALESCE(br.OwnerName, br.RequesterName) AS RequesterName,
                br.RequesterPhone, br.PatientName,
                h.Name AS HospitalName
         FROM Blood_Request br
         LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
         WHERE br.Status = ?
         ORDER BY br.RequestDate DESC, br.ReqID DESC'
    );
    $stmt->bind_param('s', $statusFilter);
} else {
    $stmt = $db->prepare(
        'SELECT br.ReqID AS RequestID, br.BloodGroup, br.UnitsRequested, br.Urgency, br.Status,
                COALESCE(br.ReqDate, br.RequestDate) AS RequestDate,
                COALESCE(br.OwnerName, br.RequesterName) AS RequesterName,
                br.RequesterPhone, br.PatientName,
                h.Name AS HospitalName
         FROM Blood_Request br
         LEFT JOIN Hospital h ON h.HospitalID = br.HospitalID
         ORDER BY br.RequestDate DESC, br.ReqID DESC'
    );
}

$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$editId = (int) ($_GET['edit'] ?? 0);
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
<title>Manage Requests - Bloodline</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#faf7f4;
  --cream-dark:#f0ebe5;
  --black:#1a0a0d;
  --red:#b5121f;
  --gray:#7a6065;
  --gray-light:#c9b8bc;
  --serif:'Cormorant Garamond', Georgia, serif;
  --sans:'DM Sans', sans-serif;
}
body{font-family:var(--sans);background:var(--cream);color:var(--black);padding:48px 24px}
.wrap{max-width:1200px;margin:0 auto}
.topbar{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:32px}
.brand-mark{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--black);flex-shrink:0}
.brand-mark img{width:48px;height:48px;object-fit:contain}
.brand-copy{font-family:var(--serif);font-size:1rem;line-height:1.15}
.brand-copy em{font-style:italic;color:var(--red)}
.title{font-family:var(--serif);font-size:clamp(2.4rem,5vw,4rem);line-height:1}
.title em{font-style:italic;color:var(--red)}
.sub{max-width:480px;color:var(--gray);line-height:1.7;font-size:.95rem}
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
.link-btn,.action-btn,.danger-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--gray-light);padding:10px 16px;border-radius:999px;background:transparent;color:var(--black);text-decoration:none;font-size:.82rem;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.action-btn{background:var(--black);color:var(--cream);border-color:var(--black)}
.danger-btn{border-color:#d5949b;color:var(--red)}
.filter-card,.table-card,.flash{background:#fff;border:1px solid var(--gray-light);border-radius:24px;box-shadow:0 10px 30px rgba(26,10,13,.04)}
.filter-card{padding:20px 24px;margin-bottom:24px}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px}
.info-card{background:#fffaf7;border:1px solid #eee7e2;border-radius:22px;padding:18px 20px}
.info-kicker{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray);margin-bottom:8px}
.info-title{font-family:var(--serif);font-size:1.45rem;margin-bottom:6px}
.info-copy{font-size:.9rem;line-height:1.7;color:var(--gray)}
.filter-form{display:flex;gap:16px;align-items:end;flex-wrap:wrap}
.field{display:flex;flex-direction:column;gap:8px;min-width:220px}
.field label{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray)}
.field select,.field input{border:1px solid var(--gray-light);border-radius:14px;padding:12px 14px;font:inherit;background:var(--cream)}
.flash{padding:16px 20px;margin-bottom:18px}
.flash.success{border-color:#88c49c;color:#166534}
.flash.error{border-color:#d5949b;color:#991b1b}
.table-card{overflow:auto}
table{width:100%;border-collapse:collapse;min-width:1100px}
th,td{padding:18px 16px;border-bottom:1px solid #eee7e2;text-align:left;vertical-align:top}
th{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:var(--gray);background:#fffaf7}
td{font-size:.94rem}
.pill{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase}
.pill.Pending{background:rgba(181,18,31,.08);color:var(--red)}
.pill.Fulfilled{background:rgba(22,163,74,.1);color:#166534}
.pill.Cancelled{background:rgba(107,114,128,.14);color:#4b5563}
.inline-form{display:grid;gap:10px}
.inline-form input,.inline-form select{width:100%;border:1px solid var(--gray-light);border-radius:12px;padding:10px 12px;font:inherit;background:var(--cream)}
.row-actions{display:flex;gap:10px;flex-wrap:wrap}
.muted{color:var(--gray)}
.empty{padding:26px 16px;color:var(--gray);font-style:italic}
.feed-card{margin-top:24px;padding:24px}
.feed-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px}
.feed-title{font-family:var(--serif);font-size:1.8rem;color:var(--black)}
.feed-sub{font-size:.82rem;line-height:1.7;color:var(--gray)}
.feed-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.feed-item{border:1px solid #eee7e2;border-radius:16px;padding:16px;background:#fffaf7}
.feed-item h4{font-family:var(--serif);font-size:1.2rem;margin-bottom:8px}
.feed-item p{font-size:.82rem;color:var(--gray);line-height:1.6;margin-bottom:2px}
.feed-actions{margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.feed-status{font-size:.78rem;color:var(--gray)}
@media (max-width:768px){
  body{padding:28px 14px}
  .topbar{flex-direction:column;align-items:start}
  .brand-mark{align-self:flex-start}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div>
      <h1 class="title">Manage <em>Blood Requests</em></h1>
      <p class="sub">Transaction-aware request console with inventory protection, rollback support, and an audit trail for Bloodline operations.</p>
      <div class="actions">
        <a class="link-btn" href="make-request.html">Create Request</a>
        <a class="link-btn" href="dashboard.php">Dashboard</a>
      </div>
    </div>
    <a class="brand-mark" href="index.html" aria-label="Bloodline home">
      <img src="assets/logo.png" alt="Bloodline" onerror="this.onerror=null;this.src='assets/logow.png';">
      <div class="brand-copy">Online <em>Blood Bank</em><br>Management</div>
    </a>
  </div>

  <?php if ($flashSuccess !== ''): ?>
    <div class="flash success"><?= h($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError !== ''): ?>
    <div class="flash error"><?= h($flashError) ?></div>
  <?php endif; ?>

  <div class="info-grid">
    <section class="info-card">
      <div class="info-kicker">TCL In Action</div>
      <h2 class="info-title">Transaction-safe fulfillment</h2>
      <p class="info-copy">When a request moves to <strong>Fulfilled</strong>, Bloodline starts a database transaction, locks the request and inventory rows, updates stock, writes an audit log, and only then commits the change.</p>
    </section>
    <section class="info-card">
      <div class="info-kicker">Rollback Protection</div>
      <h2 class="info-title">Inventory stays consistent</h2>
      <p class="info-copy">If stock is insufficient or a delete or update fails midway, the workflow rolls back and keeps <code>Blood_Request</code> and <code>Blood_Inventory</code> synchronized.</p>
    </section>
    <section class="info-card">
      <div class="info-kicker">DCL Context</div>
      <h2 class="info-title">Operator and auditor roles</h2>
      <p class="info-copy">The supporting SQL grants request-processing rights to operators and read-only rights to auditors, matching this project’s hospital request management flow.</p>
    </section>
  </div>

  <div class="filter-card">
    <form class="filter-form" method="GET" action="manage_requests.php">
      <div class="field">
        <label for="status">Filter By Status</label>
        <select name="status" id="status">
          <option value="">All requests</option>
          <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= h($status) ?>"<?= $statusFilter === $status ? ' selected' : '' ?>><?= h($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="action-btn" type="submit">Apply Filter</button>
      <a class="link-btn" href="manage_requests.php">Reset</a>
    </form>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Request ID</th>
          <th>Requester</th>
          <th>Blood Group</th>
          <th>Units</th>
          <th>Urgency</th>
          <th>Status</th>
          <th>Hospital</th>
          <th>Requested On</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($requests)): ?>
          <tr><td class="empty" colspan="9">No requests found for the current filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $request): ?>
          <?php $isEditing = $editId === (int) $request['RequestID']; ?>
          <tr>
            <td>#<?= (int) $request['RequestID'] ?></td>
            <td>
              <strong><?= h((string) $request['RequesterName']) ?></strong><br>
              <span class="muted"><?= h((string) $request['RequesterPhone']) ?></span><br>
              <span class="muted"><?= h((string) ($request['PatientName'] ?: 'Patient not specified')) ?></span>
            </td>
            <?php if ($isEditing): ?>
              <td colspan="6">
                <form class="inline-form" method="POST" action="update_request.php">
                  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="request_id" value="<?= (int) $request['RequestID'] ?>">
                  <input type="hidden" name="status_filter" value="<?= h($statusFilter) ?>">
                  <div class="field">
                    <label for="units_<?= (int) $request['RequestID'] ?>">Units Requested</label>
                    <input id="units_<?= (int) $request['RequestID'] ?>" type="number" min="1" max="20" name="units_requested" value="<?= (int) $request['UnitsRequested'] ?>" required>
                  </div>
                  <div class="field">
                    <label for="urgency_<?= (int) $request['RequestID'] ?>">Urgency</label>
                    <select id="urgency_<?= (int) $request['RequestID'] ?>" name="urgency" required>
                      <?php foreach (['Emergency', 'Urgent', 'Scheduled'] as $urgency): ?>
                        <option value="<?= h($urgency) ?>"<?= $request['Urgency'] === $urgency ? ' selected' : '' ?>><?= h($urgency) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field">
                    <label for="status_edit_<?= (int) $request['RequestID'] ?>">Status</label>
                    <select id="status_edit_<?= (int) $request['RequestID'] ?>" name="status" required>
                      <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= h($status) ?>"<?= $request['Status'] === $status ? ' selected' : '' ?>><?= h($status) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="row-actions">
                    <button class="action-btn" type="submit">Save Update</button>
                    <a class="link-btn" href="manage_requests.php<?= $statusFilter !== '' ? '?status=' . urlencode($statusFilter) : '' ?>">Cancel</a>
                  </div>
                </form>
              </td>
            <?php else: ?>
              <td><?= h((string) $request['BloodGroup']) ?></td>
              <td><?= (int) $request['UnitsRequested'] ?></td>
              <td><?= h((string) $request['Urgency']) ?></td>
              <td><span class="pill <?= h((string) $request['Status']) ?>"><?= h((string) $request['Status']) ?></span></td>
              <td><?= h((string) ($request['HospitalName'] ?: 'Not provided')) ?></td>
              <td><?= h(date('d M Y, h:i A', strtotime((string) $request['RequestDate']))) ?></td>
              <td>
                <div class="row-actions">
                  <a class="link-btn" href="manage_requests.php?edit=<?= (int) $request['RequestID'] ?><?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?>">Edit</a>
                  <form method="POST" action="delete_request.php" onsubmit="return confirm('Delete this blood request permanently?');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="request_id" value="<?= (int) $request['RequestID'] ?>">
                    <input type="hidden" name="status_filter" value="<?= h($statusFilter) ?>">
                    <button class="danger-btn" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="filter-card feed-card">
    <div class="feed-top">
      <div>
        <h2 class="feed-title">Live Request Feed</h2>
        <p class="feed-sub">AJAX demo for incremental loading. Click Load More to append additional request cards without reloading this page.</p>
      </div>
      <div class="feed-status" id="feedStatus">Ready</div>
    </div>
    <div class="feed-list" id="feedList"></div>
    <div class="feed-actions">
      <button class="action-btn" type="button" id="loadMoreFeedBtn">Load More</button>
      <button class="link-btn" type="button" id="refreshFeedBtn">Refresh Feed</button>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
let feedPage = 1;
const feedLimit = 5;
const feedStatusFilter = <?= json_encode($statusFilter, JSON_UNESCAPED_UNICODE) ?>;

function statusPill(status){
  return `<span class="pill ${status}">${status}</span>`;
}

function renderFeedItem(item){
  const date = new Date(item.RequestDate);
  const formattedDate = isNaN(date.getTime()) ? item.RequestDate : date.toLocaleString();
  return `
    <article class="feed-item">
      <h4>#${item.RequestID} ${item.BloodGroup}</h4>
      <p>${statusPill(item.Status)} ${item.Urgency}</p>
      <p>Units: ${item.UnitsRequested}</p>
      <p>Requester: ${item.RequesterName}</p>
      <p>Phone: ${item.RequesterPhone}</p>
      <p>Hospital: ${item.HospitalName || 'Not provided'}</p>
      <p>Requested on: ${formattedDate}</p>
    </article>
  `;
}

function setFeedStatus(message){
  $('#feedStatus').text(message);
}

function loadFeed(reset = false){
  if (reset) {
    feedPage = 1;
    $('#feedList').empty();
  }

  setFeedStatus('Loading...');
  $('#loadMoreFeedBtn').prop('disabled', true);

  $.getJSON('get_requests_feed.php', {
    page: feedPage,
    limit: feedLimit,
    status: feedStatusFilter
  }).done((resp) => {
    if (!resp.success) {
      setFeedStatus('Failed to load feed');
      return;
    }

    if (Array.isArray(resp.items) && resp.items.length > 0) {
      resp.items.forEach((item) => $('#feedList').append(renderFeedItem(item)));
      feedPage += 1;
    }

    $('#loadMoreFeedBtn').prop('disabled', !resp.hasMore);
    setFeedStatus(`Loaded ${$('#feedList .feed-item').length} of ${resp.total} requests`);
  }).fail(() => {
    setFeedStatus('Network error while loading feed');
    if ($('#feedList .feed-item').length > 0) {
      $('#loadMoreFeedBtn').prop('disabled', false);
    }
  });
}

$('#loadMoreFeedBtn').on('click', () => loadFeed(false));
$('#refreshFeedBtn').on('click', () => loadFeed(true));
loadFeed(true);
</script>
</body>
</html>
