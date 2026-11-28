# Rencana Revisi: Penebalan Poin Penting pada Pesan WhatsApp Tagihan

## 1. Masalah & Kondisi Saat Ini
Pada `app/Http/Controllers/Bendahara/TagihanController.php` di method `sendWhatsApp()` (baris 174–190), pesan notifikasi tagihan WhatsApp yang dikirim melalui `FonnteService` saat ini belum memiliki penekanan huruf tebal (*bold*) pada informasi penting (nama tagihan, nominal, jatuh tempo, detail rekening, dan instruksi pengiriman bukti transfer).

Selain itu, ditemukan dua hal kecil pada penyusunan teks di blok yang sama:
1. `array_filter([...])` tanpa *callback* menghapus string kosong `''`, sehingga jarak antar paragraf di baris 177, 183, dan 187 ikut terhapus.
2. Pada baris 188 tertulis `...ke nomor ini.Terima kasih...` (kurang spasi setelah titik).

## 2. Target Perubahan File
1. `app/Http/Controllers/Bendahara/TagihanController.php` — Memperbarui format teks pada `sendWhatsApp()`.
2. File pengujian Pest baru untuk memverifikasi isi pesan WhatsApp tagihan yang dikirim ke `FonnteService` (saat `store` dengan `send_now` maupun `resend`).

## 3. Rancangan Format Pesan WhatsApp
WhatsApp menggunakan format `*teks*` (bintang tunggal) untuk huruf tebal (*bold*). Poin penting yang ditebalkan:
- `Nama: *{$tagihan->nama}*`
- `Jumlah: *Rp...*`
- Tanggal maksimal pembayaran: `*{$jatuhTempo}*`
- Detail rekening transfer:
  - `Bank : *BSI*`
  - `No. Rekening : *3332006664*`
  - `Atas Nama : *STIES GASANTARA*`
- Instruksi penutup: `*mohon kirimkan bukti transfer* ke Bendahara / ke nomor ini.`

Sekaligus menggunakan `array_filter(..., fn (?string $line): bool => $line !== null)` agar baris kosong pemisah paragraf tetap terjaga, dan memperbaiki spasi sebelum `Terima kasih atas perhatiannya.`.

### Contoh Hasil Tampilan di WhatsApp
```text
Assalamualaikum Wr. Wb.
Yth. *Nama Mahasiswa*

Diinformasikan bahwa tagihan:
Nama: *SPP Semester Ganjil*
Jumlah: *Rp1.500.000*
Keterangan: Pembayaran tahap 1
sudah dapat dibayarkan. Mohon segera melakukan pembayaran maksimal tanggal *30 November 2026* melalui transfer ke:

Bank : *BSI*
No. Rekening : *3332006664*
Atas Nama : *STIES GASANTARA*

Setelah melakukan pembayaran, *mohon kirimkan bukti transfer* ke Bendahara / ke nomor ini. Terima kasih atas perhatiannya.
Wassalamualaikum Wr. Wb.
```

## 4. Estimasi Dampak
- Tidak mengubah skema database, validasi input, maupun alur `FonnteService`.
- Hanya memengaruhi teks pesan WhatsApp yang dikirim saat membuat tagihan (`send_now`) atau klik **Kirim WA** (`resend`) di halaman Bendahara -> Tagihan Mahasiswa.

## 5. Status Persetujuan
Menunggu konfirmasi pengguna: **`OKE TERAPKAN`**.
