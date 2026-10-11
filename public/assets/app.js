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
