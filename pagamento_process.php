<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

//solo cliente - solo post
if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    redirect('/home.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pagamento.php');
}

$username = $_SESSION['username'];

/* ---------- indirizzo ---------- */
$indirizzo = sanitize_string($_POST['indirizzo_spedizione'] ?? '');
//controlli lunghezza e contenuto
if ($indirizzo === '') {
    failed_request('Inserisci un indirizzo di spedizione.', '/pagamento.php');
}
if (strlen($indirizzo) > 50) {
    failed_request('Indirizzo troppo lungo (max 50 caratteri).', '/pagamento.php');
}
if (contains_dangerous_markup($indirizzo)) {
    failed_request('Indirizzo non valido.', '/pagamento.php');
}

/* ---------- carta ---------- */
$cartaScelta = sanitize_string($_POST['id_carta'] ?? 'nuova'); //possibile usare bool 

try {
    $pdo = getConnection();

    //controlli già fatti in pagamento.php ma rifaccio per richieste esterne 

    // Verifica che il carrello abbia prodotti attivi
    $items       = fetch_cart_items($pdo, $username);
    $activeItems = array_filter($items, fn($i) => $i['user_negoziante'] !== null); 

    if (count($activeItems) === 0) {
        failed_request('Il carrello è vuoto o non contiene prodotti disponibili.', '/carrello.php');
    }

    $cardId = null; // null = carta non salvata nel DB (pagamento fittizio)
    
    $pdo->beginTransaction(); //da qui perché altrimenti inserisce la carta anche se non va a buon fine l'acquisto
    
    if ($cartaScelta === 'nuova') {
        /* --- Nuova carta: validazione minima --- */
        $numero    = sanitize_string($_POST['numero_carta'] ?? '');
        $nome      = sanitize_string($_POST['nome_intestatario'] ?? '');
        $cognome   = sanitize_string($_POST['cognome_intestatario'] ?? '');
        $scadenza  = sanitize_string($_POST['data_scadenza'] ?? '');
        $salvaCarta = isset($_POST['salva_carta']) && $_POST['salva_carta'] === '1';

        // Validazioni 
        $numeroClean = preg_replace('/\s+/', '', $numero); //toglie spazi e tabulazioni
        if (!preg_match('/^\d{13,19}$/', $numeroClean)) {
            failed_request('Numero carta non valido.', '/pagamento.php');
        }
        if ($nome === '' || $cognome === '') {
            failed_request('Inserisci nome e cognome intestatario.', '/pagamento.php');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scadenza)) {//formato data (AAAA-mm-gg) 
            failed_request('Data di scadenza non valida.', '/pagamento.php');
        }

        if ($salvaCarta) { //aggiunge al db
            $ins = $pdo->prepare(
                'INSERT INTO carta (user_utente, numero, nome_intestatario, cognome_intestatario, data_scadenza)
                 VALUES (:u, :num, :nome, :cog, :scad)'
            );
            $ins->execute([
                ':u'    => $username,
                ':num'  => $numeroClean,
                ':nome' => $nome,
                ':cog'  => $cognome,
                ':scad' => $scadenza,
            ]);
            $cardId = (int) $pdo->lastInsertId();
        }
        // se non salvata: cardId rimane null 

    } else { /* --- Carta già memorizzata: verifica appartenga all'utente e non scaduta--- */
        $cardIdRaw = (int) $cartaScelta;
        $stmtC = $pdo->prepare(
            'SELECT id, data_scadenza FROM carta WHERE id = :id AND user_utente = :u LIMIT 1'
        );
        $stmtC->execute([':id' => $cardIdRaw, ':u' => $username]);
        $cartaRow = $stmtC->fetch();

        if (!$cartaRow) { //no results
            failed_request('Carta non valida.', '/pagamento.php');
        }
        if (new DateTime((string) $cartaRow['data_scadenza']) <= new DateTime()) { //carta scaduta - controllo mancante prima
            failed_request('La carta selezionata è scaduta.', '/pagamento.php');
        }

        $cardId = $cardIdRaw;
    }

    /* ---------- inserisce nella tabella ordine (i trigger fanno il resto) ---------- */
    //$pdo->beginTransaction();


    $orderId = insert_order($pdo, $username, $cardId, $indirizzo); //usata solo qui

    //era un trigger
    //trasferisce da carrello a ordine_prodotto (ogni prodotto ordinato)
    foreach($activeItems as $item){
        cart_to_op($pdo, 
                    $orderId, 
                    $item['id_prodotto'],
                    $item['quantita'],
                    $item['prezzo'],
        );
    }
    
    //aggiorna disponibilità prodotti - trigger apposito

    //svuota carrello - trigger apposito
     
    // per ogni prodotto appena ordinato, controlla i carrelli degli altri utenti:
    // se prodotto.disponibilità è scesa sotto la loro quantità, aggiorna il loro carrello
    // e manda notifica 'magazzino'
    notify_magazzino_and_fix_carts($pdo, $activeItems, $username);

    // Notifica negozianti
    notify_vendors_on_order($pdo, $orderId, $username);

    $pdo->commit();

} catch (PDOException $e) {
    //errore trigger (non è uno con sqlstate) - se è scritto male il trigger / comando sql, dice il codice e che es non trova campo 
    
    failed_request('Errore durante il pagamento. Riprova.'. $e->getMessage(), '/pagamento.php');
} catch (Throwable $e) { //errore generico
    
    failed_request('Errore di sistema.', '/pagamento.php');
}

$_SESSION['flash_success'] = 'Ordine #' . $orderId . ' confermato! Grazie per il tuo acquisto.';
redirect('/home.php');
