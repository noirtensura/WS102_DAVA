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
            // The embedded options remain usable if the request fails.
        }
    });
}

document.querySelectorAll('[data-photo-input]').forEach(input => {
    const preview = input.parentElement.querySelector('[data-photo-preview]');
    let source;
    input.addEventListener('change', () => {
        if (source) URL.revokeObjectURL(source);
        const file = input.files[0];
        if (!file || !file.type.startsWith('image/')) {
            preview.hidden = true;
            preview.removeAttribute('src');
            return;
        }
        source = URL.createObjectURL(file);
        preview.src = source;
        preview.hidden = false;
    });
});
