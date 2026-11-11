# Panduan Penggunaan Aplikasi SIAKAD

Panduan ini menjelaskan alur penggunaan aplikasi berdasarkan fitur dan hak akses yang tersedia. Nama menu dapat berbeda sedikit menurut tampilan atau konfigurasi kampus. Setelah masuk, gunakan dashboard dan menu yang tampil untuk membuka modul sesuai hak akses akun.

## 1. Masuk dan pengaturan akun

1. Buka alamat aplikasi, lalu pilih **Login**.
2. Masukkan email dan kata sandi akun.
3. Jika diminta, verifikasi alamat email melalui tautan yang dikirim ke email.
4. Setelah berhasil masuk, aplikasi mengarahkan pengguna ke dashboard sesuai role.
5. Untuk mengubah informasi akun atau kata sandi, buka **Profile**. Gunakan **Logout** untuk mengakhiri sesi.
6. Jika lupa kata sandi, gunakan **Forgot Password** pada halaman login.

Pendaftaran akun tersedia, tetapi akses ke modul akademik dan administrasi bergantung pada role serta data fakultas/program studi yang diberikan ke akun. Hubungi pengelola kampus jika role atau unit akun tidak sesuai.

## 2. Ringkasan role

| Role | Kegunaan utama |
|---|---|
| **Superadmin** (`superadmin`) | Mengelola administrasi lintas unit, data dasar, pengguna, akademik, keuangan, serta pengaturan GPS kehadiran. |
| **Admin Fakultas** (`admin_fakultas`) | Mengelola modul administrasi akademik dan operasional fakultas yang diizinkan untuk akunnya. |
| **Admin Prodi** (`admin_prodi`) | Mengelola administrasi akademik pada program studi yang ditetapkan ke akun. |
| **Dosen** (`dosen`) | Mengelola pembelajaran kelas, penilaian, presensi, bimbingan, dan melihat honorarium. |
| **Mahasiswa** (`mahasiswa`) | Mengakses informasi akademik pribadi, pembelajaran, tugas, presensi, KP, skripsi, dan layanan penasihat AI. |
| **Bendahara** (`bendahara`) | Memantau dan memproses honorarium, verifikasi kehadiran mengajar, tarif, serta pembayaran pegawai. |
| **Staff** (`staff`) | Melakukan presensi kerja dan melihat KHS mahasiswa. |

Role menentukan halaman dan tindakan yang dapat digunakan. Jika halaman menampilkan **403** atau tidak tersedia di dashboard, mintalah pengelola memeriksa role dan penugasan unit akun; jangan menggunakan akun role lain.

## 3. Panduan Superadmin

Superadmin memiliki akses paling luas. Alur yang disarankan saat menyiapkan penggunaan akademik:

1. **Siapkan data dasar**: isi fakultas, program studi, tahun akademik, mata kuliah, kelas, dan ruangan.
2. **Atur tahun akademik aktif** sebelum proses KRS dan kegiatan semester dimulai.
3. **Siapkan akun dan data sivitas**: kelola pengguna, data mahasiswa, data dosen, dan dokumen dosen. Pastikan role dan unit (fakultas/prodi) akun sudah benar.
4. **Kelola proses akademik**:
   - Pantau atau kelola KRS dan persetujuan KRS.
   - Pantau KP dan skripsi, tetapkan pembimbing, perbarui status, dan masukkan nilai sesuai proses kampus.
   - Tinjau kehadiran dosen.
5. **Kelola keuangan** bersama alur bendahara: tarif, kehadiran mengajar, insentif, generate honorarium, status pembayaran, dan ekspor laporan yang tersedia.
6. **Atur GPS kehadiran** melalui pengaturan GPS jika kebijakan presensi kampus menggunakan lokasi.
7. Pantau dashboard dan notifikasi untuk melihat informasi yang tersedia.

Modul utama: dashboard admin, fakultas, prodi, tahun akademik, mata kuliah, kelas, ruangan, mahasiswa, dosen, pengguna, impor data, KRS, KP, skripsi, kehadiran dosen, dan honorarium.

## 4. Panduan Admin Fakultas

1. Masuk dan buka dashboard admin.
2. Gunakan modul administrasi yang tampil untuk memeriksa data mahasiswa/dosen, data akademik, KRS, KP, skripsi, kehadiran dosen, dan laporan.
3. Periksa unit dan data sebelum menyimpan perubahan. Gunakan ekspor yang tersedia untuk mengunduh daftar atau laporan.
4. Koordinasikan pembuatan akun dan konfigurasi master data lintas unit dengan superadmin bila menu atau tindakan yang diperlukan tidak tersedia.

Role ini diterima oleh grup route admin dan beberapa modul dosen. Cakupan data bergantung pada konfigurasi dan pemeriksaan unit di tiap modul; pastikan akun telah diberi penugasan fakultas yang tepat.

## 5. Panduan Admin Prodi

Admin Prodi menggunakan modul administrasi untuk mengelola kegiatan di program studi yang ditetapkan pada akunnya.

1. Pastikan akun sudah terhubung ke program studi yang benar. Akun tanpa prodi yang ditetapkan tidak dapat membuka modul admin.
2. Atur atau periksa mata kuliah, kelas, jadwal, data mahasiswa, dan data dosen prodi.
3. Pantau KRS mahasiswa dan proses persetujuan sesuai kewenangan yang diberikan.
4. Kelola informasi KP dan skripsi: periksa pengajuan, tetapkan pembimbing, perbarui status, dan masukkan nilai sesuai prosedur kampus.
5. Gunakan laporan/ekspor yang tersedia untuk kebutuhan administrasi prodi.

Data admin prodi dibatasi ke prodi yang terhubung pada akun oleh aplikasi. Untuk perubahan master fakultas atau konfigurasi lintas prodi, koordinasikan dengan superadmin.

## 6. Panduan Dosen

### Pembelajaran dan kelas

1. Buka **Dashboard Dosen** untuk melihat ringkasan.
2. Buka kelas yang ditugaskan kepada Anda.
3. Pada modul **Materi**, tambahkan, perbarui, hapus, atau unduh materi kelas sesuai kebutuhan.
4. Pada modul **Tugas**, buat tugas, aktifkan/nonaktifkan sesuai alur, lihat pengumpulan mahasiswa, unduh berkas, dan berikan nilai.
5. Pada modul **Presensi**, pilih kelas, buat pertemuan, lalu isi dan simpan kehadiran mahasiswa.
6. Pada modul **Penilaian**, pilih kelas, masukkan atau perbarui nilai mahasiswa, lalu simpan.

### Bimbingan dan akademik

- **Bimbingan/KRS**: periksa daftar KRS yang menunggu tindakan, buka detailnya, lalu setujui atau tolak sesuai pertimbangan akademik.
- **KP**: buka pengajuan yang ditugaskan, tinjau logbook, beri ulasan, dan perbarui status bimbingan bila diizinkan.
- **Skripsi**: buka pengajuan/bimbingan, tinjau catatan, beri ulasan, dan perbarui status sesuai tahap penelitian.

### Kehadiran dan honorarium

- Gunakan **Kehadiran** untuk mencatat presensi kerja masuk dan keluar bila akses diberikan.
- Gunakan **Honorarium** untuk melihat daftar honorarium dan membuka slip yang tersedia.

Kelas dan mahasiswa yang tampil mengikuti penugasan dosen. Jika data kelas tidak muncul, hubungi admin prodi/fakultas.

## 7. Panduan Mahasiswa

### Data diri dan rencana studi

1. Buka **Biodata** untuk memeriksa atau memperbarui informasi pribadi dan kata sandi.
2. Buka **KRS** untuk melihat KRS yang tercatat dan status persetujuannya. Hubungi dosen pembimbing atau admin bila ada kesalahan; halaman mahasiswa yang tersedia berfungsi untuk melihat KRS.
3. Buka **Jadwal** untuk melihat jadwal kuliah semester berjalan.

### Perkuliahan

- Buka **LMS** untuk melihat kelas yang tersedia.
- Buka **Materi** pada kelas untuk melihat atau mengunduh bahan ajar.
- Buka **Tugas**, pilih tugas, unggah/kirim pengumpulan, dan periksa status atau nilai yang diberikan.
- Buka **Presensi** untuk melihat catatan kehadiran perkuliahan.

### Hasil studi

- Buka **KHS** untuk melihat hasil studi per semester.
- Buka **Transkrip** untuk melihat ringkasan nilai.
- Gunakan pilihan ekspor KHS atau transkrip jika ingin mengunduh dokumen.

### KP, skripsi, dan bantuan

- **KP**: buat pengajuan melalui formulir KP, pantau statusnya, dan kirim logbook untuk proses bimbingan.
- **Skripsi**: buat pengajuan skripsi, pantau status, dan kirim catatan bimbingan melalui modul skripsi.
- **AI Advisor**: masukkan pertanyaan akademik melalui halaman penasihat AI. Periksa kembali saran yang diterima dan konfirmasikan keputusan akademik kepada dosen atau pihak kampus.

Pastikan data pribadi, kelas, serta semester yang aktif sudah benar. KHS, jadwal, kelas, dan tugas bergantung pada data yang dimasukkan pengelola dan dosen.

## 8. Panduan Bendahara

1. Buka **Dashboard Bendahara** untuk melihat ringkasan honorarium dan kehadiran mengajar.
2. Buka modul **Honorarium/Keuangan** untuk meninjau daftar honorarium.
3. Verifikasi data kehadiran mengajar sebelum perhitungan honorarium diproses.
4. Atur tarif atau insentif sesuai kewenangan yang tersedia.
5. Jalankan proses **Generate** honorarium setelah data dan tarif diperiksa.
6. Tandai pembayaran sebagai lunas setelah pembayaran benar-benar dilakukan.
7. Gunakan ekspor PDF/Excel untuk dokumentasi jika pilihan tersebut tersedia.
8. Kelola tarif honor pegawai dan status pembayaran pegawai melalui modul terkait.
9. Jika ditugaskan, gunakan halaman kehadiran pegawai untuk mencatat presensi masuk dan keluar.

Periksa periode, nominal, dosen/pegawai, dan status verifikasi sebelum memproses pembayaran. Tindakan pembayaran mengubah status transaksi.

## 9. Panduan Staff

### Presensi kerja

1. Buka **Dashboard Staff**.
2. Pilih **Absen** untuk membuka halaman kehadiran.
3. Catat **Masuk** saat mulai bekerja dan **Keluar** saat selesai bekerja.
4. Periksa bahwa catatan presensi berhasil tersimpan.

### Melihat KHS mahasiswa

1. Buka **KHS Mahasiswa**.
2. Cari berdasarkan nama atau NIM.
3. Tinjau tahun akademik, semester, IPS, dan total SKS yang tersedia.
4. Gunakan halaman hasil/paginasi untuk melihat daftar lainnya.

KHS hanya tersedia bila KRS mahasiswa telah disetujui dan hasil studi sudah tersedia.

## 10. Notifikasi dan kebiasaan penggunaan

- Buka **Notifications** untuk melihat pemberitahuan; gunakan tindakan tandai dibaca bila diperlukan.
- Periksa kembali data sebelum menyimpan, menghapus, menyetujui, menolak, atau menandai pembayaran.
- Jangan membagikan kata sandi atau meninggalkan akun dalam keadaan terbuka pada perangkat bersama.
- Jika data tidak ditemukan, periksa filter, semester aktif, penugasan kelas/unit, dan status proses sebelum melaporkan masalah.
- Catat pesan kesalahan dan halaman tempat masalah terjadi, lalu hubungi admin kampus. Jangan mengirim kata sandi melalui laporan.

## 11. Catatan akses

- Dashboard umum mengarahkan setiap role ke dashboard sesuai role yang dikenali aplikasi.
- Hak akses bisa berbeda antar modul walaupun nama role serupa; beberapa tindakan hanya tersedia bagi superadmin atau bendahara.
- Admin Prodi perlu memiliki prodi yang ditetapkan. Admin Fakultas dan akses lintas unit bergantung pada konfigurasi akun serta cakupan data modul.
- Fitur yang tidak tercantum pada bagian role berarti tidak tersedia melalui route aplikasi untuk role tersebut atau memerlukan penugasan tambahan.
