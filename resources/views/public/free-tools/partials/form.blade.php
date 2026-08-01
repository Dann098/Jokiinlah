<div class='space-y-4 pb-8'>
    <x-free-tools.form-section title='Informasi Pribadi' description='Nama, kontak, tautan, dan foto opsional.' open>
        <div class='grid gap-4 sm:grid-cols-2'>
            <label class='cv-field sm:col-span-2'>
                <span>Nama lengkap <strong aria-hidden='true'>*</strong></span>
                <input type='text' x-model.trim='personal.fullName' maxlength='120' required autocomplete='name' placeholder='Contoh: Alya Pratama'>
            </label>
            <label class='cv-field'>
                <span>Gelar <small>(opsional)</small></span>
                <input type='text' x-model.trim='personal.title' maxlength='80' placeholder='S.Kom.'>
            </label>
            <label class='cv-field'>
                <span>Kota atau domisili</span>
                <input type='text' x-model.trim='personal.city' maxlength='100' autocomplete='address-level2' placeholder='Bandung, Jawa Barat'>
            </label>
            <label class='cv-field'>
                <span>Nomor telepon</span>
                <input type='tel' x-model.trim='personal.phone' maxlength='40' autocomplete='tel' placeholder='+62 812-3456-7890'>
            </label>
            <label class='cv-field'>
                <span>Email</span>
                <input type='email' x-model.trim='personal.email' maxlength='160' autocomplete='email' placeholder='nama@email.com' aria-describedby='email-validation' x-bind:aria-invalid="(personal.email && !safeEmailUrl(personal.email)).toString()">
                <small id='email-validation' class='text-red-700' x-show='personal.email && !safeEmailUrl(personal.email)'>Masukkan alamat email yang valid.</small>
            </label>
            <label class='cv-field'>
                <span>LinkedIn <small>(opsional)</small></span>
                <input type='url' x-model.trim='personal.linkedin' maxlength='240' inputmode='url' placeholder='https://linkedin.com/in/username' aria-describedby='linkedin-validation' x-bind:aria-invalid="(personal.linkedin && !safeUrl(personal.linkedin)).toString()">
                <small id='linkedin-validation' class='text-red-700' x-show='personal.linkedin && !safeUrl(personal.linkedin)'>Gunakan URL HTTP atau HTTPS yang valid.</small>
            </label>
            <label class='cv-field'>
                <span>Website / portofolio <small>(opsional)</small></span>
                <input type='url' x-model.trim='personal.website' maxlength='240' inputmode='url' placeholder='https://portofolio.dev' aria-describedby='website-validation' x-bind:aria-invalid="(personal.website && !safeUrl(personal.website)).toString()">
                <small id='website-validation' class='text-red-700' x-show='personal.website && !safeUrl(personal.website)'>Gunakan URL HTTP atau HTTPS yang valid.</small>
            </label>
        </div>

        <fieldset class='mt-5 rounded-xl border border-navy/10 p-4'>
            <legend class='px-1 text-sm font-bold text-navy'>Foto CV</legend>
            <label class='mt-2 flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold'>
                <input class='h-5 w-5 accent-navy' type='checkbox' x-model='usePhoto'>
                Gunakan Foto
            </label>
            <label class='cv-field mt-3' x-show='usePhoto'>
                <span>Pilih foto JPG, PNG, atau WebP (maksimal 1 MB)</span>
                <input x-ref='photoInput' type='file' accept='.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp' x-on:change='handlePhoto($event)' aria-describedby='photo-help photo-error'>
            </label>
            <p id='photo-help' class='mt-3 text-xs leading-5 text-muted'>Untuk beberapa negara atau posisi, CV tanpa foto dapat lebih sesuai. Gunakan foto hanya jika relevan.</p>
            <p id='photo-error' class='mt-2 text-xs font-semibold text-red-700' x-show='photoError' x-text='photoError' role='alert'></p>
            <button type='button' class='cv-text-button mt-3' x-show='photoPreview' x-on:click='removePhoto'>Hapus foto</button>
        </fieldset>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Pengaturan Bagian' description='Pilih bagian yang ingin ditampilkan di CV.'>
        <fieldset>
            <legend class='sr-only'>Bagian CV yang ditampilkan</legend>
            <div class='grid gap-3 sm:grid-cols-2'>
                @foreach(['summary' => 'Ringkasan Profesional', 'experience' => 'Pengalaman', 'education' => 'Pendidikan', 'projects' => 'Proyek', 'certifications' => 'Sertifikasi', 'skills' => 'Keahlian'] as $key => $label)
                    <label class='cv-check-row'><input type='checkbox' x-model='sections.{{ $key }}'> <span>{{ $label }}</span></label>
                @endforeach
            </div>
        </fieldset>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Ringkasan Profesional' description='Tulis 3–5 kalimat tentang bidang, kemampuan utama, pengalaman, dan target kontribusi.'>
        <label class='cv-field'>
            <span>Ringkasan</span>
            <textarea rows='7' maxlength='900' x-model='summary' placeholder='Fresh graduate Sistem Informasi dengan pengalaman dalam pengolahan data, pengembangan aplikasi, dan administrasi...'></textarea>
        </label>
        <p class='mt-2 text-right text-xs text-muted'><span x-text='summary.length'></span>/900 karakter</p>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Pengalaman' description='Maksimal 8 pengalaman dan 5 pencapaian per pengalaman.'>
        <div class='space-y-4'>
            <template x-for='(experience, index) in experiences' x-bind:key='experience.id'>
                <fieldset class='cv-repeater-card'>
                    <legend class='px-2 text-sm font-bold text-navy' x-text="`Pengalaman ${index + 1}`"></legend>
                    <div class='flex justify-end'><button type='button' class='cv-text-button cv-text-button--danger' x-on:click='removeExperience(index)'>Hapus pengalaman</button></div>
                    <div class='mt-3 grid gap-4 sm:grid-cols-2'>
                        <label class='cv-field'><span>Perusahaan / organisasi</span><input type='text' maxlength='140' x-model.trim='experience.organization'></label>
                        <label class='cv-field'><span>Posisi</span><input type='text' maxlength='120' x-model.trim='experience.position'></label>
                        <label class='cv-field'><span>Lokasi <small>(opsional)</small></span><input type='text' maxlength='100' x-model.trim='experience.location'></label>
                        <label class='cv-field'><span>Tanggal mulai</span><input type='month' x-model='experience.startDate'></label>
                        <label class='cv-field'><span>Tanggal selesai</span><input type='month' x-model='experience.endDate' x-bind:disabled='experience.current'></label>
                        <label class='cv-check-row self-end'><input type='checkbox' x-model='experience.current'> <span>Masih bekerja</span></label>
                    </div>
                    <div class='mt-5'>
                        <div class='flex items-center justify-between gap-3'><p class='text-sm font-bold text-navy'>Pencapaian</p><button type='button' class='cv-text-button' x-on:click='addExperienceBullet(index)' x-bind:disabled='experience.bullets.length >= 5'>Tambah bullet</button></div>
                        <template x-for='(bullet, bulletIndex) in experience.bullets' x-bind:key='`${experience.id}-${bulletIndex}`'>
                            <div class='mt-3 grid grid-cols-[1fr_auto] gap-2'>
                                <label class='cv-field'><span class='sr-only' x-text="`Pencapaian ${bulletIndex + 1}`"></span><textarea rows='2' maxlength='250' x-model='experience.bullets[bulletIndex]'></textarea></label>
                                <div class='flex flex-col gap-1'>
                                    <button type='button' class='cv-icon-button' x-on:click='moveExperienceBullet(index, bulletIndex, -1)' x-bind:disabled='bulletIndex === 0' aria-label='Naikkan bullet'>↑</button>
                                    <button type='button' class='cv-icon-button' x-on:click='moveExperienceBullet(index, bulletIndex, 1)' x-bind:disabled='bulletIndex === experience.bullets.length - 1' aria-label='Turunkan bullet'>↓</button>
                                    <button type='button' class='cv-icon-button cv-icon-button--danger' x-on:click='removeExperienceBullet(index, bulletIndex)' aria-label='Hapus bullet'>×</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </fieldset>
            </template>
        </div>
        <button type='button' class='cv-add-button mt-4' x-on:click='addExperience' x-bind:disabled='experiences.length >= 8'>+ Tambah Pengalaman</button>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Latar Belakang Pendidikan' description='Tambahkan pendidikan terbaru terlebih dahulu.'>
        <div class='space-y-4'>
            <template x-for='(education, index) in educations' x-bind:key='education.id'>
                <fieldset class='cv-repeater-card'>
                    <legend class='px-2 text-sm font-bold text-navy' x-text="`Pendidikan ${index + 1}`"></legend>
                    <div class='flex justify-end'><button type='button' class='cv-text-button cv-text-button--danger' x-on:click='removeEducation(index)'>Hapus pendidikan</button></div>
                    <div class='mt-3 grid gap-4 sm:grid-cols-2'>
                        <label class='cv-field sm:col-span-2'><span>Jenjang dan program studi</span><input type='text' maxlength='160' x-model.trim='education.degree'></label>
                        <label class='cv-field'><span>Institusi</span><input type='text' maxlength='160' x-model.trim='education.institution'></label>
                        <label class='cv-field'><span>Lokasi <small>(opsional)</small></span><input type='text' maxlength='100' x-model.trim='education.location'></label>
                        <label class='cv-field'><span>Tanggal mulai <small>(opsional)</small></span><input type='month' x-model='education.startDate'></label>
                        <label class='cv-field'><span>Tanggal lulus</span><input type='month' x-model='education.graduationDate'></label>
                        <label class='cv-field'><span>IPK <small>(opsional)</small></span><input type='text' maxlength='20' x-model.trim='education.gpa' placeholder='3,75 / 4,00'></label>
                        <label class='cv-field'><span>Penghargaan / predikat <small>(opsional)</small></span><input type='text' maxlength='180' x-model.trim='education.honors'></label>
                        <label class='cv-field sm:col-span-2'><span>Mata kuliah relevan <small>(opsional)</small></span><input type='text' maxlength='300' x-model.trim='education.coursework'></label>
                        <label class='cv-field sm:col-span-2'><span>Aktivitas organisasi <small>(opsional)</small></span><input type='text' maxlength='300' x-model.trim='education.activities'></label>
                    </div>
                </fieldset>
            </template>
        </div>
        <button type='button' class='cv-add-button mt-4' x-on:click='addEducation'>+ Tambah Pendidikan</button>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Proyek' description='Bagian opsional yang bermanfaat untuk fresh graduate.'>
        <div class='space-y-4'>
            <template x-for='(project, index) in projects' x-bind:key='project.id'>
                <fieldset class='cv-repeater-card'>
                    <legend class='px-2 text-sm font-bold text-navy' x-text="`Proyek ${index + 1}`"></legend>
                    <div class='flex justify-end'><button type='button' class='cv-text-button cv-text-button--danger' x-on:click='removeProject(index)'>Hapus proyek</button></div>
                    <div class='mt-3 grid gap-4 sm:grid-cols-2'>
                        <label class='cv-field'><span>Nama proyek</span><input type='text' maxlength='160' x-model.trim='project.name'></label>
                        <label class='cv-field'><span>Peran</span><input type='text' maxlength='120' x-model.trim='project.role'></label>
                        <label class='cv-field'><span>Periode <small>(opsional)</small></span><input type='text' maxlength='80' x-model.trim='project.period' placeholder='Januari – Mei 2026'></label>
                        <label class='cv-field'><span>Teknologi <small>(opsional)</small></span><input type='text' maxlength='200' x-model.trim='project.technologies'></label>
                        <label class='cv-field sm:col-span-2'><span>URL repository <small>(opsional)</small></span><input type='url' maxlength='240' x-model.trim='project.url' placeholder='https://github.com/...'></label>
                    </div>
                    <div class='mt-5'>
                        <div class='flex items-center justify-between gap-3'><p class='text-sm font-bold text-navy'>Kontribusi dan hasil</p><button type='button' class='cv-text-button' x-on:click='addProjectBullet(index)' x-bind:disabled='project.bullets.length >= 4'>Tambah bullet</button></div>
                        <template x-for='(bullet, bulletIndex) in project.bullets' x-bind:key='`${project.id}-${bulletIndex}`'>
                            <div class='mt-3 grid grid-cols-[1fr_auto] gap-2'>
                                <label class='cv-field'><span class='sr-only' x-text="`Kontribusi ${bulletIndex + 1}`"></span><textarea rows='2' maxlength='250' x-model='project.bullets[bulletIndex]'></textarea></label>
                                <div class='flex flex-col gap-1'>
                                    <button type='button' class='cv-icon-button' x-on:click='moveProjectBullet(index, bulletIndex, -1)' x-bind:disabled='bulletIndex === 0' aria-label='Naikkan bullet'>↑</button>
                                    <button type='button' class='cv-icon-button' x-on:click='moveProjectBullet(index, bulletIndex, 1)' x-bind:disabled='bulletIndex === project.bullets.length - 1' aria-label='Turunkan bullet'>↓</button>
                                    <button type='button' class='cv-icon-button cv-icon-button--danger' x-on:click='removeProjectBullet(index, bulletIndex)' aria-label='Hapus bullet'>×</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </fieldset>
            </template>
        </div>
        <button type='button' class='cv-add-button mt-4' x-on:click='addProject'>+ Tambah Proyek</button>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Sertifikasi' description='Cantumkan sertifikasi yang relevan secara ringkas.'>
        <div class='space-y-4'>
            <template x-for='(certification, index) in certifications' x-bind:key='certification.id'>
                <fieldset class='cv-repeater-card'>
                    <legend class='px-2 text-sm font-bold text-navy' x-text="`Sertifikasi ${index + 1}`"></legend>
                    <div class='flex justify-end'><button type='button' class='cv-text-button cv-text-button--danger' x-on:click='removeCertification(index)'>Hapus sertifikasi</button></div>
                    <div class='mt-3 grid gap-4 sm:grid-cols-2'>
                        <label class='cv-field'><span>Nama sertifikasi</span><input type='text' maxlength='160' x-model.trim='certification.name'></label>
                        <label class='cv-field'><span>Penerbit</span><input type='text' maxlength='140' x-model.trim='certification.issuer'></label>
                        <label class='cv-field'><span>Tanggal</span><input type='month' x-model='certification.date'></label>
                        <label class='cv-field'><span>Credential ID <small>(opsional)</small></span><input type='text' maxlength='120' x-model.trim='certification.credentialId'></label>
                        <label class='cv-field sm:col-span-2'><span>URL credential <small>(opsional)</small></span><input type='url' maxlength='240' x-model.trim='certification.url'></label>
                    </div>
                </fieldset>
            </template>
        </div>
        <button type='button' class='cv-add-button mt-4' x-on:click='addCertification'>+ Tambah Sertifikasi</button>
    </x-free-tools.form-section>

    <x-free-tools.form-section title='Keahlian' description='Pisahkan tiap keahlian dengan koma atau tekan Enter.'>
        <div class='space-y-4'>
            <template x-for='(category, index) in skillCategories' x-bind:key='category.id'>
                <fieldset class='cv-repeater-card'>
                    <legend class='sr-only' x-text="`Kategori keahlian ${index + 1}`"></legend>
                    <div class='grid gap-4 sm:grid-cols-[0.7fr_1.3fr_auto] sm:items-end'>
                        <label class='cv-field'><span>Nama kategori</span><input type='text' maxlength='80' x-model.trim='category.name'></label>
                        <label class='cv-field'><span>Daftar keahlian</span><input type='text' maxlength='400' x-model='category.input' x-on:change='addSkillTag(index)' x-on:keydown.enter.prevent='addSkillTag(index)' placeholder='Laravel, SQL, Excel'></label>
                        <button type='button' class='cv-icon-button cv-icon-button--danger mb-1' x-on:click='removeSkillCategory(index)' aria-label='Hapus kategori'>×</button>
                    </div>
                    <div class='mt-3 flex flex-wrap gap-2'>
                        <template x-for='(item, itemIndex) in category.items' x-bind:key='`${category.id}-${itemIndex}`'>
                            <span class='cv-skill-tag'><span x-text='item'></span><button type='button' x-on:click='removeSkillTag(index, itemIndex)' x-bind:aria-label="`Hapus ${item}`">×</button></span>
                        </template>
                    </div>
                </fieldset>
            </template>
        </div>
        <button type='button' class='cv-add-button mt-4' x-on:click='addSkillCategory'>+ Tambah Kategori</button>
    </x-free-tools.form-section>

    <div class='rounded-2xl border border-navy/10 bg-white p-5'>
        <p class='text-sm font-bold text-navy'>Draft lokal</p>
        <p class='mt-1 text-xs leading-5 text-muted'>Teks disimpan dengan debounce di perangkat ini dan otomatis kedaluwarsa setelah 30 hari. Foto tidak pernah masuk localStorage.</p>
        <p class='mt-3 text-xs font-semibold text-emerald-800' x-text='draftStatusLabel()' aria-live='polite'></p>
        <button type='button' class='cv-text-button cv-text-button--danger mt-3' x-on:click='clearAllData'>Hapus draft dan seluruh data</button>
    </div>
</div>
