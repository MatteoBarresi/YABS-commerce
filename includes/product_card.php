<?php
/**
 * script invocato all'interno di foreach (in product_row.php e results.php)
 * 
 * Visualizzazione diversa tra home (righe scroll) e results (griglia).
 * 
 * 
 * $product è dichiarata in product_row.php e rappresenta un record (array assoc con campi)
 * - se non commento, lo considera null
 * @var array<string, mixed> $product
 * @var string $cardColClass classe colonna wrapper (es. catalog-card-col o col-md-4)
 */


$cardColClass = $cardColClass ?? 'catalog-card-col'; //in results è diverso
$img = $product['img'] ?? '';
$imgSrc = ($img !== '') ? (BASE_URL . '/' . ltrim((string) $img, '/')) : ''; // parent/path_img
$rating = (float) ($product['media_recensioni'] ?? 0);
$nRev = (int) ($product['n_recensioni'] ?? 0); //numero recensioni
$desc = truncate_product_text(isset($product['descrizione']) ? 
        (string) $product['descrizione'] : null); //se esiste descrizione ed è necessario, accorcia
$productUrl = BASE_URL . '/prodotto.php?id=' . (int) $product['id']; //url pagina del prodotto - cambia a ogni iterazione

if(isset($extraJs))
    add_once_safe('assets/js/img_handler.js', $extraJs);
?>


<div class="<?= htmlspecialchars($cardColClass) ?>">
    
    <div class="card h-100 product-bootstrap-card">
       <!-- immagine prodotto --> 
        <?php if ($imgSrc !== ''): ?>
            <div class="product-card-img-wrap">            
                <img src="<?= htmlspecialchars($imgSrc) ?>"
                    class="card-img-top product-card-img-top"
                    alt="<?= htmlspecialchars((string) $product['nome']) ?>"
                    loading="lazy">
                    <!-- img caricata solo quando l'utente scorre (non subito quando si carica la pagina), 
                    problema con js? -->
                <div class="card-img-top product-card-img-placeholder" style="display:none;" aria-hidden="true">
                    <span>📦</span>
                </div>
            </div>

        <?php else: //immagine default se non esiste ------ TODO: inserire font awesome ?>
            <div class="product-card-img-placeholder" aria-hidden="true">
                <span>📦</span>
            </div>
        <?php endif; ?>

        <!-- dati prodotto -->
        <div class="card-body d-flex flex-column">
            <h5 class="card-title product-card-title"><?= htmlspecialchars((string) $product['nome']) ?></h5>

            <?php if ($desc !== ''): ?>
                <p class="card-text text-muted small mb-2"><?= htmlspecialchars($desc) ?></p>
            <?php endif; ?>

            <?php if ($nRev > 0): //parte con recensioni ?>
                <p class="card-text small mb-2">
                    <?= render_stars((int) round($rating)) ?>
                    <span class="text-muted"><?= number_format($rating, 1, ',', '') ?> (<?= $nRev ?>)</span>
                </p>
            <?php endif; ?>

            <div class="mt-auto pt-2">
                <p class="card-price fw-bold mb-2">
                    <?= format_price( isset($product['prezzo']) ? (string)$product['prezzo'] : null)?>
                </p>
                <a href="<?= htmlspecialchars($productUrl) ?>" class="btn btn-primary btn-sm w-100">
                    Vedi prodotto
                </a>
            </div>
        </div>
    </div>
</div>
