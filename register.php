<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $course = sanitize($_POST['course']);
    $pledge = isset($_POST['honesty_pledge']);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$pledge) {
        $error = 'You must sign the honesty pledge to register';
    } else {
        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $email_taken = $result->num_rows > 0;
        $stmt->close();
        
        if ($email_taken) {
            $error = 'Email already registered';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $pledge_signed = 1;
            $pledge_date = date('Y-m-d H:i:s');
            
            // Removed year_level from the INSERT query
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, course, honesty_pledge_signed, pledge_signed_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $full_name, $email, $hashed_password, $phone, $course, $pledge_signed, $pledge_date);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $success = 'Registration successful! You can now login.';
                $stmt->close();
                logActivity($new_user_id, 'register', 'New user registered');
            } else {
                $error = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --m9: #2b040d; --m8: #4a0715; --maroon: #800000; --mag: #d6249f; --hot: #ff2bd6; --blush: #fff1f7; --paper: #fffaf6; --ink: #2a0a14; --muted: #7a5b66; --ease: cubic-bezier(.2, .8, .2, 1); }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;
            font-family: 'Instrument Sans', system-ui, sans-serif; color: var(--ink);
            background: radial-gradient(800px 500px at 90% 0%, rgba(214,36,159,.5), transparent 60%), radial-gradient(700px 500px at 0% 100%, rgba(128,0,0,.8), transparent 60%), linear-gradient(160deg, var(--m9), var(--m8));
        }
        body::before { content: ''; position: fixed; inset: 0; opacity: .06; pointer-events: none; background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 44px 44px; }
        :focus-visible { outline: 3px solid var(--hot); outline-offset: 2px; }

        .shell { position: relative; display: grid; grid-template-columns: 5fr 7fr; width: 100%; max-width: 900px; border-radius: 22px; overflow: hidden; background: var(--paper); box-shadow: 0 30px 80px rgba(0,0,0,.5); animation: rise .8s var(--ease) both; }
        @keyframes rise { from { opacity: 0; transform: translateY(24px) scale(.98); } to { opacity: 1; transform: none; } }

        .side { padding: 2.2rem 2rem 1.6rem; color: #fff; display: flex; flex-direction: column; background: radial-gradient(400px 300px at 100% 0%, rgba(255,43,214,.4), transparent 70%), linear-gradient(170deg, var(--m8), var(--m9)); }
        .brand { font: 800 1.7rem 'Bricolage Grotesque', sans-serif; letter-spacing: -.5px; color: #fff; text-decoration: none; }
        .brand span { color: var(--hot); }
        .school { display: flex; align-items: center; gap: .7rem; margin-top: 1rem; font-size: .7rem; font-weight: 700; letter-spacing: 1px; line-height: 1.3; }
        .school img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.85); }
        .side h2 { font: 800 1.9rem/1.1 'Bricolage Grotesque', sans-serif; letter-spacing: -.8px; margin: 1.8rem 0 .7rem; }
        .side p { color: rgba(255,255,255,.8); font-size: .95rem; line-height: 1.55; margin: 0; }
        .points { list-style: none; padding: 0; margin: 1.3rem 0 0; display: grid; gap: .65rem; font-size: .88rem; }
        .points li { display: flex; gap: .7rem; align-items: center; color: rgba(255,255,255,.88); }
        .points i { width: 30px; height: 30px; flex: none; display: grid; place-items: center; border-radius: 50%; background: rgba(255,255,255,.12); color: #ffd1ef; font-size: .8rem; }

        /* swingable tag */
        .slot { position: relative; height: 170px; margin-top: auto; }
        .slot::before { content: ''; position: absolute; right: 44px; top: -6px; width: 12px; height: 12px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #fff, #e8a9cf 45%, #8a1b5c); z-index: 2; }
        .hang { position: absolute; top: 0; right: 0; width: 100px; transform-origin: 50% 0; cursor: grab; touch-action: none; user-select: none; filter: drop-shadow(0 14px 12px rgba(0,0,0,.4)); }
        .hang::before { content: ''; position: absolute; left: 50%; top: 0; width: 2px; height: 28px; background: #f4c6dd; }
        .hang-card { position: relative; margin-top: 28px; padding: 1.5rem .7rem .7rem; text-align: center; color: var(--ink); background: var(--paper); clip-path: polygon(18% 0, 82% 0, 100% 12%, 100% 100%, 0 100%, 0 12%); }
        .hang-card::before { content: ''; position: absolute; top: 9px; left: 50%; width: 10px; height: 10px; margin-left: -5px; border-radius: 50%; background: var(--m8); }
        .hang-card small { font-size: .55rem; letter-spacing: 1.5px; font-weight: 700; color: var(--muted); }
        .hang-card b { display: block; font: 800 .95rem/1.1 'Bricolage Grotesque', sans-serif; margin: .2rem 0 .5rem; }
        .hang-card i { display: block; height: 20px; background: repeating-linear-gradient(90deg, var(--ink) 0 2px, transparent 2px 4px, var(--ink) 4px 5px, transparent 5px 8px); opacity: .7; }
        .tip { position: absolute; left: 0; bottom: 0; font-size: .75rem; color: rgba(255,255,255,.65); transition: opacity .5s; }
        .tip.gone { opacity: 0; }

        .main { padding: 2.5rem 2.4rem; border-left: 2px dashed #ecc5d8; }
        .main h1 { font: 800 1.8rem 'Bricolage Grotesque', sans-serif; letter-spacing: -.8px; color: var(--m8); margin: 0 0 .3rem; }
        .lead { color: var(--muted); font-size: .92rem; margin: 0 0 1.5rem; }
        .field { position: relative; margin-bottom: .85rem; }
        .field .ico { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #c58aa6; transition: color .2s; pointer-events: none; font-size: .9rem; z-index: 1; }
        .field input { width: 100%; border: 2px solid #f0d3e0; background: #fff; border-radius: 12px; padding: .78rem 1rem .78rem 2.6rem; font: inherit; font-size: .95rem; color: var(--ink); transition: border-color .2s, box-shadow .2s; }
        .field input::placeholder { color: #b794a5; }
        .field input:focus { outline: 0; border-color: var(--mag); box-shadow: 0 0 0 4px rgba(214,36,159,.15); }
        .field:focus-within .ico { color: var(--mag); }
        .field.eyed input { padding-right: 2.8rem; }
        .eye { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #a37a90; width: 36px; height: 36px; border-radius: 50%; }
        .eye:hover { color: var(--maroon); background: var(--blush); }
        .caps { display: none; font-size: .78rem; color: var(--maroon); margin: -.4rem 0 .6rem; }
        .caps.on { display: block; animation: rise .3s var(--ease); }
        .remember { display: flex; gap: .5rem; align-items: center; font-size: .88rem; color: var(--muted); margin: .2rem 0 1.1rem; cursor: pointer; }
        .remember input { accent-color: var(--mag); width: 16px; height: 16px; }
        .go { width: 100%; border: 0; border-radius: 50px; padding: .85rem 1rem; font: 700 1rem inherit; color: #fff; background: linear-gradient(135deg, var(--maroon), var(--mag)); transition: transform .2s var(--ease), box-shadow .2s; }
        .go:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(214,36,159,.4); }
        .go.loading { pointer-events: none; opacity: .85; }
        .go.loading::after { content: ''; display: inline-block; width: 14px; height: 14px; margin-left: .6rem; vertical-align: -2px; border: 2px solid rgba(255,255,255,.5); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert-lf { display: flex; gap: .6rem; align-items: flex-start; background: #fde8ee; color: #8a1030; border: 1px solid #f5b8ca; border-radius: 12px; padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; animation: shake .45s var(--ease); }
        @keyframes shake { 20%, 60% { transform: translateX(-5px); } 40%, 80% { transform: translateX(5px); } }
        .links { text-align: center; font-size: .88rem; color: var(--muted); margin-top: 1.6rem; }
        .links a { color: var(--maroon); font-weight: 700; text-decoration: none; }
        .links a:hover { color: var(--mag); text-decoration: underline; }


        .shell.wide { max-width: 980px; }
        select { appearance: none; -webkit-appearance: none; cursor: pointer; width: 100%; border: 2px solid #f0d3e0; background: #fff; border-radius: 12px; padding: .78rem 1rem .78rem 2.6rem; font: inherit; font-size: .95rem; color: var(--ink); transition: border-color .2s, box-shadow .2s; }
        select:focus { outline: 0; border-color: var(--mag); box-shadow: 0 0 0 4px rgba(214,36,159,.15); }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
        .field .tick { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%) scale(0); color: #1f9d6b; font-size: .9rem; transition: transform .3s var(--ease); }
        .field.ok .tick { transform: translateY(-50%) scale(1); }
        .field.ok input { border-color: #aee0c8; }
        .progress { margin: 0 0 1.3rem; }
        .progress .bar { height: 6px; border-radius: 6px; background: #f0d3e0; overflow: hidden; }
        .progress .bar i { display: block; height: 100%; width: 0; border-radius: 6px; background: linear-gradient(90deg, var(--maroon), var(--hot)); transition: width .5s var(--ease); }
        .progress small { display: block; margin-top: .4rem; font-size: .76rem; color: var(--muted); }
        .meter { height: 5px; border-radius: 5px; background: #f0d3e0; overflow: hidden; margin: -.35rem 0 .3rem; }
        .meter i { display: block; height: 100%; width: 0; border-radius: 5px; background: var(--maroon); transition: width .4s var(--ease), background .3s; }
        .hint { font-size: .78rem; color: var(--muted); min-height: 1.1rem; margin-bottom: .6rem; }
        .hint.ok { color: #1f9d6b; } .hint.bad { color: var(--maroon); }
        .pledge { background: var(--blush); border: 2px dashed #ecc5d8; border-radius: 14px; padding: .9rem 1rem; margin: 1rem 0; font-size: .85rem; color: var(--muted); }
        .pledge label { color: var(--m8); font-weight: 700; display: flex; gap: .55rem; align-items: center; cursor: pointer; margin-bottom: .3rem; }
        .pledge input { accent-color: var(--mag); width: 18px; height: 18px; }
        .done { text-align: center; padding: 1rem 0; }
        .done .check { width: 72px; height: 72px; margin: 0 auto 1rem; border-radius: 50%; display: grid; place-items: center; font-size: 1.9rem; color: #fff; background: linear-gradient(135deg, #1f9d6b, #34c38f); animation: pop .6s var(--ease) both; }
        @keyframes pop { from { transform: scale(0) rotate(-40deg); } to { transform: none; } }
        .alert-ok { background: #e6f6ef; color: #146c4a; border-color: #aee0c8; animation: none; }
        a.go { display: block; text-align: center; text-decoration: none; }
        @media (max-width: 767px) { .shell.wide { max-width: 460px; } .row2 { grid-template-columns: 1fr; gap: 0; } }
        @media (max-width: 767px) {
            .shell { grid-template-columns: 1fr; max-width: 460px; }
            .side { padding: 1.4rem; } .side h2, .side p, .points, .slot { display: none; }
            .main { padding: 1.8rem 1.4rem; border-left: 0; border-top: 2px dashed #ecc5d8; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
    <main class="shell wide">
        <aside class="side">
            <a class="brand" href="index.php"><i class="fas fa-search-location me-2"></i>iFind<span>Peace</span></a>
            <div class="school"><img src="assets/images/msu-logo.jpg" alt="MSU Logo"><div>MINDANAO STATE UNIVERSITY<br>GENERAL SANTOS</div></div>
            <h2>Join the community.</h2>
            <p>One account lets you report lost items, post what you found, and follow every claim.</p>
            <ul class="points">
                <li><i class="fas fa-pen"></i>Report lost or found items in a minute</li>
                <li><i class="fas fa-handshake"></i>Built on honesty and accountability</li>
                <li><i class="fas fa-shield-heart"></i>Safe drop-off at the Organization Office</li>
            </ul>
            <div class="slot" id="slot" aria-hidden="true">
                <div class="hang" id="hang"><div class="hang-card"><small>NEW MEMBER</small><b>You're in</b><i></i></div></div>
                <div class="tip" id="tip"><i class="fas fa-hand-pointer me-1"></i>Swing the tag</div>
            </div>
        </aside>

        <section class="main">
            <h1>Create account</h1>
            <p class="lead">It takes less than a minute.</p>

            <?php if ($error): ?>
                <div class="alert-lf" role="alert"><i class="fas fa-circle-exclamation mt-1"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="done">
                    <div class="check"><i class="fas fa-check"></i></div>
                    <div class="alert-lf alert-ok" role="status" style="justify-content: center;"><span><?php echo htmlspecialchars($success); ?></span></div>
                </div>
                <a href="login.php" class="go">Proceed to login</a>
            <?php else: ?>
                <div class="progress" aria-live="polite">
                    <div class="bar"><i id="pbar"></i></div>
                    <small id="ptxt">0 of 4 required steps done</small>
                </div>

                <form method="POST" id="regForm">
                    <div class="field" data-req><i class="fas fa-user ico"></i>
                        <input type="text" name="full_name" required placeholder="Full name" autocomplete="name" aria-label="Full name"><i class="fas fa-circle-check tick"></i></div>
                    <div class="field" data-req><i class="fas fa-envelope ico"></i>
                        <input type="email" name="email" required placeholder="Email address" autocomplete="email" aria-label="Email address"><i class="fas fa-circle-check tick"></i></div>

                    <div class="row2">
                        <div class="field eyed"><i class="fas fa-lock ico"></i>
                            <input type="password" name="password" id="pw" required placeholder="Password (min 6)" autocomplete="new-password" aria-label="Password">
                            <button type="button" class="eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                        <div class="field eyed"><i class="fas fa-lock ico"></i>
                            <input type="password" name="confirm_password" id="pw2" required placeholder="Confirm password" autocomplete="new-password" aria-label="Confirm password">
                            <button type="button" class="eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                    </div>
                    <div class="meter" aria-hidden="true"><i id="meterBar"></i></div>
                    <div class="hint" id="pwHint" aria-live="polite"></div>

                    <div class="row2">
                        <div class="field"><i class="fas fa-phone ico"></i>
                            <input type="text" name="phone" placeholder="Phone (optional)" autocomplete="tel" aria-label="Phone"></div>
                        <div class="field"><i class="fas fa-graduation-cap ico"></i>
                            <select name="course" aria-label="Course">
                                <option value="">Course (optional)</option>
                                <option value="BSED">BSED</option>
                                <option value="BEED">BEED</option>
                                <option value="BPED">BPED</option>
                            </select>
                        </div>
                    </div>

                    <div class="pledge">
                        <label for="honestyPledge">
                            <input type="checkbox" name="honesty_pledge" id="honestyPledge" required>
                            <span><i class="fas fa-handshake me-1" style="color: var(--mag);"></i>I agree to be honest</span>
                        </label>
                        I will report truthfully, not claim others' items, and follow platform rules.
                    </div>
                    <button type="submit" class="go">Create account</button>
                </form>

                <div class="links">Already have an account? <a href="login.php">Login</a>
                    <div class="mt-2"><a href="index.php"><i class="fas fa-arrow-left me-1"></i>Back to home</a></div></div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.querySelectorAll('.eye').forEach(function (b) {
            b.onclick = function () {
                var input = b.parentElement.querySelector('input'), show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                b.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
                b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            };
        });

        var form = document.getElementById('regForm');
        if (form) {
            var pw = document.getElementById('pw'), pw2 = document.getElementById('pw2'), pledge = document.getElementById('honestyPledge');
            var bar = document.getElementById('meterBar'), hint = document.getElementById('pwHint');
            var pbar = document.getElementById('pbar'), ptxt = document.getElementById('ptxt');
            var labels = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'], colors = ['#800000', '#c2410c', '#d6a100', '#7fb800', '#1f9d6b'];

            function update() {
                var v = pw.value, s = 0, done = 0;
                if (v.length >= 6) s++;
                if (v.length >= 10) s++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
                if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
                bar.style.width = v ? (s + 1) * 20 + '%' : '0';
                bar.style.background = colors[s];
                var msg = v ? 'Strength: ' + labels[s] : '', cls = '';
                if (pw2.value) { var same = v === pw2.value; msg = same ? 'Passwords match' : 'Passwords do not match'; cls = same ? 'ok' : 'bad'; }
                hint.textContent = msg; hint.className = 'hint ' + cls;

                // live checkmarks and progress (Changed to 4 steps since year level is gone)
                var name = form.full_name, mail = form.email;
                var okName = name.value.trim().length > 1, okMail = mail.validity.valid && mail.value !== '';
                name.parentElement.classList.toggle('ok', okName);
                mail.parentElement.classList.toggle('ok', okMail);
                var okPw = v.length >= 6, okMatch = okPw && v === pw2.value;
                [okName, okMail, okPw, okMatch, pledge.checked].forEach(function (b) { if (b) done++; });
                pbar.style.width = done * 20 + '%';
                ptxt.textContent = done === 5 ? 'All set. Ready to create your account.' : done + ' of 5 required steps done';
            }
            form.addEventListener('input', update); pledge.addEventListener('change', update);
            form.onsubmit = function () { form.querySelector('.go').classList.add('loading'); };
        }

        // Swingable tag: pendulum you can grab and throw
        (function () {
            var hang = document.getElementById('hang'), slot = document.getElementById('slot'), tip = document.getElementById('tip');
            if (!hang) return;
            var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            var a = reduce ? 0 : .7, v = 0, drag = false, last = performance.now();
            function pivot() { var r = slot.getBoundingClientRect(); return { x: r.right - 50, y: r.top }; }
            hang.addEventListener('pointerdown', function (e) { drag = true; v = 0; hang.setPointerCapture(e.pointerId); tip.classList.add('gone'); });
            hang.addEventListener('pointermove', function (e) {
                if (!drag) return;
                var p = pivot(), n = Math.max(-2.4, Math.min(2.4, Math.atan2(e.clientX - p.x, e.clientY - p.y)));
                v = (n - a) * 50; a = n;
            });
            hang.addEventListener('pointerup', function () { drag = false; });
            hang.addEventListener('pointercancel', function () { drag = false; });
            (function loop(t) {
                requestAnimationFrame(loop);
                var dt = Math.min((t - last) / 1000, 1 / 30); last = t;
                if (!drag) {
                    var wind = reduce ? 0 : .6 * Math.sin(t / 1200);
                    v += (-7 * Math.sin(a) - .6 * v + wind) * dt; a += v * dt;
                }
                hang.style.transform = 'rotate(' + (-a) + 'rad)';
            })(last);
        })();
    </script>
</body>
</html>