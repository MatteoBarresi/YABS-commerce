<?php
/* 
ci si arriva da navbar o dopo aver pagato
*/
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

// Solo i clienti hanno un carrello
if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    redirect('/home.php', 'I negozianti non hanno un carrello.');
}

$pageTitle  = 'Carrello';
$withNavbar = true;
//$mainClass  = 'main-catalog';
$extraJs    = ['assets/js/carrello.js', 'assets/js/img_handler.js'];

$username = $_SESSION['username'];
$items    = [];
$error    = flash_error();
$success  = flash_success();

//fetch dei prodotti + totale (considera prodotti attivi)
try {
    $pdo   = getConnection();
    $items = fetch_cart_items($pdo, $username);
    $total = calc_cart_total($items);
} catch (Throwable $e) {
    redirect('/db_error.php');
}

require APP_ROOT . '/includes/head.php';
?>


<main class="main-catalog">
    <div class="container py-4" style="max-width: 860px;">

        <h1 class="h3 mb-4" style="color: var(--indigo);">🛒 Il tuo carrello</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- CARRELLO VUOTO non mostrato: gestito nel carrello.js - alt fare if php -->
        <div id="cart-empty" <?= count($items) > 0 ? 'style="display:none;"' : '' ?>>
            <div class="card border-0 text-center py-5" style="background:var(--white); border:1px solid var(--lilac)!important; border-radius:12px;">
                <div class="card-body">
                    <p style="font-size:3rem;">🛍️</p>
                    <h2 class="h5 mb-2">Il carrello è vuoto</h2>
                    <p class="text-muted mb-4">Aggiungi qualcosa dal catalogo!</p>
                    <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary" style="width:auto; padding: 0.6rem 1.5rem;">
                        Torna alla home
                    </a>
                </div>
            </div>
        </div>

        <!-- TABELLA CARRELLO - nascosto solo se non ci sono prodotti dentro -->
        <div id="cart-table-wrap" <?= count($items) === 0 ? 'style="display:none;"' : '' ?>>

            <?php foreach ($items as $item): //controllo che ogni prodotto sia in vendita e disponibile
                $removed    = $item['user_negoziante'] === null;
                $imgSrc     = (!empty($item['img'])) ? BASE_URL . '/' . ltrim((string) $item['img'], '/') : ''; //se c'è un'img, memorizza path (parte da qui)
                $disponib   = (int) ($item['disponibilita'] ?? 0);
                $rowClass   = $removed ? 'cart-row cart-row-removed' : 'cart-row';
            ?>
            
                <!-- parte in cui mostra il prodotto -->
                <div class="<?= $rowClass ?> mb-3 p-3"
                    data-product-id="<?= (int) $item['id_prodotto'] ?>"
                    style="background:var(--white); border:1px solid var(--lilac); border-radius:10px;">

                    <div class="d-flex align-items-center gap-3 flex-wrap">

                        <!-- Immagine -->
                        <div class="cart-img-wrap flex-shrink-0" 
                            style="width:80px; height:80px; border-radius:8px; overflow:hidden; background:var(--lilac); display:flex; align-items:center; justify-content:center;">
                            
                            <?php if ($imgSrc !== '' && !$removed): //prodotto in vendita, con immagine ?>
                                <img src="<?= htmlspecialchars($imgSrc) ?>"
                                    alt="<?= htmlspecialchars((string) $item['nome']) ?>"
                                    style="width:100%; height:100%; object-fit:cover;">
                                <span style="display:none; font-size:1.8rem;">📦</span>
                            
                            <?php else: ?>
                                <span style="font-size:1.8rem;"><?= $removed ? '🚫' : '📦' ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Info prodotto -->
                        <div class="flex-grow-1">
                            <?php if ($removed): ?>
                                <p class="fw-semibold mb-1 text-danger">
                                    <?= htmlspecialchars((string) $item['nome']) ?>
                                    <span class="badge ms-1" style="background:var(--error); font-size:0.7rem;">Non disponibile</span>
                                </p>
                                <p class="small text-muted mb-0">Questo prodotto è stato rimosso dal venditore.</p>
                            
                            <?php else: //prodotto esiste ancora ?>
                                <!-- porta a scheda prodotto-->  
                                <a href="<?= BASE_URL ?>/prodotto.php?id=<?= (int) $item['id_prodotto'] ?>"
                                class="fw-semibold text-decoration-none" style="color:var(--indigo);">
                                    <?= htmlspecialchars((string) $item['nome']) ?>
                                </a>
                                <p class="small text-muted mb-0">
                                    Prezzo unitario: <?= format_price((string) $item['prezzo']) ?>
                                    &nbsp;·&nbsp; Disponibili: <?= $disponib //TODO: valutare se metterlo ?>
                                </p>
                            <?php endif; ?>

                            <!-- errore riga (da JS)-->
                            <p class="cart-row-error small mb-0" style="color:var(--error); display:none;"></p>
                        </div>

                        <!-- Controllo quantità -->
                        <?php if (!$removed): //per un prodotto in vendita, possibile modificare quantità da ordinare - onclick gestito su js ?>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <button class="btn-qty"
                                    data-delta="-1"
                                    aria-label="Diminuisci"
                                    style="width:32px; height:32px; border:1px solid var(--lilac); background:var(--white); border-radius:6px; font-size:1rem; cursor:pointer;">−</button>

                            <input type="number"
                                class="qty-input"
                                value="<?= (int) $item['quantita'] ?>"
                                min="1"
                                max="<?= $disponib ?>"
                                style="width:52px; text-align:center; border:1px solid var(--lilac); border-radius:6px; padding:0.3rem; font-size:0.95rem;">

                            <button class="btn-qty"
                                    data-delta="1"
                                    aria-label="Aumenta"
                                    style="width:32px; height:32px; border:1px solid var(--lilac); background:var(--white); border-radius:6px; font-size:1rem; cursor:pointer;">+</button>
                        </div>
                        <?php endif; ?>

                        <!-- Subtotale riga - mostra quanto costa il singolo prodotto (in base a quantità) -->
                        <div class="text-end flex-shrink-0" style="min-width:90px;">
                            <?php if (!$removed): ?>
                                <span class="cart-subtotal fw-bold" style="color:var(--indigo);">
                                    <?= format_price((string) ((float) $item['prezzo'] * (int) $item['quantita'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>

                        <!-- btn Rimuovi -->
                        <button class="btn-remove flex-shrink-0"
                                aria-label="Rimuovi dal carrello"
                                title="Rimuovi"
                                style="background:none; border:none; cursor:pointer; color:var(--error); font-size:1.2rem; padding:0.2rem 0.4rem;">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- TOTALE + CHECKOUT -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3"
                 style="border-top:1px solid var(--lilac);">

                <!-- tasto back home -->
                <a href="<?= BASE_URL ?>/home.php" class="btn btn-secondary"
                   style="width:auto; padding:0.6rem 1.25rem;">← Continua acquisti</a>

                <!-- display prezzo totale -->
                <div class="text-end">
                    <p class="mb-1 small text-muted">Totale (prodotti disponibili)</p>
                    <p class="fw-bold mb-3" style="font-size:1.35rem; color:var(--indigo);">
                        <span id="cart-total"><?= format_price((string) $total) ?></span>
                    </p>

                    <?php
                    // Ci sono prodotti acquistabili?
                    $hasActive = false;
                    foreach ($items as $item) { //controlla che almeno un prodotto sia ancora in vendita (per mostrare btn "paga")
                        if ($item['user_negoziante'] !== null) { $hasActive = true; break; }
                    }
                    ?>
                    <?php if ($hasActive): //gestito su altra pagina?>
                        <a href="<?= BASE_URL ?>/pagamento.php"
                           class="btn btn-primary"
                           style="width:auto; padding:0.7rem 1.75rem;">Procedi al pagamento →</a>
                    <?php else: //disattiva btn ?>
                        <button class="btn btn-primary" disabled
                                style="width:auto; padding:0.7rem 1.75rem; opacity:0.5;">
                            Procedi al pagamento →
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /cart-table-wrap -->
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
