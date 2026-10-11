(function () {
    var party = document.getElementById('party');
    var classification = document.getElementById('classification');
    if (!party || !classification || !document.getElementById('classification-field')) {
        return;
    }
    function sync() {
        var isZpm = party.value === 'zpm';
        var isOther = party.value === 'other';
        ['classification-field', 'classification-note-field'].forEach(function (id) {
            document.getElementById(id).classList.toggle('is-hidden', isZpm);
        });
        document.getElementById('other-party-field').classList.toggle('is-hidden', !isOther);
        classification.required = !isZpm;
        document.getElementById('other_party_name').required = isOther;
        if (isZpm) {
            classification.value = '';
        }
    }
    party.addEventListener('change', sync);
    sync();
})();

document.addEventListener('submit', function (event) {
    var form = event.target;
    var submit = form.querySelector('button[type="submit"]');
    if (submit && form.dataset.preventRepeat === 'true') {
        submit.disabled = true;
        submit.textContent = 'Please wait...';
    }
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.querySelectorAll('[data-width]').forEach(function (el) { el.style.width = el.dataset.width + '%'; });
