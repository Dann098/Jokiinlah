import { SAMPLE_CV_DATA } from './cv-builder-sample.js';

export const CV_STORAGE_KEY = 'jokiinlah_cv_academic_classic_v1';
const SAVE_DELAY_MS = 600;
const DRAFT_RETENTION_DAYS = 30;
const DRAFT_RETENTION_MS = DRAFT_RETENTION_DAYS * 24 * 60 * 60 * 1000;
const TOAST_DURATION_MS = 4500;
const MAX_PHOTO_BYTES = 1024 * 1024;
const ALLOWED_PHOTO_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const ALLOWED_PHOTO_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp']);
const ZOOM_LEVELS = new Set([75, 90, 100]);
const MOBILE_TABS = new Set(['form', 'preview']);
const INDONESIAN_MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const SECTION_NAMES = new Set([
    'summary',
    'experience',
    'education',
    'projects',
    'certifications',
    'skills',
]);

const LIMITS = Object.freeze({
    experiences: 8,
    educations: 8,
    projects: 8,
    certifications: 12,
    skillCategories: 8,
    experienceBullets: 5,
    projectBullets: 4,
    skillItems: 20,
});

let fallbackId = 0;
function createId(prefix) {
    if (globalThis.crypto?.randomUUID) return `${prefix}-${globalThis.crypto.randomUUID()}`;

    fallbackId += 1;
    return `${prefix}-${Date.now()}-${fallbackId}`;
}

function text(value, maxLength = 500) {
    return typeof value === 'string' ? value.slice(0, maxLength) : '';
}

function safeUrlInput(value) {
    const candidate = text(value, 500).trim();
    return normalizeSafeHttpUrl(candidate) ? candidate : '';
}

function record(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function createExperience(values = {}) {
    values = record(values);
    return {
        id: text(values.id, 100) || createId('experience'),
        organization: text(values.organization, 160),
        position: text(values.position, 160),
        location: text(values.location, 120),
        startDate: text(values.startDate, 30),
        endDate: text(values.endDate, 30),
        current: values.current === true,
        bullets: sanitizeBullets(values.bullets, LIMITS.experienceBullets),
    };
}

function createEducation(values = {}) {
    values = record(values);
    return {
        id: text(values.id, 100) || createId('education'),
        degree: text(values.degree, 180),
        institution: text(values.institution, 180),
        location: text(values.location, 120),
        startDate: text(values.startDate, 30),
        graduationDate: text(values.graduationDate, 30),
        gpa: text(values.gpa, 40),
        honors: text(values.honors, 200),
        coursework: text(values.coursework, 400),
        activities: text(values.activities, 400),
    };
}

function createProject(values = {}) {
    values = record(values);
    return {
        id: text(values.id, 100) || createId('project'),
        name: text(values.name, 180),
        role: text(values.role, 160),
        period: text(values.period, 80),
        technologies: text(values.technologies, 240),
        url: safeUrlInput(values.url),
        bullets: sanitizeBullets(values.bullets, LIMITS.projectBullets),
    };
}

function createCertification(values = {}) {
    values = record(values);
    return {
        id: text(values.id, 100) || createId('certification'),
        name: text(values.name, 180),
        issuer: text(values.issuer, 180),
        date: text(values.date, 30),
        credentialId: text(values.credentialId, 160),
        url: safeUrlInput(values.url),
    };
}

function createSkillCategory(values = {}) {
    values = record(values);
    const items = Array.isArray(values.items)
        ? values.items.map((item) => text(item, 80).trim()).filter(Boolean).slice(0, LIMITS.skillItems)
        : [];

    return {
        id: text(values.id, 100) || createId('skill'),
        name: text(values.name, 100),
        items: [...new Set(items)],
        input: '',
    };
}

function sanitizeBullets(value, limit) {
    if (!Array.isArray(value)) return [''];

    const bullets = value.slice(0, limit).map((bullet) => text(bullet, 250));
    return bullets.length > 0 ? bullets : [''];
}

function createDefaults() {
    return {
        personal: {
            fullName: '',
            title: '',
            city: '',
            phone: '',
            email: '',
            linkedin: '',
            website: '',
        },
        summary: '',
        experiences: [createExperience()],
        educations: [createEducation()],
        projects: [],
        certifications: [],
        skillCategories: [
            createSkillCategory({ name: 'Teknis' }),
            createSkillCategory({ name: 'Perangkat Lunak' }),
            createSkillCategory({ name: 'Bahasa' }),
            createSkillCategory({ name: 'Soft Skills' }),
        ],
        sections: {
            summary: true,
            experience: true,
            education: true,
            projects: true,
            certifications: true,
            skills: true,
        },
        usePhoto: false,
    };
}

function sanitizePersonal(value) {
    const personal = value && typeof value === 'object' ? value : {};
    return {
        fullName: text(personal.fullName, 160),
        title: text(personal.title, 160),
        city: text(personal.city, 160),
        phone: text(personal.phone, 80),
        email: text(personal.email, 254),
        linkedin: safeUrlInput(personal.linkedin),
        website: safeUrlInput(personal.website),
    };
}

function sanitizeCollection(value, limit, factory, fallback = []) {
    if (!Array.isArray(value)) return fallback;
    return value.slice(0, limit).map((item) => factory(item));
}

function sanitizeSections(value) {
    const defaults = createDefaults().sections;
    if (!value || typeof value !== 'object') return defaults;

    return Object.fromEntries(
        Object.entries(defaults).map(([name, enabled]) => [
            name,
            typeof value[name] === 'boolean' ? value[name] : enabled,
        ]),
    );
}

function sanitizeDraft(value) {
    const defaults = createDefaults();
    const draft = value && typeof value === 'object' ? value : {};

    return {
        personal: sanitizePersonal(draft.personal),
        summary: text(draft.summary, 900),
        experiences: sanitizeCollection(
            draft.experiences,
            LIMITS.experiences,
            createExperience,
            defaults.experiences,
        ),
        educations: sanitizeCollection(
            draft.educations,
            LIMITS.educations,
            createEducation,
            defaults.educations,
        ),
        projects: sanitizeCollection(draft.projects, LIMITS.projects, createProject),
        certifications: sanitizeCollection(
            draft.certifications,
            LIMITS.certifications,
            createCertification,
        ),
        skillCategories: sanitizeCollection(
            draft.skillCategories,
            LIMITS.skillCategories,
            createSkillCategory,
            defaults.skillCategories,
        ),
        sections: sanitizeSections(draft.sections),
        usePhoto: draft.usePhoto === true,
    };
}

function draftHasExpired(value) {
    if (!value || typeof value.expiresAt !== 'string') return true;
    const expiry = Date.parse(value.expiresAt);
    return !Number.isFinite(expiry) || expiry <= Date.now();
}

function sampleData() {
    return sanitizeDraft(SAMPLE_CV_DATA);
}

function replaceAt(items, index, replacement) {
    return items.map((item, itemIndex) => (itemIndex === index ? replacement : item));
}

function removeAt(items, index) {
    return items.filter((_, itemIndex) => itemIndex !== index);
}

function moveAt(items, index, direction) {
    const destination = index + Number(direction);
    if (index < 0 || destination < 0 || index >= items.length || destination >= items.length) return items;

    const nextItems = [...items];
    [nextItems[index], nextItems[destination]] = [nextItems[destination], nextItems[index]];
    return nextItems;
}

function resolveIndex(items, idOrIndex) {
    if (Number.isInteger(idOrIndex)) return idOrIndex;
    return items.findIndex((item) => item.id === idOrIndex);
}

function fileExtension(filename) {
    const parts = String(filename).toLowerCase().split('.');
    return parts.length > 1 ? parts.at(-1) : '';
}

export function normalizeSafeHttpUrl(value) {
    const candidate = text(value, 2000).trim();
    if (!candidate || /[\u0000-\u001f\u007f]/u.test(candidate)) return '';

    const withProtocol = /^[a-z][a-z\d+.-]*:/i.test(candidate) ? candidate : `https://${candidate}`;
    try {
        const url = new URL(withProtocol);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch {
        return '';
    }
}

export function isSafeHttpUrl(value) {
    return normalizeSafeHttpUrl(value) !== '';
}

export function cvBuilder() {
    const defaults = createDefaults();

    return {
        ...defaults,
        storageKey: CV_STORAGE_KEY,
        photoPreview: '',
        photoError: '',
        zoom: 90,
        mobileTab: 'form',
        draftStatus: 'empty',
        lastSavedAt: '',
        storageAvailable: true,
        toast: { visible: false, message: '', type: 'success' },
        _saveTimer: null,
        _toastTimer: null,
        _resumePersistenceTimer: null,
        _photoObjectUrl: '',
        _persistencePaused: false,

        init() {
            this.storageAvailable = this.checkStorageAvailable();
            this.restoreDraft();

            if (typeof this.$watch === 'function') {
                [
                    'personal',
                    'summary',
                    'experiences',
                    'educations',
                    'projects',
                    'certifications',
                    'skillCategories',
                    'sections',
                    'usePhoto',
                ].forEach((property) => this.$watch(property, () => this.scheduleSave()));
            }
        },

        destroy() {
            clearTimeout(this._saveTimer);
            clearTimeout(this._toastTimer);
            clearTimeout(this._resumePersistenceTimer);
            this.revokePhotoObjectUrl();
        },

        checkStorageAvailable() {
            if (typeof window === 'undefined') return false;

            try {
                if (!window.localStorage) return false;
                const probeKey = `${CV_STORAGE_KEY}_probe`;
                window.localStorage.setItem(probeKey, '1');
                window.localStorage.removeItem(probeKey);
                return true;
            } catch {
                return false;
            }
        },

        restoreDraft() {
            if (!this.storageAvailable) {
                this.draftStatus = 'unavailable';
                return;
            }

            try {
                const storedDraft = window.localStorage.getItem(CV_STORAGE_KEY);
                if (!storedDraft) return;
                const parsedDraft = JSON.parse(storedDraft);
                if (draftHasExpired(parsedDraft)) {
                    window.localStorage.removeItem(CV_STORAGE_KEY);
                    this.draftStatus = 'empty';
                    return;
                }
                Object.assign(this, sanitizeDraft(parsedDraft));
                this.draftStatus = 'saved';
            } catch {
                try {
                    window.localStorage.removeItem(CV_STORAGE_KEY);
                } catch {
                    this.storageAvailable = false;
                }
                this.draftStatus = 'error';
            }
        },

        scheduleSave() {
            if (!this.storageAvailable || this._persistencePaused) return;
            this.draftStatus = 'saving';
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.saveDraft(), SAVE_DELAY_MS);
        },

        saveDraft() {
            if (!this.storageAvailable) return;

            try {
                window.localStorage.setItem(CV_STORAGE_KEY, JSON.stringify(this.persistedData()));
                this.lastSavedAt = new Date().toISOString();
                this.draftStatus = 'saved';
            } catch {
                this.draftStatus = 'error';
            }
        },

        persistedData() {
            return {
                version: 1,
                savedAt: new Date().toISOString(),
                expiresAt: new Date(Date.now() + DRAFT_RETENTION_MS).toISOString(),
                personal: sanitizePersonal(this.personal),
                summary: text(this.summary, 900),
                experiences: this.experiences.map(createExperience),
                educations: this.educations.map(createEducation),
                projects: this.projects.map(createProject),
                certifications: this.certifications.map(createCertification),
                skillCategories: this.skillCategories.map(createSkillCategory),
                sections: sanitizeSections(this.sections),
                usePhoto: this.usePhoto === true,
            };
        },

        addExperience() {
            if (!this.canAdd(this.experiences, LIMITS.experiences, 'Maksimal delapan pengalaman.')) return;
            this.experiences = [...this.experiences, createExperience()];
        },

        removeExperience(idOrIndex) {
            this.experiences = removeAt(this.experiences, resolveIndex(this.experiences, idOrIndex));
        },

        moveExperience(index, direction) {
            this.experiences = moveAt(this.experiences, index, direction);
        },

        addExperienceBullet(idOrIndex) {
            this.addBulletTo('experiences', idOrIndex, LIMITS.experienceBullets, 'Maksimal lima poin per pengalaman.');
        },

        removeExperienceBullet(idOrIndex, bulletIndex) {
            this.removeBulletFrom('experiences', idOrIndex, bulletIndex);
        },

        moveExperienceBullet(idOrIndex, bulletIndex, direction) {
            this.moveBulletIn('experiences', idOrIndex, bulletIndex, direction);
        },

        addEducation() {
            if (!this.canAdd(this.educations, LIMITS.educations, 'Maksimal delapan riwayat pendidikan.')) return;
            this.educations = [...this.educations, createEducation()];
        },

        removeEducation(idOrIndex) {
            this.educations = removeAt(this.educations, resolveIndex(this.educations, idOrIndex));
        },

        moveEducation(index, direction) {
            this.educations = moveAt(this.educations, index, direction);
        },

        addProject() {
            if (!this.canAdd(this.projects, LIMITS.projects, 'Maksimal delapan proyek.')) return;
            this.projects = [...this.projects, createProject()];
        },

        removeProject(idOrIndex) {
            this.projects = removeAt(this.projects, resolveIndex(this.projects, idOrIndex));
        },

        moveProject(index, direction) {
            this.projects = moveAt(this.projects, index, direction);
        },

        addProjectBullet(idOrIndex) {
            this.addBulletTo('projects', idOrIndex, LIMITS.projectBullets, 'Maksimal empat poin per proyek.');
        },

        removeProjectBullet(idOrIndex, bulletIndex) {
            this.removeBulletFrom('projects', idOrIndex, bulletIndex);
        },

        moveProjectBullet(idOrIndex, bulletIndex, direction) {
            this.moveBulletIn('projects', idOrIndex, bulletIndex, direction);
        },

        addCertification() {
            if (!this.canAdd(this.certifications, LIMITS.certifications, 'Maksimal dua belas sertifikasi.')) return;
            this.certifications = [...this.certifications, createCertification()];
        },

        removeCertification(idOrIndex) {
            this.certifications = removeAt(
                this.certifications,
                resolveIndex(this.certifications, idOrIndex),
            );
        },

        moveCertification(index, direction) {
            this.certifications = moveAt(this.certifications, index, direction);
        },

        addSkillCategory() {
            if (!this.canAdd(this.skillCategories, LIMITS.skillCategories, 'Maksimal delapan kategori keahlian.')) return;
            this.skillCategories = [...this.skillCategories, createSkillCategory({ name: 'Kategori Baru' })];
        },

        removeSkillCategory(idOrIndex) {
            this.skillCategories = removeAt(
                this.skillCategories,
                resolveIndex(this.skillCategories, idOrIndex),
            );
        },

        addSkillTag(idOrIndex, suppliedValue = '') {
            const index = resolveIndex(this.skillCategories, idOrIndex);
            const category = this.skillCategories[index];
            if (!category) return;

            const source = suppliedValue || category.input;
            const additions = text(source, 500)
                .split(',')
                .map((item) => item.trim().slice(0, 80))
                .filter(Boolean);
            const items = [...new Set([...category.items, ...additions])].slice(0, LIMITS.skillItems);
            this.skillCategories = replaceAt(this.skillCategories, index, { ...category, items, input: '' });
        },

        removeSkillTag(idOrIndex, itemIndex) {
            const index = resolveIndex(this.skillCategories, idOrIndex);
            const category = this.skillCategories[index];
            if (!category) return;
            this.skillCategories = replaceAt(this.skillCategories, index, {
                ...category,
                items: removeAt(category.items, itemIndex),
            });
        },

        addBulletTo(collectionName, idOrIndex, limit, message) {
            const collection = this[collectionName];
            const index = resolveIndex(collection, idOrIndex);
            const item = collection[index];
            if (!item) return;
            if (!this.canAdd(item.bullets, limit, message)) return;
            this[collectionName] = replaceAt(collection, index, { ...item, bullets: [...item.bullets, ''] });
        },

        removeBulletFrom(collectionName, idOrIndex, bulletIndex) {
            const collection = this[collectionName];
            const index = resolveIndex(collection, idOrIndex);
            const item = collection[index];
            if (!item) return;
            const bullets = removeAt(item.bullets, bulletIndex);
            this[collectionName] = replaceAt(collection, index, {
                ...item,
                bullets: bullets.length > 0 ? bullets : [''],
            });
        },

        moveBulletIn(collectionName, idOrIndex, bulletIndex, direction) {
            const collection = this[collectionName];
            const index = resolveIndex(collection, idOrIndex);
            const item = collection[index];
            if (!item) return;
            this[collectionName] = replaceAt(collection, index, {
                ...item,
                bullets: moveAt(item.bullets, bulletIndex, direction),
            });
        },

        canAdd(collection, limit, message) {
            if (collection.length < limit) return true;
            this.showToast(message, 'warning');
            return false;
        },

        toggleSection(name) {
            if (!SECTION_NAMES.has(name)) return;
            this.sections = { ...this.sections, [name]: !this.sections[name] };
        },

        loadSampleData() {
            this.clearPhoto();
            Object.assign(this, sampleData());
            this.saveDraft();
            this.showToast('Data contoh berhasil dimuat. Silakan ganti dengan informasi Anda.');
        },

        resetData() {
            this.clearAllData();
        },

        clearAllData() {
            const confirmed = typeof window === 'undefined'
                || typeof window.confirm !== 'function'
                || window.confirm('Hapus seluruh data CV dan draft yang tersimpan di perangkat ini?');
            if (!confirmed) return false;

            clearTimeout(this._saveTimer);
            clearTimeout(this._resumePersistenceTimer);
            this._persistencePaused = true;
            this.clearPhoto();
            Object.assign(this, createDefaults());
            this.zoom = 90;
            this.mobileTab = 'form';
            this.removeStoredDraft();
            this._resumePersistenceTimer = setTimeout(() => {
                this._persistencePaused = false;
            }, 0);
            this.showToast('Semua data CV berhasil dihapus.');
            return true;
        },

        deleteDraft() {
            clearTimeout(this._saveTimer);
            this.removeStoredDraft();
            this.showToast('Draft di perangkat berhasil dihapus.');
        },

        removeStoredDraft() {
            if (this.storageAvailable) {
                try {
                    window.localStorage.removeItem(CV_STORAGE_KEY);
                } catch {
                    this.storageAvailable = false;
                }
            }
            this.draftStatus = this.storageAvailable ? 'empty' : 'unavailable';
            this.lastSavedAt = '';
        },

        handlePhoto(event) {
            const input = event?.currentTarget || event?.target;
            const file = input?.files?.[0];
            this.photoError = '';
            if (!file) {
                this.clearPhoto();
                return;
            }

            if (!ALLOWED_PHOTO_TYPES.has(file.type) || !ALLOWED_PHOTO_EXTENSIONS.has(fileExtension(file.name))) {
                this.rejectPhoto(input, 'Pilih foto JPG, JPEG, PNG, atau WebP. File SVG tidak diizinkan.');
                return;
            }
            if (file.size > MAX_PHOTO_BYTES) {
                this.rejectPhoto(input, 'Ukuran foto maksimal 1 MB.');
                return;
            }

            this.revokePhotoObjectUrl();
            this._photoObjectUrl = URL.createObjectURL(file);
            this.photoPreview = this._photoObjectUrl;
            this.usePhoto = true;
        },

        rejectPhoto(input, message) {
            this.clearPhoto();
            if (input) input.value = '';
            this.photoError = message;
            this.showToast(message, 'error');
        },

        clearPhoto() {
            this.revokePhotoObjectUrl();
            this.photoPreview = '';
            this.usePhoto = false;
            this.photoError = '';
            if (this.$refs?.photoInput) this.$refs.photoInput.value = '';
        },

        removePhoto() {
            this.clearPhoto();
        },

        revokePhotoObjectUrl() {
            if (!this._photoObjectUrl || typeof URL === 'undefined') return;
            URL.revokeObjectURL(this._photoObjectUrl);
            this._photoObjectUrl = '';
        },

        safeUrl(value) {
            return normalizeSafeHttpUrl(value);
        },

        displayUrl(value) {
            const safe = normalizeSafeHttpUrl(value);
            if (!safe) return '';
            return safe.replace(/^https?:\/\//i, '').replace(/\/$/, '');
        },

        safeEmailUrl(value) {
            const email = text(value, 254).trim();
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email) ? `mailto:${email}` : '';
        },

        formatMonth(value) {
            const match = /^(\d{4})-(\d{2})(?:-\d{2})?$/u.exec(text(value, 30).trim());
            if (!match) return '';
            const monthIndex = Number(match[2]) - 1;
            if (!INDONESIAN_MONTHS[monthIndex]) return '';
            return `${INDONESIAN_MONTHS[monthIndex]} ${match[1]}`;
        },

        formatPeriod(startDate, endDate, current = false) {
            const start = this.formatMonth(startDate);
            const end = current ? 'Sekarang' : this.formatMonth(endDate);
            return [start, end].filter(Boolean).join(' – ');
        },

        draftStatusLabel() {
            return {
                empty: 'Belum ada draft tersimpan',
                saving: 'Menyimpan draft…',
                saved: 'Draft tersimpan di perangkat',
                error: 'Draft tidak dapat disimpan',
                unavailable: 'Penyimpanan browser tidak tersedia',
            }[this.draftStatus] || '';
        },

        setZoom(value) {
            const zoom = Number(value);
            if (ZOOM_LEVELS.has(zoom)) this.zoom = zoom;
        },

        setMobileTab(tab) {
            if (MOBILE_TABS.has(tab)) this.mobileTab = tab;
        },

        showPreview() {
            this.setMobileTab('preview');
        },

        printCv() {
            if (typeof window !== 'undefined') window.print();
        },

        showToast(message, type = 'success') {
            clearTimeout(this._toastTimer);
            this.toast = { visible: true, message: text(message, 300), type };
            this._toastTimer = setTimeout(() => this.dismissToast(), TOAST_DURATION_MS);
        },

        dismissToast() {
            this.toast = { ...this.toast, visible: false };
        },
    };
}
