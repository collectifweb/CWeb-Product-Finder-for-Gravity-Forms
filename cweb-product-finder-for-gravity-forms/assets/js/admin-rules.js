/* CWeb Product Finder for Gravity Forms — Scoring Rules editor */
(function () {
    'use strict';

    var list = document.getElementById('cwebpf-rules-list');
    if (!list) return;

    var pickAChoice = (window.cwebpfRulesL10n && window.cwebpfRulesL10n.pickAChoice) || '— pick a choice —';
    var ridx = parseInt(list.getAttribute('data-initial-count'), 10) || 0;

    var ruleTpl = document.getElementById('cwebpf-rule-template').innerHTML;
    var condTpl = document.getElementById('cwebpf-condition-template').innerHTML;
    var effectTpl = document.getElementById('cwebpf-effect-template').innerHTML;

    document.getElementById('cwebpf-add-rule').addEventListener('click', function () {
        list.insertAdjacentHTML('beforeend', ruleTpl.replaceAll('__RIDX__', ridx++));
    });

    list.addEventListener('click', function (e) {
        var card = e.target.closest('.cwebpf-rule-card');
        if (!card) return;
        var cardRidx = card.dataset.ridx;

        if (e.target.classList.contains('cwebpf-remove-rule')) {
            card.remove();
        }
        if (e.target.classList.contains('cwebpf-add-condition')) {
            var conds = card.querySelector('.cwebpf-conditions');
            var newCidx = conds.querySelectorAll('.cwebpf-condition-row').length;
            conds.insertAdjacentHTML('beforeend', condTpl.replaceAll('__RIDX__', cardRidx).replaceAll('__CIDX__', newCidx));
        }
        if (e.target.classList.contains('cwebpf-remove-condition')) {
            e.target.closest('.cwebpf-condition-row').remove();
        }
        if (e.target.classList.contains('cwebpf-add-effect')) {
            var effects = card.querySelector('.cwebpf-effects');
            var newEidx = effects.querySelectorAll('.cwebpf-effect-row').length;
            effects.insertAdjacentHTML('beforeend', effectTpl.replaceAll('__RIDX__', cardRidx).replaceAll('__EIDX__', newEidx));
        }
        if (e.target.classList.contains('cwebpf-remove-effect')) {
            e.target.closest('.cwebpf-effect-row').remove();
        }
    });

    // Dynamic value dropdown based on selected field
    list.addEventListener('change', function (e) {
        if (e.target.classList.contains('cwebpf-cond-field')) {
            var row = e.target.closest('.cwebpf-condition-row');
            var valueSelect = row.querySelector('.cwebpf-cond-value');
            var choices = JSON.parse((e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.choices) || '[]');
            valueSelect.innerHTML = '<option value="">' + pickAChoice + '</option>'
                + choices.map(function (c) {
                    var safe = String(c).replace(/"/g, '&quot;');
                    return '<option value="' + safe + '">' + safe + '</option>';
                }).join('');
        }
        if (e.target.classList.contains('cwebpf-cond-operator')) {
            var row2 = e.target.closest('.cwebpf-condition-row');
            var vs = row2.querySelector('.cwebpf-cond-value');
            vs.style.display = e.target.value === 'not_empty' ? 'none' : '';
        }
        if (e.target.classList.contains('cwebpf-effect-action')) {
            var row3 = e.target.closest('.cwebpf-effect-row');
            var pw = row3.querySelector('.cwebpf-effect-points-wrap');
            pw.style.display = ['exclude', 'require'].indexOf(e.target.value) !== -1 ? 'none' : '';
        }
    });
})();
