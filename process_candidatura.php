<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$citta = trim($_POST['citta'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');
$metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'contrassegno');
$indirizzo = trim($_POST['indirizzo'] ?? '');
$cap = trim($_POST['cap'] ?? '');
$scala = trim($_POST['scala'] ?? '');
$note = trim($_POST['note'] ?? '');

if (!$nome || !$cognome || !$telefono || !$citta || !$provincia || !$metodo_pagamento) {
    header('Location: index.html?errore=campi#iscriviti');
    exit;
}

$stmt = $pdo->prepare("INSERT INTO candidature (nome, cognome, email, telefono, citta, provincia, metodo_pagamento, indirizzo, cap, scala, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$nome, $cognome, $email, $telefono, $citta, $provincia, $metodo_pagamento, $indirizzo, $cap, $scala, $note]);

$candidatura_id = $pdo->lastInsertId();

header("Location: grazie.php?id=$candidatura_id");
exit;
