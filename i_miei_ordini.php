<?php
session_start();
require_once 'db_config.php';

// --- Blocco Sicurezza ---
// Controlliamo solo che l'utente sia loggato, non importa il ruolo.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Dati dell'utente loggato presi dalla sessione
$id_utente_loggato = $_SESSION['user_id'];
$username_display = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$user_role_display = htmlspecialchars($_SESSION['ruolo'] ?? 'N/A');

// --- Logica Database per recuperare gli ordini di QUESTO utente ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port ?? 3306);
$ordini_utente = [];
$db_error_message = null;

if ($conn->connect_error) {
    $db_error_message = "Impossibile connettersi al database per caricare lo storico.";
} else {
    // 1. Prendi tutti gli ordini creati dall'utente loggato
    $sql_ordini = "SELECT id_ordine, data_richiesta, centro_costo, stato_ordine 
                   FROM ordini 
                   WHERE id_utente_richiedente = ? 
                   ORDER BY data_richiesta DESC";
    
    $stmt_ordini = $conn->prepare($sql_ordini);
    $stmt_ordini->bind_param("i", $id_utente_loggato);
    $stmt_ordini->execute();
    $result_ordini = $stmt_ordini->get_result();

    // 2. Per ogni ordine, prendi i suoi prodotti
    while ($ordine_row = $result_ordini->fetch_assoc()) {
        $current_order_id = $ordine_row['id_ordine'];
        $ordine_row['prodotti'] = [];
        
        $stmt_dettagli = $conn->prepare("SELECT nome_prodotto, quantita, unita_misura, note_prodotto, stato_prodotto 
                                         FROM dettagli_ordine 
                                         WHERE id_ordine = ? 
                                         ORDER BY nome_prodotto ASC");
        if ($stmt_dettagli) {
            $stmt_dettagli->bind_param("i", $current_order_id);
            $stmt_dettagli->execute();
            $result_dettagli = $stmt_dettagli->get_result();
            $ordine_row['prodotti'] = $result_dettagli->fetch_all(MYSQLI_ASSOC);
            $stmt_dettagli->close();
        }
        $ordini_utente[] = $ordine_row;
    }
    $stmt_ordini->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Mie Richieste - Storico Acquisti</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Stili ripresi da gestioneordini.php per coerenza */
        html, body { background-color: #f8f9fa; color: #495057; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin:0; padding:0; }
        .module-page-container { max-width: 1100px; margin: 25px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 18px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); border-bottom: 4px solid #B08D57; margin-bottom: 30px; }
        .header-branding { display: flex; align-items: center; gap: 15px; }
        .header-branding .logo { max-height: 45px; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; }
        .user-session-controls { display: flex; align-items: center; gap: 15px; }
        .nav-link-button, .logout-button { text-decoration: none; padding: 8px 15px; border-radius: 5px; color: white !important; font-weight: 500; transition: background-color 0.2s ease; border: none; cursor: pointer; }
        .nav-link-button { background-color: #6c757d; }
        .logout-button { background-color: #D42A2A; }
        .module-content { background-color: #ffffff; padding: 25px 30px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .app-section-header h2 { font-size: 1.4em; color: #2E572E; margin: 0; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }
        
        .order-record { background-color: #fff; border: 1px solid #e9ecef; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        .order-summary { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; cursor: pointer; flex-wrap: wrap; gap: 10px; }
        .order-summary-info h3 { margin: 0 0 5px 0; color: #343a40; }
        .order-summary-info span { font-size: 0.9em; color: #6c757d; }
        .order-meta { display: flex; align-items: center; gap: 20px; font-size: 0.9em; color: #343a40; }
        .order-details { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-in-out, padding 0.4s ease-in-out; background-color: #fdfdfd; border-top: 1px solid #e9ecef; padding: 0 20px; }
        .order-record.is-open .order-details { max-height: 1000px; padding: 20px; }
        
        .product-detail-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f1f1; }
        .product-detail-item:last-child { border-bottom: none; }
        .product-info strong { color: #0056b3; }
        .product-info .notes { font-style: italic; color: #555; display: block; font-size: 0.85em; margin-top: 3px; }
        .product-status { font-weight: bold; text-align: right; flex-shrink: 0; min-width: 120px; }

        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8em; font-weight: bold; color: white; text-align: center; min-width: 120px; display: inline-block; }
        .status-badge.inviato { background-color: #007bff; }
        .status-badge.in-lavorazione { background-color: #17a2b8; }
        .status-badge.approvato { background-color: #28a745; }
        .status-badge.approvato-parzialmente { background-color: #ffc107; color: #212529; }
        .status-badge.rifiutato { background-color: #dc3545; }
        .status-badge.evaso { background-color: #6c757d; }

        .status-prodotto.Approvato { color: #28a745; }
        .status-prodotto.Rifiutato { color: #dc3545; }
        .status-prodotto.Inviato { color: #007bff; }
        .status-prodotto.Evaso { color: #6c757d; }
    </style>
</head>
<body>
    <div class="module-page-container">
        <header class="page-header">
            <div class="header-branding">
                <a href="dashboard.php"><img src="logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Le Mie Richieste</h1>
                    <h2>Storico Richieste di Acquisto</h2>
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
                <h2>Storico delle tue richieste</h2>
            </div>

            <div class="orders-list" id="orders-list-container">
                <?php if ($db_error_message): ?>
                    <p style="color: red;"><?php echo $db_error_message; ?></p>
                <?php elseif (empty($ordini_utente)): ?>
                    <p style="text-align:center; padding: 30px; color: #6c757d; font-style: italic;">Non hai ancora inviato nessuna richiesta di acquisto.</p>
                <?php else: ?>
                    <?php foreach ($ordini_utente as $ordine): ?>
                        <div class="order-record">
                            <div class="order-summary">
                                <div class="order-summary-info">
                                    <h3>Ordine #<?php echo htmlspecialchars($ordine['id_ordine']); ?> - <?php echo htmlspecialchars($ordine['centro_costo']); ?></h3>
                                    <span>Inviato il: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ordine['data_richiesta']))); ?></span>
                                </div>
                                <div class="order-meta">
                                    <?php $status_class = strtolower(str_replace(' ', '-', $ordine['stato_ordine'])); ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($ordine['stato_ordine']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="order-details">
                                <h4>Dettaglio Prodotti:</h4>
                                <?php if (!empty($ordine['prodotti'])): ?>
                                    <?php foreach ($ordine['prodotti'] as $prodotto): ?>
                                        <div class="product-detail-item">
                                            <div class="product-info">
                                                <strong><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></strong>
                                                (Quantità: <?php echo htmlspecialchars($prodotto['quantita']); ?> <?php echo htmlspecialchars($prodotto['unita_misura']); ?>)
                                                <?php if (!empty($prodotto['note_prodotto'])): ?>
                                                    <span class="notes">Note: <?php echo htmlspecialchars($prodotto['note_prodotto']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-status">
                                                <span class="status-prodotto <?php echo htmlspecialchars($prodotto['stato_prodotto']); ?>">
                                                    <?php echo htmlspecialchars($prodotto['stato_prodotto']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nessun prodotto trovato per questo ordine.</p>
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
    const ordersListContainer = document.getElementById('orders-list-container');
    if (ordersListContainer) {
        ordersListContainer.addEventListener('click', function(event) {
            const summary = event.target.closest('.order-summary');
            if (summary) {
                const orderRecord = summary.closest('.order-record');
                if (orderRecord) {
                    orderRecord.classList.toggle('is-open');
                }
            }
        });
    }
});
</script>
</body>
</html>