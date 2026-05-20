/* CWeb Product Finder for Gravity Forms — onboarding wizard, step 4 (form picker) */
(function () {
    'use strict';

    var formSel = document.getElementById('cwebpf_form_id');
    if (!formSel) return;

    var fieldSel = document.getElementById('cwebpf_field_id_select');
    var fieldManual = document.getElementById('cwebpf_field_id_manual');
    var hint = document.getElementById('cwebpf_field_id_hint');
    var currentFieldId = formSel.getAttribute('data-current-field-id') || '';

    var l10n = window.cwebpfOnboardingL10n || {
        noHiddenField: 'No hidden field found in this form. Add one in Gravity Forms (Hidden field), then type its ID here.',
        oneHiddenField: 'One hidden field found and selected automatically.',
        multipleHiddenFields: 'Multiple hidden fields detected. Pick the one that should store the recommendation result.'
    };

    function refreshFieldOptions() {
        var opt = formSel.selectedOptions[0];
        var hidden = opt && opt.dataset.hidden ? JSON.parse(opt.dataset.hidden) : [];
        fieldSel.innerHTML = '';

        if (!opt || !opt.value) {
            fieldSel.style.display = '';
            fieldManual.style.display = 'none';
            fieldSel.name = 'cwebpf_field_id';
            fieldManual.name = '';
            return;
        }

        if (hidden.length === 0) {
            fieldSel.style.display = 'none';
            fieldSel.name = '';
            fieldManual.style.display = 'inline-block';
            fieldManual.name = 'cwebpf_field_id';
            fieldManual.value = currentFieldId || '';
            hint.textContent = l10n.noHiddenField;
            return;
        }

        fieldSel.style.display = '';
        fieldSel.name = 'cwebpf_field_id';
        fieldManual.style.display = 'none';
        fieldManual.name = '';

        hidden.forEach(function (f) {
            var o = document.createElement('option');
            o.value = f.id;
            o.textContent = '#' + f.id + ' — ' + (f.label || '(no label)');
            if (String(f.id) === String(currentFieldId)) o.selected = true;
            fieldSel.appendChild(o);
        });

        if (hidden.length === 1) {
            fieldSel.value = hidden[0].id;
            hint.textContent = l10n.oneHiddenField;
        } else {
            hint.textContent = l10n.multipleHiddenFields;
        }
    }

    formSel.addEventListener('change', refreshFieldOptions);
    refreshFieldOptions();
})();
