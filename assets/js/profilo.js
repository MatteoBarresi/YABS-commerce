/**
 * profilo.js — toggle sezioni, rimozione carta AJAX, formattazione input.
 */
$(function () {
    //var baseUrl = $('body').data('base-url') || '';

    /* -------- toggle generico mostra/nascondi form -------- */
    function makeToggle(btnOpen, btnCancel, formSel, viewSel) { //bottoni antagonisti per mostrare form
        $(btnOpen).on('click', function () {
            $(formSel).slideDown(150); //mostra form
            if (viewSel) 
                $(viewSel).hide(); //usata solo per dati utente se si modificano
            $(btnOpen).hide();
        });
        $(btnCancel).on('click', function () { //reverse azioni di sopra
            $(formSel).slideUp(150);
            if (viewSel) $(viewSel).show();
            $(btnOpen).show();
        });
    }

    makeToggle('#btn-edit-toggle',    '#btn-edit-cancel',    '#form-profilo',   '#profile-view');   //btn modifica dati utente 
    makeToggle('#btn-pwd-toggle',     '#btn-pwd-cancel',     '#form-password',  null);              //btn modifica psw
    makeToggle('#btn-addcard-toggle', '#btn-addcard-cancel', '#form-addcard',   null);              //btn modifica carte

    // zona pericolosa
    $('#btn-delete-toggle').on('click', function () { //btn elimina account
        $('#delete-confirm').slideDown(150); //div con form di conferma
        $(this).hide();
    });

    $('#btn-delete-cancel').on('click', function () { //annulla eliminazione account
        $('#delete-confirm').slideUp(150);
        $('#btn-delete-toggle').show();
    });

    /* -------- formattazione numero carta TODO: preso da pagamento.js -------- */
    $('#card_numero').on('input', function () {
        var v = $(this).val().replace(/\D/g, '').substring(0, 16);  //sostituisce quello che non è cifra (tutte le occorrenze)
        var f = v.match(/.{1,4}/g);                                 //restituisce array con match - perché vengono divisi in 4 gruppi separati da spazi
        $(this).val(f ? f.join(' ') : v);                           //unisce array - finche non ci sono 16 cifre, lascia input iniziale
    });

    $('#card_cvv').on('input', function () {
        $(this).val($(this).val().replace(/\D/g, '').substring(0, 4)); //toglie non cifre
    });

    /* -------- rimozione carta AJAX -------- */
    $(document).on('click', '.btn-remove-card', function () { //bottone apposito
        var $btn    = $(this);
        var cardId  = $btn.data('card-id'); 
        var $row    = $('#carta-' + cardId); //id costruito

        $btn.prop('disabled', true); //disattiva btn

        $.post(baseUrl + '/profilo_card_handler.php', {
            action:  'remove_card',
            id_carta: cardId
        })
        .done(function (data) { //risposta: stringa errore oppure true
            if (data.error) { //mostra errore e riabilita bottone
                alert(data.error);
                $btn.prop('disabled', false);
            } else {//elimina div con dati di quella carta
                $row.fadeOut(200, function () {
                    $(this).remove();
                    if ($('.carta-row').length === 0) { //non ci sono più carte per questo utente (classe presente in tutti i row)
                        $('#cards-list').append( //div contenitore lista carte
                            '<p class="text-muted small mb-0" id="no-cards-msg">Nessuna carta salvata.</p>'
                        );
                    }
                });
            }
        })
        .fail(function () {
            alert('Errore di rete. Riprova.');
            $btn.prop('disabled', false);
        });
    });

    /* -------- validazione jQuery Validator TODO: stessi controlli fatti anche in pagamento.js -------- */

    //applicato al numero carta
    $.validator.addMethod('cardNumber', function (v) {  
        return /^\d{4}(\s\d{4}){3}$/.test(v.trim()); // dddd dddd dddd dddd - solo cifre
    }, 'Numero carta non valido (16 cifre).');

    //scadenza nel futuro
    $.validator.addMethod('futureDate', function (v) {
        if (!v) { return false; } //vuota, null
        return new Date(v) > new Date(); 
    }, 'La carta è scaduta o la data non è valida.');

    /* 
        check per submit vera e propria - metodi appropriati ecc 
    */
    $('#form-profilo').validate({
        rules: {
            email:     { required: true, email: true, maxlength: 100 },
            indirizzo: { maxlength: 50 }
        },
        messages: {
            email: {
                required: 'Inserisci la tua email.',
                email: 'Inserisci un indirizzo email valido.',
                maxlength: 'Massimo 100 caratteri.'
            },
            indirizzo: {
                maxlength: 'Massimo 50 caratteri.'
            }
        }
    });

    $('#form-password').validate({
        rules: {
            psw_attuale:  { required: true },
            psw_nuova:    { required: true, minlength: 6 },
            psw_conferma: { required: true, equalTo: '#psw_nuova' }
        },
        messages: {
            psw_attuale: {
                required: 'Inserisci la password attuale.'
            },
            psw_nuova: {
                required: 'Inserisci la nuova password.',
                minlength: 'La password deve avere almeno 6 caratteri.'
            },
            psw_conferma: {
                required: 'Conferma la nuova password.',
                equalTo: 'Le due password non coincidono.'
            }
        }
    });

    $('#form-addcard').validate({
        rules: {
            numero_carta:        { required: true, cardNumber: true },
            nome_intestatario:   { required: true, maxlength: 20 },
            cognome_intestatario:{ required: true, maxlength: 20 },
            data_scadenza:       { required: true, futureDate: true },
            //cvv:                 { required: true, minlength: 3, maxlength: 4 }
        },
        messages: {
            numero_carta: {
                required: 'Inserisci il numero della carta.',
                cardNumber: 'Numero carta non valido (16 cifre).'
            },
            nome_intestatario: {
                required: 'Inserisci il nome.',
                maxlength: 'Massimo 20 caratteri.'
            },
            cognome_intestatario: {
                required: 'Inserisci il cognome.',
                maxlength: 'Massimo 20 caratteri.'
            },
            data_scadenza: {
                required: 'Inserisci la data di scadenza.',
                futureDate: 'La carta è scaduta o la data non è valida.'
            },
            /*cvv: {
                required: 'Inserisci il CVV.',
                minlength: 'Il CVV deve avere 3 o 4 cifre.',
                maxlength: 'Il CVV deve avere 3 o 4 cifre.'
            }*/
        }
    });

    $('#form-delete').validate({
        rules: {
            psw_conferma_delete: { required: true }
        },
        messages: {
            psw_conferma_delete: { required: 'Inserisci la password per confermare.' }
        }
    });
});
