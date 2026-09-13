<?php

/* 
pagina con dettagli prodotto
array con info prodotto è inizialmente null
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

// id passato nella get
$productId = (int) ($_GET['id'] ?? 0); //stringhe di caratteri convertite comunque a 0
if ($productId < 1) {
    redirect('/home.php', 'Prodotto non valido.');
}


$username = $_SESSION['username'];
$tipo = $_SESSION['tipo_utente'] ?? ''; //controllo in più - require login, quindi session[tipo] ha un valore (si assegna lì)

$withNavbar = true;
$extraJs = ['assets/js/prodotto.js', 'assets/js/img_handler.js']; //jquery validator e gestione errori img

$product = null; //info prodotto
$images = [];
$reviews = [];

$hasReviewImages = false; //true se almeno una recensione ha una foto allegata - una delle condizioni per decidere se stampare galleria

$canReview = false; //se questo ut può fare recensione
$existingReview = null;
$isRemoved = false;
$cartQty = 0; //quanti pezzi di questo prodotto ha già nel carrello (solo clienti)
$error = flash_error();
$success = flash_success();

try {
    $pdo = getConnection();
    $product = fetch_product_detail($pdo, $productId);

    if ($product === null) { //restituito un array vuoto
        redirect('/home.php', 'Prodotto non trovato.');
    }

    $isRemoved = $product['user_negoziante'] === null; //prodotto rimosso - negoziante null

    //cerca immagini e incrementa views
    if (!$isRemoved) {
        increment_product_views($pdo, $productId);
        $images = fetch_product_images($pdo, $productId);
        $reviews = fetch_product_reviews($pdo, $productId); 

        $hasReviewImages = count(array_filter($reviews, fn($r) => !empty($r['img_recensione']))) > 0; //se almeno una recensione ha colonna img_recensione non empty
    
        //se un cliente visualizza la pagina, controlla carrello se ha fatto una recensione
        if($tipo === 'cliente'){
            $stmtCart = $pdo->prepare(
                'SELECT quantita FROM carrello WHERE user_cliente = :u AND id_prodotto = :p LIMIT 1'
            );
            $stmtCart->execute([':u' => $username, ':p' => $productId]);
            $rowCart = $stmtCart->fetch();
            $cartQty = $rowCart ? (int) $rowCart['quantita'] : 0; 

            for($i = 0; $i < count($reviews); $i++){
                if($reviews[$i]['user_cliente'] == $username) { //controllo solo una colonna di tutti i risultati
                    $existingReview = true; //serve per mostrare avviso, altrimenti potrei mettere qui dentro la prossima istruzione
                    break;
                }
            }
            //può farla se non l'ha fatta e se ha comprato il prodotto 
            $canReview = ($existingReview === null) && prodotto_arrivato($pdo, $username, $productId);
        }
    }
} catch (PDOException $e) {
    redirect('/db_error.php');
}

//se è vuoto non arriviamo fino a qui
$pageTitle = $product ? (string) $product['nome'] : 'Prodotto';

require APP_ROOT . '/includes/head.php';
?>


<main class="main-catalog">
    <div class="container py-4">
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($product && $isRemoved): //abbiamo un risultato ma vendor è null - controllo in più su $product?>
            <div class="card border-0 product-unavailable">
                <div class="card-body text-center py-5">
                    <h1 class="h3 text-danger">Il prodotto non esiste più</h1>
                    <p class="text-muted mb-4">
                        «<?= htmlspecialchars((string) $product['nome']) ?>»
                        non è più disponibile sul sito.
                    </p>
                    <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary">Torna alla home</a>
                </div>
            </div>

    
        <?php elseif ($product): //prodotto presente?>
            <?php if(false) : ?>
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/home.php">Home</a></li>
                        
                        <?php if (!empty($product['categoria_nome'])): //se c'è il nome della categoria, la mette nel breadcrumb?>
                            <li class="breadcrumb-item"><?= htmlspecialchars((string) $product['categoria_nome']) ?></li>
                        <?php endif; ?>
                            
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars((string) $product['nome']) ?></li>
                    </ol>
                </nav>
            <?php endif; ?>


            <!-- immagine prodotto -->
            <div class="row g-4 mb-5">
                <div class="col-md-5 col-lg-4">
                    <div class="product-gallery card border-0 h-100">
                        <?php
                            $mainImg = $images[0] ?? '';
                            $mainSrc = $mainImg !== '' ? BASE_URL . '/' . ltrim($mainImg, '/') : ''; //controllo in più - non c'è bisogno di ltrim e aggiunta di "/"
                        ?>
                        <div class="product-gallery-main ratio ratio-4x3 bg-light rounded-top">
                            <?php if ($mainSrc !== ''): ?>
                                <img src="<?= htmlspecialchars($mainSrc) ?>"
                                     class="product-main-image rounded-top"
                                     data-index="0"
                                     alt="<?= htmlspecialchars((string) $product['nome']) ?>"
                                     style="cursor:zoom-in;">
                                
                                <span class="product-img-placeholder" style="display:none;">📦</span>
                            
                            <?php else: ?>
                                <span class="product-img-placeholder">📦</span>
                            <?php endif; ?>
                        </div>

                        <?php
                        if (count($images) > 0): //miniature sotto l'immagine principale ?>
                            <div class="product-gallery-thumbs d-flex flex-wrap gap-2 p-2">
                                <?php foreach ($images as $i => $imgPath):
                                    $thumbSrc = BASE_URL . '/' . ltrim((string) $imgPath, '/');
                                ?>
                                    <button type="button"
                                            class="product-gallery-thumb<?= $i === 0 ? ' active' : '' ?>"
                                            data-src="<?= htmlspecialchars($thumbSrc) //in js per slideshow ?>"
                                            data-index="<?= $i ?>"
                                            aria-label="Immagine <?= $i + 1 ?>">
                                        <img src="<?= htmlspecialchars($thumbSrc) ?>"
                                             alt="<?= htmlspecialchars((string) $product['nome']) ?> - <?= $i + 1 ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- dati prodotto -->
                <div class="col-md-7 col-lg-8">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <h1 class="h2 mb-2"><?= htmlspecialchars((string) $product['nome']) ?></h1>

                            <?php //if ((int) $product['n_recensioni'] > 0): //render stelline media recensioni - messo sempre ?>
                                <p class="mb-2">
                                    <?= render_stars((int) round((float) $product['media_recensioni'])) ?>
                                    <span class="text-muted small ms-1">
                                        <?= number_format((float) $product['media_recensioni'], 1, ',', '') //media a numero?>
                                        (<?= (int) $product['n_recensioni'] ?> recensioni)
                                    </span>
                                </p>
                            <?php //endif; ?>

                            <p class="product-price display-6 mb-3"><?= format_price($product['prezzo']) ?></p>
                            <p class="mb-3"><?= htmlspecialchars((string) $product['descrizione']) ?></p>
                            
                            <!-- link a venditore, disponibilità, data pubblicazione-->
                            <ul class="list-unstyled small text-muted mb-4">
                                <li><strong>Venditore:</strong>
                                    <a href="<?= BASE_URL ?>/results.php?vendor=<?= urlencode((string) $product['user_negoziante']) //come encodeURIcomponent ma per php?>">
                                        <?= htmlspecialchars((string) $product['user_negoziante']) ?>
                                    </a>
                                </li>
                                <li><strong>Disponibilità:</strong> <?= (int) $product['disponibilita'] ?> pezzi</li>
                                <li><strong>Pubblicato:</strong> <?= htmlspecialchars((string) $product['data_pubblicazione']) ?></li>
                            </ul>

                            <?php if ($tipo === 'cliente'): 
                                $disponibilita = (int) $product['disponibilita'];
                                $maxAggiungibili = max(0, $disponibilita - $cartQty);

                                //se sei un cliente, mostra FORM PER aggiungere al carrello - con qta modificabile - porta a prodotto_carrello_process ?>
                                <?php if ($disponibilita < 1): ?>
                                    <div class="alert alert-warning mb-0">Prodotto esaurito.</div>
                                
                                <?php elseif ($cartQty > 0): //l'utente ha il prodotto nel carrello?>
                                    <div class="alert alert-info mb-3">
                                        Hai già <strong><?= $cartQty ?></strong>
                                        <?= $cartQty === 1 ? 'pezzo' : 'pezzi' ?>
                                        di questo prodotto nel carrello.
                                        <?php if ($maxAggiungibili > 0): ?>
                                            Puoi aggiungerne ancora al massimo <strong><?= $maxAggiungibili ?></strong>.
                                        <?php else: ?>
                                            Non ci sono altre copie disponibili. Non puoi aggiungerne più.
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/carrello.php" class="alert-link">Vai al carrello</a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($disponibilita >= 1 && $maxAggiungibili > 0): //form per aggiungere al carrello ?>
                                    <form method="post" 
                                        action="<?= BASE_URL ?>/prodotto_carrello_process.php" 
                                        class="row g-2 align-items-end product-add-cart"
                                        data-cart-qty="<?= $cartQty ?>"
                                        data-disponibilita="<?= $disponibilita ?>"
                                        data-max-add="<?= $maxAggiungibili ?>"
                                        novalidate>
                                        <input type="hidden" name="id_prodotto" value="<?= (int) $product['id'] ?>">
                                        <div class="col-auto">
                                            <label for="quantita" class="form-label mb-0">Quantità da aggiungere</label>
                                            <input type="number"
                                                   class="form-control"
                                                   id="quantita"
                                                   name="quantita"
                                                   value="1"
                                                   min="1"
                                                   max="<?= /*(int) $product['disponibilita']*/ $maxAggiungibili ?>"
                                                   required>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-primary">Aggiungi al carrello</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                            <?php elseif ($tipo === 'negoziante' && $product['user_negoziante'] === $username): //negoziante che vede suo prodotto ?>
                                <p class="text-muted mb-0"><em>Questo è uno dei tuoi prodotti.</em></p>
                                <a href="<?= BASE_URL ?>/venditore_vendi.php?edit=<?= (int) $product['id'] ?>"
                                   class="btn btn-secondary btn-sm"
                                   style="width:auto;">
                                    ✏️ Modifica prodotto
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <section class="product-reviews">
                <h2 class="h4 mb-3">Recensioni</h2>

                <?php if ($canReview): //form insert recensione TODO: fare script a parte e includere ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="h6 mb-3">Scrivi una recensione</h3>

                            <form id="form-recensione" method="post" action="<?= BASE_URL ?>/recensione_process.php" 
                                enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="id_prodotto" value="<?= (int) $product['id'] ?>">

                                <!-- voto recensione - render stelline -->
                                <div class="mb-3">
                                    <label class="form-label">Valutazione</label>
                                    <div class="star-rating-input d-flex flex-row gap-1">
                                        <?php for ($i = RECENSIONE_VOTO_MIN; $i <= RECENSIONE_VOTO_MAX; $i++): ?>
                                            <label class="star-rating-label" title="<?= $i ?> stell<?= $i > 1 ? 'e' : 'a' ?>">
                                                <input type="radio" name="valutazione" value="<?= $i ?>" class="d-none" required>
                                                <span class="star-icon">★</span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- testo recensione -->
                                <div class="mb-3">
                                    <label for="testo" class="form-label">
                                        Testo (opzionale) - 
                                        <span id="testo-counter"><?= RECENSIONE_TESTO_MAX ?></span> caratteri rimanenti
                                    </label>
                                    <textarea class="form-control"
                                            id="testo"
                                            name="testo"
                                            maxlength="<?= RECENSIONE_TESTO_MAX ?>"
                                            rows="3"
                                            placeholder="Scrivi qui la tua recensione..."></textarea>
                                </div>

                                <!-- immagine recensione -->
                                <div class="mb-3">
                                    <label for="img_recensione" class="form-label">Immagine (opzionale, jpg/png, max 2MB)</label>
                                    <input type="file"
                                           id="img_recensione"
                                           name="img_recensione"
                                           accept="image/jpeg,image/png"
                                           class="form-control">
                                </div>

                                <button type="submit" class="btn btn-primary">Pubblica recensione</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($existingReview):?>
                    <div class="alert alert-info">Hai già recensito questo prodotto.</div>
                <?php endif; ?>

                <!-- parte che mostra recensioni del prodotto-->
                <?php if (!$reviews): //non ci sono - array vuoto?>
                    <p class="text-muted">Nessuna recensione ancora.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($reviews as $rev): //per ogni recensione, crea div?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                            
                                            <!-- chi l'ha scritta -->
                                            <strong>    
                                                <?php if ($rev['user_cliente'] === null): ?>
                                                    account cancellato
                                                <?php else: ?>
                                                    <?= htmlspecialchars((string) $rev['user_cliente']) ?>
                                                <?php endif; ?>
                                            </strong>

                                            <span class="text-muted small">
                                                <?= htmlspecialchars((string) $rev['data_inserimento']) ?>
                                            </span>

                                        </div>
                                        <p class="mb-1"><?= render_stars((int) $rev['valutazione']) ?></p>
                                       
                                        <?php if (!empty($rev['testo'])): //c'è testo ?>
                                            <p class="mb-1"><?= htmlspecialchars((string) $rev['testo']) ?></p>
                                        <?php endif; ?>

                                         <?php if (!empty($rev['img_recensione'])): ?>
                                            <img src="<?= BASE_URL . '/' . ltrim((string) $rev['img_recensione'], '/') ?>"
                                                 alt="Immagine recensione"
                                                 class="review-img mt-2"
                                                 role="button" tabindex="0"
                                                 title="Clicca per ingrandire"
                                                 style="max-width:220px; max-height:180px; object-fit:cover;
                                                        border-radius:6px; border:1px solid var(--lilac); cursor:pointer;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; // fine lista recensioni?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; //fine dettagli prodotto?>
    </div>
</main>

<?php if (!empty($images) || $hasReviewImages): ?>
<!-- galleria: overlay + slideshow. aperto da prodotto.js al click su miniatura o immagine principale -->
<div id="lightbox-overlay" role="dialog" aria-modal="true" aria-label="Galleria immagini" hidden>

    <div id="lightbox-container">

        <!-- chiudi -->
        <button id="lightbox-close" aria-label="Chiudi galleria">✕</button>

        <!-- immagine principale -->
        <div id="lightbox-main">
            <button class="lightbox-arrow" id="lightbox-prev" aria-label="Immagine precedente">‹</button>

            <img id="lightbox-img"
                 src=""
                 alt="<?= htmlspecialchars((string) $product['nome']) ?>">

            <button class="lightbox-arrow" id="lightbox-next" aria-label="Immagine successiva">›</button>
        </div>

        <!-- strip miniature in basso -->
        <div id="lightbox-thumbs">
            <?php foreach ($images as $i => $imgPath):
                $src = BASE_URL . '/' . ltrim((string) $imgPath, '/');
            ?>
                <button type="button"
                        class="lightbox-thumb"
                        data-src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
                        data-index="<?= $i ?>"
                        aria-label="Immagine <?= $i + 1 ?>">
                    <img src="<?= htmlspecialchars($src) ?>"
                         alt="<?= htmlspecialchars((string) $product['nome']) ?> - <?= $i + 1 ?>">
                </button>
            <?php endforeach; ?>
        </div>

    </div><!-- /lightbox-container -->
</div><!-- /lightbox-overlay -->
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
