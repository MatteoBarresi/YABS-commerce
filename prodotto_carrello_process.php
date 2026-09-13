<?php
/* 
da prodotto.php, l'utente aggiunge al carrello tramite form
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

//check che sia cliente
if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    failed_request('Solo i clienti possono usare il carrello.', '/home.php');
}

//check request giusta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/home.php');
}

//dati passati - form
$productId = (int) ($_POST['id_prodotto'] ?? 0); //hidden input
$quantita = (int) ($_POST['quantita'] ?? 0);
$username = $_SESSION['username'];

//check dati validi
if ($productId < 1 || $quantita < 1) {
    failed_request('Dati non validi.', '/home.php?'); 
}

try {
    $pdo = getConnection();
    $product = fetch_product_detail($pdo, $productId); //prodotto + categoria

    //check che il prodotto sia in vendita e disponibile
    if ($product === null || $product['user_negoziante'] === null) {
        failed_request('Prodotto non disponibile.', '/home.php');
    }

    $disponibilita = (int) ($product['disponibilita'] ?? 0); 
    if ($disponibilita < 1) {
        failed_request('Prodotto esaurito.', '/prodotto.php?id=' . $productId);
    }

    //per questo utente, quante copie di questo prodotto ha nel carrello (return [] se non c'è)
    $stmt = $pdo->prepare(
        'SELECT quantita 
        FROM carrello 
        WHERE user_cliente = :u AND id_prodotto = :p 
        LIMIT 1'
    );
    $stmt->execute([':u' => $username, ':p' => $productId]);
    $inCart = $stmt->fetch();
    $qtyInCart = $inCart ? (int) $inCart['quantita'] : 0;

    $pdo->beginTransaction(); 
    
    //prodotto già presente - aggiorna quantità
    if ($inCart) {
        $newQty = $qtyInCart + $quantita;
        if ($newQty > $disponibilita) { //non dovrebbe succedere - blocca già nel frontend
            failed_request(
                //'Quantità non disponibile (max ' . $disponibilita . ').',
                'Hai provato ad aggiungere troppi prodotti. Nel carrello ne hai già '
                . $qtyInCart . ' (disponibilità: ' . $disponibilita . ').',
                '/prodotto.php?id=' . $productId
            );
        }
        //query
        $upd = $pdo->prepare(
            'UPDATE carrello SET quantita = :q WHERE user_cliente = :u AND id_prodotto = :p'
        );
        $upd->execute([':q' => $newQty, ':u' => $username, ':p' => $productId]);
    } else {//prodotto non era presente
        if ($quantita > $disponibilita) { //controllo riserve - non dovrebbe succedere (trigger, controlli frontend)
            failed_request(
                //'Quantità non disponibile (max ' . $disponibilita . ').',
                'Hai provato ad aggiungere troppi prodotti (disponibilità: ' . $disponibilita . ').',
                '/prodotto.php?id=' . $productId
            );
        }

        //insert nel carrello
        $ins = $pdo->prepare(
            'INSERT INTO carrello (user_cliente, id_prodotto, quantita) VALUES (:u, :p, :q)'
        );
        $ins->execute([':u' => $username, ':p' => $productId, ':q' => $quantita]);
    }

    $pdo->commit();

} catch (PDOException $e) { //qualunque errore porta a pagina prodotto
    
    if((int)$e->errorInfo[0] == 45000) //- TODO: creare costante errore
        failed_request(
            //'Quantità non disponibile per questo prodotto.', 
            'Hai provato ad aggiungere troppi prodotti (disponibilità: ' . $disponibilita . ').',
        '/prodotto.php?id=' . $productId
    );
    
    failed_request('Impossibile aggiungere al carrello.', '/prodotto.php?id=' . $productId);
} catch (Throwable $e) { //generico
    failed_request('Errore di sistema.', '/db_error.php');
}

//imposta messaggio popup
$_SESSION['flash_success'] = 'Prodotto aggiunto al carrello!';
redirect('/home.php');
