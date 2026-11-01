(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var requestToken = window.OC && typeof window.OC.requestToken === 'string'
            ? window.OC.requestToken
            : '';

        document.querySelectorAll('#teamtrip-app input[data-teamtrip-requesttoken]').forEach(function (input) {
            if (requestToken !== '') {
                input.value = requestToken;
            }
        });

        document.querySelectorAll('#teamtrip-app form[data-teamtrip-confirm-remove]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-teamtrip-confirm-remove') || '';
                if (message !== '' && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    });
}());
