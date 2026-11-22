document.querySelectorAll('input[data-upload-preview]').forEach((input) => {
    const preview = document.getElementById(input.dataset.uploadPreview);
    if (!preview) return;

    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files?.[0];
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            preview.removeAttribute('src');
            preview.hidden = true;
            return;
        }
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
    });
});
