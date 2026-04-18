<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

$totali = (int)$pdo->query("SELECT COUNT(*) FROM candidature")->fetchColumn();
$nuovi = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato IN ('nuova','nuovo')")->fetchColumn();
$oggi = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE DATE(data_candidatura)=CURDATE()")->fetchColumn();
$bonifico = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE metodo_pagamento='bonifico'")->fetchColumn();
$contrassegno = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE metodo_pagamento='contrassegno'")->fetchColumn();
$whatsapp = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE note LIKE '%WhatsApp%'")->fetchColumn();

// Stati avanzati (confermati include anche spedito/completato perche' sono step successivi alla conferma)
$contattato = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='contattato'")->fetchColumn();
$non_risponde = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='non_risponde'")->fetchColumn();
$da_ricontattare = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='da_ricontattare'")->fetchColumn();
$confermati = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato IN ('confermato','spedito','completato')")->fetchColumn();
$annullati = (int)$pdo->query("SELECT COUNT(*) FROM candidature WHERE stato IN ('annullato','annullata')")->fetchColumn();

echo json_encode([
    'totali' => $totali,
    'nuovi' => $nuovi,
    'oggi' => $oggi,
    'bonifico' => $bonifico,
    'contrassegno' => $contrassegno,
    'whatsapp' => $whatsapp,
    'contattato' => $contattato,
    'non_risponde' => $non_risponde,
    'da_ricontattare' => $da_ricontattare,
    'confermati' => $confermati,
    'annullati' => $annullati
]);
