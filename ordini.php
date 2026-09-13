<?php
/* 
display ordini utente + prodotti per ogni ordine
ci si arriva da navbar
*/

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

$pageTitle  = 'I miei ordini';
$withNavbar = true;
//$mainClass  = 'main-catalog';
$extraJs    = ['assets/js/ordini.js', 'assets/js/img_handler.js'];

$username = $_SESSION['username'];
$tipo     = $_SESSION['tipo_utente'] ?? '';
$error    = flash_error();
$success  = flash_success();


//da notifiche
$expand = $_GET['expand'] ?? 0;
if($expand){
    $expand = filter_var($_GET['expand'], FILTER_VALIDATE_INT);
    $expand = $expand > 0 ? $expand : 0;
}

//vengono cercati 10 ordini alla volta e si tiene conto di dove siamo grazie all'offset
$page   = max(1, (int) ($_GET['p'] ?? 1)); 
$limit  = 10;
$offset = ($page - 1) * $limit; //0 : 0; 1:10; 2:20

$orders = [];

function ordini(PDO $pdo, string $tipo, string $user, int $lim, $off) : array{
    if ($tipo === 'cliente') {
        return fetch_client_orders($pdo, $user, $lim, $off); //ordini fatti da questo utente
    } else
     {
        return fetch_vendor_orders($pdo, $user, $lim, $off); //ordini ricevuti da questo negoziante
    }
}


try {
    $pdo = getConnection();

    $orders = ordini($pdo, $tipo, $username, $limit, $offset);
    
    $ordiniPagina = count($orders);
    $totale = ($tipo === 'negoziante') 
        ? count_vendor_orders_total($pdo, $username)
        : count_orders($pdo, $username);
    // per ogni ordine, trova prodotti comprati 
    $orderProducts = []; //associa: id_ordine => [prod_1 => {dati}, prod_2=> {dati}]
    foreach ($orders as $ord) {
        if ($tipo === 'cliente') {
            $orderProducts[$ord['id']] = fetch_order_products($pdo, (int) $ord['id']);
        } else {
            $orderProducts[$ord['id']] = fetch_vendor_order_products($pdo, (int) $ord['id'], $username);
        }
    }

    if($expand){
        $all_orders = ordini($pdo, $tipo, $username, $totale, 0);
        
        $ids = array_map(fn($item)=> $item['id'], $all_orders);
        $posizione = array_search($expand, $ids);
        if($posizione !== false){
            $_SESSION['id_expand'] = $expand;
            header('Location: ?p='.(int)ceil(($posizione+1)/$limit));
            exit;
            
        }
    }

} catch (Throwable $e) {
    redirect('/db_error.php');
}
$totPagine = $totale > 0 ? (int) ceil($totale / $limit) : 1; //limit ordini in una pagina - quante volte ci sta il totale nel limite

// per ordine_stato_map() 
require_once APP_ROOT . '/includes/enums.php';

require APP_ROOT . '/includes/head.php';

?>


<main class="main-catalog">
<div class="container py-4" style="max-width:820px;">

    <h1 class="h3 mb-4" style="color:var(--indigo);">
        <?= $tipo === 'negoziante' ? '📦 Ordini ricevuti' : '📦 I miei ordini' ?>
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($totale === 0): //non ci sono ordini?>
        <?php include APP_ROOT . "/includes/no-ordini.php"?>

    <?php else: ?>

        <?php if ($ordiniPagina === 0): //numero ordini per questa pagina - es inserito ?p=100?>
            <div id="ordini-empty" class="card border-0 text-center py-5"
                    style="background:var(--white); border:1px solid var(--lilac)!important; border-radius:12px;">
                <div class="card-body">
                    <p style="font-size:3rem;"></p>
                    <h2 class="h5 mb-1">Fine ordini</h2>
                    <p class="text-muted mb-0">Non hai così tanti ordini</p>
                    
                    <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary" style="width:auto; padding: 0.6rem 1.5rem;">
                        Torna alla home
                    </a>
                </div>
            </div>
        <?php else: //lista vera e propria ?>
            <div id="orders-list"
                data-page="<?= $page ?>"
                data-limit="<?= $limit ?>"
                data-total="<?= $totale ?>"
            >

                <?php foreach ($orders as $ord): //dati di ogni ordine: id, stato, prodotti acquistati
                    $ordId    = (int) $ord['id'];
                    $stato    = (string) $ord['stato'];
                    $prodotti = $orderProducts[$ordId] ?? []; //sarà array di array associativi (dati prodotto)

                    //quindi crea scheda per ogni ordine
                ?>

                <div class="order-card mb-3"
                    style="background:var(--white); border:1px solid var(--lilac); border-radius:10px; overflow:hidden;">

                    <!-- testata ordine -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2"
                        style="border-bottom:1px solid var(--lilac); background:#faf8ff;">

                        <div> <!--  id e data / o nome cliente-->
                            <span class="fw-semibold" style="color:var(--indigo);">Ordine #<?= $ordId ?></span>
                            <span class="text-muted small ms-2">
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $ord['data_ordine']))) ?>
                            </span>

                            <?php if ($tipo === 'negoziante'): ?>
                                <span class="text-muted small ms-2">
                                    — cliente: <strong><?= htmlspecialchars((string) $ord['user_cliente']) ?></strong>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <!-- badge stato: label e colore da enums.php (ordine_stato_map) -->
                            <?php
                                $statoMap   = ordine_stato_map();
                                $statoEntry = $statoMap[$stato] ?? ordine_stato_fallback();
                            ?>
                            <span id = "badge-stato-btn<?= $ordId ?>" class="small fw-semibold px-2 py-1"
                                style="border-radius:20px; background:<?= $statoEntry['color'] ?>22;
                                        color:<?= $statoEntry['color'] ?>; border:1px solid <?= $statoEntry['color'] ?>44;">
                                <?= htmlspecialchars($statoEntry['icon'] . ' ' .$statoEntry['label']) ?>
                            </span>

                            <!-- totale -->
                            <span class="fw-bold" style="color:var(--indigo);">
                                <?= format_price((string) ($ord['totale'] /*?? '0'*/)) //totale_mio è sum (catalog.php metodo fetch_vendor_orders()) ?>
                            </span>

                            <!-- toggle dettaglio -->
                            <button class="btn-order-toggle" id = "dettagli-btn<?= $ordId ?>"
                                    data-order-id="<?= $ordId  //usato in js ma basta quello sopra TODO: aggiustare ?>"
                                    data-selezionato = <?php  
                                        if(isset($_SESSION['id_expand']) ){
                                            echo ((int)$_SESSION['id_expand'] ?? 0) === (int)$ordId ? "true" : "false";
                                        }
                                        else echo "false"?> 
                                    aria-expanded = "false"
                                    style="background:none; border:1px solid var(--lilac); border-radius:6px;
                                        padding:0.2rem 0.6rem; cursor:pointer; font-size:0.85rem; color:var(--indigo);">
                                Dettagli ▾
                            </button>
                        </div>
                    </div>

                    <!-- corpo ordine (nascosto di default) -->
                    <div class="order-detail" id="order-detail-<?= $ordId ?>" style="display:none; padding:0.75rem 1rem;">

                        <!-- prodotti -->
                        <?php foreach ($prodotti as $prod): //display dei dati, per ogni prodotto di questo ordine ?>
                        <div class="d-flex align-items-center gap-3 py-2"
                            style="border-bottom:1px solid var(--lilac);">

                            <!-- miniatura - TODO: crea classe-->
                            <div style="width:52px; height:52px; border-radius:6px; overflow:hidden;
                                        background:var(--lilac); flex-shrink:0;
                                        display:flex; align-items:center; justify-content:center;">
                                <?php if (!empty($prod['img'])): //c'è img?>
                                    <img src="<?= BASE_URL . '/' . ltrim((string) $prod['img'], '/') ?>"
                                        alt="" style="width:100%; height:100%; object-fit:cover;">
                                    
                                    <!-- errore nel caricare img -->
                                    <span style="display:none; font-size:1.4rem;">📦</span>
                                <?php else: ?>
                                    <span style="font-size:1.4rem;">📦</span>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow-1">
                                <?php if (!empty($prod['id_prodotto'])): ?>
                                    <a href="<?= BASE_URL ?>/prodotto.php?id=<?= (int) $prod['id_prodotto'] //link dettagli e nome?>"
                                    class="small fw-semibold text-decoration-none" style="color:var(--indigo);">
                                        <?= htmlspecialchars((string) $prod['nome']) ?>
                                    </a>
                                <?php else: //prodotto comprato, poi rimosso dal DB?>
                                    <span class="small text-muted"><?= htmlspecialchars((string) ($prod['nome'] ?? 'Prodotto rimosso')) ?></span>
                                <?php endif; ?>

                                <span class="text-muted small ms-1">× <?= (int) $prod['quantita'] ?></span>
                            </div>
                            <!-- quantità -->
                            <span class="small fw-semibold" style="color:var(--indigo); flex-shrink:0;">
                                <?= format_price((string) ((float) $prod['prezzo'] * (int) $prod['quantita'])) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>

                        <!-- indirizzo -->
                        <p class="small text-muted mt-2 mb-0">
                            📍 <?= htmlspecialchars((string) $ord['indirizzo_spedizione']) ?>
                        </p>

                        <!-- sezione annulla ordine (solo cliente, solo se ancora annullabile) -->
                        <?php //gestito in js (ajax) 
                        if ($tipo === 'cliente' && ($stato === 'non_spedito' || $stato === 'in_transito')): ?>
                            <div class="mt-3 pt-2" style="border-top:1px solid var(--lilac);">
                                <button class="btn-cancel-order"
                                        data-order-id="<?= $ordId //alt id='order'$ordId?>"
                                        style="background:none; border:1px solid var(--error); color:var(--error);
                                            border-radius:6px; padding:0.3rem 0.9rem; font-size:0.85rem; cursor:pointer;">
                                    Annulla ordine
                                </button>
                                <span class="cancel-msg small ms-2" style="display:none;"></span>
                            </div>
                        <?php endif; ?>

                        <!-- controllo /cambio stato (solo negoziante) -->
                        <?php //ordine in transito
                            if ($tipo === 'negoziante' && $stato !== 'consegnato' && $stato !== 'fallito' && $stato !== 'annullato'): ?>
                        
                            <div class="d-flex align-items-center gap-2 mt-3 pt-2"
                                style="border-top:1px solid var(--lilac);">
                                <label class="small text-muted mb-0" for="stato-<?= $ordId ?>">Aggiorna stato:</label>
                                <select id="stato-<?= $ordId ?>"
                                        class="stato-select"
                                        data-order-id="<?= $ordId ?>"
                                        style="border:1px solid var(--lilac); border-radius:6px;
                                            padding:0.3rem 0.6rem; font-size:0.85rem; cursor:pointer;">

                                    <?php if($stato != 'in_transito') :?>
                                        <option value="non_spedito" <?= $stato === 'non_spedito' ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statoMap['non_spedito']['icon'] . ' ' .$statoMap['non_spedito']['label']) ?>
                                        </option>
                                    <?php endif; ?>
                                    <option value="in_transito" <?= $stato === 'in_transito' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($statoMap['in_transito']['icon'] . ' ' .$statoMap['in_transito']['label']) ?>
                                    </option>
                                    <option value="consegnato">
                                        <?= htmlspecialchars($statoMap['consegnato']['icon'] . ' ' .$statoMap['consegnato']['label']) ?>
                                    </option>
                                    <option value="fallito">
                                        <?= htmlspecialchars($statoMap['fallito']['icon'] . ' ' .$statoMap['fallito']['label']) ?>
                                    </option>
                                </select>

                                <?php // bottone che aggiorna stato - fa partire chiamata ajax, gestita in ordini.js?>
                                <button class="btn-update-stato"
                                        data-order-id="<?= $ordId ?>"
                                        style="display: none; background:var(--indigo); color:#fff; border:none; border-radius:6px;
                                            padding:0.3rem 0.9rem; font-size:0.85rem; cursor:pointer;">
                                    Salva
                                </button>
                                <span class="stato-msg small" style="display:none;"></span>
                            </div>
                        <?php endif; ?>

                    </div><!-- /order-detail -->
                </div>
                <?php endforeach; //lo fa per ogni ordine?>
            </div><!-- /orders-list -->

            <!-- paginazione -->

            <!-- paginazione TODO: modulo-->
            <?php if ($totPagine > 1): //tasto indietro ?>
                <div id = "navigazione-ordini" class="row align-items-center mt-4">

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
                    <?php if($page < $totPagine) : //tasto avanti ?>
                        <a href="?p=<?= $page + 1 ?>" id="btn-next-page" class="btn btn-secondary"
                        style="width:auto; padding:0.45rem 1.1rem;">Successiva →</a>
                    <?php endif; ?>
                </div>
                </div>
            <?php endif; ?>
            <!-- paginazione TODO: fine modulo-->

        <?php endif; ?>
    <?php endif; ?>
</div>
</main>
<?php require APP_ROOT . '/includes/footer.php';
 if(isset($_SESSION['id_expand'])){
    $_SESSION['id_expand'] = null;
 }
?>

