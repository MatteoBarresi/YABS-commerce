<?php
/**
 * 
 * contiene query e alcuni metodi di validazione
 * TODO: dividere in più file in base alla tabella
 *  
 */

declare(strict_types=1);



//mappe enum - incluso qui perché catalog.php può essere richiesto senza passare da head.php
require_once __DIR__ . '/enums.php';


/* 
    se è settato il cookie con categorie preferite, genera array e lo restituisce
*/
function get_preferred_category_ids(): array
{
    if (empty($_COOKIE['categorie_preferite'])) { //stringa con tutte categorie
        return [];
    }
    $raw = explode(',', (string) $_COOKIE['categorie_preferite']); //crea array con stringa 
    $ids = [];
    foreach ($raw as $id) { //push stuff
        $id = (int) $id;
        if ($id > 0) { //ignora 0 e negativi - anche (int)stringa diventa 0
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids)); //restituisce valori presi una sola volta in un array con indici interi (se prima era associativo) 
}

/* 
    serve per il testo della recensione: se è null o vuoto, restituisce stringa vuota. 
    toglie spazi bianchi e controlla che sia < di una certa lunghezza: se non lo è, mostra i primi tot caratteri
*/
function truncate_product_text(?string $text, int $max = 60): string
{
    if ($text === null || trim($text) === '') {
        return '';
    }
    $text = trim($text);
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max) . '…';
}


/**
 * select categorie (id, nome) per cui esistono prodotti in vendita - check che ci sia un prodotto con quella 
 * categoria come fk e che il negoziante non l'abbia "tolto" dal db 
 * (raggruppa in base al nome categoria)
 * group by per prenderle una volta sola - alt SELECT DISTINCT
 * 
 * prima prendeva tutte le categorie con almeno un prodotto in vendita. 
 * ora aggiunti parametri opzionali che di base fanno funzionare uguale
 * 
 */
function fetch_categories_with_products(PDO $pdo, array $categoryIds = [], int $limit = 0, int $offset = 0): array
{
    //select categorie per cui esistono prodotti in vendita
    $sql = 'SELECT c.id, c.nome
         FROM categoria c
         INNER JOIN prodotto p ON p.id_categoria = c.id AND p.user_negoziante IS NOT NULL';
    $params = [];

    //aggiungo condizione per filtro categorie (quando si passa ?cat[])
    if ($categoryIds !== []) { 
        $placeholders = [];
        foreach ($categoryIds as $i => $id) {
            $key = ':cid' . $i; //:cid1, :cid7, ecc
            $placeholders[] = $key;
            $params[$key] = $id; //binding [':cid1' => 1]
        }
        $sql .= ' AND c.id IN (' . implode(', ', $placeholders) . ')';
    }

    //perché abbiamo un risultato per ogni prodotto
    $sql .= ' GROUP BY c.id, c.nome 
            ORDER BY c.nome ASC';

    //quale "pagina" di categorie deve restituire (infinite scroll di home.php) 
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
    

/*
    $stmt = $pdo->query(
        'SELECT c.id, c.nome
         FROM categoria c
         INNER JOIN prodotto p ON p.id_categoria = c.id AND p.user_negoziante IS NOT NULL
         GROUP BY c.id, c.nome
         ORDER BY c.nome ASC'
    );
    return $stmt->fetchAll();*/
}

/**
 * Conta quante categorie hanno almeno un prodotto in vendita (stesso join di
 * fetch_categories_with_products, senza LIMIT/OFFSET). Usata per sapere se
 * l'infinite scroll di home.php deve continuare a fare richieste o fermarsi.
 *
 * @param list<int> $categoryIds  stesso filtro opzionale (già espanso con i figli)
 */
function count_categories_with_products(PDO $pdo, array $categoryIds = []): int
{
    //count una sola volta (altrimenti più prodotti per categoria)
    $sql = 'SELECT COUNT(DISTINCT c.id)
            FROM categoria c
            INNER JOIN prodotto p ON p.id_categoria = c.id AND p.user_negoziante IS NOT NULL';
    $params = [];

    //binding filtro
    if ($categoryIds !== []) {
        $placeholders = [];
        foreach ($categoryIds as $i => $id) {
            $key = ':cid' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $sql .= ' AND c.id IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * prende query preparata + params da bindare (sotto forma di array associativo) 
 * 
 * restituisce un array associativo - tipo (
 *      [0] => (key1 => value1, key2 => value2)
 *      [1] => (key1 => value1, key2 => value2)
 *      )
 */
function fetch_products_for_row(PDO $pdo, string $sql, array $params = [], int $limit = 12): array
{
    $stmt = $pdo->prepare($sql . ' LIMIT ' . (int) $limit);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* 
    select dati su tutti i prodotti attualmente in vendita + immagine


    seleziona dati su prodotto - join id prodotto con fk su img_prod

    query interna seleziona fk_prodotto e percorso img - group by perché ci possono essere più img per prodotto - min per restituire un solo risultato:
    per non avere una tabella sbilenca - alt DISTINCT o Group by 
    
    tiene conto che il prodotto sia in vendita (negoziante non null)
*/
function product_card_sql_base(): string
{
    return 'SELECT p.id, p.nome, p.prezzo, p.descrizione, p.media_recensioni, p.n_recensioni,
                   ip.percorso AS img
            FROM prodotto p
            LEFT JOIN (
                SELECT MIN(id) as id, id_prodotto, percorso
                '.
                //SELECT id_prodotto, MIN(percorso) AS percorso
                '
                FROM immagine_prodotto
                GROUP BY id_prodotto
            ) ip ON ip.id_prodotto = p.id
            WHERE p.user_negoziante IS NOT NULL';
}

/**
 * @return list<array<string, mixed>>
 * select prodotti in vendita e screma per una categoria specifica e ordina per giorno di pubblicazione da più recente 
 */
function fetch_products_by_category(PDO $pdo, int $categoryId, int $limit = 12): array
{
    return fetch_products_for_row(
        $pdo,
        product_card_sql_base() . ' AND p.id_categoria = :cat
         ORDER BY p.data_pubblicazione DESC',
        [':cat' => $categoryId],
        $limit
    );
}

/**
 * @return list<array<string, mixed>>
 * 
 *  query su tutti i prodotti di determinate categorie (prepared stmt)
 * 
 * categoryIds è un array con id categorie
 * se sono impostate categorie preferite, usa l'array associativo per preparare query: crea placeholder (prepend 'c' alle key)
 * ordina per views da più a meno, ordina per data più recente 
 * 
 *  ((key=> value, key2=>value2 ecc), (), ())
 */
function fetch_suggested_products(PDO $pdo, array $categoryIds, int $limit = 12): array
{
    if ($categoryIds === []) {
        return [];
    }
    $placeholders = []; //placeholders tipo cCASA, cTECH ecc
    $params = []; //array associativo cCategoria => id - usato nel binding
    foreach ($categoryIds as $i => $id) {
        $key = ':c' . $i; //actually c2, c0, ecc
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $in = implode(', ', $placeholders); //stringa, lista di placeholder

    return fetch_products_for_row(
        $pdo,
        product_card_sql_base() . " AND p.id_categoria IN ($in)
         ORDER BY p.visualizzazioni DESC, p.data_pubblicazione DESC",
        $params,
        $limit
    );
}

/**
 * @return list<array<string, mixed>>
 * 
 * cerca tutti i prodotti e ordina da più recente a meno
 * 
 */
function fetch_new_arrivals(PDO $pdo, int $limit = 12): array
{
    return fetch_products_for_row(
        $pdo,
        product_card_sql_base() . '
         ORDER BY p.data_pubblicazione DESC',
        [],
        $limit
    );
}

/**
 * @return array{count: int, has_removed: bool}
 * 
 * conta quanti prodotti sono nel carrello e quanti sono rimossi per questo utente
 * somma qta per un prodotto (o 0 se null);
 * controlla quando negoziante sia null (somma 1 altrimenti 0)
 * JOIN su prodotto: solo quelli il cui id è presente nel carrello
 * 
 * restituisce array associativo con numero di prodotti e quanti sono stati rimossi
 *  
 * 
 * quando trova null mette 1; quando trova altro 0 e poi fa la somma--> ottiene prodotti soft rimossi 
 * uso CASE perché trasforma true-> 1; false-> 0 - alt COUNT(CASE WHEN p.user_negoziante IS NULL THEN 1 END)
 * in MYsql (non in SQL standard) vale anche SUM(p.user_negoziante IS NULL)
 */
function get_cart_navbar_info(PDO $pdo, string $username): array
{
    $stmt = $pdo->prepare(
        'SELECT SUM(c.quantita) AS totale,
                SUM(CASE WHEN p.user_negoziante IS NULL THEN 1 ELSE 0 END) AS rimossi
         FROM carrello c
         INNER JOIN prodotto p ON p.id = c.id_prodotto
         WHERE c.user_cliente = :u'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch() ?: ['totale' => 0, 'rimossi' => 0];

    return [
        'count' => (int) $row['totale'],
        'has_removed' => ((int) $row['rimossi']) > 0,
    ];
}

/* 
    tipo 1.000,37 euro
*/
function format_price(?string $prezzo): string
{
    if ($prezzo === null || $prezzo === '') {
        return '—';
    }
    return number_format((float) $prezzo, 2, ',', '.') . ' €';
}

/**
 * @return array<string, mixed>|null
 * 
 * seleziona dati del prodotto passato (per id) + nome categoria (fk in prodotto)
 * 
 */
function fetch_product_detail(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome, p.prezzo, p.descrizione, p.disponibilita,
                p.user_negoziante, p.id_categoria, p.data_pubblicazione,
                p.visualizzazioni, p.n_recensioni, p.media_recensioni,
                c.nome AS categoria_nome
         FROM prodotto p
         LEFT JOIN categoria c ON c.id = p.id_categoria
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $productId]);
    $row = $stmt->fetch();
    return $row ? $row: null; //se true, $row
}

/**
 * @return list<string>
 * 
 * immagini per id prodotto passato - le ordina per id (= ordine in cui sono state inserite)
 */
function fetch_product_images(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT percorso FROM immagine_prodotto WHERE id_prodotto = :id ORDER BY id ASC'
    );
    $stmt->execute([':id' => $productId]);
    return array_column($stmt->fetchAll(), 'percorso'); //sotto forma di array normale
}

/* 
    aumenta di uno le views di un prodotto (passato id)
*/
function increment_product_views(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare(
        'UPDATE prodotto SET visualizzazioni = visualizzazioni + 1 WHERE id = :id'
    );
    $stmt->execute([':id' => $productId]);
}


/* 
controllo che il prodotto sia stato acquistato dall'utente e che sia arrivato 
*/
function prodotto_arrivato(PDO $pdo, string $username, int $productId) :bool {
     $stmt = $pdo->prepare(
        'SELECT (stato)
         FROM ordine o
         INNER JOIN ordine_prodotto op ON op.id_ordine = o.id
         WHERE o.user_cliente = :u AND op.id_prodotto = :p 
         ' //evito limit perché l'utente può comprare lo stesso prodotto in ordini diversi - basta che uno sia arrivato
        /* alt
        SELECT stato
        FROM ordine o , ordine_prodotto op
        Where o.id = op.id_ordine AND 
        o.user_cliente = 'cliente' AND op.id_prodotto = :p
        */
    );
    $stmt->execute([':u' => $username, ':p' => $productId]);
    
    $result = $stmt->fetchAll();
    foreach($result as $op)
        if($op['stato'] == 'consegnato' ||  $op['stato'] == 'fallito') //hard coded - valori enum che vengono trattati come string TODO: fare enum/costanti?
            return true;
    return false;

}

/**
 * @return array<string, mixed>|null
 * 
 * seleziona recensione di un cliente per un prodotto specifico
 * 
 * 
 */
function fetch_client_review(PDO $pdo, string $username, int $productId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.testo, r.valutazione AS valutazione, r.data_inserimento
         FROM recensione r
         WHERE r.user_cliente = :u AND r.id_prodotto = :p
         LIMIT 1'
    );
    $stmt->execute([':u' => $username, ':p' => $productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return list<array<string, mixed>>
 * 
 * TUTTE LE recensioni per un prodotto
 * 
 */
function fetch_product_reviews(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.testo, r.valutazione AS valutazione,
                r.data_inserimento, r.user_cliente, 
                ir.percorso AS img_recensione
         FROM recensione r
         LEFT JOIN immagine_recensione ir ON ir.id_recensione = r.id
         WHERE r.id_prodotto = :p
         ORDER BY r.data_inserimento DESC'
    );
    $stmt->execute([':p' => $productId]);
    return $stmt->fetchAll();
}

/* 
    aggiorna prodotto.numero/media_recensioni in base a id prodotto passato
    
    conta quante volte appare nella table recensione 
    fa media con avg rece.valutazione quando match fk prodotto 

    no righe con quell'id, viene restituito NULL, ma non è un problema: non si setta una colonna esistente a NULL
    meglio senza COALESCE perché è più facile debuggare visto che la colonna valutazione è not null 
*/

function update_product_review_stats(PDO $pdo, int $productId): int //void
{
    $stmt = $pdo->prepare(
        'UPDATE prodotto p
         SET p.n_recensioni = (
                 SELECT COUNT(*) FROM recensione r WHERE r.id_prodotto = p.id
             ),
             p.media_recensioni = './/COALESCE(
             '(
                 SELECT AVG(r.valutazione)
                 FROM recensione r WHERE r.id_prodotto = p.id
             )'.//, 0)
         'WHERE p.id = :id'
    );
    $stmt->execute([':id' => $productId]);
    return $stmt->rowCount(); //righe interessate

}

/* 
    crea stringa con stelline colorate in base al voto e la mette in un tag html
*/

function render_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $filled = str_repeat('★', $rating);
    $empty = str_repeat('☆', 5 - $rating);
    return '<span class="product-stars" aria-label="' . $rating . ' su 5">' . $filled . $empty . '</span>';
}

/* ================================================================
   CARRELLO
   ================================================================ */

/**
 * 
 * @return list<array<string, mixed>>
 * 
 * prende user utente e restituisce tutti i prodotti che ha nel carrello: 
 * dati prodotti presi perché è fk in carrello
 * 
 * query interna: seleziona id e tutte img di un percorso - raggruppa per id - 
 * left join mantiene anche quelli che sono null nel carrello 
 * 
 * 
 */
function fetch_cart_items(PDO $pdo, string $username): array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.id_prodotto,
            c.quantita,
            p.nome,
            p.prezzo,
            p.disponibilita,
            p.user_negoziante,
            ip.percorso AS img
         FROM carrello c
         INNER JOIN prodotto p ON p.id = c.id_prodotto
         LEFT JOIN (
             SELECT id_prodotto, MIN(percorso) AS percorso
             FROM immagine_prodotto
             GROUP BY id_prodotto
         ) ip ON ip.id_prodotto = p.id
         WHERE c.user_cliente = :u
         ORDER BY p.nome ASC'
    );
    $stmt->execute([':u' => $username]);
    return $stmt->fetchAll();
}

/**
 * @return array{ok?: bool, error?: string}
 * 
 * Aggiorna la quantità di un prodotto NEL CARRELLO.
 * Return ['ok'=>true] o ['error'=>'messaggio'].
 * 
 * cerca disponibilità e venditore di un prodotto che gli passiamo: 
 * se false/empty o negoziante== null, è stato rimosso
 * 
 * se ut cerca di diminuire da 1 a 0 --> errore (dovrei impedire già nel frontend)
 * se ut seleziona qta > disponibilità --> errore (dopo submit o a ogni click?)
 * 
 * quindi fa update e restituisce array associativo con esito (con semplice bool non possiamo specificare errore)
 */
function update_cart_quantity(PDO $pdo, string $username, int $productId, int $qty): array
{
    // Controlla disponibilità prodotto
    $stmt = $pdo->prepare(
        'SELECT disponibilita, user_negoziante FROM prodotto WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if (!$product || $product['user_negoziante'] === null) {
        return ['error' => 'Prodotto non più disponibile.'];
    }
    if ($qty < 1) {
        return ['error' => 'Quantità minima: 1.'];
    }
    if ($qty > (int) $product['disponibilita']) {
        return ['error' => 'Disponibilità insufficiente (max ' . (int) $product['disponibilita'] . ').'];
    }

    $upd = $pdo->prepare(
        'UPDATE carrello SET quantita = :q WHERE user_cliente = :u AND id_prodotto = :p'
    );
    $upd->execute([':q' => $qty, ':u' => $username, ':p' => $productId]);
    return ['ok' => true];
}

/**
 * Rimuove il prodotto selezionato dal carrello dell'utente. - anche se input con dati inesistenti, non fa danni
 * TODO: no feedback può essere un problema 
 */
function remove_cart_item(PDO $pdo, string $username, int $productId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM carrello WHERE user_cliente = :u AND id_prodotto = :p'
    );
    $stmt->execute([':u' => $username, ':p' => $productId]);
}

/**
 * Calcola il totale del carrello (solo prodotti con negoziante attivo).
 * 
 * l'array passato è un array associativo con dati sui prodotti in un carrello: 
 * per ogni prodotto controlla che sia ancora nello store (venditore non null), e incrementa totale 
 */
function calc_cart_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        if ($item['user_negoziante'] !== null) {
            $total += (float) $item['prezzo'] * (int) $item['quantita'];
        }
    }
    return $total;
}

/* ================================================================
   ORDINE + PAGAMENTO
   ================================================================ */
/**
 * 
 * @return list<array<string, mixed>>
 * 
 * query su table "carta" - seleziona le carte salvate di un utente
 * 
 */
function fetch_user_cards(PDO $pdo, string $username): array
{
    $stmt = $pdo->prepare(
        'SELECT id, numero, nome_intestatario, cognome_intestatario, data_scadenza
         FROM carta
         WHERE user_utente = :u
         ORDER BY id DESC'
    );
    $stmt->execute([':u' => $username]);
    return $stmt->fetchAll();
}

/**
 * Inserisce un ordine (i trigger SQL pensano al resto:
 * ListaProdotti, SvuotaCarrello, AggiornaDisponibilita).
 * Restituisce l'id del nuovo ordine.
 * 
 */
function insert_order(PDO $pdo, string $username, ?int $cardId, string $indirizzo): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO ordine (user_cliente, id_carta, data_ordine, stato, indirizzo_spedizione)
         VALUES (:u, :carta, NOW(), :stato, :ind)'
    );
    $stmt->execute([
        ':u'     => $username,
        ':carta' => $cardId,
        ':stato' => 'non_spedito',
        ':ind'   => $indirizzo,
    ]);
    return (int) $pdo->lastInsertId();
}

/*
Corrispettivo del trigger ListaProdotti - la uso in pagamento_process

insert into Ordine_Prodotto 
Select new.id, c.id_prodotto, c.quantita, p.prezzo
From Carrello as c, Prodotto as p
Where p.id = c.id_prodotto and c.user_cliente = new.user_cliente

item che ha nel carrello sono già fetched
non serve user
*/
function cart_to_op(PDO $pdo, int $orderId, int $idProdotto, int $quantita, float $prezzo): void
{
    $stmt = $pdo->prepare(
        'INSERT into ordine_prodotto (id_ordine, id_prodotto, quantita, prezzo) 
        VALUES (:ordid, :pid, :qt, :prc)'
    );
    $stmt->execute([
        ':ordid' => $orderId, 
        ':pid' => $idProdotto, 
        ':qt' => $quantita, 
        ':prc' => $prezzo 
    ]);

}

/**
 * Invia una notifica "acquisto" ai negozianti coinvolti nell'ordine.
 * Chiamata dopo insert_order (quando ordine_prodotto è già popolato dal trigger).
 * 
 * prima query: per ogni prodotto nell'ordine (match su id che è fk in ordine_prodotto), seleziona il negoziante
 * controlla che l'id dell'ordine sia quello e che il negoziante non sia null per quel prodotto - ovviamente se è cliccato "ordina" il prodotto è ancora nel db  
 * 
 */
function notify_vendors_on_order(PDO $pdo, int $orderId, string $clientUsername): void
{
    // Recupera i negozianti coinvolti nell'ordine (una sola volta)
    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.user_negoziante, p.nome AS nome_prodotto
         FROM ordine_prodotto op
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE op.id_ordine = :oid AND p.user_negoziante IS NOT NULL'
    );
    $stmt->execute([':oid' => $orderId]);
    $rows = $stmt->fetchAll();

    //query insert in notifica
    $ins = $pdo->prepare(//letta = 0 di default
        'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
         VALUES (:tipo, :testo, NOW(), :user)'
    );

    //per ogni negoziante (se più prodotti per lo stesso negoziante, funziona uguale)
    foreach ($rows as $row) {
        $testo = 'Ordine #' . $orderId . ': ' . $clientUsername . ' ha effettuato un ordine che include "' . $row['nome_prodotto'] . '".'; // indicare order_id mi serve per linkare
        //$testo = $clientUsername . ' ha effettuato un ordine che include "' . $row['nome_prodotto'] . '".';
        $ins->execute([
            ':tipo'  => 'acquisto',
            ':testo' => substr($testo, 0, 255),
            ':user'  => $row['user_negoziante'],
        ]);
    }
}

/* ================================================================
   PROFILO UTENTE
   ================================================================ */

/**
 * Dati completi di un utente.
 * @return array<string, mixed>|null
 * 
 * seleziona utente (prende username == pk)
 * restituisce array associativo con i dati or null se non esiste
 * 
 * la register usa altra funzione ma posso usare questa
 */
function fetch_user_profile(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare(
        'SELECT username, email, indirizzo, tipo_utente, n_prodotti_in_vendita
         FROM utente WHERE username = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Aggiorna email e/o indirizzo di un utente.
 * 
 */
function update_user_profile(PDO $pdo, string $username, string $email, string $indirizzo): void
{
    $stmt = $pdo->prepare(
        'UPDATE utente SET email = :email, indirizzo = :ind WHERE username = :u'
    );
    $stmt->execute([':email' => $email, ':ind' => $indirizzo, ':u' => $username]);
}

/**
 * Aggiorna la password di un utente (già hashed).
 */
function update_user_password(PDO $pdo, string $username, string $hashedPsw): void
{
    $stmt = $pdo->prepare('UPDATE utente SET psw = :psw WHERE username = :u');
    $stmt->execute([':psw' => $hashedPsw, ':u' => $username]);
}

/**
 * Elimina un utente e tutti i dati collegati (CASCADE in DB).
 */
function delete_user(PDO $pdo, string $username): void
{
    // cancella dal disco le immagini dei prodotti del negoziante (se è un negoziante)
    // e le immagini delle recensioni scritte dall'utente (se è un cliente)
    // — va fatto PRIMA del DELETE perché il CASCADE rimuove le righe dal DB
    // ma non i file fisici
    $vendorImgs = fetch_product_image_paths_by_vendor($pdo, $username); //array vuoto se cliente
    //$reviewImgs = fetch_review_image_paths_by_user($pdo, $username);
    delete_files_on_disk(//array_merge(
    $vendorImgs
    //, $reviewImgs)
    );

    $stmt = $pdo->prepare('DELETE FROM utente WHERE username = :u');
    $stmt->execute([':u' => $username]);
}

/**
 * Id e categoria di tutti i prodotti in vendita di un negoziante.
 * Usata da delete_vendor_products prima di eliminare l'account.
 * @return list<array{id:int, id_categoria:int|null}>
 */
function fetch_vendor_product_ids(PDO $pdo, string $vendor): array
{
    $stmt = $pdo->prepare('SELECT id, id_categoria FROM prodotto WHERE user_negoziante = :v');
    $stmt->execute([':v' => $vendor]);
    return $stmt->fetchAll();
}

/**
 * Elimina/soft-elimina TUTTI i prodotti di un negoziante che sta eliminando
 * l'account (chiamata PRIMA di delete_user).
 *
 * Per ogni prodotto, stessa logica di venditore_delete_handler.php ma ripetuta
 * su tutto il catalogo del negoziante:
 *  - se è già stato ordinato almeno una volta: soft-delete (delete_product_soft),
 *    che pulisce anche dal disco le immagini del prodotto E quelle delle sue
 *    recensioni (fetch_review_image_paths_by_product) 
 *  - altrimenti (mai ordinato): delete_product, che innesca il CASCADE su
 *    immagine_prodotto/recensione/immagine_recensione
 *
 * Nel primo caso va decrementato a mano categoria.totale_prodotti
 */
function delete_vendor_products(PDO $pdo, string $vendor): void
{
    $products = fetch_vendor_product_ids($pdo, $vendor);

    foreach ($products as $prod) {
        $productId = (int) $prod['id'];
        $catId     = $prod['id_categoria']; //salvato PRIMA della delete/soft-delete (che lo azzera o lo rimuove)

        $hasOrders = order_states($pdo, $productId) /*!== []*/;

        if ($hasOrders) {
            delete_product_soft($pdo, $productId, $vendor);

            //ridondanza categoria.totale_prodotti
            if ($catId) {
                $pdo->prepare(
                    'UPDATE categoria SET totale_prodotti = GREATEST(0, totale_prodotti - 1) WHERE id = :id'
                )->execute([':id' => $catId]);
            }
        } else { //gestito dalla logica del db, quindi superfluo
            delete_product($pdo, $productId); 
            //ridondanza gestita con trigger
        }
        
    }
}

/**
 * Quando un negoziante elimina l'account, gli ordini "in lavorazione" che 
 * contengono i suoi prodotti, vengono segnati 'fallito'.
 *
 * lo stato vale per l'intero ORDINE: se contiene prodotti di ALTRI negozianti, 
 * fallisce anche per loro. 
 * come in ordini_handler.php, viene ripristinata la disponibilità di TUTTI 
 * i prodotti dell'ordine + viene mandata una notifica al cliente.
 *
 * @return list<int> id degli ordini falliti
 */
function fail_pending_orders_for_vendor(PDO $pdo, string $vendor): array
{
    $stmt = $pdo->prepare(
        //select ordini in corso che contengono prodotti del negoziante
        'SELECT DISTINCT o.id
         FROM ordine o
         INNER JOIN ordine_prodotto op ON op.id_ordine = o.id
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE p.user_negoziante = :v
           AND o.stato IN (\'non_spedito\', \'in_transito\')'
    );
    $stmt->execute([':v' => $vendor]);
    $orderIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    //questo è per i prodotti di altri negozianti
    $stmtQt = $pdo->prepare(
        'UPDATE prodotto SET disponibilita = disponibilita + :qt
         WHERE id = :pid AND user_negoziante IS NOT NULL'
    );

    foreach ($orderIds as $orderId) {
        //ripristina la disponibilità (stessa logica di ridondanze_prodotto in ordini_handler.php)
        foreach (fetch_order_products($pdo, $orderId) as $item) {
            $stmtQt->execute([':qt' => $item['quantita'], ':pid' => $item['id_prodotto']]);
        }

        //fail ordine
        $pdo->prepare('UPDATE ordine SET stato = \'fallito\' WHERE id = :id')
            ->execute([':id' => $orderId]);

        notify_client_shipping($pdo, $orderId, 'fallito');
    }

    return $orderIds;
}

/**
 * Aggiunge una carta di credito al profilo.
 * 
 * gli passiamo pk user, numero carta, nome cognome tizio, scadenza
 * RESTITUISCE id carta
 */
function insert_card(PDO $pdo, string $username, string $numero, string $nome,
                     string $cognome, string $scadenza): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO carta (user_utente, numero, nome_intestatario, cognome_intestatario, data_scadenza)
         VALUES (:u, :num, :nome, :cog, :scad)'
    );
    $stmt->execute([
        ':u'    => $username,
        ':num'  => $numero,
        ':nome' => $nome,
        ':cog'  => $cognome,
        ':scad' => $scadenza,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Elimina una carta verificando che appartenga all'utente.
 * 
 * restituisce bool con risultato operazione
 */
function delete_card(PDO $pdo, int $cardId, string $username): bool
{
    $stmt = $pdo->prepare(
        'DELETE FROM carta WHERE id = :id AND user_utente = :u'
    );
    $stmt->execute([':id' => $cardId, ':u' => $username]);
    return $stmt->rowCount() > 0;
}

/* ================================================================
   ORDINI
   ================================================================ */


function count_orders(PDO $pdo, string $username): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ordine WHERE user_cliente = :u');
    $stmt->execute([':u' => $username]);
    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string,mixed>>
 * 
 * Ordini di un cliente con totale calcolato, impaginati.
 * 
 * dati degli ordini fatti da un cliente (where o.user == user). 
 * per ogni ordine, controlla in ordine_prodotto, quali prodotti sono stati acquistati 
 * e somma prezzo * quantità - no coalesce perché sono not NULL
 * 
 * raggruppa per id ordine (basta lui), data, stato e indirizzo - alcuni DBMS richiedono gr By completo 
 * 
 * limit e offset servono per non fare una query troppo pesante - vedi ordini.php
 */
function fetch_client_orders(PDO $pdo, string $username, int $limit = 20, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT o.id, o.data_ordine, o.stato, o.indirizzo_spedizione,
                './/COALESCE(
                'SUM(op.prezzo * op.quantita)
                './/, 0) 
                'AS totale
         FROM ordine o
         LEFT JOIN ordine_prodotto op ON op.id_ordine = o.id
         WHERE o.user_cliente = :u
         GROUP BY o.id, o.data_ordine, o.stato, o.indirizzo_spedizione
         ORDER BY o.data_ordine DESC
         LIMIT :lim OFFSET :off'
    );
    //bind invece execute perché non voglio che limit e offset vengano inviati come stringhe (alcuni driver PDO lo fanno)
    $stmt->bindValue(':u',   $username, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT); 
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * @return list<array<string,mixed>>
 * 
 * Prodotti di un singolo ordine.
 * 
 * query interna: seleziona id prodotto e immagine
 * 
 * passiamo id ordine--> seleziona dati sui prodotti nell'ordine (id, qta, prezzo - nome, venditore, img)
 * 
 * 
 */
function fetch_order_products(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare(
        'SELECT op.id_prodotto, op.quantita, op.prezzo,
                p.nome, p.user_negoziante,
                ip.percorso AS img
         FROM ordine_prodotto op
         LEFT JOIN prodotto p ON p.id = op.id_prodotto
         LEFT JOIN (
             SELECT id_prodotto, MIN(percorso) AS percorso
             FROM immagine_prodotto GROUP BY id_prodotto
         ) ip ON ip.id_prodotto = op.id_prodotto
         WHERE op.id_ordine = :id'
    );
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string,mixed>>
 * 
 * Ordini ricevuti da un negoziante (prodotti che vende), paginati.
 * 
 * 
 * prende user_negoziante e 
 * seleziona dati degli ordini che ha ricevuto (id, data, stato, destinatario, calcola totale - deve fare join su ordine_prodotto) 
 * 
 */
function fetch_vendor_orders(PDO $pdo, string $vendor, int $limit = 20, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT o.id, o.data_ordine, o.stato,
                o.indirizzo_spedizione, o.user_cliente,
                SUM(op.prezzo * op.quantita) AS totale
         FROM ordine o
         INNER JOIN ordine_prodotto op ON op.id_ordine = o.id
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE p.user_negoziante = :v
         GROUP BY o.id, o.data_ordine, o.stato, o.indirizzo_spedizione, o.user_cliente
         ORDER BY o.data_ordine DESC
         LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue(':v',   $vendor, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * @return list<array<string,mixed>>
 * Prodotti di un ordine che appartengono a uno specifico negoziante.
 * 
 * prende id ordine + id negoziante
 * 
 * seleziona dati dei prodotti venduti da uno specifico negoziante (param id) in uno specifico ordine (param id) ordine: 
 * un prodotto (nome, id - come fk vabbe) e prezzo e qta quando è stato venduto + immagine (quindi 3 tabelle coinvolte)
 *   
 * query interna seleziona id prodotto + path sua immagine (una) 
 * 
 */
function fetch_vendor_order_products(PDO $pdo, int $orderId, string $vendor): array
{
    $stmt = $pdo->prepare(
        'SELECT op.id_prodotto, op.quantita, op.prezzo, p.nome,
                ip.percorso AS img
         FROM ordine_prodotto op INNER JOIN prodotto p ON p.id = op.id_prodotto
         LEFT JOIN (
             SELECT id_prodotto, MIN(percorso) AS percorso
             FROM immagine_prodotto GROUP BY id_prodotto
         ) ip ON ip.id_prodotto = op.id_prodotto
         WHERE op.id_ordine = :oid AND p.user_negoziante = :v'
    );
    $stmt->execute([':oid' => $orderId, ':v' => $vendor]);
    return $stmt->fetchAll();
}


/* restituisce lo stato dell'ordine (lo uso quando si aggiorna, per controllare che si inserisca un nuovo stato valido) */
function ordine_statoAttuale(PDO $pdo, int $orderId) : string{
    $stmt = $pdo->prepare('SELECT stato from ordine WHERE id = :oid');
    $stmt->execute([':oid' => $orderId]);
    $old = $stmt->fetch();
    return $old['stato'];
}

/**
 * IL NEGOZIANTE Aggiorna lo stato di un ordine (solo negoziante, solo transizioni valide).
 * Ritorna true se aggiornato, false se ordine non trovato / stato non valido.
 * 
 */
function update_order_status(PDO $pdo, int $orderId, string $newStatus, string $vendor): bool
{

    //enum possibili - se il param non è qui, si ferma e fallisce
    $allowed = ['non_spedito', 'in_transito', 'consegnato', 'fallito']; //alt - array_filter(array_keys($arr), fn($item) => $item != "annullato")
    if (!in_array($newStatus, $allowed, true)) { //case sensitive
        return false;
    }

    // Verifica che il negoziante abbia almeno un prodotto in quell'ordine
    //conta rows 
    //prodotti venduti in un ordine (che stiamo chiedendo) e per il negoziante (che stiamo chiedendo)
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM ordine_prodotto op
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE op.id_ordine = :oid AND p.user_negoziante = :v'
    );
    $check->execute([':oid' => $orderId, ':v' => $vendor]);

    //conta colonna a caso, andava bene anche rows penso...
    //se non ci sono dati, end
    if ((int) $check->fetchColumn() === 0) {
        return false;
    }

    //altrimenti aggiorna stato ordine
    $stmt = $pdo->prepare('UPDATE ordine SET stato = :s WHERE id = :id');
    $stmt->execute([':s' => $newStatus, ':id' => $orderId]);
    return $stmt->rowCount() > 0; //rows interessate dal comando
}

/** 
 * Annulla un ordine — solo il cliente proprietario, solo se lo stato
 * è ancora 'non_spedito' o 'in_transito'.
 * true se l'annullamento è andato a buon fine.
 */
function cancel_order(PDO $pdo, int $orderId, string $username): bool
{
    $stmt = $pdo->prepare(
        "UPDATE ordine
         SET stato = 'annullato'
         WHERE id = :id
           AND user_cliente = :u
           AND stato IN ('non_spedito', 'in_transito')" //TODO: controllo anche in ordini_handler 
    );
    $stmt->execute([':id' => $orderId, ':u' => $username]);
    return $stmt->rowCount() > 0;
}



/* ================================================================
   VENDITORE — GESTIONE PRODOTTI
   ================================================================ */

/**
 * @return list<array<string,mixed>>
 * TODO: eliminare
 * select tutte le categorie (usata per inserimento prodotto).
 * 
 * 
 */
/*function fetch_all_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, nome, is_a FROM categoria ORDER BY nome ASC'
    );
    return $stmt->fetchAll();
}
*/
/**
 * @return list<array<string,mixed>>
 * 
 * Prodotti in vendita di un negoziante, impaginati (articolo singolo - non importa disponibilità). 
 * seleziona dati sui prodotti where venditore è quello passato. 
 * Tra i dati sono compresi categoria e immagine (per cui deve fare due join)
 * 
 * query interna: trova id_prodotto e immagine in immagine_prodotto per linkare con prodotto corrispondente  
 */
function fetch_vendor_products(PDO $pdo, string $vendor, int $limit = 12, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome, p.prezzo, p.disponibilita,
                p.data_pubblicazione, p.visualizzazioni,
                p.n_recensioni, p.media_recensioni,
                c.nome AS categoria,
                ip.percorso AS img
         FROM prodotto p
         LEFT JOIN categoria c ON c.id = p.id_categoria
         LEFT JOIN (
             SELECT id_prodotto, MIN(percorso) AS percorso
             FROM immagine_prodotto GROUP BY id_prodotto
         ) ip ON ip.id_prodotto = p.id
         WHERE p.user_negoziante = :v
         ORDER BY p.data_pubblicazione DESC
         LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue(':v',   $vendor, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Conta i prodotti in vendita di un negoziante (param).
 */
function count_vendor_products(PDO $pdo, string $vendor): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM prodotto WHERE user_negoziante = :v'
    );
    $stmt->execute([':v' => $vendor]);
    return (int) $stmt->fetchColumn();
}

/**
 * Inserisce un nuovo prodotto e restituisce il suo id.
 */
function insert_product(PDO $pdo, string $vendor, string $nome, float $prezzo,
                        string $descrizione, int $disponibilita, ?int $categoriaId): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prodotto
            (nome, prezzo, descrizione, disponibilita, user_negoziante, id_categoria, data_pubblicazione)
         VALUES
            (:nome, :prezzo, :desc, :disp, :vendor, :cat, CURDATE())'
    );
    $stmt->execute([
        ':nome'   => $nome,
        ':prezzo' => $prezzo,
        ':desc'   => $descrizione,
        ':disp'   => $disponibilita,
        ':vendor' => $vendor,
        ':cat'    => $categoriaId,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Salva il percorso di un'immagine prodotto.
 * 
 * fa un'insert in immagine_prodotto - gli passiamo quale prodotto e il percorso. 
 */
function insert_product_image(PDO $pdo, int $productId, string $percorso): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO immagine_prodotto (id_prodotto, percorso) VALUES (:pid, :perc)'
    );
    $stmt->execute([':pid' => $productId, ':perc' => $percorso]);
}

/**
 * Aggiorna i campi modificabili di un prodotto (solo del negoziante proprietario).
 * 
 * nella query specifico id prodotto e id negoziante - se non c'è riscontro, restituito false 
 */
function update_product(PDO $pdo, int $productId, string $vendor, string $nome,
                        float $prezzo, string $descrizione, int $disponibilita,
                        ?int $categoriaId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE prodotto
         SET nome = :nome, prezzo = :prezzo, descrizione = :desc,
             disponibilita = :disp, id_categoria = :cat
         WHERE id = :id AND user_negoziante = :v'
    );
    $stmt->execute([
        ':nome'   => $nome,
        ':prezzo' => $prezzo,
        ':desc'   => $descrizione,
        ':disp'   => $disponibilita,
        ':cat'    => $categoriaId,
        ':id'     => $productId,
        ':v'      => $vendor,
    ]);
    return $stmt->rowCount() > 0;
}

/**
 * Elimina dal disco i file. 
 * percorso relativo passato nell'array.
 * Il percorso nel DB è relativo alla root del progetto (APP_ROOT),
 * es. "assets/img/prodotti/p_12_abc.jpg".
 *
 * @param list<string> $paths  percorsi relativi come salvati in DB
 */
function delete_files_on_disk(array $paths): void
{
    foreach ($paths as $path) {
        if ($path === '' || $path === null) {
            continue;
        }
        $abs = APP_ROOT . '/' . ltrim($path, '/');
        if (is_file($abs)) {
            @unlink($abs); //silenzia errori: se il file non esiste non è un problema bloccante.
        }
    }
}

/**
 * Recupera i percorsi delle immagini di un prodotto.
 * Usata prima di delete per poter cancellare i file dal disco.
 * @return list<string>
 */
function fetch_product_image_paths(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT percorso FROM immagine_prodotto WHERE id_prodotto = :id'
    );
    $stmt->execute([':id' => $productId]);
    return array_column($stmt->fetchAll(), 'percorso');
}

/**
 * percorsi delle immagini nelle recensioni di un prodotto.
 * @return list<string>
 */
function fetch_review_image_paths_by_product(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT ir.percorso
         FROM immagine_recensione ir
         INNER JOIN recensione r ON r.id = ir.id_recensione
         WHERE r.id_prodotto = :id'
    );
    $stmt->execute([':id' => $productId]);
    return array_column($stmt->fetchAll(), 'percorso');
}

/**
 * percorsi delle immagini nelle recensioni scritte da un utente.
 * @return list<string>
 */
function fetch_review_image_paths_by_user(PDO $pdo, string $username): array
{
    $stmt = $pdo->prepare(
        'SELECT ir.percorso
         FROM immagine_recensione ir
         INNER JOIN recensione r ON r.id = ir.id_recensione
         WHERE r.user_cliente = :u'
    );
    $stmt->execute([':u' => $username]);
    return array_column($stmt->fetchAll(), 'percorso');
}

/**
 * percorsi di tutte le immagini dei prodotti di un negoziante.
 * Usata da delete_user prima di cancellare l'account.
 * @return list<string>
 */
function fetch_product_image_paths_by_vendor(PDO $pdo, string $vendor): array
{
    $stmt = $pdo->prepare(
        'SELECT ip.percorso
         FROM immagine_prodotto ip
         INNER JOIN prodotto p ON p.id = ip.id_prodotto
         WHERE p.user_negoziante = :v'
    );
    $stmt->execute([':v' => $vendor]);
    return array_column($stmt->fetchAll(), 'percorso');
}

/**
 * setta a null campi di un prodotto (può farlo solo il negoziante proprietario) 
 * TODO:non necessario negoziante... fare un if prima
 * 
 * true se ha successo
 */
function delete_product_soft(PDO $pdo, int $productId, string $vendor): bool
{
    // recupera e cancella dal disco le immagini del prodotto PRIMA della soft-delete:
    // con soft-delete user_negoziante viene settato NULL ma le righe in
    // immagine_prodotto restano (il CASCADE su DELETE non scatta)
    $imgPaths    = fetch_product_image_paths($pdo, $productId);
    $revImgPaths = fetch_review_image_paths_by_product($pdo, $productId);
    delete_files_on_disk(array_merge($imgPaths, $revImgPaths));
        //////////////////// ripeto sotto  - TODO: fare funzione

    // possibile mettere all'esterno
    // rimuove le righe da immagine_prodotto (il prodotto rimane ma senza immagini)
    $pdo->prepare('DELETE FROM immagine_prodotto WHERE id_prodotto = :id')
        ->execute([':id' => $productId]);
        
    //rimuovere recensioni
    $pdo->prepare('DELETE FROM recensione WHERE id_prodotto = :id') //rimuove da immagine_recensione di conseguenza
        ->execute([':id' => $productId]);


    $stmt = $pdo->prepare(
        //'DELETE FROM prodotto WHERE id = :id AND user_negoziante = :v' //non necessario negoziante... - fare un if prima?
        'UPDATE prodotto 
        SET prezzo = null, descrizione = null, disponibilita = null, user_negoziante = null, id_categoria = null, data_pubblicazione = null,
            visualizzazioni = null, n_recensioni = null, media_recensioni = null 
        WHERE id = :id'
    );
    $stmt->execute([':id' => $productId/*, ':v' => $vendor*/]);
    return $stmt->rowCount() > 0; //TODO: serve??
}
/* 
come sopra ma lo elimina davvero dal db
*/
function delete_product(PDO $pdo, int $productId): bool
{
    // recupera e cancella dal disco le immagini PRIMA del DELETE:
    // il CASCADE rimuove le righe da immagine_prodotto/immagine_recensione
    // ma non i file fisici sul disco
    $imgPaths    = fetch_product_image_paths($pdo, $productId);
    $revImgPaths = fetch_review_image_paths_by_product($pdo, $productId);
    delete_files_on_disk(array_merge($imgPaths, $revImgPaths));
    //////////////////// aggiunto anche sopra - TODO: fare funzione
    $stmt = $pdo->prepare(
    'DELETE FROM prodotto WHERE id = :id'
    );
    $stmt->execute([':id' => $productId]);
    return $stmt->rowCount() > 0;

}

/* 
restituisce stati degli ordini in cui si trova il prodotto passato
*/
function order_states(PDO $pdo, int $productId) : array 
{
    $stmt = $pdo->prepare(
        'SELECT op.id_prodotto, o.stato
        from ordine_prodotto as op, ordine as o
        where o.id = op.id_ordine and op.id_prodotto = :id'
    );
    $stmt->execute([':id' => $productId]);
    return $stmt->fetchAll(); 
}


/**
 * Invia notifica aggiornamento spedizione al cliente.
 * 
 * select utente (pk) che ha fatto l'ordine (che passiamo) e lo usa per fare insert in "notifica"
 *
 */
function notify_client_shipping(PDO $pdo, int $orderId, string $newStatus): void
{
    $stmt = $pdo->prepare(
        'SELECT user_cliente FROM ordine WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $cliente = $stmt->fetchColumn(); //seleziona unico dato richiesto == username
    if (!$cliente) { return; } //no results

    //annullato è incluso nella mappa
    $map   = ordine_stato_map(); //in enums.php
    $label = isset($map[$newStatus]) ? strtolower($map[$newStatus]['label']) 
            : $newStatus;
    $testo = "Il tuo ordine #{$orderId} è ora: {$label}.";

    //inserisce in notifica
    $ins = $pdo->prepare( //letta = 0 di default
        'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
         VALUES (:tipo, :testo, NOW(), :u)'
    );
    $ins->execute([
        ':tipo'  => 'aggiornamento_spedizione',
        ':testo' => $testo,
        ':u'     => $cliente,
    ]);
}



/* ================================================================
   NOTIFICHE
   ================================================================ */

/**
 * @return list<array<string,mixed>>
 * 
 * seleziona tutte le notifiche dell'utente passato come param - più recenti prima.
 * 
 */
function fetch_notifications(PDO $pdo, string $username, int $limit = 20, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT id, tipo, testo, data_notifica, letta
         FROM notifica
         WHERE user_cliente = :u
         ORDER BY data_notifica DESC
         LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue(':u',   $username, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Conta le notifiche totali di un utente. restituisce il numero - se prima si usa fetch_notifications, basta size array
 * più sicuro di count(fetch_notifications) perché conta quelle vere e non con offset troppo alti
 */
function count_notifications(PDO $pdo, string $username): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifica WHERE user_cliente = :u');
    $stmt->execute([':u' => $username]);
    return (int) $stmt->fetchColumn();
}

/**
 * 
 * Elimina una singola notifica (param) di un utente utente (param).
 * 
 * 
 */
function delete_notification(PDO $pdo, int $notifId/*, string $username*/): bool
{
    $stmt = $pdo->prepare(
        'DELETE FROM notifica WHERE id = :id'// AND user_cliente = :u' //TODO: inutile passare user
    );
    $stmt->execute([':id' => $notifId/*, ':u' => $username*/]);
    return $stmt->rowCount() > 0;
}

/**
 * Elimina tutte le notifiche di un utente.
 */
function delete_all_notifications(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare('DELETE FROM notifica WHERE user_cliente = :u');
    $stmt->execute([':u' => $username]);
}


/* ================================================================
   NOTIFICHE — helper aggiuntivi (badge navbar, deep link)
   ================================================================ */

/**
 * Conta le notifiche NON lette di un utente (letta = 0).
 * Usata per il badge in navbar.
 */
function count_unread_notifications(PDO $pdo, string $username): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM notifica WHERE user_cliente = :u AND letta = 0'
    );
    $stmt->execute([':u' => $username]);
    return (int) $stmt->fetchColumn();
}

/**
 * Setta come lette (letta = 1) tutte le notifiche non lette di un utente.
 * Chiamata all'apertura di notifiche.php.
 */
function mark_all_notifications_read(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare(
        'UPDATE notifica SET letta = 1 WHERE user_cliente = :u AND letta = 0' //and superfluo
    );
    $stmt->execute([':u' => $username]);
}


/**
 * Estrae l'id ordine da un testo di notifica del tipo
 * "Ordine #12: ..." oppure "Il tuo ordine #12 è ora: ...".
 * Ritorna null se non trovato.
 * 
 */
function extract_order_id_from_text(string $testo): ?int
{
    if (preg_match('/ordine\s*#(\d+)/i', $testo, $m)) {
        return (int) $m[1];
    }
    return null;
}

/**
 * TODO: spostata in enums.php 
 * Costruisce il link contenuto in una notifica, in base al tipo
 * e al testo (da cui si estrae l'id ordine quando presente).
 */
/*function notif_link(string $tipo, string $testo): ?string {
    switch ($tipo) {
        
        case 'acquisto':
        case 'aggiornamento_spedizione':
            $orderId = extract_order_id_from_text($testo);
            return $orderId !== null
                ? BASE_URL . '/ordini.php?expand=' . $orderId
                : BASE_URL . '/ordini.php';

        case 'oos':
        case 'magazzino':
            return BASE_URL . '/carrello.php';

        default:
            return null;
    }
}*/


/* ================================================================
   FILTRO CATEGORIE (albero, per home.php e results.php)
   ================================================================ */


/* 
===================================================================================================
TODO: eliminare
*/
/**
 * Per ogni categoria base, conta le derivate dirette 
 * @return list<array<string,mixed>>
 */
function fetch_root_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT c.id, c.nome,
                (SELECT COUNT(*) FROM categoria child WHERE child.is_a = c.id) AS n_figlie
         FROM categoria c
         WHERE c.is_a IS NULL
         ORDER BY c.nome ASC'
    );
    return $stmt->fetchAll();
}

/**
 * Categorie figlie di una categoria madre (is_a = :parent).
 * @return list<array<string,mixed>>
 */
function fetch_child_categories(PDO $pdo, int $parentId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome FROM categoria WHERE is_a = :p ORDER BY nome ASC'
    );
    $stmt->execute([':p' => $parentId]);
    return $stmt->fetchAll();
}

/* 
===================================================================================================
*/

/**
 * Tutte le categorie (radice + figlie) in un'unica struttura ad albero,
 * usata per popolare il pannello filtro lato server in un solo round-trip.
 * Ogni nodo: ['id'=>, 'nome'=>, 
 *      'children'=>[id =>, nome=>, children => [] ]] - quando si arriva ad array vuoto, siamo a una foglia (e si ritorna)
 * @return list<array<string,mixed>>
 */
function fetch_category_tree(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, nome, is_a FROM categoria ORDER BY nome ASC'); //tutte le categorie
    $all  = $stmt->fetchAll();

    $byParent = []; //array di array associativi in cui indice (=id_genitore) => [ [dati_child1] ] 
    //es [0root => [ [00c], [01c], [02c] ], 1c=> [ [10c], [11c], [12c] ] ] - ogni child è un array associativo con dati categoria fetched da db
    foreach ($all as $cat) {
        $parent = $cat['is_a'] !== null ? (int) $cat['is_a'] : 0; //segna genitore di questa categoria - 0 se è base (IMPORTANTE)
        $byParent[$parent][] = $cat; //push di questa categoria, nel gruppo relativo a un parent
    }

    //funzione ricorsiva che si ferma quando raggiunge categoria senza figli
    $build = function (int $parentId) use (&$build, $byParent): array { //Costruisce albero dal nodo con id = $parentId
        $nodes = []; //figli del nodo corrente
        foreach ($byParent[$parentId] ?? [] as $cat) { //per ogni figlio di una categoria (se esistono, altrimenti array vuoto esce dal foreach)
            $nodes[] = [
                'id'       => (int) $cat['id'],
                'nome'     => $cat['nome'],
                'children' => $build((int) $cat['id']), //ricorsione 
            ];
        }
        return $nodes;
    };

    return $build(0); // categorie con is_a IS NULL sono raggruppate sotto la chiave 0
}
/**
 * Genera le <optgroup>/<option> per la <select> di scelta categoria (venditore_vendi.php),
 * a partire dall'albero prodotto da fetch_category_tree().
 * Ogni categoria radice diventa una <optgroup> (il browser la mostra in grassetto e non selezionabile)
 * con dentro: un'opzione per la radice stessa (un prodotto può appartenere anche solo alla radice)
 * e a seguire le sottocategorie, rientrate in base alla profondità.
 *
 * @param list<array<string,mixed>> $tree     albero radici, come restituito da fetch_category_tree()
 * @param int                       $selectedId id categoria attualmente selezionata (0 = nessuna)
 */
function render_category_options(array $tree, int $selectedId = 0): string
{
    $html = '';

    //funzione ricorsiva interna: stampa le option di un nodo e dei suoi figli, indentando in base a $depth
    $renderNode = function (array $node, int $depth) use (&$renderNode, $selectedId): string {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . ($depth > 0 ? '– ' : ''); //rientro visivo per le sottocategorie
        $out = '<option value="' . (int) $node['id'] . '"'
             . ($selectedId === (int) $node['id'] ? ' selected' : '')
             . '>' . $indent . htmlspecialchars((string) $node['nome']) . '</option>';

        foreach ($node['children'] as $child) { //sottocategorie, sottosottocategorie ecc, sempre più rientrate
            $out .= $renderNode($child, $depth + 1);
        }
        return $out;
    };

    foreach ($tree as $root) { //ogni categoria radice = un gruppo in grassetto
        $html .= '<optgroup label="' . htmlspecialchars((string) $root['nome']) . '">';
        $html .= $renderNode($root, 0); //la radice stessa è selezionabile come prima option del gruppo
        $html .= '</optgroup>';
    }

    return $html;
}

/**
 * Prende i figli diretti delle categorie selezionate e le restituisce insieme ai genitori in un array di id. 
 * 
 * @param list<int> $selectedIds è array di interi (id). si riferisce agli id delle categorie selezionate
 * @return list<int>
 */
function expand_category_ids_with_children(PDO $pdo, array $selectedIds): array
{
    if ($selectedIds === []) { //non seleziona niente
        return [];
    }

    $allIds   = array_map('intval', $selectedIds); //categorie trovate fino ad ora (espanso a ogni iterazione)
    $frontier = $allIds; //categorie (id) di cui cercare i figli diretti in questo giro (rimpicciolito a ogni iterazione)

    //espande un livello alla volta finché non trova più discendenti nuovi:
    while ($frontier !== []) {
        $placeholders = []; //per prepared stmt, [':id0', ':id1' ecc] - suffisso basato su posizione
        $params = []; //array associativo [':id0' => 6]
        

        foreach ($frontier as $i => $id) {
            $key = ':id' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $in = implode(', ', $placeholders); //stringa di placeholder da inserire nel prepared stmt

        //query che cerca figli di questa categoria
        $stmt = $pdo->prepare(
            "SELECT id FROM categoria WHERE is_a IN ($in)"
        );
        $stmt->execute($params); 

        $childrenIds = array_map('intval', //casting a intero (dati presi da db sono stringhe)
            array_column($stmt->fetchAll(), 'id') //estrae il valore associato a ogni id - uguale a prendere row['id'] dopo
        ); 

        $newIds = array_values(array_diff($childrenIds, $allIds)); //solo i discendenti non ancora trovati nei giri precedenti

        $allIds   = array_merge($allIds, $newIds);
        $frontier = $newIds; //al prossimo giro cerca solo i figli di questi nuovi (gli altri sono già stati espansi)
    }

    return array_values( //ricostruisce indici da 0 (per indici continui)
            array_unique($allIds)); //per sicurezza, anche se non dovrebbero più esserci duplicati qui
}

/**
 * Prodotti filtrati per un insieme di categorie + ricerca testuale/venditore
 * opzionali, con ordinamento configurabile. usata in results.php.
 *
 * @param list<int> $categoryIds  id categorie già espanse con i figli (vuoto = nessun filtro categoria)
 * @param string    $sortBy       'data' | 'prezzo'
 * @param string    $sortDir      'asc' | 'desc'
 * @return list<array<string,mixed>>
 */
function fetch_filtered_products(
    PDO $pdo,
    array $categoryIds = [],
    string $query = '',
    string $vendor = '',
    string $sortBy = 'data',
    string $sortDir = 'desc'
): array {
    $sql    = product_card_sql_base(); //dati prodotto
    $params = [];

    if ($categoryIds !== []) { //filtro per categoria - stesso procedimento di sopra
        $placeholders = []; 
        foreach ($categoryIds as $i => $id) {
            $key = ':cat' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $sql .= ' AND p.id_categoria IN (' . implode(', ', $placeholders) . ')';
    }

    if ($vendor !== '') { //ricerca per negoziante
        $sql .= ' AND p.user_negoziante = :vendor';
        $params[':vendor'] = $vendor;
    } elseif ($query !== '') { //ricerca prodotto 
        $sql .= ' AND LOWER(p.nome) LIKE :q';
        $params[':q'] = '%' . strtolower($query) . '%';
    }

    // parte ordinamento della query
    $columns = [
        'data'   => 'p.data_pubblicazione',
        'prezzo' => 'p.prezzo',
    ];
    $directions = ['asc' => 'ASC', 'desc' => 'DESC']; //TODO: non necessario - va bene anche in minuscolo

    $col = $columns[$sortBy] ?? $columns['data']; //se non viene specificato nei param, ordina per data
    $dir = $directions[$sortDir] ?? $directions['desc']; //se non viene specificato nei param, ordinamento discendente

    $sql .= " ORDER BY $col $dir";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Conta gli ordini distinti che contengono almeno un prodotto del negoziante.
 * Analogo a count_orders() ma per il tipo 'negoziante'.
 */
function count_vendor_orders_total(PDO $pdo, string $vendor): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT o.id)
         FROM ordine o
         INNER JOIN ordine_prodotto op ON op.id_ordine = o.id
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE p.user_negoziante = :v'
    );
    $stmt->execute([':v' => $vendor]);
    return (int) $stmt->fetchColumn();
}

/**
 * Notifica tutti i negozianti coinvolti in un ordine che il cliente lo ha annullato.
 * Usa il tipo enum 'acquisto' (notifica diretta al venditore su un suo ordine).
 */
function notify_vendor_cancellation(PDO $pdo, int $orderId, string $clientUsername): void
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.user_negoziante
         FROM ordine_prodotto op
         INNER JOIN prodotto p ON p.id = op.id_prodotto
         WHERE op.id_ordine = :oid AND p.user_negoziante IS NOT NULL'
    );
    $stmt->execute([':oid' => $orderId]);
    $vendors = $stmt->fetchAll();

    $ins = $pdo->prepare(
        'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
         VALUES (:tipo, :testo, NOW(), :user)'
    );

    foreach ($vendors as $row) {
        $testo = 'Ordine #' . $orderId . ': ' . $clientUsername . ' ha annullato l\'ordine.';
        $ins->execute([
            ':tipo'  => 'acquisto',
            ':testo' => substr($testo, 0, 255),
            ':user'  => $row['user_negoziante'],
        ]);
    }
}

/* ================================================================
   NOTIFICHE CARRELLO — OOS e MAGAZZINO
   ================================================================ */

/**
 * prodotto eliminato dal venditore. notifica + rimozione dal carrello. 
 * Chiamata dopo delete_product_soft: trova tutti i clienti che hanno
 * quel prodotto nel carrello, li notifica con 'oos' e rimuove il prodotto
 * dal loro carrello.
 */
function notify_oos_and_clean_carts(PDO $pdo, int $productId, string $productName): void
{
    // trova clienti che hanno questo prodotto nel carrello
    $stmt = $pdo->prepare(
        'SELECT user_cliente FROM carrello WHERE id_prodotto = :pid'
    );
    $stmt->execute([':pid' => $productId]);
    $clienti = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($clienti)) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
         VALUES (:tipo, :testo, NOW(), :u)'
    );

    //TODO: verrebbe fatto comunque in automatico
    $del = $pdo->prepare(
        'DELETE FROM carrello WHERE id_prodotto = :pid AND user_cliente = :u'
    );

    foreach ($clienti as $username) {
        $testo = 'Il prodotto "' . $productName . '" non è più disponibile ed è stato rimosso dal tuo carrello.';
        $ins->execute([
            ':tipo'  => 'oos',
            ':testo' => substr($testo, 0, 255),
            ':u'     => $username,
        ]);
        $del->execute([':pid' => $productId, ':u' => $username]);
    }
}

/**
 * Chiamata dopo un ordine completato: per ogni prodotto dell'ordine,
 * controlla i carrelli altrui. Se la quantità nel carrello supera
 * la disponibilità attuale, la riduce (o rimuove se disponibilità = 0)
 * e manda notifica 'magazzino'.
 *
 * Va chiamata DOPO che il trigger UpdateProdDisponibilita ha già
 * decrementato prodotto.disponibilita.
 *
 * @param list<array<string,mixed>> $orderedItems  righe di ordine_prodotto appena inserite
 * @param string $excludeUser  il cliente che ha appena ordinato (il suo carrello è già vuoto)
 */
function notify_magazzino_and_fix_carts(PDO $pdo, array $orderedItems, string $excludeUser): void
{
    foreach ($orderedItems as $item) {
        $productId = (int) $item['id_prodotto'];

        // legge la disponibilità aggiornata (dopo il trigger)
        $stmtDisp = $pdo->prepare(
            'SELECT disponibilita, nome FROM prodotto WHERE id = :pid LIMIT 1'
        );
        $stmtDisp->execute([':pid' => $productId]);
        $prod = $stmtDisp->fetch();

        if (!$prod || $prod['disponibilita'] === null) {
            continue; // prodotto soft-deleted o non trovato
        }

        $disponibilita = (int) $prod['disponibilita'];
        $nome          = (string) $prod['nome'];

        // trova altri clienti che hanno questo prodotto nel carrello
        // con quantità > disponibilità attuale
        $stmtCart = $pdo->prepare(
            'SELECT user_cliente, quantita
             FROM carrello
             WHERE id_prodotto = :pid
               AND user_cliente != :u
               AND quantita > :disp'
        );
        $stmtCart->execute([
            ':pid'  => $productId,
            ':u'    => $excludeUser,
            ':disp' => $disponibilita,
        ]);
        $righe = $stmtCart->fetchAll();

        if (empty($righe)) {
            continue;
        }

        $ins = $pdo->prepare(
            'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
             VALUES (:tipo, :testo, NOW(), :u)'
        );
        $upd = $pdo->prepare(
            'UPDATE carrello SET quantita = :q
             WHERE id_prodotto = :pid AND user_cliente = :u'
        );
        $delCart = $pdo->prepare(
            'DELETE FROM carrello WHERE id_prodotto = :pid AND user_cliente = :u'
        );

        foreach ($righe as $riga) {
            $cliente = $riga['user_cliente'];

            if ($disponibilita === 0) {
                // disponibilità esaurita: rimuovi dal carrello e manda notifica
                $delCart->execute([':pid' => $productId, ':u' => $cliente]);
                $testo = 'Il prodotto "' . $nome . '" è esaurito ed è stato rimosso dal tuo carrello.';
                $ins->execute([':tipo' => 'magazzino', ':testo' => substr($testo, 0, 255), ':u' => $cliente]);
            } else {
                // disponibilità ridotta: aggiorna quantità nel carrello e manda notifica
                $upd->execute([':q' => $disponibilita, ':pid' => $productId, ':u' => $cliente]);
                $testo = 'Sono diminuite le nostre scorte per il prodotto "' . $nome . '". La quantità di "' . $nome . '" nel tuo carrello è stata ridotta a '
                       . $disponibilita . ' (disponibilità massima attuale).';
                $ins->execute([':tipo' => 'magazzino', ':testo' => substr($testo, 0, 255), ':u' => $cliente]);
            }
        }
    }
}
/**
 * Notifica il venditore che è stata fatta una nuova recensione su un suo prodotto.
 * Il testo include "prodotto #N" per consentire a notif_build_link() di
 * costruire il link a prodotto.php?id=N.
 */
function notify_vendor_review(PDO $pdo, int $productId, string $productName,
                              string $clientUsername, int $rating): void
{
    // recupera il negoziante del prodotto
    $stmt = $pdo->prepare(
        'SELECT user_negoziante FROM prodotto WHERE id = :id AND user_negoziante IS NOT NULL LIMIT 1'
    );
    $stmt->execute([':id' => $productId]);
    $vendor = $stmt->fetchColumn();

    if (!$vendor) {
        return; // prodotto rimosso o negoziante null: niente notifica
    }

    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $testo = $clientUsername . ' ha recensito prodotto #' . $productId
           . ' "' . $productName . '" con ' . $stars . '.';

    $ins = $pdo->prepare(
        'INSERT INTO notifica (tipo, testo, data_notifica, user_cliente)
         VALUES (:tipo, :testo, NOW(), :u)'
    );
    $ins->execute([
        ':tipo'  => 'recensione',
        ':testo' => substr($testo, 0, 255),
        ':u'     => $vendor,
    ]);
}
