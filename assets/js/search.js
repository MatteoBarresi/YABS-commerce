/* 
messo in head - script che gestisce ricerca
*/
$(function () {
    const search_input = $('#search-input'); //searchbar
    const search_suggest = $('#search-suggest'); //div nascosto inizialmente

    //=========================TODO: CANCELLARE
    /*
        se non esistono - superfluo perché questo js viene incluso solo se variabile withNavbar == true
    */
    if (search_input.length === 0 || search_suggest.length === 0) {
        return;
    }
    //=========================

    //const baseUrl = $('body').data('base-url') || ''; //padre pagina
    let timer = null;
    let lastQuery = '';
    let selectedIndex = -1;

    function hideSuggest() {
        search_suggest.attr('hidden', true).empty();
    }

    //riempie il contenuto del div (con stringa passata) e lo mostra
    function showSuggest(html) {
        search_suggest.html(html).removeAttr('hidden');
        selectedIndex=-1;
    }

    //funzione che costruisce link - porta a results e nella get mette prodotto/venditore passato
    function buildUrl(item) {
        if (item.type === 'prodotto') { //caso in cui si tratta di un prodotto
            return baseUrl + '/results.php?q=' + encodeURIComponent(item.nome); //crea URI utilizzabile nella searchbar (gestisce spazi, simboli ecc)
        }
        //caso in cui si tratta di un venditore
        return baseUrl + '/results.php?vendor=' + encodeURIComponent(item.username);
    }

    //mostra dati restituiti dalla chiamata ajax
    function render(data) {
        const parts = []; //contiene stringhe - codice html poi unito (può anche essere una stringa e fare +=)
        
        if (data.products && data.products.length) { //c'è la key "products" e non è vuota
            parts.push('<p class="suggest-label">Prodotti</p><ul>');
            data.products.forEach(function (p) { //crea lista prodotti fetched / suggested
                parts.push(
                    '<li><a href="' + buildUrl(p) + '">' + //link item
                        /*
                        crea <span></span> (in memoria, non nel DOM) e ci mette il nome dell' item (letteralmente, ignora html - i tag diventano &lt ecc, quindi fa escaping)
                        poi .html() prende il contenuto del tag e lo restituisce
                        */
                        $('<span>').text(p.nome).html() + 
                        '</a></li>'
                );
            });
            parts.push('</ul>');
        }
        //stessa cosa - TODO: funzione
        if (data.vendors && data.vendors.length) {
            parts.push('<p class="suggest-label">Negozianti</p><ul>');
            data.vendors.forEach(function (v) {
                parts.push(
                    '<li><a href="' + buildUrl(v) + '">@' +
                        $('<span>').text(v.username).html() +
                        '</a></li>'
                );
            });
            parts.push('</ul>');
        }
        if (parts.length === 0) { //condizioni di sopra sono false (non ci sono keys o sono vuote)
            hideSuggest();
            return;
        }
        showSuggest(parts.join('')); //crea stringa da array
    }

    //chiamata ajax - passa stringa al server
    function fetchSuggest(q) {
        $.getJSON(baseUrl + '/api/search_suggest.php', { q: q })
            .done(function (data) { 

                //se mentre attendavamo risposta, utente digita altro
                if (search_input.val().trim() !== q) { //stringa passata != quella nella searchbar--> non mostra niente
                    return;
                }
                render(data);
            })
            .fail(function () {
                hideSuggest();
            });
    }

    //quando si inserisce testo nella searchbar
    search_input.on('input', function () {
        const q = $(this).val().trim(); //testo
        clearTimeout(timer); //annulla esecuzione callback futura - se si preme velocemente un'altra lettera
        
        if (q.length < 1) { //search vuota
            hideSuggest();
            lastQuery = '';
            return;
        }
        if (q === lastQuery) { //backspace veloce, si evita richiesta
            return;
        }

        //dopo x ms fa partire richiesta
        timer = setTimeout(function () {
            lastQuery = q;
            fetchSuggest(q);
        }, 250);
    });

    //evento quando si seleziona search, se il div suggested è mostrato, lo nasconde
    search_input.on('focus', function () {
        const q = $(this).val().trim();
        if (q.length >= 1 && search_suggest.children().length) { //controllo ci sia testo (inutile - più o meno: se c'è testo, probabilmente c'è suggested) e div suggested è riempito
            search_suggest.removeAttr('hidden');
        }
    });

    //se non clicchiamo nella form, nasconde suggest
    /*
        se si clicca nella pagina e l'elemento del dom (e.target) 
        non ha un parent di classe .search-wrap con dei figli 
        (ergo non clicchiamo nella form/dropdown)
    */
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-wrap').length) {
            hideSuggest();
        }
    });

    //controlla se il tasto premuto può nascondere dropdown
    search_input.on('keydown', function (e) {
        const items=search_suggest.find('li');

        if(e.key==='Escape'){
            hideSuggest(); 
            return;
        }
        if(!items.length) //nessun risultato
            return;

        //navigazione con frecce direzionali
        if(e.key==='ArrowDown'||e.key==='ArrowUp'){
            e.preventDefault(); //non fa scorrere la pagina
            if(e.key==='ArrowDown') 
                selectedIndex  = (selectedIndex + 1) % items.length; 
            else 
                selectedIndex = (selectedIndex - 1 + items.length) % items.length; //aggiungo items.length per valori negativi 
            //riassegno selezione
            items.removeClass('selected'); 
            $(items[selectedIndex]).addClass('selected');
        }
        if(e.key==='Enter' && selectedIndex >= 0){
            e.preventDefault(); 
            window.location = $(items[selectedIndex]).find('a').attr('href');
        }
    });
});
