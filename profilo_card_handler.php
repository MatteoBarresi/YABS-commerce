<?php
/**
 * Handler AJAX — rimozione carta dal profilo - richiesta che parte da profilo.js
 * POST: action=remove_card, id_carta=N
 * Risponde con JSON (error=> stringa oppure ok=> bool).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

//controlli cliente loggato + arriviamo con post
if (empty($_SESSION['username']) || ($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    http_response_code(401);    //Unauthorized. richiesta fallita perché mancante / invalida / scaduta delle credenziali di autenticazione
    echo json_encode(['error' => 'Non autorizzato.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);    //(Method Not Allowed - es. inviare un POST a una pagina che accetta solo GET)
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}

$action  = sanitize_string($_POST['action'] ?? '');
$cardId  = (int) ($_POST['id_carta'] ?? 0); //cosa passata che ci interessa
$username = $_SESSION['username'];

//controllo action e id validi
if ($action !== 'remove_card' || $cardId < 1) {
    http_response_code(400);    //Bad Request: il server non comprende la richiesta del client. Invalid syntax. Bad routing. Wrong parameters
    echo json_encode(['error' => 'Richiesta non valida.']);
    exit;
}

try { //query cancella carta dell'utente
    $pdo     = getConnection();
    $deleted = delete_card($pdo, $cardId, $username);

    if (!$deleted) {
        echo json_encode(['error' => 'Carta non trovata o non autorizzato.']);
    } else {
        echo json_encode(['ok' => true]);
    }

} catch (Throwable $e) {
    http_response_code(500);    //generico
    echo json_encode(['error' => 'Errore di sistema.']);
}
