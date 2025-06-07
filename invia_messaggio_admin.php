<?php
require 'connessione.php'; // connessione al DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_segnalazione'];
    $msg = $_POST['messaggio_admin'];

    $stmt = $conn->prepare("UPDATE segnalazioni SET messaggio_admin = ? WHERE id = ?");
    $stmt->execute([$msg, $id]);

    header("Location: gestione_segnalazioni.php");
    exit;
}
?>
