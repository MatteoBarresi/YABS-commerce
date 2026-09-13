<?php
/* 
ci si arriva da prodotto.php, sezione recensioni. gli passiamo id prodotto, voto, testo
*/


require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    failed_request('Solo i clienti possono lasciare recensioni.', '/home.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/home.php');
}

//dati passati
$productId = (int) ($_POST['id_prodotto'] ?? 0); 
$valutazione = (int) ($_POST['valutazione'] ?? 0);
$testo = isset($_POST['testo']) ? sanitize_string($_POST['testo']) : ''; 
$username = $_SESSION['username'];
$redirect = '/prodotto.php?id=' . max(1, $productId); //max in caso di valori negativi o 0

//controllo validità id, voto, testo, pulizia testo
if ($productId < 1) {
    failed_request('Prodotto non valido.', '/home.php');
}

if ($valutazione < RECENSIONE_VOTO_MIN || $valutazione > RECENSIONE_VOTO_MAX) {
    failed_request('Seleziona una valutazione da 1 a 5 stelle.', $redirect);
}

if ($testo !== '' && strlen($testo) > RECENSIONE_TESTO_MAX) {
    failed_request('Testo recensione troppo lungo.', $redirect);
}

if ($testo !== '' && contains_dangerous_markup($testo)) {
    failed_request('Testo recensione non consentito.', $redirect);
}

try {
    $pdo = getConnection();
    $product = fetch_product_detail($pdo, $productId);

    //controlli disponibilità, recensione precedente, acquisto eseguito
    if ($product === null || $product['user_negoziante'] === null) { //prodotto null in caso non venga trovato; negoziante null se non è più in vendita
        failed_request('Prodotto non disponibile.', '/home.php');
    }

    if (fetch_client_review($pdo, $username, $productId) !== null) {
        failed_request('Hai già recensito questo prodotto.', $redirect);
    }

    if (!prodotto_arrivato($pdo, $username, $productId)) {
        failed_request('Puoi recensire solo prodotti che hai acquistato.', $redirect);
    }

    $pdo->beginTransaction();

    //query inserimento recensione
    $stmt = $pdo->prepare(
        'INSERT INTO recensione (user_cliente, id_prodotto, valutazione, testo, data_inserimento)
         VALUES (:u, :p, :v, :t, NOW())'
    );
    $stmt->execute([
        ':u' => $username,
        ':p' => $productId,
        ':v' => (int)$valutazione,
        ':t' => $testo !== '' ? $testo : null,
    ]);

    $reviewId = (int) $pdo->lastInsertId(); //id recensione per salvare img

    // upload immagine recensione (opzionale — max 1, jpg/png, max 2MB)
    if (!empty($_FILES['img_recensione']['name']) && $_FILES['img_recensione']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = APP_ROOT . '/assets/img/recensioni/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, /*0755, */recursive: true);
        }

        $allowed = ['image/jpeg', 'image/png'];
        $maxSize = 2 * 1024 * 1024;

        if ($_FILES['img_recensione']['size'] <= $maxSize) {
            $mime = mime_content_type($_FILES['img_recensione']['tmp_name']);
            if (in_array($mime, $allowed, true)) {
                //creo nome file
                $ext      = $mime === 'image/png' ? 'png' : 'jpg';
                $filename = 'r_' . $reviewId . '_' . uniqid() . '.' . $ext;
                $dest     = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['img_recensione']['tmp_name'], $dest)) {
                    $percorso = 'assets/img/recensioni/' . $filename;
                    $insImg = $pdo->prepare(
                        'INSERT INTO immagine_recensione (id_recensione, percorso) VALUES (:rid, :perc)'
                    );
                    $insImg->execute([':rid' => $reviewId, ':perc' => $percorso]);
                } else {
                    failed_request('Impossibile salvare l\'immagine sul server.', $redirect);
                }
            } else {
                failed_request('Formato immagine non supportato (solo jpg/png).', $redirect);
            }
        } else {
            failed_request('Immagine troppo grande (max 2MB).', $redirect);
        }
    }

    update_product_review_stats($pdo, $productId); //ridondanze: aggiorna numero recensioni e media recensioni

    // notifica al venditore
    notify_vendor_review($pdo, $productId, (string) $product['nome'], $username, (int) $valutazione);

    $pdo->commit();
} catch (PDOException $e) {
    if((int)$e->errorInfo[0] == 45000){
        failed_request('Puoi recensire solo prodotti acquistati.', $redirect);
    }
    failed_request('Recensione non pubblicata.', $redirect); //errore di connessione
} catch (Throwable $e) {
    failed_request('Errore di sistema.', $redirect);
}

$_SESSION['flash_success'] = 'Recensione pubblicata, grazie!';
redirect($redirect);
