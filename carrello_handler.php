<?php
/**
 * Handler AJAX carrello (carrello.js)
 * Azioni POST: update_qty, remove_item
 * Risponde sempre con JSON.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

/*header http inviato al client per dire di interpretare la risposta in un certo modo: 
contiene dati JSON; codifica caratteri per evitare problemi con lettere accentate, simboli
*/
header('Content-Type: application/json; charset=utf-8'); 

// Solo clienti autenticati
if (empty($_SESSION['username']) || ($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    http_response_code(401); //Unauthorized. richiesta fallita perché mancante / invalida / scaduta delle credenziali di autenticazione
    echo json_encode(['error' => 'Non autorizzato.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); //(Method Not Allowed - es. inviare un POST a una pagina che accetta solo GET)
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}

//lo script gestisce diverse richieste ajax - ognuna passa dati diversi con post
$action     = sanitize_string($_POST['action'] ?? '');
$productId  = (int) ($_POST['id_prodotto'] ?? 0); //può non essere nel carrello se la richiesta non avviene dalla form del sito
$username   = $_SESSION['username'];

if ($productId < 1) {
    http_response_code(400); //Bad Request: il server non comprende la richiesta del client. Invalid syntax. Bad routing. Wrong parameters
    echo json_encode(['error' => 'Prodotto non valido.']);
    exit;
}

try {
    $pdo = getConnection();

    if ($action === 'update_qty') { //una delle due action usate in carrello.js - aggiorna quantità di un prodotto nel carrello
        $qty = (int) ($_POST['quantita'] ?? 0);

        $result = update_cart_quantity($pdo, $username, $productId, $qty); //tenta di aggiornare e restituisce oggetto [risultato]

        if (isset($result['error'])) { 
            echo json_encode(['error' => $result['error']]);
            exit;
        }

        // Ricalcola riga e totale aggiornato
        $items   = fetch_cart_items($pdo, $username); //tutti i prodotti che ha nel carrello
        $total   = calc_cart_total($items);
        $rowItem = null;

        foreach ($items as $item) { //cerca il prodotto in questione (di cui vuole aggiornare quantità) tra tutti quelli che ha nel carrello
            if ((int) $item['id_prodotto'] === $productId) {
                $rowItem = $item;
                break;
            }
        }

        echo json_encode([
            'ok'        => true,
            'subtotal'  => format_price((string) ((float) ($rowItem['prezzo'] ?? 0) * $qty)), //prezzo aggiornato
            'total'     => format_price((string) $total),
            'cart_count' => array_sum(array_column($items, 'quantita')), //quanti prodotti nel carrello
        ]);

    } elseif ($action === 'remove_item') { //altra possibile operazione - rimuove e ricalcola cose
        remove_cart_item($pdo, $username, $productId); //se dati inesistenti, non fa danni - db inalterato

        $items = fetch_cart_items($pdo, $username);
        $total = calc_cart_total($items);

        echo json_encode([
            'ok'         => true,
            'total'      => format_price((string) $total),
            'cart_count' => array_sum(array_column($items, 'quantita')),
            'empty'      => count($items) === 0, //se quello eliminato era l'unico, allora true 
        ]);

    } else { //richiesta inesistente
        http_response_code(400);
        echo json_encode(['error' => 'Azione non riconosciuta.']);
    }

} catch (Throwable $e) {
    http_response_code(500); //Internal Server Error - condizione inaspettata (generico)
    echo json_encode(['error' => 'Errore di sistema.']);
}
