<?php
$errore = $_GET['errore'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Checkout - Corso Segretaria</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--font:'Inter',sans-serif;--text:rgba(0,0,0,0.81);--heading:#000;--bg:#fff;--sidebar:#f5f5f5;--border:#dfdfdf;--blue:#1a3a6b;--gold:#d4a03c;--radius:8px}
body{font-family:var(--font);color:var(--text);background:var(--bg);line-height:1.5;-webkit-tap-highlight-color:transparent}
.checkout{display:grid;grid-template-columns:1fr 380px;min-height:100vh}
@media(max-width:900px){.checkout{grid-template-columns:1fr;display:flex;flex-direction:column}.sidebar{order:-1}}
.main{padding:40px 50px 40px 80px;max-width:640px;margin-left:auto}
@media(max-width:900px){.main{padding:24px 20px;max-width:100%;margin:0}}
.logo{font-size:18px;font-weight:800;color:var(--blue);margin-bottom:24px}
.logo span{color:var(--gold)}
.section-title{font-size:17px;font-weight:700;color:var(--heading);margin-bottom:16px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.form-row.full{grid-template-columns:1fr}
@media(max-width:500px){.form-row{grid-template-columns:1fr}}
.form-group{display:flex;flex-direction:column;gap:4px}
.form-group label{font-size:12px;color:rgba(0,0,0,0.6);font-weight:500}
.form-group input,.form-group select,.form-group textarea{padding:12px;border:1px solid var(--border);border-radius:4px;font-size:14px;font-family:var(--font);outline:none;transition:border-color .2s;-webkit-appearance:none}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 1.5px var(--blue)}
.form-group textarea{resize:vertical;min-height:60px}

/* Metodo pagamento */
.pay-method{margin-bottom:20px}
.pay-method-label{font-size:12px;color:rgba(0,0,0,0.6);font-weight:500;margin-bottom:8px}
.pay-options{display:flex;flex-direction:column;gap:8px}
.pay-opt{cursor:pointer}
.pay-opt input{display:none}
.pay-opt-box{display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:var(--radius);transition:all .2s}
.pay-opt input:checked+.pay-opt-box{border-color:var(--blue);background:#e8f0fe}
.pay-opt-icon{font-size:22px;flex-shrink:0}
.pay-opt-box strong{font-size:14px;color:var(--heading);display:block}
.pay-opt-box small{font-size:11px;color:rgba(0,0,0,0.5)}

/* Indirizzo (solo per contrassegno) */
.address-section{display:none;margin-top:4px}
.address-section.visible{display:block}

.submit-btn{width:100%;padding:18px;background:var(--gold);color:var(--blue);border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:var(--font);cursor:pointer;transition:all .2s;margin-top:8px;letter-spacing:0.02em}
.submit-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(212,160,60,0.3)}
.secure{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:12px;font-size:12px;color:rgba(0,0,0,0.45)}
.secure svg{width:14px;height:14px;stroke:rgba(0,0,0,0.4);fill:none;stroke-width:1.5}
.error-msg{background:#fee;border:1px solid #fcc;color:#900;padding:10px 14px;border-radius:var(--radius);margin-bottom:16px;font-size:13px}

/* Sidebar */
.sidebar{background:var(--sidebar);padding:40px 30px;border-left:1px solid rgba(0,0,0,0.06)}
@media(max-width:900px){.sidebar{padding:20px;border-left:none;border-bottom:1px solid rgba(0,0,0,0.06)}}
.product-card{display:flex;gap:14px;align-items:center;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid rgba(0,0,0,0.08)}
.product-card .prod-icon{width:64px;height:64px;border-radius:var(--radius);background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0}
.product-card .info{flex:1}
.product-card .name{font-size:14px;font-weight:600;color:var(--heading)}
.product-card .variant{font-size:12px;color:rgba(0,0,0,0.5);margin-top:2px}
.product-card .price{font-size:18px;font-weight:800;color:var(--heading)}
.totals .row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px}
.totals .row .lbl{color:rgba(0,0,0,0.6)}
.totals .row.total{padding-top:16px;margin-top:12px;border-top:1px solid rgba(0,0,0,0.08);font-size:20px;font-weight:800;color:var(--heading)}
.totals .row .old{text-decoration:line-through;color:rgba(0,0,0,0.35);font-size:14px;margin-right:6px}
.trust{display:flex;flex-direction:column;gap:8px;margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,0,0,0.08)}
.trust-item{display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(0,0,0,0.55)}
.trust-item svg{width:16px;height:16px;stroke:var(--blue);fill:none;stroke-width:1.5;flex-shrink:0}
</style>
</head>
<body>
<form method="POST" action="process_candidatura.php" id="checkoutForm">

<div class="checkout">
  <div class="main">
    <div class="logo">Corso Segretaria <span>+ Inserimento Lavorativo</span></div>

    <?php if ($errore === 'campi'): ?>
    <div class="error-msg">Compila tutti i campi obbligatori.</div>
    <?php endif; ?>

    <h2 class="section-title">Informazioni di contatto</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Nome *</label>
        <input type="text" name="nome" required>
      </div>
      <div class="form-group">
        <label>Cognome *</label>
        <input type="text" name="cognome" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Telefono *</label>
        <input type="tel" name="telefono" required placeholder="+39">
      </div>
      <div class="form-group">
        <label>Email (facoltativo)</label>
        <input type="email" name="email">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Citta *</label>
        <input type="text" name="citta" required>
      </div>
      <div class="form-group">
        <label>Provincia *</label>
        <select name="provincia" required>
          <option value="">Seleziona</option>
          <option value="AG">Agrigento</option><option value="AL">Alessandria</option><option value="AN">Ancona</option><option value="AO">Aosta</option><option value="AR">Arezzo</option><option value="AP">Ascoli Piceno</option><option value="AT">Asti</option><option value="AV">Avellino</option>
          <option value="BA">Bari</option><option value="BT">Barletta-Andria-Trani</option><option value="BL">Belluno</option><option value="BN">Benevento</option><option value="BG">Bergamo</option><option value="BI">Biella</option><option value="BO">Bologna</option><option value="BZ">Bolzano</option><option value="BS">Brescia</option><option value="BR">Brindisi</option>
          <option value="CA">Cagliari</option><option value="CL">Caltanissetta</option><option value="CB">Campobasso</option><option value="CE">Caserta</option><option value="CT">Catania</option><option value="CZ">Catanzaro</option><option value="CH">Chieti</option><option value="CO">Como</option><option value="CS">Cosenza</option><option value="CR">Cremona</option><option value="KR">Crotone</option><option value="CN">Cuneo</option>
          <option value="EN">Enna</option>
          <option value="FM">Fermo</option><option value="FE">Ferrara</option><option value="FI">Firenze</option><option value="FG">Foggia</option><option value="FC">Forli-Cesena</option><option value="FR">Frosinone</option>
          <option value="GE">Genova</option><option value="GO">Gorizia</option><option value="GR">Grosseto</option>
          <option value="IM">Imperia</option><option value="IS">Isernia</option>
          <option value="SP">La Spezia</option><option value="AQ">L'Aquila</option><option value="LT">Latina</option><option value="LE">Lecce</option><option value="LC">Lecco</option><option value="LI">Livorno</option><option value="LO">Lodi</option><option value="LU">Lucca</option>
          <option value="MC">Macerata</option><option value="MN">Mantova</option><option value="MS">Massa-Carrara</option><option value="MT">Matera</option><option value="ME">Messina</option><option value="MI">Milano</option><option value="MO">Modena</option><option value="MB">Monza e Brianza</option>
          <option value="NA">Napoli</option><option value="NO">Novara</option><option value="NU">Nuoro</option>
          <option value="OR">Oristano</option>
          <option value="PD">Padova</option><option value="PA">Palermo</option><option value="PR">Parma</option><option value="PV">Pavia</option><option value="PG">Perugia</option><option value="PU">Pesaro e Urbino</option><option value="PE">Pescara</option><option value="PC">Piacenza</option><option value="PI">Pisa</option><option value="PT">Pistoia</option><option value="PN">Pordenone</option><option value="PZ">Potenza</option><option value="PO">Prato</option>
          <option value="RG">Ragusa</option><option value="RA">Ravenna</option><option value="RC">Reggio Calabria</option><option value="RE">Reggio Emilia</option><option value="RI">Rieti</option><option value="RN">Rimini</option><option value="RM">Roma</option><option value="RO">Rovigo</option>
          <option value="SA">Salerno</option><option value="SS">Sassari</option><option value="SV">Savona</option><option value="SI">Siena</option><option value="SR">Siracusa</option><option value="SO">Sondrio</option><option value="SU">Sud Sardegna</option>
          <option value="TA">Taranto</option><option value="TE">Teramo</option><option value="TR">Terni</option><option value="TO">Torino</option><option value="TP">Trapani</option><option value="TN">Trento</option><option value="TV">Treviso</option><option value="TS">Trieste</option>
          <option value="UD">Udine</option>
          <option value="VA">Varese</option><option value="VE">Venezia</option><option value="VB">Verbano-Cusio-Ossola</option><option value="VC">Vercelli</option><option value="VR">Verona</option><option value="VV">Vibo Valentia</option><option value="VI">Vicenza</option><option value="VT">Viterbo</option>
        </select>
      </div>
    </div>

    <h2 class="section-title" style="margin-top:24px">Metodo di pagamento</h2>
    <div class="pay-method">
      <div class="pay-options">
        <label class="pay-opt">
          <input type="radio" name="metodo_pagamento" value="bonifico" onchange="toggleAddress()">
          <div class="pay-opt-box">
            <span class="pay-opt-icon">&#127974;</span>
            <div>
              <strong>Bonifico Bancario</strong>
              <small>Paghi subito e ricevi accesso immediato al corso via email</small>
            </div>
          </div>
        </label>
        <label class="pay-opt">
          <input type="radio" name="metodo_pagamento" value="contrassegno" checked onchange="toggleAddress()">
          <div class="pay-opt-box">
            <span class="pay-opt-icon">&#128230;</span>
            <div>
              <strong>Pagamento alla Consegna</strong>
              <small>Ricevi il materiale a casa e paghi 89&euro; al corriere</small>
            </div>
          </div>
        </label>
      </div>
    </div>

    <!-- Indirizzo spedizione (solo contrassegno) -->
    <div class="address-section visible" id="addressSection">
      <h2 class="section-title">Indirizzo di spedizione</h2>
      <div class="form-row full">
        <div class="form-group">
          <label>Indirizzo *</label>
          <input type="text" name="indirizzo" id="indirizzo" placeholder="Via, numero civico">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>CAP *</label>
          <input type="text" name="cap" id="cap">
        </div>
        <div class="form-group">
          <label>Citta spedizione</label>
          <input type="text" name="citta_spedizione" id="cittaSpedizione" placeholder="Se diversa da sopra">
        </div>
      </div>
      <div class="form-row full">
        <div class="form-group">
          <label>Note per il corriere (facoltativo)</label>
          <textarea name="note" placeholder="Citofono, piano, orari preferiti..."></textarea>
        </div>
      </div>
    </div>

    <button type="submit" class="submit-btn">Conferma Iscrizione — &euro;89</button>
    <div class="secure">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      I tuoi dati sono protetti e al sicuro
    </div>
  </div>

  <div class="sidebar">
    <div class="product-card">
      <div class="prod-icon">&#127891;</div>
      <div class="info">
        <div class="name">Corso Segretaria + Inserimento Lavorativo</div>
        <div class="variant">Corso online + test finale + inserimento in azienda</div>
      </div>
      <div class="price">&euro;89</div>
    </div>

    <div class="totals">
      <div class="row"><span class="lbl">Valore corso</span><span><span class="old">&euro;297</span></span></div>
      <div class="row"><span class="lbl">Prezzo riservato</span><span style="color:var(--blue);font-weight:700">&euro;89,00</span></div>
      <div class="row"><span class="lbl">Spedizione materiale</span><span>Gratuita</span></div>
      <div class="row"><span class="lbl">Inserimento lavorativo</span><span style="color:var(--blue);font-weight:600">Incluso</span></div>
      <div class="row total"><span>Totale</span><span>&euro;89,00</span></div>
    </div>

    <div class="trust">
      <div class="trust-item"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Garanzia rimborso se non ti inseriamo in azienda</div>
      <div class="trust-item"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>Spedizione gratuita in tutta Italia</div>
      <div class="trust-item"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Dati protetti e sicuri</div>
      <div class="trust-item"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Contratto a tempo indeterminato</div>
      <div class="trust-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Solo 10 posti disponibili</div>
    </div>
  </div>
</div>
</form>

<script>
function toggleAddress(){
  const cod=document.querySelector('input[name="metodo_pagamento"][value="contrassegno"]').checked;
  const sec=document.getElementById('addressSection');
  const ind=document.getElementById('indirizzo');
  const cap=document.getElementById('cap');
  if(cod){
    sec.classList.add('visible');
    ind.required=true;
    cap.required=true;
  }else{
    sec.classList.remove('visible');
    ind.required=false;
    cap.required=false;
  }
}
toggleAddress();
</script>
</body>
</html>
