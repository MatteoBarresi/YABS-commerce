<?php
/**
 * Contiene le mappe per i  valori enum del database (notifica, ordine).
 * Ogni funzione restituisce un array associativo: key è il valore enum, value sono le proprietà di presentazione (label, colore, icona, ecc.).
 *
 * 
 * Usato da:
 *   - ordini.php         (rendering badge stato)
 *   - notifiche.php      (rendering tipo notifica)
 *   - catalog.php        (notify_client_shipping — label nel testo notifica)
 *   - includes/head.php  (genera costanti JS via json_encode)
 */

/**
 * Mappa dei valori enum `ordine.stato`. - funzioni stato_label e stato_color  precedentemente contenute in ordini.php
 * @return array<string, array{label: string, color: string}>
 * 
 */
function ordine_stato_map(): array
{
    return [
        'non_spedito' => ['label' => 'In lavorazione', 'icon'=> '🕐', 'color' => '#a78bfa'],
        'in_transito' => ['label' => 'In transito',    'icon'=> '🚚', 'color' => '#60a5fa'],
        'consegnato'  => ['label' => 'Consegnato',     'icon'=> '✅', 'color' => '#34d399'],
        'fallito'     => ['label' => 'Fallito',        'icon'=> '❌', 'color' => '#f87171'],
        'annullato'   => ['label' => 'Annullato',      'icon'=> '🚫', 'color' => '#9ca3af'],
    ];
}

/**
 * Array associativo testo => [dati enum] in cui la key è l'enum e arr contiene dati display (label, icon, color, link)
 *
 * @return array<string, array{label: string, icon: string, color: string, link: string|null}>
 */
function notif_tipo_map(): array
{
    return [
        'acquisto' => [
            'label' => 'Nuovo ordine ricevuto',
            'icon'  => '🛍️',
            'color' => '#34d399',
            'link'  => null,
        ],
        'oos' => [
            'label' => 'Prodotto non più disponibile',
            'icon'  => '🚫',
            'color' => '#f87171',
            'link'  => '/carrello.php',
        ],
        'magazzino' => [
            'label' => 'Quantità non più disponibile',
            'icon'  => '📉',
            'color' => '#fb923c',
            'link'  => '/carrello.php',
        ],
        'aggiornamento_spedizione' => [
            'label' => 'Aggiornamento ordine',
            'icon'  => '🚚',
            'color' => '#60a5fa',
            'link'  => null,
        ],
        'recensione' => [
            'label' => 'Nuova recensione',
            'icon'  => '⭐',
            'color' => '#f59e0b',
            'link'  => null,
        ],
    ];
}

/**
 * Fallback per valori enum non previsti nella mappa.
 */
function ordine_stato_fallback(): array
{
    return ['label' => '—', 'color' => '#aaa'];
}

function notif_tipo_fallback(): array
{
    return ['label' => 'Notifica', 'icon' => '🔔', 'color' => '#aaa', 'link' => null];
}

/**
 * Costruisce il link di notifiche acquisto / aggiornamento_spedizione - era in catalog (notif_link)
 * dipende dal testo (per estrarre l'id ordine)
 */
function notif_build_link(string $tipo, string $testo): ?string
{
    $map   = notif_tipo_map();
    $entry = $map[$tipo] ?? null; //eccezione altrimenti

    if ($entry === null) {
        return null;
    }

    // link statico (oos, magazzino)
    if ($entry['link'] !== null) {
        return BASE_URL . $entry['link'];
    }

    // link dinamico: estrae l'id ordine dal testo della notifica
    if ($tipo === 'acquisto' || $tipo === 'aggiornamento_spedizione') {
        $orderId = extract_order_id_from_text($testo); // definita in catalog.php
        return $orderId !== null
            ? BASE_URL . '/ordini.php?expand=' . $orderId
            : BASE_URL . '/ordini.php';
    }

    // link dinamico: estrae l'id prodotto dal testo della notifica recensione
    if ($tipo === 'recensione') {
        if (preg_match('/prodotto\s*#(\d+)/i', $testo, $m)) { // '\s' per spazi; 'i' per case insensitive
            return BASE_URL . '/prodotto.php?id=' . (int) $m[1]; //subpattern, cioè id prodotto
        }
        return BASE_URL . '/home.php';
    }

    return null;
}
