<?php
/**
 * Handler AJAX — eliminazione notifiche.
 * POST: action=delete_one, id_notifica=N
 *       action=delete_all
 * Risponde con JSON.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

//si arriva con post + loggato
if (empty($_SESSION['username'])) {
    http_response_code(401); //Unauthorized. richiesta fallita perché mancante / invalida / scaduta delle credenziali di autenticazione
    echo json_encode(['error' => 'Non autorizzato.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); //(Method Not Allowed - es. inviare un POST a una pagina che accetta solo GET)
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}

$action   = sanitize_string($_POST['action'] ?? '');
$username = $_SESSION['username'];

try {
    $pdo = getConnection();

    if ($action === 'delete_one') { //viene passato id
        $notifId = (int) ($_POST['id_notifica'] ?? 0); 
        if ($notifId < 1) {
            http_response_code(400); //Bad Request: il server non comprende la richiesta del client. Invalid syntax. Bad routing. Wrong parameters
            echo json_encode(['error' => 'ID non valido.']);
            exit;
        }
        $deleted = delete_notification($pdo, $notifId/*, $username*/);
        if (!$deleted) {
            echo json_encode(['error' => 'Notifica non trovata.']);
            exit;
        }
        // parametri di paginazione passati dal JS (necessari per calcolare
        // quante righe rimangono nella pagina corrente e se esiste la successiva)
        $page  = max(1, (int) ($_POST['page']  ?? 1));
        $limit = max(1, (int) ($_POST['limit'] ?? 15));
        $totale = count_notifications($pdo, $username); //totale dopo la delete
        $offset       = ($page - 1) * $limit; //come calcolato in notifiche.php
        $rowsInPage   = max(0, min($limit, $totale - $offset)); // righe che rimangono in questa pagina
        $hadNextPage  = $totale >= $page * $limit;               // prima della delete c'era una pagina successiva -- maggiore o uguale perché quando rimane 1 nella next page, devo poterlo richiedere
        
        
        echo json_encode(['ok' => true, 
            'totale' => $totale, 
            'rows_in_page' => $rowsInPage,
            'had_next_page'=> $hadNextPage,
        ]);

    } elseif ($action === 'delete_all') {
        delete_all_notifications($pdo, $username);
        echo json_encode(['ok' => true, 'totale' => 0]);

    } else {
        http_response_code(400); //Bad Request
        echo json_encode(['error' => 'Azione non riconosciuta.']);
    }

} catch (Throwable $e) {
    http_response_code(500); //generico
    echo json_encode(['error' => 'Errore di sistema.']);
}
