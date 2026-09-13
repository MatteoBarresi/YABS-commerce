/**
 * venditore.js — toggle form, anteprima immagini, eliminazione prodotto AJAX, validazione.
 */
$(function () {
   // var baseUrl = $('body').data('base-url') || '';

    /* -------- toggle form nuovo prodotto -------- 
    
    */
    //TODO: come notifiche.js - fare generica in head tipo (che prende path)
    function nessunProdotto(){
        $("#products-list").load(baseUrl + "/includes/no-prodotto.php");
        //TODO: togliere anche bottoni navigazione??? - $('#navigazione-pagine').hide();
    }

    
    //btn mostrato se si inserisce 
    $('#btn-form-toggle').on('click', function () {
        $('#form-prodotto').slideDown(150); //mostra form inserimento prodotto
        $(this).hide();
        $('#btn-form-cancel').show();
    });

    //quando si clicca sul bottone annulla (mostrato solo se si inserisce un prodotto)
    $('#btn-form-cancel').on('click', function () {
        $('#form-prodotto').slideUp(150); //nasconde form
        $('#btn-form-toggle').show(); //btn per espandere form
        $('#img-preview').empty(); //rimuove figli
        $('#form-prodotto')[0].reset(); //[0] perché ogni oggetto jquery è un array di dom elements (e reset non è un metodo jquery ma vanilla js = metodi del dom) - porta a default gli input della form
    });

    /* -------- anteprima immagini 
        input in cui mettere immagini
    -------- */
    function mostraAnteprima(e) {

        $('#img-preview').append( //div con miniature
            $('<img>')
                .attr('src', e.target.result) //imposta come src il file letto (non il percorso, ma l'immagine convertita) 
                .css({
                    width: '70px',
                    height: '70px',
                    objectFit: 'cover',
                    borderRadius: '6px',
                    border: '1px solid var(--lilac)'
                })
        );

    }
    //input in cui mettere immagini
    $('#immagini').on('change', function () { //quando si aggiungono immagini (si riferisce all'input type file)
        var $preview = $('#img-preview'); 
        $preview.empty(); //svuota div che mostra anteprima immagini

        var files = this.files; //files property di un tag element, restituisce un FileList (ogni elemento è un File)
        
        var max   = Math.min(files.length, 5); //ignora altre dopo la quinta inserita nell'input

        //---------------- legge ogni file --------------------------
        for (let i = 0; i < max; i++) {
            
            const file = files[i];
            const reader = new FileReader();

            reader.onload = mostraAnteprima;

            reader.readAsDataURL(file);
       }
    });

    /* -------- eliminazione prodotto AJAX -------- */
    $(document).on('click', '.btn-delete-product', function () {
        var $btn      = $(this);
        var productId = $btn.data('product-id'); //trovare alternativa
        var nome      = $btn.data('nome');
        var $list     = $('#products-list');

        // legge parametri di paginazione stampati dal PHP nei data-* del container
        var page      = parseInt($list.data('page'),  10) || 1;
        var limit     = parseInt($list.data('limit'), 10) || 10;
        var total     = parseInt($list.data('total'), 10);

        if (!confirm('Eliminare "' + nome + '"? L\'operazione è irreversibile.')) {
            return;
        }

        $btn.prop('disabled', true); //disattiva btn

        //chiamata ajax
        $.post(baseUrl + '/venditore_delete_handler.php', {
            action:      'delete_product',
            id_prodotto: productId,
            page:        page,
            limit:       limit
        })
        .done(function (data) { //restituisce errore o ok=>true
            if (data.error) {
                alert(data.error); 
                $btn.prop('disabled', false);
                return;
            }
                
            //elimina riga del prodotto eliminato - TODO: migliorare ricerca elementi (es con id)
            var $row = $btn.closest('.prod-row'/*'.d-flex.align-items-center.gap-3.mb-2'*/);
            $row.fadeOut(250, function () { //sfuma e dopo esegue callback
                $(this).remove(); 


                $list.data('total', data.remaining);

                if (data.remaining === 0) {
                    nessunProdotto();
                    //location.reload(); 
                    return;
                }

                //aggiorna pagina x di y
                if(data.remaining % limit == 0){ //ogni volta che succede
                    let ultima = (total > 0) ? Math.ceil(total/limit) -1 : 1;
                    $('#progresso').html("Pagina " + page + " di "+ ultima);
                }               
                

                if (data.had_next_page) {
                    // preleva il primo prodotto della pagina successiva
                    $.get(baseUrl + '/venditore_vendi.php?p=' + (page /*+ 1*/), function (html) {
                        var $nextPageFirstRow = $(html).find('#products-list .prod-row:last');
                        if ($nextPageFirstRow.length) {
                            $list.append($nextPageFirstRow);
                        }
                    });
                    $('#btn-next-page-products').html("");//.hide();
                }

                //caso in cui elimino tutte le rows di questa pagina ma ci sono pagine precedenti (es sono su pag 2 ed elimino tutto --> passo a pag 1)
                if(data.rows_in_page == 0){
                    if(data.page > 1){
                        //vai indietro di una pagina
                        window.location.replace(baseUrl + '/venditore_vendi.php?p=' + (page - 1)); 
                        return;
                    }else{ //finito tutto
                        nessunProdotto();
                        return;
                    }
                }
            });
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            
            alert('Errore di rete. Riprova.'+
                jqXHR.responseText + " errore numero: " + jqXHR.status 
            );
            $btn.prop('disabled', false);
        });
    });

    /* -------- validazione --------*/
    $('#form-prodotto').validate({
        rules: {
            nome:          { required: true, maxlength: 50 },
            prezzo:        { required: true, min: 1, max: 9999.99, number: true }, //TODO: fatto anche in venditore_vendi.php - controlla che siano uguali minmax
            disponibilita: { required: true, min: 0, digits: true },
            descrizione:   { maxlength: 200 }
        },
        messages: {
            nome:   { required: 'Il nome del prodotto è obbligatorio.' },
            prezzo: { required: 'Inserisci un prezzo.', min: 'Il prezzo deve essere almeno 1 euro.' },
            disponibilita: { required: 'Inserisci la disponibilità.', min: 'La disponibilità non può essere negativa.' }
        },
        errorPlacement: function (error, element) { //dove piazzare label dell'errore, prende errorlabel e invalid element
            error.addClass('small').css('color', 'var(--error)');
            error.insertAfter(element);
        }
    });
});
