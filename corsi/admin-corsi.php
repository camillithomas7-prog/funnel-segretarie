<?php
session_start();
require_once 'config.php';

if(isset($_POST['login_pw'])){if($_POST['login_pw']===$admin_password)$_SESSION['corsi_admin']=true;}
if(isset($_GET['logout'])){unset($_SESSION['corsi_admin']);header('Location: admin-corsi.php');exit;}
if(empty($_SESSION['corsi_admin'])){?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Corsi</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f5f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}.card{background:#fff;padding:40px;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.06);width:360px;text-align:center}h1{font-size:20px;font-weight:800;margin-bottom:4px;color:#111}p{font-size:12px;color:#888;margin-bottom:24px}input{width:100%;padding:12px 16px;border:1px solid #e0e0e0;border-radius:10px;font-size:14px;font-family:inherit;margin-bottom:14px;outline:none}button{width:100%;padding:12px;background:<?=$corso_accent?>;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}</style>
</head><body><div class="card"><h1>Admin Corsi</h1><p><?=$corso_nome?></p><form method="POST"><input type="password" name="login_pw" placeholder="Password" autofocus><button type="submit">Accedi</button></form></div></body></html>
<?php exit;}

$upload_dir=__DIR__.'/uploads/';if(!is_dir($upload_dir))mkdir($upload_dir,0755,true);
$tab=$_GET['tab']??'lezioni';

// Actions
if(isset($_POST['add_modulo'])){$pdo->prepare("INSERT INTO moduli (titolo,ordine) VALUES (?,?)")->execute([trim($_POST['mod_titolo']),intval($_POST['mod_ordine']??0)]);header('Location: admin-corsi.php?tab=lezioni');exit;}
if(isset($_POST['del_modulo'])){$pdo->prepare("DELETE FROM lezioni WHERE modulo_id=?")->execute([$_POST['mid']]);$pdo->prepare("DELETE FROM moduli WHERE id=?")->execute([$_POST['mid']]);header('Location: admin-corsi.php?tab=lezioni');exit;}
if(isset($_POST['add_lezione'])){
    $pdf='';
    if(!empty($_FILES['lez_pdf']['tmp_name'])){$fn='lez_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','',basename($_FILES['lez_pdf']['name']));move_uploaded_file($_FILES['lez_pdf']['tmp_name'],$upload_dir.$fn);$pdf=$fn;}
    $pdo->prepare("INSERT INTO lezioni (modulo_id,titolo,video_url,pdf_file,descrizione,ordine) VALUES (?,?,?,?,?,?)")
        ->execute([intval($_POST['lez_modulo']),trim($_POST['lez_titolo']),trim($_POST['lez_video']??''),$pdf,trim($_POST['lez_desc']??''),intval($_POST['lez_ordine']??0)]);
    header('Location: admin-corsi.php?tab=lezioni');exit;
}
if(isset($_POST['del_lezione'])){
    $l=$pdo->prepare("SELECT pdf_file FROM lezioni WHERE id=?");$l->execute([$_POST['lid']]);$r=$l->fetch();
    if($r&&$r['pdf_file']&&file_exists($upload_dir.$r['pdf_file']))unlink($upload_dir.$r['pdf_file']);
    $pdo->prepare("DELETE FROM progressi WHERE lezione_id=?")->execute([$_POST['lid']]);
    $pdo->prepare("DELETE FROM lezioni WHERE id=?")->execute([$_POST['lid']]);
    header('Location: admin-corsi.php?tab=lezioni');exit;
}
if(isset($_POST['toggle_studente'])){$pdo->prepare("UPDATE studenti SET attivo=NOT attivo WHERE id=?")->execute([$_POST['stud_id']]);header('Location: admin-corsi.php?tab=studenti');exit;}
if(isset($_POST['del_studente'])){$pdo->prepare("DELETE FROM progressi WHERE studente_id=?")->execute([$_POST['stud_id']]);$pdo->prepare("DELETE FROM studenti WHERE id=?")->execute([$_POST['stud_id']]);header('Location: admin-corsi.php?tab=studenti');exit;}

$moduli=$pdo->query("SELECT * FROM moduli ORDER BY ordine,id")->fetchAll(PDO::FETCH_ASSOC);
$lezioni=$pdo->query("SELECT l.*,m.titolo as mod_titolo FROM lezioni l LEFT JOIN moduli m ON l.modulo_id=m.id ORDER BY l.ordine,l.id")->fetchAll(PDO::FETCH_ASSOC);
$studenti=$pdo->query("SELECT s.*,(SELECT COUNT(*) FROM progressi p WHERE p.studente_id=s.id AND p.completata=1) as completate FROM studenti s ORDER BY s.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$tot_lez=count($lezioni);
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Corsi - <?=$corso_nome?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f5f5f7;color:#333}
.container{max-width:960px;margin:0 auto;padding:20px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px}
.header h1{font-size:18px;font-weight:800;color:#111}
.header-links{display:flex;gap:8px;align-items:center}
.header-links a{font-size:12px;text-decoration:none;padding:5px 12px;border-radius:6px;color:#888;background:#fff;border:1px solid #e8e8e8}
.header-links a.active{background:<?=$corso_accent?>;color:#fff;border-color:<?=$corso_accent?>}
.header-links .logout{color:#888!important;background:none!important;border:none!important}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px;margin-bottom:16px}
.stat{background:#fff;border-radius:10px;padding:14px 10px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.stat .num{font-size:22px;font-weight:800;color:#111}.stat .lbl{font-size:9px;color:#999;text-transform:uppercase;font-weight:700;margin-top:2px}
.card{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:12px}
.card h3{font-size:13px;font-weight:700;color:#111;margin-bottom:10px}
.form-row{display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end}
.form-row input,.form-row select,.form-row textarea{padding:8px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;outline:none;background:#fafafa}
.form-row input:focus,.form-row textarea:focus{border-color:<?=$corso_accent?>}
.form-row button{padding:8px 16px;background:<?=$corso_accent?>;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.item{background:#fff;border-radius:10px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.item-title{font-size:14px;font-weight:700;color:#111;flex:1;min-width:120px}
.item-sub{font-size:11px;color:#888}
.badge{padding:2px 8px;border-radius:10px;font-size:9px;font-weight:700}
.btn-sm{padding:4px 10px;border:none;border-radius:5px;font-size:10px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-red{background:#fff;color:#ef4444;border:1px solid #fecaca}
.btn-green{background:#22c55e;color:#fff}
.btn-gray{background:#f0f0f0;color:#888}
@media(max-width:600px){.form-row{flex-direction:column}.form-row input,.form-row select,.form-row textarea{width:100%}}
</style></head><body>
<div class="container">
  <div class="header">
    <h1>Admin Corsi — <?=$corso_nome?></h1>
    <div class="header-links">
      <a href="?tab=lezioni" class="<?=$tab==='lezioni'?'active':''?>">Lezioni</a>
      <a href="?tab=studenti" class="<?=$tab==='studenti'?'active':''?>">Studenti</a>
      <a href="?logout=1" class="logout">Esci</a>
    </div>
  </div>

  <div class="stats">
    <div class="stat"><div class="num"><?=count($moduli)?></div><div class="lbl">Moduli</div></div>
    <div class="stat"><div class="num"><?=$tot_lez?></div><div class="lbl">Lezioni</div></div>
    <div class="stat"><div class="num"><?=count($studenti)?></div><div class="lbl">Studenti</div></div>
    <div class="stat"><div class="num"><?=count(array_filter($studenti,function($s){return $s['attivo'];}))?></div><div class="lbl">Attivi</div></div>
  </div>

<?php if($tab==='studenti'): ?>
  <?php foreach($studenti as $s): ?>
  <div class="item">
    <div style="flex:1;min-width:120px">
      <div class="item-title"><?=htmlspecialchars($s['nome'].' '.$s['cognome'])?></div>
      <div class="item-sub"><?=htmlspecialchars($s['email'])?> · <?=date('d/m/Y',strtotime($s['created_at']))?></div>
    </div>
    <span class="badge" style="background:<?=$s['attivo']?'#dcfce7;color:#22c55e':'#fee2e2;color:#ef4444'?>"><?=$s['attivo']?'Attivo':'Disattivato'?></span>
    <span class="badge" style="background:#f0f0f0;color:#555"><?=$s['completate']?>/<?=$tot_lez?> lezioni</span>
    <form method="POST"><input type="hidden" name="stud_id" value="<?=$s['id']?>"><button type="submit" name="toggle_studente" class="btn-sm <?=$s['attivo']?'btn-gray':'btn-green'?>"><?=$s['attivo']?'Disattiva':'Attiva'?></button></form>
    <form method="POST" onsubmit="return confirm('Eliminare studente e progressi?')"><input type="hidden" name="stud_id" value="<?=$s['id']?>"><button type="submit" name="del_studente" class="btn-sm btn-red">X</button></form>
  </div>
  <?php endforeach; ?>
  <?php if(empty($studenti)): ?><div style="text-align:center;padding:30px;color:#aaa;font-size:13px">Nessuno studente registrato</div><?php endif; ?>

<?php else: ?>
  <!-- Add Modulo -->
  <div class="card">
    <h3>+ Nuovo Modulo</h3>
    <form method="POST" class="form-row">
      <input type="text" name="mod_titolo" placeholder="Titolo modulo (es: Modulo 1 - Excel)" required style="flex:2;min-width:200px">
      <input type="number" name="mod_ordine" placeholder="Ordine" value="0" style="width:70px">
      <button type="submit" name="add_modulo">Aggiungi Modulo</button>
    </form>
  </div>

  <!-- Add Lezione -->
  <div class="card">
    <h3>+ Nuova Lezione</h3>
    <form method="POST" enctype="multipart/form-data" class="form-row">
      <select name="lez_modulo" required style="min-width:120px"><?php foreach($moduli as $m): ?><option value="<?=$m['id']?>"><?=htmlspecialchars($m['titolo'])?></option><?php endforeach; ?></select>
      <input type="text" name="lez_titolo" placeholder="Titolo lezione" required style="flex:1;min-width:150px">
      <input type="url" name="lez_video" placeholder="Link Vimeo/YouTube" style="flex:1;min-width:150px">
      <input type="number" name="lez_ordine" placeholder="Ordine" value="0" style="width:60px">
      <input type="file" name="lez_pdf" accept=".pdf" style="font-size:11px">
      <textarea name="lez_desc" placeholder="Descrizione (opzionale)" style="width:100%;min-height:50px"></textarea>
      <button type="submit" name="add_lezione">Aggiungi Lezione</button>
    </form>
  </div>

  <!-- List -->
  <?php foreach($moduli as $m): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 8px;padding:0 4px">
    <div style="font-size:12px;font-weight:800;color:<?=$corso_accent?>;text-transform:uppercase"><?=htmlspecialchars($m['titolo'])?></div>
    <form method="POST" onsubmit="return confirm('Eliminare modulo e tutte le lezioni?')"><input type="hidden" name="mid" value="<?=$m['id']?>"><button type="submit" name="del_modulo" class="btn-sm btn-red">Elimina Modulo</button></form>
  </div>
  <?php foreach($lezioni as $l): if($l['modulo_id']!=$m['id'])continue; ?>
  <div class="item">
    <div style="flex:1;min-width:120px">
      <div class="item-title"><?=htmlspecialchars($l['titolo'])?></div>
      <div class="item-sub">
        <?=$l['video_url']?'Video: '.htmlspecialchars(substr($l['video_url'],0,40)).'...':'Nessun video'?>
        <?=$l['pdf_file']?' · PDF: '.$l['pdf_file']:''?>
      </div>
    </div>
    <?php if($l['video_url']): ?><a href="<?=htmlspecialchars($l['video_url'])?>" target="_blank" class="btn-sm" style="background:#3b82f6;color:#fff;text-decoration:none">Video</a><?php endif; ?>
    <?php if($l['pdf_file']): ?><a href="uploads/<?=urlencode($l['pdf_file'])?>" target="_blank" class="btn-sm" style="background:#ef4444;color:#fff;text-decoration:none">PDF</a><?php endif; ?>
    <form method="POST" onsubmit="return confirm('Eliminare lezione?')"><input type="hidden" name="lid" value="<?=$l['id']?>"><button type="submit" name="del_lezione" class="btn-sm btn-red">X</button></form>
  </div>
  <?php endforeach; endforeach; ?>
  <?php if(empty($moduli)): ?><div style="text-align:center;padding:30px;color:#aaa;font-size:13px">Crea il primo modulo per iniziare</div><?php endif; ?>
<?php endif; ?>

</div>
</body></html>
