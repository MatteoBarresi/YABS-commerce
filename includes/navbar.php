<?php
/**
 * Navbar + searchbar (pagine autenticate).
 */

$navUser = $_SESSION['username'] ?? '';
$navTipo = $_SESSION['tipo_utente'] ?? '';
$cartInfo = ['count' => 0, 'has_removed' => false]; //carrello - n prodotti e rimossi
$notifNewCount = 0; //notifiche non ancora viste in questa sessione 


if ($navUser !== '') { //qualsiasi utente loggato (cliente o negoziante) può avere notifiche
    try {

        $pdo = getConnection();
        $notifNewCount = count_unread_notifications($pdo, $navUser);

        if($navTipo === 'cliente')
            $cartInfo = get_cart_navbar_info($pdo, $navUser); //controllo quanti prodotti nel cart + se alcuni sono stati rimossi
    
    } catch (PDOException $e) {
        redirect('/db_error.php'); //TODO: feedback
    }
}
?>

<header class="site-header">
    <a class="site-logo" href="<?= BASE_URL ?>/home.php">YABS</a>

    <!-- SEARCH BAR gestita in results.php -->
    <div class="search-wrap">
        <form class="search-form" action="<?= BASE_URL ?>/results.php" method="get" role="search">
            <label class="visually-hidden" for="search-input">Cerca prodotti o negozianti</label>
            <input type="search"
                   id="search-input"
                   name="q"
                   class="search-input"
                   placeholder="Cerca prodotti o negozianti…"
                   maxlength="50">
                   
            <button type="submit" class="search-submit" aria-label="Cerca">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <div id="search-suggest" class="search-suggest" hidden></div>
        </form>
    </div>

    <nav class="site-nav" aria-label="Menu principale">
       
        <a href="<?= BASE_URL ?>/notifiche.php" class="nav-link nav-notifiche" title="Notifiche">
            Notifiche 🔔
            <?php if ($notifNewCount > 0): // mostra numero su badge ?>
                <span class="nav-badge"><?= $notifNewCount > 9 ? '9+' : $notifNewCount ?></span>
            <?php endif; ?>
        </a>
        
        <?php if ($navTipo === 'cliente'): //CARRELLO CLIENTE ?>
            <a href="<?= BASE_URL ?>/carrello.php" class="nav-link nav-cart" title="Carrello">
                Carrello 🛒
                <?php if ($cartInfo['count'] > 0): //ha cose nel carrello  ?>
                    <span class="nav-badge"><?= (int) $cartInfo['count'] ?></span>
                <?php endif; ?>
                <?php if ($cartInfo['has_removed']): //è stato rimosso un prodotto che aveva nel carrello?>
                    <span class="nav-badge nav-badge-alert" title="Prodotti non più disponibili">!</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>


        <a href="<?= BASE_URL ?>/ordini.php" class="nav-link" title="I miei ordini">I miei Ordini 📦</a>


        <?php if ($navTipo === 'negoziante'): //solo se negoziante, mostra questo link?>
            <a href="<?= BASE_URL ?>/venditore_vendi.php" class="nav-link" title="I miei prodotti">Aggiungi prodotto ➕</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/profilo.php" class="nav-link" title="Profilo">Profilo 👤</a>
        <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-logout" title="Esci">Esci</a>
    </nav>
</header>
