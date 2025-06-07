<?php
session_start();
require_once 'db_config.php';

// Sicurezza: L'utente deve essere loggato
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Dati dell'utente loggato
$id_utente_loggato = $_SESSION['user_id'];
$username_display = htmlspecialchars($_SESSION['username'] ?? 'N/A');

// Connessione al DB e recupero delle segnalazioni SOLO di questo utente
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$mie_segnalazioni = [];
$chat_messaggi = [];
$db_error_message = null;

if ($conn->connect_error) {
    $db_error_message = "Impossibile connettersi al database per caricare lo storico.";
} else {
    $sql = "SELECT id_segnalazione, titolo, descrizione, area_competenza, data_invio, stato, data_ultima_modifica
            FROM segnalazioni
            WHERE id_utente_segnalante = ?
            ORDER BY data_invio DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_utente_loggato);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $mie_segnalazioni = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        // Carica tutti i messaggi associati a queste segnalazioni
        if (!empty($mie_segnalazioni)) {
            $ids = array_column($mie_segnalazioni, 'id_segnalazione');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql_chat = "SELECT id, id_segnalazione, messaggio_admin, risposta_utente, data_messaggio, data_risposta FROM segnalazioni_chat WHERE id_segnalazione IN ($placeholders) ORDER BY data_messaggio";
            $stmt_chat = $conn->prepare($sql_chat);
            $stmt_chat->bind_param($types, ...$ids);
            $stmt_chat->execute();
            $res_chat = $stmt_chat->get_result();
            if ($res_chat) {
                while ($row = $res_chat->fetch_assoc()) {
                    $chat_messaggi[$row['id_segnalazione']][] = $row;
                }
                $res_chat->free();
            }
            $stmt_chat->close();
        }
    } else {
        $db_error_message = "Errore nel caricamento delle tue segnalazioni.";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Mie Segnalazioni - Gruppo Vitolo</title>
    <style>
        /* Stile coerente con le altre pagine di gestione/visualizzazione */
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .module-page-container { max-width: 1100px; margin: 25px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; gap: 15px; }
        .header-branding .logo { max-height: 45px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .nav-link-button, .logout-button { text-decoration: none; padding: 8px 15px; border-radius: 5px; color: white !important; font-weight: 500; transition: all 0.2s ease; border: none; cursor: pointer; }
        .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .app-section-header h2 { font-size: 1.4em; color: #2E572E; margin: 0; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }

        .report-record { border: 1px solid #e9ecef; margin-top: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .report-summary { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; cursor: pointer; flex-wrap: wrap; gap: 10px; }
        .report-summary-info h3 { margin: 0 0 5px 0; color: #343a40; font-size: 1.1em; }
        .report-summary-info span { font-size: 0.9em; color: #6c757d; }
        .report-meta { display: flex; align-items: center; gap: 20px; }
        .report-details { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; background-color: #fdfdfd; border-top: 1px solid #e9ecef; padding: 0 20px; }
        .report-record.is-open .report-details { max-height: 1000px; padding: 25px; }
        .detail-item { margin-bottom: 15px; }
        .detail-item strong { color: #2E572E; }

        .status-badge { padding: 4px 12px; border-radius: 15px; font-size: 0.85em; font-weight: bold; color: white; text-align: center; }
        .status-badge.inviata { background-color: #007bff; }
        .status-badge.in-lavorazione { background-color: #ffc107; color: #212529; }
        .status-badge.in-attesa-di-risposta { background-color: #17a2b8; }
        .status-badge.conclusa { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php"><img src="logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Le Mie Segnalazioni</h1>
                    <h2>Storico e Stato delle Tue Richieste</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <span><strong><?php echo $username_display; ?></strong></span>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content">
            <div class="app-section-header">
                <h2>Riepilogo delle tue segnalazioni inviate</h2>
            </div>
            <?php if (isset($_GET['reply'])): ?>
                <?php if ($_GET['reply'] === 'success'): ?>
                    <p style="color: green; font-weight: bold;">Risposta inviata correttamente.</p>
                <?php elseif ($_GET['reply'] === 'error'): ?>
                    <p style="color: red; font-weight: bold;">Errore nell'invio della risposta.</p>
                <?php endif; ?>
            <?php endif; ?>

            <div id="reports-list-container">
                <?php if ($db_error_message): ?>
                    <p style="color: red;"><?php echo $db_error_message; ?></p>
                <?php elseif (empty($mie_segnalazioni)): ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Non hai ancora inviato nessuna segnalazione.</p>
                <?php else: ?>
                    <?php foreach ($mie_segnalazioni as $s): ?>
                        <div class="report-record">
                            <div class="report-summary">
                                <div class="report-summary-info">
                                    <h3><?php echo htmlspecialchars($s['titolo']); ?></h3>
                                    <span>Inviata il: <?php echo date('d/m/Y H:i', strtotime($s['data_invio'])); ?></span>
                                </div>
                                <div class="report-meta">
                                    <?php $status_class = strtolower(str_replace(' ', '-', $s['stato'])); ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($s['stato']); ?></span>
                                </div>
                            </div>
                            <div class="report-details">
                                <div class="detail-item">
                                    <strong>Descrizione Originale:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($s['descrizione'])); ?></p>
                                </div>
                                <div class="detail-item">
                                    <strong>Area di Competenza:</strong> <?php echo htmlspecialchars($s['area_competenza']); ?>
                                </div>
                                <div class="detail-item">
                                    <strong>Ultimo Aggiornamento:</strong> <?php echo date('d/m/Y H:i', strtotime($s['data_ultima_modifica'])); ?>
                                </div>
                                <?php if (!empty($chat_messaggi[$s['id_segnalazione']])): ?>
                                    <?php foreach ($chat_messaggi[$s['id_segnalazione']] as $msg): ?>
                                        <div class="detail-item" style="border-bottom:1px solid #eee; margin-bottom:10px; padding-bottom:10px;">
                                            <strong>Messaggio dall'Amministratore:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($msg['messaggio_admin'])); ?></p>
                                            <small><?php echo date('d/m/Y H:i', strtotime($msg['data_messaggio'])); ?></small>
                                            <?php if (empty($msg['risposta_utente'])): ?>
                                                <form action="rispondi_a_admin_action.php" method="POST" style="margin-top:8px;">
                                                    <input type="hidden" name="id_messaggio" value="<?php echo $msg['id']; ?>">
                                                    <textarea name="risposta_utente" required style="width:100%;padding:8px;min-height:80px;"></textarea>
                                                    <button type="submit" class="nav-link-button" style="margin-top:10px;">Invia Risposta</button>
                                                </form>
                                            <?php else: ?>
                                                <div style="margin-top:6px; padding-left:10px;">
                                                    <strong>La tua Risposta:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($msg['risposta_utente'])); ?></p>
                                                    <small><?php echo date('d/m/Y H:i', strtotime($msg['data_risposta'])); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('reports-list-container');
    if(container) {
        container.addEventListener('click', function(event) {
            const summary = event.target.closest('.report-summary');
            if (summary) {
                const record = summary.closest('.report-record');
                if (record) {
                    record.classList.toggle('is-open');
                }
            }
        });
    }
});
</script>
</body>
</html>