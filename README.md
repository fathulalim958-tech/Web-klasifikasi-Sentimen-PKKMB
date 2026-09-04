# RasaPKKMB

Aplikasi klasifikasi sentimen komentar PKKMB berbasis Gemini API. Aplikasi ini membantu panitia melihat distribusi komentar **Positif**, **Netral**, dan **Negatif** dari input manual maupun file.

## Fitur

- Klasifikasi komentar menggunakan Gemini LLM.
- Input satu komentar secara manual.
- Upload file CSV atau TXT hingga 20 MB.
- Pemrosesan data besar menggunakan batch dan request paralel terbatas.
- Keterangan alasan dan tingkat keyakinan untuk setiap hasil.
- Bar chart dan donut chart distribusi sentimen.
- Pencarian komentar langsung di tabel tanpa request tambahan ke Gemini.
- Validasi respons Gemini dan pesan error untuk API key, model, kuota, serta koneksi.

## Persyaratan

- PHP 8.3 atau lebih baru
- Composer
- Node.js dan npm
- API key Gemini

## Instalasi

Clone repository lalu masuk ke folder proyek:

```bash
git clone https://github.com/fathulalim958-tech/Web-klasifikasi-Sentimen-PKKMB.git
cd Web-klasifikasi-Sentimen-PKKMB
```

Pasang dependency dan siapkan aplikasi:

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
```

Pada Linux atau macOS, gunakan `cp .env.example .env` sebagai pengganti perintah `copy`.

## Konfigurasi Gemini

Buka file `.env`, lalu isi:

```env
GEMINI_API_KEY=API_KEY_GEMINI_ANDA
GEMINI_MODEL=gemini-3.5-flash-lite
```

Jangan commit file `.env` atau membagikan API key. File `.env` sudah dikecualikan melalui `.gitignore`.

## Menjalankan Aplikasi

Jalankan server Laravel:

```bash
php artisan serve
```

Buka alamat berikut di browser:

```text
http://127.0.0.1:8000
```

## Format File Input

### CSV

Gunakan kolom bernama `komentar`, `comment`, atau `text`:

```csv
komentar
Acara PKKMB sangat seru dan panitianya ramah
Jadwal kegiatan terlambat dan membosankan
```

Jika tidak menggunakan header, aplikasi membaca kolom pertama:

```csv
Acara PKKMB sangat seru
Panitianya kurang ramah
```

### TXT

Satu komentar per baris:

```txt
Acara PKKMB sangat seru dan menarik
Jadwalnya terlambat serta membosankan
Materinya cukup jelas
```

Gunakan encoding UTF-8 agar karakter Bahasa Indonesia terbaca dengan benar.

## Pengujian

Jalankan seluruh test:

```bash
php artisan test --compact
```

Test menggunakan HTTP fake sehingga tidak mengirim request Gemini sungguhan.

## Struktur Penting

- `app/Services/GeminiSentimentService.php` - integrasi dan parsing Gemini.
- `app/Http/Controllers/SentimentController.php` - validasi input dan pembacaan CSV/TXT.
- `resources/views/welcome.blade.php` - halaman input, grafik, pencarian, dan tabel hasil.
- `resources/css/app.css` - styling aplikasi.
- `tests/Feature/SentimentClassificationTest.php` - test klasifikasi, upload, batching, dan error API.

## Lisensi

Proyek ini menggunakan lisensi MIT.
