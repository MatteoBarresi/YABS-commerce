<?php
/*
gestisce inserimento / modifica di un prodotto
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

if (($_SESSION['tipo_utente'] ?? '') !== 'negoziante') {
    redirect('/home.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/venditore_vendi.php');
}

$vendor = $_SESSION['username'];
$action = sanitize_string($_POST['action'] ?? ''); //insert / update 

/* ---------- helper validazione campi prodotto ---------- 
    controlla contenuto campi
*/
function validate_product_fields(): array //TODO: inserire in utilities , anche funzione sotto
{
    $nome         = sanitize_string($_POST['nome'] ?? ''); //trim strip tags
    $prezzo       = $_POST['prezzo'] ?? '';
    $disponibilita = (int) ($_POST['disponibilita'] ?? -1);
    $descrizione  = sanitize_string($_POST['descrizione'] ?? '');
    $categoriaId  = ($_POST['id_categoria'] ?? '') !== '' ? (int) $_POST['id_categoria'] : null;

    //lunghezza
    if ($nome === '' || strlen($nome) > 50) {
        failed_request('Nome prodotto obbligatorio (max 50 caratteri).', '/venditore_vendi.php');
    }
    if (contains_dangerous_markup($nome)) { //xss
        failed_request('Nome prodotto non valido.', '/venditore_vendi.php');
    }

    $prezzoFloat = filter_var($prezzo, FILTER_VALIDATE_FLOAT);
    if ($prezzoFloat === false || $prezzoFloat < 1 || $prezzoFloat > 99999.99) { //TODO: min e max da inserire in costanti magari  e cambiare a 20 cent
        failed_request('Prezzo non valido (min 1, max 99999.99).', '/venditore_vendi.php');
    }
    if ($disponibilita < 0) { 
        failed_request('Disponibilità non valida.', '/venditore_vendi.php');
    }
    if ($descrizione !== '' && strlen($descrizione) > 200) {
        failed_request('Descrizione troppo lunga (max 200 caratteri).', '/venditore_vendi.php');
    }

    return [$nome, $prezzoFloat, $descrizione, $disponibilita, $categoriaId];
}

/* ---------- helper upload immagini ---------- 
    crea folder immagini, controlla formato e size accettati, crea path unico (nome diverso da quello dato dal client)
    sposta nella destinazione e carica img sul db
*/
function handle_image_uploads(PDO $pdo, int $productId): void  //TODO: inserire in utilities 
{
    //"name" = nome del file su client
    if (empty($_FILES['immagini']['name'][0])) { //nessuna img inserita
        return;
    }

    //cartella finale - crea percorso se non esiste
    $uploadDir = APP_ROOT . '/assets/img/prodotti/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, recursive: true); //restituisce bool quindi TODO: controllare se è andata a buon fine (quando lo fa)
    }

    //togliere webp anche da html 
    //sono iana mediatypes visualizzabili qui https://www.iana.org/assignments/media-types/media-types.xhtml#image:~:text=%5D%5BRFC9993%5D-,image,-Available%20Formats
    $allowed   = ['image/jpeg', 'image/png', 'image/webp']; //gli stessi sono scritti nell'html 
    $maxSize   = 2 * 1024 * 1024; // 2 MB

    //penso: se vengono caricate >5 img, le altre vengono scartate
    $maxFiles  = 5;
    $count     = min(count($_FILES['immagini']['name']), $maxFiles); 

    //gestione immagini - se qualcosa non va, non viene mostrato nessun messaggio
    for ($i = 0; $i < $count; $i++) { 
        /*
            ['error'] è il codice di errore associato con questo file upload.
            UPLOAD_ERROR_OK (value 0) means no error occurred.

            altri errori qui
            https://www.php.net/manual/en/reserved.variables.files.php#:~:text=Error%20code%20returned%20in%20%24_FILES%5B%27userfile%27%5D%5B%27error%27%5D.
        */

        //se ci sono errori nel caricamento, salta img
        if ($_FILES['immagini']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($_FILES['immagini']['size'][$i] > $maxSize) { //errori 1 o 2
            //continue;
            throw new \RuntimeException('Immagine ' . ($i + 1) . ' troppo grande (max 2MB).');
        }

        //formato immagine
        $mime = mime_content_type($_FILES['immagini']['tmp_name'][$i]);
        if (!in_array($mime, $allowed, true)) { //true confronta valore e tipo
            //continue;
            throw new \RuntimeException('Formato immagine ' . ($i + 1) . ' non supportato (jpg/png/webp).');
        }

        $ext      = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            default      => 'jpg',
        };
        $filename = 'p_' . $productId . '_' . uniqid() . '.' . $ext; //p_0001_unique.jpg
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['immagini']['tmp_name'][$i], $dest)) {

            // percorso relativo salvato nel DB (max 100 char come da schema)
            $percorso = 'assets/img/prodotti/' . $filename;

            //controllo che ci stia in 100 chars
            /*if (strlen($percorso) > 100) {
                $percorso = $filename; //TODO: togliere
            }*/
            insert_product_image($pdo, $productId, $percorso); 
        } else {
            throw new \RuntimeException('Impossibile salvare l\'immagine ' . ($i + 1) . ' sul server.');
        }
    }
}

try {
    $pdo = getConnection();

    /* ------------------------------------------------------------------ */
    /* 1. INSERIMENTO NUOVO PRODOTTO                                       */
    /* ------------------------------------------------------------------ */
    if ($action === 'insert_product') {

        [$nome, $prezzo, $descrizione, $disponibilita, $categoriaId] = validate_product_fields();

        $pdo->beginTransaction();

        $productId = insert_product($pdo, $vendor, $nome, $prezzo, $descrizione, $disponibilita, $categoriaId); 

        //carica img su server e db
        handle_image_uploads($pdo, $productId);
        $pdo->commit();

        $_SESSION['flash_success'] = 'Prodotto pubblicato con successo!';
        redirect('/venditore_vendi.php');

    /* ------------------------------------------------------------------ */
    /* 2. MODIFICA PRODOTTO                                                */
    /* ------------------------------------------------------------------ */
    } elseif ($action === 'update_product') {

        $productId = (int) ($_POST['id_prodotto'] ?? 0);
        if ($productId < 1) {
            failed_request('Prodotto non valido.', '/venditore_vendi.php');
        }

        // fetch categoria precedente (per aggiornare totale_prodotti)
        $stmtOld = $pdo->prepare('SELECT id_categoria FROM prodotto WHERE id = :id AND user_negoziante = :v LIMIT 1'); //categoria di questo prodotto
        $stmtOld->execute([':id' => $productId, ':v' => $vendor]);
        $oldCat = $stmtOld->fetchColumn();

        [$nome, $prezzo, $descrizione, $disponibilita, $categoriaId] = validate_product_fields();

        $pdo->beginTransaction();

        //aggiorna dati di questo prodotto
        $updated = update_product($pdo, $productId, $vendor, $nome, $prezzo, $descrizione, $disponibilita, $categoriaId);
        if (!$updated) {
            failed_request('Prodotto non trovato o non autorizzato.', '/venditore_vendi.php');
        }

        //non ho fatto il trigger per questo
        if ((int) $oldCat !== (int) $categoriaId) { // aggiorna categoria.totale_prodotti se categoria cambiata 
            if ($oldCat) { //diminuisce vecchia
                $pdo->prepare('UPDATE categoria 
                SET totale_prodotti = totale_prodotti - 1 
                WHERE id = :id')
                    ->execute([':id' => $oldCat]);
            }
            if ($categoriaId) { //incrementa nuova
                $pdo->prepare('UPDATE categoria SET totale_prodotti = totale_prodotti + 1 WHERE id = :id')
                    ->execute([':id' => $categoriaId]);
            }

            //alt funzione da applicare a oldCat e categoriaId
            /* 
                update categoria
                SET totale_prodotti = (SELECT COUNT(*) from prodotto as p where p.id_categoria = :cat) 
                WHERE id = :cat;

            */

        }
        $pdo->commit();

        $_SESSION['flash_success'] = 'Prodotto aggiornato con successo!';
        redirect('/venditore_vendi.php');

    } else {
        redirect('/venditore_vendi.php');
    }
} catch (\RuntimeException $e) {
    // errore upload immagini: rollback DB + messaggio utente
    failed_request($e->getMessage(), '/venditore_vendi.php');
} catch (PDOException $e) {
    failed_request('Errore di sistema. Riprova. '. $e->getMessage(), '/venditore_vendi.php');
}
