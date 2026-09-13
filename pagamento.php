<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';
require_login();

//solo cliente può pagare
if (($_SESSION['tipo_utente'] ?? '') !== 'cliente') {
    redirect('/home.php');
}

$pageTitle  = 'Pagamento';
$withNavbar = true;
$extraJs    = ['assets/js/pagamento.js'];

$username = $_SESSION['username'];
$error    = flash_error();

$items = []; //dati prodotti nel carrello
$cards = []; //carte di credito utente
$total = 0.0;
$savedAddress = ''; //indirizzo utente

try {
    $pdo          = getConnection();
    $items        = fetch_cart_items($pdo, $username);  //dati prodotti che ha nel carrello (+ disponibilità, venditore)
    $total        = calc_cart_total($items);            //passa risultato precedente per calcolare totale
    $cards        = fetch_user_cards($pdo, $username);

    // indirizzo salvato sul profilo
    $stmtAddr = $pdo->prepare('SELECT indirizzo FROM utente WHERE username = :u LIMIT 1');
    $stmtAddr->execute([':u' => $username]);
    $savedAddress = (string) ($stmtAddr->fetchColumn() ?? '');
} catch (Throwable $e) {
    redirect('/db_error.php');
}

// Se si procede al pagamento, mostriamo solo prodotti acquistabili
$activeItems = array_filter($items, fn($it) => $it['user_negoziante'] !== null);
if (count($activeItems) === 0 && $error === null) { //tutti quelli nel carrello sono stati rimossi dal venditore ma nessun errore 
    redirect('/carrello.php', 'Il carrello non contiene prodotti disponibili.'); 
}

require APP_ROOT . '/includes/head.php';
?>

<main class="main-catalog">
    <div class="container py-4" style="max-width: 740px;">

        <h1 class="h3 mb-4" style="color: var(--indigo);">💳 Pagamento</h1>

        <?php if ($error): //parte flash error?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <form id="form-pagamento" method="post" action="<?= BASE_URL ?>/pagamento_process.php" novalidate>

            <!-- RIEPILOGO ORDINE -->
            <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
                <div class="card-body">
                    <h2 class="h6 mb-3" style="color:var(--indigo);">Riepilogo ordine</h2>

                    <?php foreach ($activeItems as $item): //dati di ogni prodotto acquistabile?>
                        <div class="d-flex justify-content-between align-items-center py-2"
                             style="border-bottom:1px solid var(--lilac);">
                            
                            <!-- nome e quantità -->
                            <span class="small">
                                <?= htmlspecialchars((string) $item['nome']) ?>
                                <span class="text-muted">× <?= (int) $item['quantita'] ?></span>
                            </span>
                            
                            <!-- prezzo -->
                            <span class="small fw-semibold">
                                <?= format_price((string) ((float) $item['prezzo'] * (int) $item['quantita'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>

                    <!-- totale --> 
                    <div class="d-flex justify-content-between align-items-center pt-3">
                        <span class="fw-bold">Totale</span>
                        <span class="fw-bold" style="color:var(--indigo); font-size:1.15rem;">
                            <?= format_price((string) $total) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- INDIRIZZO DI SPEDIZIONE 
            rivedi stile 
            -->
            <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
                <div class="card-body">
                    <h2 class="h6 mb-3" style="color:var(--indigo);">Indirizzo di spedizione</h2>
                    <div class="form-group mb-0">
                        <label for="indirizzo_spedizione">Indirizzo *</label>
                        <input type="text"
                               id="indirizzo_spedizione"
                               name="indirizzo_spedizione"
                               maxlength="50"
                               required
                               value="<?= htmlspecialchars($savedAddress) ?>"
                               placeholder="Via Roma 1, Milano">
                    </div>
                </div>
            </div>

            <!-- METODO DI PAGAMENTO -->
            <div class="card border-0 mb-4" style="border:1px solid var(--lilac)!important; border-radius:10px;">
                <div class="card-body">
                    <h2 class="h6 mb-3" style="color:var(--indigo);">Metodo di pagamento</h2>

                    <p class="small text-muted mb-3">Usa una carta salvata o inseriscine una nuova.</p>
                    
                    <!-- selezione carta -->
                    <div class="mb-3">
                        <?php if (count($cards) > 0): ?>
                            <?php foreach ($cards as $card): //mostra dati di ogni carta e si può selezionare una ?>
                                <?php $cardExpired = new DateTime((string) $card['data_scadenza']) <= new DateTime(); //controllo scadenza, mancava del tutto prima ?>
                                <!-- Carte salvate -->
                                <label class="d-flex align-items-center gap-2 p-2 mb-2"
                                       style="border:1px solid var(--lilac); border-radius:8px;
                                              cursor:<?= $cardExpired ? 'not-allowed' : 'pointer' ?>;
                                              opacity:<?= $cardExpired ? '0.55' : '1' ?>;">
                                    
                                    <input type="radio" name="id_carta" value="<?= (int) $card['id'] ?>"
                                           class="carta-salvata-radio"
                                           <?= $cardExpired ? 'disabled' : '' ?>>
                                    
                                    <!-- dati carta -->
                                    <span class="small">
                                        💳 **** <?= htmlspecialchars(substr((string) $card['numero'], -4)) //mostra ultime 4 cifre carta?>
                                        &nbsp;—&nbsp;
                                        <?= htmlspecialchars((string) $card['nome_intestatario']) ?>
                                        <?= htmlspecialchars((string) $card['cognome_intestatario']) ?>
                                        &nbsp;(scad. <?= htmlspecialchars((string) $card['data_scadenza']) //questo va inserito dall'utente al massimo ?>)
                                        <?php if ($cardExpired): ?>
                                            <strong style="color:var(--error);">— scaduta</strong>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <!-- cvv per la carta salvata selezionata: non memorizzato nel db (solo regole jquery) -->
                            <div class="form-group" id="cvv-carta-salvata-field" style="max-width:120px;">
                                <label for="cvv_carta_salvata">CVV *</label>
                                <input type="text" id="cvv_carta_salvata" name="cvv_carta_salvata"
                                       maxlength="4" placeholder="123"
                                       autocomplete="cc-csc">
                            </div>
                            
                        <?php else: ?>
                            <input type="hidden" name="id_carta" value="nuova">
                        <?php endif; ?>
                            
                            <!-- nuova carta -->
                            <label class="d-flex align-items-center gap-2 p-2 mb-0"
                                    style="border:1px solid var(--lilac); border-radius:8px; cursor:pointer;">
                                <input type="radio" name="id_carta" value="nuova" id="radio-nuova" checked>
                                <span class="small">➕ Inserisci nuova carta</span>
                            </label>
                    </div>
                    <!-- Campi nuova carta -->
                    <div id="nuova-carta-fields">
                        <div class="form-group">
                            <label for="numero_carta">Numero carta *</label>
                            <!-- devono essere in gruppi di 4 separati da spazio -->
                            <input type="text"
                                   id="numero_carta"
                                   name="numero_carta"
                                   maxlength="19"
                                   placeholder="1234 5678 9012 3456"
                                   autocomplete="cc-number">
                        </div>
                        <div class="d-flex gap-3">
                            <div class="form-group flex-grow-1">
                                <label for="nome_intestatario">Nome *</label>
                                <input type="text" id="nome_intestatario" name="nome_intestatario"
                                       maxlength="20" autocomplete="cc-name">
                            </div>
                            <div class="form-group flex-grow-1">
                                <label for="cognome_intestatario">Cognome *</label>
                                <input type="text" id="cognome_intestatario" name="cognome_intestatario"
                                       maxlength="20">
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="form-group flex-grow-1">
                                <label for="data_scadenza">Scadenza (AAAA-MM-GG) *</label>
                                <input type="date" id="data_scadenza" name="data_scadenza"
                                       autocomplete="cc-exp">
                            </div>
                            <div class="form-group" style="width:120px;">
                                <label for="cvv">CVV *</label>
                                <!-- mantenere 3 come maxlength -->
                                <input type="text" id="cvv" name="cvv"
                                       maxlength="4" placeholder="123"
                                       autocomplete="cc-csc">
                            </div>
                        </div>

                        <!-- opzione per salvare nel DB -->
                        <div class="form-group mb-0">
                            <label class="d-flex align-items-center gap-2" style="font-weight:normal; cursor:pointer;">
                                <input type="checkbox" id="salva_carta" name="salva_carta" value="1">
                                Salva questa carta per i prossimi acquisti
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            <!-- BOTTONE CONFERMA -->
            <div class="text-end">
                <a href="<?= BASE_URL ?>/carrello.php" class="btn btn-secondary"
                   style="width:auto; padding:0.6rem 1.25rem; margin-right:0.5rem;">← Torna al carrello</a>
                <button type="submit" id="btn-conferma" class="btn btn-primary"
                        style="width:auto; padding:0.7rem 2rem;">
                    Conferma ordine
                </button>
            </div>

        </form>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
