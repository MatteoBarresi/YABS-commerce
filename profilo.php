<?php
/* 
accesso da navbar
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

$pageTitle  = 'Il mio profilo';
$withNavbar = true;
$extraJs    = ['assets/js/profilo.js'];

$username = $_SESSION['username'];
$error    = flash_error();
$success  = flash_success();

$profile = null;
$cards   = [];

//query select dati utente + carte se è un cliente
try {
    $pdo     = getConnection();
    $profile = fetch_user_profile($pdo, $username);
    if ($_SESSION['tipo_utente'] === 'cliente') {
        $cards = fetch_user_cards($pdo, $username);
    }
} catch (Throwable $e) {
    redirect('/db_error.php');
}

require APP_ROOT . '/includes/head.php';
?>

<main class="main-catalog">
<div class="container py-4" style="max-width:680px;">

    <h1 class="h3 mb-4" style="color:var(--indigo);">👤 Il mio profilo</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($profile): //utente esiste TODO: gestire caso contrario prima; togliere questo if ?>

        <!-- ===== DATI PERSONALI ===== -->
        <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0" style="color:var(--indigo);">
                        Dati personali
                    </h2>
                    <!-- bottone che mostra form-profilo -->
                    <button id="btn-edit-toggle" class="btn btn-secondary"
                            style="width:auto; padding:0.35rem 1rem; font-size:0.85rem;">Modifica</button>
                </div>

                <!-- DATI UTENTE - nascosti alla modifica -->
                <div id="profile-view">
                    <p class="mb-1">
                        <span class="text-muted small">Username</span>
                        <br>
                        <strong><?= htmlspecialchars($profile['username']) ?></strong>
                    </p>
                    <p class="mb-1">
                        <span class="text-muted small">Email</span>
                        <br>
                        <strong><?= htmlspecialchars((string) $profile['email']) ?></strong>
                    </p>
                    <p class="mb-0">
                        <span class="text-muted small">Indirizzo</span>
                        <br>
                        <strong><?= $profile['indirizzo'] ? htmlspecialchars($profile['indirizzo']) : '<em class="text-muted">non inserito</em>' ?></strong>
                    </p>
                </div>

                <!-- form modifica (nascosto di default)-->
                <form id="form-profilo" action="<?= BASE_URL ?>/profilo_process.php" method="post"
                    novalidate style="display:none;">
                    <!-- tipo di operazione -->
                    <input type="hidden" name="action" value="update_profile">

                    <!-- campi -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required maxlength="100"
                            value="<?= htmlspecialchars((string) $profile['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="indirizzo">Indirizzo</label>
                        <input type="text" id="indirizzo" name="indirizzo" maxlength="50"
                            value="<?= htmlspecialchars((string) ($profile['indirizzo'] ?? '')) ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"
                                style="width:auto; padding:0.5rem 1.25rem;">
                                Salva
                        </button>
                        <!-- bottone che nasconde form-profilo -->
                        <button type="button" id="btn-edit-cancel" class="btn btn-secondary"
                                style="width:auto; padding:0.5rem 1.25rem;">
                                Annulla
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== CAMBIO PASSWORD ===== -->
        <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0" style="color:var(--indigo);">Cambia password</h2>
                    <!-- bottone che mostra form-password --> 
                    <button id="btn-pwd-toggle" class="btn btn-secondary" style="width:auto; padding:0.35rem 1rem; font-size:0.85rem;">
                            Modifica
                    </button>
                </div>

                <!--form modifica password -->
                <form id="form-password" action="<?= BASE_URL ?>/profilo_process.php" method="post"
                    novalidate style="display:none;">
                    
                    <!-- tipo di operazione -->
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label for="psw_attuale">Password attuale</label>
                        <input type="password" id="psw_attuale" name="psw_attuale" required>
                    </div>

                    <div class="form-group">
                        <label for="psw_nuova">Nuova password</label>
                        <input type="password" id="psw_nuova" name="psw_nuova" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="psw_conferma">Conferma nuova password</label>
                        <input type="password" id="psw_conferma" name="psw_conferma" required>
                    </div>
                    <!-- bottoni -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" style="width:auto; padding:0.5rem 1.25rem;">Salva</button>
                        <!-- bottone che nasconde form-password -->
                        <button type="button" id="btn-pwd-cancel" class="btn btn-secondary"style="width:auto; padding:0.5rem 1.25rem;">Annulla</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== CARTE DI CREDITO (solo clienti) ===== -->
        <?php if ($_SESSION['tipo_utente'] === 'cliente'): ?>
            <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0" style="color:var(--indigo);">Carte di pagamento</h2>
                        <!-- bottone che mostra form-addcard -->
                        <button id="btn-addcard-toggle" class="btn btn-secondary" style="width:auto; padding:0.35rem 1rem; font-size:0.85rem;">
                            + Aggiungi
                        </button>
                    </div>

                    <!-- lista carte -->
                    <div id="cards-list">
                        <?php if (count($cards) === 0): ?>
                            <p class="text-muted small mb-0" id="no-cards-msg">Nessuna carta salvata.</p>
                        <?php else: ?>
                            <?php foreach ($cards as $card): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 carta-row"
                                id="carta-<?= (int) $card['id'] ?>"
                                style="border-bottom:1px solid var(--lilac);">
                                
                                <!-- SN, e altri dati carta -->
                                <span class="small">
                                    💳 **** <?= htmlspecialchars(substr((string) $card['numero'], -4)) ?>
                                    &nbsp;—&nbsp;
                                    <?= htmlspecialchars((string) $card['nome_intestatario']) ?>
                                    <?= htmlspecialchars((string) $card['cognome_intestatario']) ?>
                                    &nbsp;(scad. <?= htmlspecialchars((string) $card['data_scadenza']) ?>)
                                </span>

                                <!-- btn che genera chiamata ajax-->
                                <button class="btn-remove-card"
                                        data-card-id="<?= (int) $card['id'] ?>"
                                        style="background:none; border:none; color:var(--error); cursor:pointer; font-size:1.1rem;"
                                        aria-label="Rimuovi carta">✕</button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- form aggiungi carta (nascosto) -->
                    <form id="form-addcard" action="<?= BASE_URL ?>/profilo_process.php" method="post"
                        novalidate style="display:none; margin-top:1rem;">
                        
                        <!-- tipo operazione -->
                        <input type="hidden" name="action" value="add_card">
                        
                        <div class="form-group">
                            <label for="card_numero">Numero carta</label>
                            <input type="text" id="card_numero" name="numero_carta"
                                maxlength="19" placeholder="1234 5678 9012 3456" required>
                        </div>
                        <!-- dati utente-->
                        <div class="d-flex gap-3">
                            <div class="form-group flex-grow-1">
                                <label for="card_nome">Nome</label>
                                <input type="text" id="card_nome" name="nome_intestatario"
                                    maxlength="20" required>
                            </div>
                            <div class="form-group flex-grow-1">
                                <label for="card_cognome">Cognome</label>
                                <input type="text" id="card_cognome" name="cognome_intestatario"
                                    maxlength="20" required>
                            </div>
                        </div>
                        
                        <!-- scadenza -->
                        <div class="d-flex gap-3">
                            <div class="form-group flex-grow-1">
                                <label for="card_scadenza">Scadenza (AAAA-MM-GG)</label>
                                <input type="date" id="card_scadenza" name="data_scadenza" required>
                            </div>
                        </div>
                        
                        <!-- display errori-->
                        <p id="card-error" class="small mb-2" style="color:var(--error); display:none;"></p>
                        
                        <!-- bottoni conferma-->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" style="width:auto; padding:0.5rem 1.25rem;">
                                    Salva carta
                            </button>
                            <!-- bottone che nasconde form-addcard -->
                            <button type="button" id="btn-addcard-cancel" class="btn btn-secondary" style="width:auto; padding:0.5rem 1.25rem;">
                                    Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== ELIMINA ACCOUNT ===== -->
        <div class="card border-0 mb-4" style="border:1px solid #f5c6c6!important; border-radius:10px;">
            <div class="card-body">
                <h2 class="h6 mb-1" style="color:var(--error);">Zona pericolosa</h2>
                <p class="small text-muted mb-3">L'eliminazione dell'account è permanente e non reversibile.</p>
                
                <button id="btn-delete-toggle" class="btn"
                        style="width:auto; padding:0.45rem 1.25rem; background:var(--error); color:#fff; border:none; border-radius:8px; font-size:0.9rem;">
                    Elimina account
                </button>
                
                <!-- conferma inline -->
                <div id="delete-confirm" style="display:none; margin-top:1rem;">
                    <p class="small mb-2" style="color:var(--error);">
                        Sei sicuro? Inserisci la tua password per confermare.
                    </p>

                    <form id="form-delete" action="<?= BASE_URL ?>/profilo_process.php" method="post" novalidate>
                        <!-- tipo azione -->
                        <input type="hidden" name="action" value="delete_account">
                        <div class="form-group">
                            <input type="password" name="psw_conferma_delete" id="psw_conferma_delete"
                                placeholder="Password attuale" required>
                        </div>
                        <!-- bottoni -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn"
                                    style="width:auto; padding:0.5rem 1.25rem; background:var(--error); color:#fff; border:none; border-radius:8px;">
                                Sì, elimina
                            </button>
                            <button type="button" id="btn-delete-cancel" class="btn btn-secondary"
                                    style="width:auto; padding:0.5rem 1.25rem;">
                                    Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div> 
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
