<?php
require __DIR__.'/config.php';

$page = $_GET['page'] ?? (logged_in() ? 'dashboard' : 'home');
$errors = [];

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $errors[] = 'Enter your name, a valid email, and a password with at least 8 characters.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users(name,email,phone,password,role) VALUES(?,?,?,?, 'patient')");
                $stmt->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
                flash('Account created. You can now sign in.');
                header('Location: index.php?page=login'); exit;
            } catch (PDOException $e) { $errors[] = 'That email address is already registered.'; }
        }
        $page = 'register';
    }
    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([strtolower(trim($_POST['email'] ?? ''))]);
        $u = $stmt->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
            header('Location: index.php?page=dashboard'); exit;
        }
        $errors[] = 'Incorrect email or password.';
        $page = 'login';
    }
    if ($action === 'book') {
        require_login();
        $doctor = (int)($_POST['doctor_id'] ?? 0);
        $date = $_POST['appointment_date'] ?? '';
        $time = $_POST['appointment_time'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        if (!$doctor || !$date || !$time || $reason === '') {
            $errors[] = 'Please complete all appointment details.';
            $page = 'book';
        } elseif (strtotime($date.' '.$time) < time()) {
            $errors[] = 'Please choose a future date and time.';
            $page = 'book';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status <> 'Cancelled'");
            $check->execute([$doctor,$date,$time]);
            if ($check->fetchColumn()) {
                $errors[] = 'That doctor already has an appointment at this time.';
                $page = 'book';
            } else {
                $stmt = $pdo->prepare("INSERT INTO appointments(patient_id,doctor_id,appointment_date,appointment_time,reason) VALUES(?,?,?,?,?)");
                $stmt->execute([$_SESSION['user']['id'],$doctor,$date,$time,$reason]);
                flash('Appointment request submitted successfully.');
                header('Location: index.php?page=appointments'); exit;
            }
        }
    }
    if ($action === 'cancel') {
        require_login();
        $id = (int)($_POST['appointment_id'] ?? 0);
        if (role() === 'admin') {
            $stmt = $pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND patient_id=? AND status='Pending'");
            $stmt->execute([$id,$_SESSION['user']['id']]);
        }
        flash('Appointment status updated.');
        header('Location: index.php?page=appointments'); exit;
    }
    if ($action === 'status') {
        require_login();
        if (in_array(role(),['doctor','admin'],true)) {
            $stmt = $pdo->prepare("UPDATE appointments SET status=? WHERE id=?");
            $stmt->execute([$_POST['status'] === 'Completed' ? 'Completed' : 'Confirmed',(int)$_POST['appointment_id']]);
            flash('Appointment updated.');
        }
        header('Location: index.php?page=appointments'); exit;
    }
    if ($action === 'record') {
        require_login();
        if (in_array(role(),['doctor','admin'],true)) {
            $stmt = $pdo->prepare("INSERT INTO medical_records(patient_id,doctor_id,appointment_id,diagnosis,prescription,notes) VALUES(?,?,?,?,?,?)");
            $stmt->execute([(int)$_POST['patient_id'],$_SESSION['user']['id'],($_POST['appointment_id'] ?: null),trim($_POST['diagnosis']),trim($_POST['prescription']),trim($_POST['notes'])]);
            flash('Medical record saved.');
        }
        header('Location: index.php?page=records'); exit;
    }
    if ($action === 'add_doctor') {
        require_login();
        if (role() === 'admin') {
            $name=trim($_POST['name']); $email=strtolower(trim($_POST['email']));
            $specialty=trim($_POST['specialty']); $phone=trim($_POST['phone']);
            if (filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($_POST['password'])>=8) {
                try {
                    $pdo->prepare("INSERT INTO users(name,email,phone,password,role,specialty) VALUES(?,?,?,?, 'doctor',?)")
                        ->execute([$name,$email,$phone,password_hash($_POST['password'],PASSWORD_DEFAULT),$specialty]);
                    flash('Doctor account created.');
                } catch(PDOException $e) { flash('Email already exists.','error'); }
            } else flash('Use a valid email and password of at least 8 characters.','error');
        }
        header('Location: index.php?page=doctors'); exit;
    }
}

function page_title(string $page): string {
    return ['home'=>'Welcome','login'=>'Sign in','register'=>'Create account','dashboard'=>'Dashboard','book'=>'Book appointment','appointments'=>'Appointments','doctors'=>'Doctors','records'=>'Medical records'][$page] ?? 'WellCare';
}
function nav_link(string $p,string $label): void {
    echo '<a class="nav-link '.(($_GET['page'] ?? '')===$p?'active':'').'" href="index.php?page='.h($p).'">'.h($label).'</a>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h(page_title($page))?> | WellCare Clinic</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="brand-mark">✚</span> WellCare <small>CLINIC</small></a>
  <button class="menu-toggle" onclick="document.querySelector('.nav').classList.toggle('show')">☰</button>
  <nav class="nav">
    <?php if (logged_in()): ?>
      <?php nav_link('dashboard','Dashboard'); nav_link('appointments','Appointments'); ?>
      <?php if (role()==='patient') nav_link('book','Book a visit'); ?>
      <?php if (in_array(role(),['doctor','admin'],true)) nav_link('records','Medical records'); ?>
      <?php if (role()==='admin') nav_link('doctors','Doctors'); ?>
      <span class="welcome">Hi, <?=h($_SESSION['user']['name'])?></span>
      <a class="nav-link signout" href="index.php?logout=1">Sign out</a>
    <?php else: ?>
      <a class="nav-link" href="index.php?page=login">Sign in</a>
      <a class="btn btn-small" href="index.php?page=register">Create account</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert <?=h($f['type'])?>"><?=h($f['message'])?></div>
<?php endif; ?>
<?php foreach($errors as $error): ?><div class="alert error"><?=h($error)?></div><?php endforeach; ?>

<?php if ($page==='home' && !logged_in()): ?>
<section class="hero">
 <div class="hero-copy"><span class="eyebrow">CARE THAT FITS YOUR LIFE</span><h1>Your health.<br><em>Our priority.</em></h1>
 <p>Book clinic visits, stay connected with your healthcare team, and keep your care information organized in one convenient place.</p>
 <div class="hero-actions"><a class="btn" href="index.php?page=register">Get started</a><a class="btn btn-outline" href="index.php?page=login">Sign in</a></div>
 <div class="trust"><span>✓ Easy appointment booking</span><span>✓ Organized patient records</span><span>✓ Simple clinic management</span></div></div>
 <div class="hero-art"><div class="art-circle"></div><div class="doctor-card"><div class="doctor-icon">⚕</div><h3>Care made simpler</h3><p>Appointments and clinic information, all in one place.</p><div class="mini-line"></div><div class="mini-line short"></div></div><div class="floating plus">+</div><div class="floating heart">♡</div></div>
</section>
<section class="features"><div class="section-heading"><span class="eyebrow">WELLCARE SERVICES</span><h2>One place for better care</h2><p>Helpful tools for patients and clinic staff.</p></div>
<div class="feature-grid"><article class="feature"><div class="feature-icon">▦</div><h3>Online appointments</h3><p>Request a consultation and review your upcoming visits.</p></article><article class="feature"><div class="feature-icon">♡</div><h3>Patient records</h3><p>Keep consultation notes and treatment details organized.</p></article><article class="feature"><div class="feature-icon">✚</div><h3>Clinic management</h3><p>Help authorized staff coordinate schedules and patient care.</p></article></div></section>
<?php elseif ($page==='login' || $page==='register'): ?>
<section class="auth-wrap"><div class="auth-aside"><span class="eyebrow">WELCOME TO WELLCARE</span><h1>Care begins with a simple step.</h1><p>Access your clinic services with a secure account.</p><div class="auth-symbol">✚</div></div>
<div class="auth-card"><h2><?=$page==='login'?'Welcome back':'Create your account'?></h2><p class="muted"><?=$page==='login'?'Sign in to continue to your dashboard.':'Register as a patient to book appointments.'?></p>
<form method="post">
<?=csrf_field()?>
<?php if($page==='register'): ?><input type="hidden" name="action" value="register"><label>Full name</label><input name="name" required placeholder="Your full name"><label>Phone number</label><input name="phone" placeholder="09XX XXX XXXX"><?php else: ?><input type="hidden" name="action" value="login"><?php endif; ?>
<label>Email address</label><input type="email" name="email" required placeholder="you@example.com"><label>Password</label><input type="password" name="password" required minlength="<?=$page==='register'?'8':'1'?>" placeholder="<?=$page==='register'?'At least 8 characters':'Your password'?>">
<button class="btn full" type="submit"><?=$page==='login'?'Sign in':'Create account'?></button></form>
<p class="auth-switch"><?=$page==='login'?'New to WellCare?':'Already have an account?'?> <a href="index.php?page=<?=$page==='login'?'register':'login'?>"><?=$page==='login'?'Create an account':'Sign in'?></a></p></div></section>
<?php elseif ($page==='dashboard'): require_login(); ?>
<div class="page-head"><div><span class="eyebrow">OVERVIEW</span><h1>Your dashboard</h1><p class="muted">Here is what's happening at your clinic.</p></div><?php if(role()==='patient'): ?><a class="btn" href="index.php?page=book">＋ Book appointment</a><?php endif; ?></div>
<?php
if(role()==='patient') {
 $q=$pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=?");$q->execute([$_SESSION['user']['id']]);$total=(int)$q->fetchColumn();
 $q=$pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='Pending'");$q->execute([$_SESSION['user']['id']]);$pending=(int)$q->fetchColumn();
 $q=$pdo->prepare("SELECT COUNT(*) FROM medical_records WHERE patient_id=?");$q->execute([$_SESSION['user']['id']]);$records=(int)$q->fetchColumn();
} else {
 $total=(int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
 $pending=(int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
 $records=(int)$pdo->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
}
?>
<div class="stat-grid"><div class="stat-card"><span class="stat-icon">▦</span><p>Total appointments</p><strong><?=$total?></strong></div><div class="stat-card"><span class="stat-icon amber">◷</span><p>Pending requests</p><strong><?=$pending?></strong></div><div class="stat-card"><span class="stat-icon violet">♡</span><p>Medical records</p><strong><?=$records?></strong></div></div>
<div class="panel"><div class="panel-head"><div><h2>Recent appointments</h2><p class="muted">Your latest clinic activity</p></div><a href="index.php?page=appointments">View all →</a></div>
<?php
if(role()==='patient') {$q=$pdo->prepare("SELECT a.*,d.name doctor_name,d.specialty FROM appointments a JOIN users d ON d.id=a.doctor_id WHERE a.patient_id=? ORDER BY a.appointment_date DESC LIMIT 5");$q->execute([$_SESSION['user']['id']]);}
elseif(role()==='doctor') {$q=$pdo->prepare("SELECT a.*,p.name patient_name FROM appointments a JOIN users p ON p.id=a.patient_id WHERE a.doctor_id=? ORDER BY a.appointment_date DESC LIMIT 5");$q->execute([$_SESSION['user']['id']]);}
else {$q=$pdo->query("SELECT a.*,p.name patient_name,d.name doctor_name FROM appointments a JOIN users p ON p.id=a.patient_id JOIN users d ON d.id=a.doctor_id ORDER BY a.appointment_date DESC LIMIT 5");}
$rows=$q->fetchAll();
if(!$rows) echo '<div class="empty">No appointments yet.</div>'; else {echo '<div class="table-wrap"><table><tr><th>Date</th><th>'.(role()==='patient'?'Doctor':'Patient').'</th><th>Status</th></tr>';foreach($rows as $r){echo '<tr><td>'.h($r['appointment_date']).' · '.h(substr($r['appointment_time'],0,5)).'</td><td>'.h(role()==='patient'?$r['doctor_name']:$r['patient_name']).'</td><td><span class="status">'.h($r['status']).'</span></td></tr>';}echo '</table></div>';}
?>
</div>
<?php elseif ($page==='book'): require_login(); if(role()!=='patient') {echo '<div class="alert error">Only patient accounts can book appointments.</div>';} else { ?>
<div class="page-head"><div><span class="eyebrow">SCHEDULE A VISIT</span><h1>Book an appointment</h1><p class="muted">Choose a doctor and a convenient time.</p></div></div>
<div class="form-card"><form method="post"><input type="hidden" name="action" value="book"><?=csrf_field()?>
<label>Select doctor</label><select name="doctor_id" required><option value="">Choose a doctor</option><?php foreach($pdo->query("SELECT id,name,specialty FROM users WHERE role='doctor' AND active=1 ORDER BY name") as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?><?= $d['specialty']?' — '.h($d['specialty']):'' ?></option><?php endforeach; ?></select>
<div class="form-row"><div><label>Date</label><input type="date" name="appointment_date" min="<?=date('Y-m-d')?>" required></div><div><label>Time</label><input type="time" name="appointment_time" required></div></div>
<label>Reason for consultation</label><textarea name="reason" rows="4" required placeholder="Briefly describe your concern..."></textarea><p class="hint">Please do not use this form for emergencies. Contact local emergency services for urgent care.</p><button class="btn" type="submit">Submit appointment request</button></form></div>
<?php } ?>
<?php elseif ($page==='appointments'): require_login(); ?>
<div class="page-head"><div><span class="eyebrow">SCHEDULE</span><h1>Appointments</h1><p class="muted">Review and manage clinic appointments.</p></div><?php if(role()==='patient'): ?><a class="btn" href="index.php?page=book">＋ New appointment</a><?php endif; ?></div>
<?php
if(role()==='patient') {$q=$pdo->prepare("SELECT a.*,d.name doctor_name,d.specialty FROM appointments a JOIN users d ON d.id=a.doctor_id WHERE a.patient_id=? ORDER BY a.appointment_date DESC");$q->execute([$_SESSION['user']['id']]);}
elseif(role()==='doctor') {$q=$pdo->prepare("SELECT a.*,p.name patient_name FROM appointments a JOIN users p ON p.id=a.patient_id WHERE a.doctor_id=? ORDER BY a.appointment_date DESC");$q->execute([$_SESSION['user']['id']]);}
else {$q=$pdo->query("SELECT a.*,p.name patient_name,d.name doctor_name FROM appointments a JOIN users p ON p.id=a.patient_id JOIN users d ON d.id=a.doctor_id ORDER BY a.appointment_date DESC");}
$rows=$q->fetchAll();
?>
<div class="panel"><div class="table-wrap"><table><tr><th>Date & time</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th>Action</th></tr>
<?php foreach($rows as $r): ?><tr><td><?=h($r['appointment_date'])?><br><small><?=h(substr($r['appointment_time'],0,5))?></small></td><td><?=h($r['patient_name'] ?? $_SESSION['user']['name'])?></td><td><?=h($r['doctor_name'] ?? 'Assigned doctor')?></td><td><?=h($r['reason'])?></td><td><span class="status"><?=h($r['status'])?></span></td><td class="actions">
<?php if(in_array(role(),['doctor','admin'],true) && $r['status']!=='Cancelled'): ?><form method="post" class="inline"><?=csrf_field()?><input type="hidden" name="action" value="status"><input type="hidden" name="appointment_id" value="<?=$r['id']?>"><button class="text-btn" name="status" value="Confirmed">Confirm</button><button class="text-btn" name="status" value="Completed">Complete</button></form><?php elseif(role()==='patient' && $r['status']==='Pending'): ?><form method="post" class="inline" onsubmit="return confirm('Cancel this appointment?')"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="appointment_id" value="<?=$r['id']?>"><button class="text-btn danger-text">Cancel</button></form><?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="empty">No appointments found.</td></tr><?php endif; ?></table></div></div>
<?php elseif ($page==='records'): require_login(); if(!in_array(role(),['doctor','admin'],true)): ?><div class="alert error">You do not have permission to view this page.</div><?php else: ?>
<div class="page-head"><div><span class="eyebrow">PATIENT CARE</span><h1>Medical records</h1><p class="muted">Create and review patient consultation records.</p></div></div>
<div class="form-card"><h2>Add consultation record</h2><form method="post"><input type="hidden" name="action" value="record"><?=csrf_field()?>
<label>Patient</label><select name="patient_id" required><option value="">Select patient</option><?php foreach($pdo->query("SELECT id,name FROM users WHERE role='patient' ORDER BY name") as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?></select>
<label>Related appointment ID (optional)</label><input type="number" name="appointment_id" min="1" placeholder="Appointment ID">
<label>Diagnosis</label><input name="diagnosis" required>
<label>Prescription / treatment</label><textarea name="prescription" rows="2"></textarea>
<label>Clinical notes</label><textarea name="notes" rows="3"></textarea>
<button class="btn" type="submit">Save medical record</button></form></div>
<div class="panel"><h2>Saved records</h2><div class="table-wrap"><table><tr><th>Date</th><th>Patient</th><th>Diagnosis</th><th>Prescription</th><th>Doctor</th></tr>
<?php $q=$pdo->query("SELECT m.*,p.name patient_name,d.name doctor_name FROM medical_records m JOIN users p ON p.id=m.patient_id JOIN users d ON d.id=m.doctor_id ORDER BY m.created_at DESC");foreach($q as $r): ?><tr><td><?=h(date('M d, Y',strtotime($r['created_at'])))?></td><td><?=h($r['patient_name'])?></td><td><?=h($r['diagnosis'])?></td><td><?=h($r['prescription'])?></td><td><?=h($r['doctor_name'])?></td></tr><?php endforeach; ?></table></div></div>
<?php endif; ?>
<?php elseif ($page==='doctors'): require_login(); if(role()!=='admin'): ?><div class="alert error">Administrator access only.</div><?php else: ?>
<div class="page-head"><div><span class="eyebrow">CLINIC DIRECTORY</span><h1>Manage doctors</h1><p class="muted">Add doctor accounts to the clinic system.</p></div></div>
<div class="form-card"><h2>Add a doctor</h2><form method="post"><input type="hidden" name="action" value="add_doctor"><?=csrf_field()?><div class="form-row"><div><label>Full name</label><input name="name" required></div><div><label>Specialty</label><input name="specialty" placeholder="e.g. General Medicine"></div></div><div class="form-row"><div><label>Email</label><input type="email" name="email" required></div><div><label>Phone</label><input name="phone"></div></div><label>Temporary password (minimum 8 characters)</label><input type="password" name="password" minlength="8" required><button class="btn">Create doctor account</button></form></div>
<div class="panel"><h2>Doctor directory</h2><div class="table-wrap"><table><tr><th>Name</th><th>Specialty</th><th>Email</th><th>Phone</th></tr><?php foreach($pdo->query("SELECT name,specialty,email,phone FROM users WHERE role='doctor' ORDER BY name") as $d): ?><tr><td><?=h($d['name'])?></td><td><?=h($d['specialty'])?></td><td><?=h($d['email'])?></td><td><?=h($d['phone'])?></td></tr><?php endforeach; ?></table></div></div>
<?php endif; ?>
<?php else: ?><div class="empty"><h2>Page not found</h2><a href="index.php">Return home</a></div><?php endif; ?>
</main>
<footer><div class="footer-inner"><a class="brand" href="index.php"><span class="brand-mark">✚</span> WellCare</a><span>© <?=date('Y')?> WellCare Clinic Management System</span><span>For educational demonstration only</span></div></footer>
</body></html>
