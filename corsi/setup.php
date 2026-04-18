<?php
error_reporting(E_ALL); ini_set('display_errors',1);
require_once 'config.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS studenti (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cognome VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        attivo TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS moduli (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titolo VARCHAR(255) NOT NULL,
        ordine INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lezioni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modulo_id INT NOT NULL,
        titolo VARCHAR(255) NOT NULL,
        video_url VARCHAR(500) DEFAULT '',
        pdf_file VARCHAR(255) DEFAULT '',
        descrizione TEXT,
        ordine INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS progressi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        studente_id INT NOT NULL,
        lezione_id INT NOT NULL,
        completata TINYINT DEFAULT 0,
        completed_at TIMESTAMP NULL,
        UNIQUE KEY (studente_id, lezione_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "Setup corsi completato!";
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
