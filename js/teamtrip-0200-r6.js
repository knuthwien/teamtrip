(function () {
    'use strict';

    function showShareError(form) {
        var section = form.closest('#shares');
        if (!section) {
            return;
        }

        var notice = section.querySelector('[data-teamtrip-share-error]');
        if (!notice) {
            notice = document.createElement('p');
            notice.className = 'teamtrip-form-notice is-error';
            notice.setAttribute('data-teamtrip-share-error', '');
            notice.setAttribute('role', 'alert');
            notice.textContent = form.getAttribute('data-teamtrip-error-message') || 'The share could not be updated. Please try again.';
            var title = section.querySelector('.teamtrip-section-title-row');
            if (title) {
                title.insertAdjacentElement('afterend', notice);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var requestToken = window.OC && typeof window.OC.requestToken === 'string'
            ? window.OC.requestToken
            : '';

        document.querySelectorAll('#teamtrip-app form[data-teamtrip-share-request]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var operation = form.getAttribute('data-teamtrip-share-request') || 'save';
                var confirmation = form.getAttribute('data-teamtrip-confirm-remove') || '';
                if (confirmation !== '' && !window.confirm(confirmation)) {
                    return;
                }

                var buttons = form.querySelectorAll('button');
                buttons.forEach(function (button) { button.disabled = true; });

                var headers = {
                    'Accept': 'application/json',
                    'OCS-APIRequest': 'true'
                };
                if (requestToken !== '') {
                    headers.requesttoken = requestToken;
                }

                var options = {
                    method: operation === 'remove' ? 'DELETE' : 'POST',
                    credentials: 'same-origin',
                    headers: headers
                };

                if (operation !== 'remove') {
                    var formData = new FormData(form);
                    var body = new URLSearchParams();
                    body.set('userId', String(formData.get('shareUserId') || ''));
                    body.set('role', String(formData.get('shareRole') || 'read'));
                    options.body = body;
                    headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
                }

                window.fetch(form.action, options).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Share request failed with status ' + response.status);
                    }
                    window.location.assign(form.getAttribute('data-teamtrip-success-url') || window.location.href);
                }).catch(function () {
                    buttons.forEach(function (button) { button.disabled = false; });
                    showShareError(form);
                });
            });
        });
    });
}());
