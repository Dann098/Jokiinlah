import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import { CV_STORAGE_KEY, cvBuilder, normalizeSafeHttpUrl } from '../../resources/js/cv-builder.js';

function memoryStorage(initial = {}) {
    const values = new Map(Object.entries(initial));

    return {
        getItem: (key) => values.has(key) ? values.get(key) : null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
        clear: () => values.clear(),
    };
}

beforeEach(() => {
    globalThis.window = {
        localStorage: memoryStorage(),
        confirm: () => true,
        print: () => {},
    };
});

afterEach(() => {
    delete globalThis.window;
});

test('URL preview hanya menerima HTTP dan HTTPS', () => {
    assert.equal(normalizeSafeHttpUrl('javascript:alert(1)'), '');
    assert.equal(normalizeSafeHttpUrl('data:text/html,halo'), '');
    assert.equal(normalizeSafeHttpUrl('linkedin.com/in/nadia'), 'https://linkedin.com/in/nadia');
});

test('repeater menambah dan menghapus pengalaman tanpa mengubah array lama', () => {
    const state = cvBuilder();
    const before = state.experiences;

    state.addExperience();
    assert.equal(state.experiences.length, 2);
    assert.notEqual(state.experiences, before);

    const withTwoItems = state.experiences;
    state.removeExperience(0);
    assert.equal(state.experiences.length, 1);
    assert.notEqual(state.experiences, withTwoItems);
});

test('section dapat dinonaktifkan secara immutable', () => {
    const state = cvBuilder();
    const before = state.sections;

    state.toggleSection('projects');

    assert.equal(state.sections.projects, false);
    assert.notEqual(state.sections, before);
});

test('draft rusak disanitasi per item tanpa menghapus seluruh draft', () => {
    window.localStorage = memoryStorage({
        [CV_STORAGE_KEY]: JSON.stringify({
            personal: { fullName: 'Nadia' },
            experiences: [null, { organization: 'Organisasi Aman' }],
            expiresAt: '2099-01-01T00:00:00.000Z',
        }),
    });
    const state = cvBuilder();

    state.init();

    assert.equal(state.personal.fullName, 'Nadia');
    assert.equal(state.experiences.at(-1).organization, 'Organisasi Aman');
    assert.notEqual(window.localStorage.getItem(CV_STORAGE_KEY), null);
});

test('draft yang melewati masa retensi otomatis dihapus', () => {
    window.localStorage = memoryStorage({
        [CV_STORAGE_KEY]: JSON.stringify({
            personal: { fullName: 'Data Kedaluwarsa' },
            expiresAt: '2020-01-01T00:00:00.000Z',
        }),
    });
    const state = cvBuilder();

    state.init();

    assert.equal(state.personal.fullName, '');
    assert.equal(window.localStorage.getItem(CV_STORAGE_KEY), null);
});

test('draft lama tanpa metadata retensi dianggap tidak valid', () => {
    window.localStorage = memoryStorage({
        [CV_STORAGE_KEY]: JSON.stringify({ personal: { fullName: 'Draft Tanpa Retensi' } }),
    });
    const state = cvBuilder();

    state.init();

    assert.equal(state.personal.fullName, '');
    assert.equal(window.localStorage.getItem(CV_STORAGE_KEY), null);
});

test('hapus semua data membatalkan penyimpanan debounce yang tertunda', async () => {
    const state = cvBuilder();
    state.storageAvailable = true;
    state.personal = { ...state.personal, fullName: 'Data Privat' };
    state.scheduleSave();

    assert.equal(state.clearAllData(), true);
    await new Promise((resolve) => setTimeout(resolve, 650));

    assert.equal(window.localStorage.getItem(CV_STORAGE_KEY), null);
    assert.equal(state.personal.fullName, '');
});

test('file dan preview foto tidak pernah ikut dipulihkan dari draft', () => {
    window.localStorage = memoryStorage({
        [CV_STORAGE_KEY]: JSON.stringify({
            usePhoto: true,
            expiresAt: '2099-01-01T00:00:00.000Z',
        }),
    });
    const state = cvBuilder();

    state.init();

    assert.equal(state.usePhoto, true);
    assert.equal(state.photoPreview, '');
    assert.equal('photoPreview' in state.persistedData(), false);
});

test('foto SVG ditolak sebelum object URL dibuat', () => {
    const state = cvBuilder();
    const input = {
        value: 'resume.svg',
        files: [{ name: 'resume.svg', type: 'image/svg+xml', size: 100 }],
    };

    state.handlePhoto({ currentTarget: input });

    assert.match(state.photoError, /SVG tidak diizinkan/u);
    assert.equal(state.photoPreview, '');
    assert.equal(input.value, '');
    state.destroy();
});

test('data contoh fiktif siap dipreview dan dipersistenkan', () => {
    const state = cvBuilder();
    state.storageAvailable = true;

    state.loadSampleData();

    assert.equal(state.personal.fullName, 'Nadia Prameswari');
    assert.ok(state.projects.length >= 2);
    assert.match(state.toast.message, /Data contoh berhasil dimuat/u);
    assert.notEqual(window.localStorage.getItem(CV_STORAGE_KEY), null);
    state.destroy();
});
