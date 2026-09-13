<?php
/* 
si arriva da navbar per negoziante (anche da home) 

*/


require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

if (($_SESSION['tipo_utente'] ?? '') !== 'negoziante') {
    redirect('/home.php');
}

$pageTitle  = 'I miei prodotti';
$withNavbar = true;
$extraJs    = ['assets/js/venditore.js', 'assets/js/img_handler.js'];

$vendor  = $_SESSION['username'];
$error   = flash_error();
$success = flash_success();

//paginazione. analogo a ordini
$page   = max(1, (int) ($_GET['p'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$prodotti   = []; //dati articoli singoli
$totale     = 0;  //quanti articoli tipo
$categoryTree = []; //albero categorie (radici + sottocategorie annidate)

// se arriva ?edit=N precompila il form
$editId      = (int) ($_GET['edit'] ?? 0);  //id del prodotto da modificare
$editProduct = null;

//richiesta dati al DB
try {
    $pdo       = getConnection();
    $prodotti  = fetch_vendor_products($pdo, $vendor, $limit, $offset); //prodotti in vendita di questo venditore (paginati - numero di articoli singoli)
    $prodottiPagina = count($prodotti);
    $totale    = count_vendor_products($pdo, $vendor); //TUTTI, non solo quelli in questa pagina
    $categoryTree = fetch_category_tree($pdo);          //albero categorie: radici con sottocategorie annidate

    //si ritorna su questa pagina con id prodotto da modificare nella GET 
    if ($editId > 0) {
        // carica solo se appartiene al negoziante - ulteriore controllo
        $stmtE = $pdo->prepare(
            'SELECT id, nome, prezzo, descrizione, disponibilita, id_categoria
             FROM prodotto WHERE id = :id AND user_negoziante = :v LIMIT 1'
        );
        $stmtE->execute([':id' => $editId, ':v' => $vendor]);
        $editProduct = $stmtE->fetch() ?: null;
    }
} catch (PDOException $e) {
    redirect('/db_error.php');
}

$totPagine = $totale > 0 ? (int) ceil($totale / $limit) : 1; //limit articoli in una pagina - quante volte ci sta il totale nel limite

require APP_ROOT . '/includes/head.php';
?>

<main class="main-catalog">
<div class="container py-4" style="max-width:860px;">

    <h1 class="h3 mb-4" style="color:var(--indigo);">🏪 I miei prodotti</h1>

    <!-- display messaggi errore -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ===== FORM INSERIMENTO / MODIFICA ===== -->
    <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                
                <h2 class="h6 mb-0" style="color:var(--indigo);">
                    <?= $editProduct ? '✏️ Modifica prodotto' : '➕ Nuovo prodotto' ?>
                </h2>

                <?php if (!$editProduct): //il bottone mostra form di inserimento.  ?>
                    <button id="btn-form-toggle"
                            style="background:none; border:1px solid var(--lilac); border-radius:8px;
                                   padding:0.35rem 1rem; font-size:0.85rem; cursor:pointer; color:var(--indigo);">
                        Espandi ▾
                    </button>
                <?php endif; ?>
            </div>

            <form id="form-prodotto" action="<?= BASE_URL ?>/venditore_process.php" method="post"
                  enctype="multipart/form-data" novalidate 
                  <?= $editProduct ? '' : 'style="display:none;"' //se non c'è un prodotto da editare, nascondi form ?>>

                <input type="hidden" name="action"
                       value="<?= $editProduct ? 'update_product' : 'insert_product' //process gestisce questi due casi ?>">
                
                <?php if ($editProduct): //prodotto da editare - passa id alla pagina di elaborazione ?>
                    <input type="hidden" name="id_prodotto" value="<?= $editId ?>">
                <?php endif; ?>

                <!-- dati prodotto : nome, prezzo, disponibilità TODO: check minmax-->
                <div class="d-flex gap-3 flex-wrap">
                    <div class="form-group flex-grow-1" style="min-width:200px;">
                        <label for="nome">Nome prodotto *</label>
                        <input type="text" id="nome" name="nome" required maxlength="50"
                               value="<?= htmlspecialchars((string) ($editProduct['nome'] ?? '')) ?>">
                    </div>
                    <div class="form-group" style="width:130px;">
                        <label for="prezzo">Prezzo (€) *</label>
                        <input type="number" id="prezzo" name="prezzo" required
                               min="1" max="9999.99" step="any"
                               value="<?= htmlspecialchars((string) ($editProduct['prezzo'] ?? '')) ?>">
                    </div>
                    <div class="form-group" style="width:130px;">
                        <label for="disponibilita">Disponibilità *</label>
                        <input type="number" id="disponibilita" name="disponibilita" required
                               min="0" max="9999999"
                               value="<?= htmlspecialchars((string) ($editProduct['disponibilita'] ?? '')) ?>">
                    </div>
                </div>

                <!-- testo descrizione -->
                <div class="form-group">
                    <label for="descrizione">Descrizione</label>
                    <textarea id="descrizione" name="descrizione" 
                            maxlength="200"
                            rows="3"
                            style="width:100%; border:1px solid var(--lilac); border-radius:8px;
                                     padding:0.6rem 0.9rem; font-size:0.95rem; resize:vertical;"
                            placeholder="inserisci descrizione"
                    ><?= htmlspecialchars((string) ($editProduct['descrizione'] ?? '')) ?></textarea>
                </div>

                <!-- scelta categoria -->
                <div class="form-group">
                    <label for="id_categoria">Categoria</label>
                    <select id="id_categoria" name="id_categoria"
                            style="width:100%; border:1px solid var(--lilac); border-radius:8px;
                                   padding:0.55rem 0.9rem; font-size:0.95rem; background:var(--white);">
                        
                        <option value="">— Nessuna categoria —</option>
                        <?php 
                            //optgroup con categoria root + derivate
                            echo render_category_options($categoryTree, (int) ($editProduct['id_categoria'] ?? 0));
                        ?>
                    </select>
                </div>

                <!-- inserimento immagini - passato come array di path-->
                <?php if (!$editProduct): ?>
                    <div class="form-group">
                        <label for="immagini">Immagini (max 5, jpg/png/webp)</label>
                        <input type="file" id="immagini" name="immagini[]"
                            accept="image/jpeg,image/png" multiple
                            style="border:1px solid var(--lilac); border-radius:8px;
                                    padding:0.5rem 0.9rem; width:100%; font-size:0.9rem;">
                        <div id="img-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                <?php endif; ?>

                <!-- display errori -->
                <p id="form-prodotto-error" class="small mb-2"
                   style="color:var(--error); display:none;"></p>

                <!-- bottone di conferma -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"
                            style="width:auto; padding:0.55rem 1.5rem;">
                        <?= $editProduct ? 'Salva modifiche' : 'Pubblica prodotto' ?>
                    </button>

                    <?php if ($editProduct): //stiamo modificando un prodotto - riporta a questa pagina, annulla operazione ?>
                        <a href="<?= BASE_URL ?>/venditore_vendi.php"
                           class="btn btn-secondary"
                           style="width:auto; padding:0.55rem 1.25rem;">Annulla</a>
                    <?php else: //stiamo inserendo - gestito in js -- svuota campi, annulla operazione?>
                        <button type="button" id="btn-form-cancel" class="btn btn-secondary"
                                style="width:auto; padding:0.55rem 1.25rem;">Annulla</button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>

    <!-- ===== LISTA PRODOTTI ===== -->
    <?php if ($totale === 0): //no prodotti. ?>
        <?php include APP_ROOT . "/includes/no-prodotti.php"?>
    
    <?php else: //ci sono prodotti ?>

        <!-- quanti prodotti ha caricato questo venditore -->
        <p class="small text-muted mb-2">
            <?= $totale ?> prodott<?= $totale !== 1 ? 'i' : 'o' ?> in vendita
        </p>

        <?php if ($prodottiPagina === 0): //numero prodotti per questa pagina - es inserito ?p=100 ?>
            
            <div id="prodotti-empty" class="card border-0 text-center py-5"
                style="background:var(--white); border:1px solid var(--lilac)!important; border-radius:12px;">
                <div class="card-body">
                    <p style="font-size:3rem;"></p>
                    <h2 class="h5 mb-1">Fine Prodotti</h2>
                    <p class="text-muted mb-0">Non hai così tanti prodotti in vendita</p>
                    
                    <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary" style="width:auto; padding: 0.6rem 1.5rem;">
                        Torna alla home
                    </a>
                </div>
            </div>
        <?php else: //lista vera e propria ?>

            <!-- data-* letti da venditore.js per la paginazione AJAX (analogo a notifiche.php) -->
            <div id="products-list"
                data-page="<?= $page ?>"
                data-limit="<?= $limit ?>"
                data-total="<?= $totale ?>"
            >
                <?php foreach ($prodotti as $prod):
                    $pid = (int) $prod['id']; ?>

                    <!-- contiene dati del prodotto -->
                    <div class="prod-row d-flex align-items-center gap-3 mb-2 p-3"
                        style="background:var(--white); border:1px solid var(--lilac);
                                border-radius:10px; flex-wrap:wrap;">

                        <!-- miniatura -->
                        <div style="width:60px; height:60px; border-radius:8px; overflow:hidden; flex-shrink:0;
                                    background:var(--lilac); display:flex; align-items:center; justify-content:center;">
                            
                            <?php if (!empty($prod['img'])): //c'è un'immagine. ?>
                                <img src="<?= BASE_URL . '/' . ltrim((string) $prod['img'], '/') ?>"
                                    alt="" style="width:100%; height:100%; object-fit:cover;">
                                <span style="display:none; font-size:1.5rem;">📦</span>
                            
                            <?php else: //placeholder. ?>
                                <span style="font-size:1.5rem;">📦</span>
                            <?php endif; ?>
                        </div>

                        <!-- info -->
                        <div class="flex-grow-1">
                            
                            <!-- link alla pagina dettagli prodotto -->
                            <a href="<?= BASE_URL ?>/prodotto.php?id=<?= $pid ?>"
                            class="fw-semibold text-decoration-none" style="color:var(--indigo);">
                                <?= htmlspecialchars((string) $prod['nome']) ?>
                            </a>

                            <!-- prezzo, disponibilità, views, categoria-->
                            <p class="small text-muted mb-0">
                                <?= format_price((string) $prod['prezzo']) 
                                //nbsp per mantenere sulla stessa riga?>
                                &nbsp;·&nbsp; Disponibili: <?= (int) $prod['disponibilita'] ?>
                                &nbsp;·&nbsp; Visualizzazioni: <?= (int) $prod['visualizzazioni'] ?>
                                <?php if ($prod['categoria']): //non dovrebbe mai essere null quindi controllo perché???? ?>
                                    &nbsp;·&nbsp; <?= htmlspecialchars((string) $prod['categoria']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- azioni: modifica o cancella prodotto : per modifica, ritorna su questa pagina con GET 
                        per cancellare, gestito con un evento js + ajax -->
                        <div class="d-flex gap-2 flex-shrink-0">
                            <a href="<?= BASE_URL ?>/venditore_vendi.php?edit=<?= $pid ?>"
                            class="btn btn-secondary"
                            style="width:auto; padding:0.35rem 0.9rem; font-size:0.85rem;">✏️ Modifica</a>

                            <button class="btn-delete-product"
                                    data-product-id="<?= $pid ?>"
                                    data-nome="<?= htmlspecialchars((string) $prod['nome']) ?>"
                                    style="background:none; border:1px solid var(--error); color:var(--error);
                                        border-radius:8px; padding:0.35rem 0.9rem; font-size:0.85rem; cursor:pointer;">
                                🗑 Elimina
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div><!-- /products-list -->

            <!-- paginazione TODO: modulo-->
            <?php if ($totPagine > 1): ?>
                <div id = "navigazione-pagine" class="row align-items-center mt-4">
                    
                <div class="col d-flex justify-content-start">
                    <?php if ($page > 1): //tasto indietro?>
                        <a href="?p=<?= $page - 1 ?>" class="btn btn-secondary"
                        style="width:auto; padding:0.45rem 1.1rem;">← Precedente</a>
                    <?php endif; ?>
                </div>

                    <!-- attuale -->
                <div class="col text-center">
                    <span id = "progresso" class="small text-muted">Pagina <?= $page ?> di <?= $totPagine ?></span>
                </div>
                    
                <div class="col d-flex justify-content-end">
                    <?php if ($page < $totPagine): //tasto avanti?>
                        <a href="?p=<?= $page + 1 ?>" id = "btn-next-page-products" class="btn btn-secondary"
                            style="width:auto; padding:0.45rem 1.1rem;">Successiva →
                        </a>
                    <?php endif; ?>
                </div>
                
                </div>
            <?php endif; ?>
            <!-- paginazione TODO: fine modulo-->

        <?php endif; ?>
    <?php endif; ?>
</div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
