<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE candidature ADD COLUMN indirizzo VARCHAR(200) DEFAULT '' AFTER metodo_pagamento");
    echo "Colonna indirizzo aggiunta. ";
} catch (PDOException $e) { echo "indirizzo gia presente. "; }
try {
    $pdo->exec("ALTER TABLE candidature ADD COLUMN cap VARCHAR(10) DEFAULT '' AFTER indirizzo");
    echo "Colonna cap aggiunta. ";
} catch (PDOException $e) { echo "cap gia presente. "; }
try {
    $pdo->exec("ALTER TABLE candidature ADD COLUMN scala VARCHAR(50) DEFAULT '' AFTER cap");
    echo "Colonna scala aggiunta. ";
} catch (PDOException $e) { echo "scala gia presente. "; }
echo "FATTO!";
