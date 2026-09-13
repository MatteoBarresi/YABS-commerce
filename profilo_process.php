<?php
/* 
gestisce le form di profilo.php
*/


require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/profilo.php');
}

$username = $_SESSION['username'];
$action   = sanitize_string($_POST['action'] ?? ''); //sicurezza esterni 

try {
    $pdo = getConnection();

    /* ------------------------------------------------------------------ */
    /* 1. AGGIORNA DATI PERSONALI                                         */
    /* ------------------------------------------------------------------ */
    if ($action === 'update_profile') {

        $email     = sanitize_string($_POST['email'] ?? '');
        $indirizzo = sanitize_string($_POST['indirizzo'] ?? '');

        //controlli regex, lunghezza, presenza tag
        if (($err = validate_email($email)) !== null) {
            failed_request($err, '/profilo.php');
        }
        if ($indirizzo !== '' && ($err = validate_indirizzo($indirizzo)) !== null) {
            failed_request($err, '/profilo.php');
        }

        // email già usata da un altro utente?
        $stmt = $pdo->prepare(
            'SELECT username FROM utente WHERE email = :e AND username != :u LIMIT 1'
        );
        $stmt->execute([':e' => $email, ':u' => $username]);
        if ($stmt->fetchColumn()) {
            failed_request('Questa email è già in uso.', '/profilo.php');
        }

        update_user_profile($pdo, $username, $email, $indirizzo);
        $_SESSION['flash_success'] = 'Dati aggiornati con successo.';
        redirect('/profilo.php');

    /* ------------------------------------------------------------------ */
    /* 2. CAMBIA PASSWORD                                                   */
    /* ------------------------------------------------------------------ */
    } elseif ($action === 'update_password') {

        $pswAttuale  = $_POST['psw_attuale']  ?? '';
        $pswNuova    = $_POST['psw_nuova']    ?? '';
        $pswConferma = $_POST['psw_conferma'] ?? '';

        // Verifica password attuale corretta
        $stmt = $pdo->prepare('SELECT psw FROM utente WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $hash = (string) $stmt->fetchColumn();

        //controlli
        if ($hash !== hash_password($pswAttuale, $username)) { //password_verify($pswAttuale, $hash);
            failed_request('Password attuale non corretta.', '/profilo.php');
        }
        if ($pswNuova !== $pswConferma) { //lo dovrei fare già lato client
            failed_request('Le due password non coincidono.', '/profilo.php');
        }
        if (($err = validate_password($pswNuova)) !== null) {
            failed_request($err, '/profilo.php');
        }

        update_user_password($pdo, $username, hash_password($pswNuova, $username)); // update_user_password($pdo, $username, password_hash($pswNuova))
        $_SESSION['flash_success'] = 'Password aggiornata con successo.';
        redirect('/profilo.php');

    /* ------------------------------------------------------------------ */
    /* 3. AGGIUNGI CARTA                                                   */
    /* ------------------------------------------------------------------ */
    } elseif ($action === 'add_card') {

        if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
            redirect('/profilo.php');
        }

        $numero   = preg_replace('/\s+/', '', sanitize_string($_POST['numero_carta'] ?? ''));
        $nome     = sanitize_string($_POST['nome_intestatario'] ?? '');
        $cognome  = sanitize_string($_POST['cognome_intestatario'] ?? '');
        $scadenza = sanitize_string($_POST['data_scadenza'] ?? '');

        //stesse regex del pagamento_process TODO: funzione apposita
        if (!preg_match('/^\d{13,19}$/', $numero)) {
            failed_request('Numero carta non valido.', '/profilo.php');
        }
        if ($nome === '' || $cognome === '') { //anche lato client (jquery validator)
            failed_request('Inserisci nome e cognome.', '/profilo.php');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scadenza) || new DateTime($scadenza) <= new DateTime()) {
            failed_request('Data di scadenza non valida o già scaduta.', '/profilo.php');
        }

        insert_card($pdo, $username, $numero, $nome, $cognome, $scadenza);
        $_SESSION['flash_success'] = 'Carta aggiunta con successo.';
        redirect('/profilo.php');

    /* ------------------------------------------------------------------ */
    /* 4. ELIMINA ACCOUNT                                                  */
    /* ------------------------------------------------------------------ */
    } elseif ($action === 'delete_account') {

        $psw = $_POST['psw_conferma_delete'] ?? '';

        //conferma che sia giusta
        $stmt = $pdo->prepare('SELECT psw FROM utente WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $hash = (string) $stmt->fetchColumn();

        if ($hash !== hash_password($psw, $username)) {//password_verify($psw, $hash);
            failed_request('Password non corretta. Account non eliminato.', '/profilo.php');
        }


        /* 
            trigger prodotti per categoria
            cancella img su disco (OK - non recensioni, solo prodotto) ma prodotto va settato null per ogni campo  
            cliente ha un prodotto in transito  - settare fallito

        */
        $pdo->beginTransaction();

        //se un negoziante cancella account, sistemo ordini in corso + soft delete per prodotti ordinati
        if (($_SESSION['tipo_utente'] ?? '') === 'negoziante') {

            // ordini in lavorazione con suoi prodotti -> falliti
            // (ripristina disponibilità prodotti di altri negozianti coinvolti + notifica cliente)
            fail_pending_orders_for_vendor($pdo, $username);

            //   ogni prodotto del negoziante: soft/hard delete 
            //   eliminate immagini prodotto + immagini recensioni sul prodotto
            //   gestisce ridondanza categoria.totale_prodotti
            delete_vendor_products($pdo, $username);
        }

        delete_user($pdo, $username);
        
        $pdo->commit();

        // Distruggi la sessione (che viene disattivata) e reindirizza al login
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php');
        exit;

    } else { //nessuna action valida
        redirect('/profilo.php');
    }

} catch (Throwable $e) {
    failed_request('Errore di sistema. Riprova.', '/profilo.php');
}
