/**
 * Noisemon - contoh konfigurasi perangkat
 *
 * Salin file ini menjadi config.h (di folder yang sama), isi nilainya, lalu
 * upload firmware. config.h tidak ikut git karena berisi password.
 *
 * Nilai di config.h LANGSUNG dipakai setelah upload. Pengaturan yang diubah
 * lewat web lokal (http://192.168.4.1) disimpan di memori perangkat (NVS)
 * sampai config.h diubah dan firmware di-upload ulang.
 */
#pragma once

// --- WiFi tujuan (client / internet) ---
#define CFG_WIFI_SSID "nama-wifi"
#define CFG_WIFI_PASSWORD "password-wifi"

// --- Identitas device (lihat halaman device di web Laravel) ---
#define CFG_DEVICE_ID "ESP32-XXXXXX"
#define CFG_DEVICE_TOKEN "token-dari-halaman-device"

// --- Server Laravel (domain atau IP; port 443 = HTTPS) ---
#define CFG_SERVER_HOST "dev.nhmedia.pro"
#define CFG_SERVER_PORT 443

// --- MQTT broker ---
#define CFG_MQTT_HOST "xxxxxxxx.s1.eu.hivemq.cloud"
#define CFG_MQTT_PORT 8883
#define CFG_MQTT_USER "mqtt-user"
#define CFG_MQTT_PASSWORD "mqtt-password"

// --- Hotspot lokal ESP32 (selalu aktif, juga saat internet putus) ---
// Nama hotspot: "Noisemon-<DEVICE_ID>", alamat web: http://192.168.4.1
// Password minimal 8 karakter.
#define CFG_AP_PASSWORD "noisemon123"

// --- Login web lokal (dari hotspot maupun dari jaringan WiFi) ---
#define CFG_WEB_USER "admin"
#define CFG_WEB_PASSWORD "ganti-password-ini"
