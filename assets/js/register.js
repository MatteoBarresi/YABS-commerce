$(function () {
    //caratteri username accettati
    $.validator.addMethod(
        'usernameFormat',
        function (value, element) { //prende current value dell'element, element da validare, params
            return this.optional(element) || /^[a-zA-Z0-9_]{3,20}$/.test(value);
            /* 
                optional restituisce true se campo vuoto e non required (opzionale); false negli altri casi. se non è vuoto, applica la regex
                alt: return value === "" || regexp.test(value); ma non controlla se è opzionale
            */
        },
        'Solo lettere, numeri e underscore (3–20).'
    );
    const requiredMessage = "Campo obbligatorio";
    
    $('#form-register').validate({
        rules: {
            username: {
                required: true,
                usernameFormat: true,
                //maxlength: 20 //controllo gia nell'html (impedito numero maggiore) 
            },
            
            email: { required: true, 
                email: true, //ridefinito 
                maxlength: 100 },
            
            password: { required: true, minlength: 6 },
            
            password_confirm: { required: true, equalTo: '#password' },
            
            indirizzo: { 
                maxlength: 50, 
                secureText: true }
        },

        messages: {
            username: {
                required: requiredMessage,
                maxlength: "Troppi caratteri - non dovresti vederlo"
            },
            
            email: { 
                required: requiredMessage, 
                email: "Formato email non valido", 
                maxlength: "Troppi caratteri - non dovresti vederlo" },
            
            password: { 
                required: requiredMessage, 
                minlength: "Password troppo corta" },
            
            password_confirm: { 
                required: requiredMessage, 
                equalTo: "Le Password non coincidono" },
            
            indirizzo: { 
                maxlength: "Troppi caratteri - non dovresti vederlo", 
                secureText: "caratteri non consentiti" }
        },
        //viene già fatto di default
        submitHandler: function(form) {
            form.submit(); 
        }
    });
});
