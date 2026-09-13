$(function () {
    $('#form-register-tipo').validate({
        rules: {
            tipo_utente: { required: true }
        },
        messages: {
            tipo_utente: 'Seleziona cliente o negoziante.'
        }
    });
});
