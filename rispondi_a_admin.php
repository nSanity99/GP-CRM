<?php
require 'connessione.php'; // connessione al DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_segnalazione'];
    $risposta = $_POST['risposta_utente'];

    // controlla che non esista già una risposta
    $stmt = $conn->prepare("SELECT risposta_utente FROM segnalazioni WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetchColumn();

    if (empty($existing)) {
        $stmt = $conn->prepare("UPDATE segnalazioni SET risposta_utente = ? WHERE id = ?");
        $stmt->execute([$risposta, $id]);
    }

    header("Location: le_mie_segnalazioni.php");
    exit;
}
?>
