<?php

return [
    'version' => '2026.1',

    // Fallback bila akun Kepala Sekolah kosong/berhalangan: izinkan superadmin kelola delegasi & persetujuan
    'allow_superadmin_fallback' => env('KPI_ALLOW_SUPERADMIN_FALLBACK', true),

    'scale' => [
        'min'        => 1,
        'max'        => 5,
        'multiplier' => 20,
        'labels'     => [
            1 => 'Sangat Kurang',
            2 => 'Kurang',
            3 => 'Cukup',
            4 => 'Baik',
            5 => 'Sangat Baik',
        ],
    ],

    'predicates' => [
        ['min' => 90, 'label' => 'Sangat Baik'],
        ['min' => 75, 'label' => 'Baik'],
        ['min' => 60, 'label' => 'Cukup'],
        ['min' => 0,  'label' => 'Kurang'],
    ],

    'categories' => [
        'guru' => [
            'label'   => 'Guru / Pendidik',
            'aspects' => [
                'pedagogik' => [
                    'label'       => 'Kompetensi Pedagogik',
                    'description' => 'Kemampuan merencanakan, melaksanakan, dan mengevaluasi pembelajaran.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'ped_perencanaan', 'label' => 'Perencanaan Pembelajaran', 'description' => 'Menyusun RPP/Modul Ajar sesuai kurikulum dan kebutuhan peserta didik.'],
                        ['code' => 'ped_pengelolaan_kelas', 'label' => 'Pengelolaan Kelas', 'description' => 'Menciptakan suasana belajar yang kondusif, tertib, dan partisipatif.'],
                        ['code' => 'ped_media_teknologi', 'label' => 'Pemanfaatan Media & Teknologi', 'description' => 'Menggunakan media ajar atau teknologi pembelajaran secara efektif.'],
                        ['code' => 'ped_evaluasi_hasil', 'label' => 'Evaluasi & Tindak Lanjut', 'description' => 'Melakukan asesmen objektif serta program remedial/pengayaan.'],
                        ['code' => 'ped_pemahaman_siswa', 'label' => 'Pemahaman Karakteristik Siswa', 'description' => 'Memperhatikan perbedaan kemampuan, minat, dan kebutuhan belajar siswa.'],
                    ],
                ],
                'profesional' => [
                    'label'       => 'Kompetensi Profesional',
                    'description' => 'Penguasaan materi ajar, kelengkapan administrasi, dan pengembangan diri.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'pro_penguasaan_materi', 'label' => 'Penguasaan Materi Ajar', 'description' => 'Menguasai materi, struktur, dan konsep keilmuan mata pelajaran secara mendalam.'],
                        ['code' => 'pro_administrasi', 'label' => 'Kelengkapan Administrasi & Perangkat Ajar', 'description' => 'Mengunggah perangkat pembelajaran dan mengisi jurnal mengajar tepat waktu.'],
                        ['code' => 'pro_pengembangan_diri', 'label' => 'Pengembangan Keprofesian Berkelanjutan', 'description' => 'Aktif mengikuti pelatihan, KKG/MGMP, kajian, atau peningkatan kompetensi.'],
                        ['code' => 'pro_inovasi', 'label' => 'Karya & Inovasi Pembelajaran', 'description' => 'Mengembangkan metode, bahan ajar, atau proyek inovatif di kelas.'],
                    ],
                ],
                'kepribadian' => [
                    'label'       => 'Kompetensi Kepribadian',
                    'description' => 'Keteladanan akhlak, kedisiplinan, kejujuran, dan etos kerja Islami.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'kep_keteladanan', 'label' => 'Keteladanan Akhlak & Adab', 'description' => 'Menunjukkan sikap santun, amanah, dan menjadi teladan bagi peserta didik.'],
                        ['code' => 'kep_kedisiplinan', 'label' => 'Kedisiplinan & Kehadiran', 'description' => 'Hadir tepat waktu, mematuhi jam kerja, dan menjaga konsistensi presensi.'],
                        ['code' => 'kep_tanggung_jawab', 'label' => 'Kejujuran & Tanggung Jawab', 'description' => 'Menyelesaikan amanah dan tugas sekolah secara jujur dan tuntas.'],
                        ['code' => 'kep_etos_kerja', 'label' => 'Etos Kerja & Kematangan Emosi', 'description' => 'Bekerja tenang, stabil, serta menjaga semangat ibadah dan habit harian.'],
                    ],
                ],
                'sosial' => [
                    'label'       => 'Kompetensi Sosial',
                    'description' => 'Komunikasi efektif, kerja sama tim, dan kontribusi di lingkungan sekolah.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'sos_komunikasi', 'label' => 'Komunikasi dengan Siswa & Wali Murid', 'description' => 'Berkomunikasi secara empatik, jelas, dan konstruktif dengan siswa/orang tua.'],
                        ['code' => 'sos_kerjasama', 'label' => 'Kerja Sama Tim & Rekan Sejawat', 'description' => 'Mampu berkolaborasi lintas guru dan unit dalam kegiatan sekolah.'],
                        ['code' => 'sos_kontribusi', 'label' => 'Kontribusi Lingkungan Sekolah', 'description' => 'Berperan aktif dalam kepanitiaan, rapat, dan program sekolah.'],
                        ['code' => 'sos_inklusi', 'label' => 'Sikap Terbuka & Menghargai', 'description' => 'Bersikap objektif, tidak diskriminatif, dan terbuka terhadap masukan.'],
                    ],
                ],
            ],
        ],

        'karyawan' => [
            'label'   => 'Karyawan / Tenaga Kependidikan',
            'aspects' => [
                'kualitas_kuantitas_kerja' => [
                    'label'       => 'Kualitas & Kuantitas Kerja',
                    'description' => 'Ketepatan, ketelitian, volume, dan ketepatan waktu penyelesaian tugas.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'kk_ketelitian', 'label' => 'Ketelitian & Kerapian Hasil', 'description' => 'Hasil kerja akurat, rapi, dan minim kesalahan atau revisi berulang.'],
                        ['code' => 'kk_volume', 'label' => 'Volume & Produktivitas Kerja', 'description' => 'Mampu memenuhi target beban kerja operasional sesuai standar unit.'],
                        ['code' => 'kk_ketepatan_waktu', 'label' => 'Ketepatan Waktu Penyelesaian', 'description' => 'Menyelesaikan tugas, laporan, atau layanan sesuai tenggat waktu.'],
                        ['code' => 'kk_kepatuhan_sop', 'label' => 'Kepatuhan Prosedur & SOP', 'description' => 'Menjalankan alur kerja administrasi/operasional sesuai standar yang berlaku.'],
                    ],
                ],
                'kedisiplinan' => [
                    'label'       => 'Kedisiplinan',
                    'description' => 'Kehadiran, ketepatan waktu, dan kepatuhan terhadap tata tertib.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'dis_kehadiran', 'label' => 'Tingkat Kehadiran', 'description' => 'Konsisten hadir sesuai jadwal kerja tanpa ketidakhadiran tanpa izin.'],
                        ['code' => 'dis_ketepatan_waktu', 'label' => 'Ketepatan Waktu Masuk & Pulang', 'description' => 'Melakukan presensi tepat waktu dan memanfaatkan jam kerja secara efektif.'],
                        ['code' => 'dis_tata_tertib', 'label' => 'Kepatuhan Tata Tertib & Etika', 'description' => 'Mematuhi peraturan sekolah, seragam, dan adab lingkungan kerja Islami.'],
                        ['code' => 'dis_habit', 'label' => 'Konsistensi Habit & Piket', 'description' => 'Menjaga pengisian habit harian serta tanggung jawab jadwal piket.'],
                    ],
                ],
                'inisiatif_tanggung_jawab' => [
                    'label'       => 'Inisiatif & Tanggung Jawab',
                    'description' => 'Sikap proaktif, penyelesaian masalah, dan ketuntasan amanah.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'it_proaktif', 'label' => 'Inisiatif & Proaktif', 'description' => 'Sigap mengambil langkah perbaikan tanpa selalu menunggu instruksi.'],
                        ['code' => 'it_problem_solving', 'label' => 'Penyelesaian Masalah', 'description' => 'Mampu mengidentifikasi kendala operasional dan menawarkan solusi praktis.'],
                        ['code' => 'it_ketuntasan', 'label' => 'Ketuntasan & Keandalan', 'description' => 'Mengawal tugas hingga tuntas dan bertanggung jawab atas hasilnya.'],
                        ['code' => 'it_pengelolaan_aset', 'label' => 'Pemeliharaan Sarana & Dokumen', 'description' => 'Menjaga kerahasiaan data, arsip, serta kebersihan fasilitas kerja.'],
                    ],
                ],
                'pelayanan_kerja_sama' => [
                    'label'       => 'Pelayanan & Kerja Sama',
                    'description' => 'Layanan prima kepada stakeholder, komunikasi, dan kolaborasi tim.',
                    'weight'      => 0.25,
                    'indicators'  => [
                        ['code' => 'pk_layanan', 'label' => 'Kualitas Layanan Stakeholder', 'description' => 'Memberikan pelayanan ramah, cepat, dan solutif bagi guru, siswa, dan wali murid.'],
                        ['code' => 'pk_komunikasi', 'label' => 'Komunikasi Kerja', 'description' => 'Menyampaikan informasi secara jelas, sopan, dan responsif.'],
                        ['code' => 'pk_kolaborasi', 'label' => 'Kolaborasi Lintas Unit', 'description' => 'Bersedia membantu rekan kerja dan menjaga kekompakan tim.'],
                        ['code' => 'pk_adaptasi', 'label' => 'Adaptasi & Penerimaan Masukan', 'description' => 'Terbuka terhadap umpan balik dan cepat beradaptasi dengan kebutuhan baru.'],
                    ],
                ],
            ],
        ],
    ],
];
