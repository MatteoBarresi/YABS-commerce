/**
 * home.js — infinite scroll delle righe categoria in home.php.
 *
 * "Suggeriti per te" e "Novità" arrivano già tutte nel render iniziale (PHP).
 * 
 * Le altre righe categoria: renderizzate solo
 * le prime N ($catLimit), poi quando #catalog-sentinel entra
 * nel viewport, questo js fa richiesta ajax per altre categorie a home_catalog_handler.php
 * (offset/limit) e appende l'HTML ricevuto al container.
 *
 * L'handler risponde con HTML puro (come notifiche.php?p=N), non json:
 * se risponde con array vuoto, le categorie sono finite e si smette
 * di osservare il sentinel.
 */
$(function () {
    var $container = $('#catalog-rows-container'); //div in cui vengono messe categorie (non suggeriti o novità)
    var $sentinel  = $('#catalog-sentinel'); //div che triggera richiesta ajax

    // se ci sono poche categorie, già mostrate tutte o filtro senza risultati:
    // il sentinel non viene renderizzato in home.php
    if ($container.length === 0 || $sentinel.length === 0) {
        return;
    }

    //dati impostati in home
    var offset  = parseInt($container.data('offset'), 10) || 0;
    var limit   = parseInt($container.data('limit'), 10) || 3;
    var catRaw  = ($container.data('cat') || '').toString(); // stringa tipo "1,4,7" oppure "" (nessun filtro)
    var loading = false;  // evita richieste doppie mentre una è già in corso - se div sentinel rimane visibile mentre la richiesta è ancora in corso (es connessione lenta)
    var finished = false; // true quando l'handler ha risposto vuoto (o in errore) - quindi blocca altre richieste

    /* -------- richiede altre categorie via ajax e le appende -------- */
    function caricaProssimeCategorie() {
        //controllo doppia richiesta
        if (loading || finished) {
            return;
        }
        loading = true;

        var params = { offset: offset, limit: limit }; //array passato nella richiesta ajax
        if (catRaw !== '') {
            params['cat[]'] = catRaw.split(','); // filtro categorie della pagina corrente (array) // stringa "1,4" -> ["1","4"]
            //in params avremo 'cat[]': ['1', '4'] 
        }

        $.get(baseUrl + '/home_catalog_handler.php', params) //serializzazione automatica -> ?cat[]=1&cat[]=4
            .done(function (html) {
                var trimmed = $.trim(html);
                if (trimmed === '') { //html vuoto
                    // nessun'altra categoria: smette di osservare il sentinel
                    finished = true;
                    observer.disconnect(); // stop osservazione
                    return;
                }
                //html non vuoto
                $container.append(html);
                offset += limit; // prossima richiesta partirà da qui
            })
            .fail(function () {
                // errore di rete/server
                finished = true;
                observer.disconnect();
            })
            .always(function () {
                loading = false;
            });
    }

    /* -------- IntersectionObserver sul sentinel in fondo alla lista -------- */
    //oggetto per capire quando un oggetto entra nel viewport (no evento scroll - più pesante)
    var observer = new IntersectionObserver(function (entries) { //eseguita quando target diventa visibile / esce dalla finestra
        //entries è un array di oggetti IntersectionOberverEntry (cioè oggetti osservati)
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                caricaProssimeCategorie();
                //console.log("infinite scroll triggerato");
            }
        });
    }, { //oggetto di opzioni per calcolare visibilità
        root: null, //significa viewport del browser (scroll della pagina)
        rootMargin: '400px', // aumenta viewport di 400px verso il basso (per anticipare la richiesta prima che il sentinel sia visibile)
        threshold: 0 //percentuale visibile dell'elemento osservato per lanciare callback
    });

    observer.observe($sentinel[0]);
});
