$(function () {
    
    function changeImage(newSrc) {
        const $mainImage = $('.product-main-image');
        if ($mainImage.length) {
            $mainImage.attr('src', newSrc);
        }
    }

    /* 
        controllo quantità - già fatto in html con min-max. 
        controlli form aggiungi al carrello - quantità rispetto a disponibilità e pezzi già nel carrello
    */
    var $cartForm = $('.product-add-cart'); //form per aggiungere al carrello
    if ($cartForm.length) {
        var cartQty = parseInt($cartForm.data('cart-qty'), 10) || 0;
        var disponibilita = parseInt($cartForm.data('disponibilita'), 10) || 0;
        var maxAdd = parseInt($cartForm.data('max-add'), 10) || 0;

         $.validator.addMethod('cartQtyLimit', function (value) {
            var qty = parseInt(value, 10);
            if (isNaN(qty) || qty < 1) {
                return false;
            }
            return qty <= maxAdd && (cartQty + qty) <= disponibilita;
        }, function () {
            var qty = parseInt($('#quantita').val(), 10) || 0;
            if (qty < 1) {
                return 'Inserisci almeno 1 pezzo.';
            }
            if (qty > maxAdd) {
                return 'Hai provato ad aggiungere troppi prodotti. Nel carrello ne hai già '
                    + cartQty + ' (puoi aggiungerne al massimo ' + maxAdd + ').';
            }
            //caso in cui cartQty + qty > disponibilita
            return 'Hai provato ad aggiungere troppi prodotti. Nel carrello ne hai già '
                + cartQty + ' (disponibilità: ' + disponibilita + ').';
        });

        $cartForm.validate({
            rules: {
                quantita: {
                    required: true,
                    digits: true,
                    min: 1,
                    cartQtyLimit: true
                }
            },
            messages: {
                quantita: {
                    required: 'Inserisci la quantità.',
                    digits: 'Inserisci un numero intero.',
                    min: 'Inserisci almeno 1 pezzo.'
                }
            }
        });
    }
    /* ================================================================
    GALLERIA IMMAGINI
    ================================================================ */

    var galleryImages = []; // contiene src di tutte le miniature del prodotto
    $('.product-gallery-thumb').each(function () {
        galleryImages.push($(this).data('src'));
    });

    var currentImages = galleryImages; // immagini mostrate nello slideshow in questo momento (di base quelle della galleria prodotto ma possono essere della recensione)
    var currentIndex  = 0;
    var isGallery     = true; // true = galleria prodotto (mostra frecce/miniature), false = foto singola (recensione)
    

    //funzione helper che triggera apertura della galleria all'immagine selezionata
    //IMPORTANTE: se si passa "images", lo slideshow mostra SOLO quelle immagini, senza frecce/miniature (usato per la foto di una recensione)
    //SE NON SI PASSA il secondo parametro, viene trattato come img recensione
    function lightboxOpen(index, images=galleryImages) {
        currentImages = images;
        isGallery     = !images; //senza secondo parametro è la normale galleria del prodotto

        if (currentImages.length === 0) { return; }
        currentIndex = index;
        lightboxShow(currentIndex);
        $('#lightbox-overlay').removeAttr('hidden');
        $('body').css('overflow', 'hidden'); // blocca scroll pagina
        $('#lightbox-close').trigger('focus');
    }

    //funzione chiusura galleria
    function lightboxClose() {
        $('#lightbox-overlay').attr('hidden', true); //nasconde slideshow
        $('body').css('overflow', '');
    }
    
    //apre galleria
    function lightboxShow(index) {
        var src = currentImages[index]; // galleryImages[index]; //img selezionata
        
        if (!src) { return; }

        var $img = $('#lightbox-img'); //immagine principale slideshow
        $img.css('opacity', 0);
        $img.attr('src', src);
        
        $img.attr('alt', isGallery ? ($('.product-main-image').attr('alt') || '') : 'Immagine recensione');

        $img.on('load.lb', function () { //aggiunge listener (con namespace custom) 
            $img.css('opacity', 1);
            $img.off('load.lb'); //rimuove solo listener lightbox
        });

        // SOLO GALLERIA PRODOTTO: aggiorna thumb attiva
        $('.lightbox-thumb').removeClass('active');
        $('.lightbox-thumb[data-index="' + index + '"]').addClass('active');

        // SOLO GALLERIA PRODOTTO: mostra frecce e strip miniature (frecce solo se c'è più di un'immagine)
        // (la foto di una recensione è singola)
        $('#lightbox-prev, #lightbox-next').toggle(isGallery && currentImages.length > 1); //più di una img prodotto
        $('#lightbox-thumbs').toggle(isGallery);
    }

    //btn avanti e indietro
    function lightboxNext() {
        currentIndex = (currentIndex + 1) % /*galleryImages*/currentImages.length;
        lightboxShow(currentIndex);
    }
    function lightboxPrev() {
        currentIndex = (currentIndex - 1 + /*galleryImages*/currentImages.length) % /*galleryImages*/currentImages.length;
        lightboxShow(currentIndex);
    }

    //evento clic su immagine - viene mostrata come principale
    // apertura: click su thumb nella galleria normale
    $(document).on('click', '.product-gallery-thumb', function () {
        var index = parseInt($(this).data('index'), 10) || 0;

        // aggiorna anche la galleria normale (immagine principale) TODO: valutare se togliere
        changeImage($(this).data('src'));
        //solo per il bordo
        $('.product-gallery-thumb').removeClass('active');
        $(this).addClass('active');
        
        //apre slideshow
        lightboxOpen(index);
    });

    // apertura: click sull'immagine principale
    $(document).on('click', '.product-main-image', function () {
        var index = parseInt($(this).data('index'), 10) || 0;
        lightboxOpen(index);
    });

    // apertura: click (o Invio/Spazio da tastiera) sulla foto allegata a una recensione
    // si vede solo lei, ingrandita come le foto del prodotto - niente frecce/miniature, non fa parte della galleria
    $(document).on('click keydown', '.review-img', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; } //da tastiera: solo invio/spazio
        e.preventDefault(); //per lo spazio, evita lo scroll della pagina
        lightboxOpen(0, [$(this).attr('src')]);
    });

    // chiudi: bottone ✕
    $('#lightbox-close').on('click', lightboxClose);

    // chiudi: click sull'overlay (fuori dal container)
    $('#lightbox-overlay').on('click', function (e) {
        if ($(e.target).is('#lightbox-overlay')) {
            lightboxClose();
        }
    });

    // frecce
    $('#lightbox-next').on('click', lightboxNext);
    $('#lightbox-prev').on('click', lightboxPrev);

    // click su miniatura nel lightbox
    $(document).on('click', '.lightbox-thumb', function () {
        var index = parseInt($(this).data('index'), 10) || 0;
        currentIndex = index;
        lightboxShow(index);
    });

    // tastiera: ← → Esc
    $(document).on('keydown', function (e) {
        if ($('#lightbox-overlay').attr('hidden') !== undefined) { return; }
        if (e.key === 'ArrowRight') { lightboxNext(); }
        if (e.key === 'ArrowLeft')  { lightboxPrev(); }
        if (e.key === 'Escape')     { lightboxClose(); }
    });

     /*
        stelle recensione.
        uso 1..5 (value del radio)
        selectedValue è il voto scelto. aggiornato al click 
    */
    var selectedValue = parseInt($('input[name="valutazione"]:checked').val(), 10) || 0; //0 = nessuna stella selezionata

    //attiva le stelle da 1 a value (0 = nessuna) 
    function starSelect(value) {
        $('.star-rating-label').each(function (i) {
            $(this).toggleClass('active', 
            (i + 1) <= value //aggiunge classe se true, rimuove se false
            ); 
        });
    }

    // stelle recensione
    $(document).on('mouseenter', '.star-rating-label', function () {
        var value = $('.star-rating-label').index(this) + 1; //indice to valore 1..5
        starSelect(value);
    });

    //stelle recensione: ripristina solo le stelle selezionate
    $(document).on('mouseleave', '.star-rating-input', function () {
        starSelect(selectedValue);
    });


    //stelle recensione: segna come checked quella selezionata
    // click: fissa la selezione sulla stella cliccata (e solo su quella)
    $(document).on('click', '.star-rating-label', function () {
        var value = $('.star-rating-label').index(this) + 1;
        selectedValue = value;

        // seleziona manualmente il radio corrispondente per valore
        $('input[name="valutazione"][value="' + value + '"]').prop('checked', true);

        starSelect(selectedValue);
    });


    // mostra n_caratteri_attuali di MAX_caratteri in tempo reale
    var $testoInput   = $('#testo');
    var $testoCounter = $('#testo-counter'); //span con numero caratteri rimanenti
    var maxLen        = parseInt($testoInput.attr('maxlength'), 10) || 255;

    //quando si inserisce carattere, controlla lunghezza e sottrae da max
    $testoInput.on('input', function () {
        var remaining = maxLen - $(this).val().length;
        $testoCounter.text(remaining);
        $testoCounter.css('color', remaining < 20 ? 'var(--error)' : '');
    });

    if ( $('#form-recensione').length ) { //se esiste (= se l'utente può scrivere recensione)
        
        //TODO: check controllo nel php
        // validazione dimensione/tipo file lato client
        $.validator.addMethod('fileSize', function (value, element) {
            return !element.files.length || element.files[0].size <= 2 * 1024 * 1024; //input type file che ha property 'files'
        }, 'Immagine troppo grande (max 2MB).');

        $.validator.addMethod('fileType', function (value, element) {
            if (!element.files.length) { return true; }
            return ['image/jpeg', 'image/png'].includes(element.files[0].type);
        }, 'Formato non supportato (solo jpg/png).');

        $('#form-recensione').validate({
            rules: {
                valutazione: { required: true, min: 1, max: 5 },
                testo: {
                    maxlength: 255,
                    secureText: true
                },

                //TODO: controllo
                img_recensione: {
                    fileSize: true,
                    fileType: true
                }
            },
            messages: {
                valutazione: 'Seleziona da 1 a 5 stelle.',
                
                testo: { 
                    maxlength: 'Massimo 255 caratteri.'
                }
            }
        });
    }
});
