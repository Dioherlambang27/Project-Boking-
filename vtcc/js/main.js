// VTCC - main.js v2

function toggleSidebar() {
    const sidebar  = document.querySelector('.sidebar');
    const overlay  = document.querySelector('.sidebar-overlay');
    if (sidebar)  sidebar.classList.toggle('open');
    if (overlay)  overlay.classList.toggle('show');
}

document.addEventListener('DOMContentLoaded', function () {

    // Hitung total bayar otomatis (booking admin tambah/edit)
    const kmInput     = document.getElementById('estimasi_km');
    const mobilSelect = document.getElementById('id_mobil');
    const totalEl     = document.getElementById('info-total');

    if (kmInput && mobilSelect) {
        function hitungTotal() {
            const opt = mobilSelect.options[mobilSelect.selectedIndex];
            if (!opt || !opt.dataset.hargaKm) return;
            const km       = parseFloat(kmInput.value) || 0;
            const hargaKm  = parseFloat(opt.dataset.hargaKm)  || 0;
            const hargaMin = parseFloat(opt.dataset.hargaMin) || 0;
            const total    = Math.max(km * hargaKm, hargaMin);
            if (totalEl) {
                totalEl.textContent = 'Estimasi Biaya: Rp ' + Math.round(total).toLocaleString('id-ID');
            }
        }
        kmInput.addEventListener('input', hitungTotal);
        mobilSelect.addEventListener('change', hitungTotal);
        hitungTotal();
    }

    // Auto-dismiss alerts
    setTimeout(function () {
        document.querySelectorAll('.alert-auto').forEach(function (el) {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity    = '0';
            setTimeout(function () { el.remove(); }, 400);
        });
    }, 4000);

    // Close sidebar overlay on click
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            document.querySelector('.sidebar')?.classList.remove('open');
            overlay.classList.remove('show');
        });
    }
});
