/**
 * ReUse - JavaScript du site (sans framework).
 * Le serveur (PHP) refait toujours les vérifications : le JS sert au confort.
 *  - AJAX favorites toggle (falls back to normal form submit)
 *  - Image previews for avatar / product uploads + drag & drop
 *  - Client-side form validation (server validates again)
 *  - Password visibility toggle
 *  - Delete confirmations
 *  - Scroll reveal animations
 */
document.addEventListener('DOMContentLoaded', () => {

    /* ---------- Auto-hide flash messages ---------- */
    document.querySelectorAll('.flash-stack .alert').forEach((el) => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 5000);
    });

    /* ---------- Favorites (AJAX) ---------- */
    document.querySelectorAll('.fav-form').forEach((form) => {
        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const btn = form.querySelector('.fav-btn');
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'Error');
                btn.classList.toggle('active', data.favorite);
                btn.querySelector('i').className = 'bi ' + (data.favorite ? 'bi-heart-fill' : 'bi-heart');
                btn.classList.remove('pop'); void btn.offsetWidth; btn.classList.add('pop');

                // On the favorites page, remove the card once un-saved
                const card = form.closest('[data-fav-item]');
                if (card && !data.favorite) {
                    card.style.transition = 'opacity .3s, transform .3s';
                    card.style.opacity = '0'; card.style.transform = 'scale(.95)';
                    setTimeout(() => {
                        card.remove();
                        if (!document.querySelector('[data-fav-item]')) location.reload();
                    }, 300);
                }
                const label = document.querySelector('[data-fav-label]');
                if (label) label.textContent = data.favorite ? 'Saved' : 'Save';
            } catch (e) {
                form.submit(); // fallback to classic POST
            }
        });
    });

    /* ---------- Image preview helpers ---------- */
    const MAX = 2 * 1024 * 1024;
    const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    function checkFile(file) {
        if (!file) return 'Please choose an image.';
        if (!TYPES.includes(file.type)) return 'Only JPG, PNG or WEBP images are allowed.';
        if (file.size > MAX) return 'The image is too large (max 2 MB).';
        return null;
    }

    function preview(input, onReady) {
        input.addEventListener('change', () => {
            const file = input.files[0];
            const err = checkFile(file);
            const box = input.closest('[data-upload]');
            const msg = box.parentElement.querySelector('.upload-error');
            box.classList.toggle('is-invalid', !!err);
            if (msg) { msg.textContent = err || ''; msg.classList.toggle('d-block', !!err); }
            if (err) { input.value = ''; return; }
            const reader = new FileReader();
            reader.onload = (e) => onReady(e.target.result, file);
            reader.readAsDataURL(file);
        });
    }

    // Avatar upload (register / profile)
    document.querySelectorAll('.avatar-upload').forEach((box) => {
        const input = box.querySelector('input[type=file]');
        const holder = box.querySelector('.avatar-preview');
        const name = box.querySelector('[data-file-name]');
        preview(input, (src, file) => {
            holder.innerHTML = '<img src="' + src + '" alt="">';
            if (name) name.textContent = file.name;
        });
    });

    // Product dropzone (add / edit)
    document.querySelectorAll('.dropzone').forEach((zone) => {
        const input = zone.querySelector('input[type=file]');
        zone.addEventListener('click', (e) => { if (e.target !== input) input.click(); });
        ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('dragover'); }));
        ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('dragover'); }));
        zone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
        preview(input, (src) => {
            let img = zone.querySelector('img.dz-preview');
            if (!img) { img = document.createElement('img'); img.className = 'dz-preview'; zone.appendChild(img); }
            img.src = src;
        });
    });

    /* ---------- Password visibility ---------- */
    document.querySelectorAll('.toggle-pass').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
        });
    });

    /* ---------- Client-side validation (Bootstrap style) ---------- */
    document.querySelectorAll('form.needs-validation').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            const pass = form.querySelector('[name=password]');
            const confirm = form.querySelector('[name=confirm_password]');
            if (pass && confirm) {
                confirm.setCustomValidity(pass.value !== confirm.value ? 'Passwords do not match.' : '');
            }
            // Required file inputs inside custom upload boxes
            form.querySelectorAll('[data-upload] input[type=file][required]').forEach((inp) => {
                const box = inp.closest('[data-upload]');
                const bad = !inp.files.length;
                box.classList.toggle('is-invalid', bad);
                const msg = box.parentElement.querySelector('.upload-error');
                if (bad && msg) { msg.textContent = 'Please choose an image.'; msg.classList.add('d-block'); }
            });
            if (!form.checkValidity()) {
                ev.preventDefault();
                ev.stopPropagation();
                const first = form.querySelector(':invalid');
                if (first) first.focus({ preventScroll: false });
            }
            form.classList.add('was-validated');
        });
    });

    /* ---------- Confirm destructive actions ---------- */
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (!confirm(form.dataset.confirm)) ev.preventDefault();
        });
    });

    /* ---------- Character counter ---------- */
    document.querySelectorAll('[data-counter]').forEach((field) => {
        const out = document.getElementById(field.dataset.counter);
        const max = field.getAttribute('maxlength');
        const upd = () => { out.textContent = field.value.length + ' / ' + max; };
        field.addEventListener('input', upd); upd();
    });

    /* ---------- Price range live label ---------- */
    document.querySelectorAll('[data-price-sync]').forEach((range) => {
        const target = document.getElementById(range.dataset.priceSync);
        range.addEventListener('input', () => { target.value = range.value; });
    });

    /* ---------- Marketplace: auto-submit filters ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    /* ---------- Galerie : clic sur une miniature = changer la grande photo ---------- */
    document.querySelectorAll('.gallery-thumbs .thumb').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            document.getElementById('mainPhoto').src = thumb.dataset.photo;
            document.querySelectorAll('.gallery-thumbs .thumb').forEach((t) => t.classList.remove('active'));
            thumb.classList.add('active');
        });
    });

    /* ---------- Aperçu des photos supplémentaires choisies (input multiple) ---------- */
    document.querySelectorAll('[data-multi-preview]').forEach((input) => {
        const grid = document.getElementById(input.dataset.multiPreview);
        input.addEventListener('change', () => {
            grid.innerHTML = '';
            Array.from(input.files).forEach((file) => {
                if (!TYPES.includes(file.type) || file.size > MAX) return;     // ignoré (le serveur refusera aussi)
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                grid.appendChild(img);
            });
        });
    });

    /* ---------- Gallery zoom ---------- */
    const gallery = document.querySelector('.gallery-main');
    if (gallery) {
        gallery.addEventListener('click', () => gallery.classList.toggle('zoom'));
        gallery.addEventListener('mousemove', (e) => {
            if (!gallery.classList.contains('zoom')) return;
            const r = gallery.getBoundingClientRect();
            const img = gallery.querySelector('img');
            img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
        });
    }

    /* ---------- Chat: scroll to latest message ---------- */
    const thread = document.querySelector('.thread-body');
    if (thread) thread.scrollTop = thread.scrollHeight;

    /* ---------- Reveal on scroll ---------- */
    const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
        entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('visible'); io.unobserve(en.target); } });
    }, { threshold: .12 }) : null;
    document.querySelectorAll('.reveal').forEach((el) => io ? io.observe(el) : el.classList.add('visible'));
});
