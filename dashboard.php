<?php
// dashboard.php — Donor Dashboard
// Protected: only accessible after login

session_start();
if (!isset($_SESSION['donor_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
$db = getDB();

$donorID = (int)$_SESSION['donor_id'];

// Fetch full donor details
$stmt = $db->prepare(
    'SELECT d.FName, d.MName, d.LName, d.DOB, d.Sex, d.Email,
            d.BloodGroup, d.City, d.Pincode, d.EmergencyContact,
            d.LastDonationDate, d.UnitsLastDonated, d.RegisteredAt,
            dc.PhoneNo
     FROM   Donor d
     LEFT JOIN Donor_Contact dc ON dc.DonorID = d.DonorID
     WHERE  d.DonorID = ?
     LIMIT  1'
);
$stmt->bind_param('i', $donorID);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donor) {
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired. Please sign in again.'));
    exit;
}

// Check Regular or First-Time donor
$stmtR = $db->prepare('SELECT TotalDonations, LastDonationDate FROM Regular_Donor WHERE DonorID = ? LIMIT 1');
$stmtR->bind_param('i', $donorID);
$stmtR->execute();
$regular = $stmtR->get_result()->fetch_assoc();
$stmtR->close();

// Fetch blood requests submitted under this donor's name
$stmtReq = $db->prepare(
    'SELECT BloodGroup, UnitsRequested, Urgency, Status, RequestDate
     FROM   Blood_Request
     WHERE  RequesterPhone = (
         SELECT PhoneNo FROM Donor_Contact WHERE DonorID = ? LIMIT 1
     )
     ORDER  BY RequestDate DESC
     LIMIT  5'
);
$stmtReq->bind_param('i', $donorID);
$stmtReq->execute();
$requests = $stmtReq->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtReq->close();

// Helper
function fmt(?string $date): string {
    if (!$date) return '—';
    return date('d M Y', strtotime($date));
}
$fullName  = trim($donor['FName'] . ' ' . ($donor['MName'] ? $donor['MName'] . ' ' : '') . $donor['LName']);
$donorType = $regular ? 'Regular Donor' : 'First-Time Donor';
$totalDons = $regular ? $regular['TotalDonations'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Bloodline</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
  --cream:#FAF7F4;--cream-dark:#F0EBE5;
  --black:#1A0A0D;--red:#B5121F;
  --gray:#7A6065;--gray-light:#C9B8BC;
  --serif:'Cormorant Garamond',Georgia,serif;
  --sans:'DM Sans',sans-serif;
}
body{font-family:var(--sans);background:var(--cream);color:var(--black);cursor:none;overflow-x:hidden;}
body.dark-mode{--cream:#1E0E12;--cream-dark:#280E14;--black:#FAF0F2;--gray:#B89098;--gray-light:#5A3040;}

.cursor-dot{width:8px;height:8px;background:var(--black);border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:99999;transform:translate(-50%,-50%);transition:width .2s,height .2s,background .2s;}
.cursor-ring{width:36px;height:36px;border:1.5px solid var(--black);border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .35s,height .35s,border-color .2s;}
.cursor-dot.hov{width:12px;height:12px;background:var(--red);}
.cursor-ring.hov{width:56px;height:56px;border-color:var(--red);}

/* NAV */
nav{position:fixed;top:0;left:0;right:0;height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;z-index:1000;background:rgba(250,247,244,.97);backdrop-filter:blur(12px);border-bottom:1px solid rgba(181,18,31,.08);transition:background .4s;}
body.dark-mode nav{background:rgba(30,14,18,.97);}
.nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--black);cursor:none;}
.nav-logo img{width:38px;height:38px;object-fit:contain;}
.nav-logo-text{font-family:var(--serif);font-size:1rem;line-height:1.2;color:var(--black);}
.nav-logo-text em{font-style:italic;color:var(--red);}
.nav-right{display:flex;align-items:center;gap:24px;}
.nav-logout{display:flex;align-items:center;gap:8px;text-decoration:none;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:var(--gray);cursor:none;transition:color .2s;}
.nav-logout:hover{color:var(--red);}
.nav-logout-arrow{width:30px;height:30px;border:1px solid var(--gray-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .3s;}
.nav-logout:hover .nav-logout-arrow{border-color:var(--red);color:var(--red);}

/* HERO */
.dash-hero{padding:130px 48px 60px;background:var(--cream);border-bottom:1px solid rgba(181,18,31,.1);display:flex;justify-content:space-between;align-items:flex-end;transition:background .4s;}
.dash-hero h1{font-family:var(--serif);font-size:clamp(2.5rem,5vw,5rem);font-weight:300;line-height:1.0;color:var(--black);}
.dash-hero h1 em{font-style:italic;color:var(--red);}
.dash-hero-right{text-align:right;}
.donor-type-badge{display:inline-flex;align-items:center;gap:8px;font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;color:var(--gray);border:1px solid var(--gray-light);padding:6px 16px;border-radius:100px;}
.donor-type-badge span{width:7px;height:7px;border-radius:50%;background:var(--red);display:inline-block;}

/* BODY */
.dash-body{max-width:1100px;margin:0 auto;padding:72px 48px 120px;}

/* Stat cards row */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--gray-light);border:1px solid var(--gray-light);margin-bottom:64px;}
.stat-card{background:var(--cream);padding:36px 28px;transition:background .3s;}
body.dark-mode .stat-card{background:var(--cream);}
.stat-card-val{font-family:var(--serif);font-size:2.8rem;font-weight:300;color:var(--black);line-height:1;margin-bottom:8px;}
.stat-card-val.red{color:var(--red);}
.stat-card-lbl{font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--gray);}

/* Section divider */
.sec-div{display:flex;align-items:center;gap:20px;margin:56px 0 36px;}
.sec-div-line{flex:1;height:1px;background:rgba(181,18,31,.15);}
.sec-div-label{font-size:.67rem;letter-spacing:.28em;text-transform:uppercase;color:var(--red);white-space:nowrap;}

/* Detail grid */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 56px;}
.detail-item{padding:20px 0;border-bottom:1px solid rgba(181,18,31,.08);}
.detail-item:last-child,.detail-item:nth-last-child(2){border-bottom:none;}
.detail-lbl{font-size:.63rem;letter-spacing:.2em;text-transform:uppercase;color:var(--gray);margin-bottom:6px;}
.detail-val{font-family:var(--serif);font-size:1.2rem;color:var(--black);}

/* Requests table */
.req-table{width:100%;border-collapse:collapse;margin-top:8px;}
.req-table th{font-size:.63rem;letter-spacing:.18em;text-transform:uppercase;color:var(--gray);padding:12px 0;text-align:left;border-bottom:1px solid rgba(181,18,31,.15);}
.req-table td{padding:16px 0;font-family:var(--serif);font-size:1rem;color:var(--black);border-bottom:1px solid rgba(181,18,31,.06);}
.req-table tr:last-child td{border-bottom:none;}
.status-pill{display:inline-block;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;padding:3px 10px;border-radius:100px;font-family:var(--sans);}
.status-pill.Pending{background:rgba(181,18,31,.08);color:var(--red);}
.status-pill.Fulfilled{background:rgba(34,197,94,.1);color:#15803d;}
.status-pill.Cancelled{background:rgba(100,100,100,.1);color:var(--gray);}

.empty-note{font-size:.85rem;color:var(--gray);font-style:italic;padding:24px 0;}

/* Actions row */
.action-row{display:flex;gap:24px;flex-wrap:wrap;margin-top:64px;padding-top:40px;border-top:1px solid rgba(181,18,31,.1);}
.action-link{display:flex;align-items:center;gap:10px;text-decoration:none;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:var(--black);cursor:none;transition:color .2s;}
.action-link:hover{color:var(--red);}
.action-link-circle{width:40px;height:40px;border:1.5px solid var(--gray-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;transition:all .3s;}
.action-link:hover .action-link-circle{border-color:var(--red);color:var(--red);transform:rotate(45deg);}

/* Theme toggle */
.theme-toggle{position:fixed;bottom:32px;right:32px;z-index:9000;width:44px;height:44px;border-radius:50%;border:1px solid rgba(181,18,31,.2);background:rgba(250,247,244,.9);backdrop-filter:blur(8px);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:border-color .3s,background .4s,transform .3s;}
.theme-toggle:hover{border-color:#B5121F;transform:scale(1.08);}
.theme-toggle-icon{width:18px;height:18px;position:relative;transition:transform .5s ease;}
.tog-sun{width:18px;height:18px;border-radius:50%;border:1.5px solid #1A0A0D;position:absolute;top:0;left:0;transition:all .4s;}
.tog-sun::before{content:'';position:absolute;top:50%;left:50%;width:26px;height:26px;margin:-13px;border-radius:50%;box-shadow:0 -10px 0 -7px #1A0A0D,0 10px 0 -7px #1A0A0D,-10px 0 0 -7px #1A0A0D,10px 0 0 -7px #1A0A0D,-7px -7px 0 -7px #1A0A0D,7px -7px 0 -7px #1A0A0D,-7px 7px 0 -7px #1A0A0D,7px 7px 0 -7px #1A0A0D;transition:all .4s;}
.tog-moon{width:18px;height:18px;border-radius:50%;background:rgba(250,247,244,.9);position:absolute;top:-3px;left:5px;transform:scale(0);transition:transform .4s,background .4s;}
body.dark-mode .theme-toggle{background:rgba(26,10,13,.9);border-color:rgba(181,18,31,.3);}
body.dark-mode .tog-sun{border-color:#FAF0F2;}
body.dark-mode .tog-sun::before{box-shadow:none;opacity:0;}
body.dark-mode .tog-moon{background:rgba(26,10,13,.9);transform:scale(1);}
body.dark-mode .theme-toggle-icon{transform:rotate(25deg);}
</style>
</head>
<body>

<div class="cursor-dot" id="cDot"></div>
<div class="cursor-ring" id="cRing"></div>

<nav>
  <a href="index.html" class="nav-logo">
    <img src="assets/logo-light.png" alt="Bloodline" id="pageLogo" onerror="this.style.display='none'">
    <div class="nav-logo-text">Online <em>Blood Bank</em><br>Management</div>
  </a>
  <div class="nav-right">
    <a href="logout.php" class="nav-logout">
      <div class="nav-logout-arrow">×</div>
      Sign Out
    </a>
  </div>
</nav>

<!-- HERO -->
<div class="dash-hero">
  <div>
    <h1>Hello,<br><em><?= htmlspecialchars($donor['FName']) ?></em></h1>
  </div>
  <div class="dash-hero-right">
    <div class="donor-type-badge">
      <span></span>
      <?= htmlspecialchars($donorType) ?>
    </div>
  </div>
</div>

<div class="dash-body">

  <!-- STAT CARDS -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-card-val red"><?= htmlspecialchars($donor['BloodGroup']) ?></div>
      <div class="stat-card-lbl">Blood Group</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-val"><?= $totalDons ?></div>
      <div class="stat-card-lbl">Total Donations</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-val"><?= fmt($donor['LastDonationDate']) ?></div>
      <div class="stat-card-lbl">Last Donated</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-val"><?= fmt($donor['RegisteredAt']) ?></div>
      <div class="stat-card-lbl">Member Since</div>
    </div>
  </div>

  <!-- PERSONAL DETAILS -->
  <div class="sec-div">
    <div class="sec-div-line"></div>
    <div class="sec-div-label">Personal Details</div>
    <div class="sec-div-line"></div>
  </div>

  <div class="detail-grid">
    <div class="detail-item">
      <div class="detail-lbl">Full Name</div>
      <div class="detail-val"><?= htmlspecialchars($fullName) ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Email Address</div>
      <div class="detail-val"><?= htmlspecialchars($donor['Email']) ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Phone Number</div>
      <div class="detail-val"><?= htmlspecialchars($donor['PhoneNo'] ?? '—') ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Date of Birth</div>
      <div class="detail-val"><?= fmt($donor['DOB']) ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Sex</div>
      <div class="detail-val"><?= $donor['Sex'] === 'M' ? 'Male' : ($donor['Sex'] === 'F' ? 'Female' : 'Other') ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">City</div>
      <div class="detail-val"><?= htmlspecialchars($donor['City'] ?: '—') ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Emergency Contact</div>
      <div class="detail-val"><?= htmlspecialchars($donor['EmergencyContact'] ?: '—') ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-lbl">Units Last Donated</div>
      <div class="detail-val"><?= (int)$donor['UnitsLastDonated'] ?: '—' ?></div>
    </div>
  </div>

  <!-- RECENT REQUESTS -->
  <div class="sec-div">
    <div class="sec-div-line"></div>
    <div class="sec-div-label">Recent Blood Requests</div>
    <div class="sec-div-line"></div>
  </div>

  <?php if (empty($requests)): ?>
    <p class="empty-note">No blood requests found under your phone number.</p>
  <?php else: ?>
    <table class="req-table">
      <thead>
        <tr>
          <th>Blood Type</th>
          <th>Units</th>
          <th>Urgency</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $req): ?>
        <tr>
          <td><?= htmlspecialchars($req['BloodGroup']) ?></td>
          <td><?= (int)$req['UnitsRequested'] ?></td>
          <td><?= htmlspecialchars($req['Urgency']) ?></td>
          <td><?= fmt($req['RequestDate']) ?></td>
          <td><span class="status-pill <?= htmlspecialchars($req['Status']) ?>"><?= htmlspecialchars($req['Status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- QUICK ACTIONS -->
  <div class="action-row">
    <a href="make-request.html" class="action-link">
      <div class="action-link-circle">↗</div>
      Make a Blood Request
    </a>
    <a href="inventory.html" class="action-link">
      <div class="action-link-circle">↗</div>
      View Live Inventory
    </a>
    <a href="contact.php" class="action-link">
      <div class="action-link-circle">↗</div>
      Contact Us
    </a>
  </div>

</div>

<button class="theme-toggle" id="themeToggle">
  <div class="theme-toggle-icon">
    <div class="tog-sun"></div>
    <div class="tog-moon"></div>
  </div>
</button>

<script src="gsap.min.js"></script>
<script>
const cDot=document.getElementById('cDot'),cRing=document.getElementById('cRing');
let mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;});
(function loop(){cDot.style.left=mx+'px';cDot.style.top=my+'px';rx+=(mx-rx)*.12;ry+=(my-ry)*.12;cRing.style.left=rx+'px';cRing.style.top=ry+'px';requestAnimationFrame(loop);})();
document.querySelectorAll('a,button').forEach(e=>{
  e.addEventListener('mouseenter',()=>{cDot.classList.add('hov');cRing.classList.add('hov');});
  e.addEventListener('mouseleave',()=>{cDot.classList.remove('hov');cRing.classList.remove('hov');});
});

const tBtn=document.getElementById('themeToggle');
const pageLogo=document.getElementById('pageLogo');
let dark=false;
tBtn.addEventListener('click',()=>{
  dark=!dark;
  document.body.classList.toggle('dark-mode',dark);
  if(pageLogo) pageLogo.src=dark?'assets/logo-dark.png':'assets/logo-light.png';
});

try{
  if(typeof gsap!=='undefined'){
    gsap.from('.dash-hero h1',{y:40,opacity:0,duration:1,ease:'power3.out',delay:.1});
    gsap.from('.stat-card',{y:30,opacity:0,duration:.7,stagger:.08,ease:'power2.out',delay:.2});
    gsap.from('.detail-item',{y:20,opacity:0,duration:.5,stagger:.04,ease:'power2.out',delay:.4});
  }
}catch(e){}
</script>
</body>
</html>
