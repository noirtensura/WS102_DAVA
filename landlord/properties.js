'use strict';

const locationForm = document.querySelector('[data-location-form]');

if (locationForm) {
    const municipality = locationForm.querySelector('[data-municipality]');
    const barangay = locationForm.querySelector('[data-barangay]');
    const allOptions = Array.from(barangay.options, option => option.cloneNode(true));
    let request = 0;

    const localOptions = (place, selected = '') => {
        barangay.replaceChildren(...allOptions
            .filter(option => !option.value || !place || option.dataset.municipality === place)
            .map(option => option.cloneNode(true)));
        barangay.value = selected;
    };

    localOptions(municipality.value, barangay.value);
    municipality.addEventListener('change', async () => {
        const place = municipality.value;
        const change = ++request;
        localOptions(place);
        if (!place) return;
        try {
            const response = await fetch(`${locationForm.dataset.barangayUrl}?municipality_id=${encodeURIComponent(place)}`);
            if (!response.ok) return;
            const items = await response.json();
            if (change !== request || barangay.value) return;
            barangay.replaceChildren(new Option('Choose a barangay', ''), ...items.map(item => new Option(item.barangay_name, item.barangay_id)));
        } catch {
            // Embedded options remain usable if request fails.
        }
    });
}

// Multi-photo drag & drop + thumbnail previews
document.querySelectorAll('[data-multi-dropzone]').forEach((zone) => {
    const input = zone.querySelector('[data-multi-photo-input]');
    const grid = zone.querySelector('[data-multi-photo-preview]');
    if (!input || !grid) return;
    let previewUrls = [];

    const renderPreviews = () => {
        previewUrls.forEach((u) => URL.revokeObjectURL(u));
        previewUrls = [];
        grid.replaceChildren();
        const files = Array.from(input.files || []).filter((f) => f.type.startsWith('image/'));
        if (!files.length) {
            grid.hidden = true;
            return;
        }
        grid.hidden = false;
        files.slice(0, 12).forEach((file, index) => {
            const url = URL.createObjectURL(file);
            previewUrls.push(url);
            const item = document.createElement('figure');
            item.className = 'preview-thumb';
            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            const cap = document.createElement('figcaption');
            cap.textContent = (index === 0 ? '1st · ' : '') + file.name;
            item.append(img, cap);
            grid.append(item);
        });
    };

    input.addEventListener('change', renderPreviews);
    ['dragenter', 'dragover'].forEach((ev) => {
        zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.add('is-dragging');
        });
    });
    ['dragleave', 'drop'].forEach((ev) => {
        zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.remove('is-dragging');
        });
    });
    zone.addEventListener('drop', (e) => {
        if (!e.dataTransfer?.files?.length) return;
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).slice(0, 12).forEach((file) => {
            if (file.type.startsWith('image/')) dt.items.add(file);
        });
        input.files = dt.files;
        renderPreviews();
    });
});

// Dynamic Custom Necessities / Extra Details builder
document.querySelectorAll('[data-custom-details]').forEach((builder) => {
    const rowsWrap = builder.querySelector('[data-custom-rows]');
    const addBtn = builder.querySelector('[data-add-custom-row]');
    if (!rowsWrap || !addBtn) return;

    const createRow = () => {
        const div = document.createElement('div');
        div.className = 'custom-detail-row';
        div.setAttribute('data-custom-row', '');
        div.innerHTML =
            '<input type="text" name="custom_label[]" maxlength="80" placeholder="Label (e.g. Appliance Fee)">' +
            '<input type="text" name="custom_value[]" maxlength="240" placeholder="Detail (e.g. ₱150/mo for rice cooker)">' +
            '<button type="button" class="button small secondary icon-only" data-remove-custom-row aria-label="Remove row">✕</button>';
        return div;
    };

    addBtn.addEventListener('click', () => {
        if (rowsWrap.children.length >= 20) return;
        const row = createRow();
        rowsWrap.append(row);
        row.querySelector('input')?.focus();
    });

    rowsWrap.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-custom-row]');
        if (!remove) return;
        const row = remove.closest('[data-custom-row]');
        if (rowsWrap.children.length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((inp) => { inp.value = ''; });
        }
    });
});

// No-curfew toggle helper
const noCurfewBox = document.querySelector('input[name="no_curfew"]');
const curfewInput = document.querySelector('input[name="curfew_time"]');
if (noCurfewBox && curfewInput) {
    const syncCurfew = () => {
        if (noCurfewBox.checked) curfewInput.value = '';
        curfewInput.disabled = noCurfewBox.checked;
    };
    noCurfewBox.addEventListener('change', syncCurfew);
    syncCurfew();
}
