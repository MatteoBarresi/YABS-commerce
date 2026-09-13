<?php
/**
 * 
 * Pannello filtro categorie
 * incluso dopo aver caricato $categoryTree (con fetch_category_tree()) e $selectedCategoryIds (gli id già selezionati, da query string, per
 * mantenere la spunta dopo il submit).
 *
 * 
 * La form invia le categorie selezionate come parametro GET ripetuto "cat[]" 
 * e fa submit automatico al cambio di ogni checkbox (JS),
 * così sia home.php che results.php possono leggerlo da $_GET['cat'].
 *
 * @var list<array<string,mixed>> $categoryTree
 * @var list<int> $selectedCategoryIds
 */

$categoryTree        = $categoryTree ?? [];
$selectedCategoryIds  = $selectedCategoryIds ?? [];

/* 
    Renderizza <li> di un nodo categoria e, ricorsivamente, tutti i suoi discendenti
*/
    function renderCatFilterNode(array $node, int $depth, array $selectedCategoryIds) : void{
        $hasChildren = count($node['children']) > 0;
    ?>
    <li class="cat-filter-node">
        <div class="cat-filter-row<?= $depth > 0 ? ' cat-filter-row-child' : '' ?>"
             <?php //dalla seconda sottocategoria in poi (depth >= 2) il rientro fisso della classe CSS non basta più: lo aumento in proporzione alla profondità
                if ($depth > 1): ?>
                style="padding-left: <?= 1.4 * $depth?>rem;"
             <?php endif; ?>>
            <?php if ($hasChildren): //ha categorie discendenti? crea freccia espansione ?>

                <button type="button" class="cat-filter-expand"
                        aria-expanded="false"
                        aria-label="Espandi sottocategorie">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            <?php else: //span solo estetico ?>

                <span class="cat-filter-expand-spacer"></span>
            <?php endif; ?>

            <!-- nome categoria -->
            <label class="cat-filter-label">
                <input type="checkbox" name="cat[]" 
                       value="<?= (int) $node['id'] ?>"
                       class="cat-filter-checkbox"
                       <?= in_array((int) $node['id'], $selectedCategoryIds, true) ? 'checked' : '' //check se si imposta dalla barra degli indirizzi ?>>
                <?= htmlspecialchars((string) $node['nome']) ?>
            </label>
        </div>

        <?php if ($hasChildren): //ci sono categorie figlie ?>
            <ul class="cat-filter-children" hidden>
                <?php foreach ($node['children'] as $child): //richiama se stessa per ogni figlio, un livello più in profondità ?>
                    <?php renderCatFilterNode($child, $depth + 1, $selectedCategoryIds); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
};
?>
<div class="cat-filter-wrap">

    <!-- icona/bottone che apre il pannello -->
    <button type="button" id="cat-filter-toggle" class="cat-filter-toggle"
            aria-expanded="false" aria-controls="cat-filter-panel"
            title="Filtra per categoria">
        <i class="fa-solid fa-filter"></i>
        <span class="cat-filter-count" id="cat-filter-count"
              style="<?= count($selectedCategoryIds) > 0 ? '' : 'display:none;' //nasconde numero se non ci sono filtri attivi ?>">
            <?= count($selectedCategoryIds) ?>
        </span>
    </button>

    <!-- pannello a tendina -->
    <div id="cat-filter-panel" class="cat-filter-panel" hidden>

        <form id="cat-filter-form" method="get" action="">

            <?php //passo come hidden i parametri GET già presenti (q, vendor, sort, dir)
                foreach ($_GET as $key => $value): 
                    if ($key === 'cat') { continue; } // gestito a parte dai checkbox
                    if (is_array($value)) { continue; } //solo valori singoli
            ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>"
                       value="<?= htmlspecialchars((string) $value) ?>">
            <?php endforeach; ?>

            <div class="cat-filter-header">
                <span class="cat-filter-title">Categorie</span>
                <button type="button" id="cat-filter-clear" class="cat-filter-clear">Azzera</button>
            </div>

            <ul class="cat-filter-tree">
                <?php foreach ($categoryTree as $node): //ogni categoria radice, poi i suoi discendenti a qualsiasi profondità ?>
                    <?php renderCatFilterNode($node, 0, $selectedCategoryIds); ?>
                <?php endforeach; ?>    
            </ul>

            <button type="submit" class="cat-filter-apply">Applica</button>
        </form>
    </div>
</div>
