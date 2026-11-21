<?php

/**
 * Generator for VOWSPACE System Documentation (.docx) and Draw.io Diagrams (.drawio).
 */

require __DIR__.'/../vendor/autoload.php';

$schemaData = json_decode(file_get_contents(__DIR__.'/../docs/schema_dump.json'), true);

// ==========================================
// 1. GENERATE MICROSOFT WORD (.DOCX)
// ==========================================

class DocxBuilder
{
    private string $xml = '';

    public function __construct()
    {
        $this->xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.
            '<w:body>';
    }

    public function addTitle(string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="360" w:after="120"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="283640"/><w:sz w:val="48"/></w:rPr>'.
            '<w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addSubtitle(string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="60" w:after="360"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:i/><w:color w:val="725A40"/><w:sz w:val="28"/></w:rPr>'.
            '<w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addHeading1(string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:spacing w:before="400" w:after="140"/><w:pBdr><w:bottom w:val="single" w:sz="8" w:space="4" w:color="725A40"/></w:pBdr></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="283640"/><w:sz w:val="34"/></w:rPr>'.
            '<w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addHeading2(string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:spacing w:before="260" w:after="100"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="725A40"/><w:sz w:val="26"/></w:rPr>'.
            '<w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addHeading3(string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:spacing w:before="180" w:after="80"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="336956"/><w:sz w:val="22"/></w:rPr>'.
            '<w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addParagraph(string $text, bool $bold = false): void
    {
        $b = $bold ? '<w:b/>' : '';
        $this->xml .= '<w:p><w:pPr><w:spacing w:before="60" w:after="100" w:line="276" w:lineRule="auto"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="333333"/><w:sz w:val="22"/>'.$b.'</w:rPr>'.
            '<w:t xml:space="preserve">'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addBullet(string $boldPrefix, string $text): void
    {
        $this->xml .= '<w:p><w:pPr><w:ind w:left="360" w:hanging="240"/><w:spacing w:before="40" w:after="60"/></w:pPr>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="725A40"/><w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">•  '.htmlspecialchars($boldPrefix).': </w:t></w:r>'.
            '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="333333"/><w:sz w:val="22"/></w:rPr><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>';
    }

    public function addTable(array $headers, array $rows, array $widths = []): void
    {
        $this->xml .= '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'.
            '<w:tblBorders>'.
            '<w:top w:val="single" w:sz="6" w:space="0" w:color="CCD1CD"/>'.
            '<w:left w:val="single" w:sz="6" w:space="0" w:color="CCD1CD"/>'.
            '<w:bottom w:val="single" w:sz="6" w:space="0" w:color="CCD1CD"/>'.
            '<w:right w:val="single" w:sz="6" w:space="0" w:color="CCD1CD"/>'.
            '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="EAE7DF"/>'.
            '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="EAE7DF"/>'.
            '</w:tblBorders>'.
            '<w:tblCellMar><w:top w:w="120" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:left w:w="160" w:type="dxa"/><w:right w:w="160" w:type="dxa"/></w:tblCellMar>'.
            '</w:tblPr>';

        // Header Row
        $this->xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($headers as $idx => $header) {
            $w = isset($widths[$idx]) ? '<w:tcW w:w="'.$widths[$idx].'" w:type="dxa"/>' : '';
            $this->xml .= '<w:tc><w:tcPr>'.$w.'<w:shd w:val="clear" w:color="auto" w:fill="283640"/></w:tcPr>'.
                '<w:p><w:pPr><w:spacing w:before="60" w:after="60"/></w:pPr>'.
                '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="F6F4F0"/><w:sz w:val="20"/></w:rPr>'.
                '<w:t>'.htmlspecialchars($header).'</w:t></w:r></w:p></w:tc>';
        }
        $this->xml .= '</w:tr>';

        // Data Rows
        foreach ($rows as $rowIndex => $row) {
            $fill = ($rowIndex % 2 === 1) ? 'F9F8F6' : 'FFFFFF';
            $this->xml .= '<w:tr>';
            foreach ($row as $idx => $cell) {
                $w = isset($widths[$idx]) ? '<w:tcW w:w="'.$widths[$idx].'" w:type="dxa"/>' : '';
                $this->xml .= '<w:tc><w:tcPr>'.$w.'<w:shd w:val="clear" w:color="auto" w:fill="'.$fill.'"/></w:tcPr>'.
                    '<w:p><w:pPr><w:spacing w:before="40" w:after="40"/></w:pPr>'.
                    '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="333333"/><w:sz w:val="18"/></w:rPr>'.
                    '<w:t>'.htmlspecialchars($cell).'</w:t></w:r></w:p></w:tc>';
            }
            $this->xml .= '</w:tr>';
        }

        $this->xml .= '</w:tbl><w:p><w:pPr><w:spacing w:before="120" w:after="120"/></w:pPr></w:p>';
    }

    public function save(string $filePath): void
    {
        $this->xml .= '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'.
            '</w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'.
            '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'.
            '</Relationships>';

        $zip = new ZipArchive;
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create zip archive: {$filePath}");
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addEmptyDir('_rels');
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addEmptyDir('word');
        $zip->addFromString('word/document.xml', $this->xml);

        $zip->close();
    }
}

// Instantiate Docx Builder
$docx = new DocxBuilder;

// TITLE & METADATA
$docx->addTitle('DOKUMEN SPESIFIKASI ALUR SISTEM DAN PERANCANGAN BASIS DATA');
$docx->addSubtitle("VOWSPACE (Singgah Guestbook) — Ekosistem Pernikahan Digital Terintegrasi\nDisusun secara Otomatis Berdasarkan Arsitektur Basis Data & Sistem Aktual");

// CHAPTER 1
$docx->addHeading1('BAB 1: GAMBARAN UMUM SISTEM');
$docx->addParagraph('VOWSPACE (Singgah Guestbook) adalah sistem ekosistem digital komprehensif untuk acara pernikahan yang mencakup pengelolaan undangan personal, RSVP dinamis, check-in tamu berbasis QR code pass berkeamanan tinggi, love wall interaktif untuk venue, galeri kenangan digital, interaksi live (polling, game quiz, song request), serta pencatatan amplop digital (gift accounts).');
$docx->addParagraph('Aplikasi dibangun di atas framework Laravel 13 dengan arsitektur multi-tenant berbasis event (event-scoped tenancy), CSS Tailwind v4, basis data SQLite / MySQL, dan interaktivitas frontend berbasis Alpine.js.');

$docx->addHeading2('1.1 Aktor dan Peran dalam Sistem');
$docx->addBullet('Superadmin / Event Organizer (EO)', 'Memiliki wewenang tertinggi untuk membuat event, mengatur jadwal (schedule) dan denah meja (seating), mengimpor tamu (CSV), menerbitkan token undangan & QR pass, mengoperasikan scanner check-in di meja resepsionis, melakukan moderasi pesan buku tamu & galeri foto, serta mencetak laporan rekapitulasi (PDF/CSV) dan audit log.');
$docx->addBullet('Owner (Pasangan Pengantin)', 'Memiliki akses khusus untuk memantau persiapan pernikahan melalui dashboard pribadi (/owner). Owner dapat melihat rekap kehadiran tamu secara real-time, membaca dan menandai pesan doa favorit dari para tamu, mengunduh arsip ucapan, membuat album kenangan, serta memperbarui cerita cinta (love story) mereka tanpa dapat mengubah data teknis tamu atau melewati moderasi.');
$docx->addBullet('Guest (Tamu Undangan)', 'Mengakses sistem melalui tautan personal ber-token aman (/invite/{token}) tanpa memerlukan username dan password. Tamu dapat melihat detail acara, melakukan konfirmasi RSVP dengan batas kuota pendamping, mengunduh QR Pass digital untuk tiket masuk, menulis ucapan buku tamu, mengunggah foto ke galeri bersama, mengikuti polling & quiz, me-request lagu, serta memberikan hadiah digital melalui QRIS atau transfer bank.');
$docx->addBullet('Public Visitor', 'Pengunjung umum yang mengakses tautan publik pernikahan (/e/{slug}) untuk melihat profil acara, atau audiens di venue yang menyaksikan layar proyektor Live Love Wall (/wall/{slug}) yang menampilkan pesan doa secara real-time.');

// CHAPTER 2
$docx->addHeading1('BAB 2: ALUR KERJA SISTEM (SYSTEM WORKFLOWS)');

$docx->addHeading2('2.1 Alur Manajemen Acara dan Penerbitan Token (Superadmin)');
$docx->addParagraph('Alur kerja Superadmin dimulai dari inisialisasi acara hingga siap didistribusikan kepada tamu:');
$docx->addBullet('Langkah 1 (Pembuatan Event)', 'Superadmin login ke panel /admin lalu membuat event baru melalui EventService::create(). Sistem membuat record event, venue utama, kategori default (Keluarga, Teman, Kolega), dan menginisialisasi setelan fitur.');
$docx->addBullet('Langkah 2 (Penetapan Akun Owner)', 'Superadmin menghubungkan akun email mempelai pria dan wanita melalui tabel relasi event_user sehingga pasangan dapat login ke portal /owner.');
$docx->addBullet('Langkah 3 (Pendaftaran Tamu & Penerbitan Token Otomatis)', 'Superadmin menambahkan tamu secara manual atau massal via impor CSV. Setiap kali data tamu disimpan, InvitationService::issueForGuest() secara otomatis menerbitkan 2 jenis token aman berkriptografi SHA-256: token undangan personal (invitation) dan token tiket masuk (QR code). Data undangan dicatat pada tabel invitations.');
$docx->addBullet('Langkah 4 (Distribusi Undangan)', 'Superadmin menyebarkan tautan personal ke tamu melalui integrasi pesan WhatsApp langsung atau tombol salin tautan.');

$docx->addHeading2('2.2 Alur Pengalaman Tamu (Guest Journey & RSVP)');
$docx->addParagraph('Alur interaksi tamu dirancang sangat mulus tanpa registrasi akun konvensional:');
$docx->addBullet('Langkah 1 (Akses Token)', 'Tamu membuka tautan /invite/{token}. Middleware ResolveGuestToken memverifikasi hash SHA-256 dari token. Jika valid dan belum kedaluwarsa, sistem membuka undangan dan mencatat open_count serta memperbarui status menjadi link_opened.');
$docx->addBullet('Langkah 2 (Pengisian RSVP)', 'Tamu memilih status kehadiran (Confirmed / Declined / Maybe), memasukkan jumlah hadir (dibatasi oleh kuota max_attendees), mendaftarkan nama pendamping, dan preferensi diet. Sistem memvalidasi batas waktu deadline RSVP dan mengunci data ke tabel rsvps.');
$docx->addBullet('Langkah 3 (Penerbitan Digital QR Pass)', 'Setelah mengonfirmasi hadir, tamu dapat membuka menu Digital Pass (/invite/{token}/pass) dan mengunduh gambar kartu pass QR (PNG) ke ponsel mereka untuk ditunjukkan saat tiba di resepsionis.');

$docx->addHeading2('2.3 Alur Operasional Check-in di Meja Resepsi (Door Check-in)');
$docx->addParagraph('Proses check-in mengedepankan kecepatan dan proteksi anti-duplikasi tingkat tinggi:');
$docx->addBullet('Langkah 1 (Scanning QR)', 'Petugas resepsionis membuka aplikasi kamera scanner di /admin/checkins/scanner. Petugas mengarahkan kamera ke QR Pass tamu.');
$docx->addBullet('Langkah 2 (Validasi Kriptografi)', 'Payload QR dikirim melalui request POST /admin/checkins/scan. Sistem memverifikasi kecocokan hash SHA-256, status token aktif, dan kesesuaian event_id.');
$docx->addBullet('Langkah 3 (Proteksi Duplikasi & Concurrency Lock)', 'Sistem menerapkan database row-lock dan constraint unik unique(event_id, guest_id) pada tabel checkins. Jika QR yang sama di-scan dua kali oleh petugas berbeda, sistem secara instan menolak dengan status duplicate.');
$docx->addBullet('Langkah 4 (Pencatatan Kehadiran & Audit Trail)', "Jika valid, data check-in disimpan dengan metode 'qr', waktu timestamp akurat, dan alamat IP petugas. Sistem menampilkan nomor meja dan ucapan selamat datang di layar petugas.");

$docx->addHeading2('2.4 Alur Moderasi Konten & Live Love Wall');
$docx->addParagraph('Untuk menjaga kenyamanan dan kesantunan acara, konten tamu dimoderasi secara terpusat:');
$docx->addBullet('Pengiriman Pesan', "Tamu mengirim ucapan melalui form buku tamu. Ucapan publik secara otomatis berstatus 'pending'.");
$docx->addBullet('Moderasi Superadmin', 'Di panel /admin/guestbook, Superadmin dapat melakukan Approve, Reject, Pin (sematkan di atas), atau Hide.');
$docx->addBullet('Tayangan Layar Proyektor', 'Layar besar di venue membuka /wall/{slug}. Komponen Live Wall melakukan polling AJAX berkala ke endpoint /wall/{slug}/messages?since_id=x dan secara otomatis memunculkan pesan-pesan baru yang telah disetujui (approved) dengan animasi transisi yang elegan.');

// CHAPTER 3
$docx->addHeading1('BAB 3: PERANCANGAN BASIS DATA & KAMUS DATA (DATA DICTIONARY)');
$docx->addParagraph('Struktur basis data VOWSPACE dirancang dengan prinsip Event Scoping — setiap tabel yang berhubungan dengan data tamu dan operasional wajib memiliki foreign key event_id untuk menjamin isolasi data antar pernikahan (multi-tenancy level database).');

$tableDescriptions = [
    'users' => 'Menyimpan data pengguna terdaftar (Superadmin dan Owner/Pengantin). Mendukung autentikasi, role-based access control, dan status aktif.',
    'organizations' => 'Menyimpan data organisasi atau vendor wedding organizer yang mengelola banyak pernikahan.',
    'organization_memberships' => 'Tabel pivot keanggotaan user dalam organisasi beserta peran (owner, admin, member).',
    'events' => 'Entitas pusat sistem. Menyimpan detail pernikahan seperti nama mempelai, tanggal acara, slug publik, status, dan setting privasi.',
    'event_venues' => 'Menyimpan informasi lokasi/gedung pernikahan, alamat lengkap, tautan Google Maps, dan kapasitas gedung.',
    'event_schedules' => 'Menyimpan rangkaian jadwal acara/run-down pernikahan (misal: Akad Nikah, Resepsi, Ramah Tamah).',
    'event_settings' => 'Konfigurasi fitur dinamis per event (fitur rsvp aktif, galeri aktif, kado aktif, game aktif).',
    'gift_accounts' => 'Menyimpan informasi rekening bank, dompet digital, nama penerima, atau gambar QRIS untuk amplop digital.',
    'guest_categories' => 'Pengelompokan kategori tamu undangan (misal: Keluarga Inti, Sahabat, Rekan Kerja, VIP).',
    'guest_groups' => 'Pengelompokan rombongan tamu (misal: Rombongan Pengantin Pria, Alumni Kampus).',
    'guests' => 'Data induk tamu undangan. Menyimpan nama personal, kontak, jatah kuota pendamping, dan status rsvp.',
    'guest_tokens' => 'Menyimpan hash token keamanan SHA-256 untuk akses tautan web (/invite) dan kode QR tiket masuk.',
    'invitations' => 'Mencatat status distribusi undangan fisik/digital ke tamu, jumlah klik buka tautan, dan waktu kirim.',
    'rsvps' => 'Mencatat respons kehadiran tamu, jumlah orang yang akan hadir, daftar nama pendamping, dan catatan diet.',
    'checkins' => 'Mencatat kehadiran aktual tamu di pintu masuk (pintu registrasi), metode (QR/Manual), waktu, dan IP petugas.',
    'guestbook_messages' => 'Menyimpan pesan ucapan, doa restu, visibilitas (publik/privat), dan status moderasi.',
    'guestbook_favorites' => 'Menyimpan penandaan pesan favorit yang dipilih oleh pasangan pengantin (owner).',
    'media_albums' => 'Kategori album foto kenangan pernikahan yang dibuat oleh owner atau admin.',
    'media_assets' => 'Menyimpan metadata foto yang diunggah oleh tamu atau owner, path berkas, status moderasi, dan featured.',
    'seating_tables' => 'Daftar meja resepsi, nomor/nama meja, kapasitas kursi, dan area penempatan (zone).',
    'table_assignments' => 'Penempatan tamu ke meja resepsi tertentu dengan proteksi 1 tamu = 1 meja.',
    'polls' => 'Pertanyaan polling interaktif yang dibuat untuk menghibur tamu undangan selama acara.',
    'poll_options' => 'Pilihan jawaban untuk setiap pertanyaan polling.',
    'poll_responses' => 'Suara voting yang dimasukkan oleh tamu dengan proteksi 1 vote per tamu per polling.',
    'games' => 'Permainan quiz mini-game interaktif seputar kisah cinta kedua mempelai.',
    'game_questions' => 'Daftar pertanyaan quiz beserta poin nilai dan durasi pengerjaan.',
    'game_options' => 'Opsi pilihan ganda untuk setiap pertanyaan quiz beserta penanda kunci jawaban.',
    'game_attempts' => 'Mencatat sesi pengerjaan quiz oleh tamu beserta total skor dan waktu selesai.',
    'game_answers' => 'Jawaban spesifik yang dipilih oleh tamu pada setiap butir soal.',
    'song_requests' => 'Permintaan lagu dari tamu untuk dimainkan oleh pemusik / band / DJ pernikahan.',
    'event_stations' => 'Pos operasional pada venue acara (misal: Resepsi Pintu Utama, Meja Souvenir, Pintu VIP).',
    'event_tasks' => 'Daftar tugas operasional panitia/staff dengan penanggung jawab, tenggat, dan status.',
    'event_incidents' => 'Pencatatan laporan insiden atau kendala operasional lapangan selama acara berlangsung.',
    'event_announcements' => 'Pengumuman dan instruksi siaran cepat dari pimpinan operasional kepada seluruh panitia.',
    'audit_logs' => 'Pencatatan rekam jejak audit komprehensif atas setiap aksi sensitif (login, scan, moderasi, update data).',
];

foreach ($schemaData as $tableName => $columns) {
    $docx->addHeading2('3.'.(array_search($tableName, array_keys($schemaData)) + 1)." Tabel: `{$tableName}`");
    $desc = $tableDescriptions[$tableName] ?? 'Tabel pendukung arsitektur sistem VOWSPACE.';
    $docx->addParagraph($desc);

    $headers = ['Nama Kolom', 'Tipe Data', 'Primary Key', 'Not Null', 'Keterangan / Constraint'];
    $rows = [];
    foreach ($columns as $col) {
        $pk = $col['pk'] ? 'YA' : 'TIDAK';
        $nn = $col['notnull'] ? 'YA' : 'TIDAK';
        $extra = '';
        if ($col['name'] === 'id') {
            $extra = 'Identifier Unik (Auto Increment)';
        } elseif (str_ends_with($col['name'], '_id')) {
            $extra = 'Foreign Key ke tabel relasi';
        } elseif ($col['default'] !== null) {
            $extra = 'Default: '.$col['default'];
        } elseif ($col['name'] === 'status') {
            $extra = 'State machine / Enum status';
        } elseif (str_contains($col['name'], 'token')) {
            $extra = 'Kriptografi SHA-256 Hash';
        } elseif (str_contains($col['name'], 'at')) {
            $extra = 'Timestamp tanggal & waktu';
        }

        $rows[] = [
            $col['name'],
            $col['type'],
            $pk,
            $nn,
            $extra,
        ];
    }
    $docx->addTable($headers, $rows, [1800, 1400, 1000, 1000, 2800]);
}

// CHAPTER 4
$docx->addHeading1('BAB 4: PANDUAN PENGGUNAAN DIAGRAM DRAW.IO');
$docx->addParagraph('Seluruh diagram UML untuk perancangan sistem ini telah di-generate secara native dalam format file XML draw.io (.drawio) di dalam direktori `docs/diagrams/`:');
$docx->addBullet('docs/diagrams/usecase.drawio', 'Diagram Use Case lengkap mencakup Superadmin, Owner, Guest, dan Public Visitor beserta seluruh usecase-nya.');
$docx->addBullet('docs/diagrams/activity.drawio', 'Diagram Aktivitas (Activity Diagram) alur konfirmasi RSVP dan alur operasional scan QR check-in di meja resepsi.');
$docx->addBullet('docs/diagrams/class_diagram.drawio', 'Class Diagram komprehensif menampilkan entitas Eloquent Model, atribut, method, dan relasi kardinalitas (1 to 1, 1 to many, many to many).');
$docx->addBullet('docs/diagrams/sequence_diagram.drawio', 'Diagram Sequence yang memvisualisasikan interaksi pesan antar objek untuk proses Validasi & Scan QR Pass serta Pengiriman RSVP.');
$docx->addBullet('docs/diagrams/vowspace_all_diagrams.drawio', 'File Master Multi-Page yang menggabungkan keempat diagram di atas ke dalam tab-tab terpisah dalam satu berkas kerja Draw.io.');

$docx->addHeading2('4.1 Cara Membuka di Draw.io');
$docx->addBullet('Metode 1 (Web Draw.io)', "Buka browser ke https://app.diagrams.net (atau https://app.diagrams.net). Pilih 'Open Existing Diagram', lalu pilih salah satu file .drawio yang tersedia di folder `docs/diagrams/`.");
$docx->addBullet('Metode 2 (Aplikasi Desktop Draw.io)', 'Buka Draw.io Desktop, klik menu File -> Open, dan arahkan ke file .drawio.');
$docx->addBullet('Metode 3 (Drag and Drop)', 'Tarik (drag) langsung file .drawio dari Windows File Explorer dan jatuhkan (drop) ke kanvas browser Draw.io yang sedang terbuka.');

$docxFilePath = __DIR__.'/../docs/vowspace_sistem_dan_database.docx';
$docx->save($docxFilePath);
echo 'Word Document generated: '.$docxFilePath.PHP_EOL;

// ==========================================
// 2. GENERATE DRAW.IO DIAGRAMS (.drawio XML)
// ==========================================

function wrapDrawioDiagram(string $diagramName, string $contentXml): string
{
    return '<mxfile host="app.diagrams.net">'.
        '<diagram id="'.md5($diagramName).'" name="'.htmlspecialchars($diagramName).'">'.
        '<mxGraphModel dx="1422" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1600" pageHeight="1100" math="0" shadow="0">'.
        '<root>'.
        '<mxCell id="0"/>'.
        '<mxCell id="1" parent="0"/>'.
        $contentXml.
        '</root>'.
        '</mxGraphModel>'.
        '</diagram>'.
        '</mxfile>';
}

// ------------------------------------------
// 2.1 USE CASE DIAGRAM XML
// ------------------------------------------
$ucXml = '';
// System Boundary
$ucXml .= '<mxCell id="sys_box" value="SISTEM VOWSPACE (SINGGAH GUESTBOOK)" style="shape=rect;rounded=1;arcSize=4;fillColor=#F6F4F0;strokeColor=#725A40;strokeWidth=2;fontStyle=1;fontSize=14;fontColor=#283640;verticalAlign=top;spacingTop=15;" vertex="1" parent="1">'.
    '<mxGeometry x="280" y="40" width="840" height="980" as="geometry"/>'.
    '</mxCell>';

// Actors
$actors = [
    ['id' => 'act_admin', 'label' => "Superadmin / EO\n(Event Organizer)", 'x' => 60, 'y' => 240],
    ['id' => 'act_owner', 'label' => "Owner\n(Mempelai)", 'x' => 60, 'y' => 680],
    ['id' => 'act_guest', 'label' => "Guest\n(Tamu Undangan)", 'x' => 1200, 'y' => 380],
    ['id' => 'act_public', 'label' => "Public Visitor\n(Audiens Venue)", 'x' => 1200, 'y' => 800],
];

foreach ($actors as $act) {
    $ucXml .= '<mxCell id="'.$act['id'].'" value="'.htmlspecialchars($act['label']).'" style="shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;fillColor=#D7E2EB;strokeColor=#283640;strokeWidth=1.5;fontStyle=1;fontColor=#283640;fontSize=12;" vertex="1" parent="1">'.
        '<mxGeometry x="'.$act['x'].'" y="'.$act['y'].'" width="50" height="90" as="geometry"/>'.
        '</mxCell>';
}

// Use Cases
$useCases = [
    // Admin Use Cases
    ['id' => 'uc_event_mgmt', 'label' => 'Kelola Data Event, Venue & Jadwal', 'x' => 320, 'y' => 80],
    ['id' => 'uc_owner_sync', 'label' => 'Hubungkan Akun Mempelai (Owner)', 'x' => 320, 'y' => 150],
    ['id' => 'uc_guest_mgmt', 'label' => 'Kelola Tamu & Impor CSV', 'x' => 320, 'y' => 220],
    ['id' => 'uc_token_gen', 'label' => 'Terbitkan Token & QR Code Pass', 'x' => 320, 'y' => 290],
    ['id' => 'uc_rsvp_admin', 'label' => 'Monitor & Override Data RSVP', 'x' => 320, 'y' => 360],
    ['id' => 'uc_scan_qr', 'label' => 'Scan QR Pass & Check-in Manual', 'x' => 320, 'y' => 430],
    ['id' => 'uc_seating', 'label' => 'Kelola Meja & Penempatan Kursi', 'x' => 320, 'y' => 500],
    ['id' => 'uc_mod_wishes', 'label' => 'Moderasi Pesan Buku Tamu', 'x' => 320, 'y' => 570],
    ['id' => 'uc_mod_media', 'label' => 'Moderasi Galeri & Foto Tamu', 'x' => 320, 'y' => 640],
    ['id' => 'uc_reports', 'label' => 'Ekspor Laporan (PDF, CSV) & Audit Log', 'x' => 320, 'y' => 710],
    ['id' => 'uc_operations', 'label' => 'Operasional Lapangan (Task & Incident)', 'x' => 320, 'y' => 780],

    // Owner Use Cases
    ['id' => 'uc_owner_dash', 'label' => 'Lihat Dashboard & Statistik Acara', 'x' => 480, 'y' => 850],
    ['id' => 'uc_owner_fav', 'label' => 'Baca Doa Tamu & Tandai Favorit', 'x' => 480, 'y' => 920],
    ['id' => 'uc_owner_memories', 'label' => 'Lihat Galeri Foto & Buat Album', 'x' => 700, 'y' => 850],
    ['id' => 'uc_owner_story', 'label' => 'Kelola Profil & Kisah Cinta (Story)', 'x' => 700, 'y' => 920],

    // Guest Use Cases
    ['id' => 'uc_open_invite', 'label' => 'Buka Undangan via Token Unik', 'x' => 800, 'y' => 100],
    ['id' => 'uc_submit_rsvp', 'label' => 'Konfirmasi Kehadiran (RSVP)', 'x' => 800, 'y' => 170],
    ['id' => 'uc_dl_pass', 'label' => 'Unduh Digital Pass QR Tiket Masuk', 'x' => 800, 'y' => 240],
    ['id' => 'uc_send_wish', 'label' => 'Kirim Ucapan Buku Tamu (Publik/Privat)', 'x' => 800, 'y' => 310],
    ['id' => 'uc_up_photo', 'label' => 'Unggah Foto Momen ke Galeri', 'x' => 800, 'y' => 380],
    ['id' => 'uc_send_gift', 'label' => 'Kirim Kado / Amplop Digital (QRIS)', 'x' => 800, 'y' => 450],
    ['id' => 'uc_vote_poll', 'label' => 'Ikuti Polling Interaktif', 'x' => 800, 'y' => 520],
    ['id' => 'uc_play_quiz', 'label' => 'Mainkan Mini Game Quiz Mempelai', 'x' => 800, 'y' => 590],
    ['id' => 'uc_req_song', 'label' => 'Request Lagu ke Band / DJ', 'x' => 800, 'y' => 660],

    // Public Use Cases
    ['id' => 'uc_pub_landing', 'label' => 'Akses Halaman Landing Publik Event', 'x' => 800, 'y' => 750],
    ['id' => 'uc_live_wall', 'label' => 'Tonton Live Love Wall di Proyektor Venue', 'x' => 800, 'y' => 820],
];

foreach ($useCases as $uc) {
    $ucXml .= '<mxCell id="'.$uc['id'].'" value="'.htmlspecialchars($uc['label']).'" style="ellipse;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;strokeWidth=1.5;fontColor=#283640;fontSize=11;fontStyle=1;" vertex="1" parent="1">'.
        '<mxGeometry x="'.$uc['x'].'" y="'.$uc['y'].'" width="240" height="50" as="geometry"/>'.
        '</mxCell>';
}

// Connections Admin -> Usecases
$adminUcs = ['uc_event_mgmt', 'uc_owner_sync', 'uc_guest_mgmt', 'uc_token_gen', 'uc_rsvp_admin', 'uc_scan_qr', 'uc_seating', 'uc_mod_wishes', 'uc_mod_media', 'uc_reports', 'uc_operations'];
foreach ($adminUcs as $ucId) {
    $ucXml .= '<mxCell id="edge_adm_'.$ucId.'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#283640;strokeWidth=1.2;html=1;endArrow=none;" edge="1" parent="1" source="act_admin" target="'.$ucId.'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

// Connections Owner -> Usecases
$ownerUcs = ['uc_owner_dash', 'uc_owner_fav', 'uc_owner_memories', 'uc_owner_story'];
foreach ($ownerUcs as $ucId) {
    $ucXml .= '<mxCell id="edge_own_'.$ucId.'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#725A40;strokeWidth=1.2;html=1;endArrow=none;" edge="1" parent="1" source="act_owner" target="'.$ucId.'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

// Connections Guest -> Usecases
$guestUcs = ['uc_open_invite', 'uc_submit_rsvp', 'uc_dl_pass', 'uc_send_wish', 'uc_up_photo', 'uc_send_gift', 'uc_vote_poll', 'uc_play_quiz', 'uc_req_song'];
foreach ($guestUcs as $ucId) {
    $ucXml .= '<mxCell id="edge_gst_'.$ucId.'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#336956;strokeWidth=1.2;html=1;endArrow=none;" edge="1" parent="1" source="act_guest" target="'.$ucId.'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

// Connections Public -> Usecases
$publicUcs = ['uc_pub_landing', 'uc_live_wall'];
foreach ($publicUcs as $ucId) {
    $ucXml .= '<mxCell id="edge_pub_'.$ucId.'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#8B4D55;strokeWidth=1.2;html=1;endArrow=none;" edge="1" parent="1" source="act_public" target="'.$ucId.'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

file_put_contents(__DIR__.'/../docs/diagrams/usecase.drawio', wrapDrawioDiagram('Use Case Diagram - VOWSPACE', $ucXml));

// ------------------------------------------
// 2.2 ACTIVITY DIAGRAM XML
// ------------------------------------------
$actXml = '';
// Partition 1: Tamu (Guest) RSVP Flow
$actXml .= '<mxCell id="swim_guest" value="Tamu (Guest)" style="shape=swimlane;whiteSpace=wrap;html=1;startSize=26;fillColor=#EAE7DF;strokeColor=#725A40;fontStyle=1;fontColor=#283640;" vertex="1" parent="1">'.
    '<mxGeometry x="40" y="40" width="340" height="980" as="geometry"/>'.
    '</mxCell>';

// Partition 2: Sistem VOWSPACE (Backend & Middleware)
$actXml .= '<mxCell id="swim_system" value="Sistem Backend VOWSPACE" style="shape=swimlane;whiteSpace=wrap;html=1;startSize=26;fillColor=#F6F4F0;strokeColor=#725A40;fontStyle=1;fontColor=#283640;" vertex="1" parent="1">'.
    '<mxGeometry x="380" y="40" width="400" height="980" as="geometry"/>'.
    '</mxCell>';

// Partition 3: Petugas Resepsionis (Staff)
$actXml .= '<mxCell id="swim_staff" value="Petugas Resepsionis / Admin" style="shape=swimlane;whiteSpace=wrap;html=1;startSize=26;fillColor=#D7E2EB;strokeColor=#283640;fontStyle=1;fontColor=#283640;" vertex="1" parent="1">'.
    '<mxGeometry x="780" y="40" width="380" height="980" as="geometry"/>'.
    '</mxCell>';

// Flow Elements - RSVP
$actXml .= '<mxCell id="a_start1" value="" style="ellipse;fillColor=#283640;strokeColor=none;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="155" y="40" width="30" height="30" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step1" value="Klik Tautan Personal&#xa;/invite/{token}" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;fontStyle=1;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="95" y="90" width="150" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step2" value="Middleware ResolveGuestToken:&#xa;Hash SHA-256 &amp; Validasi Token" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="100" y="90" width="200" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_dec1" value="Token Valid?" style="rhombus;whiteSpace=wrap;html=1;fillColor=#E5DFD4;strokeColor=#725A40;fontStyle=1;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="140" y="170" width="120" height="60" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step_err" value="Tampilkan Halaman 404&#xa;Token Invalid / Expired" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#8B4D55;strokeColor=#none;fontColor=#FFFFFF;fontStyle=1;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="250" y="250" width="140" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step3" value="Render Halaman Undangan &amp;&#xa;Catat Status link_opened" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="100" y="260" width="200" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step4" value="Buka Form RSVP &amp; Isi Kehadiran&#xa;(Status, Jumlah Tamu, Catatan)" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="85" y="340" width="170" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step5" value="Kirim POST /rsvp" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;fontStyle=1;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="105" y="420" width="130" height="40" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step6" value="Validasi Kuota max_attendees &amp;&#xa;Simpan ke Tabel rsvps" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="100" y="415" width="200" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step7" value="Generate Digital QR Pass&#xa;via Simple-QRCode PNG/SVG" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="100" y="490" width="200" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step8" value="Simpan / Unduh QR Pass&#xa;ke Ponsel Tamu" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#336956;strokeColor=#none;fontColor=#FFFFFF;fontStyle=1;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="95" y="490" width="150" height="50" as="geometry"/>'.
    '</mxCell>';

// Flow Elements - Check-in
$actXml .= '<mxCell id="a_step9" value="Tamu Tiba di Gedung &amp;&#xa;Tunjukkan QR Pass" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_guest">'.
    '<mxGeometry x="95" y="580" width="150" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step10" value="Buka Scanner Kamera di&#xa;/admin/checkins/scanner" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#283640;fontColor=#283640;" vertex="1" parent="swim_staff">'.
    '<mxGeometry x="100" y="580" width="180" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step11" value="Kamera Mendeteksi QR &amp;&#xa;Kirim POST /checkins/scan" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#283640;fontColor=#283640;fontStyle=1;" vertex="1" parent="swim_staff">'.
    '<mxGeometry x="100" y="650" width="180" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step12" value="Kunci Baris Database (Row Lock)&#xa;&amp; Verifikasi Hash Token" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="100" y="650" width="200" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_dec2" value="Sudah Pernah&#xa;Check-in?" style="rhombus;whiteSpace=wrap;html=1;fillColor=#E5DFD4;strokeColor=#725A40;fontStyle=1;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="140" y="730" width="120" height="60" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step_dup" value="Tolak Check-in (Status: duplicate)&#xa;&amp; Catat Audit Security" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#8B4D55;strokeColor=#none;fontColor=#FFFFFF;fontStyle=1;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="240" y="810" width="150" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step13" value="Simpan ke Tabel checkins,&#xa;Update checked_in_at &amp; Log" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#725A40;fontColor=#283640;" vertex="1" parent="swim_system">'.
    '<mxGeometry x="40" y="810" width="190" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_step14" value="Layar Scanner Menampilkan&#xa;Nama Tamu &amp; Nomor Meja" style="rounded=1;whiteSpace=wrap;html=1;fillColor=#336956;strokeColor=#none;fontColor=#FFFFFF;fontStyle=1;" vertex="1" parent="swim_staff">'.
    '<mxGeometry x="100" y="880" width="180" height="50" as="geometry"/>'.
    '</mxCell>';

$actXml .= '<mxCell id="a_end" value="" style="ellipse;shape=doubleEllipse;fillColor=#283640;strokeColor=#FFFFFF;strokeWidth=2;" vertex="1" parent="swim_staff">'.
    '<mxGeometry x="175" y="945" width="30" height="30" as="geometry"/>'.
    '</mxCell>';

// Connections
$actEdges = [
    ['a_start1', 'a_step1', ''],
    ['a_step1', 'a_step2', 'HTTP Request'],
    ['a_step2', 'a_dec1', ''],
    ['a_dec1', 'a_step_err', 'Tidak Valid'],
    ['a_dec1', 'a_step3', 'Valid'],
    ['a_step3', 'a_step4', 'Render View'],
    ['a_step4', 'a_step5', ''],
    ['a_step5', 'a_step6', 'Submit Form'],
    ['a_step6', 'a_step7', 'Success'],
    ['a_step7', 'a_step8', 'Download URL'],
    ['a_step8', 'a_step9', 'Hari H Acara'],
    ['a_step9', 'a_step11', 'Tunjukkan QR'],
    ['a_step10', 'a_step11', 'Aktifkan Kamera'],
    ['a_step11', 'a_step12', 'POST Payload'],
    ['a_step12', 'a_dec2', ''],
    ['a_dec2', 'a_step_dup', 'Ya (Pernah)'],
    ['a_dec2', 'a_step13', 'Belum (Baru)'],
    ['a_step13', 'a_step14', 'JSON Response Sukses'],
    ['a_step14', 'a_end', ''],
];

foreach ($actEdges as $idx => $e) {
    $actXml .= '<mxCell id="e_act_'.$idx.'" value="'.htmlspecialchars($e[2]).'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#283640;strokeWidth=1.5;html=1;fontSize=10;fontColor=#48545C;" edge="1" parent="1" source="'.$e[0].'" target="'.$e[1].'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

file_put_contents(__DIR__.'/../docs/diagrams/activity.drawio', wrapDrawioDiagram('Activity Diagram - VOWSPACE', $actXml));

// ------------------------------------------
// 2.3 CLASS DIAGRAM XML
// ------------------------------------------
$clsXml = '';
$classes = [
    [
        'id' => 'cls_user', 'name' => 'User', 'x' => 40, 'y' => 40, 'w' => 220, 'h' => 190,
        'attrs' => ['+ id: int', '+ name: string', '+ email: string', '+ password: string', '+ role: UserRole', '+ phone: string', '+ is_active: bool'],
        'methods' => ['+ isSuperadmin(): bool', '+ isOwner(): bool', '+ events(): BelongsToMany', '+ auditLogs(): HasMany'],
    ],
    [
        'id' => 'cls_event', 'name' => 'Event', 'x' => 360, 'y' => 40, 'w' => 260, 'h' => 240,
        'attrs' => ['+ id: int', '+ organization_id: int', '+ slug: string', '+ name: string', '+ bride_name: string', '+ groom_name: string', '+ event_date: date', '+ status: EventStatus'],
        'methods' => ['+ coupleNames(): string', '+ coverUrl(): ?string', '+ isLive(): bool', '+ guests(): HasMany', '+ venues(): HasMany', '+ rsvps(): HasMany', '+ checkins(): HasMany'],
    ],
    [
        'id' => 'cls_venue', 'name' => 'EventVenue', 'x' => 720, 'y' => 40, 'w' => 220, 'h' => 150,
        'attrs' => ['+ id: int', '+ event_id: int', '+ name: string', '+ address: string', '+ maps_url: string', '+ capacity: int'],
        'methods' => ['+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_schedule', 'name' => 'EventSchedule', 'x' => 720, 'y' => 230, 'w' => 220, 'h' => 150,
        'attrs' => ['+ id: int', '+ event_id: int', '+ title: string', '+ start_time: time', '+ end_time: time', '+ status: string'],
        'methods' => ['+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_guest', 'name' => 'Guest', 'x' => 360, 'y' => 360, 'w' => 260, 'h' => 240,
        'attrs' => ['+ id: int', '+ event_id: int', '+ category_id: int', '+ name: string', '+ email: ?string', '+ phone: ?string', '+ max_attendees: int', '+ rsvp_status: RsvpStatus', '+ checked_in_at: ?datetime'],
        'methods' => ['+ tokens(): HasMany', '+ invitation(): HasOne', '+ rsvp(): HasOne', '+ checkin(): HasOne', '+ isCheckedIn(): bool', '+ hasRsvpd(): bool'],
    ],
    [
        'id' => 'cls_token', 'name' => 'GuestToken', 'x' => 40, 'y' => 360, 'w' => 220, 'h' => 170,
        'attrs' => ['+ id: int', '+ guest_id: int', '+ type: string', '+ token_hash: string', '+ expires_at: ?datetime', '+ revoked_at: ?datetime'],
        'methods' => ['+ guest(): BelongsTo', '+ rawToken(): string', '+ isValid(): bool'],
    ],
    [
        'id' => 'cls_invitation', 'name' => 'Invitation', 'x' => 40, 'y' => 580, 'w' => 220, 'h' => 170,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: int', '+ status: InvitationStatus', '+ open_count: int', '+ opened_at: ?datetime'],
        'methods' => ['+ guest(): BelongsTo', '+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_rsvp', 'name' => 'Rsvp', 'x' => 360, 'y' => 670, 'w' => 260, 'h' => 180,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: int', '+ status: RsvpStatus', '+ attendees_count: int', '+ companion_names: array', '+ notes: ?string'],
        'methods' => ['+ guest(): BelongsTo', '+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_checkin', 'name' => 'Checkin', 'x' => 720, 'y' => 440, 'w' => 220, 'h' => 170,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: int', '+ method: CheckinMethod', '+ checked_in_at: datetime', '+ ip_address: string'],
        'methods' => ['+ guest(): BelongsTo', '+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_msg', 'name' => 'GuestbookMessage', 'x' => 1020, 'y' => 40, 'w' => 250, 'h' => 210,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: ?int', '+ sender_name: string', '+ message: text', '+ visibility: string', '+ status: string', '+ is_pinned: bool'],
        'methods' => ['+ event(): BelongsTo', '+ guest(): BelongsTo', '+ favorites(): HasMany'],
    ],
    [
        'id' => 'cls_media', 'name' => 'MediaAsset', 'x' => 1020, 'y' => 300, 'w' => 250, 'h' => 190,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: ?int', '+ file_path: string', '+ file_type: string', '+ status: string', '+ is_featured: bool'],
        'methods' => ['+ event(): BelongsTo', '+ url(): string', '+ albums(): BelongsToMany'],
    ],
    [
        'id' => 'cls_table', 'name' => 'SeatingTable', 'x' => 1020, 'y' => 540, 'w' => 250, 'h' => 170,
        'attrs' => ['+ id: int', '+ event_id: int', '+ name: string', '+ capacity: int', '+ zone: string'],
        'methods' => ['+ assignments(): HasMany', '+ event(): BelongsTo'],
    ],
    [
        'id' => 'cls_assignment', 'name' => 'TableAssignment', 'x' => 720, 'y' => 670, 'w' => 220, 'h' => 150,
        'attrs' => ['+ id: int', '+ event_id: int', '+ guest_id: int', '+ seating_table_id: int', '+ seat_number: ?string'],
        'methods' => ['+ table(): BelongsTo', '+ guest(): BelongsTo'],
    ],
    [
        'id' => 'cls_audit', 'name' => 'AuditLog', 'x' => 40, 'y' => 790, 'w' => 240, 'h' => 180,
        'attrs' => ['+ id: int', '+ event_id: ?int', '+ user_id: ?int', '+ action: string', '+ auditable_type: ?string', '+ auditable_id: ?int', '+ ip_address: string'],
        'methods' => ['+ user(): BelongsTo', '+ event(): BelongsTo'],
    ],
];

foreach ($classes as $c) {
    $content = '<p style="margin:0px;padding:4px;text-align:center;background-color:#283640;color:#F6F4F0;font-weight:bold;">'.htmlspecialchars($c['name']).'</p>';
    $content .= '<hr style="margin:0px;"/><p style="margin:0px;padding:4px;font-size:10px;color:#283640;">';
    foreach ($c['attrs'] as $a) {
        $content .= htmlspecialchars($a).'<br/>';
    }
    $content .= '</p><hr style="margin:0px;"/><p style="margin:0px;padding:4px;font-size:10px;color:#48545C;">';
    foreach ($c['methods'] as $m) {
        $content .= htmlspecialchars($m).'<br/>';
    }
    $content .= '</p>';

    $clsXml .= '<mxCell id="'.$c['id'].'" value="'.htmlspecialchars($content).'" style="shape=rect;rounded=0;whiteSpace=wrap;html=1;overflow=hidden;fillColor=#FFFFFF;strokeColor=#725A40;strokeWidth=1.5;align=left;verticalAlign=top;" vertex="1" parent="1">'.
        '<mxGeometry x="'.$c['x'].'" y="'.$c['y'].'" width="'.$c['w'].'" height="'.$c['h'].'" as="geometry"/>'.
        '</mxCell>';
}

// Class Relationships
$classEdges = [
    ['cls_user', 'cls_event', '1..*  creates / owns  0..*'],
    ['cls_event', 'cls_venue', '1  has  1..*'],
    ['cls_event', 'cls_schedule', '1  has  0..*'],
    ['cls_event', 'cls_guest', '1  has  0..*'],
    ['cls_guest', 'cls_token', '1  owns  1..*'],
    ['cls_guest', 'cls_invitation', '1  has  1'],
    ['cls_guest', 'cls_rsvp', '1  submits  0..1'],
    ['cls_guest', 'cls_checkin', '1  performs  0..1'],
    ['cls_event', 'cls_msg', '1  receives  0..*'],
    ['cls_guest', 'cls_msg', '0..1  writes  0..*'],
    ['cls_event', 'cls_media', '1  stores  0..*'],
    ['cls_event', 'cls_table', '1  defines  0..*'],
    ['cls_table', 'cls_assignment', '1  seats  0..*'],
    ['cls_guest', 'cls_assignment', '1  assigned  0..1'],
    ['cls_user', 'cls_audit', '0..1  acts  0..*'],
    ['cls_event', 'cls_audit', '0..1  scopes  0..*'],
];

foreach ($classEdges as $idx => $ce) {
    $clsXml .= '<mxCell id="ce_'.$idx.'" value="'.htmlspecialchars($ce[2]).'" style="edgeStyle=orthogonalEdgeStyle;rounded=1;strokeColor=#725A40;strokeWidth=1.2;html=1;fontSize=9;fontColor=#283640;endArrow=open;endSize=8;" edge="1" parent="1" source="'.$ce[0].'" target="'.$ce[1].'">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
}

file_put_contents(__DIR__.'/../docs/diagrams/class_diagram.drawio', wrapDrawioDiagram('Class Diagram - VOWSPACE', $clsXml));

// ------------------------------------------
// 2.4 SEQUENCE DIAGRAM XML
// ------------------------------------------
$seqXml = '';

// Lifelines
$lifelines = [
    ['id' => 'll_guest', 'label' => 'Tamu (Guest)', 'x' => 60],
    ['id' => 'll_scanner', 'label' => 'UI Scanner Kamera\n(Alpine.js / Html5Qrcode)', 'x' => 260],
    ['id' => 'll_ctrl', 'label' => 'CheckinController\n(Backend API)', 'x' => 490],
    ['id' => 'll_srv', 'label' => 'InvitationService\n(Core Domain Service)', 'x' => 720],
    ['id' => 'll_db_token', 'label' => 'Database\n(guest_tokens)', 'x' => 930],
    ['id' => 'll_db_chk', 'label' => 'Database\n(checkins)', 'x' => 1120],
    ['id' => 'll_db_audit', 'label' => 'Database\n(audit_logs)', 'x' => 1310],
];

foreach ($lifelines as $ll) {
    // Header Box
    $seqXml .= '<mxCell id="'.$ll['id'].'_box" value="'.htmlspecialchars($ll['label']).'" style="shape=rect;rounded=1;whiteSpace=wrap;html=1;fillColor=#283640;strokeColor=#725A40;strokeWidth=1.5;fontColor=#F6F4F0;fontStyle=1;fontSize=11;" vertex="1" parent="1">'.
        '<mxGeometry x="'.$ll['x'].'" y="40" width="150" height="50" as="geometry"/>'.
        '</mxCell>';
    // Lifeline Vertical Line
    $seqXml .= '<mxCell id="'.$ll['id'].'_line" value="" style="edgeStyle=none;html=1;dashed=1;strokeColor=#725A40;strokeWidth=1.5;endArrow=none;entryX=0.5;entryY=1;entryDx=0;entryDy=0;" edge="1" parent="1" source="'.$ll['id'].'_end" target="'.$ll['id'].'_box">'.
        '<mxGeometry relative="1" as="geometry"/>'.
        '</mxCell>';
    // Bottom Anchor
    $seqXml .= '<mxCell id="'.$ll['id'].'_end" value="" style="ellipse;fillColor=none;strokeColor=none;" vertex="1" parent="1">'.
        '<mxGeometry x="'.($ll['x'] + 70).'" y="880" width="10" height="10" as="geometry"/>'.
        '</mxCell>';
}

// Sequence Messages
$seqMessages = [
    ['from' => 135, 'to' => 335, 'y' => 120, 'text' => '1. Tunjukkan QR Pass di Layar Ponsel', 'type' => 'sync'],
    ['from' => 335, 'to' => 565, 'y' => 160, 'text' => '2. POST /admin/checkins/scan {qr_payload}', 'type' => 'sync'],
    ['from' => 565, 'to' => 795, 'y' => 200, 'text' => '3. scan(qr_payload, actor)', 'type' => 'sync'],
    ['from' => 795, 'to' => 795, 'y' => 240, 'text' => '4. Hash SHA-256(qr_payload)', 'type' => 'self'],
    ['from' => 795, 'to' => 1005, 'y' => 280, 'text' => '5. SELECT WHERE token_hash = ? AND event_id = ? FOR UPDATE', 'type' => 'sync'],
    ['from' => 1005, 'to' => 795, 'y' => 320, 'text' => '6. Return Token & Guest Record', 'type' => 'reply'],
    ['from' => 795, 'to' => 1195, 'y' => 370, 'text' => '7. SELECT FROM checkins WHERE guest_id = ? AND event_id = ?', 'type' => 'sync'],
    ['from' => 1195, 'to' => 795, 'y' => 410, 'text' => '8. Return Existing Checkin Record (null jika baru)', 'type' => 'reply'],

    // ALT BLOCK (DUPLICATE)
    ['from' => 795, 'to' => 565, 'y' => 480, 'text' => '[Alt: Sudah Pernah Checkin] Throw ValidationException (duplicate)', 'type' => 'reply_err'],
    ['from' => 565, 'to' => 335, 'y' => 520, 'text' => 'HTTP 422 {status: duplicate, message: Tamu sudah check-in}', 'type' => 'reply_err'],
    ['from' => 335, 'to' => 135, 'y' => 560, 'text' => 'UI Bunyi Beep Merah & Muncul Peringatan Duplikat', 'type' => 'sync'],

    // SUCCESS PATH
    ['from' => 795, 'to' => 1195, 'y' => 630, 'text' => '[Alt: Belum Checkin] INSERT INTO checkins (event_id, guest_id, method, checked_in_at, ip)', 'type' => 'sync'],
    ['from' => 1195, 'to' => 795, 'y' => 670, 'text' => 'Insert OK', 'type' => 'reply'],
    ['from' => 795, 'to' => 1385, 'y' => 710, 'text' => '9. INSERT INTO audit_logs (action: checkin.scanned, metadata)', 'type' => 'sync'],
    ['from' => 1385, 'to' => 795, 'y' => 750, 'text' => 'Audit Logged', 'type' => 'reply'],
    ['from' => 795, 'to' => 565, 'y' => 780, 'text' => 'Return Success Payload (Guest Data, Seating Table)', 'type' => 'reply'],
    ['from' => 565, 'to' => 335, 'y' => 810, 'text' => 'HTTP 200 JSON {success: true, guest: "Raka Pratama", table: "VIP-A1"}', 'type' => 'reply'],
    ['from' => 335, 'to' => 135, 'y' => 850, 'text' => 'UI Bunyi Ding Hijau & Tampilkan Ucapan Selamat Datang + Nomor Meja', 'type' => 'sync'],
];

foreach ($seqMessages as $idx => $m) {
    $dashed = ($m['type'] === 'reply' || $m['type'] === 'reply_err') ? 'dashed=1;' : '';
    $color = ($m['type'] === 'reply_err') ? '#8B4D55' : '#283640';
    $fontColor = ($m['type'] === 'reply_err') ? '#8B4D55' : '#283640';

    if ($m['type'] === 'self') {
        $seqXml .= '<mxCell id="sm_'.$idx.'" value="'.htmlspecialchars($m['text']).'" style="edgeStyle=orthogonalEdgeStyle;rounded=0;strokeColor='.$color.';strokeWidth=1.5;html=1;fontColor='.$fontColor.';fontSize=10;" edge="1" parent="1">'.
            '<mxGeometry relative="1" as="geometry">'.
            '<mxPoint x="'.$m['from'].'" y="'.$m['y'].'" as="sourcePoint"/>'.
            '<mxPoint x="'.($m['from'] + 40).'" y="'.($m['y'] + 20).'" as="targetPoint"/>'.
            '<Array as="points">'.
            '<mxPoint x="'.($m['from'] + 30).'" y="'.$m['y'].'"/>'.
            '<mxPoint x="'.($m['from'] + 30).'" y="'.($m['y'] + 20).'"/>'.
            '</Array>'.
            '</mxGeometry></mxCell>';
    } else {
        $seqXml .= '<mxCell id="sm_'.$idx.'" value="'.htmlspecialchars($m['text']).'" style="edgeStyle=none;rounded=0;'.$dashed.'strokeColor='.$color.';strokeWidth=1.5;html=1;fontColor='.$fontColor.';fontSize=10;" edge="1" parent="1">'.
            '<mxGeometry relative="1" as="geometry">'.
            '<mxPoint x="'.$m['from'].'" y="'.$m['y'].'" as="sourcePoint"/>'.
            '<mxPoint x="'.$m['to'].'" y="'.$m['y'].'" as="targetPoint"/>'.
            '</mxGeometry></mxCell>';
    }
}

file_put_contents(__DIR__.'/../docs/diagrams/sequence_diagram.drawio', wrapDrawioDiagram('Sequence Diagram - Check-in QR Verification', $seqXml));

// ------------------------------------------
// 2.5 MULTI-PAGE COMBINED DRAW.IO XML
// ------------------------------------------
$allDrawio = '<mxfile host="app.diagrams.net">'.
    '<diagram id="page_uc" name="1. Use Case Diagram">'.
    '<mxGraphModel dx="1422" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1600" pageHeight="1100" math="0" shadow="0">'.
    '<root><mxCell id="0"/><mxCell id="1" parent="0"/>'.$ucXml.'</root></mxGraphModel></diagram>'.
    '<diagram id="page_act" name="2. Activity Diagram">'.
    '<mxGraphModel dx="1422" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1600" pageHeight="1100" math="0" shadow="0">'.
    '<root><mxCell id="0"/><mxCell id="1" parent="0"/>'.$actXml.'</root></mxGraphModel></diagram>'.
    '<diagram id="page_cls" name="3. Class Diagram">'.
    '<mxGraphModel dx="1422" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1600" pageHeight="1100" math="0" shadow="0">'.
    '<root><mxCell id="0"/><mxCell id="1" parent="0"/>'.$clsXml.'</root></mxGraphModel></diagram>'.
    '<diagram id="page_seq" name="4. Sequence Diagram">'.
    '<mxGraphModel dx="1422" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1600" pageHeight="1100" math="0" shadow="0">'.
    '<root><mxCell id="0"/><mxCell id="1" parent="0"/>'.$seqXml.'</root></mxGraphModel></diagram>'.
    '</mxfile>';

file_put_contents(__DIR__.'/../docs/diagrams/vowspace_all_diagrams.drawio', $allDrawio);
echo "Draw.io diagrams generated successfully in docs/diagrams/\n";
