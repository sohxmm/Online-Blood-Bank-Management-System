<?php
// login.php — Bloodline Donor Login
session_start();
// If already logged in, go straight to dashboard
if (isset($_SESSION['donor_id'])) {
    header('Location: dashboard.php');
    exit;
}
$error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error']), ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Bloodline</title>
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

.reg-hero{padding:130px 48px 70px;background:var(--cream);border-bottom:1px solid rgba(181,18,31,.1);display:flex;justify-content:space-between;align-items:flex-end;transition:background .4s;}
.reg-hero h1{font-family:var(--serif);font-size:clamp(3rem,7vw,7rem);font-weight:300;line-height:1.0;color:var(--black);}
.reg-hero h1 em{font-style:italic;color:var(--red);}
.reg-hero-right{max-width:320px;text-align:right;}
.reg-hero-right p{font-size:.9rem;color:var(--gray);line-height:1.7;}

.reg-body{max-width:640px;margin:0 auto;padding:72px 48px 120px;}

.sec-div{display:flex;align-items:center;gap:20px;margin:0 0 36px;}
.sec-div-line{flex:1;height:1px;background:rgba(181,18,31,.15);}
.sec-div-label{font-size:.67rem;letter-spacing:.28em;text-transform:uppercase;color:var(--red);white-space:nowrap;}

.field{margin-bottom:38px;position:relative;}
.field label{display:block;font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--gray);margin-bottom:9px;}
.field input{width:100%;background:transparent;border:none;border-bottom:1.5px solid var(--gray-light);padding:10px 0;font-family:var(--serif);font-size:1.25rem;color:var(--black);outline:none;transition:border-color .3s;}
.field input:focus{border-color:var(--black);}
.field input::placeholder{color:var(--gray-light);font-size:1.1rem;}
.field input.err{border-color:var(--red);}
.field input.ok{border-color:#22c55e;}
.field-msg{font-size:.7rem;color:var(--red);margin-top:5px;min-height:14px;display:block;}

.form-actions{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;margin-top:48px;}
.submit-btn{display:flex;align-items:center;gap:14px;background:none;border:none;cursor:none;font-family:var(--sans);font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--black);padding:0;}
.submit-btn:disabled{pointer-events:none;opacity:.4;}
.submit-circle{width:52px;height:52px;border:1.5px solid var(--black);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .3s;}
.submit-btn:hover:not(:disabled) .submit-circle{background:var(--black);color:var(--cream);transform:rotate(45deg);}

.register-link{font-size:.8rem;color:var(--gray);display:flex;align-items:center;gap:8px;text-decoration:none;cursor:none;transition:color .2s;}
.register-link:hover{color:var(--black);}
.register-link-arrow{width:28px;height:28px;border:1px solid var(--gray-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .3s;}
.register-link:hover .register-link-arrow{border-color:var(--black);transform:rotate(45deg);}

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
  <a href="index.html" class="nav-back">
    <div class="nav-back-arrow">←</div>
    Back to Home
  </a>
</nav>

<div class="reg-hero">
  <div><h1>Welcome<br><em>Back</em></h1></div>
  <div class="reg-hero-right">
    <p>Sign in to your Bloodline donor account to view your profile, donation history, and contribution record.</p>
  </div>
</div>

<div class="reg-body">

<?php if ($error): ?>
<div style="background:rgba(181,18,31,.06);border:1.5px solid rgba(181,18,31,.4);border-radius:10px;padding:18px 28px;margin-bottom:32px;display:flex;align-items:flex-start;gap:14px;">
  <div style="width:32px;height:32px;border-radius:50%;background:#B5121F;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-size:.9rem;margin-top:2px;">!</div>
  <div>
    <div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:1.15rem;color:#B5121F;margin-bottom:3px;">Sign in failed</div>
    <div style="font-size:.8rem;color:#7f1d1d;line-height:1.7;"><?= $error ?></div>
  </div>
</div>
<?php endif; ?>

<form id="loginForm" method="POST" action="login_handler.php" novalidate>

  <div class="sec-div">
    <div class="sec-div-line"></div>
    <div class="sec-div-label">Your Credentials</div>
    <div class="sec-div-line"></div>
  </div>

  <div class="field">
    <label>Email Address *</label>
    <input type="email" id="email" name="email" placeholder="you@example.com" required
           value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
    <span class="field-msg" id="msg-email"></span>
  </div>

  <div class="field">
    <label>Password *</label>
    <input type="password" id="password" name="password" placeholder="Your password" required>
    <span class="field-msg" id="msg-password"></span>
  </div>

  <div class="form-actions">
    <button type="submit" class="submit-btn" id="submitBtn">
      <div class="submit-circle">→</div>
      Sign In
    </button>
    <a href="registration.php" class="register-link">
      New donor? Register
      <div class="register-link-arrow">↗</div>
    </a>
  </div>

</form>
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
document.querySelectorAll('a,button,input').forEach(e=>{
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

function setE(id,m){const e=document.getElementById(id);e?.classList.add('err');e?.classList.remove('ok');const ms=document.getElementById('msg-'+id);if(ms)ms.textContent=m;}
function setO(id){const e=document.getElementById(id);e?.classList.remove('err');e?.classList.add('ok');const ms=document.getElementById('msg-'+id);if(ms)ms.textContent='';}

document.getElementById('email')?.addEventListener('blur',function(){/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)?setO('email'):setE('email','Invalid email address');});
document.getElementById('password')?.addEventListener('blur',function(){this.value.length>=8?setO('password'):setE('password','Min 8 characters');});

document.getElementById('loginForm').addEventListener('submit',function(e){
  let valid=true;
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(document.getElementById('email').value)){setE('email','Valid email required');valid=false;}else setO('email');
  if(document.getElementById('password').value.length<8){setE('password','Min 8 characters');valid=false;}else setO('password');
  if(!valid){e.preventDefault();document.querySelector('.err')?.scrollIntoView({behavior:'smooth',block:'center'});return;}
  const btn=document.getElementById('submitBtn');
  btn.disabled=true;
  btn.querySelector('.submit-circle').textContent='…';
});

try{
  if(typeof gsap!=='undefined'){
    gsap.from('.reg-hero h1',{y:50,opacity:0,duration:1.1,ease:'power3.out',delay:.1});
    gsap.from('.field',{y:20,opacity:0,duration:.6,stagger:.08,ease:'power2.out',delay:.2});
  }
}catch(e){}

// Scroll to error banner if present
(function(){
  if(new URLSearchParams(window.location.search).has('error')){
    const banner=document.querySelector('[style*="border-radius:10px"]');
    if(banner) setTimeout(()=>banner.scrollIntoView({behavior:'smooth',block:'center'}),300);
  }
})();
</script>
</body>
</html>
