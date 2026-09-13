<?php
/* 
pagina a cui si arriva dalla search bar o da link a venditore nei dettagli di un prodotto

*/
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

$pageTitle = 'Risultati ricerca';
$withNavbar = true;
$extraJs = ['assets/js/cat_filter.js'];

//anche search.js li costruisce
//NELLA GET VIENE PASSATO  SOLO UNO DEI DUE
$q = sanitize_string($_GET['q'] ?? ''); //stringa search bar (in navbar)
$vendor = sanitize_string($_GET['vendor'] ?? ''); //link a venditore (da prodotto.php)
$products = [];
$error = null;

// filtro categorie: id selezionati dall'utente via pannello (?cat[]=1&cat[]=3) - stesso usato in home.php
//cast int - presi una sola volta
$selectedCategoryIds = array_values(array_unique(array_map(
    'intval',
    array_filter((array) ($_GET['cat'] ?? []), fn($v) => (int) $v > 0)
)));

$categoryTree = [];

// ordinamento (solo qui, non in home.php): per data o prezzo, asc o desc
$sortBy  = sanitize_string($_GET['sort'] ?? 'data');
$sortDir = sanitize_string($_GET['dir'] ?? 'desc');

//controlli in caso di input manuale dell'utente
if (!in_array($sortBy, ['data', 'prezzo'], true)) { 
    $sortBy = 'data';
}
if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'desc';
}

// la pagina mostra risultati se c'è: una ricerca testuale, un venditore, o almeno una categoria selezionata dal filtro
$hasActiveFilter = ($q !== '' || $vendor !== '' || $selectedCategoryIds !== []);

try {
    $pdo = getConnection();
    $categoryTree = fetch_category_tree($pdo); //array con albero categorie

    if ($hasActiveFilter) {
        //se l'utente seleziona almeno una categoria, mettiamo i figli diretti in un array
        $expandedCategoryIds = expand_category_ids_with_children($pdo, $selectedCategoryIds);


        /*posso passare sia $q che $vendor a fetch_filtered_products (senza condizioni) e viene gestito in automatico (prende vendor perché la condizione viene prima).
            è più una sicurezza per input da barra degli indirizzi: voglio stesso comportamento che c'è normalmente con search bar 
            (se vengono inseriti entrambi, anche qui prende solo vendor perché la condizione viene prima) 
        */
        if ($vendor !== '') { //viene cercato un venditore

            $products = fetch_filtered_products(
                $pdo,
                $expandedCategoryIds,
                '',
                $vendor,
                $sortBy,
                $sortDir
            );
            $pageTitle = 'Prodotti di @' . $vendor;

        } else { // ricerca testuale e/o filtro categorie, con ordinamento configurabile
            $products = fetch_filtered_products(
                $pdo,
                $expandedCategoryIds,
                $q,
                '',
                $sortBy,
                $sortDir
            );
        }
    }
} catch (Throwable $e) {
    error_log('Results: ' . $e->getMessage());
    $error = 'Errore durante la ricerca.';
}

require APP_ROOT . '/includes/head.php';
?>



<main class="main-catalog">
    <header class="results-header">

        <?php if ($vendor !== ''): ?>
            <h1>Negoziante: <?= htmlspecialchars($vendor) ?></h1>
        <?php elseif ($q !== ''): ?>
            <h1>Risultati per «<?= htmlspecialchars($q) ?>»</h1>
        
        <?php elseif ($selectedCategoryIds !== []): ?>
            <h1>Prodotti filtrati per categoria</h1>
        
        <?php else: //caso in cui si arriva scrivendo nella barra degli indirizzi ?>
            <h1>Cerca nel catalogo</h1>
            <p class="subtitle">Usa la barra di ricerca in alto, oppure filtra per categoria.</p>
        <?php endif; ?>
    </header>
    <div class="container-fluid px-3 px-lg-4">
            <!-- toolbar: filtro categorie + ordinamento affiancati-->
        <div class="catalog-toolbar mb-3">
            <?php require APP_ROOT . '/includes/category_filter.php'; ?>

            <?php if (/*$vendor === ''*/true): // applico anche alla vista "prodotti di un venditore" ?>
                <div class="sort-select-wrap">
                    <label for="sort-select" class="small text-muted mb-0">Ordina per:</label>
                    <select id="sort-select" class="sort-select">
                        <option value="data-desc"   <?= ($sortBy === 'data'   && $sortDir === 'desc') ? 'selected' : '' ?>>Più recenti</option>
                        <option value="data-asc"    <?= ($sortBy === 'data'   && $sortDir === 'asc')  ? 'selected' : '' ?>>Meno recenti</option>
                        <option value="prezzo-asc"  <?= ($sortBy === 'prezzo' && $sortDir === 'asc')  ? 'selected' : '' ?>>Prezzo: dal più basso</option>
                        <option value="prezzo-desc" <?= ($sortBy === 'prezzo' && $sortDir === 'desc') ? 'selected' : '' ?>>Prezzo: dal più alto</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php //vari controlli - errore, get vuota, nessun risultato
            if ($error): //errore nella connessione - parte query ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!$hasActiveFilter): ?>
            <p class="catalog-hint">Digita nella searchbar per trovare prodotti o negozianti, oppure filtra per categoria.</p>
        <?php elseif ($products === []): ?>
            <p>Nessun risultato trovato.</p>
        
        <?php else: //lista risultati - TODO: --- copiato da product_row (usato in home.php) ?>
            <div class="row g-3">
                <?php foreach ($products as $product): ?>
                    <?php
                    $cardColClass = 'col-md-4 col-sm-6';
                    require APP_ROOT . '/includes/product_card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
