export const SAMPLE_CV_DATA = Object.freeze({
    personal: {
        fullName: 'Nadia Prameswari',
        title: 'Fresh Graduate Sistem Informasi',
        city: 'Bandung, Jawa Barat',
        phone: '+62 812 3456 7890',
        email: 'nadia.prameswari@example.com',
        linkedin: 'linkedin.com/in/nadia-prameswari',
        website: 'nadiaprameswari.example.com',
    },
    summary: 'Fresh graduate Sistem Informasi dengan pengalaman magang dalam administrasi data dan pengembangan aplikasi. Terampil menggunakan Excel, SQL, Python, Laravel, dan Power BI untuk mengolah data menjadi informasi yang mudah dipahami. Siap berkontribusi dalam tim yang mengutamakan ketelitian dan perbaikan proses.',
    experiences: [
        {
            organization: 'PT Arunika Data Nusantara',
            position: 'Intern Administrasi Data',
            location: 'Bandung',
            startDate: '2025-01',
            endDate: '2025-06',
            bullets: [
                'Membersihkan dan memvalidasi lebih dari 2.000 baris data operasional menggunakan Microsoft Excel.',
                'Menyusun dashboard mingguan untuk membantu tim memantau ketepatan pemrosesan dokumen.',
            ],
        },
        {
            organization: 'Himpunan Mahasiswa Sistem Informasi',
            position: 'Staf Divisi Teknologi',
            location: 'Bandung',
            startDate: '2023-08',
            endDate: '2024-08',
            bullets: ['Mengelola formulir dan rekap data peserta untuk empat kegiatan organisasi.'],
        },
    ],
    educations: [
        {
            degree: 'S1 Sistem Informasi',
            institution: 'Universitas Cakrawala Indonesia',
            location: 'Bandung',
            startDate: '2021-08',
            graduationDate: '2025-07',
            gpa: '3,78 / 4,00',
            honors: 'Cum Laude',
            coursework: 'Analisis Sistem, Basis Data, Business Intelligence',
            activities: 'Himpunan Mahasiswa Sistem Informasi',
        },
    ],
    projects: [
        {
            name: 'Dashboard Kinerja Penjualan',
            role: 'Data Analyst',
            period: '2024',
            technologies: 'Power BI, Excel, SQL',
            bullets: [
                'Membangun dashboard interaktif untuk membandingkan pencapaian penjualan per wilayah.',
                'Menyusun proses pembersihan data agar laporan dapat diperbarui secara konsisten.',
            ],
        },
        {
            name: 'Aplikasi Inventaris Laboratorium',
            role: 'Full-stack Developer',
            period: '2025',
            technologies: 'Laravel, MySQL, Tailwind CSS',
            bullets: ['Mengembangkan pencatatan barang, riwayat peminjaman, dan laporan stok berbasis web.'],
        },
    ],
    certifications: [
        {
            name: 'Data Analytics Fundamentals',
            issuer: 'Akademi Digital Nusantara',
            date: '2025-03',
            credentialId: 'CONTOH-DA-2025-0142',
        },
    ],
    skillCategories: [
        { name: 'Teknis', items: ['Laravel', 'Python', 'SQL'] },
        { name: 'Perangkat Lunak', items: ['Microsoft Excel', 'Power BI', 'Git'] },
        { name: 'Bahasa', items: ['Indonesia', 'Inggris'] },
        { name: 'Soft Skills', items: ['Analitis', 'Komunikasi', 'Kerja Tim'] },
    ],
    sections: {
        summary: true,
        experience: true,
        education: true,
        projects: true,
        certifications: true,
        skills: true,
    },
});
