$(function() {
    $(/*'.product-card-img-top'*/'img').each(() => {
        $(this).on('error', ()=> {

            $(this).next('product-card-img-placeholder').show();
            $(this).hide();
        });     
    });
});
