<?php
/* 
ci si arriva da login
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_guest(); //non loggato

$pageTitle = 'Recupera password';
$error = flash_error();

require APP_ROOT . '/includes/head.php';
?>
<main>
    <div class="auth-card">
        <h1>Password dimenticata - ***WIP***</h1>

        <?php //TODO: mandare mail e settare variabile di sessione apposita?>
        <p class="subtitle">Funzionalità in arrivo (mail() + token in sessione)</p>

        <?php if ($error): //display errori ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="alert alert-success">
            Prossimo step: invio link via <code>mail()</code> e pagina reset con controllo sessione.
        </div>

        <p class="auth-links">
            <a href="<?= BASE_URL ?>/login.php">← Torna al login</a>
        </p>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
