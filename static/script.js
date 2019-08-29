(function () {
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
