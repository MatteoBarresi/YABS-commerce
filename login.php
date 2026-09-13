<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_guest(); //utente non loggato

$pageTitle = 'Accedi';
$extraJs = ['assets/js/login.js']; //usato nel footer - valida form  


$error = flash_error(); //per redirect su questa pagina (es login_process)
$success = flash_success();

require APP_ROOT . '/includes/head.php';
?>
<main>
    <div class="auth-card">
        <h1>Accedi</h1>
        <p class="subtitle">Username o email e password</p>

        <?php if ($success): //avvisi / errori ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form id="form-login" method="post" action="<?= BASE_URL ?>/login_process.php" novalidate>
            <div class="form-group">
                <label for="login">Username o Email*</label>
                <input type="text" id="login" name="login" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Accedi</button>
        </form>

        <p class="auth-links">
            Non hai un account? <a href="<?= BASE_URL ?>/register.php">Registrati</a>
        </p>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
