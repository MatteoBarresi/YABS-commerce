<?php
/**
 * Handler AJAX — aggiornamento stato ordine (solo negozianti) - chiamato da ordini.js
 * annulla ordine - solo cliente
 * 
 * funzioni di catalog usate: update_order_status, cancel_order - entrambe aggiornano stato 
 * solo la seconda controlla che sia aggiornabile - l'altra fa un controllo a parte
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

function ridondanze_prodotto(PDO $pdo, string $newStato, int $orderId) : void{
    if($newStato == 'fallito' || $newStato == 'annullato'){ //aggiorno disponibilità per ogni prodotto
        $prods = fetch_order_products($pdo, $orderId);
        $stmt = $pdo->prepare('UPDATE prodotto SET prodotto.disponibilita =  prodotto.disponibilita + :qt 
            WHERE prodotto.id = :pid and prodotto.user_negoziante IS NOT NULL');
        foreach($prods as $item){
            $stmt->execute([':qt' => $item['quantita'], ':pid' => $item['id_prodotto']]);
            if ($stmt->rowCount() == 0) 
                throw new RuntimeException("Non è stato possibile aggiornare il prodotto" . $item['id_prodotto'] . ' : ' . $item['nome'] );
        }        
    }
}

//loggato (sia negoziante (update_stato) che utente (cancel_order))
if (empty($_SESSION['username']) 
//    || ($_SESSION['tipo_utente'] ?? '') !== 'negoziante' //controllo spostato sotto nella action apposita
) {
    http_response_code(401); //Unauthorized. richiesta fallita perché mancante / invalida / scaduta delle credenziali di autenticazione
    echo json_encode(['error' => 'Non autorizzato.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); //(Method Not Allowed - es. inviare un POST a una pagina che accetta solo GET)
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}

$action  = sanitize_string($_POST['action'] ?? '');
$orderId = (int) ($_POST['id_ordine'] ?? 0);

if ($orderId < 1 
//|| $newStato === '' //spostato sotto nella action apposita
) {
    http_response_code(400);
    echo json_encode(['error' => 'Dati non validi.']);
    exit;
}

//aggiorna stato e invia notifica al cliente
try {
    $pdo     = getConnection();
    /* ------------------------------------------------------------------ 
        AGGIORNAMENTO STATO (negoziante)                               
     ------------------------------------------------------------------ */
    if ($action === 'update_stato') {


        //controlli--- è vendor, loggato, stato settato
        if (($_SESSION['tipo_utente'] ?? '') !== 'negoziante') {
            http_response_code(401); //Unauthorized.
            echo json_encode(['error' => 'Non autorizzato.']);
            exit;
        }

        $newStato = sanitize_string($_POST['stato'] ?? '');
        $vendor   = $_SESSION['username'];

        if ($newStato === '') {
            http_response_code(400); //Bad Request: il server non comprende la richiesta del client. Invalid syntax. Bad routing. Wrong parameters
            echo json_encode(['error' => 'Dati non validi.']);
            exit;
        }        

        //controllo che il vecchio stato sia aggiornabile
        $old = ordine_statoAttuale($pdo, $orderId);
        if(in_array($old, ['consegnato', 'annullato', 'fallito'])){
            echo json_encode(['error' => 'Impossibile aggiornare: l\'ordine si trova in uno stato irreversibile.']);
            exit;
        }

        if($old == 'in_transito' && $newStato == 'non_spedito'){
            echo json_encode(['error' => 'Impossibile aggiornare: l\'ordine è gia in transito.', 'stato_old' => $old]);
            exit;
        }

        $pdo->beginTransaction();
        //aggiorna stato e invia notifica al cliente
        $updated = update_order_status($pdo, $orderId, $newStato, $vendor);

        if (!$updated) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Impossibile aggiornare: ordine non trovato o stato non valido.']);
            exit;
        }

        //RIDONDANZE
        ridondanze_prodotto($pdo, $newStato, $orderId);
        
        notify_client_shipping($pdo, $orderId, $newStato);
        
        $pdo->commit();

        echo json_encode(['ok' => true, 'stato' => $newStato]);
/* ------------------------------------------------------------------ */
    /* ANNULLAMENTO ORDINE (cliente che lo ha ordinato)               */
    /* ------------------------------------------------------------------ */
    } elseif ($action === 'cancel_order') {

        //controlli -- è cliente, loggato, l'ordine può essere annullato?
        if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
            http_response_code(401); //Unauthorized.
            echo json_encode(['error' => 'Non autorizzato.']);
            exit;
        }

        //controllo che il vecchio stato sia aggiornabile
        $old = ordine_statoAttuale($pdo, $orderId);
        if(!in_array($old, ['non_spedito', 'in_transito'])){
            echo json_encode(['error' => 'Impossibile aggiornare: l\'ordine è già stato completato, annullato o fallito.']);
            exit;
        }
        

        $pdo->beginTransaction();

        $username  = $_SESSION['username'];
        $cancelled = cancel_order($pdo, $orderId, $username);


        if (!$cancelled) {
            echo json_encode(['error' => 'Impossibile annullare: l\'ordine non è più annullabile.']);
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exit;
        }

        //RIDONDANZE: se stato == 'annullato', aggiorna disponibilità per ogni prodotto 
        ridondanze_prodotto($pdo, 'annullato', $orderId);        
        
       // notifica i negozianti coinvolti nell'ordine annullato
        notify_vendor_cancellation($pdo, $orderId, $username);
        
        $pdo->commit();

        echo json_encode([
            'ok'            => true,
            'stato'         => 'annullato',
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Azione non riconosciuta.']);
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    //error_log('ordini_handler: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore di sistema.']);
}
