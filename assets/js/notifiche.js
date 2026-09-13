/**
 * notifiche.js — eliminazione singola e massiva via AJAX.
 */
$(function () {
    //var baseUrl = $('body').data('base-url') || '';


    function nessunaNotifica(){
        $('#btn-clear-all').html("");//.hide(); //provare display:none al posto di vuoto
        $("#notifiche-list").load(baseUrl + "/includes/no-notifiche.php");
        //TODO: togliere anche bottoni navigazione??? - $('#navigazione-pagine').hide();
    }

    /* -------- elimina singola notifica -------- */
    $(document).on('click', '.btn-delete-notif', function () {
        var $btn    = $(this);
        var notifId = $btn.attr('id').split("-")[1];  //$btn.data('notif-id'); - usato id
        var $row    = $('#notif-' + notifId);
        var $list   = $('#notifiche-list');

        // legge parametri di paginazione stampati dal PHP nei data-* del container
        var page  = parseInt($list.data('page'),  10);
        var limit = parseInt($list.data('limit'), 10);
        var total = parseInt($list.data('total'), 10);
        $btn.prop('disabled', true);

        //chiamata ajax per eliminazione di una notifica
        $.post(baseUrl + '/notifiche_handler.php', { 
            action:      'delete_one',
            id_notifica: notifId,
            page:        page,
            limit:       limit
        })
        .done(function (data) { //error oppure notifiche rimanenti
            if (data.error) {
                $btn.prop('disabled', false);
                return;

            }

            //elimina div
            $row.fadeOut(200, function () {
                $(this).remove();                   //equivale a $row.remove()
                $list.data('total', data.totale);   // aggiorna il totale nel data attribute (per le eliminazioni successive)


                if (data.totale === 0) { //questo utente non ha più notifiche                
                    nessunaNotifica();
                    return;
                }

                //aggiornare pagina x di y
                if(data.totale % limit == 0){ //ogni volta che succede
                    let ultima = (total > 0) ? Math.ceil(total/limit) -1 : 1;
                    $('#progresso').html("Pagina " + page + " di "+ ultima);
                }

                //prende la prima notifica dalla prossima pagina (se esiste) e la aggiunge in fondo al div #notifiche-list
                if (data.had_next_page ) {
                    
                    //visto che un nuovo accesso a questa pagina calcola dati con nuovo stato db, prendo quello che sarebbe l'ultimo
                    $.get(baseUrl + '/notifiche.php?p=' + (page), function (html) {
                        var $nextPageFirstRow = $(html).find('#notifiche-list .notif-row:last');
                        if ($nextPageFirstRow.length) {
                            $list.append($nextPageFirstRow);
                        }
                    });
                    $('#btn-next-page').html("");//.hide(); //non faccio hide, altrimenti sballa aspetto
                    //possibile anche $('#navigazione-pagine').hide();
                }
                //caso in cui elimino tutte le rows di questa pagina ma ci sono pagine precedenti (es sono su pag 2 ed elimino tutto --> passo a pag 1)
                if(data.rows_in_page == 0){
                    if(data.page > 1){
                        //vai indietro di una pagina
                        window.location.replace(baseUrl + '/notifiche.php?p=' + (page - 1)); // no ajax
                        return;
                    }else{ //finito tutto
                        nessunaNotifica();
                        return;
                    }
                }

            });
        })
        .fail(function () {
            $btn.prop('disabled', false);
        });
    });

    /* -------- elimina tutte -------- */
    $('#btn-clear-all').on('click', function () {
        var $btn = $(this);
        if (!confirm('Eliminare tutte le notifiche?')) { return; } 

        $btn.prop('disabled', true);

        $.post(baseUrl + '/notifiche_handler.php', { action: 'delete_all' })
        .done(function (data) {
            if (data.ok) {
                nessunaNotifica();
            
            } else { //TODO: altro feedback? 
                $btn.prop('disabled', false);
            }
        })
        .fail(function () { //TODO: altro feedback? 
            $btn.prop('disabled', false);
        });
    });
});
