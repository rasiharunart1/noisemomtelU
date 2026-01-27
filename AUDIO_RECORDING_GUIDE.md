# Panduan Merekam Audio dengan ESP32 ke SD Card

Great job berhasil mengakses SD Card! Untuk merekam audio, kita perlu menambahkan perangkat keras mikrofon dan sedikit logika perangkat lunak untuk menyimpan data suara dalam format yang bisa diputar (WAV).

## 1. Perangkat Keras (Hardware)

Anda membutuhkan mikrofon dengan antarmuka **I2S**. Mikrofon analog biasa sulit digunakan untuk kualitas audio yang baik dengan ESP32 tanpa komponen tambahan.

**Rekomendasi Modul:**
- **INMP441** (Paling umum & mudah digunakan)
- **MSM261S4030H0**

**Koneksi Pin (Wiring) untuk INMP441:**

| INMP441 Pin | ESP32 Pin (dari `esp32_firmware_complete.ino`) | Keterangan |
| :--- | :--- | :--- |
| VDD | 3.3V | Daya |
| GND | GND | Ground |
| SD | GPIO 33 | Serial Data (I2S_SD) |
| WS | GPIO 25 | Word Select (I2S_WS) |
| SCK | GPIO 32 | Serial Clock (I2S_SCK) |
| L/R | GND | Channel Select (Left) |

*Catatan: Pin SD Card tetap sama (CS=5, MOSI=23, MISO=19, SCK=18).*

## 2. Logika Perangkat Lunak (Code)

Anda sebenarnya sudah memiliki kode yang sangat lengkap di folder project Anda:
`esp32_firmware_complete.ino`

Berikut adalah inti dari cara kerjanya:

### A. Setup I2S
Kita mengkonfigurasi I2S untuk membaca data dari mikrofon dengan kecepatan sampling tertentu (misalnya 16000Hz).
```cpp
// Konfigurasi I2S
const i2s_config_t i2s_config = {
    .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX), // Receive mode
    .sample_rate = 16000,
    ...
};
```

### B. Format File WAV (PENTING!)
Jika kita hanya menulis data mentah (raw data) ke SD card, file tersebut tidak akan bisa diputar oleh player biasa. Kita harus menambahkan **WAV Header** (44 bytes di awal file) yang memberi tahu player tentang format audio (sample rate, mono/stereo, bit depth).

Lihat `struct WAVHeader` di dalam file `esp32_firmware_complete.ino`.

### C. Alur Perekaman
1. Buka file di SD Card (`SD.open`).
2. Tulis **Header Kosong** terlebih dahulu (sebagai tempat reservasi).
3. Baca data dari I2S (`i2s_read`) dan tulis ke file secara berulang (Looping).
4. Setelah selesai, hitung ukuran file total.
5. Kembali ke awal file (`file.seek(0)`) dan **update Header** dengan ukuran file yang benar.
6. Tutup file.

## 3. Cara Menggunakan
1. Pasang mikrofon INMP441 sesuai tabel wiring di atas.
2. Buka `esp32_firmware_complete.ino` di Arduino IDE.
3. Pastikan library `ArduinoJson` dan `PubSubClient` terinstall.
4. Upload ke ESP32.
5. Gunakan MQTT untuk mengirim perintah `start_recording` (atau ubah kode untuk merekam otomatis saat boot jika ingin tes manual).

Jika Anda ingin versi yang lebih sederhana hanya untuk tes rekam tanpa MQTT/WiFi, Anda bisa melihat `esp32_firmware_recording.ino`, namun Anda perlu menambahkan logika **WAV Header** agar file hasil rekaman bisa diputar di laptop.
