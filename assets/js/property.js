'use strict';

const gallery = document.getElementById('property-gallery-view');
if (gallery?.showModal) {
    const photos = Array.from(document.querySelectorAll('[data-gallery-photo]'));
    const photo = gallery.querySelector('[data-gallery-preview]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const galleryButton = document.querySelector('[data-open-gallery]');
    let index = 0;

    function showPhoto(position) {
        index = (position + photos.length) % photos.length;
        const link = photos[index];
        photo.alt = link.querySelector('img').alt;
        photo.src = link.href;
        const label = link.dataset.caption?.trim();
        caption.textContent = 'Photo ' + (index + 1) + ' of ' + photos.length + (label ? ' · ' + label : '');
    }

    function openGallery(position) {
        showPhoto(position);
        gallery.showModal();
    }

    galleryButton.hidden = false;
    galleryButton.addEventListener('click', () => openGallery(0));
    const extraPhotos = document.querySelector('[data-extra-photos]');
    if (extraPhotos) extraPhotos.hidden = true;

    photos.forEach((link, position) => {
        link.setAttribute('aria-haspopup', 'dialog');
        link.addEventListener('click', (event) => {
            if (event.button || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
            event.preventDefault();
            openGallery(position);
        });
    });
    gallery.querySelectorAll('[data-gallery-step]').forEach((button) => {
        button.addEventListener('click', () => showPhoto(index + Number(button.dataset.galleryStep)));
    });
    gallery.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            showPhoto(index + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });
}
