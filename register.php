<?php
/* 
    contiene form registrazione (1 di 3), gestita in register_process.php
*/


require_once __DIR__ . '/includes/bootstrap.php';
require_guest(); //utente non loggato

$pageTitle = 'Registrazione';
$extraJs = ['assets/js/register.js']; //contiene funzione jquery validation 
$error = flash_error();

//contiene dati inseriti nella form - se torniamo indietro, riempie la form con dati appena inseriti dall'utente
$draft = $_SESSION['register_draft'] ?? []; 
require APP_ROOT . '/includes/head.php';
?>

<main>
    <div class="auth-card">
        <h1>Crea account</h1>
        <p class="subtitle">Passo 1 di 3 — dati personali</p>

        <?php if ($error): //messaggi di errore ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- novalidate per non inviare form: uso jquery validator-->
        <form id="form-register" method="post" action="<?= BASE_URL ?>/register_process.php" novalidate>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" maxlength="20"
                       value="<?= htmlspecialchars($draft['username'] ?? '') //se torniamo indietro, usa sessione ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" maxlength="100"
                       value="<?= htmlspecialchars($draft['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" minlength="6" required>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Conferma password *</label>
                <input type="password" id="password_confirm" name="password_confirm" minlength="6" required>
            </div>
            
            <div class="form-group">
                <label for="indirizzo">Indirizzo (opzionale)</label>
                <input type="text" id="indirizzo" name="indirizzo" maxlength="50"
                       value="<?= htmlspecialchars($draft['indirizzo'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Continua</button>
        </form>

        <p class="auth-links">
            Hai già un account? <a href="<?= BASE_URL ?>/login.php">Accedi</a>
        </p>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
