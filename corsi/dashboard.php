<?php
session_start();
require_once 'config.php';
if(empty($_SESSION['studente_id'])){header('Location: index.php');exit;}
$sid=$_SESSION['studente_id'];
$studente=$pdo->prepare("SELECT * FROM studenti WHERE id=?");$studente->execute([$sid]);$user=$studente->fetch(PDO::FETCH_ASSOC);
if(!$user||!$user['attivo']){session_destroy();header('Location: index.php');exit;}

// Segna completata
if(isset($_POST['toggle_complete'])){
    $lid=$_POST['lezione_id'];
    $ex=$pdo->prepare("SELECT completata FROM progressi WHERE studente_id=? AND lezione_id=?");$ex->execute([$sid,$lid]);$row=$ex->fetch();
    if($row){$pdo->prepare("UPDATE progressi SET completata=?, completed_at=? WHERE studente_id=? AND lezione_id=?")->execute([!$row['completata'],$row['completata']?null:date('Y-m-d H:i:s'),$sid,$lid]);}
    else{$pdo->prepare("INSERT INTO progressi (studente_id,lezione_id,completata,completed_at) VALUES (?,?,1,NOW())")->execute([$sid,$lid]);}
    header('Location: dashboard.php'.(isset($_GET['lezione'])?'?lezione='.$_GET['lezione']:''));exit;
}
if(isset($_GET['logout'])){session_destroy();header('Location: index.php');exit;}

$moduli=$pdo->query("SELECT * FROM moduli ORDER BY ordine,id")->fetchAll(PDO::FETCH_ASSOC);
$lezioni_all=$pdo->query("SELECT * FROM lezioni ORDER BY ordine,id")->fetchAll(PDO::FETCH_ASSOC);
$progressi_raw=$pdo->prepare("SELECT lezione_id,completata FROM progressi WHERE studente_id=?");$progressi_raw->execute([$sid]);
$prog=[];while($r=$progressi_raw->fetch(PDO::FETCH_ASSOC)){$prog[$r['lezione_id']]=$r['completata'];}
$tot_lez=count($lezioni_all);$tot_done=count(array_filter($prog));
$current_lez=isset($_GET['lezione'])?intval($_GET['lezione']):0;
$active_lezione=null;
if($current_lez){$stmt=$pdo->prepare("SELECT * FROM lezioni WHERE id=?");$stmt->execute([$current_lez]);$active_lezione=$stmt->fetch(PDO::FETCH_ASSOC);}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$corso_nome?> - Area Studenti</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f5f5f7;color:#333;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid #e8e8e8;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10}
.topbar h1{font-size:15px;font-weight:800;color:<?=$corso_accent?>}
.topbar a{font-size:12px;color:#888;text-decoration:none}.topbar a:hover{color:#ef4444}
.topbar .user{font-size:12px;color:#888;margin-right:12px}
.layout{display:flex;min-height:calc(100vh - 50px)}
.sidebar-c{width:280px;background:#fff;border-right:1px solid #e8e8e8;overflow-y:auto;padding:16px 0;flex-shrink:0}
.main-c{flex:1;padding:24px;overflow-y:auto}
.progress-bar{margin:0 16px 16px;background:#e8e8e8;border-radius:10px;height:6px;overflow:hidden}
.progress-fill{height:100%;background:<?=$corso_accent?>;border-radius:10px;transition:width .3s}
.progress-text{font-size:10px;color:#888;margin:0 16px 12px;font-weight:600}
.mod-title{font-size:10px;font-weight:700;color:#888;text-transform:uppercase;padding:12px 16px 6px;letter-spacing:.05em}
.lez-item{display:flex;align-items:center;gap:8px;padding:10px 16px;cursor:pointer;transition:background .15s;font-size:13px;color:#555;text-decoration:none;border-left:3px solid transparent}
.lez-item:hover{background:#f9f9f9}
.lez-item.active{background:#f0f4ff;color:<?=$corso_accent?>;font-weight:600;border-left-color:<?=$corso_accent?>}
.lez-item.done{color:#22c55e}
.lez-check{width:18px;height:18px;border-radius:50%;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px}
.lez-check.done{background:#22c55e;border-color:#22c55e;color:#fff}
.lez-check.done::after{content:'\2713'}
.video-wrap{background:#000;border-radius:12px;overflow:hidden;margin-bottom:16px;aspect-ratio:16/9}
.video-wrap iframe{width:100%;height:100%;border:none}
.lez-title{font-size:22px;font-weight:800;color:#111;margin-bottom:8px}
.lez-desc{font-size:14px;color:#666;line-height:1.7;margin-bottom:16px}
.lez-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.btn{padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit;border:none;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:<?=$corso_accent?>;color:#fff}
.btn-outline{background:#fff;color:#333;border:1px solid #e0e0e0}
.btn-green{background:#22c55e;color:#fff}
.empty-state{text-align:center;padding:60px 20px;color:#aaa}
.empty-state h2{font-size:18px;color:#111;margin-bottom:8px}
@media(max-width:768px){.sidebar-c{display:none}.main-c{padding:12px}}
</style></head><body>
<div class="topbar">
  <h1><?=$corso_nome?></h1>
  <div><span class="user">Ciao, <?=htmlspecialchars($user['nome'])?></span><a href="?logout=1">Esci</a></div>
</div>
<div class="layout">
  <div class="sidebar-c">
    <div class="progress-text"><?=$tot_done?>/<?=$tot_lez?> lezioni completate</div>
    <div class="progress-bar"><div class="progress-fill" style="width:<?=$tot_lez>0?round($tot_done/$tot_lez*100):0?>%"></div></div>
    <?php foreach($moduli as $m): ?>
    <div class="mod-title"><?=htmlspecialchars($m['titolo'])?></div>
    <?php foreach($lezioni_all as $l): if($l['modulo_id']!=$m['id'])continue;
      $is_done=!empty($prog[$l['id']]);
      $is_active=$current_lez==$l['id'];
    ?>
    <a href="?lezione=<?=$l['id']?>" class="lez-item <?=$is_active?'active':''?> <?=$is_done?'done':''?>">
      <div class="lez-check <?=$is_done?'done':''?>"></div>
      <?=htmlspecialchars($l['titolo'])?>
    </a>
    <?php endforeach; endforeach; ?>
  </div>
  <div class="main-c">
    <?php if($active_lezione): ?>
      <?php if(!empty($active_lezione['video_url'])):
        $vurl=$active_lezione['video_url'];
        if(strpos($vurl,'vimeo.com/')!==false){preg_match('/vimeo\.com\/(\d+)/',$vurl,$vm);if(!empty($vm[1]))$vurl='https://player.vimeo.com/video/'.$vm[1];}
        if(strpos($vurl,'youtube.com/watch')!==false){preg_match('/v=([^&]+)/',$vurl,$ym);if(!empty($ym[1]))$vurl='https://www.youtube.com/embed/'.$ym[1];}
        if(strpos($vurl,'youtu.be/')!==false){preg_match('/youtu\.be\/([^?]+)/',$vurl,$ym);if(!empty($ym[1]))$vurl='https://www.youtube.com/embed/'.$ym[1];}
      ?>
      <div class="video-wrap"><iframe src="<?=htmlspecialchars($vurl)?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
      <?php endif; ?>
      <div class="lez-title"><?=htmlspecialchars($active_lezione['titolo'])?></div>
      <?php if(!empty($active_lezione['descrizione'])): ?><div class="lez-desc"><?=nl2br(htmlspecialchars($active_lezione['descrizione']))?></div><?php endif; ?>
      <div class="lez-actions">
        <form method="POST" action="dashboard.php?lezione=<?=$active_lezione['id']?>">
          <input type="hidden" name="lezione_id" value="<?=$active_lezione['id']?>">
          <button type="submit" name="toggle_complete" class="btn <?=!empty($prog[$active_lezione['id']])?'btn-green':'btn-primary'?>">
            <?=!empty($prog[$active_lezione['id']])?'&#10003; Completata':'Segna come completata'?>
          </button>
        </form>
        <?php if(!empty($active_lezione['pdf_file'])): ?>
        <a href="uploads/<?=urlencode($active_lezione['pdf_file'])?>" target="_blank" class="btn btn-outline">PDF Lezione</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <h2>Benvenuto nel tuo corso!</h2>
        <p>Seleziona una lezione dal menu a sinistra per iniziare</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</body></html>
