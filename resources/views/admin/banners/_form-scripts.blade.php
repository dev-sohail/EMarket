<script nonce="{{ $cspNonce }}">
const BANNER_FIELDS = {
    homepage: {
        title: true, subtitle: true, description: true, image: true, color: false, url: true, cta: true, cta2: true, url2: true,
        sort: true, schedule: true, titleRequired: true, imageRequired: true,
        hintTitle: 'Homepage Hero Banner',
        hintDesc: 'Eyebrow, title, description and two CTA buttons with links, next to an image.',
    },
    promotional: {
        title: true, subtitle: false, description: true, image: true, color: false, url: true, cta: true, cta2: false, url2: false,
        sort: true, schedule: true, titleRequired: true, imageRequired: true,
        hintTitle: 'Promotional Banner',
        hintDesc: 'Mid-page banner with an image and text overlay between sections.',
    },
    sale: {
        title: true, subtitle: false, description: true, image: false, color: true, url: true, cta: true, cta2: false, url2: false,
        sort: true, schedule: true, titleRequired: true, imageRequired: false,
        hintTitle: 'Sale Banner',
        hintDesc: 'Solid background colour with text overlay. No image is used.',
    },
    footer: {
        title: true, subtitle: true, description: true, image: true, color: false, url: true, cta: true, cta2: false, url2: false,
        sort: true, schedule: false, titleRequired: true, imageRequired: false,
        hintTitle: 'Footer Banner',
        hintDesc: 'Full-width banner with the image on the right and text on the left.',
    },
};

const positionSelect = document.getElementById('position-select');
const hintBox = document.getElementById('position-hint');
const titleInput = document.getElementById('title-input');
const subtitleInput = document.getElementById('subtitle-input');
const descriptionInput = document.getElementById('description-input');
const ctaInput = document.getElementById('cta-input');
const cta2Input = document.getElementById('cta2-input');
const colorPicker = document.getElementById('color-picker');
const colorHex = document.getElementById('color-hex');
const colorPreview = document.getElementById('color-preview');
const imageInput = document.getElementById('image-input');
const imagePreview = document.getElementById('image-preview');

const previewSurface = document.getElementById('live-preview-surface');
const previewSubtitle = document.getElementById('live-preview-subtitle');
const previewTitle = document.getElementById('live-preview-title');
const previewDescription = document.getElementById('live-preview-description');
const previewCtas = document.getElementById('live-preview-ctas');

let selectedImageUrl = null;

function toggleField(id, show) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden', !show);
}

function currentPosition() {
    return positionSelect ? positionSelect.value : '';
}

function setText(el, value, fallback) {
    const text = (value || '').trim();
    el.textContent = text || fallback;
    el.classList.toggle('hidden', text === '' && fallback === null);
}

function renderPreview() {
    const config = BANNER_FIELDS[currentPosition()] || null;

    previewSurface.style.backgroundImage = '';
    previewSurface.style.backgroundColor = '';

    if (config && config.color && /^#[0-9A-Fa-f]{6}$/.test(colorHex.value)) {
        previewSurface.style.backgroundColor = colorHex.value;
    } else if (selectedImageUrl) {
        previewSurface.style.backgroundImage = `linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.35)), url("${selectedImageUrl}")`;
        previewSurface.style.backgroundSize = 'cover';
        previewSurface.style.backgroundPosition = 'center';
    } else {
        previewSurface.style.backgroundColor = '#2b2e33';
    }

    setText(previewSubtitle, subtitleInput.value, null);
    previewTitle.textContent = (titleInput.value || '').trim() || 'Banner title';

    const description = (descriptionInput.value || '').trim();
    previewDescription.textContent = description;
    previewDescription.classList.toggle('hidden', description === '');

    previewCtas.innerHTML = '';
    [ctaInput.value, cta2Input.value].forEach((value) => {
        const label = (value || '').trim();
        if (!label) return;
        const button = document.createElement('span');
        button.className = 'inline-flex items-center px-3 py-1 rounded text-[11px] font-semibold bg-white text-[var(--color-ink)]';
        button.textContent = label;
        previewCtas.appendChild(button);
    });
}

function applyPosition(position) {
    const config = BANNER_FIELDS[position];

    if (!config) {
        hintBox.classList.add('hidden');
        renderPreview();
        return;
    }

    toggleField('field-title', config.title);
    toggleField('field-subtitle', config.subtitle);
    toggleField('field-description', config.description);
    toggleField('field-image', config.image);
    toggleField('field-color', config.color);
    toggleField('field-url', config.url);
    toggleField('field-cta', config.cta);
    toggleField('field-cta2', config.cta2);
    toggleField('field-url2', config.url2);
    toggleField('field-sort', config.sort);
    toggleField('field-schedule', config.schedule);
    toggleField('field-schedule-end', config.schedule);

    document.querySelectorAll('.title-required').forEach((el) => {
        el.style.display = config.titleRequired ? 'inline' : 'none';
    });
    document.querySelectorAll('.image-required').forEach((el) => {
        el.style.display = config.imageRequired ? 'inline' : 'none';
    });

    titleInput.required = config.titleRequired;

    document.getElementById('hint-title').textContent = config.hintTitle;
    document.getElementById('hint-desc').textContent = config.hintDesc;
    hintBox.classList.remove('hidden');

    renderPreview();
}

positionSelect.addEventListener('change', function () {
    applyPosition(this.value);
});

[titleInput, subtitleInput, descriptionInput, ctaInput, cta2Input].forEach((input) => {
    input.addEventListener('input', renderPreview);
});

function syncColor(value) {
    if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
        colorPicker.value = value;
        colorPreview.style.backgroundColor = value;
    }
}

colorPicker.addEventListener('input', function () {
    colorHex.value = this.value;
    colorPreview.style.backgroundColor = this.value;
    renderPreview();
});

colorHex.addEventListener('input', function () {
    syncColor(this.value);
    renderPreview();
});

imageInput.addEventListener('change', function (event) {
    const file = event.target.files[0];

    if (!file) {
        imagePreview.classList.add('hidden');
        selectedImageUrl = null;
        renderPreview();
        return;
    }

    const reader = new FileReader();
    reader.onload = function (loadEvent) {
        selectedImageUrl = loadEvent.target.result;
        imagePreview.querySelector('img').src = selectedImageUrl;
        imagePreview.classList.remove('hidden');
        renderPreview();
    };
    reader.readAsDataURL(file);
});

applyPosition(currentPosition());
</script>
