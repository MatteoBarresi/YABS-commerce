<?php
/**
 * Riga orizzontale scrollabile di card prodotto.
 * 
 * variabili dichiarate in home.php: 
 * $rowTitle è il titolo della sezione
 * $products è un array che contiene array associativi (prodotti db)
 * @var string $rowTitle
 * @var list<array<string, mixed>> $products
 * 
 * è un pezzo modulare, usato in home.php per mostrare card prodotto
*/
if (count($products) === 0) { // non ci sono row per questo "accumulo"
    return;
}

$cardColClass = 'catalog-card-col';
?>
<section class="catalog-row mb-4">
    <div class="container-fluid px-3 px-lg-4">
        <!-- motivo accumulo -->
        <h2 class="h5 catalog-row-title mb-3"><?= htmlspecialchars($rowTitle) ?></h2>
        <div class="catalog-row-track">
            <?php foreach ($products as $product): ?>
                <?php require APP_ROOT . '/includes/product_card.php'; //card singolo prodotto  ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
