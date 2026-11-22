'use strict';

const compareForm = document.getElementById('compare-form');
if (compareForm) {
    const boxes = Array.from(compareForm.elements).filter((field) => field.type === 'checkbox');
    const status = document.getElementById('compare-selection');
    const submit = compareForm.querySelector('button[type=submit]');
    function refreshSelection() {
        const selected = boxes.filter((box) => box.checked).length;
        submit.disabled = selected < 2;
        for (const box of boxes) box.disabled = selected >= 4 && !box.checked;
        status.textContent = selected < 2
            ? selected + ' selected. Choose at least two listings.'
            : selected + ' of 4 selected.';
    }
    boxes.forEach((box) => box.addEventListener('change', refreshSelection));
    refreshSelection();
}
