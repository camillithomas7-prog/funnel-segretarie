<?php
require_once 'config.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS candidature (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_candidatura DATETIME DEFAULT CURRENT_TIMESTAMP,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    telefono VARCHAR(30) NOT NULL,
    citta VARCHAR(100) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    metodo_pagamento ENUM('bonifico','contrassegno') NOT NULL DEFAULT 'contrassegno',
    note TEXT,
    stato ENUM('nuova','contattata','iscritta','in_formazione','completata','annullata') DEFAULT 'nuova',
    aggiornato DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "Database candidature configurato con successo! Ora puoi eliminare questo file.";
