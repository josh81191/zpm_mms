(function () {
    var form = document.querySelector('form[data-auto-submit="true"]');
    if (!form) {
        return;
    }
    var search = form.querySelector('input[name="q"]');
    var button = form.querySelector('button[type="submit"]');
    var timer = null;
    if (button) {
        button.hidden = true;
    }
    function submit() {
        clearTimeout(timer);
        if (search) {
            sessionStorage.setItem('refocusSearch', document.activeElement === search ? '1' : '');
        }
        form.requestSubmit();
    }
    form.addEventListener('change', function (event) {
        if (event.target !== search) {
            submit();
        }
    });
    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(submit, 400);
        });
        if (sessionStorage.getItem('refocusSearch') === '1') {
            sessionStorage.removeItem('refocusSearch');
            search.focus();
            search.setSelectionRange(search.value.length, search.value.length);
        }
    }
})();

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

(function () {
    var field = document.getElementById('family-head-field');
    if (!field) {
        return;
    }
    var search = document.getElementById('family-head-search');
    var hidden = document.getElementById('family_head_id');
    var selected = document.getElementById('family-selected');
    var label = document.getElementById('family-selected-label');
    var results = document.getElementById('family-results');
    var unit = document.getElementById('current_unit_id');
    var timer = null;

    function choose(item) {
        hidden.value = item.id;
        label.textContent = item.full_name + ' (' + item.member_code + ')';
        selected.hidden = false;
        search.hidden = true;
        search.value = '';
        results.hidden = true;
    }

    function render(items) {
        results.innerHTML = '';
        if (!items.length) {
            var none = document.createElement('li');
            none.className = 'muted';
            none.textContent = 'No matching individuals found.';
            results.appendChild(none);
        }
        items.forEach(function (item) {
            var li = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = item.full_name + ' — ' + item.member_code + ' — ' + item.unit_name;
            button.addEventListener('click', function () { choose(item); });
            li.appendChild(button);
            results.appendChild(li);
        });
        results.hidden = false;
    }

    search.addEventListener('input', function () {
        clearTimeout(timer);
        if (search.value.trim().length < 2) {
            results.hidden = true;
            return;
        }
        timer = setTimeout(function () {
            var url = field.dataset.searchUrl + '?q=' + encodeURIComponent(search.value.trim()) +
                '&exclude=' + encodeURIComponent(field.dataset.exclude) +
                '&unit_id=' + encodeURIComponent(unit ? unit.value : '');
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error('failed'); } return r.json(); })
                .then(function (data) { render(data.results); })
                .catch(function () {
                    results.innerHTML = '<li class="muted">Search is unavailable. Please try again.</li>';
                    results.hidden = false;
                });
        }, 250);
    });

    document.getElementById('family-clear').addEventListener('click', function () {
        hidden.value = '';
        selected.hidden = true;
        search.hidden = false;
        search.focus();
    });
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
