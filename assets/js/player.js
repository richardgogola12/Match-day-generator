/* Profil hráča – nahratie fotky (uloží sa v prehliadači) */
(function ($) {
    'use strict';
    const MD = window.MD;
    const box = document.getElementById('photoBox');
    if (!box) return;
    const key = box.dataset.key;
    const $img = $('#photoImg');
    let url = null;

    async function show() {
        const blob = await MD.store.getPhoto(key);
        if (url) URL.revokeObjectURL(url);
        url = blob ? URL.createObjectURL(blob) : null;
        const src = url || box.dataset.remote || ''; // bez vlastnej fotky fotka zo Sportnetu, ak existuje
        $img.attr('src', src).prop('hidden', !src);
        box.classList.toggle('has-photo', !!src);
        $('#photoRemove').prop('hidden', !url);
    }

    async function setFile(file) {
        if (!file || !/^image\//.test(file.type)) { MD.toast('Vyberte obrázok (PNG, JPG, WEBP).', 'danger'); return; }
        try {
            await MD.store.setPhoto(key, await MD.store.shrinkImage(file, 1200));
            await show();
            MD.toast('Fotka uložená v prehliadači');
        } catch (e) {
            MD.toast(e.message || 'Fotku sa nepodarilo uložiť.', 'danger');
        }
    }

    $('#photoFile').on('change', function () { setFile(this.files[0]); this.value = ''; });
    $('#photoDrop')
        .on('dragover dragenter', (e) => { e.preventDefault(); $('#photoDrop').addClass('drag'); })
        .on('dragleave drop', (e) => { e.preventDefault(); $('#photoDrop').removeClass('drag'); })
        .on('drop', (e) => setFile(e.originalEvent.dataTransfer.files[0]));
    $('#photoRemove').on('click', async () => { await MD.store.removePhoto(key); await show(); MD.toast('Fotka odstránená', 'info'); });

    show();
})(jQuery);
