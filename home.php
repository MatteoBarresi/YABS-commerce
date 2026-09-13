<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login(); //se non è settata variabile di sessione, rimanda a pagina di login

$pageTitle = 'Home';
$withNavbar = true;

$extraJs = ['assets/js/cat_filter.js', 'assets/js/home.js'];
$success = flash_success();
$error = flash_error();
$tipo = $_SESSION['tipo_utente'] ?? '';

//array con prodotto
$rows = []; //nested array ([title=> nomeSezione, products => fetchDB]) - solo "Suggeriti"/"Novità", sempre renderizzate subito
$catRows = []; //righe per categoria (catLimit alla volta)

$dbError = null; //gestisco nel catch... legacy TODO: eliminare

$catLimit  = 3;   // categorie (+ prodotti) renderizzate alla volta (la prima volta e a ogni richiesta ajax)
$catOffset = 0;   // categorie già mostrate - passato al js per la prossima richiesta
$catTotal  = 0;   // totale categorie disponibili (con questo eventuale filtro) - per sapere quando fermarsi


// filtro categorie: id selezionati dall'utente via pannello (?cat[]=1&cat[]=3)
//cast int - presi una sola volta
$selectedCategoryIds = array_values(array_unique(array_map(
    'intval',
    array_filter((array) ($_GET['cat'] ?? []), fn($v) => (int) $v > 0)
)));
$categoryTree = [];


try {
    $pdo = getConnection();
    $categoryTree = fetch_category_tree($pdo); //[ [id=> '', nome=> '', children=> [id, nome, children] ], [same], [same] ]

    //se l'utente seleziona almeno una categoria, mettiamo i figli diretti in un array
    $expandedCategoryIds = expand_category_ids_with_children($pdo, $selectedCategoryIds);

    if ($expandedCategoryIds === []) { // nessun filtro attivo: comportamento base (suggeriti, novità, tutte le categorie)
        if ($tipo === 'cliente') {
        
            //finita register, porta a home con cookie categorie settati
            $preferred = get_preferred_category_ids(); //array numerico (id categorie preferite)

            //prodotti di categorie preferite
            $suggested = fetch_suggested_products($pdo, $preferred); //restituisce tutti prodotti delle categorie preferite
            if ($suggested !== []) {
                $rows[] = [
                    'title' => 'Suggeriti per te', 
                    'products' => $suggested
                    ];
            }

            //ultimi prodotti aggiunti
            $newArrivals = fetch_new_arrivals($pdo);
            if ($newArrivals !== []) {
                $rows[] = ['title' => 'Novità', 'products' => $newArrivals];
            }
        }
    }
        
        //altri push dentro rows 
        // se $expandedCategoryIds è vuoto, nessun filtro attivo - mostra //righe per categoria - SOLO le prime $catLimit (il resto arriva via ajax quando si scorre)
        // altrimenti, filtro attivo: mostra solo le righe delle categorie selezionate (espanse con i figli)
        $catList = fetch_categories_with_products($pdo, $expandedCategoryIds, $catLimit, 0);
        foreach ($catList as $cat) {            //per ogni categoria di questa "pagina", già filtrata lato SQL
            $products = fetch_products_by_category($pdo, (int) $cat['id']); //seleziona prodotti della categoria e fa push nei risultati
            if ($products !== []) {     //ci sono prodotti per questa categoria
                $catRows[] = [
                    'title' => $cat['nome'],
                    'products' => $products,
                ];
            }
                    
        }
        $catOffset = count($catList);   //categorie già mostrate
        $catTotal  = count_categories_with_products($pdo, $expandedCategoryIds); //totale categorie per questo filtro (se array vuoto, no filtro)
    
} catch (PDOException $e) {
    redirect('/db_error.php');
}

require APP_ROOT . '/includes/head.php';
?>

<main class="main-catalog">
    
    <!-- avvisi di errore-->
    <div class="container-fluid px-3 px-lg-4 pt-3">
        <?php if ($success): ?>
            <div class="alert alert-success mb-3"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($dbError): ?>
            <div class="alert alert-danger mb-3"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
     <!-- toolbar: filtro categorie -->
    <div class="container-fluid px-3 px-lg-4">
        <div class="catalog-toolbar">
            <?php require APP_ROOT . '/includes/category_filter.php'; ?>
        </div>
    </div>

     <?php $noProducts = ($rows === [] && $catRows === []); //niente da mostrare, né righe statiche né righe categoria ?>

    <!-- nessun errore ma nessun prodotto (c'è un filtro attivo) -->
    <?php if ($noProducts && $dbError === null && $selectedCategoryIds !== []): ?>
        
        <div class="container py-5">
            <div class="card border-0 catalog-empty mx-auto">
                <div class="card-body text-center py-5">
                    <h1 class="h5 mb-2">Nessun prodotto per questo filtro</h1>
                    <p class="text-muted mb-0">Prova ad azzerare o cambiare le categorie selezionate.</p>
                </div>
            </div>
        </div>

    <?php elseif ($noProducts && $dbError === null): //no errore, no prodotto, no filtro ?>
        <div class="container py-5">
            <div class="card border-0 catalog-empty mx-auto">
                <div class="card-body text-center py-5">
                    <h1 class="h3 mb-2">Benvenuto nello Shop</h1>
                    <p class="text-muted mb-4">Il catalogo è ancora vuoto.</p>
                    
                    <?php if ($tipo === 'negoziante'): ?>
                        <a href="<?= BASE_URL ?>/venditore_vendi.php" class="btn btn-primary">Inserisci il tuo primo prodotto</a>
                    <?php else: ?>
                        <p class="mb-0">Non è stato aggiunto nessun articolo.</p>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

    <?php else: //ci sono prodotti?>
        <?php foreach ($rows as $row): //"Suggeriti per te" / "Novità" - sempre tutte, no paginazione?>
            <?php
                $rowTitle = $row['title']; //motivo accumulo
                $products = $row['products']; //db fetch con nomi della table 
                require APP_ROOT . '/includes/product_row.php';
            ?>
        <?php endforeach; ?>

        <?php if ($catRows !== []): ?>
            <!-- righe per categoria - impaginate: il container tiene i dati per la prossima richiesta ajax -->
            <div id="catalog-rows-container"
                data-offset="<?= $catOffset //da dove ripartire ?>"
                data-limit="<?= $catLimit   //quante prenderne ?>"
                data-cat="<?= htmlspecialchars(implode(',', $selectedCategoryIds)) //da array a stringa ?>">
                <?php foreach ($catRows as $row): ?>
                    <?php
                        $rowTitle = $row['title'];
                        $products = $row['products'];
                        require APP_ROOT . '/includes/product_row.php';
                    ?>
                <?php endforeach; ?>
            </div>

            <?php if ($catOffset < $catTotal): //ci sono ancora categorie da caricare - il js osserva questo div, 
                //renderizzato solo se c'è altro da caricare?>
                <div id="catalog-sentinel" aria-hidden="true"></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
