$(function () {
    const requiredMessage = "Campo obbligatorio";
    $('#form-login').validate({
        rules: {
            login: { required: true, maxlength: 100, secureText: true },
            password: { required: true, minlength: 6 }
        }, 
        messages: {
            login: { required: requiredMessage, maxlength: "Numero massimo di caratteri raggiunto"},
            password: { required: requiredMessage, minlength: "Questo campo richiede un numero maggiore di caratteri" }
        }, 
    });
});
