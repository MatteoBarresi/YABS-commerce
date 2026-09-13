<?php
/**
 * home_catalog_handler.php
 *
 * gestisce AJAX per infinite scroll delle righe categoria di home.php
 * ripete logica di home.php per x categorie (in base al limit)
 * GET:
 *   offset  = quante categorie sono già state renderizzate (int)
 *   limit   = quante categorie richiedere in questa "pagina" (int)
 *   cat[]   = id categorie selezionate dal filtro (opzionale, NON espanse -
 *             sono gli stessi id che home.php legge da $_GET['cat'])
 *
 * Risponde con HTML puro (righe categoria, stesso markup di product_row.php),
 * da fare "append" al container #catalog-rows-container - NON json, come
 * notifiche.php?p=N. Se non ci sono altre categorie, la risposta è vuota:
 * il JS interpreta questo come segnale per smettere di fare richieste.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

// stesso controllo di home.php - niente accesso senza sessione
if (empty($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit  = max(1, min(20, (int) ($_GET['limit'] ?? 3))); // cap per evitare richieste con limit enorme

// stesso parsing di home.php per il filtro categorie (?cat[]=1&cat[]=3) 
//cast int - presi una sola volta (poi rigenera indici da 0)
$selectedCategoryIds = array_values(array_unique(array_map(
    'intval',
    array_filter((array) ($_GET['cat'] ?? []), fn($v) => (int) $v > 0)
)));

try {
    $pdo = getConnection();

    // id categorie selezionate + figli 
    $expandedCategoryIds = expand_category_ids_with_children($pdo, $selectedCategoryIds);

    //select altre categorie presenti nel db - in base a offset e limit
    $categories = fetch_categories_with_products($pdo, $expandedCategoryIds, $limit, $offset);

    foreach ($categories as $cat) {
        $products = fetch_products_by_category($pdo, (int) $cat['id']); //prodotti di quella categoria
        if ($products === []) {   //controllo in più (ma c'è join)
            continue;
        }
        $rowTitle = $cat['nome'];
        require APP_ROOT . '/includes/product_row.php'; //stampa come in home.php
    }
} catch (Throwable $e) {
    // risposta vuota in caso di errore: il js smette di richiedere altre pagine
    http_response_code(500); //generico
    exit;
}
