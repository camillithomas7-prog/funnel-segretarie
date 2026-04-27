<?php
// Funzioni condivise tra admin.php e process_candidatura.php

/**
 * Sceglie l'operatore a cui assegnare un nuovo lead.
 * Algoritmo: weighted round-robin basato su ore_settimanali.
 * Il lead va all'operatore con il rapporto piu' basso tra (lead aperti / ore).
 * "Lead aperti" = stato NOT IN (completato, annullato, numero_sbagliato).
 * Ritorna nome operatore o null se nessuno ha ore_settimanali > 0.
 */
function pickOperator($pdo) {
    try {
        $ops = $pdo->query("SELECT nome, ore_settimanali FROM operatori WHERE ore_settimanali > 0")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
    if (empty($ops)) return null;

    $best = null;
    $best_ratio = INF;
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM candidature WHERE assegnato=? AND stato NOT IN ('completato','annullato','numero_sbagliato')");
    foreach ($ops as $op) {
        $cnt->execute([$op['nome']]);
        $count = (int)$cnt->fetchColumn();
        $ratio = $count / max(1, (int)$op['ore_settimanali']);
        if ($ratio < $best_ratio) {
            $best_ratio = $ratio;
            $best = $op['nome'];
        }
    }
    return $best;
}
