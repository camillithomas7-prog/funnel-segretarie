<?php
session_start();
require_once 'config.php';
$msg='';

// Registrazione
if (isset($_POST['register'])) {
    $nome=trim($_POST['nome']??'');$cognome=trim($_POST['cognome']??'');
    $email=trim($_POST['email']??'');$pass=$_POST['password']??'';
    if($nome&&$email&&strlen($pass)>=6){
        $exists=$pdo->prepare("SELECT id FROM studenti WHERE email=?");$exists->execute([$email]);
        if($exists->fetch()){$msg='Email gia registrata';}
        else{$hash=password_hash($pass,PASSWORD_DEFAULT);$pdo->prepare("INSERT INTO studenti (nome,cognome,email,password) VALUES (?,?,?,?)")->execute([$nome,$cognome,$email,$hash]);$_SESSION['studente_id']=$pdo->lastInsertId();$_SESSION['studente_nome']=$nome;header('Location: dashboard.php');exit;}
    } else {$msg='Compila tutti i campi (password min 6 caratteri)';}
}
// Login
if (isset($_POST['login'])) {
    $email=trim($_POST['email']??'');$pass=$_POST['password']??'';
    $stmt=$pdo->prepare("SELECT * FROM studenti WHERE email=?");$stmt->execute([$email]);$u=$stmt->fetch(PDO::FETCH_ASSOC);
    if($u&&password_verify($pass,$u['password'])){
        if(!$u['attivo']){$msg='Account disattivato. Contatta il supporto.';}
        else{$_SESSION['studente_id']=$u['id'];$_SESSION['studente_nome']=$u['nome'];header('Location: dashboard.php');exit;}
    } else {$msg='Email o password errata';}
}
if(!empty($_SESSION['studente_id'])){header('Location: dashboard.php');exit;}
$tab=$_GET['tab']??'login';
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$corso_nome?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f5f5f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;padding:36px;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.06);width:400px;max-width:100%}
.card h1{font-size:20px;font-weight:800;color:#111;margin-bottom:4px;text-align:center}
.card .sub{font-size:12px;color:#888;text-align:center;margin-bottom:20px}
.tabs{display:flex;gap:4px;margin-bottom:20px}.tabs a{flex:1;padding:10px;text-align:center;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:#888;background:#f5f5f7;border:1px solid #e8e8e8}
.tabs a.active{background:<?=$corso_accent?>;color:#fff;border-color:<?=$corso_accent?>}
label{font-size:11px;color:#888;font-weight:600;display:block;margin-bottom:4px;text-transform:uppercase}
input{width:100%;padding:11px 14px;border:1px solid #e0e0e0;border-radius:8px;font-size:14px;font-family:inherit;margin-bottom:12px;outline:none;background:#fafafa}
input:focus{border-color:<?=$corso_accent?>}
button{width:100%;padding:12px;background:<?=$corso_accent?>;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.error{background:#fef2f2;color:#ef4444;padding:8px 12px;border-radius:8px;font-size:12px;margin-bottom:12px;text-align:center}
.row{display:flex;gap:8px}.row>*{flex:1}
</style></head><body>
<div class="card">
  <h1><?=$corso_nome?></h1>
  <p class="sub">Area Studenti</p>
  <div class="tabs"><a href="?tab=login" class="<?=$tab==='login'?'active':''?>">Accedi</a><a href="?tab=register" class="<?=$tab==='register'?'active':''?>">Registrati</a></div>
  <?php if($msg): ?><div class="error"><?=$msg?></div><?php endif; ?>
  <?php if($tab==='register'): ?>
  <form method="POST">
    <div class="row"><div><label>Nome</label><input type="text" name="nome" required></div><div><label>Cognome</label><input type="text" name="cognome"></div></div>
    <label>Email</label><input type="email" name="email" required>
    <label>Password</label><input type="password" name="password" placeholder="Min 6 caratteri" required>
    <button type="submit" name="register">Registrati</button>
  </form>
  <?php else: ?>
  <form method="POST">
    <label>Email</label><input type="email" name="email" required>
    <label>Password</label><input type="password" name="password" required>
    <button type="submit" name="login">Accedi</button>
  </form>
  <?php endif; ?>
</div>
</body></html>
