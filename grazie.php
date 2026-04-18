<?php
$candidatura_id = intval($_GET['id'] ?? 0) + 1000;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Candidatura Inviata!</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f8f7f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:48px 36px;max-width:500px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,0.06)}
.check{width:72px;height:72px;border-radius:50%;background:#1a3a6b;display:flex;align-items:center;justify-content:center;margin:0 auto 24px}
.check svg{width:36px;height:36px;stroke:#fff;stroke-width:3;fill:none}
h1{font-size:24px;font-weight:800;margin-bottom:8px;color:#111}
.cid{font-size:15px;color:#888;margin-bottom:20px}
.cid strong{color:#1a3a6b}
.info{background:#e8f0fe;border-radius:12px;padding:18px;margin-bottom:24px;font-size:14px;color:#333;line-height:1.7;text-align:left}
.info strong{color:#1a3a6b}
p{font-size:14px;color:#888;line-height:1.6;margin-bottom:20px}
.btn{display:inline-block;padding:14px 32px;background:#d4a03c;color:#1a3a6b;border-radius:14px;font-size:14px;font-weight:700;text-decoration:none;transition:all .2s}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(212,160,60,0.3)}
@media(max-width:500px){.card{padding:36px 24px;border-radius:16px}h1{font-size:20px}.info{font-size:13px;padding:14px}}
</style>
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1640258307311235');
fbq('track', 'PageView');
fbq('track', 'Purchase', {value: 89.00, currency: 'EUR'});
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=1640258307311235&ev=Purchase&noscript=1"/></noscript>
<!-- End Meta Pixel Code -->
</head>
<body>
<div class="card">
  <div class="check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
  <h1>Candidatura Inviata!</h1>
  <!-- <div class="cid">Numero candidatura: <strong>#<?= $candidatura_id ?></strong></div> -->
  <div class="info">
    <strong>Cosa succede ora?</strong><br>
    1. Riceverai una chiamata entro 24 ore per confermare la tua candidatura<br>
    2. Ti invieremo le istruzioni per il pagamento e l'accesso al corso<br>
    3. Inizi la formazione e dopo il test ti inseriamo in azienda
  </div>
  <p>Grazie per la tua candidatura! Tieni il telefono a portata di mano, ti contatteremo a breve.</p>
  <a href="index.html" class="btn">Torna alla pagina</a>
</div>
</body>
</html>
