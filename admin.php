<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';
$project_name = 'Corso Segretarie';
$accent = '#1a3a6b';
$session_key = 'admin_logged_seg';
$reminder_template = "Buongiorno {NOME}, le scriviamo dal Corso Segretarie.\n\nAbbiamo provato a contattarla al telefono nei giorni scorsi ma non siamo riusciti a raggiungerla. Si era candidata per partecipare al nostro corso e volevamo ricordarle che la promozione attiva scade tra 2 giorni.\n\nSe è ancora interessata, può rispondere a questo messaggio indicandoci un orario comodo: la richiameremo volentieri per fissare una chiamata e spiegarle tutti i dettagli. Proveremo comunque a ricontattarla noi nei prossimi giorni, prima della scadenza della promozione.\n\nGrazie e buona giornata!";
function wa_link($phone, $text='') {
    $d = preg_replace('/[^0-9]/','', (string)$phone);
    if (substr($d,0,2)==='00') $d = substr($d,2);
    if (preg_match('/^3\d{8,9}$/', $d)) $d = '39'.$d;
    $url = 'https://web.whatsapp.com/send?phone='.$d;
    if ($text !== '') $url .= '&text='.rawurlencode($text);
    return $url;
}

// Auto-add missing columns
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN fonte VARCHAR(20) DEFAULT 'form'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature MODIFY COLUMN stato VARCHAR(30) DEFAULT 'nuovo'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN note TEXT"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN provincia VARCHAR(10) DEFAULT ''"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN assegnato VARCHAR(50) DEFAULT ''"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN prossimo_contatto DATETIME DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE candidature ADD COLUMN nr_count INT DEFAULT 0"); } catch(PDOException $e) {}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS chiamate_registrate (id INT AUTO_INCREMENT PRIMARY KEY, lead_id INT NOT NULL, operatore VARCHAR(50) DEFAULT '', filename VARCHAR(255) NOT NULL, durata INT DEFAULT 0, size_bytes BIGINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_lead (lead_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(PDOException $e) {}
if (!is_dir(__DIR__.'/uploads/calls')) @mkdir(__DIR__.'/uploads/calls', 0755, true);
// Operatori table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS operatori (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(50) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(PDOException $e) {}
// Log attivita table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS log_attivita (id INT AUTO_INCREMENT PRIMARY KEY, lead_id INT, operatore VARCHAR(50), azione VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(PDOException $e) {}
// Migra vecchi WhatsApp
try {
    $pdo->exec("UPDATE candidature SET fonte='whatsapp' WHERE note LIKE '%WhatsApp%' AND (fonte IS NULL OR fonte='form')");
    $pdo->exec("UPDATE candidature SET note='' WHERE note='Lead da WhatsApp'");
    $pdo->exec("UPDATE candidature SET note=TRIM(REPLACE(REPLACE(note,' | Contattato anche via WhatsApp',''),'Lead da WhatsApp','')) WHERE note LIKE '%WhatsApp%'");
} catch(PDOException $e) {}

// Auth
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $admin_password) $_SESSION[$session_key] = true;
}
if (isset($_GET['logout'])) { unset($_SESSION[$session_key]); header('Location: admin.php'); exit; }
$logged = !empty($_SESSION[$session_key]);

// Actions
$qs = isset($_GET['filter'])?'?filter='.$_GET['filter']:'';
if ($logged && isset($_POST['update_stato'])) {
    $old = $pdo->prepare("SELECT stato,nr_count FROM candidature WHERE id=?"); $old->execute([$_POST['cid']]); $oldRow=$old->fetch(PDO::FETCH_ASSOC);
    $oldStato = $oldRow['stato'] ?? '';
    $newStato = $_POST['nuovo_stato'];
    $nr = (int)($oldRow['nr_count'] ?? 0);
    if ($newStato === 'non_risponde') {
        $nr++;
        $pdo->prepare("UPDATE candidature SET stato=?, nr_count=? WHERE id=?")->execute([$newStato, $nr, $_POST['cid']]);
        $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['operatore_azione']??'','Tentativo NR #'.$nr.' — non risponde']);
    } else {
        if (in_array($newStato, ['contattato','confermato','completato'], true)) $nr = 0;
        $pdo->prepare("UPDATE candidature SET stato=?, nr_count=? WHERE id=?")->execute([$newStato, $nr, $_POST['cid']]);
        $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['operatore_azione']??'','Stato: '.$oldStato.' → '.$newStato]);
    }
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['update_note'])) {
    $pdo->prepare("UPDATE candidature SET note=? WHERE id=?")->execute([$_POST['note'], $_POST['cid']]);
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['operatore_azione']??'','Nota aggiornata']);
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['update_assegnato'])) {
    $pdo->prepare("UPDATE candidature SET assegnato=? WHERE id=?")->execute([$_POST['assegnato'], $_POST['cid']]);
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['assegnato'],'Lead assegnato a '.$_POST['assegnato']]);
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['update_prossimo'])) {
    $val = !empty($_POST['prossimo_contatto']) ? $_POST['prossimo_contatto'] : null;
    $pdo->prepare("UPDATE candidature SET prossimo_contatto=? WHERE id=?")->execute([$val, $_POST['cid']]);
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['dismiss_reminder'])) {
    $pdo->prepare("UPDATE candidature SET prossimo_contatto=NULL WHERE id=?")->execute([$_POST['cid']]);
    header('Location: admin.php'.$qs); exit;
}
if ($logged && isset($_POST['update_anagrafica'])) {
    $pdo->prepare("UPDATE candidature SET nome=?, cognome=?, email=?, telefono=? WHERE id=?")
        ->execute([trim($_POST['nome']??''), trim($_POST['cognome']??''), trim($_POST['email']??''), trim($_POST['telefono']??''), $_POST['cid']]);
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['operatore_azione']??'','Anagrafica aggiornata']);
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['update_indirizzo_lead'])) {
    $pdo->prepare("UPDATE candidature SET indirizzo=?, cap=?, citta=?, provincia=? WHERE id=?")
        ->execute([trim($_POST['indirizzo']??''), trim($_POST['cap']??''), trim($_POST['citta_lead']??''), trim($_POST['provincia_lead']??''), $_POST['cid']]);
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")->execute([$_POST['cid'],$_POST['operatore_azione']??'','Indirizzo aggiornato']);
    header('Location: admin.php'.$qs.'#lead-'.$_POST['cid']); exit;
}
if ($logged && isset($_POST['add_manual'])) {
    $tel = trim($_POST['m_telefono']??'');
    $tel_clean = preg_replace('/[^0-9]/', '', $tel);
    $existing = $pdo->prepare("SELECT id FROM candidature WHERE REPLACE(REPLACE(REPLACE(telefono,' ',''),'+',''),'-','') LIKE ?");
    $existing->execute(['%'.$tel_clean.'%']);
    $found = $existing->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $pdo->prepare("UPDATE candidature SET fonte='whatsapp' WHERE id=?")->execute([$found['id']]);
    } else {
        $pdo->prepare("INSERT INTO candidature (nome, cognome, telefono, email, citta, provincia, metodo_pagamento, fonte, stato) VALUES (?, ?, ?, ?, ?, ?, ?, 'whatsapp', 'nuovo')")
            ->execute([trim($_POST['m_nome']??''),trim($_POST['m_cognome']??''),$tel,trim($_POST['m_email']??''),trim($_POST['m_citta']??''),trim($_POST['m_provincia']??''),trim($_POST['m_pagamento']??'contrassegno')]);
    }
    header('Location: admin.php'); exit;
}
if ($logged && isset($_POST['elimina'])) {
    $pdo->prepare("DELETE FROM candidature WHERE id=?")->execute([$_POST['cid']]);
    header('Location: admin.php'); exit;
}
// Operatori management
if ($logged && isset($_POST['add_operatore'])) {
    $nome = trim($_POST['nome_operatore']??'');
    if ($nome) $pdo->prepare("INSERT INTO operatori (nome) VALUES (?)")->execute([$nome]);
    header('Location: admin.php?tab=team'); exit;
}
if ($logged && isset($_POST['del_operatore'])) {
    $pdo->prepare("DELETE FROM operatori WHERE id=?")->execute([$_POST['op_id']]);
    header('Location: admin.php?tab=team'); exit;
}

// SOP table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS sop (id INT AUTO_INCREMENT PRIMARY KEY, titolo VARCHAR(255) NOT NULL, link VARCHAR(500) NOT NULL, descrizione TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(PDOException $e) {}
if ($logged && isset($_POST['add_sop'])) {
    $titolo = trim($_POST['sop_titolo']??'');
    $link = trim($_POST['sop_link']??'');
    $desc = trim($_POST['sop_desc']??'');
    if ($titolo && $link) $pdo->prepare("INSERT INTO sop (titolo, link, descrizione) VALUES (?, ?, ?)")->execute([$titolo, $link, $desc]);
    header('Location: admin.php?tab=sop'); exit;
}
if ($logged && isset($_POST['del_sop'])) {
    $pdo->prepare("DELETE FROM sop WHERE id=?")->execute([$_POST['sop_id']]);
    header('Location: admin.php?tab=sop'); exit;
}

// Logistica table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS logistica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT,
    nome VARCHAR(100),
    cognome VARCHAR(100),
    telefono VARCHAR(30),
    email VARCHAR(100),
    indirizzo VARCHAR(255) DEFAULT '',
    cap VARCHAR(10) DEFAULT '',
    citta VARCHAR(100) DEFAULT '',
    provincia VARCHAR(10) DEFAULT '',
    metodo_pagamento VARCHAR(30) DEFAULT 'contrassegno',
    stato_spedizione VARCHAR(30) DEFAULT 'da_evadere',
    tracking VARCHAR(100) DEFAULT '',
    corriere VARCHAR(50) DEFAULT '',
    note_logistica TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    operatore VARCHAR(50) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE logistica ADD COLUMN operatore VARCHAR(50) DEFAULT ''"); } catch(PDOException $e) {}

// Auto-import confermati in logistica
if ($logged) {
    try {
        $confermati = $pdo->query("SELECT * FROM candidature WHERE stato='confermato'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($confermati as $conf) {
            $exists = $pdo->prepare("SELECT id FROM logistica WHERE lead_id=?");
            $exists->execute([$conf['id']]);
            if (!$exists->fetch()) {
                $pdo->prepare("INSERT INTO logistica (lead_id,nome,cognome,telefono,email,citta,provincia,metodo_pagamento,operatore) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$conf['id'],$conf['nome'],$conf['cognome'],$conf['telefono'],$conf['email']??'',$conf['citta']??'',$conf['provincia']??'',$conf['metodo_pagamento']??'contrassegno',$conf['assegnato']??'']);
            }
        }
    } catch(PDOException $e) {}
}

// Logistica actions
if ($logged && isset($_POST['update_spedizione'])) {
    $pdo->prepare("UPDATE logistica SET stato_spedizione=? WHERE id=?")->execute([$_POST['stato_sped'], $_POST['lid']]);
    header('Location: admin.php?tab=logistica'.(isset($_GET['lfilter'])?'&lfilter='.$_GET['lfilter']:'').'#log-'.$_POST['lid']); exit;
}
if ($logged && isset($_POST['update_tracking'])) {
    $pdo->prepare("UPDATE logistica SET tracking=?, corriere=? WHERE id=?")->execute([$_POST['tracking'], $_POST['corriere']??'', $_POST['lid']]);
    header('Location: admin.php?tab=logistica'.'#log-'.$_POST['lid']); exit;
}
if ($logged && isset($_POST['update_indirizzo'])) {
    $pdo->prepare("UPDATE logistica SET indirizzo=?, cap=?, citta=?, provincia=? WHERE id=?")->execute([$_POST['indirizzo'], $_POST['cap'], $_POST['citta_sped'], $_POST['prov_sped'], $_POST['lid']]);
    header('Location: admin.php?tab=logistica'.'#log-'.$_POST['lid']); exit;
}
if ($logged && isset($_POST['update_note_log'])) {
    $pdo->prepare("UPDATE logistica SET note_logistica=? WHERE id=?")->execute([$_POST['note_log'], $_POST['lid']]);
    header('Location: admin.php?tab=logistica'.'#log-'.$_POST['lid']); exit;
}
if ($logged && isset($_POST['del_logistica'])) {
    $pdo->prepare("DELETE FROM logistica WHERE id=?")->execute([$_POST['lid']]);
    header('Location: admin.php?tab=logistica'); exit;
}

// Upload prodotto PDF
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if ($logged && isset($_POST['upload_prodotto']) && !empty($_FILES['pdf_file']['tmp_name'])) {
    $file = $_FILES['pdf_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'pdf' && $file['size'] < 50 * 1024 * 1024) {
        $filename = 'prodotto_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        move_uploaded_file($file['tmp_name'], $upload_dir . $filename);
    }
    header('Location: admin.php?tab=prodotto'); exit;
}
if ($logged && isset($_POST['delete_file'])) {
    $f = basename($_POST['filename']);
    if (file_exists($upload_dir . $f)) unlink($upload_dir . $f);
    header('Location: admin.php?tab=prodotto'); exit;
}

if (!$logged) { ?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin - <?=$project_name?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#f5f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}.card{background:#fff;padding:40px;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.06);width:360px;text-align:center}h1{font-size:20px;font-weight:800;margin-bottom:4px;color:#111}p{font-size:12px;color:#888;margin-bottom:24px}input{width:100%;padding:12px 16px;border:1px solid #e0e0e0;border-radius:10px;font-size:14px;font-family:inherit;margin-bottom:14px;outline:none;background:#fafafa}input:focus{border-color:<?=$accent?>}button{width:100%;padding:12px;background:<?=$accent?>;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}</style>
</head><body><div class="card"><h1><?=$project_name?></h1><p>Pannello Admin</p><form method="POST"><input type="password" name="login_password" placeholder="Password" autofocus><button type="submit">Accedi</button></form></div></body></html>
<?php exit; }

// Data
$stati = ['nuovo','contattato','non_risponde','da_ricontattare','confermato','spedito','completato','annullato'];
$stato_label = ['nuovo'=>'Nuovo','contattato'=>'Contattato','non_risponde'=>'Non Risponde','da_ricontattare'=>'Da Ricontattare','confermato'=>'Confermato','spedito'=>'Spedito','completato'=>'Completato','annullato'=>'Annullato'];
$stato_color = ['nuovo'=>'#eab308','contattato'=>'#3b82f6','non_risponde'=>'#ef4444','da_ricontattare'=>'#f97316','confermato'=>'#22c55e','spedito'=>'#a855f7','completato'=>'#059669','annullato'=>'#9ca3af'];
$operatori = $pdo->query("SELECT * FROM operatori ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$op_names = array_column($operatori, 'nome');

$filter = $_GET['filter'] ?? 'tutti';
$tab = $_GET['tab'] ?? 'leads';
$where = '';
if ($filter === 'oggi') $where = "WHERE DATE(created_at)=CURDATE()";
elseif (in_array($filter, $stati)) $where = "WHERE stato='$filter'";
elseif ($filter === 'whatsapp') $where = "WHERE fonte='whatsapp'";
elseif ($filter === 'scaduti') $where = "WHERE prossimo_contatto IS NOT NULL AND prossimo_contatto < NOW() AND stato NOT IN ('completato','annullato')";
elseif (strpos($filter,'op_')===0) { $opf=substr($filter,3); $where = "WHERE assegnato='".addslashes($opf)."'"; }

$totali = $pdo->query("SELECT COUNT(*) FROM candidature")->fetchColumn();
$oggi_count = $pdo->query("SELECT COUNT(*) FROM candidature WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$nuovi = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='nuovo'")->fetchColumn();
$non_risponde = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='non_risponde'")->fetchColumn();
$da_ricontattare = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='da_ricontattare'")->fetchColumn();
$scaduti = $pdo->query("SELECT COUNT(*) FROM candidature WHERE prossimo_contatto IS NOT NULL AND prossimo_contatto < NOW() AND stato NOT IN ('completato','annullato')")->fetchColumn();
$annullati = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='annullato'")->fetchColumn();
$confermati = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='confermato'")->fetchColumn();
$leads = $pdo->query("SELECT * FROM candidature $where ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$recs_by_lead = [];
try {
    $_rs = $pdo->query("SELECT * FROM chiamate_registrate ORDER BY created_at DESC");
    while ($_r = $_rs->fetch(PDO::FETCH_ASSOC)) { $recs_by_lead[$_r['lead_id']][] = $_r; }
} catch (Exception $e) {}

// Stats per operatore
$op_stats = [];
foreach ($op_names as $op) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM candidature WHERE assegnato=?"); $s->execute([$op]);
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM candidature WHERE assegnato=? AND stato='completato'"); $s2->execute([$op]);
    $op_stats[$op] = ['totali'=>$s->fetchColumn(), 'chiusi'=>$s2->fetchColumn()];
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin - <?=$project_name?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f5f5f7;color:#333;min-height:100vh}
.container{max-width:960px;margin:0 auto;padding:20px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px}
.header h1{font-size:20px;font-weight:800;color:#111;display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap}
.btn-copy-site{background:<?=$accent?>;color:#fff;border:none;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:.3px;transition:all .15s;font-family:inherit}
.btn-copy-site:hover{filter:brightness(1.1);transform:translateY(-1px)}
.btn-copy-site.copied{background:#22c55e}
.header-links{display:flex;gap:12px;align-items:center}
.header-links a{font-size:12px;text-decoration:none;padding:5px 12px;border-radius:6px}
.tab-link{color:#888;background:#fff;border:1px solid #e8e8e8}
.tab-link.active{background:<?=$accent?>;color:#fff;border-color:<?=$accent?>}
.logout{color:#888!important;background:none!important;border:none!important}
.logout:hover{color:#ef4444!important}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,1fr));gap:6px;margin-bottom:14px}
.stat{background:#fff;border-radius:10px;padding:12px 8px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.stat .num{font-size:20px;font-weight:800}
.stat .lbl{font-size:8px;color:#999;text-transform:uppercase;font-weight:700;margin-top:2px}
.filters{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}
.filters a{padding:4px 10px;background:#fff;border:1px solid #e8e8e8;border-radius:6px;color:#888;font-size:10px;text-decoration:none;font-weight:600}
.filters a:hover,.filters a.active{background:<?=$accent?>;color:#fff;border-color:<?=$accent?>}
.wa-form{background:#fff;border-radius:10px;padding:12px;margin-bottom:14px;border-left:4px solid #25d366;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.wa-form .title{font-size:11px;font-weight:700;color:#25d366;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.wa-form .title svg{width:14px;height:14px}
.wa-form form{display:flex;flex-wrap:wrap;gap:5px}
.wa-form input,.wa-form select{padding:7px 8px;border:1px solid #e8e8e8;border-radius:6px;background:#fafafa;color:#333;font-size:11px;font-family:inherit;flex:1;min-width:70px}
.wa-form button{padding:7px 12px;background:#25d366;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer}
.lead{background:#fff;border-radius:10px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);border-left:4px solid #e8e8e8}
.lead.scaduto{border-left-color:#ef4444!important;background:#fff5f5}
.lead__top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:4px}
.lead__name{font-size:15px;font-weight:700;color:#111;display:flex;align-items:center;gap:5px}
.wa-icon{width:16px;height:16px;flex-shrink:0}
.lead__date{font-size:10px;color:#aaa}
.lead__email{font-size:11px;color:#888;margin-top:1px}
.lead__fields{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px}
.lead__field{background:#f9f9f9;padding:7px 10px;border-radius:6px}
.lead__field-label{font-size:8px;color:#aaa;text-transform:uppercase;font-weight:700}
.lead__field-value{font-size:12px;color:#333;font-weight:600;margin-top:1px}
.lead__field-value a{color:#333;text-decoration:none}
.lead__badges{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;align-items:center}
.badge{padding:2px 8px;border-radius:20px;font-size:9px;font-weight:700;text-transform:uppercase}
.badge-op{background:#e8e8e8;color:#555}
.lead__followup{font-size:10px;padding:4px 8px;border-radius:6px;margin-bottom:8px;display:inline-block}
.followup-ok{background:#f0fdf4;color:#059669}
.followup-scaduto{background:#fef2f2;color:#ef4444;font-weight:700}
.lead__actions{display:flex;gap:3px;flex-wrap:wrap;align-items:center}
.lead__actions select,.lead__actions input{padding:5px 6px;border:1px solid #e8e8e8;border-radius:5px;background:#fafafa;color:#333;font-size:10px;font-family:inherit}
.lead__actions input[type="text"]{flex:1;min-width:80px}
.lead__actions input[type="datetime-local"]{width:150px;font-size:10px}
.btn-sm{padding:4px 10px;border:none;border-radius:5px;font-size:9px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-blue{background:<?=$accent?>;color:#fff}
.btn-red{background:#fff;color:#ef4444;border:1px solid #fecaca}
.btn-wa{background:#25d366;color:#fff;text-decoration:none;padding:4px 8px;border-radius:5px;font-size:9px;font-weight:700;display:inline-flex;align-items:center;gap:3px}
.btn-wa-r{background:#dc2626;color:#fff;text-decoration:none;padding:4px 8px;border-radius:5px;font-size:9px;font-weight:700;display:inline-flex;align-items:center;gap:3px;white-space:nowrap}
.btn-wa-r:hover{background:#b91c1c}
.lead__recs{margin-top:8px;padding-top:8px;border-top:1px dashed #eee}
.rec-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.btn-rec{background:#dc2626;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-family:inherit}
.btn-rec:hover{background:#b91c1c}
.btn-rec.recording{background:#7f1d1d;animation:pulse-rec 1.2s infinite}
.btn-rec:disabled{opacity:.6;cursor:wait}
@keyframes pulse-rec{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.6)}50%{box-shadow:0 0 0 10px rgba(220,38,38,0)}}
.rec-item{display:flex;align-items:center;gap:6px;padding:3px 0;font-size:11px;color:#666}
.rec-item audio{height:30px;flex:1;min-width:0;max-width:280px}
.rec-item__meta{white-space:nowrap;font-size:10px;color:#999}
.rec-item__dl{color:#06b6d4;text-decoration:none;font-size:13px;padding:2px 4px}
.rec-item__del{background:transparent;border:none;cursor:pointer;color:#999;padding:2px 4px;font-size:12px}
.rec-item__del:hover{color:#ef4444}
.empty{text-align:center;padding:40px;color:#aaa;font-size:13px}
.log{margin-top:6px;border-top:1px solid #f0f0f0;padding-top:6px}
.log-item{font-size:9px;color:#aaa;padding:1px 0}
.log-item b{color:#888}
/* Team tab */
.team-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:14px}
.team-card{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;justify-content:space-between;align-items:center}
.team-card__name{font-size:14px;font-weight:700;color:#111}
.team-card__stats{font-size:11px;color:#888}
.team-card__del{background:none;border:none;color:#ccc;cursor:pointer;font-size:16px}
.team-card__del:hover{color:#ef4444}
.team-add{display:flex;gap:6px;margin-top:14px}
.team-add input{padding:10px 14px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;flex:1;font-family:inherit;outline:none}
.team-add button{padding:10px 20px;background:<?=$accent?>;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
/* REMINDER BAR */
.reminder-bar{background:#fff;border:1px solid #fecaca;border-left:4px solid #ef4444;border-radius:10px;padding:12px 14px;margin-bottom:14px;box-shadow:0 2px 8px rgba(239,68,68,.08);display:none}
.reminder-bar.active{display:block;animation:pulse-border 2s infinite}
@keyframes pulse-border{0%,100%{border-color:#fecaca;box-shadow:0 2px 8px rgba(239,68,68,.08)}50%{border-color:#ef4444;box-shadow:0 2px 12px rgba(239,68,68,.15)}}
.reminder-bar__title{font-size:11px;font-weight:800;color:#ef4444;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.reminder-bar__title svg{width:16px;height:16px}
.reminder-list{display:flex;flex-direction:column;gap:6px}
.reminder-item{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fef2f2;border-radius:8px;gap:8px;flex-wrap:wrap}
.reminder-item__info{display:flex;flex-direction:column;gap:2px}
.reminder-item__name{font-size:13px;font-weight:700;color:#111}
.reminder-item__time{font-size:10px;color:#ef4444;font-weight:600}
.reminder-item__op{font-size:10px;color:#888}
.reminder-item__actions{display:flex;gap:4px;align-items:center}
.reminder-item__actions a{padding:4px 10px;border-radius:5px;font-size:9px;font-weight:700;text-decoration:none;color:#fff}

/* SEARCH */
.search-bar{position:relative;margin-bottom:14px}
.search-bar input{width:100%;padding:10px 14px 10px 36px;border:1px solid #e0e0e0;border-radius:10px;font-size:13px;font-family:inherit;outline:none;background:#fff}
.search-bar input:focus{border-color:<?=$accent?>;box-shadow:0 0 0 3px <?=$accent?>15}
.search-bar svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#aaa}
.search-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e0e0e0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:100;max-height:400px;overflow-y:auto;display:none;margin-top:4px}
.search-results.open{display:block}
.sr-section{padding:8px 14px;font-size:10px;font-weight:700;color:#888;text-transform:uppercase;background:#f9f9f9;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
.sr-section a{font-size:10px;color:<?=$accent?>;text-decoration:none;font-weight:700}
.sr-item{padding:10px 14px;border-bottom:1px solid #f5f5f5;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
.sr-item:hover{background:#f9f9f9}
.sr-item:last-child{border-bottom:none}
.sr-name{font-size:13px;font-weight:600;color:#111}
.sr-tel{font-size:11px;color:#888}
.sr-badge{font-size:9px;padding:2px 8px;border-radius:10px;font-weight:700}
.sr-empty{padding:20px;text-align:center;color:#aaa;font-size:12px}
.sr-goto{padding:4px 10px;background:<?=$accent?>;color:#fff;border-radius:6px;font-size:10px;font-weight:700;text-decoration:none;white-space:nowrap}
@media(max-width:600px){.container{padding:10px}.lead__fields{grid-template-columns:1fr}.stats{grid-template-columns:repeat(3,1fr)}.lead__actions{flex-direction:column;align-items:stretch}.lead__actions form{display:flex;gap:3px}}
</style></head><body>
<div class="container">
  <?php $site_url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/'; ?>
  <div class="header">
    <h1><?=$project_name?> <button type="button" class="btn-copy-site" onclick="copySite(this,'<?=htmlspecialchars($site_url,ENT_QUOTES)?>')" title="Copia il link del sito">📋 Copia Link Sito</button></h1>
    <div class="header-links">
      <a href="?tab=leads" class="tab-link <?=$tab==='leads'?'active':''?>">Lead</a>
      <a href="?tab=team" class="tab-link <?=$tab==='team'?'active':''?>">Team</a>
      <a href="?tab=prodotto" class="tab-link <?=$tab==='prodotto'?'active':''?>">Prodotto</a>
      <a href="?tab=logistica" class="tab-link <?=$tab==='logistica'?'active':''?>">Logistica</a>
      <a href="?tab=sop" class="tab-link <?=$tab==='sop'?'active':''?>">SOP</a>
      <a href="?logout=1" class="logout">Esci</a>
    </div>
  </div>

  <div class="search-bar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" id="global-search" placeholder="Cerca numero di telefono..." oninput="globalSearch(this.value)">
    <div class="search-results" id="search-results"></div>
  </div>

  <?php
  // Reminder: lead da ricontattare ora o scaduti
  $now = date('Y-m-d H:i:s');
  $soon = date('Y-m-d H:i:s', strtotime('+30 minutes'));
  $reminders = $pdo->query("SELECT * FROM candidature WHERE prossimo_contatto IS NOT NULL AND prossimo_contatto <= '$soon' AND stato NOT IN ('completato','annullato') ORDER BY prossimo_contatto ASC")->fetchAll(PDO::FETCH_ASSOC);
  if (!empty($reminders)): ?>
  <div class="reminder-bar active">
    <div class="reminder-bar__title">
      <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Reminder — <?=count($reminders)?> lead da contattare
    </div>
    <div class="reminder-list">
      <?php foreach($reminders as $r):
        $scaduto = strtotime($r['prossimo_contatto']) < time();
        $tempo = $scaduto ? 'SCADUTO da '.round((time()-strtotime($r['prossimo_contatto']))/60).' min' : 'Tra '.round((strtotime($r['prossimo_contatto'])-time())/60).' min';
      ?>
      <div class="reminder-item">
        <div class="reminder-item__info">
          <div class="reminder-item__name"><?=htmlspecialchars($r['nome'].' '.$r['cognome'])?> — <?=htmlspecialchars($r['telefono'])?></div>
          <div class="reminder-item__time"><?=$tempo?> (<?=date('H:i', strtotime($r['prossimo_contatto']))?>)</div>
          <?php if(!empty($r['assegnato'])): ?><div class="reminder-item__op">Assegnato a: <b><?=htmlspecialchars($r['assegnato'])?></b></div><?php endif; ?>
        </div>
        <div class="reminder-item__actions">
          <a href="tel:<?=htmlspecialchars($r['telefono'])?>" style="background:#059669">Chiama</a>
          <a href="<?=wa_link($r['telefono']??'')?>" target="_blank" style="background:#25d366">WA</a>
          <a href="#lead-<?=$r['id']?>" style="background:<?=$accent?>" onclick="document.getElementById('lead-<?=$r['id']?>').scrollIntoView({block:'center'})">Vai</a>
          <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Eliminare questo reminder?')">
            <input type="hidden" name="cid" value="<?=$r['id']?>">
            <button type="submit" name="dismiss_reminder" value="1" title="Elimina reminder" style="background:#9ca3af;border:none;color:#fff;padding:4px 8px;border-radius:5px;font-size:9px;font-weight:700;cursor:pointer;font-family:inherit">✕</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

<?php if ($tab === 'logistica'): ?>
<?php
$sped_stati = ['da_evadere'=>'Da Evadere','in_preparazione'=>'In Preparazione','spedito'=>'Spedito','in_transito'=>'In Transito','in_giacenza'=>'In Giacenza','svincolo'=>'Svincolo','consegnato'=>'Consegnato','reso'=>'Reso'];
$sped_color = ['da_evadere'=>'#eab308','in_preparazione'=>'#f97316','spedito'=>'#3b82f6','in_transito'=>'#8b5cf6','in_giacenza'=>'#ef4444','svincolo'=>'#f59e0b','consegnato'=>'#22c55e','reso'=>'#9ca3af'];
$lfilter = $_GET['lfilter'] ?? 'tutti';
$lwhere = '';
if ($lfilter !== 'tutti' && array_key_exists($lfilter, $sped_stati)) $lwhere = "WHERE stato_spedizione='$lfilter'";
elseif (strpos($lfilter,'op_')===0) { $lopf=substr($lfilter,3); $lwhere = "WHERE operatore='".addslashes($lopf)."'"; }
// Stats per operatore logistica
$op_log_stats = [];
foreach ($op_names as $opn) {
    $s1 = $pdo->prepare("SELECT COUNT(*) FROM logistica WHERE operatore=?"); $s1->execute([$opn]);
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM logistica WHERE operatore=? AND stato_spedizione='consegnato'"); $s2->execute([$opn]);
    $s3 = $pdo->prepare("SELECT COUNT(*) FROM logistica WHERE operatore=? AND stato_spedizione='reso'"); $s3->execute([$opn]);
    $op_log_stats[$opn] = ['totali'=>$s1->fetchColumn(), 'consegnati'=>$s2->fetchColumn(), 'resi'=>$s3->fetchColumn()];
}
$ordini = $pdo->query("SELECT * FROM logistica $lwhere ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$log_tot = $pdo->query("SELECT COUNT(*) FROM logistica")->fetchColumn();
$log_evadere = $pdo->query("SELECT COUNT(*) FROM logistica WHERE stato_spedizione='da_evadere'")->fetchColumn();
$log_spediti = $pdo->query("SELECT COUNT(*) FROM logistica WHERE stato_spedizione='spedito' OR stato_spedizione='in_transito'")->fetchColumn();
$log_giacenza = $pdo->query("SELECT COUNT(*) FROM logistica WHERE stato_spedizione='in_giacenza'")->fetchColumn();
$log_consegnati = $pdo->query("SELECT COUNT(*) FROM logistica WHERE stato_spedizione='consegnato'")->fetchColumn();
$log_resi = $pdo->query("SELECT COUNT(*) FROM logistica WHERE stato_spedizione='reso'")->fetchColumn();
// Get uploaded PDFs for product attachment
$upload_dir_log = __DIR__ . '/uploads/';
$pdf_files = glob($upload_dir_log . '*.pdf');
?>
  <h2 style="font-size:16px;color:#111;margin-bottom:4px">Logistica & Spedizioni</h2>
  <p style="font-size:12px;color:#888;margin-bottom:14px">I lead confermati appaiono automaticamente qui</p>

  <div class="stats">
    <div class="stat"><div class="num"><?=$log_tot?></div><div class="lbl">Totali</div></div>
    <div class="stat"><div class="num" style="color:#eab308"><?=$log_evadere?></div><div class="lbl">Da Evadere</div></div>
    <div class="stat"><div class="num" style="color:#3b82f6"><?=$log_spediti?></div><div class="lbl">Spediti</div></div>
    <div class="stat"><div class="num" style="color:#ef4444"><?=$log_giacenza?></div><div class="lbl">Giacenza</div></div>
    <div class="stat"><div class="num" style="color:#22c55e"><?=$log_consegnati?></div><div class="lbl">Consegnati</div></div>
    <div class="stat"><div class="num" style="color:#9ca3af"><?=$log_resi?></div><div class="lbl">Resi</div></div>
  </div>

  <?php if(!empty($op_log_stats)): ?>
  <div style="background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:14px">
    <div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:8px">Performance Operatori</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:6px">
      <?php foreach($op_log_stats as $opn=>$ops): ?>
      <div style="background:#f9f9f9;padding:10px;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:12px;font-weight:700;color:#111"><?=htmlspecialchars($opn)?></div>
          <div style="font-size:9px;color:#888"><?=$ops['totali']?> ordini</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:12px;font-weight:800;color:#22c55e"><?=$ops['consegnati']?> <span style="font-size:8px;color:#888">ok</span></div>
          <?php if($ops['resi']>0): ?><div style="font-size:10px;color:#ef4444"><?=$ops['resi']?> resi</div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="filters">
    <a href="?tab=logistica&lfilter=tutti" class="<?=$lfilter==='tutti'?'active':''?>">Tutti</a>
    <?php foreach($sped_stati as $sk=>$sl): ?>
    <a href="?tab=logistica&lfilter=<?=$sk?>" class="<?=$lfilter===$sk?'active':''?>"><?=$sl?></a>
    <?php endforeach; ?>
    <?php foreach($op_names as $opn): ?>
    <a href="?tab=logistica&lfilter=op_<?=urlencode($opn)?>" class="<?=$lfilter==='op_'.$opn?'active':''?>" style="border-left:3px solid <?=$accent?>"><?=htmlspecialchars($opn)?></a>
    <?php endforeach; ?>
  </div>

  <?php if(empty($ordini)): ?>
    <div class="empty">Nessun ordine in logistica. I lead confermati appariranno qui automaticamente.</div>
  <?php endif; ?>

  <?php foreach($ordini as $o):
    $sc = $sped_color[$o['stato_spedizione']] ?? '#999';
    $is_giacenza = $o['stato_spedizione'] === 'in_giacenza';
  ?>
  <div id="log-<?=$o['id']?>" style="background:#fff;border-radius:12px;padding:16px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,.04);border-left:4px solid <?=$sc?>;<?=$is_giacenza?'background:#fff5f5;':''?>">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;gap:6px">
      <div>
        <div style="font-size:16px;font-weight:700;color:#111"><?=htmlspecialchars($o['nome'].' '.$o['cognome'])?></div>
        <div style="font-size:11px;color:#888"><?=htmlspecialchars($o['email'])?></div>
      </div>
      <div style="text-align:right">
        <span style="padding:3px 10px;border-radius:20px;font-size:9px;font-weight:700;background:<?=$sc?>20;color:<?=$sc?>;text-transform:uppercase"><?=$sped_stati[$o['stato_spedizione']]??$o['stato_spedizione']?></span>
        <?php if(!empty($o['operatore'])): ?><span style="padding:3px 10px;border-radius:20px;font-size:9px;font-weight:700;background:#e8e8e8;color:#555"><?=htmlspecialchars($o['operatore'])?></span><?php endif; ?>
        <div style="font-size:10px;color:#aaa;margin-top:4px">#<?=$o['id']?> · <?=date('d/m/Y', strtotime($o['created_at']))?></div>
      </div>
    </div>

    <!-- Info cliente -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:10px">
      <div style="background:#f9f9f9;padding:8px 10px;border-radius:6px"><div style="font-size:8px;color:#aaa;text-transform:uppercase;font-weight:700">Telefono</div><div style="font-size:12px;font-weight:600;color:#333;margin-top:2px"><a href="tel:<?=htmlspecialchars($o['telefono'])?>" style="color:#333;text-decoration:none"><?=htmlspecialchars($o['telefono'])?></a></div></div>
      <div style="background:#f9f9f9;padding:8px 10px;border-radius:6px"><div style="font-size:8px;color:#aaa;text-transform:uppercase;font-weight:700">Pagamento</div><div style="font-size:12px;font-weight:600;color:#333;margin-top:2px"><?=ucfirst($o['metodo_pagamento'])?></div></div>
      <div style="background:#f9f9f9;padding:8px 10px;border-radius:6px"><div style="font-size:8px;color:#aaa;text-transform:uppercase;font-weight:700">Tracking</div><div style="font-size:12px;font-weight:600;color:<?=$o['tracking']?'#3b82f6':'#ccc'?>;margin-top:2px"><?=$o['tracking']?htmlspecialchars($o['tracking']):'—'?></div></div>
    </div>

    <!-- Indirizzo -->
    <div style="background:#f0f7ff;padding:10px 12px;border-radius:8px;margin-bottom:10px;border:1px solid #e0eeff">
      <div style="font-size:8px;color:#3b82f6;text-transform:uppercase;font-weight:700;margin-bottom:4px">Indirizzo Spedizione</div>
      <?php if(!empty($o['indirizzo'])): ?>
        <div style="font-size:13px;color:#333;font-weight:600"><?=htmlspecialchars($o['indirizzo'])?>, <?=htmlspecialchars($o['cap'])?> <?=htmlspecialchars($o['citta'])?> (<?=htmlspecialchars($o['provincia'])?>)</div>
      <?php else: ?>
        <div style="font-size:11px;color:#999">Indirizzo non inserito</div>
      <?php endif; ?>
      <form method="POST" style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap">
        <input type="hidden" name="lid" value="<?=$o['id']?>">
        <input type="text" name="indirizzo" placeholder="Via e numero" value="<?=htmlspecialchars($o['indirizzo'])?>" style="flex:2;min-width:120px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="cap" placeholder="CAP" value="<?=htmlspecialchars($o['cap'])?>" style="width:55px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="citta_sped" placeholder="Citta" value="<?=htmlspecialchars($o['citta'])?>" style="flex:1;min-width:80px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="prov_sped" placeholder="Prov" value="<?=htmlspecialchars($o['provincia'])?>" style="width:40px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <button type="submit" name="update_indirizzo" value="1" class="btn-sm btn-blue">Salva</button>
      </form>
    </div>

    <!-- Actions row -->
    <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
      <!-- Stato spedizione -->
      <form method="POST" style="display:flex;gap:3px;align-items:center">
        <input type="hidden" name="lid" value="<?=$o['id']?>">
        <select name="stato_sped" style="padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:10px;font-family:inherit;background:#fafafa;color:#333">
          <?php foreach($sped_stati as $sk=>$sl): ?><option value="<?=$sk?>" <?=$o['stato_spedizione']===$sk?'selected':''?>><?=$sl?></option><?php endforeach; ?>
        </select>
        <button type="submit" name="update_spedizione" value="1" class="btn-sm btn-blue">Stato</button>
      </form>

      <!-- Tracking -->
      <form method="POST" style="display:flex;gap:3px;align-items:center;flex:1">
        <input type="hidden" name="lid" value="<?=$o['id']?>">
        <input type="text" name="corriere" placeholder="Corriere" value="<?=htmlspecialchars($o['corriere'])?>" style="width:70px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:10px;font-family:inherit;outline:none">
        <input type="text" name="tracking" placeholder="Codice tracking" value="<?=htmlspecialchars($o['tracking'])?>" style="flex:1;min-width:80px;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:10px;font-family:inherit;outline:none">
        <button type="submit" name="update_tracking" value="1" class="btn-sm btn-blue">Track</button>
      </form>

      <!-- WA + Elimina -->
      <a href="<?=wa_link($o['telefono']??'')?>" target="_blank" class="btn-wa"><svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>WA</a>
      <?php if(!empty($pdf_files)): ?>
      <a href="uploads/<?=urlencode(basename($pdf_files[0]))?>" target="_blank" style="padding:4px 8px;background:#ef4444;color:#fff;border-radius:5px;font-size:9px;font-weight:700;text-decoration:none">PDF</a>
      <?php endif; ?>
      <form method="POST" onsubmit="return confirm('Eliminare?')" style="display:inline">
        <input type="hidden" name="lid" value="<?=$o['id']?>">
        <button type="submit" name="del_logistica" class="btn-sm btn-red">X</button>
      </form>
    </div>

    <!-- Note logistica -->
    <form method="POST" style="display:flex;gap:3px;align-items:center">
      <input type="hidden" name="lid" value="<?=$o['id']?>">
      <input type="text" name="note_log" placeholder="Note logistica..." value="<?=htmlspecialchars($o['note_logistica']??'')?>" style="flex:1;padding:6px 8px;border:1px solid #e0e0e0;border-radius:5px;font-size:10px;font-family:inherit;outline:none">
      <button type="submit" name="update_note_log" value="1" class="btn-sm btn-blue">Nota</button>
    </form>
  </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'sop'): ?>
  <h2 style="font-size:16px;color:#111;margin-bottom:4px">SOP — Procedure Operative</h2>
  <p style="font-size:12px;color:#888;margin-bottom:14px">Documenti e procedure che gli operatori devono seguire</p>

  <form method="POST" style="background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
    <div style="flex:1;min-width:150px">
      <label style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px">Titolo SOP</label>
      <input type="text" name="sop_titolo" placeholder="Es: Script Chiamata Primo Contatto" required style="width:100%;padding:10px 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;font-family:inherit;outline:none">
    </div>
    <div style="flex:1;min-width:200px">
      <label style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px">Link Google Drive / Docs</label>
      <input type="url" name="sop_link" placeholder="https://docs.google.com/..." required style="width:100%;padding:10px 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;font-family:inherit;outline:none">
    </div>
    <div style="flex:1;min-width:150px">
      <label style="font-size:10px;color:#888;font-weight:700;text-transform:uppercase;display:block;margin-bottom:4px">Descrizione (opzionale)</label>
      <input type="text" name="sop_desc" placeholder="Breve descrizione..." style="width:100%;padding:10px 12px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;font-family:inherit;outline:none">
    </div>
    <button type="submit" name="add_sop" value="1" style="padding:10px 20px;background:<?=$accent?>;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap">+ Aggiungi SOP</button>
  </form>

  <?php
  $sops = $pdo->query("SELECT * FROM sop ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
  if (empty($sops)): ?>
    <div style="text-align:center;padding:30px;color:#aaa;font-size:13px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04)">Nessuna SOP aggiunta</div>
  <?php else: ?>
    <?php foreach ($sops as $sop): ?>
    <div style="background:#fff;border-radius:10px;padding:16px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-left:4px solid <?=$accent?>">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="<?=$accent?>" stroke-width="1.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <div style="flex:1;min-width:150px">
        <div style="font-size:14px;font-weight:700;color:#111"><?=htmlspecialchars($sop['titolo'])?></div>
        <?php if(!empty($sop['descrizione'])): ?><div style="font-size:11px;color:#888;margin-top:2px"><?=htmlspecialchars($sop['descrizione'])?></div><?php endif; ?>
        <div style="font-size:10px;color:#aaa;margin-top:4px">Aggiunto il <?=date('d/m/Y', strtotime($sop['created_at']))?></div>
      </div>
      <a href="<?=htmlspecialchars($sop['link'])?>" target="_blank" style="padding:8px 16px;background:<?=$accent?>;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Apri Documento
      </a>
      <form method="POST" onsubmit="return confirm('Eliminare questa SOP?')" style="display:inline">
        <input type="hidden" name="sop_id" value="<?=$sop['id']?>">
        <button type="submit" name="del_sop" style="padding:8px 14px;background:#fff;color:#ef4444;border:1px solid #fecaca;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit">Elimina</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php elseif ($tab === 'prodotto'): ?>
  <h2 style="font-size:16px;color:#111;margin-bottom:4px">Prodotto Digitale</h2>
  <p style="font-size:12px;color:#888;margin-bottom:14px">Carica il PDF del prodotto per la stampa e spedizione</p>

  <div style="background:<?=$accent?>10;border:1px solid <?=$accent?>40;border-radius:12px;padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="<?=$accent?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20v1"/></svg>
    <div style="flex:1;min-width:200px">
      <div style="font-size:14px;font-weight:700;color:#111;margin-bottom:2px">Riepilogo iscrizione (A4) con QR portale</div>
      <div style="font-size:12px;color:#666">Foglio stampabile da inserire nel pacco: benvenuto + contenuto + QR per accedere al portale corsi</div>
    </div>
    <a href="riepilogo.php" target="_blank" style="padding:10px 18px;background:<?=$accent?>;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none">Apri / Stampa</a>
  </div>

  <form method="POST" enctype="multipart/form-data" style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:16px;border:2px dashed #e0e0e0">
    <div style="text-align:center;padding:20px 0">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      <p style="font-size:13px;color:#888;margin:8px 0">Seleziona un file PDF (max 50MB)</p>
      <input type="file" name="pdf_file" accept=".pdf" required style="margin:10px 0;font-family:inherit;font-size:12px">
      <br>
      <button type="submit" name="upload_prodotto" value="1" style="padding:10px 24px;background:<?=$accent?>;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:8px">Carica PDF</button>
    </div>
  </form>

  <h3 style="font-size:14px;color:#111;margin-bottom:10px">File Caricati</h3>
  <?php
  $files = glob($upload_dir . '*.pdf');
  usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
  if (empty($files)): ?>
    <div style="text-align:center;padding:30px;color:#aaa;font-size:13px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04)">Nessun file caricato</div>
  <?php else: ?>
    <?php foreach ($files as $f):
      $fname = basename($f);
      $fsize = filesize($f);
      $fdate = date('d/m/Y H:i', filemtime($f));
      $size_str = $fsize > 1048576 ? round($fsize/1048576, 1).' MB' : round($fsize/1024).' KB';
    ?>
    <div style="background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <div style="flex:1;min-width:150px">
        <div style="font-size:13px;font-weight:700;color:#111;word-break:break-all"><?=htmlspecialchars($fname)?></div>
        <div style="font-size:10px;color:#888"><?=$size_str?> · <?=$fdate?></div>
      </div>
      <a href="uploads/<?=urlencode($fname)?>" target="_blank" style="padding:6px 14px;background:<?=$accent?>;color:#fff;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none">Scarica</a>
      <a href="uploads/<?=urlencode($fname)?>" target="_blank" style="padding:6px 14px;background:#f0f0f0;color:#333;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none">Visualizza</a>
      <form method="POST" onsubmit="return confirm('Eliminare questo file?')" style="display:inline">
        <input type="hidden" name="filename" value="<?=htmlspecialchars($fname)?>">
        <button type="submit" name="delete_file" style="padding:6px 14px;background:#fff;color:#ef4444;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit">Elimina</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php elseif ($tab === 'team'): ?>
  <h2 style="font-size:16px;color:#111;margin-bottom:4px">Gestione Operatori</h2>
  <p style="font-size:12px;color:#888;margin-bottom:14px">Aggiungi o rimuovi le persone che gestiscono i lead</p>
  <div class="team-grid">
    <?php foreach($operatori as $op): ?>
    <div class="team-card">
      <div>
        <div class="team-card__name"><?=htmlspecialchars($op['nome'])?></div>
        <div class="team-card__stats"><?=$op_stats[$op['nome']]['totali']??0?> lead · <?=$op_stats[$op['nome']]['chiusi']??0?> chiusi</div>
      </div>
      <form method="POST" onsubmit="return confirm('Rimuovere?')"><input type="hidden" name="op_id" value="<?=$op['id']?>"><button type="submit" name="del_operatore" class="team-card__del">&times;</button></form>
    </div>
    <?php endforeach; ?>
    <?php if(empty($operatori)): ?><div class="empty" style="padding:20px">Nessun operatore aggiunto</div><?php endif; ?>
  </div>
  <form method="POST" class="team-add">
    <input type="text" name="nome_operatore" placeholder="Nome operatore..." required>
    <button type="submit" name="add_operatore" value="1">+ Aggiungi</button>
  </form>

<?php else: ?>
  <div class="stats">
    <div class="stat"><div class="num"><?=$totali?></div><div class="lbl">Totali</div></div>
    <div class="stat"><div class="num" style="color:#eab308"><?=$oggi_count?></div><div class="lbl">Oggi</div></div>
    <div class="stat"><div class="num" style="color:#3b82f6"><?=$nuovi?></div><div class="lbl">Nuovi</div></div>
    <div class="stat"><div class="num" style="color:#ef4444"><?=$non_risponde?></div><div class="lbl">Non Risp.</div></div>
    <div class="stat"><div class="num" style="color:#f97316"><?=$da_ricontattare?></div><div class="lbl">Da Ricont.</div></div>
    <div class="stat"><div class="num" style="color:<?=$scaduti>0?'#ef4444':'#999'?>"><?=$scaduti?></div><div class="lbl">Scaduti</div></div>
    <div class="stat"><div class="num" style="color:#22c55e"><?=$confermati?></div><div class="lbl">Confermati</div></div>
    <div class="stat"><div class="num" style="color:#9ca3af"><?=$annullati?></div><div class="lbl">Annullati</div></div>
    <?php foreach($op_names as $opn): ?><div class="stat"><div class="num"><?=$op_stats[$opn]['totali']?></div><div class="lbl"><?=htmlspecialchars($opn)?></div></div><?php endforeach; ?>
  </div>
  <div class="filters">
    <a href="?filter=tutti" class="<?=$filter==='tutti'?'active':''?>">Tutti</a>
    <a href="?filter=oggi" class="<?=$filter==='oggi'?'active':''?>">Oggi</a>
    <a href="?filter=nuovo" class="<?=$filter==='nuovo'?'active':''?>">Nuovi</a>
    <a href="?filter=contattato" class="<?=$filter==='contattato'?'active':''?>">Contattati</a>
    <a href="?filter=non_risponde" class="<?=$filter==='non_risponde'?'active':''?>">Non Risponde</a>
    <a href="?filter=da_ricontattare" class="<?=$filter==='da_ricontattare'?'active':''?>">Da Ricontattare</a>
    <a href="?filter=confermato" class="<?=$filter==='confermato'?'active':''?>">Confermati</a>
    <a href="?filter=annullato" class="<?=$filter==='annullato'?'active':''?>">Annullati</a>
    <a href="?filter=scaduti" class="<?=$filter==='scaduti'?'active':''?>" style="<?=$scaduti>0?'color:#ef4444;border-color:#fecaca':''?>">Scaduti (<?=$scaduti?>)</a>
    <a href="?filter=whatsapp" class="<?=$filter==='whatsapp'?'active':''?>">WhatsApp</a>
    <?php foreach($op_names as $opn): ?><a href="?filter=op_<?=urlencode($opn)?>" class="<?=$filter==='op_'.$opn?'active':''?>"><?=htmlspecialchars($opn)?></a><?php endforeach; ?>
  </div>
  <div class="wa-form">
    <div class="title"><svg viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg> Aggiungi Lead da WhatsApp</div>
    <form method="POST">
      <input type="text" name="m_nome" placeholder="Nome" required>
      <input type="text" name="m_cognome" placeholder="Cognome">
      <input type="tel" name="m_telefono" placeholder="Telefono" required>
      <input type="email" name="m_email" placeholder="Email">
      <input type="text" name="m_citta" placeholder="Citta">
      <input type="text" name="m_provincia" placeholder="Prov" style="max-width:40px">
      <select name="m_pagamento"><option value="contrassegno">COD</option><option value="bonifico">Bonifico</option></select>
      <button type="submit" name="add_manual" value="1">+</button>
    </form>
  </div>

  <?php foreach ($leads as $c):
    $is_wa = ($c['fonte']??'')==='whatsapp';
    $sc = $stato_color[$c['stato']] ?? '#999';
    $has_followup = !empty($c['prossimo_contatto']);
    $is_scaduto = $has_followup && strtotime($c['prossimo_contatto']) < time() && !in_array($c['stato'],['completato','annullato']);
    // Get log for this lead
    $log_stmt = $pdo->prepare("SELECT * FROM log_attivita WHERE lead_id=? ORDER BY created_at DESC LIMIT 5");
    $log_stmt->execute([$c['id']]);
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="lead <?=$is_scaduto?'scaduto':''?>" id="lead-<?=$c['id']?>" style="border-left-color:<?=$sc?>">
    <div class="lead__top">
      <div style="flex:1;min-width:0">
        <div id="nview-<?=$c['id']?>">
          <div class="lead__name">
            <?php if($is_wa): ?><svg class="wa-icon" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg><?php endif; ?>
            <?=htmlspecialchars($c['nome'].' '.$c['cognome'])?>
            <button type="button" onclick="document.getElementById('nedit-<?=$c['id']?>').style.display='flex';document.getElementById('nview-<?=$c['id']?>').style.display='none'" title="Modifica anagrafica" style="margin-left:6px;background:transparent;border:none;cursor:pointer;color:#999;padding:2px 4px;font-size:12px;vertical-align:middle">✏</button>
          </div>
          <?php if(!empty($c['email'])): ?><div class="lead__email"><?=htmlspecialchars($c['email'])?></div><?php endif; ?>
        </div>
        <form method="POST" id="nedit-<?=$c['id']?>" style="display:none;gap:4px;flex-wrap:wrap;align-items:center">
          <input type="hidden" name="cid" value="<?=$c['id']?>">
          <input type="text" name="nome" value="<?=htmlspecialchars($c['nome']??'')?>" placeholder="Nome" style="flex:1;min-width:90px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:12px;font-family:inherit;outline:none">
          <input type="text" name="cognome" value="<?=htmlspecialchars($c['cognome']??'')?>" placeholder="Cognome" style="flex:1;min-width:90px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:12px;font-family:inherit;outline:none">
          <input type="email" name="email" value="<?=htmlspecialchars($c['email']??'')?>" placeholder="Email" style="flex:1.5;min-width:140px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:12px;font-family:inherit;outline:none">
          <input type="text" name="telefono" value="<?=htmlspecialchars($c['telefono']??'')?>" placeholder="Telefono" style="flex:1;min-width:110px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:12px;font-family:inherit;outline:none">
          <button type="submit" name="update_anagrafica" value="1" class="btn-sm btn-blue">Salva</button>
          <button type="button" onclick="document.getElementById('nedit-<?=$c['id']?>').style.display='none';document.getElementById('nview-<?=$c['id']?>').style.display='block'" class="btn-sm" style="background:#eee;color:#333">Annulla</button>
        </form>
      </div>
      <div class="lead__date">#<?=$c['id']?> · <?=date('d/m/Y H:i', strtotime($c['created_at']))?></div>
    </div>
    <div class="lead__fields">
      <div class="lead__field"><div class="lead__field-label">Telefono</div><div class="lead__field-value"><a href="tel:<?=htmlspecialchars($c['telefono'])?>"><?=htmlspecialchars($c['telefono'])?></a></div></div>
      <div class="lead__field"><div class="lead__field-label">Citta</div><div class="lead__field-value"><?=htmlspecialchars(($c['citta']??'').(!empty($c['provincia'])?' ('.$c['provincia'].')':''))?></div></div>
    </div>
    <div class="lead__field" style="background:#fafafa;border:1px solid #eee;border-radius:6px;padding:6px 8px;margin-bottom:6px">
      <div style="font-size:9px;color:#888;text-transform:uppercase;font-weight:700;margin-bottom:4px">Indirizzo</div>
      <?php if(!empty($c['indirizzo']) || !empty($c['cap'])): ?>
        <div style="font-size:12px;color:#333;margin-bottom:4px"><?=htmlspecialchars($c['indirizzo']??'')?><?=!empty($c['cap'])?', '.htmlspecialchars($c['cap']):''?><?=!empty($c['citta'])?' '.htmlspecialchars($c['citta']):''?><?=!empty($c['provincia'])?' ('.htmlspecialchars($c['provincia']).')':''?></div>
      <?php else: ?>
        <div style="font-size:11px;color:#bbb;margin-bottom:4px">Nessun indirizzo inserito</div>
      <?php endif; ?>
      <form method="POST" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="cid" value="<?=$c['id']?>">
        <input type="text" name="indirizzo" placeholder="Via e numero" value="<?=htmlspecialchars($c['indirizzo']??'')?>" style="flex:2;min-width:140px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="cap" placeholder="CAP" value="<?=htmlspecialchars($c['cap']??'')?>" maxlength="5" style="width:60px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="citta_lead" placeholder="Citta" value="<?=htmlspecialchars($c['citta']??'')?>" style="flex:1;min-width:100px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none">
        <input type="text" name="provincia_lead" placeholder="Prov" value="<?=htmlspecialchars($c['provincia']??'')?>" maxlength="2" style="width:50px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:5px;font-size:11px;font-family:inherit;outline:none;text-transform:uppercase">
        <button type="submit" name="update_indirizzo_lead" value="1" class="btn-sm btn-blue">Salva</button>
      </form>
    </div>
    <div class="lead__badges">
      <span class="badge" style="background:<?=$sc?>20;color:<?=$sc?>"><?=$stato_label[$c['stato']]??ucfirst($c['stato'])?><?php if((int)($c['nr_count']??0)>0 && $c['stato']==='non_risponde'): ?> · NR <?=(int)$c['nr_count']?><?php endif; ?></span>
      <?php if((int)($c['nr_count']??0)>0 && $c['stato']!=='non_risponde'): ?><span class="badge" style="background:#fee2e2;color:#b91c1c" title="Tentativi NR storici">NR <?=(int)$c['nr_count']?></span><?php endif; ?>
      <?php if(!empty($c['metodo_pagamento'])): ?><span class="badge" style="background:#f0f0f0;color:#666"><?=ucfirst($c['metodo_pagamento'])?></span><?php endif; ?>
      <?php if(!empty($c['assegnato'])): ?><span class="badge badge-op"><?=htmlspecialchars($c['assegnato'])?></span><?php endif; ?>
    </div>
    <?php if($has_followup): ?>
    <div class="lead__followup <?=$is_scaduto?'followup-scaduto':'followup-ok'?>">
      <?=$is_scaduto?'SCADUTO — ':''?>Ricontattare: <?=date('d/m/Y H:i', strtotime($c['prossimo_contatto']))?>
    </div>
    <?php endif; ?>
    <?php if(!empty($c['note'])): ?><div style="font-size:11px;color:#666;padding:5px 8px;background:#f9f9f9;border-radius:6px;margin-bottom:6px"><?=htmlspecialchars($c['note'])?></div><?php endif; ?>
    <div class="lead__actions">
      <form method="POST" style="display:flex;gap:3px;align-items:center">
        <input type="hidden" name="cid" value="<?=$c['id']?>"><input type="hidden" name="operatore_azione" value="">
        <select name="nuovo_stato"><?php foreach($stati as $s): ?><option value="<?=$s?>" <?=$c['stato']===$s?'selected':''?>><?=$stato_label[$s]?></option><?php endforeach; ?></select>
        <button type="submit" name="update_stato" value="1" class="btn-sm btn-blue">Stato</button>
      </form>
      <form method="POST" style="display:flex;gap:3px;align-items:center">
        <input type="hidden" name="cid" value="<?=$c['id']?>">
        <select name="assegnato"><option value="">— Assegna —</option><?php foreach($op_names as $opn): ?><option value="<?=$opn?>" <?=($c['assegnato']??'')===$opn?'selected':''?>><?=$opn?></option><?php endforeach; ?></select>
        <button type="submit" name="update_assegnato" value="1" class="btn-sm btn-blue">Assegna</button>
      </form>
      <form method="POST" style="display:flex;gap:3px;align-items:center">
        <input type="hidden" name="cid" value="<?=$c['id']?>">
        <input type="datetime-local" name="prossimo_contatto" value="<?=!empty($c['prossimo_contatto'])?date('Y-m-d\TH:i',strtotime($c['prossimo_contatto'])):''?>">
        <button type="submit" name="update_prossimo" value="1" class="btn-sm btn-blue">Follow</button>
      </form>
      <form method="POST" style="display:flex;gap:3px;align-items:center;flex:1">
        <input type="hidden" name="cid" value="<?=$c['id']?>"><input type="hidden" name="operatore_azione" value="">
        <input type="text" name="note" placeholder="Nota..." value="<?=htmlspecialchars($c['note']??'')?>">
        <button type="submit" name="update_note" value="1" class="btn-sm btn-blue">Nota</button>
      </form>
      <?php $reminder_msg = str_replace('{NOME}', trim($c['nome']??''), $reminder_template); ?>
      <a href="<?=wa_link($c['telefono']??'')?>" target="_blank" class="btn-wa"><svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>WA</a>
      <a href="<?=wa_link($c['telefono']??'', $reminder_msg)?>" target="_blank" class="btn-wa-r" title="Invia messaggio di reminder promozione"><svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>Reminder</a>
      <form method="POST" onsubmit="return confirm('Eliminare?')" style="display:inline">
        <input type="hidden" name="cid" value="<?=$c['id']?>">
        <button type="submit" name="elimina" class="btn-sm btn-red">X</button>
      </form>
    </div>
    <div class="lead__recs">
      <div class="rec-row">
        <button type="button" class="btn-rec" onclick="toggleRec(<?=$c['id']?>,this)">🎙️ Registra Chiamata</button>
      </div>
      <?php foreach (($recs_by_lead[$c['id']] ?? []) as $rec): ?>
        <div class="rec-item" id="rec-<?=$rec['id']?>">
          <audio controls preload="metadata" src="uploads/calls/<?=htmlspecialchars($rec['filename'])?>"></audio>
          <div class="rec-item__meta"><?=date('d/m H:i', strtotime($rec['created_at']))?> · <?=gmdate('i:s', (int)$rec['durata'])?></div>
          <a class="rec-item__dl" href="uploads/calls/<?=htmlspecialchars($rec['filename'])?>" download title="Scarica">⬇</a>
          <button type="button" class="rec-item__del" onclick="delRec(<?=$rec['id']?>)" title="Elimina">🗑</button>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if(!empty($logs)): ?>
    <div class="log">
      <?php foreach($logs as $l): ?><div class="log-item"><?=date('d/m H:i',strtotime($l['created_at']))?> <?php if($l['operatore']): ?><b><?=htmlspecialchars($l['operatore'])?></b> — <?php endif; ?><?=htmlspecialchars($l['azione'])?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if(empty($leads)): ?><div class="empty">Nessun lead</div><?php endif; ?>
<?php endif; ?>
</div>
<script>
function copySite(btn,url){
  var done=function(){var orig=btn.innerHTML;btn.innerHTML='✓ Copiato!';btn.classList.add('copied');setTimeout(function(){btn.innerHTML=orig;btn.classList.remove('copied')},1500)};
  if(navigator.clipboard && navigator.clipboard.writeText){navigator.clipboard.writeText(url).then(done).catch(function(){prompt('Copia manualmente:',url)})}
  else {var ta=document.createElement('textarea');ta.value=url;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done()}catch(e){prompt('Copia manualmente:',url)}document.body.removeChild(ta)}
}
if('scrollRestoration' in history) history.scrollRestoration='manual';
document.addEventListener('submit',function(){sessionStorage.setItem('adminScrollY',window.scrollY)});
window.addEventListener('DOMContentLoaded',function(){
  var y=sessionStorage.getItem('adminScrollY');
  if(y!==null){
    if(location.hash) history.replaceState(null,'',location.pathname+location.search);
    window.scrollTo(0,parseInt(y,10));
    sessionStorage.removeItem('adminScrollY');
    if(location.hash){var el=document.querySelector(location.hash);if(el){el.style.boxShadow='0 0 0 3px rgba(0,0,0,.08)';setTimeout(function(){el.style.boxShadow=''},2000)}}
  } else if(location.hash){
    var el=document.querySelector(location.hash);
    if(el){el.scrollIntoView({block:'nearest'});el.style.boxShadow='0 0 0 3px rgba(0,0,0,.08)';setTimeout(function(){el.style.boxShadow=''},2000)}
  }
});

var searchTimer;
var projects=[
  {name:'Segretarie',url:'https://aliceblue-dragonfly-326952.hostingersite.com',color:'#1a3a6b'},
  {name:'Corso Unghie',url:'https://mediumturquoise-mule-624710.hostingersite.com',color:'#e91e8c'},
  {name:'Lash Art Academy',url:'https://darkred-koala-809285.hostingersite.com',color:'#c9a96e'}
];
function globalSearch(q){
  clearTimeout(searchTimer);
  var box=document.getElementById('search-results');
  q=q.replace(/[^0-9]/g,'');
  if(q.length<4){box.classList.remove('open');return}
  searchTimer=setTimeout(function(){
    box.innerHTML='<div class="sr-empty">Ricerca...</div>';box.classList.add('open');
    var done=0,html='';
    projects.forEach(function(p){
      fetch(p.url+'/search.php?q='+q).then(function(r){return r.json()}).then(function(data){
        if(data.length>0){
          html+='<div class="sr-section"><span>'+p.name+'</span><a href="'+p.url+'/admin.php" target="_blank">Apri Admin &rarr;</a></div>';
          data.forEach(function(l){
            html+='<div class="sr-item" onclick="window.open(\''+p.url+'/admin.php#lead-'+l.id+'\',\'_blank\')"><div><div class="sr-name">'+l.nome+' '+l.cognome+'</div><div class="sr-tel">'+l.telefono+'</div></div><div style="display:flex;gap:6px;align-items:center"><span class="sr-badge" style="background:'+p.color+'20;color:'+p.color+'">'+l.stato+'</span><a href="'+p.url+'/admin.php#lead-'+l.id+'" target="_blank" class="sr-goto">Vai</a></div></div>';
          });
        }
        done++;
        if(done===projects.length){
          box.innerHTML=html||'<div class="sr-empty">Nessun risultato per questo numero</div>';
        }
      }).catch(function(){done++;if(done===projects.length){box.innerHTML=html||'<div class="sr-empty">Nessun risultato</div>'}});
    });
  },300);
}
document.addEventListener('click',function(e){if(!e.target.closest('.search-bar'))document.getElementById('search-results').classList.remove('open')});

// Salva scroll prima di ogni submit form (per evitare salto dopo redirect)
document.addEventListener('submit',function(e){
  if(e.target.tagName==='FORM'){
    sessionStorage.setItem('adminScroll',window.scrollY);
    sessionStorage.setItem('adminFromSubmit','1');
  }
},true);

// Auto-refresh pagina ogni 60 secondi per aggiornare reminder
setInterval(function(){
  if(document.activeElement.tagName!=='INPUT'&&document.activeElement.tagName!=='SELECT'&&document.activeElement.tagName!=='TEXTAREA'){
    sessionStorage.setItem('adminScroll',window.scrollY);
    location.reload();
  }
},60000);

// Ripristina scroll dopo reload o POST redirect
(function(){
  var s=sessionStorage.getItem('adminScroll');
  var fromSubmit=sessionStorage.getItem('adminFromSubmit');
  if(s && (fromSubmit || !location.hash)){
    // Disabilita lo scroll automatico all'anchor del browser
    if('scrollRestoration' in history) history.scrollRestoration='manual';
    setTimeout(function(){window.scrollTo(0,parseInt(s))},0);
    sessionStorage.removeItem('adminScroll');
    sessionStorage.removeItem('adminFromSubmit');
  }
})();

// Notifica browser quando c'è un reminder
if('Notification' in window && Notification.permission==='default'){Notification.requestPermission()}
var reminderBar=document.querySelector('.reminder-bar.active');
if(reminderBar && 'Notification' in window && Notification.permission==='granted'){
  var count=reminderBar.querySelectorAll('.reminder-item').length;
  new Notification('<?=$project_name?> — Reminder',{body:count+' lead da contattare adesso!',icon:'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📞</text></svg>'});
}
// Cambia titolo pagina se ci sono reminder
if(reminderBar){document.title='(!) '+document.title}

// === CALL RECORDING ===
var __rec=null;
async function toggleRec(leadId, btn){
  if(__rec){
    if(__rec.leadId!==leadId){alert('Ferma prima la registrazione del lead #'+__rec.leadId);return}
    __rec.recorder.stop();return;
  }
  if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){alert('Browser non supporta la registrazione audio');return}
  try{
    var stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});
    var opts={audioBitsPerSecond:32000};
    try{opts.mimeType='audio/webm;codecs=opus'}catch(e){}
    var recorder=new MediaRecorder(stream, opts);
    var chunks=[],startTime=Date.now();
    recorder.ondataavailable=function(e){if(e.data&&e.data.size)chunks.push(e.data)};
    var state={leadId:leadId,recorder:recorder,stream:stream,chunks:chunks,startTime:startTime,btn:btn};
    __rec=state;
    state.timerInterval=setInterval(function(){
      var s=Math.floor((Date.now()-startTime)/1000);
      var m=Math.floor(s/60), sec=s%60;
      btn.innerHTML='⏹️ Stop '+m+':'+(sec<10?'0':'')+sec;
    },500);
    recorder.onstop=async function(){
      clearInterval(state.timerInterval);
      stream.getTracks().forEach(function(t){t.stop()});
      btn.classList.remove('recording');
      btn.innerHTML='⏳ Caricamento...';btn.disabled=true;
      var blob=new Blob(chunks,{type:'audio/webm'});
      var durata=Math.round((Date.now()-startTime)/1000);
      var fd=new FormData();
      fd.append('file',blob,'rec.webm');
      fd.append('lead_id',leadId);
      fd.append('durata',durata);
      try{
        var r=await fetch('api_call_upload.php',{method:'POST',credentials:'same-origin',body:fd});
        var txt=await r.text();
        var j; try{j=JSON.parse(txt)}catch(_){j=null}
        if(j && j.ok){__rec=null;sessionStorage.setItem('adminScrollY',window.scrollY);location.hash='lead-'+leadId;location.reload();return}
        else{
          var msg=j? (j.error||'sconosciuto') : ('HTTP '+r.status+' — '+txt.substring(0,300));
          alert('Errore upload:\n'+msg);
          console.error('Upload raw response:', txt);
          btn.innerHTML='🎙️ Registra Chiamata';btn.disabled=false;
        }
      }catch(e){alert('Errore upload: '+e.message);btn.innerHTML='🎙️ Registra Chiamata';btn.disabled=false}
      __rec=null;
    };
    recorder.start();
    btn.classList.add('recording');
    btn.innerHTML='⏹️ Stop 0:00';
  }catch(err){alert('Impossibile accedere al microfono: '+err.message)}
}
async function delRec(recId){
  if(!confirm('Eliminare questa registrazione?'))return;
  try{
    var fd=new FormData();fd.append('id',recId);
    var r=await fetch('api_call_delete.php',{method:'POST',credentials:'same-origin',body:fd});
    var j=await r.json();
    if(j.ok){sessionStorage.setItem('adminScrollY',window.scrollY);location.reload()}
    else{alert('Errore: '+(j.error||'eliminazione fallita'))}
  }catch(e){alert('Errore: '+e.message)}
}
window.addEventListener('beforeunload',function(e){
  if(__rec){e.preventDefault();e.returnValue='Registrazione in corso. Uscire comunque?';return e.returnValue}
});
// Fix durata WebM: MediaRecorder non scrive la duration nell'header.
// Appena l'audio carica metadata, se duration è Infinity facciamo seek a grande valore
// per forzare il browser a scansionare il file e calcolare la vera durata.
function fixWebmDuration(audio){
  if (audio._durFixed) return; audio._durFixed = true;
  if (audio.readyState >= 1 && !isFinite(audio.duration)) {
    var handler = function(){ audio.removeEventListener('timeupdate', handler); audio.currentTime = 0; };
    audio.addEventListener('timeupdate', handler);
    try { audio.currentTime = 1e101; } catch(e) {}
  }
}
document.querySelectorAll('.rec-item audio').forEach(function(a){
  a.addEventListener('loadedmetadata', function(){ fixWebmDuration(a); });
  if (a.readyState >= 1) fixWebmDuration(a);
});
</script>
</body></html>
