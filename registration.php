<?php
session_start();

$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$loggedIn  = isset($_SESSION['donor_id']);
$donorName = $_SESSION['donor_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Donor — Bloodline</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
  --cream:#FAF7F4; --cream-dark:#F0EBE5;
  --black:#1A0A0D; --red:#B5121F;
  --gray:#7A6065; --gray-light:#C9B8BC;
  --serif:'Cormorant Garamond',Georgia,serif;
  --sans:'DM Sans',sans-serif;
}
body{font-family:var(--sans);background:var(--cream);color:var(--black);cursor:none;overflow-x:hidden;}
body.dark-mode{
  --cream:#1E0E12;--cream-dark:#280E14;
  --black:#FAF0F2;--gray:#B89098;--gray-light:#5A3040;
}

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
.nav-back{display:flex;align-items:center;gap:8px;text-decoration:none;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:var(--gray);cursor:none;transition:color .2s;}
.nav-back:hover{color:var(--black);}
.nav-back-arrow{width:30px;height:30px;border:1px solid var(--gray-light);border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all .3s;}
.nav-back:hover .nav-back-arrow{border-color:var(--black);transform:rotate(-45deg);}

/* HERO */
.reg-hero{padding:130px 48px 70px;background:var(--cream);border-bottom:1px solid rgba(181,18,31,.1);display:flex;justify-content:space-between;align-items:flex-end;transition:background .4s;}
.reg-hero h1{font-family:var(--serif);font-size:clamp(3rem,7vw,7rem);font-weight:300;line-height:1.0;color:var(--black);}
.reg-hero h1 em{font-style:italic;color:var(--red);}
.reg-hero-right{max-width:320px;text-align:right;}
.reg-hero-right p{font-size:.9rem;color:var(--gray);line-height:1.7;}

/* FORM BODY */
.reg-body{max-width:1100px;margin:0 auto;padding:72px 48px 120px;}

/* Section divider */
.sec-div{display:flex;align-items:center;gap:20px;margin:56px 0 36px;}
.sec-div-line{flex:1;height:1px;background:rgba(181,18,31,.15);}
.sec-div-label{font-size:.67rem;letter-spacing:.28em;text-transform:uppercase;color:var(--red);white-space:nowrap;font-family:var(--sans);}

/* Grid */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:0 56px;}
.fg.three{grid-template-columns:1fr 1fr 1fr;}
.field{margin-bottom:38px;position:relative;}
.field.full{grid-column:span 2;}
.field.full3{grid-column:span 3;}
.field label{display:block;font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--gray);margin-bottom:9px;}

/* Inputs */
.field input:not([type=checkbox]):not([type=radio]),
.field select,
.field textarea{
  width:100%;background:transparent;
  border:none;border-bottom:1.5px solid var(--gray-light);
  padding:10px 0;font-family:var(--serif);font-size:1.25rem;
  color:var(--black);outline:none;transition:border-color .3s;
  -webkit-appearance:none;resize:none;
}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--black);}
.field input::placeholder,.field textarea::placeholder{color:var(--gray-light);font-size:1.1rem;}
.field select option{background:var(--cream);}
body.dark-mode .field select option{background:#1E0E12;}
.field input.err{border-color:var(--red);}
.field input.ok,.field select.ok{border-color:#22c55e;}
.field-msg{font-size:.7rem;color:var(--red);margin-top:5px;min-height:14px;display:block;}

/* Yes/No rows */
.yn-table{width:100%;border-collapse:collapse;margin-bottom:8px;}
.yn-table th{font-size:.63rem;letter-spacing:.18em;text-transform:uppercase;color:var(--gray);padding:0 0 10px;text-align:left;font-weight:500;}
.yn-table th:last-child,.yn-table th:nth-last-child(2){text-align:center;width:60px;}
.yn-table td{padding:14px 0;border-bottom:1px solid rgba(181,18,31,.08);font-family:var(--serif);font-size:1.05rem;color:var(--black);line-height:1.4;}
.yn-table tr:last-child td{border-bottom:none;}
.yn-table td:last-child,.yn-table td:nth-last-child(2){text-align:center;width:60px;}
.yn-radio{display:none;}
.yn-label{
  width:28px;height:28px;border-radius:50%;
  border:1.5px solid var(--gray-light);
  display:inline-flex;align-items:center;justify-content:center;
  cursor:none;transition:all .2s;font-size:.7rem;
}
.yn-radio:checked + .yn-label{background:var(--black);border-color:var(--black);color:var(--cream);}
.yn-radio.yes-r:checked + .yn-label{background:#22c55e;border-color:#22c55e;color:#fff;}
.yn-radio.no-r:checked + .yn-label{background:var(--red);border-color:var(--red);color:#fff;}
.yn-label:hover{border-color:var(--black);}

/* Checkbox grid for conditions */
.check-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;}
.check-item{display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid rgba(181,18,31,.06);cursor:none;}
.check-item:hover .check-box{border-color:var(--black);}
.check-box{width:20px;height:20px;border-radius:4px;border:1.5px solid var(--gray-light);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .2s;font-size:.65rem;}
.check-box.checked{background:var(--black);border-color:var(--black);color:var(--cream);}
.check-real{display:none;}
.check-text{font-size:.85rem;color:var(--black);line-height:1.3;}

/* Consent */
.consent-block{
  background:rgba(181,18,31,.04);
  border:1px solid rgba(181,18,31,.15);
  border-radius:12px;
  padding:28px 32px;
  margin:48px 0 40px;
}
.consent-block p{font-size:.83rem;color:var(--gray);line-height:1.8;}
.consent-check{display:flex;align-items:flex-start;gap:14px;margin-top:20px;cursor:none;}
.consent-box{width:22px;height:22px;border-radius:5px;border:1.5px solid var(--gray-light);flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.consent-box.checked{background:var(--red);border-color:var(--red);color:#fff;font-size:.75rem;}
.consent-text-label{font-size:.82rem;color:var(--black);line-height:1.7;}

/* Submit */
.form-actions{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;}
.submit-btn{display:flex;align-items:center;gap:14px;background:none;border:none;cursor:none;font-family:var(--sans);font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--black);padding:0;}
.submit-btn:disabled{pointer-events:none;opacity:.4;}
.submit-circle{width:52px;height:52px;border:1.5px solid var(--black);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .3s;}
.submit-btn:hover:not(:disabled) .submit-circle{background:var(--black);color:var(--cream);transform:rotate(45deg);}
.login-link{font-size:.8rem;color:var(--gray);display:flex;align-items:center;gap:8px;text-decoration:none;cursor:none;transition:color .2s;}
.login-link:hover{color:var(--black);}
.login-link-arrow{width:28px;height:28px;border:1px solid var(--gray-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .3s;}
.login-link:hover .login-link-arrow{border-color:var(--black);transform:rotate(45deg);}

/* PW strength */
.pw-bar-wrap{height:2px;margin-top:8px;border-radius:2px;background:var(--gray-light);overflow:hidden;}
.pw-bar{height:100%;width:0;transition:width .4s,background .4s;border-radius:2px;}
.pw-lbl{font-size:.67rem;margin-top:5px;letter-spacing:.08em;}


body.dark-mode .theme-toggle{background:#280E14;border-color:rgba(181,18,31,.4);}

/* Theme toggle */
.theme-toggle{position:fixed;bottom:32px;right:32px;z-index:9000;width:44px;height:44px;border-radius:50%;border:1px solid rgba(181,18,31,.2);background:rgba(250,247,244,.9);backdrop-filter:blur(8px);cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 16px rgba(181,18,31,.1);transition:border-color .3s,background .4s,transform .3s;}
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
  <a href="index.html" class="nav-back">
    <div class="nav-back-arrow">←</div>
    Back to Home
  </a>
</nav>

<div class="reg-hero">
  <div><h1>Register</h1></div>
  <div class="reg-hero-right">
    <p>Join thousands of donors across India. Your information is secure and your contribution saves lives.</p>
  </div>
</div>

<div class="reg-body">
<form id="donorForm" method="POST" action="register.php" novalidate>

<?php if ($success): ?>
<div style="
  background:rgba(34,197,94,.08);
  border:1.5px solid rgba(34,197,94,.5);
  border-radius:10px;
  padding:18px 28px;
  margin-bottom:32px;
  display:flex;align-items:center;gap:14px;">
  <div style="width:32px;height:32px;border-radius:50%;background:#22c55e;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-size:.9rem;">✓</div>
  <div>
    <div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:1.15rem;color:#15803d;margin-bottom:3px;">Registration successful!</div>
    <div style="font-size:.78rem;color:#166534;letter-spacing:.03em;">Welcome to Bloodline. Your donor profile has been created.</div>
  </div>
</div>
<?php elseif ($error): ?>
<div style="
  background:rgba(181,18,31,.06);
  border:1.5px solid rgba(181,18,31,.4);
  border-radius:10px;
  padding:18px 28px;
  margin-bottom:32px;
  display:flex;align-items:flex-start;gap:14px;">
  <div style="width:32px;height:32px;border-radius:50%;background:#B5121F;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-size:.9rem;margin-top:2px;">!</div>
  <div>
    <div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:1.15rem;color:#B5121F;margin-bottom:3px;">Please fix the following</div>
    <div style="font-size:.8rem;color:#7f1d1d;line-height:1.7;"><?= $error ?></div>
  </div>
</div>
<?php endif; ?>

  <!-- ── PERSONAL INFO ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Personal Information</div><div class="sec-div-line"></div></div>
  <div class="fg">
    <div class="field">
      <label>Full Name *</label>
      <input type="text" id="fullname" name="fullname" placeholder="Your full name" required minlength="3">
      <span class="field-msg" id="msg-fullname"></span>
    </div>
    <div class="field">
      <label>Date of Birth * (min. age 18)</label>
      <input type="date" id="dob" name="dob" required>
      <span class="field-msg" id="msg-dob"></span>
    </div>
    <div class="field">
      <label>Sex *</label>
      <select id="sex" name="sex" required>
        <option value="">Select</option>
        <option value="M">Male</option>
        <option value="F">Female</option>
        <option value="Other">Other</option>
        <option value="Other">Prefer not to mention</option>
      </select>
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>Marital Status</label>
      <select id="marital" name="marital">
        <option value="">Select</option>
        <option>Single</option>
        <option>Married</option>
        <option>Divorced</option>
        <option>Widowed</option>
        <option>Prefer not to mention</option>
      </select>
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>Occupation</label>
      <input type="text" id="occupation" name="occupation" placeholder="Your occupation">
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>Aadhaar Card Number *</label>
      <input type="text" id="aadhar" name="aadhar" placeholder="12-digit Aadhaar number" maxlength="12">
      <span class="field-msg" id="msg-aadhar"></span>
    </div>
    <div class="field">
      <label>Email Address *</label>
      <input type="email" id="email" name="email" placeholder="you@example.com" required>
      <span class="field-msg" id="msg-email"></span>
    </div>
    <div class="field">
      <label>Phone Number *</label>
      <input type="tel" id="phone" name="phone" placeholder="10-digit number" maxlength="10">
      <span class="field-msg" id="msg-phone"></span>
    </div>
    <div class="field full">
      <label>Address</label>
      <textarea id="address" name="address" rows="2" placeholder="Your full address"></textarea>
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>City</label>
      <input type="text" id="city" name="city" placeholder="Mumbai">
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>PIN Code</label>
      <input type="text" id="pincode" name="pincode" placeholder="6-digit PIN" maxlength="6">
      <span class="field-msg" id="msg-pincode"></span>
    </div>
    <div class="field">
      <label>Emergency Contact *</label>
      <input type="tel" id="emergencyContact" name="emergencyContact" placeholder="10-digit number" maxlength="10">
      <span class="field-msg" id="msg-emergency"></span>
    </div>
  </div>

  <!-- ── DONATION DETAILS ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Donation Details</div><div class="sec-div-line"></div></div>
  <div class="fg">
    <div class="field">
      <label>Blood Group *</label>
      <select id="bloodGroup" name="bloodGroup" required>
        <option value="">Select blood group</option>
        <option>A+</option><option>A-</option>
        <option>B+</option><option>B-</option>
        <option>O+</option><option>O-</option>
        <option>AB+</option><option>AB-</option>
      </select>
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>Units Willing to Donate (1–5)</label>
      <input type="number" id="units" name="units" placeholder="1 – 5" min="1" max="5">
      <span class="field-msg" id="msg-units"></span>
    </div>
    <div class="field">
      <label>Last Donation Date</label>
      <input type="date" id="lastDonation" name="lastDonation">
      <span class="field-msg" id="msg-lastDonation"></span>
    </div>
  </div>

  <!-- ── ACCOUNT SECURITY ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Account Security</div><div class="sec-div-line"></div></div>
  <div class="fg">
    <div class="field">
      <label>Password *</label>
      <input type="password" id="password" name="password" placeholder="Min 8 characters" required minlength="8">
      <div class="pw-bar-wrap"><div class="pw-bar" id="pwBar"></div></div>
      <div class="pw-lbl" id="pwLbl"></div>
      <span class="field-msg" id="msg-password"></span>
    </div>
    <div class="field">
      <label>Confirm Password *</label>
      <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Repeat password" required>
      <span class="field-msg" id="msg-confirmPassword"></span>
    </div>
  </div>

  <!-- ── MEDICAL HISTORY ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Medical History</div><div class="sec-div-line"></div></div>
  <div class="fg">
    <div class="field">
      <label>Primary Care Physician's Name</label>
      <input type="text" name="physicianName" placeholder="Dr. Full Name">
      <span class="field-msg"></span>
    </div>
    <div class="field">
      <label>Physician Phone Number</label>
      <input type="tel" name="physicianPhone" placeholder="10-digit number" maxlength="10">
      <span class="field-msg"></span>
    </div>
  </div>

  <!-- Yes/No questions -->
  <table class="yn-table">
    <thead><tr>
      <th style="width:100%">Question</th>
      <th>Yes</th><th>No</th>
    </tr></thead>
    <tbody id="ynBody"></tbody>
  </table>

  <div class="field" style="margin-top:20px;">
    <label>If you answered Yes to any of the above, please explain:</label>
    <textarea name="ynExplain" rows="3" placeholder="Please provide details..."></textarea>
  </div>

  <!-- ── ALLERGIES ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Allergies</div><div class="sec-div-line"></div></div>
  <p style="font-size:.82rem;color:var(--gray);margin-bottom:24px;">Are you allergic to any of the following?</p>
  <table class="yn-table" id="allergyTable">
    <thead><tr><th style="width:100%">Substance</th><th>Yes</th><th>No</th></tr></thead>
    <tbody id="allergyBody"></tbody>
  </table>
  <div class="field" style="margin-top:20px;">
    <label>If Other — please list:</label>
    <input type="text" name="allergyOther" placeholder="Other allergies...">
  </div>

  <!-- ── CONDITIONS ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Medical Conditions</div><div class="sec-div-line"></div></div>
  <p style="font-size:.82rem;color:var(--gray);margin-bottom:24px;">Please check any conditions you currently or previously have had:</p>
  <div class="check-grid" id="conditionsGrid"></div>
  <div class="field" style="margin-top:24px;">
    <label>If Other — please list:</label>
    <input type="text" name="condOther" placeholder="Other conditions...">
  </div>
  <div class="field">
    <label>List any major illness not listed above:</label>
    <textarea name="majorIllness" rows="2" placeholder="Any other major illness..."></textarea>
  </div>

  <!-- ── CONSENT ── -->
  <div class="sec-div"><div class="sec-div-line"></div><div class="sec-div-label">Consent & Declaration</div><div class="sec-div-line"></div></div>
  <div class="consent-block">
    <p>I certify that I have read and understood the above information to the best of my knowledge and that all responses provided are accurate. I understand that providing incorrect or incomplete information may affect the safety of blood donation or transfusion. I consent to the necessary screening, testing, and processing of blood as required. I acknowledge that all procedures are carried out following standard medical guidelines. I agree to comply with all instructions provided by the blood bank and accept responsibility for my participation in the process. I also consent to the use of my information for record-keeping, safety monitoring, and regulatory purposes, in accordance with applicable guidelines.</p>
    <div class="consent-check" id="consentCheck">
      <div class="consent-box" id="consentBox"></div>
      <input type="checkbox" id="consent" name="consent" required style="display:none;">
      <div class="consent-text-label">I have read and agree to the above declaration.</div>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="submit-btn" id="submitBtn">
      <div class="submit-circle">→</div>
      Register as Donor
    </button>
    <a href="login.php" class="login-link">
      Already registered? Sign in
      <div class="login-link-arrow">↗</div>
    </a>
  </div>

</form>
</div><!-- /reg-body -->

<button class="theme-toggle" id="themeToggle">
  <div class="theme-toggle-icon">
    <div class="tog-sun"></div>
    <div class="tog-moon"></div>
  </div>
</button>

<script src="gsap.min.js"></script>
<script>
/* ── CURSOR ── */
const cDot=document.getElementById('cDot'),cRing=document.getElementById('cRing');
let mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;});
(function loop(){cDot.style.left=mx+'px';cDot.style.top=my+'px';rx+=(mx-rx)*.12;ry+=(my-ry)*.12;cRing.style.left=rx+'px';cRing.style.top=ry+'px';requestAnimationFrame(loop);})();
function addHover(els){els.forEach(e=>{e.addEventListener('mouseenter',()=>{cDot.classList.add('hov');cRing.classList.add('hov');});e.addEventListener('mouseleave',()=>{cDot.classList.remove('hov');cRing.classList.remove('hov');});});}
addHover(document.querySelectorAll('a,button,input,select,textarea,.yn-label,.check-item,.consent-check'));

/* ── THEME ── */
const tBtn=document.getElementById('themeToggle');
let dark=false;
if(dark){document.body.classList.add('dark-mode');tBtn.textContent='🌙';}
const regLogo=document.getElementById('pageLogo');
tBtn.addEventListener('click',()=>{dark=!dark;document.body.classList.toggle('dark-mode',dark);if(regLogo)regLogo.src=dark?'assets/logo-dark.png':'assets/logo-light.png';});

/* ── YES/NO TABLE BUILDER ── */
const YN_QUESTIONS = [
  "Are you under a physician's care?",
  "Have you ever been hospitalized or had a major operation?",
  "Women: Are you pregnant, trying to get pregnant, or nursing?",
  "Do you use controlled substances?",
  "Do you use tobacco?",
  "Are you on a special diet?"
];
const ynBody = document.getElementById('ynBody');
YN_QUESTIONS.forEach((q,i)=>{
  const key = 'yn_'+i;
  ynBody.innerHTML += `<tr>
    <td>${q}</td>
    <td>
      <input type="radio" class="yn-radio yes-r" name="${key}" id="${key}_y" value="yes">
      <label for="${key}_y" class="yn-label" title="Yes">Y</label>
    </td>
    <td>
      <input type="radio" class="yn-radio no-r" name="${key}" id="${key}_n" value="no">
      <label for="${key}_n" class="yn-label" title="No">N</label>
    </td>
  </tr>`;
});

/* ── ALLERGIES ── */
const ALLERGIES = [
  "Antibiotics or Sulfa drugs","Anticoagulants (blood thinners)",
  "Insulin / diabetes medication","Heart medications","Aspirin",
  "High blood pressure medicine","Nitroglycerine","Tranquilizers",
  "Herbal supplements","Contraceptives","Other"
];
const allergyBody = document.getElementById('allergyBody');
ALLERGIES.forEach((a,i)=>{
  const key='allergy_'+i;
  allergyBody.innerHTML+=`<tr>
    <td>${a}</td>
    <td><input type="radio" class="yn-radio yes-r" name="${key}" id="${key}_y" value="yes"><label for="${key}_y" class="yn-label">Y</label></td>
    <td><input type="radio" class="yn-radio no-r" name="${key}" id="${key}_n" value="no"><label for="${key}_n" class="yn-label">N</label></td>
  </tr>`;
});

/* ── CONDITIONS CHECKBOXES ── */
const CONDITIONS = [
  "HIV/AIDS","Hepatitis B/C","Anemia","Blood disease",
  "Cancer/Chemotherapy","Kidney disease/Dialysis","Liver disease/Jaundice",
  "Heart disease/Irregular heartbeat","Stroke","Tuberculosis",
  "Drug addiction","Diabetes","High blood pressure","Low blood pressure",
  "Asthma/Lung disease","Epilepsy/Seizures","Excessive bleeding",
  "Recent weight loss","Arthritis/Gout","Thyroid disease",
  "Osteoporosis","Psychiatric care","Frequent headaches",
  "Allergies (hives/rash)","Other"
];
const condGrid = document.getElementById('conditionsGrid');
CONDITIONS.forEach((c,i)=>{
  const id='cond_'+i;
  const item=document.createElement('div');
  item.className='check-item';
  item.innerHTML=`<input type="checkbox" class="check-real" name="${id}" id="${id}"><div class="check-box" id="cb_${i}"></div><label for="${id}" class="check-text" style="cursor:none;">${c}</label>`;
  item.addEventListener('click',()=>{
    const inp=item.querySelector('.check-real');
    inp.checked=!inp.checked;
    item.querySelector('.check-box').classList.toggle('checked',inp.checked);
    item.querySelector('.check-box').textContent=inp.checked?'✓':'';
  });
  condGrid.appendChild(item);
});

/* ── CUSTOM CONSENT CHECKBOX ── */
const consentCheck=document.getElementById('consentCheck');
const consentBox=document.getElementById('consentBox');
const consentInput=document.getElementById('consent');
consentCheck.addEventListener('click',()=>{
  consentInput.checked=!consentInput.checked;
  consentBox.classList.toggle('checked',consentInput.checked);
  consentBox.textContent=consentInput.checked?'✓':'';
});

/* ── AUTO-CLEAN PHONE FIELDS ── */
['phone','emergencyContact','physicianPhone'].forEach(id=>{
  document.getElementById(id)?.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'');});
});
document.getElementById('aadhar')?.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,12);});
document.getElementById('pincode')?.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,6);});
document.getElementById('confirmPassword')?.addEventListener('paste',e=>e.preventDefault());

/* ── PASSWORD STRENGTH ── */
document.getElementById('password')?.addEventListener('input',function(){
  const v=this.value,bar=document.getElementById('pwBar'),lbl=document.getElementById('pwLbl');
  if(!v){bar.style.width='0';lbl.textContent='';return;}
  let s=0;
  if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
  const cols=['','#ef4444','#f97316','#22c55e','#16a34a'];
  const labs=['','Weak','Fair','Strong','Very Strong'];
  bar.style.width=(s*25)+'%';bar.style.background=cols[s];
  lbl.textContent=labs[s];lbl.style.color=cols[s];
});

/* ── INLINE VALIDATION ── */
function setE(id,m){const e=document.getElementById(id);e?.classList.add('err');e?.classList.remove('ok');const ms=document.getElementById('msg-'+id);if(ms)ms.textContent=m;}
function setO(id){const e=document.getElementById(id);e?.classList.remove('err');e?.classList.add('ok');const ms=document.getElementById('msg-'+id);if(ms)ms.textContent='';}

document.getElementById('fullname')?.addEventListener('blur',function(){this.value.trim().length>=3?setO('fullname'):setE('fullname','At least 3 characters required');});
document.getElementById('email')?.addEventListener('blur',function(){/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)?setO('email'):setE('email','Invalid email address');});
document.getElementById('phone')?.addEventListener('blur',function(){/^[0-9]{10}$/.test(this.value)?setO('phone'):setE('phone','Must be 10 digits');});
document.getElementById('aadhar')?.addEventListener('blur',function(){/^[0-9]{12}$/.test(this.value)?setO('aadhar'):setE('aadhar','Must be exactly 12 digits');});
document.getElementById('pincode')?.addEventListener('blur',function(){if(!this.value)return;/^[0-9]{6}$/.test(this.value)?setO('pincode'):setE('pincode','Must be 6 digits');});
document.getElementById('emergencyContact')?.addEventListener('blur',function(){/^[0-9]{10}$/.test(this.value)?setO('emergency'):setE('emergency','Must be 10 digits');});
document.getElementById('units')?.addEventListener('blur',function(){if(!this.value)return;(this.value>=1&&this.value<=5)?setO('units'):setE('units','Between 1 and 5');});
document.getElementById('confirmPassword')?.addEventListener('blur',function(){this.value===document.getElementById('password').value?setO('confirmPassword'):setE('confirmPassword','Passwords do not match');});
document.getElementById('lastDonation')?.addEventListener('blur',function(){
  if(!this.value)return;
  const diff=(new Date()-new Date(this.value))/(1000*60*60*24);
  diff>=90?setO('lastDonation'):setE('lastDonation','Must be at least 90 days ago');
});
document.getElementById('dob')?.addEventListener('blur',function(){
  if(!this.value){setE('dob','Date of birth required');return;}
  const age=(new Date()-new Date(this.value))/(1000*60*60*24*365.25);
  age>=18?setO('dob'):setE('dob','Must be at least 18 years old');
});

/* ── ENTRANCE ANIMATION ── */
try {
  if(typeof gsap!=='undefined'){
    gsap.from('.reg-hero h1',{y:50,opacity:0,duration:1.1,ease:'power3.out',delay:.1});
    gsap.from('.field',{y:20,opacity:0,duration:.6,stagger:.04,ease:'power2.out',delay:.2});
  }
} catch(e){}

/* ── SCROLL TO BANNER IF REDIRECTED ── */
(function(){
  const params = new URLSearchParams(window.location.search);
  if(params.has('success') || params.has('error')){
    const banner = document.querySelector('[style*="border-radius:10px"]');
    if(banner) setTimeout(()=>banner.scrollIntoView({behavior:'smooth',block:'center'}), 300);
  }
})();

/* ── DISABLE SUBMIT ON SEND (prevent double-submit) ── */
document.getElementById('donorForm').addEventListener('submit', function(e){
  let valid = true;
  const required=[
    {id:'fullname',test:v=>v.trim().length>=3,msg:'Full name required'},
    {id:'email',test:v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),msg:'Valid email required'},
    {id:'phone',test:v=>/^[0-9]{10}$/.test(v),msg:'10-digit phone required'},
    {id:'aadhar',test:v=>/^[0-9]{12}$/.test(v),msg:'12-digit Aadhaar required'},
    {id:'dob',test:v=>{if(!v)return false;return (new Date()-new Date(v))/(1000*60*60*24*365.25)>=18;},msg:'Must be 18+'},
    {id:'sex',test:v=>v!=='',msg:'Please select sex'},
    {id:'bloodGroup',test:v=>v!=='',msg:'Please select blood group'},
    {id:'password',test:v=>v.length>=8,msg:'Min 8 characters'},
    {id:'confirmPassword',test:v=>v===document.getElementById('password').value,msg:'Passwords must match'},
  ];
  required.forEach(r=>{
    const el=document.getElementById(r.id);
    if(!el)return;
    if(!r.test(el.value)){setE(r.id,r.msg);valid=false;}else setO(r.id);
  });
  if(!consentInput.checked){alert('Please read and accept the declaration to proceed.');valid=false;}
  if(!valid){
    e.preventDefault();
    document.querySelector('.err')?.scrollIntoView({behavior:'smooth',block:'center'});
    return;
  }
  /* Valid — disable button to prevent double submit */
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.querySelector('.submit-circle').textContent = '…';
});
</script>
</body>
</html>
