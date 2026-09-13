<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

//controllo che arriviamo con post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/register_tipo.php');
}

//controllo che user e psw siano in sessione
$draft = $_SESSION['register_draft'] ?? []; 
if (empty($draft['username']) || empty($draft['password_hash'])) {
    redirect('/register.php', 'Sessione scaduta. Registrati di nuovo.');
}

//controllo che il tipo sia uno di quelli possibili
$tipo = $_POST['tipo_utente'] ?? '';
if (!in_array($tipo, ['cliente', 'negoziante'], true)) {
    failed_request('Seleziona un tipo di account valido.', '/register_tipo.php');
}


/* 
=================================
INSERT vera e propria dell'utente
=================================
*/
try {
    $pdo = getConnection();

    /* 
        anche qui controlla user - email già in db --> non c'è bisogno perché è stato fatto al passo precedente (register_process.php)
        TODO: cancellare =============================================================
        */
        if (user_exists($pdo, $draft['username'], $draft['email'])) {
            unset($_SESSION['register_draft']);
            failed_request('Username o email già in uso.', '/register.php');
        }
//            cancellare =============================================================



    $indirizzo = !empty($draft['indirizzo']) ? $draft['indirizzo'] : null; 
    $nProdotti = ($tipo === 'negoziante') ? 0 : null; //per cliente usiamo null

    //insert con tutti i valori - da inserire nella parte dedicata all'utente 
    $stmt = $pdo->prepare(
        'INSERT INTO utente (username, email, psw, indirizzo, tipo_utente, n_prodotti_in_vendita)
         VALUES (:username, :email, :psw, :indirizzo, :tipo, :n_prodotti)'
    );
    $stmt->execute([
        ':username' => $draft['username'],
        ':email' => $draft['email'],
        ':psw' => $draft['password_hash'],
        ':indirizzo' => $indirizzo,
        ':tipo' => $tipo,
        ':n_prodotti' => $nProdotti,
    ]);

} catch (PDOException $e) {
    failed_request('Registrazione non riuscita. Verifica i dati.', '/register_tipo.php');
} 
//togliere probabilmente - se non ci sono chiamate a funzioni diverse da pdo - es null->func() genera errore diverso
catch (Throwable $e) {
    error_log($e->getMessage());
    failed_request('Errore di sistema.', '/register_tipo.php');
}

//ufficializza le variabili di sessione usate in questo stage
$_SESSION['username'] = $draft['username'];
$_SESSION['tipo_utente'] = $tipo;
$_SESSION['email'] = $draft['email'];
unset($_SESSION['register_draft']); //non siamo più nella fase di registrazione

$_SESSION['flash_success'] = 'Account creato! Scegli le categorie che ti interessano.';
redirect('/register_categorie.php');
