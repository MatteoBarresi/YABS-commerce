/**
 * ordini.js — toggle dettagli ordine e aggiornamento stato (negoziante), annullamento (cliente).
 * ORDINE_STATO_MAP è la costante che contiene Label e colori degli stati (dichiarata in head.php) 
 */

$(function () {
    //var baseUrl = $('body').data('base-url') || '';
    
    //TODO: come notifiche.js - fare generica in head tipo (che prende path)
    //non serve
    /*
    function nessunOrdine(){
        $("#orders-list").load(baseUrl + "/includes/no-ordini.php");
        //TODO: togliere anche bottoni navigazione??? - $('#navigazione-pagine').hide();
    }    
    */
    

    /* -------- toggle dettagli -------- */

    //per elementi creati dinamicamente (ajax) - che non dovrebbe mai succedere
    //quando c'è un clic su "dettaglio"
    $(document).on('click', '.btn-order-toggle', function () { 
        var $btn    = $(this);
        var orderId = $btn.data('order-id');
        var $detail = $('#order-detail-' + orderId); //div con dati ordine (prodotti, miniatura, qta, stato)
        var open    = $btn.attr('aria-expanded') === 'true';

        if (open) {
            $detail.slideUp(150); //hide con animazione
            $btn.attr('aria-expanded', 'false').text('Dettagli ▾');
        } else {
            $detail.slideDown(150); //show con animazione
            $btn.attr('aria-expanded', 'true').text('Dettagli ▴');
        }
    });

    /* -------- espande automaticamente un ordine se si arriva da un link (?expand=N) -------- */    
    var $targetBtn = $('.btn-order-toggle[data-selezionato="true"]');
    if ($targetBtn.length) {
        $targetBtn.trigger('click');

        // scrolla l'ordine in vista
        var $targetCard = $targetBtn.closest('.order-card');
        if ($targetCard.length) {
            $('html, body').animate({ scrollTop: $targetCard.offset().top - 80 }, 300); //offset restituisce coordinate, sottraggo 80 pixel da top
        }
    }

    
    /* -------- view negoziante - mostra btn salva (cambio stato ordine) -------- */
    $(document).on('change', '.stato-select', function(){
        $(this).siblings('.btn-update-stato').show();
    });

    /* -------- aggiornamento stato ordine (lo può fare solo il negoziante) -------- */
    //click su salva
    $(document).on('click', '.btn-update-stato', function () {
        var $btn     = $(this);
        var orderId  = $btn.data('order-id');
        var $select  = $('#stato-' + orderId);
        var newStato = $select.val();
        var $msg     = $btn.closest('div').find('.stato-msg'); //span sibling in cui mostra risposta del server (errore o success)

        $btn.prop('disabled', true); //imposta questa proprietà - non funziona fino alla fine delle operazioni
        $msg.hide().text('');

        //chiamata ajax - passa i dati per aggiornare lo stato e inviare notifica
        $.post(baseUrl + '/ordini_handler.php', {
            action:     'update_stato',
            id_ordine:  orderId,
            stato:      newStato
        })
        .done(function (data) { //risposta server: errore OR nuovo stato
            if (data.error) {
                $msg.text(data.error)
                    .css('color', 'var(--error)')
                    .show();
                $btn.prop('disabled', false);
                if(data.old){
                    $('#stato-'+orderId).val(data.old); //imposta vecchio stato (options)
                }
                return;
            }

            // aggiorna badge stato nella testata
            var $card  = $btn.closest('.order-card'); //div contenitore per ogni ordine - meglio usare id costruiti 
            var $badge = $card.find('.small.fw-semibold.px-2'); //span con badge stato - anche qui meglio usare id
            var entry  = ORDINE_STATO_MAP[newStato] || { icon : '', label: newStato, color: '#aaa' };
            var color  = entry.color; //nuovo colore in base allo stato impostato
            
            //imposta contenuto 
            $badge.text(entry.icon + ' ' + entry.label)
                  .css({
                      'background': color + '22', //concatena opacity
                      'color':      color,
                      'border':     '1px solid ' + color + '44'
                  });

            $msg.text('Stato aggiornato.')
                .css('color', 'var(--success, #34d399)')
                .show();

            // stato irreversibile
            if (newStato === 'consegnato' || newStato === 'fallito') {
                $btn.closest('.d-flex').fadeOut(400, function () { $(this).remove(); }); //dopo il fadeout rimuove div aggiorna-stato
            } else {
                $btn.prop('disabled', false); //attiva btn
                setTimeout(function () { $msg.fadeOut(300); }, 2000); //dopo 2s nasconde messaggio
            }
        })
        .fail(function () {
            $msg.text('Errore di rete. Riprova.')
                .css('color', 'var(--error)')
                .show();
            $btn.prop('disabled', false);
        });
    });

    /* -------- annullamento ordine (lo può fare solo il cliente proprietario) -------- */
    $(document).on('click', '.btn-cancel-order', function () {
        var $btn    = $(this);
        var orderId = $btn.data('order-id'); //passare diversamente
        var $msg    = $btn.closest('div').find('.cancel-msg'); //usare id - è lo span fratello di btn
        var $list   = $('#orders-list');
        
        var page    = parseInt($list.data('page'),  10) || 1;
        var limit   = parseInt($list.data('limit'), 10) || 10;
        var total   = parseInt($list.data('total'), 10);

        if (!confirm('Sei sicuro di voler annullare questo ordine?')) { //evitare alert
            return;
        }

        $btn.prop('disabled', true); 
        $msg.hide().text('');

        $.post(baseUrl + '/ordini_handler.php', {
            action:    'cancel_order',
            id_ordine: orderId
        })
        .done(function (data) {
            if (data.error) { //non supera i controlli
                $msg.text(data.error)
                    .css('color', 'var(--error)')
                    .show();
                $btn.prop('disabled', false);
                return;
            }

            //chiudo row
            $('#dettagli-btn'+orderId).trigger('click');

            //aggiorno badge
            $('#badge-stato-btn'+orderId)
                .text(ORDINE_STATO_MAP['annullato']['icon'] + " " + ORDINE_STATO_MAP['annullato']['label'])
                .css({'border-radius': '20px', //necessario?
                    'background': ORDINE_STATO_MAP['annullato']['color']+"22",
                    'color': ORDINE_STATO_MAP['annullato']['color'],
                    'border': "1px solid " + ORDINE_STATO_MAP['annullato']['color']+"44"
                });

            //elimino tasto annulla ordine
            $btn.closest('div').fadeOut();
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            //console.log("status: " + jqXHR.status + " " + "status text: " + jqXHR.statusText + " " + textStatus + " ");
            //console.log("errore: " + errorThrown);
            $msg.text('Errore di rete. Riprova.')
                .css('color', 'var(--error)')
                .show();
            $btn.prop('disabled', false);
        });
    });
});
