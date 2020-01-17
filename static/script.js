(function () {
    // Add a warning on auth if user try to login with an email
    var url = new URL(window.location.href);
    var controllerName = url.searchParams.get('c');
    var actionName = url.searchParams.get('a');
    if (controllerName === 'auth' && actionName === 'login') {
        // Create a paragraph to explain to use username and not email address,
        // hidden by default.
        var useUsernameParagraph = document.createElement('p');
        var useUsernameContent = document.createTextNode(
            'Attention à bien saisir votre nom d’utilisateur et non votre adresse email !'
        );
        useUsernameParagraph.appendChild(useUsernameContent);
        useUsernameParagraph.id = 'use-username-info';
        useUsernameParagraph.className = 'alert alert-warn';
        useUsernameParagraph.style.display = 'none';

        // Insert the paragraph after the username input
        var usernameInput = document.getElementById('username');
        usernameInput.parentNode.insertBefore(useUsernameParagraph, usernameInput.nextSibling);

        // Listen on username input change to check if user insert a '@'. If it's
        // the case, display the paragraph.
        usernameInput.addEventListener('keyup', function() {
            if (usernameInput.value.indexOf('@') !== -1) {
                useUsernameParagraph.style.display = 'block';
            } else {
                useUsernameParagraph.style.display = 'none';
            }
        });
    }

    // Autosubmit the reminder form when checkbox is checked
    var reminderForm = document.querySelector('.form-billing-reminder');
    if (reminderForm) {
        var reminderCheckbox = reminderForm.querySelector('#reminder');
        reminderCheckbox.addEventListener('change', function () {
            reminderForm.submit();
        });
    }

    // Manage the billing renew form
    var monthPrice = document.getElementById('renew-amount-month');
    var yearPrice = document.getElementById('renew-amount-year');
    var radios = document.querySelectorAll('input[name="frequency"]');
    var monthFrequency = document.getElementById('month-frequency');

    if (!monthFrequency) {
        return;
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (this.value === 'month') {
                monthPrice.style.display = 'block';
                yearPrice.style.display = 'none';
            } else {
                monthPrice.style.display = 'none';
                yearPrice.style.display = 'block';
            }
        });
    });

    if (monthFrequency.checked) {
        monthPrice.style.display = 'block';
        yearPrice.style.display = 'none';
    } else {
        monthPrice.style.display = 'none';
        yearPrice.style.display = 'block';
    }
}());
