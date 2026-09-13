/**
 * cat_filter.js — pannello filtro categorie (home.php e results.php).
 * Apertura/chiusura, espansione sottocategorie, azzeramento, submit automatico.
 */
$(function () {
    var $toggle = $('#cat-filter-toggle');
    var $panel  = $('#cat-filter-panel');

    /* -------- apri/chiudi pannello -------- */
    $toggle.on('click', function (e) {
        e.stopPropagation(); //non triggera altri click di elementi esterni (compreso document)
        var open = $toggle.attr('aria-expanded') === 'true'; 
        if (open) { //se attributo era true, nasconde
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        } else {
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
        }
    });

    /* -------- espandi/collassa sottocategorie - bottone ">" -------- */
    $(document).on('click', '.cat-filter-expand', function () {
        var $btn      = $(this);
        var $children = $btn.closest('.cat-filter-node') //tag "li"
            .find('> .cat-filter-children'); //tag figli con questa classe (cioè lista categorie figlie)
        var open      = $btn.attr('aria-expanded') === 'true';

        if (open) {
            $children.attr('hidden', true);
            $btn.attr('aria-expanded', 'false');
            $btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            $children.removeAttr('hidden');
            $btn.attr('aria-expanded', 'true');
            $btn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down'); //cambia direzione freccia font-awesome
        }
    });

    /* -------- azzera selezione -------- */
    $('#cat-filter-clear').on('click', function () {
        $('.cat-filter-checkbox').prop('checked', false);
        $('#cat-filter-form').trigger('submit'); //come .submit() ma così posso fare search su trigger di qualsiasi evento
    });

    /* -------- submit automatico quando si seleziona un checkbox -------- */
    /*$(document).on('change', '.cat-filter-checkbox', function () {
        $('#cat-filter-form').trigger('submit');
    });*/

    /* -------- espande automaticamente i rami che hanno una sottocategoria già selezionata - in caso di input nella barra degli indirizzi-------- */
    $('.cat-filter-children').each(function () { //applicata a ogni <ul> che contiene categorie figlie
        var $children = $(this);
        if ($children.find('.cat-filter-checkbox:checked').length > 0) {
            $children.removeAttr('hidden'); //mostra tutti i figli se uno del gruppo è selezionato

            //cambio attributo (utile anche per toggle) e aspetto
            var $expandBtn = $children.closest('.cat-filter-node')
                .find('> .cat-filter-row .cat-filter-expand'); //dall'inizio della lista di categorie, cerca expand (discendente) - >.cat-filter-row  è superfluo ?
            $expandBtn.attr('aria-expanded', 'true');
            $expandBtn.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    });

    /* -------- ordinamento (solo results.php) -------- */
    $('#sort-select').on('change', function () { //quando si seleziona un modo di ordinare
        var val = $(this).val(); // es. "prezzo-asc" quindi [per cosa]-[asc/disc]
        var parts = val.split('-');
        var sortBy = parts[0];
        var sortDir = parts[1];

        //oggetto URL per mantenere i parametri già impostati
        var url = new URL(window.location.href); //lo crea dall'URL della pagina attuale
        url.searchParams.set('sort', sortBy); //con set, aggiunge se non esiste / aggiorna, se esiste
        url.searchParams.set('dir', sortDir);
        window.location.href = url.toString();
    });
});
