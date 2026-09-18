(() => {
    const input = document.getElementById('evidence-file');
    const points = document.getElementById('request-points');
    const preview = document.getElementById('evidence-preview');
    const error = document.getElementById('evidence-error');
    const remove = document.getElementById('remove-evidence');
    let objectUrl;
    const clearPreview = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        preview.removeAttribute('src');
        preview.hidden = true;
        remove.hidden = true;
    };
    points.addEventListener('change', () => { input.required = points.checked && input.dataset.existing !== '1'; });
    input.addEventListener('change', () => {
        clearPreview();
        error.textContent = '';
        const file = input.files[0];
        if (!file) return;
        if (!/\.(jpe?g|png)$/i.test(file.name) || !['image/jpeg', 'image/png'].includes(file.type)) {
            error.textContent = 'รองรับเฉพาะรูป JPG, JPEG และ PNG เท่านั้น';
        } else if (file.size > 5 * 1024 * 1024) {
            error.textContent = 'รูปหลักฐานต้องมีขนาดไม่เกิน 5 MB';
        }
        if (error.textContent) { input.value = ''; return; }
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        remove.hidden = false;
    });
    preview.addEventListener('error', () => {
        clearPreview();
        input.value = '';
        error.textContent = 'ไม่สามารถอ่านรูปนี้ได้ กรุณาเลือกไฟล์รูปภาพที่ถูกต้อง';
    });
    remove.addEventListener('click', () => { clearPreview(); input.value = ''; error.textContent = ''; });
    window.addEventListener('pagehide', clearPreview);
})();
