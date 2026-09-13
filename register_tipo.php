<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

if (empty($_SESSION['register_draft']['username'])) { //veniamo dalla pagina di gestione della prima form 
    redirect('/register.php', 'Completa prima i dati di registrazione.');
}

$pageTitle = 'Tipo account';
$extraJs = ['assets/js/register_tipo.js']; //jquery validator - controlla che sia selezionato il tipo-utente
$error = flash_error();

require APP_ROOT . '/includes/head.php';
?>
<main>
    <div class="auth-card">
        <h1>Tipo account</h1>
        <p class="subtitle">Passo 2 di 3 — come vuoi usare il sito?</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form id="form-register-tipo" method="post" action="<?= BASE_URL ?>/register_tipo_process.php">
            <div class="tipo-options">
                <label>
                    <input type="radio" name="tipo_utente" value="cliente" required>
                    <span><strong>Cliente</strong> — acquista prodotti</span>
                </label>
                <label>
                    <input type="radio" name="tipo_utente" value="negoziante" required>
                    <span><strong>Negoziante</strong> — vende i suoi prodotti</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Continua</button>
        </form>

        <p class="auth-links">
            <a href="<?= BASE_URL ?>/register.php">← Modifica dati</a>
        </p>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
