/**
 * jQuery Validator — regex email dal brief.
 * siamo nel footer - dom caricato --> non serve $(function()) o $(document).ready(callback)
 */

$.validator.addMethod(
    'secureText',
    function (value, element) {
        if (this.optional(element)) {
            return true;
        }
        return !/<script|javascript:|on\w+\s*=/i.test(value);
    },
    'Caratteri non consentiti.'
);

$.validator.methods.email = function (value, element) {
    return (
        this.optional(element) ||
        ///^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
        /^[\w._-]+@[\w.-]+\.[a-zA-Z]{2,}$/.test(value)
    );
};
