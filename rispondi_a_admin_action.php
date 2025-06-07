<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: le_mie_segnalazioni.php?reply=error');
    exit;
}

$id_segnalazione = filter_input(INPUT_POST, 'id_segnalazione', FILTER_VALIDATE_INT);
$risposta = trim($_POST['risposta_utente'] ?? '');

if (!$id_segnalazione || $risposta === '') {
    header('Location: le_mie_segnalazioni.php?reply=error');
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header('Location: le_mie_segnalazioni.php?reply=error');
    exit;
}

$sql = 'SELECT id_utente_segnalante, risposta_utente FROM segnalazioni WHERE id_segnalazione = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_segnalazione);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row || $row['id_utente_segnalante'] != $_SESSION['user_id'] || !empty($row['risposta_utente'])) {
    $conn->close();
    header('Location: le_mie_segnalazioni.php?reply=error');
    exit;
}

$sql = 'UPDATE segnalazioni SET risposta_utente = ?, data_ultima_modifica = CURRENT_TIMESTAMP WHERE id_segnalazione = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $risposta, $id_segnalazione);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if ($success) {
    header('Location: le_mie_segnalazioni.php?reply=success');
} else {
    header('Location: le_mie_segnalazioni.php?reply=error');
}
exit;
?>
