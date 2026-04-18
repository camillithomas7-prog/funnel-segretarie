<?php
require_once 'corsi/config.php';
$portal_url = 'https://' . $_SERVER['HTTP_HOST'] . '/corsi/';
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&margin=10&data=' . urlencode($portal_url);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Riepilogo - <?=htmlspecialchars($corso_nome)?></title>
<style>
  *{margin:0;padding:0;box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{font-family:'Helvetica Neue',Arial,sans-serif;background:#f5f5f5;color:#222;line-height:1.5}
  .page{width:210mm;min-height:297mm;margin:20px auto;padding:25mm 20mm;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.08);position:relative}
  .header{border-bottom:4px solid <?=$corso_accent?>;padding-bottom:16px;margin-bottom:28px}
  .header h1{font-size:32px;color:<?=$corso_accent?>;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
  .header .subtitle{font-size:14px;color:#888;font-weight:500;text-transform:uppercase;letter-spacing:2px}
  .thanks{background:linear-gradient(135deg,<?=$corso_accent?>,<?=$corso_accent?>cc);color:#fff;padding:24px 28px;border-radius:14px;margin-bottom:28px}
  .thanks h2{font-size:22px;font-weight:700;margin-bottom:8px}
  .thanks p{font-size:14px;opacity:0.95}
  .section{margin-bottom:24px}
  .section h3{font-size:16px;color:#111;margin-bottom:12px;display:flex;align-items:center;gap:10px}
  .section h3::before{content:'';width:6px;height:20px;background:<?=$corso_accent?>;border-radius:3px}
  .contents-list{list-style:none;padding:0}
  .contents-list li{padding:10px 0 10px 30px;font-size:14px;color:#333;border-bottom:1px dashed #e8e8e8;position:relative}
  .contents-list li::before{content:'✓';position:absolute;left:0;top:50%;transform:translateY(-50%);width:20px;height:20px;background:<?=$corso_accent?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
  .qr-box{background:#fafafa;border:2px dashed <?=$corso_accent?>;border-radius:16px;padding:24px;display:flex;gap:24px;align-items:center;margin-top:16px}
  .qr-box img{width:180px;height:180px;background:#fff;padding:8px;border-radius:8px;flex-shrink:0}
  .qr-info{flex:1}
  .qr-info h3{font-size:18px;color:#111;margin-bottom:8px;border:0;padding:0}
  .qr-info h3::before{display:none}
  .qr-info p{font-size:13px;color:#555;margin-bottom:8px;line-height:1.5}
  .qr-info .url{display:inline-block;padding:6px 12px;background:<?=$corso_accent?>15;color:<?=$corso_accent?>;border-radius:6px;font-size:12px;font-weight:700;word-break:break-all;margin-top:6px}
  .steps{counter-reset:step;list-style:none;padding:0;margin-top:10px}
  .steps li{counter-increment:step;padding:8px 0 8px 34px;font-size:13px;color:#444;position:relative}
  .steps li::before{content:counter(step);position:absolute;left:0;top:6px;width:22px;height:22px;background:#fff;border:2px solid <?=$corso_accent?>;color:<?=$corso_accent?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800}
  .footer{position:absolute;bottom:20mm;left:20mm;right:20mm;padding-top:14px;border-top:1px solid #eee;text-align:center;font-size:11px;color:#aaa}
  .print-bar{position:fixed;top:10px;right:10px;z-index:100;display:flex;gap:8px}
  .print-bar button{padding:10px 20px;background:<?=$corso_accent?>;color:#fff;border:0;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 2px 8px rgba(0,0,0,.15)}
  .print-bar button.secondary{background:#fff;color:#333;border:1px solid #ddd}
  @media print{
    body{background:#fff}
    .page{margin:0;box-shadow:none;padding:15mm}
    .print-bar{display:none}
    @page{size:A4;margin:0}
  }
</style>
</head>
<body>
<div class="print-bar">
  <button onclick="window.print()">Stampa</button>
  <button class="secondary" onclick="window.close()">Chiudi</button>
</div>
<div class="page">
  <div class="header">
    <h1><?=htmlspecialchars($corso_nome)?></h1>
    <div class="subtitle">Riepilogo Iscrizione</div>
  </div>

  <div class="thanks">
    <h2>Grazie di esserti iscritto!</h2>
    <p>Benvenuto/a nel percorso. All'interno di questo pacco trovi tutto il materiale necessario per iniziare. Scansiona il QR code in basso per accedere al portale video del corso.</p>
  </div>

  <div class="section">
    <h3>Contenuto del pacco</h3>
    <ul class="contents-list">
      <li>Manuale cartaceo del corso</li>
      <li>Attestato personalizzato al completamento</li>
      <li>Credenziali di accesso al portale online</li>
      <li>Materiale didattico complementare</li>
    </ul>
  </div>

  <div class="section">
    <h3>Accedi al portale corsi</h3>
    <div class="qr-box">
      <img src="<?=htmlspecialchars($qr_url)?>" alt="QR code portale corso">
      <div class="qr-info">
        <h3>Scansiona con la fotocamera</h3>
        <p>Inquadra il QR code con lo smartphone per aprire il portale del corso. Registrati con email e password per iniziare a seguire le video lezioni.</p>
        <div class="url"><?=htmlspecialchars($portal_url)?></div>
        <ol class="steps">
          <li>Apri la fotocamera del tuo smartphone</li>
          <li>Inquadra il QR code qui sopra</li>
          <li>Tocca la notifica per aprire il portale</li>
          <li>Registrati con email e password per accedere</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="footer">
    <?=htmlspecialchars($corso_nome)?> · Per assistenza contattaci via WhatsApp
  </div>
</div>
</body>
</html>
