<?php
/*
db_error.php
Pagina di errore generica per problemi di connessione/query al database

USO PREVISTO:
Nei blocchi try/catch di pagine prodotto.php, profilo.php, carrello.php, ecc., 
il codice non continua con $error ma c'è redirect('/db_error.php');
*/

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle  = 'Errore di connessione';
$withNavbar = false;

require APP_ROOT . '/includes/head.php';
?>
<main class="<?= htmlspecialchars('main-catalog') ?>">
<div class="container py-5 d-flex align-items-center justify-content-center" style="min-height:60vh;">
    <div class="text-center" style="max-width:480px;">

        <p style="font-size:4rem; line-height:1; margin-bottom:0.5rem;">🔌</p>

        <h1 class="h3 mb-2" style="color:var(--indigo);">Errore di connessione</h1>

        <p class="text-muted mb-4">
            Non è stato possibile comunicare con il database in questo momento.
            Riprova più tardi: se il problema persiste, contatta l'assistenza.
        </p>

        <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary"
           style="width:auto; padding:0.65rem 1.75rem;">← Torna alla home</a>

    </div>
</div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
