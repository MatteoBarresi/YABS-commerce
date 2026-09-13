/**
 * pagamento.js — validazione form pagamento e toggle campi nuova carta.
 */
$(function () {

    /* ---------- mostra/nascondi campi nuova carta ---------- */

    function toggleNuovaCarta() {
        var scelta = $('input[name="id_carta"]:checked').val(); //carta selezionata o nuova carta
        if (scelta === 'nuova') { //possibile anche se ci sono carte nel db
            $('#nuova-carta-fields').slideDown(150); //mostra div
            // i campi nuova carta diventano required
            $('#numero_carta, #nome_intestatario, #cognome_intestatario, #data_scadenza, #cvv')
                .attr('required', true);
            // carta salvata non selezionata: niente cvv da chiedere (se il campo esiste, cioè se ci sono carte salvate)
            $('#cvv-carta-salvata-field').slideUp(150);
            $('#cvv_carta_salvata').removeAttr('required');
        } else {
            $('#nuova-carta-fields').slideUp(150);
            $('#numero_carta, #nome_intestatario, #cognome_intestatario, #data_scadenza, #cvv')
                .removeAttr('required'); 

                // carta salvata selezionata: chiedi il cvv (mai memorizzato/controllato lato server, solo regole jquery)
            $('#cvv-carta-salvata-field').slideDown(150);
            $('#cvv_carta_salvata').attr('required', true);        
        }
    }

    // al caricamento (se ci sono carte salvate, la prima è selezionata di default)
    toggleNuovaCarta();

    //quando cambia selezione carta, gestisce cosa mostrare e campi required
    $('input[name="id_carta"]').on('change', function () {
        toggleNuovaCarta();
    });

    /* ---------- formattazione automatica numero carta (in tempo reale, appena viene inserito un carattere) ---------- */
    $('#numero_carta').on('input', function () {
        var val = $(this).val().replace(/\D/g, '') //tutto quello che non è una cifra, lo sostituisce con '' (non si ferma alla prima)
        .substring(0, 16); //prende prime 16 - l'ultimo indice è escluso
        
        //divide per 4 cifre, inserendo spazio in mezzo
        var formatted = val.match(/.{1,4}/g); //array con match o null 
        $(this).val(formatted ? formatted.join(' ') : val);
    });

    /* ---------- solo cifre sul CVV ---------- */
    $('#cvv #cvv_carta_salvata').on('input', function () {
        $(this).val($(this).val().replace(/\D/g, '').substring(0, 4)); 
    });

    /* ---------- validazione jQuery Validator - i metodi restituiscono bool---------- */
    //prende il valore attuale del field e controlla che sia rispettata regex.test(stringa) per parametro passato
    $.validator.addMethod('cardNumber', function (value) { 
        return /^\d{4}(\s\d{4}){3}$/.test(value.trim()); //0000 0000 0000 0000
    },  //messaggio default
        'Inserisci un numero carta valido (16 cifre).');

    //data di scadenza deve essere futura
    $.validator.addMethod('futureDate', function (value) {
        if (!value) { return false; }
        return new Date(value) > new Date(); //comparison tra date
    }, 'La carta è scaduta.');

    $('#form-pagamento').validate({
        rules: {
            indirizzo_spedizione: {
                required: true,
                maxlength: 50,
                secureText: true
            },
            numero_carta: {
                cardNumber: true
            },
            nome_intestatario: {
                maxlength: 20,
                secureText: true
            },
            cognome_intestatario: {
                maxlength: 20,
                secureText: true
            },
            data_scadenza: {
                futureDate: true
            },
            cvv: {
                minlength: 3,
                maxlength: 4
            },
            cvv_carta_salvata: {
                minlength: 3,
                maxlength: 4
            }
        },
        messages: {
            indirizzo_spedizione: {
                required: 'Inserisci un indirizzo di spedizione.'
            }
        },
        //callback da eseguire se la validazione passa
        submitHandler: function (form) { 
            // stato di caricamento sul bottone
            var $btn = $('#btn-conferma');
            $btn.prop('disabled', true).text('Elaborazione…');
            form.submit(); //indirizza alla pagina action della form
        }
    });
});
