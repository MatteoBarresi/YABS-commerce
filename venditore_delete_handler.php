<?php
/**
 * gestisce richiesta AJAX per l'eliminazione di un prodotto (solo il negoziante può farlo). - chiamato in venditore.js
 * POST: action=delete_product, id_prodotto=N
 * Risponde con JSON.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

//solo loggato e negoziante - con post
if (empty($_SESSION['username']) || ($_SESSION['tipo_utente'] ?? '') !== 'negoziante') {
    http_response_code(401);    //Unauthorized. richiesta fallita perché mancante / invalida / scaduta delle credenziali di autenticazione
    echo json_encode(['error' => 'Non autorizzato.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);    //(Method Not Allowed - es. inviare un POST a una pagina che accetta solo GET)
    echo json_encode(['error' => 'Metodo non consentito.']);
    exit;
}

$productId = (int) ($_POST['id_prodotto'] ?? 0);
$vendor    = $_SESSION['username'];

//controllo id valido
if ($productId < 1) {
    http_response_code(400);    //Bad Request: il server non comprende la richiesta del client. Invalid syntax. Bad routing. Wrong parameters
    echo json_encode(['error' => 'Prodotto non valido.']);
    exit;
}

try {
    $pdo = getConnection();

    // recupera categoria e nome del prodotto prima della soft delete eliminarlo (per aggiornare totale_prodotti)
    $stmtCat = $pdo->prepare(
        'SELECT id_categoria, nome FROM prodotto WHERE id = :id AND user_negoziante = :v LIMIT 1'
    );
    $stmtCat->execute([':id' => $productId, ':v' => $vendor]);
    $prodRow = $stmtCat->fetch();
    $catId   = $prodRow['id_categoria'] ?? null;
    $prodNome = (string) ($prodRow['nome'] ?? '');


    /* 
    Se il prodotto si trova in un ordine non terminato (con consegna/fallimento) 
    */
    $stati_ordini_prodotto = order_states($pdo, $productId);
    foreach($stati_ordini_prodotto as $item){
        if($item['stato'] == 'in_transito' || $item['stato'] == 'non_spedito'){
            echo json_encode(['error' => 'Eliminazione Fallita: il Prodotto '. $productId .' si trova in un ordine non terminato.']);
            exit;
        }
    }

    $pdo->beginTransaction(); 

    // notifica clienti con il prodotto nel carrello + rimozione
    if ($prodNome !== '') {
        notify_oos_and_clean_carts($pdo, $productId, $prodNome);
    }

    /* 
    se il prodotto è stato ordinato, setto null e gestisco a mano gli attributi ridondanti
    se non è mai stato ordinato (es caricato per errore)--> procedi a fare delete normale (trigger on delete gestiscono)
    */
    if($stati_ordini_prodotto){
        
        $deleted = delete_product_soft($pdo, $productId, $vendor); //bool esito - setta a null campi - rimuove img e recensioni
    
        if (!$deleted) {
            echo json_encode(['error' => 'Prodotto non trovato o non autorizzato.']);
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exit;
        }
    
        // aggiorna attributi ridondanti: stessa cosa fatta nella insert
        //utente.n_prodotti 
        $pdo->prepare(
            'UPDATE utente SET n_prodotti_in_vendita = GREATEST(0, n_prodotti_in_vendita - 1) WHERE username = :u'
        )->execute([':u' => $vendor]);
    
        //categoria.totale_prodotti
        if ($catId) {
            $pdo->prepare(
                'UPDATE categoria SET totale_prodotti = GREATEST(0, totale_prodotti - 1) WHERE id = :id'
            )->execute([':id' => $catId]);
        }
    }else{
        $deleted = delete_product($pdo, $productId); //bool esito - fa delete vera e propria
        
        if (!$deleted) {
            echo json_encode(['error' => 'Prodotto non trovato o non autorizzato.']);
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exit;
        }

    }

    $pdo->commit();

    // dati paginazione per il JS (analogo a notifiche_handler e ordini_handler) - TODO: confronta con notifiche_handler
    $page  = max(1, (int) ($_POST['page']  ?? 1));
    $limit = max(1, (int) ($_POST['limit'] ?? 10));

    $remaining   = count_vendor_products($pdo, $vendor); //totali per questo negoziante (rinominare)
    $rowsInPage  = max(0, min($limit, $remaining - ( ($page - 1) * $limit) ));
    $hadNextPage = $remaining >= $page * $limit;

    echo json_encode([
        'ok'            => true,
        'remaining'     => $remaining,
        'rows_in_page'  => $rowsInPage,
        'had_next_page' => $hadNextPage,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
