<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login(); //con l'ultimo controllo input, abbiamo settato valori sessione

$pageTitle = 'Categorie preferite';
$extraJs = ['assets/js/register_categorie.js'];
$error = flash_error(); 
$success = flash_success(); //quando passiamo da process precedente, viene impostato un messaggio

$categorie = []; //array assoc con tutte le categorie
try { 
    $pdo = getConnection();
    $categorie = fetch_categories_with_products($pdo); //categorie con almeno un prodotto - altrimenti la GET porta a una pagina vuota
    //$stmt = $pdo->query('SELECT id, nome FROM categoria ORDER BY nome ASC'); //select di tutte le categorie
    //$categorie = $stmt->fetchAll();
} catch (PDOException $e) { 
    redirect('/db_error.php');
}

require APP_ROOT . '/includes/head.php';
?>
<main>
    <div class="auth-card" style="max-width: 480px;">
        <h1>Categorie preferite</h1>
        <p class="subtitle">Passo 3 di 3 — opzionale (salvate nei cookie)</p>


        <?php if ($success): //controlli messaggi errore?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (count($categorie) === 0):?>
            <p>Nessuna categoria nel database. Torna alla home.</p>
            <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary">Vai alla home</a>
        <?php else: //array con dei risultati?>
            
            <!-- form per settare cookie - usati nella home-->
            <form id="form-categorie" method="get" action="<?= BASE_URL ?>/home.php">
                <div class="categorie-grid">
                    <?php foreach ($categorie as $cat): //crea opzione per ogni categoria - value è il valore messo nel cookie ?>
                        <label>
                            <input type="checkbox" name="cat" value="<?= (int) $cat['id'] ?>">
                            <?= htmlspecialchars($cat['nome']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary">Salva e continua</button>
                <a href="<?= BASE_URL ?>/home.php" class="btn btn-secondary">Salta</a>
            </form>
        
        <?php endif; ?>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
