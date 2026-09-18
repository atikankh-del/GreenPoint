document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-share]');
    if (!button) return;
    const url = button.dataset.share;
    try {
        if (navigator.share) await navigator.share({title: 'GreenPoint', url});
        else if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
            button.textContent = '✓ คัดลอกลิงก์แล้ว';
        } else window.prompt('คัดลอกลิงก์โพสต์ (ผู้รับต้องเข้าสู่ระบบ)', url);
    } catch (error) {
        if (error.name !== 'AbortError') window.prompt('คัดลอกลิงก์โพสต์', url);
    }
});
