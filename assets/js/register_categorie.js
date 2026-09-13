/* 
quando utente preme bottone form, esegue funzione - mette in un array il valore (id come registrato nel db) di ogni categoria selezionata
poi crea il cookie come da manuale
*/

$(function () {

    $('#form-categorie').on('submit', function () {
        //prende attributo name di <input> della form e li mette in un array--> diventa stringa da usare nel cookie 
        const ids = [];
        $(this)
        .find('input[name="cat"]:checked') //attributo name che ha valore "cat", selezionato
        .each(function () { 
            ids.push($(this).val()); //attributo value di QUESTO elemento <input>
        });
        const maxAge = 60 * 60 * 24 * 365; // 1 anno
        document.cookie =
            'categorie_preferite=' + 
            encodeURIComponent(ids.join(',')) + 
            ';path=/;max-age=' +
            maxAge; 
    });
});
