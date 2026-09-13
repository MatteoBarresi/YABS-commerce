<?php
/*
notifiche.php - lista di tutte le notifiche dell'utente (cliente o negoziante).
La colonna "user_cliente" della tabella notifica è l'utente destinatario.

La colonna "letta" (tinyint, 0/1, default 0) traccia se la notifica è stata vista. 
Si arriva su questa pagine e tutte le notifiche vengono segnate come lette. 
il badge in navbar conta le sole non lette (torna a 0).

tipi enum e significato:
- 'acquisto'                : un cliente ha fatto un ordine che include un prodotto del negoziante -> link a ordini.php (lato negoziante)
- 'oos'                     : un prodotto nel carrello del cliente non è più disponibile (rimosso)  -> link a carrello.php
- 'magazzino'               : un prodotto nel carrello del cliente non è più disponibile in quella quantità -> link a carrello.php
- 'aggiornamento_spedizione': lo stato di un ordine del cliente è cambiato -> link a ordini.php (lato cliente)
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

$pageTitle  = 'Notifiche';
$withNavbar = true;
//$mainClass  = 'main-catalog';
$extraJs    = ['assets/js/notifiche.js'];

$username = $_SESSION['username'];
$error    = flash_error();
$success  = flash_success();

$page   = max(1, (int) ($_GET['p'] ?? 1)); //controllo pagina <=0 - imposta a 1 TODO: mostrare pagina errore / numero non valido
$limit  = 3;
$offset = ($page - 1) * $limit;

$notifiche = [];
$total     = 0;

try {
    $pdo       = getConnection();
    $notifiche = fetch_notifications($pdo, $username, $limit, $offset); //qui quelle non lette hanno valore 0 (per differenziare eventualmente)
    $total     = count_notifications($pdo, $username); //più sicuro di count(notifiche) perché non tiene conto dell'offset
    $notifichePagina = count($notifiche);
    // segna come lette tutte le notifiche dell'utente 
    mark_all_notifications_read($pdo, $username);

} catch (Throwable $e) {
    redirect('/db_error.php');
}

$totPagine = $total > 0 ? (int) ceil($total / $limit) : 1;

// require_once esplicito perché enums.php serve prima di head.php (usato nel foreach dell'HTML)
require_once APP_ROOT . '/includes/enums.php';

require APP_ROOT . '/includes/head.php';
?>

<main class="main-catalog">
    <div class="container py-4" style="max-width:720px;">
        <!-- ===== LISTA notifiche ===== -->
        <?php if (($total === 0 )): //nessuna notifica?>
                <?php include APP_ROOT . "/includes/no-notifiche.php"?>
        <?php else: //l'utente ha notifiche registrate nel DB ?>
            
            <div id = "elimina-notifiche" class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0" style="color:var(--indigo);">🔔 Notifiche</h1>
                <?php if ($notifichePagina > 0): //btn elimina notifiche - ci sono notifiche in questa pagina?>
                    <button id="btn-clear-all"
                            style="background:none; border:1px solid var(--error); color:var(--error);
                                border-radius:8px; padding:0.35rem 1rem; font-size:0.85rem; cursor:pointer;">
                        Elimina tutte
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($notifichePagina === 0): //numero notifiche per questa pagina - es inserito ?p=100 ?>
                
                <div id="notifiche-empty" class="card border-0 text-center py-5"
                    style="background:var(--white); border:1px solid var(--lilac)!important; border-radius:12px;">
                    <div class="card-body">
                        <p style="font-size:3rem;">🔕</p>
                        <h2 class="h5 mb-1">Fine notifiche</h2>
                        <p class="text-muted mb-0">Non hai così tante notifiche</p>
                        
                        <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary" style="width:auto; padding: 0.6rem 1.5rem;">
                            Torna alla home
                        </a>

                    </div>
                </div>
            <?php else: //lista vera e propria ?>
                
                <!-- lista notifiche -->
                <div id="notifiche-list"
                    data-page="<?= $page ?>"
                    data-limit="<?= $limit ?>"
                    data-total="<?= $total ?>">
                    <?php foreach ($notifiche as $n):
                        $nid    = (int) $n['id'];
                        $tipo   = (string) $n['tipo'];
                        $testo  = (string) $n['testo'];
                        // da enums.php
                        $enumMap = notif_tipo_map();
                        $entry  = $enumMap[$tipo] ?? notif_tipo_fallback();
                        $color  = $entry['color'];
                        $link   = notif_build_link($tipo, $testo);
                    ?>
                        <!-- row notifica --> 
                        <div class="notif-row d-flex align-items-start gap-3 mb-2 px-3 py-3"
                            id="notif-<?= $nid //id creato dinamicamente ?>"
                            style="background:var(--white); border:1px solid var(--lilac);
                                    border-radius:10px; border-left:4px solid <?= $color ?>;">

                            <!-- icona -->
                            <div style="font-size:1.4rem; flex-shrink:0; line-height:1;"><?= $entry['icon'] ?></div>

                            <!-- contenuto -->
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="small fw-semibold px-2 py-0"
                                        style="border-radius:20px; background:<?= $color ?>22;
                                                color:<?= $color ?>; border:1px solid <?= $color ?>44;">
                                        <?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="text-muted" style="font-size:0.78rem;">
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $n['data_notifica']))) ?>
                                    </span>
                                </div>

                                <!-- messaggio completo-->
                                <p class="mb-0 small" style="color:#333;">
                                    <?= htmlspecialchars($testo) ?>
                                </p>

                                <?php if ($link !== null): //mostra link se c'è ?>
                                    <a href="<?= htmlspecialchars($link) ?>"
                                    class="small fw-semibold text-decoration-none"
                                    style="color:var(--indigo);">
                                        Vai al dettaglio →
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- elimina singola notifica-->
                            <button class="btn-delete-notif flex-shrink-0"
                                    id="btn-<?= $nid ?>"
                                    aria-label="Elimina notifica"
                                    style="background:none; border:none; color:#ccc; cursor:pointer;
                                        font-size:1rem; padding:0; line-height:1;">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div><!-- /notifiche-list -->

                <!-- paginazione TODO: modulo-->
                <?php if ($totPagine > 1): ?>
                    <div id = "navigazione-pagine" class="row align-items-center mt-4">
                        <div class="col d-flex justify-content-start">
                        <?php if ($page > 1): ?>
                            <a href="?p=<?= $page - 1 ?>" class="btn btn-secondary"
                            style="width:auto; padding:0.45rem 1.1rem;">← Precedente</a>
                        <?php endif; ?>
                        </div>

                        <div class="col text-center">
                            <span id = "progresso" class="small text-muted">Pagina <?= $page ?> di <?= $totPagine ?></span>
                        </div>

                        <div class="col d-flex justify-content-end">
                        <?php if ($page < $totPagine): ?>
                            <a href="?p=<?= $page + 1 ?>" id = "btn-next-page" class="btn btn-secondary"
                            style="width:auto; padding:0.45rem 1.1rem;">Successiva →</a>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- TODO: fine modulo-->
            <?php endif; ?>  
        <?php endif; ?> 
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
