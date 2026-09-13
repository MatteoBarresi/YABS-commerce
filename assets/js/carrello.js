/**
 * carrello.js — gestione quantità e rimozione prodotti via AJAX.
 * usato in carrello.php
 */
$(function () {
    //var baseUrl = $('body').data('base-url') || ''; //parent di carrello.php - settato in head.php, indica dir genitore di carrello.php
    /* ---------- helpers ---------- */

    //ci sono diverse row (crea entry carrello con un foreach) quindi TODO: usare id generati proceduralmente e non class
    function setRowError($row, msg) { //parte info prodotto di carrello.php 
        $row.find('.cart-row-error').text(msg).show();
    }

    function clearRowError($row) {
        $row.find('.cart-row-error').hide().text('');
    }

    /* 
        chiamata quando si preme btn per aggiornare qta
        disabilita i btn finché non finisce le operazioni, poi viene richiamata per abilitarli
    */
    function setRowLoading($row, loading) {
        $row.find('.btn-qty, .btn-remove').prop('disabled', loading);
        $row.find('.qty-input').prop('disabled', loading);
    }

    //aggiorna quantità mostrata (prodotti nel carrello) - si trova in navbar.php
    function updateNavBadge(count) {
        //seleziona elementi nav-badge che siano dentro nav-cart, che non siano anche -alert (quindi lo span mostrato se ci sono cose nel carrello - vedi navbar.php)
        //TODO: id generato
        var $badge = $('.nav-cart .nav-badge').not('.nav-badge-alert'); //seleziona numero di prodotti
        if (count > 0) { 
            if ($badge.length) { 
                $badge.text(count); 
            } else {
                $('.nav-cart').append('<span class="nav-badge">' + count + '</span>'); //inserisce alla fine
            }
        } else { //TODO: mostrare 0 invece di rimuovere numero ?
            $badge.remove(); 
        }
    }

    //mostra/nasconde div in base alla presenza di prodotti nel carrello
    function showEmptyCart() {
        $('#cart-table-wrap').hide();
        $('#cart-empty').show();
        // rimuovi badge carrello dalla navbar
        updateNavBadge(0);
        $('.nav-badge-alert').remove();
    }

    /* ---------- aggiorna quantità ---------- */

    function doUpdateQty($row, newQty) {
        //rimuove eventuali messaggi di errore precedenti e disabilita btn
        clearRowError($row);
        setRowLoading($row, true);

        var productId = $row.data('product-id');

        //richiesta ajax a carrello_handler
        $.post(baseUrl + '/carrello_handler.php', {
            action:      'update_qty',
            id_prodotto: productId,
            quantita:    newQty
        })
        .done(function (data) {
            if (data.error) { //non riesce ad aggiornare - uno dei branch restituisce un oggetto solo con questa key
                
                setRowError($row, data.error);
                // ripristina valore precedente nel campo - (attributo impostato alla fine di questo script)
                $row.find('.qty-input').val($row.data('qty-prev'));
            } else {
                // aggiorna subtotale riga e totale generale
                $row.find('.cart-subtotal').text(data.subtotal); //aggiorna subtotale di quel prodotto
                $('#cart-total').text(data.total); //aggiorna totale
                $row.data('qty-prev', newQty); //aggiorna text input con quantità
                updateNavBadge(data.cart_count); //aggiorna numero di prodotti nel carrello
            }
        })
        .fail(function () { //mostra errore e torna al valore precedente
            setRowError($row, 'Errore di rete. Riprova.'); 
            $row.find('.qty-input').val($row.data('qty-prev')); 
        })
        .always(function () { //in ogni caso riabilita i btn
            setRowLoading($row, false);
        });
    }

    // pulsanti + e -
    /* 
        impostano testo del campo input
        ogni volta che si preme su un btn, parte la richiesta ajax per aggiornare dati db 
        TODO: magari metto un btn update che fa l'update una volta sola...
    */
    $(document).on('click', '.btn-qty', function () {
        var $btn  = $(this); //riferimento al tag html - riferimento all'elemento dom cliccato (+/- per ogni riga)
        var $row  = $btn.closest('.cart-row'); //ancestor di questo tipo più vicino = div che contiene questa riga
        var $inp  = $row.find('.qty-input'); //testo vicino al btn
        var curr  = parseInt($inp.val(), 10) || 1; //or 1 per impedire 0 - TODO: trovare altro modo es controlli jquery vaildator o funzione js
        var max   = parseInt($inp.attr('max'), 10) || 9999;
        var delta = $btn.data('delta');          // 1 o -1
        var next  = curr + delta;

        //if (next < 1 || next > max) { return; } //non fa triggerare errori e non fa generare feedback

        $inp.val(next); //cambia testo
        $row.data('qty-prev', curr); //aggiorna attributo
        doUpdateQty($row, next);
    });

    // modifica diretta nel campo - fa la stessa cosa di sopra
    $(document).on('change', '.qty-input', function () {
        var $inp = $(this);     //input di questa riga
        var $row = $inp.closest('.cart-row'); 
        var val  = parseInt($inp.val(), 10); //valore inserito dall'utente
        var max  = parseInt($inp.attr('max'), 10) || 9999;

        //controllo valori non validi
        if (isNaN(val) || val < 1) {
            $inp.val($row.data('qty-prev') || 1); //reimposta precedente
            return;
        }
        if (val > max) {
            $inp.val(max);
            val = max;
        }

        var prev = $row.data('qty-prev') || val;
        $row.data('qty-prev', prev); //imposta att
        doUpdateQty($row, val); //richiesta ajax 
    });

    /* ---------- rimozione prodotto ---------- */

    $(document).on('click', '.btn-remove', function () {
        var $btn = $(this);
        var $row = $btn.closest('.cart-row');
        //toglie errori + disattiva btn
        clearRowError($row);
        setRowLoading($row, true);

        var productId = $row.data('product-id');

        //chiamata ajax
        $.post(baseUrl + '/carrello_handler.php', {
            action:      'remove_item',
            id_prodotto: productId
        })
        .done(function (data) {
            if (data.error) { //triggerato nel try-catch
                setRowError($row, data.error);
                setRowLoading($row, false); //abilita btn - TODO: usare always come sopra
            } else {

                //fadeOut(ms) cambia opacità dell'elemento from visible to hidden.
                $row.fadeOut(250, function () { 
                    $(this).remove(); //rimuove elemento DOM
                    //aggiorna totale e numero di prodotti in carrello
                    $('#cart-total').text(data.total); 
                    updateNavBadge(data.cart_count);
                    if (data.empty) { //eliminato ultimo elemento
                        showEmptyCart();
                    }
                });
            }
        })
        .fail(function () {
            setRowError($row, 'Errore di rete. Riprova.');
            setRowLoading($row, false);
        });
    });

    /* ---------- salva qty-prev iniziale per ogni entry del carrello ---------- */
    $('.cart-row').each(function () {
        var $row = $(this);
        var qty  = parseInt($row.find('.qty-input').val(), 10) || 1;
        $row.data('qty-prev', qty); //imposta attributo
    });
});
