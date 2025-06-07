<?php
session_start();
require_once 'db_config.php';

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');

// --- Sicurezza ---
// Solo admin loggati che usano il metodo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato.']);
    exit;
}

// --- Dati in ingresso ---
$id_segnalazione = filter_input(INPUT_POST, 'id_segnalazione', FILTER_VALIDATE_INT);
$nuovo_stato = trim(htmlspecialchars($_POST['nuovo_stato'] ?? ''));
$note_interne = trim(htmlspecialchars($_POST['note_interne'] ?? ''));

// Lista degli stati validi
$stati_validi = ['Inviata', 'In Lavorazione', 'In Attesa di Risposta', 'Conclusa'];

if (!$id_segnalazione || !in_array($nuovo_stato, $stati_validi)) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi.']);
    exit;
}

// --- Logica Database ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
    exit;
}

$sql = "UPDATE segnalazioni SET stato = ?, note_interne = ? WHERE id_segnalazione = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssi", $nuovo_stato, $note_interne, $id_segnalazione);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Segnalazione aggiornata con successo.']);
        } else {
            // Nessuna riga modificata, ma potrebbe essere perché i dati erano identici. Lo consideriamo un successo.
            echo json_encode(['success' => true, 'message' => 'Nessuna modifica da salvare.']);
        }
    } else {
        error_log("Errore DB in update_segnalazione_action: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento nel database.']);
    }
    $stmt->close();
} else {
    error_log("Errore DB (prepare) in update_segnalazione_action: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Errore di sistema.']);
}

$conn->close();
exit;