<article id='cv-print-root' class='cv-paper' aria-label='Preview CV Academic Classic'>
    <header class='cv-document-header' x-bind:class="usePhoto && photoPreview ? 'has-photo' : ''">
        <div class='cv-document-identity'>
            <h2 class='cv-document-name' x-text="personal.fullName || 'NAMA LENGKAP'"></h2>
            <p class='cv-document-title' x-show='personal.title' x-text='personal.title'></p>
            <div class='cv-document-contacts' aria-label='Informasi kontak'>
                <template x-for="(contact, index) in [personal.city, personal.phone, personal.email, displayUrl(personal.linkedin), displayUrl(personal.website)].filter(Boolean)" x-bind:key='`${contact}-${index}`'>
                    <span class='cv-document-contact'><span class='cv-document-dot' x-show='index > 0' aria-hidden='true'>•</span><span x-text='contact'></span></span>
                </template>
            </div>
        </div>
        <template x-if='usePhoto && photoPreview'>
            <img class='cv-document-photo' x-bind:src='photoPreview' alt='Foto profil CV yang dipilih pengguna'>
        </template>
    </header>

    <section class='cv-document-section' x-show='sections.summary && summary.trim()'>
        <h3>RINGKASAN PROFESIONAL</h3>
        <p class='cv-document-summary' x-text='summary'></p>
    </section>

    <section class='cv-document-section' x-show="sections.experience && experiences.some((item) => item.organization || item.position || item.bullets.some((bullet) => bullet.trim()))">
        <h3>PENGALAMAN</h3>
        <div class='cv-document-list'>
            <template x-for='experience in experiences' x-bind:key='experience.id'>
                <article class='cv-document-item' x-show="experience.organization || experience.position || experience.bullets.some((bullet) => bullet.trim())">
                    <div class='cv-document-row'>
                        <strong x-text='experience.organization'></strong>
                        <strong class='cv-document-date' x-text='formatPeriod(experience.startDate, experience.endDate, experience.current)'></strong>
                    </div>
                    <p x-show='experience.position || experience.location'>
                        <span x-text='experience.position'></span><span x-show='experience.position && experience.location'> — </span><span x-text='experience.location'></span>
                    </p>
                    <ul x-show='experience.bullets.some((bullet) => bullet.trim())'>
                        <template x-for='(bullet, index) in experience.bullets.filter((value) => value.trim())' x-bind:key='`${experience.id}-preview-${index}`'><li x-text='bullet'></li></template>
                    </ul>
                </article>
            </template>
        </div>
    </section>

    <section class='cv-document-section' x-show="sections.education && educations.some((item) => item.degree || item.institution)">
        <h3>LATAR BELAKANG PENDIDIKAN</h3>
        <div class='cv-document-list'>
            <template x-for='education in educations' x-bind:key='education.id'>
                <article class='cv-document-item' x-show='education.degree || education.institution'>
                    <div class='cv-document-row'>
                        <strong x-text='education.degree'></strong>
                        <strong class='cv-document-date' x-text='formatMonth(education.graduationDate)'></strong>
                    </div>
                    <p x-show='education.institution || education.location'><span x-text='education.institution'></span><span x-show='education.institution && education.location'>, </span><span x-text='education.location'></span></p>
                    <ul x-show='education.gpa || education.honors || education.coursework || education.activities'>
                        <li x-show='education.gpa'><strong>IPK:</strong> <span x-text='education.gpa'></span></li>
                        <li x-show='education.honors'><strong>Penghargaan:</strong> <span x-text='education.honors'></span></li>
                        <li x-show='education.coursework'><strong>Mata kuliah relevan:</strong> <span x-text='education.coursework'></span></li>
                        <li x-show='education.activities'><strong>Aktivitas:</strong> <span x-text='education.activities'></span></li>
                    </ul>
                </article>
            </template>
        </div>
    </section>

    <section class='cv-document-section' x-show="sections.projects && projects.some((item) => item.name || item.role || item.bullets.some((bullet) => bullet.trim()))">
        <h3>PROYEK</h3>
        <div class='cv-document-list'>
            <template x-for='project in projects' x-bind:key='project.id'>
                <article class='cv-document-item' x-show="project.name || project.role || project.bullets.some((bullet) => bullet.trim())">
                    <div class='cv-document-row'>
                        <strong x-text='project.name'></strong>
                        <strong class='cv-document-date' x-text='project.period'></strong>
                    </div>
                    <p x-show='project.role || project.technologies'><span x-text='project.role'></span><span x-show='project.role && project.technologies'> | </span><span x-text='project.technologies'></span></p>
                    <a class='cv-document-link' x-show='safeUrl(project.url)' x-bind:href='safeUrl(project.url)' target='_blank' rel='noopener noreferrer' x-text='displayUrl(project.url)'></a>
                    <ul x-show='project.bullets.some((bullet) => bullet.trim())'>
                        <template x-for='(bullet, index) in project.bullets.filter((value) => value.trim())' x-bind:key='`${project.id}-preview-${index}`'><li x-text='bullet'></li></template>
                    </ul>
                </article>
            </template>
        </div>
    </section>

    <section class='cv-document-section' x-show="sections.certifications && certifications.some((item) => item.name || item.issuer)">
        <h3>SERTIFIKASI</h3>
        <div class='cv-document-list'>
            <template x-for='certification in certifications' x-bind:key='certification.id'>
                <article class='cv-document-item cv-document-certification' x-show='certification.name || certification.issuer'>
                    <div class='cv-document-row'>
                        <p><strong x-text='certification.name'></strong><span x-show='certification.name && certification.issuer'> — </span><span x-text='certification.issuer'></span></p>
                        <strong class='cv-document-date' x-text='formatMonth(certification.date)'></strong>
                    </div>
                    <p x-show='certification.credentialId'>Credential ID: <span x-text='certification.credentialId'></span></p>
                    <a class='cv-document-link' x-show='safeUrl(certification.url)' x-bind:href='safeUrl(certification.url)' target='_blank' rel='noopener noreferrer' x-text='displayUrl(certification.url)'></a>
                </article>
            </template>
        </div>
    </section>

    <section class='cv-document-section' x-show="sections.skills && skillCategories.some((category) => category.name && category.items.length)">
        <h3>KEAHLIAN</h3>
        <ul class='cv-document-skills'>
            <template x-for='category in skillCategories' x-bind:key='category.id'>
                <li x-show='category.name && category.items.length'><strong x-text="`${category.name}:`"></strong> <span x-text="category.items.join(', ')"></span></li>
            </template>
        </ul>
    </section>
</article>
