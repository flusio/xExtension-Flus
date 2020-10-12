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
}());
