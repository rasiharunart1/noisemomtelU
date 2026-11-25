/**
 * Noisemon ESP32 Firmware v4.0 - SD Card + Server Recording + Local Dashboard
 *
 * Hardware: ESP32 + INMP441 Microphone + SD Card Module
 *
 * Arsitektur:
 *  - audioTask (core 1, prioritas tinggi): baca I2S terus-menerus, terapkan
 *    gain, tulis WAV ke SD card, dan siapkan frame untuk FFT. Tidak pernah
 *    menyentuh WiFi/MQTT, jadi rekaman tidak putus walau jaringan lambat.
 *  - loop(): WiFi/MQTT, publish FFT tiap 1 detik, progress rekaman, health,
 *    dan upload file ke Laravel (boleh sambil merekam file berikutnya).
 *
 * Penyimpanan di SD card:
 *  - /rec/<DEVICE_ID>_<YYYYMMDD>_<HHMMSS>.wav  -> belum ter-upload ke server
 *  - /rec/up/<nama yang sama>.wav               -> sudah ter-upload (TETAP
 *    disimpan sebagai salinan kedua)
 *  - Setelah rekaman selesai, file otomatis di-upload. Jika gagal, dicoba
 *    ulang dengan backoff 1 menit s/d 30 menit.
 *  - Saat SD hampir penuh, file di /rec/up paling lama dihapus otomatis.
 *    File yang belum ter-upload tidak pernah dihapus otomatis.
 *  - Header WAV diperbarui tiap 10 detik (aman terhadap listrik mati).
 *
 * MQTT topics (<ID> = DEVICE_ID):
 *  - audio/<ID>/data              FFT + dB SPL tiap 1 detik (+ Last Will offline)
 *  - audio/<ID>/recording_status  progress rekaman/upload
 *  - audio/<ID>/health            kondisi perangkat tiap 60 detik (retained)
 *  - audio/<ID>/files             daftar file di SD (jawaban list_files)
 *  - audio/<ID>/command           perintah dari Laravel (lihat mqttCallback)
 *  - audio/<ID>/control           set_gain dari slider web
 *
 * Upload ke server:
 *  - Default https://dev.nhmedia.pro (port 443 = HTTPS, port lain = HTTP)
 *  - File dikirim bertahap per 8 MB (chunked) karena Cloudflare menolak
 *    request di atas 100 MB. Server menyambung potongan menjadi satu WAV.
 *
 * Konfigurasi & jaringan:
 *  - Semua kredensial ada di config.h dan langsung dipakai setelah upload.
 *  - ESP32 berjalan sebagai WiFi client (internet) DAN hotspot sekaligus.
 *    Hotspot "Noisemon-<DEVICE_ID>" selalu aktif -> http://192.168.4.1
 *    Dashboard lokal (web/index.html, tema sama dengan web Laravel) tetap
 *    bisa dibuka saat internet putus: data live, rekam, file SD (putar /
 *    unduh), status sistem, dan pengaturan.
 *
 * Format audio: 16-bit PCM / 16000 Hz / Mono (32 KB/detik, ~115 MB/jam)
 *
 * File sketch: esp32_firmware_complete.ino (utama), web_server.ino
 * (dashboard lokal), config.h (kredensial), web_ui.h (halaman, dibuat oleh
 * web/build_web_ui.py dari web/index.html).
 *
 * Library: ArduinoJson v7, PubSubClient, arduinoFFT v2 (tanpa WiFiManager)
 * Partition: Default (4MB with spiffs) cukup, app memakai ~92% slot 1.25 MB
 * Board  : ESP32 Arduino core 2.x atau 3.x
 */

#include <Arduino.h>
#include <ArduinoJson.h>
#include <FS.h>
#include <Preferences.h>
#include <PubSubClient.h>
#include <SD.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <DNSServer.h>
#include <WebServer.h>
#include <arduinoFFT.h>
#include <driver/i2s.h>
#include <esp_system.h>
#include <sys/time.h>
#include <time.h>

#include "soc/rtc_cntl_reg.h" // BROWN-OUT FIX
#include "soc/soc.h"          // BROWN-OUT FIX

#if __has_include("config.h")
#include "config.h"
#else
#error "config.h belum ada: salin config.example.h menjadi config.h lalu isi kredensialnya"
#endif
#include "web_ui.h"

#define FW_VERSION "4.0.1"

// ============================================================================
// Configuration
// ============================================================================

// Zona waktu untuk nama file rekaman (WIB = UTC+7)
const char *TZ_INFO = "WIB-7";
const char *NTP_SERVER_1 = "pool.ntp.org";
const char *NTP_SERVER_2 = "time.google.com";

// Nilai aktif. Awalnya dari config.h; bisa diubah dari dashboard lokal atau
// MQTT dan disimpan di NVS (lihat loadConfiguration).
char WIFI_SSID[33] = CFG_WIFI_SSID;
char WIFI_PASS[65] = CFG_WIFI_PASSWORD;
char DEVICE_ID[32] = CFG_DEVICE_ID;
char DEVICE_TOKEN[64] = CFG_DEVICE_TOKEN;
// Server Laravel: domain atau IP. Port 443 otomatis memakai HTTPS.
char SERVER_HOST[64] = CFG_SERVER_HOST;
int SERVER_PORT = CFG_SERVER_PORT;
const char *UPLOAD_PATH = "/api/upload-audio";

// Cloudflare menolak body > 100 MB, jadi file dikirim per potongan
const uint32_t UPLOAD_CHUNK_BYTES = 8UL * 1024UL * 1024UL;

// Hotspot lokal
const IPAddress AP_IP(192, 168, 4, 1);
const uint8_t AP_MAX_CLIENTS = 4;

// Upload otomatis ke server setelah rekaman selesai (disimpan di NVS)
bool autoUpload = true;
uint32_t bootCount = 0;

// I2S & SD Pins
#define I2S_WS 25
#define I2S_SD 33
#define I2S_SCK 32
#define SD_CS 5

// Audio Constants
#define SAMPLES 1024        // Harus pangkat 2 untuk FFT
#define SAMPLING_FREQ 16000 // 16 kHz
#define BITS_PER_SAMPLE 16
#define NUM_CHANNELS 1
const uint32_t BYTES_PER_SECOND =
    SAMPLING_FREQ * NUM_CHANNELS * (BITS_PER_SAMPLE / 8);

// FFT Calibration
const float REFERENCE_AMPLITUDE = 32768.0f;

// Storage
#define REC_DIR "/rec"
#define UP_DIR "/rec/up"
#define MAX_PATH_LEN 96
// Sisakan ruang minimal ini di SD card. Kalau kurang, rekaman lama yang
// SUDAH ter-upload dihapus dulu.
const uint64_t SD_RESERVE_BYTES = 200ULL * 1024ULL * 1024ULL;
// Batas aman satu file (sama dengan batas durasi di Laravel = 4 jam)
const uint32_t MAX_RECORDING_SECONDS = 4UL * 3600UL;
// Tulis SD lebih lama dari ini berisiko membuat buffer I2S (~0.5 s) meluap
const uint32_t SLOW_WRITE_MS = 300;

// Timing
const unsigned long FFT_INTERVAL_MS = 1000;
const unsigned long PROGRESS_INTERVAL_MS = 5000;
const unsigned long HEALTH_INTERVAL_MS = 60000;
const unsigned long HEADER_SYNC_MS = 10000;
const unsigned long MQTT_RETRY_MS = 5000;
const unsigned long WIFI_RETRY_MS = 30000;
// Setelah 5 menit gagal, coba lebih jarang: saat mencari WiFi, ESP32
// berpindah channel dan klien hotspot sempat terputus.
const unsigned long WIFI_RETRY_SLOW_MS = 120000;
const unsigned long SD_SUMMARY_TTL_MS = 15000;
const unsigned long AUTO_UPLOAD_SCAN_MS = 30000;
const unsigned long UPLOAD_BACKOFF_MIN_MS = 60000;
const unsigned long UPLOAD_BACKOFF_MAX_MS = 30UL * 60UL * 1000UL;
const unsigned long HTTP_RESPONSE_TIMEOUT_MS = 60000;
const unsigned long GAIN_SAVE_DELAY_MS = 3000;

// ============================================================================
// Types & Globals
// CATATAN: semua tipe (struct/enum) harus dideklarasikan DI ATAS fungsi
// pertama, karena Arduino IDE menyisipkan prototype sebelum fungsi pertama.
// ============================================================================

// MQTT Topics
char data_topic[64];
char command_topic[64];
char control_topic[64];
char recording_status_topic[64];
char health_topic[64];
char files_topic[64];

Preferences preferences;

// Dashboard lokal
WebServer web(80);
DNSServer dns;
char apSsid[40] = "";
bool webBusy = false;          // cegah web.handleClient() bertingkat
bool pendingWifiReconnect = false;
unsigned long lastWifiAttempt = 0;
unsigned long lastWifiConnectedAt = 0;

// Data live terakhir (untuk dashboard lokal, juga saat MQTT offline)
float liveDb = 0, liveRms = 0;
int livePeak = 0;
long liveLow = 0, liveMid = 0, liveHigh = 0;
uint8_t liveSpectrum[64] = {0};
unsigned long liveAt = 0;
volatile int uploadProgress = 0;

WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);

ArduinoFFT<float> FFT = ArduinoFFT<float>();
float vReal[SAMPLES];
float vImag[SAMPLES];

volatile float micGain = 1.0f;
bool gainDirty = false;
unsigned long gainChangedAt = 0;
bool sdReady = false;

// --- Shared recording state (ditulis audioTask, dibaca loop via mutex) ---
portMUX_TYPE stateMux = portMUX_INITIALIZER_UNLOCKED;
struct RecState {
  bool active;
  char filename[MAX_PATH_LEN];
  uint32_t samples;
  uint32_t targetSamples;
  uint32_t plannedSeconds;
};
RecState recState = {};

// Statistik tulis SD (diisi audioTask, direset saat health dikirim)
volatile uint32_t maxWriteMs = 0;
volatile uint32_t slowWrites = 0;
volatile uint32_t sdWriteErrors = 0;

// --- loop -> audioTask commands ---
enum RecCmdType : uint8_t { REC_CMD_START, REC_CMD_STOP };
struct RecCmd {
  RecCmdType type;
  uint32_t durationSeconds;
  char filename[MAX_PATH_LEN];
};
QueueHandle_t recCmdQueue;

// --- audioTask -> loop events ---
enum RecEvtType : uint8_t {
  REC_EVT_STARTED,
  REC_EVT_COMPLETED,
  REC_EVT_OPEN_FAILED
};
struct RecEvt {
  RecEvtType type;
  char filename[MAX_PATH_LEN];
  uint32_t samples;
  uint32_t plannedSeconds;
  bool writeError;
};
QueueHandle_t recEvtQueue;

// --- FFT frame handoff (audioTask mengisi saat diminta loop) ---
volatile bool fftFrameRequested = false;
volatile bool fftFrameReady = false;
float fftFrame[SAMPLES];
volatile int fftFrameLen = 0;

// --- Deferred jobs dari MQTT callback (diproses di loop) ---
enum JobType : uint8_t { JOB_UPLOAD, JOB_DELETE };
struct Job {
  JobType type;
  char filename[MAX_PATH_LEN];
};
#define MAX_JOBS 8
Job jobs[MAX_JOBS];
int jobCount = 0;

// Perintah yang dikerjakan di loop (bukan di dalam callback MQTT)
bool pendingListFiles = false;
bool pendingHealth = false;
bool pendingDeleteUploaded = false;
bool pendingRestart = false;

// --- Upload state ---
enum UploadResult { UPLOAD_OK, UPLOAD_RETRY, UPLOAD_REJECTED };
bool isUploading = false;
char currentUploadPath[MAX_PATH_LEN] = "";
unsigned long nextAutoUploadAt = 0;
unsigned long uploadBackoffMs = UPLOAD_BACKOFF_MIN_MS;
char lastUploadError[48] = "";
// MQTT diputus sementara selama upload karena RAM tidak cukup untuk dua sesi
// TLS sekaligus; loop() menyambungkannya lagi setelah upload selesai.
bool mqttPausedForUpload = false;
// upload_all: upload semua file yang belum ter-upload sekarang juga
bool uploadAllActive = false;
uint16_t uploadAllOk = 0;
uint16_t uploadAllFailed = 0;
// File yang ditolak server (4xx) tidak dicoba otomatis lagi sampai reboot
#define MAX_REJECTED 16
char rejectedFiles[MAX_REJECTED][MAX_PATH_LEN];
int rejectedCount = 0;

// Ringkasan isi SD card (di-cache SD_SUMMARY_TTL_MS, scan folder itu mahal)
struct SdSummary {
  uint32_t pendingFiles;
  uint64_t pendingBytes;
  uint32_t uploadedFiles;
  uint64_t uploadedBytes;
  uint32_t totalMb;
  uint32_t freeMb;
};
SdSummary sdCache = {};
unsigned long sdCacheAt = 0;
bool sdCacheValid = false;

// State satu upload (bisa terdiri dari beberapa potongan / chunk)
struct UploadCtx {
  const char *path;
  uint32_t fileSize;
  uint32_t durationSec;
  uint32_t sent; // total byte file yang sudah terkirim (untuk progress)
  unsigned long lastService;
  unsigned long lastReport;
};

// WAV Header (44 byte)
struct __attribute__((packed)) WAVHeader {
  char riff[4] = {'R', 'I', 'F', 'F'};
  uint32_t fileSize = 0;
  char wave[4] = {'W', 'A', 'V', 'E'};
  char fmt[4] = {'f', 'm', 't', ' '};
  uint32_t fmtSize = 16;
  uint16_t audioFormat = 1; // PCM
  uint16_t numChannels = NUM_CHANNELS;
  uint32_t sampleRate = SAMPLING_FREQ;
  uint32_t byteRate = SAMPLING_FREQ * NUM_CHANNELS * (BITS_PER_SAMPLE / 8);
  uint16_t blockAlign = NUM_CHANNELS * (BITS_PER_SAMPLE / 8);
  uint16_t bitsPerSample = BITS_PER_SAMPLE;
  char data[4] = {'d', 'a', 't', 'a'};
  uint32_t dataSize = 0;
};
static_assert(sizeof(WAVHeader) == 44, "WAV header must be 44 bytes");

// loop() menjalankan upload TLS + web server + MQTT secara bertingkat;
// stack default 8 KB terlalu mepet. (Makro ini mendefinisikan fungsi, jadi
// harus berada SETELAH semua deklarasi tipe.)
SET_LOOP_TASK_STACK_SIZE(16 * 1024);

// ============================================================================
// Helpers
// ============================================================================

void fillWavHeader(WAVHeader &h, uint32_t samples) {
  h.dataSize = samples * NUM_CHANNELS * (BITS_PER_SAMPLE / 8);
  h.fileSize = h.dataSize + sizeof(WAVHeader) - 8;
}

RecState getRecState() {
  RecState copy;
  portENTER_CRITICAL(&stateMux);
  copy = recState;
  portEXIT_CRITICAL(&stateMux);
  return copy;
}

bool endsWith(const char *s, const char *suffix) {
  size_t ls = strlen(s), lx = strlen(suffix);
  return ls >= lx && strcmp(s + ls - lx, suffix) == 0;
}

const char *baseName(const char *path) {
  const char *slash = strrchr(path, '/');
  return slash ? slash + 1 : path;
}

bool isUploadedPath(const char *path) {
  return strncmp(path, UP_DIR "/", strlen(UP_DIR) + 1) == 0;
}

bool isCurrentRecording(const char *path) {
  RecState s = getRecState();
  return s.active && strcmp(s.filename, path) == 0;
}

bool isBusyFile(const char *path) {
  return isCurrentRecording(path) || strcmp(path, currentUploadPath) == 0;
}

bool isRejected(const char *path) {
  for (int i = 0; i < rejectedCount; i++)
    if (strcmp(rejectedFiles[i], path) == 0)
      return true;
  return false;
}

void addRejected(const char *path) {
  if (isRejected(path))
    return;
  int slot = rejectedCount < MAX_REJECTED ? rejectedCount++
                                          : (MAX_REJECTED - 1);
  strlcpy(rejectedFiles[slot], path, MAX_PATH_LEN);
}

bool enqueueJob(JobType type, const char *filename) {
  for (int i = 0; i < jobCount; i++)
    if (jobs[i].type == type && strcmp(jobs[i].filename, filename) == 0)
      return true; // already queued
  if (jobCount >= MAX_JOBS)
    return false;
  jobs[jobCount].type = type;
  strlcpy(jobs[jobCount].filename, filename, MAX_PATH_LEN);
  jobCount++;
  return true;
}

const char *resetReasonText() {
  switch (esp_reset_reason()) {
  case ESP_RST_POWERON:
    return "power_on";
  case ESP_RST_SW:
    return "software";
  case ESP_RST_PANIC:
    return "crash";
  case ESP_RST_INT_WDT:
  case ESP_RST_TASK_WDT:
  case ESP_RST_WDT:
    return "watchdog";
  case ESP_RST_BROWNOUT:
    return "brownout";
  case ESP_RST_DEEPSLEEP:
    return "deep_sleep";
  case ESP_RST_EXT:
    return "external";
  default:
    return "unknown";
  }
}

bool timeSynced() { return time(nullptr) > 1700000000; }

bool serverUsesTls() { return SERVER_PORT == 443; }

// ============================================================================
// Configuration: config.h + perubahan dari dashboard lokal / MQTT (NVS)
// ============================================================================

// Key NVS yang menimpa nilai config.h
const char *OVERRIDE_KEYS[] = {"wifi_ssid", "wifi_pass", "device_id",
                               "token",     "server_ip", "server_port"};

uint32_t fnv1a(uint32_t h, const char *str) {
  while (*str) {
    h ^= (uint8_t)*str++;
    h *= 16777619u;
  }
  h ^= 0xff; // pemisah antar field
  return h * 16777619u;
}

// Sidik jari nilai di config.h. Jika berubah (firmware baru di-upload dengan
// config.h yang diedit), nilai config.h kembali dipakai.
uint32_t codeConfigHash() {
  char port[8];
  snprintf(port, sizeof(port), "%d", CFG_SERVER_PORT);
  uint32_t h = 2166136261u;
  h = fnv1a(h, CFG_WIFI_SSID);
  h = fnv1a(h, CFG_WIFI_PASSWORD);
  h = fnv1a(h, CFG_DEVICE_ID);
  h = fnv1a(h, CFG_DEVICE_TOKEN);
  h = fnv1a(h, CFG_SERVER_HOST);
  return fnv1a(h, port);
}

// getString() mencetak error di log jika key tidak ada, jadi cek dulu
void loadOverride(const char *key, char *dest, size_t len) {
  if (preferences.isKey(key))
    strlcpy(dest, preferences.getString(key, dest).c_str(), len);
}

void loadConfiguration() {
  preferences.begin("noisemon", false);

  uint32_t hash = codeConfigHash();
  if (preferences.getUInt("cfg_hash", 0) != hash) {
    for (const char *k : OVERRIDE_KEYS)
      if (preferences.isKey(k))
        preferences.remove(k);
    preferences.putUInt("cfg_hash", hash);
    Serial.println("config.h changed: using its values");
  }

  loadOverride("wifi_ssid", WIFI_SSID, sizeof(WIFI_SSID));
  loadOverride("wifi_pass", WIFI_PASS, sizeof(WIFI_PASS));
  loadOverride("device_id", DEVICE_ID, sizeof(DEVICE_ID));
  loadOverride("token", DEVICE_TOKEN, sizeof(DEVICE_TOKEN));
  loadOverride("server_ip", SERVER_HOST, sizeof(SERVER_HOST));
  if (preferences.isKey("server_port"))
    SERVER_PORT = preferences.getInt("server_port", CFG_SERVER_PORT);

  autoUpload = preferences.isKey("auto_upload")
                   ? preferences.getBool("auto_upload", true)
                   : true;
  micGain = preferences.isKey("gain") ? preferences.getFloat("gain", 1.0f)
                                      : 1.0f;
  bootCount = (preferences.isKey("boot_count")
                   ? preferences.getUInt("boot_count", 0)
                   : 0) +
              1;
  preferences.putUInt("boot_count", bootCount);
  preferences.end();

  Serial.printf("Config: ID=%s, WiFi=%s, Server=%s://%s:%d, AutoUpload=%s, "
                "Gain=%.1f, Boot=%u\n",
                DEVICE_ID, WIFI_SSID, serverUsesTls() ? "https" : "http",
                SERVER_HOST, SERVER_PORT, autoUpload ? "ON" : "OFF",
                (float)micGain, bootCount);
}

void saveOverride(const char *key, const char *value) {
  preferences.begin("noisemon", false);
  preferences.putString(key, value);
  preferences.end();
}

void saveOverridePort(int port) {
  preferences.begin("noisemon", false);
  preferences.putInt("server_port", port);
  preferences.end();
}

void resetOverrides() {
  preferences.begin("noisemon", false);
  for (const char *k : OVERRIDE_KEYS)
    if (preferences.isKey(k))
      preferences.remove(k);
  preferences.end();
}

// Nama setting yang saat ini berbeda dari config.h (untuk dashboard)
void listOverrides(JsonArray arr) {
  preferences.begin("noisemon", true);
  for (const char *k : OVERRIDE_KEYS)
    if (preferences.isKey(k))
      arr.add(k);
  preferences.end();
}

void saveAutoUpload(bool enabled) {
  preferences.begin("noisemon", false);
  preferences.putBool("auto_upload", enabled);
  preferences.end();
}

void saveGain(float gain) {
  preferences.begin("noisemon", false);
  preferences.putFloat("gain", gain);
  preferences.end();
}

void applyServer(const char *host, int port) {
  strlcpy(SERVER_HOST, host, sizeof(SERVER_HOST));
  SERVER_PORT = port;
  saveOverride("server_ip", SERVER_HOST);
  saveOverridePort(SERVER_PORT);
  // Coba upload lagi segera ke server yang baru
  uploadBackoffMs = UPLOAD_BACKOFF_MIN_MS;
  nextAutoUploadAt = millis();
  rejectedCount = 0;
  pendingHealth = true;
}

// Sambung (ulang) ke WiFi tujuan. Hotspot tetap menyala.
void connectStation() {
  lastWifiAttempt = millis();
  if (!WIFI_SSID[0]) {
    Serial.println("No WiFi SSID configured: hotspot only");
    return;
  }
  Serial.printf("Connecting to WiFi \"%s\"...\n", WIFI_SSID);
  WiFi.disconnect();
  WiFi.begin(WIFI_SSID, WIFI_PASS);
}

void setupWiFi() {
  // Kredensial dikelola sendiri (config.h + NVS), jangan disimpan SDK
  WiFi.persistent(false);
  WiFi.mode(WIFI_AP_STA);
  // Reconnect diatur maintainConnections() dengan jeda, supaya pencarian
  // WiFi tidak terus-menerus memutus klien hotspot
  WiFi.setAutoReconnect(false);

  snprintf(apSsid, sizeof(apSsid), "Noisemon-%s", DEVICE_ID);
  const char *apPass = strlen(CFG_AP_PASSWORD) >= 8 ? CFG_AP_PASSWORD : nullptr;
  WiFi.softAPConfig(AP_IP, AP_IP, IPAddress(255, 255, 255, 0));
  bool apOk = WiFi.softAP(apSsid, apPass, 1, 0, AP_MAX_CLIENTS);
  Serial.printf("Hotspot %s: \"%s\" password \"%s\" -> http://%s (login %s)\n",
                apOk ? "ON" : "FAILED", apSsid, apPass ? apPass : "(open)",
                AP_IP.toString().c_str(), CFG_WEB_USER);

  connectStation();
}

// ============================================================================
// Hardware setup
// ============================================================================

void setupI2S() {
  const i2s_config_t i2s_config = {
      .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX),
      .sample_rate = SAMPLING_FREQ,
      .bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT,
      .channel_format = I2S_CHANNEL_FMT_ONLY_LEFT,
      .communication_format = I2S_COMM_FORMAT_STAND_I2S,
      .intr_alloc_flags = ESP_INTR_FLAG_LEVEL1,
      // 16 x 512 sample = ~0.5 detik buffer DMA untuk menyerap jeda tulis SD
      .dma_buf_count = 16,
      .dma_buf_len = 512,
      .use_apll = false};

  const i2s_pin_config_t pin_config = {.bck_io_num = I2S_SCK,
                                       .ws_io_num = I2S_WS,
                                       .data_out_num = -1,
                                       .data_in_num = I2S_SD};

  i2s_driver_install(I2S_NUM_0, &i2s_config, 0, NULL);
  i2s_set_pin(I2S_NUM_0, &pin_config);
  Serial.println("I2S initialized");
}

// Pindahkan file lama ke struktur folder baru:
//  - /rec_xxx.wav (firmware v2, di root)  -> /rec/rec_xxx.wav
//  - /rec/x.wav + penanda /rec/x.wav.up   -> /rec/up/x.wav
void migrateLegacyFiles() {
  char path[MAX_PATH_LEN], dest[MAX_PATH_LEN];

  File root = SD.open("/");
  if (root) {
    File f;
    while ((f = root.openNextFile())) {
      bool isDir = f.isDirectory();
      snprintf(path, sizeof(path), "/%s", baseName(f.name()));
      f.close();
      if (isDir || !endsWith(path, ".wav"))
        continue;
      snprintf(dest, sizeof(dest), REC_DIR "/%s", baseName(path));
      if (!SD.exists(dest) && SD.rename(path, dest))
        Serial.printf("Migrated %s -> %s\n", path, dest);
    }
    root.close();
  }

  File dir = SD.open(REC_DIR);
  if (dir) {
    File f;
    while ((f = dir.openNextFile())) {
      snprintf(path, sizeof(path), REC_DIR "/%s", baseName(f.name()));
      f.close();
      if (!endsWith(path, ".wav.up"))
        continue;
      char wav[MAX_PATH_LEN];
      strlcpy(wav, path, sizeof(wav));
      wav[strlen(wav) - 3] = '\0'; // buang ".up"
      snprintf(dest, sizeof(dest), UP_DIR "/%s", baseName(wav));
      if (SD.exists(wav) && !SD.exists(dest))
        SD.rename(wav, dest);
      SD.remove(path);
    }
    dir.close();
  }
}

// Mount SD card (aman dipanggil ulang). Tidak me-remount saat merekam.
bool ensureSD() {
  if (sdReady && SD.cardType() != CARD_NONE)
    return true;
  if (getRecState().active)
    return false;

  SD.end();
  sdReady = SD.begin(SD_CS);
  if (!sdReady) {
    Serial.println("SD Card mount failed!");
    return false;
  }
  if (!SD.exists(REC_DIR))
    SD.mkdir(REC_DIR);
  if (!SD.exists(UP_DIR))
    SD.mkdir(UP_DIR);
  migrateLegacyFiles();
  Serial.printf("SD Card ready: %llu MB total, %llu MB free\n",
                SD.totalBytes() / (1024 * 1024),
                (SD.totalBytes() - SD.usedBytes()) / (1024 * 1024));
  return true;
}

uint64_t sdFreeBytes() { return SD.totalBytes() - SD.usedBytes(); }

// ============================================================================
// Audio task (I2S -> gain -> SD card + FFT frame)
// ============================================================================

static File recFile; // hanya dipakai audioTask

static void sendRecEvt(RecEvtType type, const char *filename, uint32_t samples,
                       uint32_t planned, bool writeError) {
  RecEvt evt = {};
  evt.type = type;
  strlcpy(evt.filename, filename, sizeof(evt.filename));
  evt.samples = samples;
  evt.plannedSeconds = planned;
  evt.writeError = writeError;
  xQueueSend(recEvtQueue, &evt, 0);
}

static void audioStartRecording(const RecCmd &cmd) {
  if (recState.active) // hanya audioTask yang menulis recState
    return;

  // Jangan pernah menimpa rekaman yang sudah ada
  if (SD.exists(cmd.filename)) {
    sendRecEvt(REC_EVT_OPEN_FAILED, cmd.filename, 0, cmd.durationSeconds,
               false);
    return;
  }

  recFile = SD.open(cmd.filename, FILE_WRITE);
  if (!recFile) {
    sendRecEvt(REC_EVT_OPEN_FAILED, cmd.filename, 0, cmd.durationSeconds,
               false);
    return;
  }

  WAVHeader header;
  fillWavHeader(header, 0);
  recFile.write((uint8_t *)&header, sizeof(header));

  uint32_t seconds = cmd.durationSeconds;
  if (seconds == 0 || seconds > MAX_RECORDING_SECONDS)
    seconds = MAX_RECORDING_SECONDS; // manual stop tetap dibatasi 4 jam

  portENTER_CRITICAL(&stateMux);
  recState.active = true;
  strlcpy(recState.filename, cmd.filename, sizeof(recState.filename));
  recState.samples = 0;
  recState.targetSamples = (uint32_t)SAMPLING_FREQ * seconds;
  recState.plannedSeconds = cmd.durationSeconds;
  portEXIT_CRITICAL(&stateMux);

  sendRecEvt(REC_EVT_STARTED, cmd.filename, 0, cmd.durationSeconds, false);
}

static void audioSyncHeader(uint32_t samples) {
  WAVHeader header;
  fillWavHeader(header, samples);
  size_t end = recFile.position();
  recFile.seek(0);
  recFile.write((uint8_t *)&header, sizeof(header));
  recFile.seek(end);
  recFile.flush();
}

static void audioFinishRecording(bool writeError) {
  if (!recState.active)
    return;

  RecState s = getRecState();
  if (recFile) {
    WAVHeader header;
    fillWavHeader(header, s.samples);
    recFile.seek(0);
    recFile.write((uint8_t *)&header, sizeof(header));
    recFile.close();
  }

  // Tandai selesai SETELAH file ditutup supaya loop tidak meng-upload
  // file yang masih terbuka.
  portENTER_CRITICAL(&stateMux);
  recState.active = false;
  portEXIT_CRITICAL(&stateMux);

  sendRecEvt(REC_EVT_COMPLETED, s.filename, s.samples, s.plannedSeconds,
             writeError);
}

void audioTask(void *param) {
  static int16_t buf[SAMPLES];
  unsigned long lastHeaderSync = 0;

  for (;;) {
    RecCmd cmd;
    while (xQueueReceive(recCmdQueue, &cmd, 0) == pdTRUE) {
      if (cmd.type == REC_CMD_START) {
        audioStartRecording(cmd);
        lastHeaderSync = millis();
      } else if (cmd.type == REC_CMD_STOP) {
        audioFinishRecording(false);
      }
    }

    size_t bytesRead = 0;
    i2s_read(I2S_NUM_0, buf, sizeof(buf), &bytesRead, portMAX_DELAY);
    int n = bytesRead / sizeof(int16_t);
    if (n <= 0)
      continue;

    // Gain dengan clipping (hindari overflow int16 yang bikin "kresek")
    float gain = micGain;
    if (gain != 1.0f) {
      for (int i = 0; i < n; i++) {
        int32_t v = lroundf(buf[i] * gain);
        if (v > 32767)
          v = 32767;
        else if (v < -32768)
          v = -32768;
        buf[i] = (int16_t)v;
      }
    }

    if (recState.active && recFile) {
      size_t want = n * sizeof(int16_t);
      unsigned long t0 = millis();
      size_t written = recFile.write((uint8_t *)buf, want);
      uint32_t took = millis() - t0;
      if (took > maxWriteMs)
        maxWriteMs = took;
      if (took > SLOW_WRITE_MS)
        slowWrites = slowWrites + 1;

      if (written != want) {
        Serial.println("SD write failed (card full/removed?), stopping");
        sdWriteErrors = sdWriteErrors + 1;
        audioFinishRecording(true);
      } else {
        portENTER_CRITICAL(&stateMux);
        recState.samples += n;
        uint32_t samples = recState.samples;
        uint32_t target = recState.targetSamples;
        portEXIT_CRITICAL(&stateMux);

        if (target > 0 && samples >= target) {
          audioFinishRecording(false);
        } else if (millis() - lastHeaderSync >= HEADER_SYNC_MS) {
          lastHeaderSync = millis();
          audioSyncHeader(samples);
        }
      }
    }

    // Serahkan frame segar ke loop untuk FFT
    if (fftFrameRequested && !fftFrameReady) {
      for (int i = 0; i < n; i++)
        fftFrame[i] = buf[i] / REFERENCE_AMPLITUDE;
      fftFrameLen = n;
      fftFrameRequested = false;
      fftFrameReady = true;
    }
  }
}

// ============================================================================
// MQTT publishing (hanya dari loop)
// ============================================================================

void publishJson(const char *topic, JsonDocument &doc, bool retained = false) {
  if (!mqttClient.connected())
    return;
  static char buffer[3072];
  size_t len = serializeJson(doc, buffer, sizeof(buffer));
  mqttClient.publish(topic, (const uint8_t *)buffer, len, retained);
}

void publishRecordingStatus(const char *status, const char *filename = nullptr,
                            uint32_t durationSec = 0, uint32_t sizeBytes = 0,
                            int progress = -1, const char *error = nullptr) {
  JsonDocument doc;
  doc["status"] = status;
  doc["device_id"] = DEVICE_ID;
  doc["timestamp"] = (uint32_t)time(nullptr);
  if (filename && filename[0]) {
    doc["filename"] = filename;
    doc["duration"] = durationSec;
    doc["size"] = sizeBytes;
  }
  if (progress >= 0)
    doc["progress"] = progress;
  if (error && error[0])
    doc["error"] = error;
  publishJson(recording_status_topic, doc);
  Serial.printf("Recording status: %s %s %s\n", status, filename ? filename : "",
                error ? error : "");
}

void publishRecordingProgress() {
  RecState s = getRecState();
  if (!s.active)
    return;

  JsonDocument doc;
  doc["status"] = "recording";
  doc["device_id"] = DEVICE_ID;
  doc["filename"] = s.filename;
  doc["elapsed"] = s.samples / SAMPLING_FREQ;
  doc["duration"] = s.plannedSeconds;
  doc["samples"] = s.samples;
  doc["size"] = s.samples * 2 + sizeof(WAVHeader);
  publishJson(recording_status_topic, doc);
}

// Hitung FFT dari frame terbaru dan publish ke audio/<id>/data
void publishAudioData() {
  int n = fftFrameLen;
  if (n <= 0 || n > SAMPLES)
    n = SAMPLES;

  double sumSq = 0;
  for (int i = 0; i < SAMPLES; i++) {
    float v = (i < n) ? fftFrame[i] : 0.0f;
    vReal[i] = v;
    vImag[i] = 0;
    sumSq += (double)v * v;
  }
  fftFrameReady = false;

  double rms = sqrt(sumSq / n);
  float dbSPL = 0;
  if (rms > 0.0001) {
    dbSPL = 20.0 * log10(rms * REFERENCE_AMPLITUDE) + 30.0;
  }

  FFT.windowing(vReal, SAMPLES, FFT_WIN_TYP_HAMMING, FFT_FORWARD);
  FFT.compute(vReal, vImag, SAMPLES, FFT_FORWARD);
  FFT.complexToMagnitude(vReal, vImag, SAMPLES);
  float peak_freq = FFT.majorPeak(vReal, SAMPLES, SAMPLING_FREQ);

  int spectrum[64];
  int bin_size = (SAMPLES / 2) / 64;
  long low = 0, mid = 0, high = 0;
  for (int i = 0; i < 64; i++) {
    double avg = 0;
    for (int j = 0; j < bin_size; j++)
      avg += vReal[i * bin_size + j];
    avg /= bin_size;
    spectrum[i] = constrain((int)(avg * 100), 0, 100);
    if (i < 20)
      low += spectrum[i];
    else if (i < 40)
      mid += spectrum[i];
    else
      high += spectrum[i];
  }

  // Simpan untuk dashboard lokal (tetap tersedia saat MQTT offline)
  liveDb = dbSPL;
  liveRms = rms;
  livePeak = (int)peak_freq;
  liveLow = low;
  liveMid = mid;
  liveHigh = high;
  for (int i = 0; i < 64; i++)
    liveSpectrum[i] = (uint8_t)spectrum[i];
  liveAt = millis();

  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["token"] = DEVICE_TOKEN;
  if (getRecState().active)
    doc["status"] = "recording";

  JsonObject audio = doc["audio"].to<JsonObject>();
  audio["rms"] = rms;
  audio["db_spl"] = dbSPL;

  JsonObject fft = doc["fft"].to<JsonObject>();
  fft["peak_frequency"] = (int)peak_freq;
  fft["total_energy"] = (int)(rms * 1000);
  JsonObject band = fft["band_energy"].to<JsonObject>();
  band["low"] = low;
  band["mid"] = mid;
  band["high"] = high;
  JsonArray specArr = fft["spectrum"].to<JsonArray>();
  for (int i = 0; i < 64; i++)
    specArr.add(spectrum[i]);

  publishJson(data_topic, doc);
}

// ============================================================================
// SD card housekeeping
// ============================================================================

// Cari file .wav paling lama (urut nama = urut waktu) di folder dir.
// skipRejected=true untuk pencarian file yang akan di-upload otomatis.
bool findOldestWav(const char *dirPath, bool skipRejected, char *out,
                   size_t len) {
  File dir = SD.open(dirPath);
  if (!dir || !dir.isDirectory())
    return false;

  bool found = false;
  out[0] = '\0';
  File f;
  while ((f = dir.openNextFile())) {
    char path[MAX_PATH_LEN];
    snprintf(path, sizeof(path), "%s/%s", dirPath, baseName(f.name()));
    bool isDir = f.isDirectory();
    f.close();

    if (isDir || !endsWith(path, ".wav") || isBusyFile(path))
      continue;
    if (skipRejected && isRejected(path))
      continue;

    if (!found || strcmp(path, out) < 0) {
      strlcpy(out, path, len);
      found = true;
    }
  }
  dir.close();
  return found;
}

void countWav(const char *dirPath, uint32_t &count, uint64_t &bytes) {
  count = 0;
  bytes = 0;
  File dir = SD.open(dirPath);
  if (!dir || !dir.isDirectory())
    return;
  File f;
  while ((f = dir.openNextFile())) {
    if (!f.isDirectory() && endsWith(f.name(), ".wav")) {
      count++;
      bytes += f.size();
    }
    f.close();
  }
  dir.close();
}

void invalidateSdSummary() { sdCacheValid = false; }

SdSummary getSdSummary(bool force = false) {
  if (!sdReady)
    return SdSummary{};
  if (force || !sdCacheValid || millis() - sdCacheAt > SD_SUMMARY_TTL_MS) {
    SdSummary s = {};
    countWav(REC_DIR, s.pendingFiles, s.pendingBytes);
    countWav(UP_DIR, s.uploadedFiles, s.uploadedBytes);
    s.totalMb = (uint32_t)(SD.totalBytes() / (1024 * 1024));
    s.freeMb = (uint32_t)(sdFreeBytes() / (1024 * 1024));
    sdCache = s;
    sdCacheAt = millis();
    sdCacheValid = true;
  }
  return sdCache;
}

// Laravel menyimpan path saat rekaman selesai (/rec/x.wav). Setelah upload
// file pindah ke /rec/up/x.wav, dan file v2 lama pindah dari root. Cari
// file berdasarkan namanya di semua lokasi tersebut.
bool resolvePath(const char *requested, char *out, size_t len) {
  if (SD.exists(requested)) {
    strlcpy(out, requested, len);
    return true;
  }
  const char *dirs[] = {REC_DIR, UP_DIR};
  for (const char *d : dirs) {
    snprintf(out, len, "%s/%s", d, baseName(requested));
    if (SD.exists(out))
      return true;
  }
  out[0] = '\0';
  return false;
}

// Pindahkan file yang sudah ter-upload ke /rec/up (tetap disimpan)
void moveToUploaded(const char *path) {
  if (isUploadedPath(path))
    return;
  char dest[MAX_PATH_LEN];
  snprintf(dest, sizeof(dest), UP_DIR "/%s", baseName(path));
  if (SD.exists(dest))
    SD.remove(dest);
  if (!SD.rename(path, dest))
    Serial.printf("Failed to move %s to %s\n", path, dest);
  invalidateSdSummary();
}

bool deleteRecordingFile(const char *path) {
  if (isBusyFile(path))
    return false;
  invalidateSdSummary();
  return SD.remove(path);
}

// Pastikan ada ruang kosong >= neededBytes dengan menghapus rekaman lama
// yang SUDAH ter-upload. Rekaman yang belum ter-upload tidak disentuh.
bool ensureFreeSpace(uint64_t neededBytes) {
  uint64_t freeBytes = sdFreeBytes();
  while (freeBytes < neededBytes) {
    char oldest[MAX_PATH_LEN];
    if (!findOldestWav(UP_DIR, false, oldest, sizeof(oldest)))
      break;
    Serial.printf("SD low on space, deleting uploaded file %s\n", oldest);
    if (!deleteRecordingFile(oldest))
      break;
    publishRecordingStatus("file_deleted", oldest, 0, 0, -1, "sd_full_cleanup");
    freeBytes = sdFreeBytes();
  }
  return freeBytes >= neededBytes;
}

// Kirim daftar semua rekaman di SD ke audio/<id>/files, 25 file per pesan
void publishFileList() {
  if (!ensureSD()) {
    JsonDocument doc;
    doc["device_id"] = DEVICE_ID;
    doc["list_id"] = millis();
    doc["page"] = 0;
    doc["last"] = true;
    doc["error"] = "sd_error";
    doc["files"].to<JsonArray>();
    publishJson(files_topic, doc);
    return;
  }

  const uint32_t listId = millis();
  const int PER_PAGE = 25;
  int page = 0;
  JsonDocument doc;
  JsonArray files;

  auto startPage = [&]() {
    doc.clear();
    doc["device_id"] = DEVICE_ID;
    doc["list_id"] = listId;
    doc["page"] = page;
    files = doc["files"].to<JsonArray>();
  };
  auto flushPage = [&](bool last) {
    doc["last"] = last;
    publishJson(files_topic, doc);
    mqttClient.loop();
    page++;
    startPage();
  };

  startPage();
  const char *dirs[] = {REC_DIR, UP_DIR};
  for (const char *d : dirs) {
    File dir = SD.open(d);
    if (!dir)
      continue;
    File f;
    while ((f = dir.openNextFile())) {
      bool isDir = f.isDirectory();
      char path[MAX_PATH_LEN];
      snprintf(path, sizeof(path), "%s/%s", d, baseName(f.name()));
      uint32_t size = f.size();
      f.close();
      if (isDir || !endsWith(path, ".wav"))
        continue;

      JsonObject o = files.add<JsonObject>();
      o["n"] = path;
      o["s"] = size;
      o["u"] = isUploadedPath(path) ? 1 : 0;
      if (isCurrentRecording(path))
        o["r"] = 1; // sedang direkam

      if (files.size() >= PER_PAGE)
        flushPage(false);
    }
    dir.close();
  }
  flushPage(true);
  Serial.printf("Published file list (%d pages)\n", page);
}

void publishHealth() {
  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["fw"] = FW_VERSION;
  doc["timestamp"] = (uint32_t)time(nullptr);
  doc["uptime"] = millis() / 1000;
  doc["boot_count"] = bootCount;
  doc["reset_reason"] = resetReasonText();
  doc["heap_free"] = ESP.getFreeHeap();
  doc["heap_min"] = ESP.getMinFreeHeap();
  doc["heap_max_block"] = ESP.getMaxAllocHeap();

  JsonObject net = doc["wifi"].to<JsonObject>();
  net["rssi"] = WiFi.RSSI();
  net["ip"] = WiFi.localIP().toString();
  net["ntp"] = timeSynced();

  char server[96];
  snprintf(server, sizeof(server), "%s://%s:%d",
           serverUsesTls() ? "https" : "http", SERVER_HOST, SERVER_PORT);
  doc["server"] = server;

  JsonObject sd = doc["sd"].to<JsonObject>();
  bool ok = ensureSD();
  sd["ok"] = ok;
  if (ok) {
    SdSummary s = getSdSummary();
    sd["total_mb"] = s.totalMb;
    sd["free_mb"] = s.freeMb;
    sd["pending_files"] = s.pendingFiles;
    sd["pending_mb"] = (uint32_t)(s.pendingBytes / (1024 * 1024));
    sd["uploaded_files"] = s.uploadedFiles;
    sd["uploaded_mb"] = (uint32_t)(s.uploadedBytes / (1024 * 1024));
  }
  sd["max_write_ms"] = maxWriteMs;
  sd["slow_writes"] = slowWrites;
  sd["write_errors"] = sdWriteErrors;
  maxWriteMs = 0; // per periode

  RecState r = getRecState();
  JsonObject rec = doc["recording"].to<JsonObject>();
  rec["active"] = r.active;
  if (r.active) {
    rec["filename"] = r.filename;
    rec["elapsed"] = r.samples / SAMPLING_FREQ;
    rec["duration"] = r.plannedSeconds;
  }

  JsonObject up = doc["upload"].to<JsonObject>();
  up["auto"] = autoUpload;
  up["active"] = isUploading;
  if (isUploading)
    up["filename"] = currentUploadPath;
  up["upload_all"] = uploadAllActive;
  long retryIn = (long)(nextAutoUploadAt - millis()) / 1000;
  up["next_retry_s"] = retryIn > 0 ? retryIn : 0;
  if (lastUploadError[0])
    up["last_error"] = lastUploadError;
  up["rejected"] = rejectedCount;

  doc["gain"] = micGain;

  // Retained: halaman web langsung dapat kondisi terakhir saat subscribe
  publishJson(health_topic, doc, true);
}

// ============================================================================
// Recording control (dari loop / MQTT callback)
// ============================================================================

void makeRecordingFilename(char *out, size_t len) {
  char base[MAX_PATH_LEN];
  struct tm t;
  if (getLocalTime(&t, 10) && (t.tm_year + 1900) >= 2024) {
    snprintf(base, sizeof(base), REC_DIR "/%s_%04d%02d%02d_%02d%02d%02d",
             DEVICE_ID, t.tm_year + 1900, t.tm_mon + 1, t.tm_mday, t.tm_hour,
             t.tm_min, t.tm_sec);
  } else {
    // NTP belum sinkron: pakai boot counter supaya tetap unik setelah reboot
    snprintf(base, sizeof(base), REC_DIR "/%s_boot%05u_%010lu", DEVICE_ID,
             bootCount, millis());
  }

  // Unik di kedua folder (/rec dan /rec/up)
  char tmp[MAX_PATH_LEN];
  snprintf(out, len, "%s.wav", base);
  for (int i = 1; i < 100 && resolvePath(out, tmp, sizeof(tmp)); i++)
    snprintf(out, len, "%s_%d.wav", base, i);
}

bool requestStartRecording(uint32_t durationSeconds) {
  if (getRecState().active) {
    Serial.println("Already recording, start ignored");
    publishRecordingProgress();
    return false;
  }
  if (!ensureSD()) {
    publishRecordingStatus("sd_error");
    return false;
  }

  if (durationSeconds > MAX_RECORDING_SECONDS)
    durationSeconds = MAX_RECORDING_SECONDS;

  // Usahakan ada ruang untuk seluruh durasi + cadangan
  uint32_t plannedSec =
      durationSeconds > 0 ? durationSeconds : MAX_RECORDING_SECONDS;
  uint64_t needed = (uint64_t)plannedSec * BYTES_PER_SECOND + SD_RESERVE_BYTES;
  if (!ensureFreeSpace(needed)) {
    if (sdFreeBytes() < SD_RESERVE_BYTES) {
      Serial.println("SD card full (no uploaded files left to delete)");
      publishRecordingStatus("sd_full");
      return false;
    }
    Serial.println("Warning: SD may fill up before recording ends");
  }

  RecCmd cmd = {};
  cmd.type = REC_CMD_START;
  cmd.durationSeconds = durationSeconds;
  makeRecordingFilename(cmd.filename, sizeof(cmd.filename));
  return xQueueSend(recCmdQueue, &cmd, pdMS_TO_TICKS(100)) == pdTRUE;
}

void requestStopRecording() {
  RecCmd cmd = {};
  cmd.type = REC_CMD_STOP;
  xQueueSend(recCmdQueue, &cmd, pdMS_TO_TICKS(100));
}

void processRecEvents() {
  RecEvt evt;
  while (xQueueReceive(recEvtQueue, &evt, 0) == pdTRUE) {
    uint32_t durationSec = evt.samples / SAMPLING_FREQ;
    uint32_t sizeBytes = evt.samples * 2 + sizeof(WAVHeader);

    switch (evt.type) {
    case REC_EVT_STARTED:
      Serial.printf("Started recording: %s (%u s)\n", evt.filename,
                    evt.plannedSeconds);
      publishRecordingStatus("recording", evt.filename, evt.plannedSeconds, 0);
      break;
    case REC_EVT_OPEN_FAILED:
      Serial.printf("Failed to open %s for writing\n", evt.filename);
      sdReady = false; // paksa remount di percobaan berikutnya
      publishRecordingStatus("sd_error", evt.filename);
      break;
    case REC_EVT_COMPLETED:
      invalidateSdSummary();
      Serial.printf("Recording finished: %s (%u s, %u bytes)%s\n",
                    evt.filename, durationSec, sizeBytes,
                    evt.writeError ? " [SD write error]" : "");
      if (evt.writeError)
        sdReady = false;
      publishRecordingStatus("completed", evt.filename, durationSec, sizeBytes,
                             -1, evt.writeError ? "sd_write_error" : nullptr);
      // Beri jeda sedikit agar Laravel mendaftarkan record lebih dulu
      if (autoUpload)
        nextAutoUploadAt = millis() + 2000;
      break;
    }
  }
}

// Hentikan rekaman, tunggu file WAV ditutup, dan kirim status "completed"
// sebelum restart (dipakai oleh restart / reset_wifi / update_wifi)
void stopRecordingAndWait() {
  if (getRecState().active) {
    requestStopRecording();
    unsigned long start = millis();
    while (getRecState().active && millis() - start < 3000)
      delay(20);
  }
  processRecEvents();
  mqttClient.loop();
  delay(200);
}

// ============================================================================
// Upload to Laravel
// ============================================================================

unsigned long lastFFTTime = 0;
unsigned long lastProgressTime = 0;

// MQTT, FFT dan progress tetap jalan selama operasi panjang (upload,
// unduhan file dari dashboard). Tidak memanggil web server.
void serviceBackground() {
  mqttClient.loop();
  processRecEvents();

  if (millis() - lastFFTTime >= FFT_INTERVAL_MS) {
    lastFFTTime = millis();
    fftFrameRequested = true;
  }
  if (fftFrameReady)
    publishAudioData();

  if (getRecState().active &&
      millis() - lastProgressTime >= PROGRESS_INTERVAL_MS) {
    lastProgressTime = millis();
    publishRecordingProgress();
  }
}

// Selama upload: juga layani dashboard lokal supaya tetap responsif
void serviceDuringUpload() {
  serviceBackground();
  serviceWeb();
}

void setUploadError(const char *msg) {
  strlcpy(lastUploadError, msg, sizeof(lastUploadError));
}

// Kirim satu potongan file [offset, offset+len) sebagai multipart POST.
// Return kode HTTP, atau 0 jika gagal di jaringan (lastUploadError diisi).
int postChunk(File &file, UploadCtx &ctx, uint32_t offset, uint32_t len,
              uint32_t index, uint32_t total) {
  // Objek lokal: memori TLS dilepas lagi setelah tiap potongan
  WiFiClient plain;
  WiFiClientSecure secure;
  WiFiClient *client = &plain;
  if (serverUsesTls()) {
    secure.setInsecure(); // TODO: pasang root CA untuk validasi sertifikat
    client = &secure;
  }

  bool connected = client->connect(SERVER_HOST, SERVER_PORT);
  if (!connected && !serverUsesTls()) {
    setUploadError("server_unreachable");
    return 0;
  }
  if (!connected) {
    char err[64] = "";
    int code = secure.lastError(err, sizeof(err));
    Serial.printf("TLS connect failed: %d %s (free heap %u, largest block %u)\n",
                  code, err, ESP.getFreeHeap(), ESP.getMaxAllocHeap());

    // Tanpa PSRAM, sesi TLS MQTT + sesi TLS upload sering tidak muat di RAM.
    // Lepas sesi MQTT selama upload, lalu coba sekali lagi.
    if (mqttClient.connected()) {
      Serial.println("Pausing MQTT during the upload to free memory");
      mqttClient.disconnect();
      espClient.stop();
      mqttPausedForUpload = true;
      secure.stop();
      delay(100);
      connected = secure.connect(SERVER_HOST, SERVER_PORT);
      if (!connected) {
        code = secure.lastError(err, sizeof(err));
        Serial.printf("TLS retry failed: %d %s (free heap %u, largest block %u)\n",
                      code, err, ESP.getFreeHeap(), ESP.getMaxAllocHeap());
      }
    }
    if (!connected) {
      snprintf(lastUploadError, sizeof(lastUploadError), "tls_error_%d_ram_%uk",
               code, ESP.getMaxAllocHeap() / 1024);
      return 0;
    }
  }

  const String boundary = "----NoisemonBoundary7MA4YWxkTrZu0gW";
  String head;
  auto addField = [&](const char *name, const String &value) {
    head += "--" + boundary + "\r\n";
    head += "Content-Disposition: form-data; name=\"" + String(name) +
            "\"\r\n\r\n" + value + "\r\n";
  };
  addField("device_id", DEVICE_ID);
  addField("token", DEVICE_TOKEN);
  addField("duration", String(ctx.durationSec));
  // Path lengkap di SD (PHP memotong filename menjadi basename saja)
  addField("sd_filename", ctx.path);
  addField("total_size", String(ctx.fileSize));
  addField("total_chunks", String(total));
  addField("chunk_index", String(index));
  addField("chunk_offset", String(offset));
  head += "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"audio\"; filename=\"" +
          String(baseName(ctx.path)) + "\"\r\n";
  head += "Content-Type: audio/wav\r\n\r\n";
  String tail = "\r\n--" + boundary + "--\r\n";
  uint32_t contentLength = head.length() + len + tail.length();

  // Port standar tidak ditulis di Host (virtual host / Cloudflare)
  String hostHeader = String(SERVER_HOST);
  if (SERVER_PORT != 443 && SERVER_PORT != 80)
    hostHeader += ":" + String(SERVER_PORT);

  client->print(String("POST ") + UPLOAD_PATH + " HTTP/1.1\r\n");
  client->print("Host: " + hostHeader + "\r\n");
  client->print("User-Agent: Noisemon-ESP32/" FW_VERSION "\r\n");
  client->print("Accept: application/json\r\n");
  client->print("Content-Type: multipart/form-data; boundary=" + boundary +
                "\r\n");
  client->print("Content-Length: " + String(contentLength) + "\r\n");
  client->print("Connection: close\r\n\r\n");
  client->print(head);

  static uint8_t buffer[4096];
  file.seek(offset);
  uint32_t remaining = len;
  while (remaining > 0) {
    size_t want = remaining < sizeof(buffer) ? remaining : sizeof(buffer);
    size_t got = file.read(buffer, want);
    if (got == 0) {
      setUploadError("sd_read_error");
      client->stop();
      return 0;
    }
    if (client->write(buffer, got) != got) {
      setUploadError("connection_lost");
      client->stop();
      return 0;
    }
    remaining -= got;
    ctx.sent += got;
    uploadProgress = (int)((uint64_t)ctx.sent * 100 / ctx.fileSize);

    if (millis() - ctx.lastService >= 100) {
      ctx.lastService = millis();
      serviceDuringUpload();
    }
    if (millis() - ctx.lastReport >= PROGRESS_INTERVAL_MS) {
      ctx.lastReport = millis();
      int pct = (int)((uint64_t)ctx.sent * 100 / ctx.fileSize);
      publishRecordingStatus("uploading", ctx.path, ctx.durationSec,
                             ctx.fileSize, pct);
    }
  }
  client->print(tail);

  // Tunggu status line: "HTTP/1.1 201 Created"
  unsigned long start = millis();
  while (client->connected() && !client->available() &&
         millis() - start < HTTP_RESPONSE_TIMEOUT_MS) {
    serviceDuringUpload();
    delay(10);
  }
  if (!client->available()) {
    client->stop();
    setUploadError("response_timeout");
    return 0;
  }

  String statusLine = client->readStringUntil('\n');
  statusLine.trim();
  client->stop();

  int code = 0;
  int sp = statusLine.indexOf(' ');
  if (sp > 0)
    code = statusLine.substring(sp + 1, sp + 4).toInt();
  Serial.printf("Chunk %u/%u -> %s\n", index + 1, total, statusLine.c_str());
  return code;
}

// path harus path yang benar-benar ada (lihat resolvePath)
UploadResult uploadFile(const char *path) {
  if (WiFi.status() != WL_CONNECTED) {
    setUploadError("wifi_disconnected");
    publishRecordingStatus("upload_failed", path, 0, 0, -1, lastUploadError);
    return UPLOAD_RETRY;
  }
  if (isCurrentRecording(path)) {
    setUploadError("still_recording");
    publishRecordingStatus("upload_failed", path, 0, 0, -1, lastUploadError);
    return UPLOAD_RETRY;
  }

  File file = SD.open(path, FILE_READ);
  if (!file) {
    setUploadError("sd_read_error");
    publishRecordingStatus("upload_failed", path, 0, 0, -1, lastUploadError);
    return UPLOAD_RETRY;
  }

  uint32_t fileSize = file.size();
  if (fileSize <= sizeof(WAVHeader)) {
    file.close();
    setUploadError("empty_file");
    publishRecordingStatus("upload_failed", path, 0, fileSize, -1,
                           lastUploadError);
    return UPLOAD_REJECTED;
  }

  UploadCtx ctx = {};
  ctx.path = path;
  ctx.fileSize = fileSize;
  ctx.durationSec = (fileSize - sizeof(WAVHeader)) / BYTES_PER_SECOND;
  ctx.lastReport = millis();

  uint32_t totalChunks = (fileSize + UPLOAD_CHUNK_BYTES - 1) / UPLOAD_CHUNK_BYTES;

  isUploading = true;
  uploadProgress = 0;
  strlcpy(currentUploadPath, path, sizeof(currentUploadPath));
  publishRecordingStatus("uploading", path, ctx.durationSec, fileSize, 0);
  Serial.printf("Uploading %s (%u bytes, %u chunk(s)) to %s://%s:%d\n", path,
                fileSize, totalChunks, serverUsesTls() ? "https" : "http",
                SERVER_HOST, SERVER_PORT);

  UploadResult result = UPLOAD_RETRY;
  for (uint32_t i = 0; i < totalChunks; i++) {
    uint32_t offset = i * UPLOAD_CHUNK_BYTES;
    uint32_t len = fileSize - offset;
    if (len > UPLOAD_CHUNK_BYTES)
      len = UPLOAD_CHUNK_BYTES;

    int code = postChunk(file, ctx, offset, len, i, totalChunks);
    bool last = (i == totalChunks - 1);

    // Potongan tengah dijawab 200, potongan terakhir 201 (file tersimpan)
    if (code == 200 || code == 201) {
      if (last)
        result = UPLOAD_OK;
      continue;
    }

    if (code != 0) {
      char msg[24];
      snprintf(msg, sizeof(msg), "http_%d", code);
      setUploadError(msg);
      // Ditolak (validasi/token/terlalu besar): jangan diulang terus-menerus.
      // 409 = urutan potongan tidak cocok -> ulangi dari awal nanti.
      if (code >= 400 && code < 500 && code != 408 && code != 409 &&
          code != 429)
        result = UPLOAD_REJECTED;
    }
    break;
  }

  file.close();
  isUploading = false;
  currentUploadPath[0] = '\0';
  if (mqttPausedForUpload) {
    mqttPausedForUpload = false; // maintainConnections() menyambung lagi
    Serial.println("Upload done, MQTT will reconnect");
  }

  if (result == UPLOAD_OK) {
    lastUploadError[0] = '\0';
    // File TETAP disimpan di SD, hanya dipindah ke /rec/up
    moveToUploaded(path);
    publishRecordingStatus("upload_completed", path, ctx.durationSec, fileSize,
                           100);
  } else {
    publishRecordingStatus("upload_failed", path, ctx.durationSec, fileSize,
                           -1, lastUploadError);
  }
  return result;
}

void finishUploadAll(bool aborted) {
  if (!uploadAllActive)
    return;
  uploadAllActive = false;
  JsonDocument doc;
  doc["status"] = "upload_all_done";
  doc["device_id"] = DEVICE_ID;
  doc["uploaded"] = uploadAllOk;
  doc["failed"] = uploadAllFailed;
  doc["aborted"] = aborted;
  if (aborted && lastUploadError[0])
    doc["error"] = lastUploadError;
  publishJson(recording_status_topic, doc);
  Serial.printf("upload_all done: %u ok, %u failed%s\n", uploadAllOk,
                uploadAllFailed, aborted ? " (aborted)" : "");
  pendingHealth = true;
}

// upload_all {force:true}: kembalikan file /rec/up ke /rec supaya di-upload ulang
void startUploadAll(bool force) {
  if (!ensureSD()) {
    publishRecordingStatus("sd_error");
    return;
  }
  if (force) {
    char path[MAX_PATH_LEN], dest[MAX_PATH_LEN];
    while (findOldestWav(UP_DIR, false, path, sizeof(path))) {
      snprintf(dest, sizeof(dest), REC_DIR "/%s", baseName(path));
      if (SD.exists(dest) || !SD.rename(path, dest))
        break;
    }
  }
  rejectedCount = 0; // beri kesempatan ulang
  uploadAllActive = true;
  uploadAllOk = 0;
  uploadAllFailed = 0;
  uploadBackoffMs = UPLOAD_BACKOFF_MIN_MS;
  nextAutoUploadAt = millis();
  JsonDocument doc;
  doc["status"] = "upload_all_started";
  doc["device_id"] = DEVICE_ID;
  doc["force"] = force;
  SdSummary s = getSdSummary(true);
  doc["files"] = s.pendingFiles;
  doc["mb"] = (uint32_t)(s.pendingBytes / (1024 * 1024));
  publishJson(recording_status_topic, doc);
}

void deleteAllUploaded() {
  if (!ensureSD())
    return;
  uint32_t deleted = 0;
  char path[MAX_PATH_LEN];
  while (findOldestWav(UP_DIR, false, path, sizeof(path))) {
    if (!deleteRecordingFile(path))
      break;
    deleted++;
  }
  JsonDocument doc;
  doc["status"] = "sd_cleaned";
  doc["device_id"] = DEVICE_ID;
  doc["deleted"] = deleted;
  publishJson(recording_status_topic, doc);
  Serial.printf("Deleted %u uploaded files from SD\n", deleted);
  // Laravel menyinkronkan status SD dari daftar file terbaru
  pendingListFiles = true;
  pendingHealth = true;
}

void processJob(const Job &job) {
  char path[MAX_PATH_LEN];
  if (!ensureSD() || !resolvePath(job.filename, path, sizeof(path))) {
    Serial.printf("Job: %s not found on SD\n", job.filename);
    if (job.type == JOB_UPLOAD)
      publishRecordingStatus("upload_failed", job.filename, 0, 0, -1,
                             "not_found");
    else
      publishRecordingStatus("file_deleted", job.filename, 0, 0, -1,
                             "not_found");
    return;
  }

  if (job.type == JOB_UPLOAD) {
    Serial.printf("Job: upload %s\n", path);
    uploadFile(path);
  } else if (job.type == JOB_DELETE) {
    if (deleteRecordingFile(path)) {
      Serial.printf("Job: deleted %s\n", path);
      publishRecordingStatus("file_deleted", job.filename);
    }
  }
}

// Proses job on-demand dulu, lalu upload otomatis / upload_all
void uploadManager() {
  if (jobCount > 0) {
    Job job = jobs[0];
    for (int i = 1; i < jobCount; i++)
      jobs[i - 1] = jobs[i];
    jobCount--;
    processJob(job);
    return;
  }

  if (!autoUpload && !uploadAllActive)
    return;
  if (WiFi.status() != WL_CONNECTED)
    return;
  if ((long)(millis() - nextAutoUploadAt) < 0)
    return;
  if (!ensureSD()) {
    nextAutoUploadAt = millis() + AUTO_UPLOAD_SCAN_MS;
    return;
  }

  char path[MAX_PATH_LEN];
  if (!findOldestWav(REC_DIR, true, path, sizeof(path))) {
    finishUploadAll(false);
    nextAutoUploadAt = millis() + AUTO_UPLOAD_SCAN_MS;
    return;
  }

  UploadResult r = uploadFile(path);
  if (r == UPLOAD_OK) {
    uploadBackoffMs = UPLOAD_BACKOFF_MIN_MS;
    nextAutoUploadAt = millis() + 1000; // lanjut file berikutnya
    if (uploadAllActive)
      uploadAllOk++;
  } else if (r == UPLOAD_REJECTED) {
    addRejected(path);
    nextAutoUploadAt = millis() + 1000;
    if (uploadAllActive)
      uploadAllFailed++;
  } else {
    // Server/jaringan bermasalah: hentikan upload_all, lanjut dengan backoff
    if (uploadAllActive) {
      uploadAllFailed++;
      finishUploadAll(true);
    }
    Serial.printf("Auto upload failed, retry in %lu s\n",
                  uploadBackoffMs / 1000);
    nextAutoUploadAt = millis() + uploadBackoffMs;
    uploadBackoffMs = min(uploadBackoffMs * 2, UPLOAD_BACKOFF_MAX_MS);
  }
}

// ============================================================================
// MQTT
// ============================================================================

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  JsonDocument doc;
  if (deserializeJson(doc, payload, length)) {
    Serial.println("MQTT: invalid JSON");
    return;
  }
  const char *action = doc["action"];
  if (!action)
    return;

  if (strcmp(topic, command_topic) == 0) {
    Serial.printf("Command: %s\n", action);
    if (strcmp(action, "start_recording") == 0) {
      requestStartRecording(doc["duration"] | 0);
    } else if (strcmp(action, "stop_recording") == 0) {
      requestStopRecording();
    } else if (strcmp(action, "upload_file") == 0) {
      const char *fname = doc["filename"];
      if (fname && fname[0] && !enqueueJob(JOB_UPLOAD, fname))
        publishRecordingStatus("upload_failed", fname, 0, 0, -1, "queue_full");
    } else if (strcmp(action, "delete_file") == 0) {
      const char *fname = doc["filename"];
      if (fname && fname[0])
        enqueueJob(JOB_DELETE, fname);
    } else if (strcmp(action, "upload_all") == 0) {
      startUploadAll(doc["force"] | false);
    } else if (strcmp(action, "list_files") == 0) {
      pendingListFiles = true;
    } else if (strcmp(action, "get_status") == 0) {
      pendingHealth = true;
    } else if (strcmp(action, "delete_uploaded") == 0) {
      pendingDeleteUploaded = true;
    } else if (strcmp(action, "set_auto_upload") == 0) {
      autoUpload = doc["enabled"] | true;
      saveAutoUpload(autoUpload);
      if (autoUpload)
        nextAutoUploadAt = millis();
      Serial.printf("Auto upload %s\n", autoUpload ? "ON" : "OFF");
      pendingHealth = true;
    } else if (strcmp(action, "restart") == 0) {
      pendingRestart = true;
    } else if (strcmp(action, "reset_wifi") == 0) {
      // Kembali ke WiFi dari config.h (hotspot lokal tetap aktif)
      Serial.println("Command: WiFi back to config.h values");
      preferences.begin("noisemon", false);
      preferences.remove("wifi_ssid");
      preferences.remove("wifi_pass");
      preferences.end();
      strlcpy(WIFI_SSID, CFG_WIFI_SSID, sizeof(WIFI_SSID));
      strlcpy(WIFI_PASS, CFG_WIFI_PASSWORD, sizeof(WIFI_PASS));
      pendingWifiReconnect = true;
    } else if (strcmp(action, "update_wifi") == 0 ||
               strcmp(action, "set_server") == 0) {
      const char *new_ssid = doc["ssid"];
      const char *new_pass = doc["password"];
      const char *new_host = doc["server_ip"];
      int new_port = doc["server_port"] | 0;

      if (new_host && new_host[0] && new_port > 0) {
        Serial.printf("Command: server -> %s:%d\n", new_host, new_port);
        applyServer(new_host, new_port);
      }

      // WiFi hanya diganti jika SSID diisi; tidak perlu restart
      if (new_ssid && new_ssid[0]) {
        Serial.printf("Command: WiFi -> %s\n", new_ssid);
        strlcpy(WIFI_SSID, new_ssid, sizeof(WIFI_SSID));
        strlcpy(WIFI_PASS, new_pass ? new_pass : "", sizeof(WIFI_PASS));
        saveOverride("wifi_ssid", WIFI_SSID);
        saveOverride("wifi_pass", WIFI_PASS);
        pendingWifiReconnect = true;
      }
    }
  } else if (strcmp(topic, control_topic) == 0) {
    if (strcmp(action, "set_gain") == 0) {
      float g = doc["value"] | 1.0f;
      micGain = constrain(g, 0.1f, 50.0f);
      // Simpan ke NVS setelah slider berhenti digeser (hemat flash)
      gainDirty = true;
      gainChangedAt = millis();
    }
  }
}

void setupMQTT() {
  snprintf(data_topic, sizeof(data_topic), "audio/%s/data", DEVICE_ID);
  snprintf(command_topic, sizeof(command_topic), "audio/%s/command", DEVICE_ID);
  snprintf(control_topic, sizeof(control_topic), "audio/%s/control", DEVICE_ID);
  snprintf(recording_status_topic, sizeof(recording_status_topic),
           "audio/%s/recording_status", DEVICE_ID);
  snprintf(health_topic, sizeof(health_topic), "audio/%s/health", DEVICE_ID);
  snprintf(files_topic, sizeof(files_topic), "audio/%s/files", DEVICE_ID);

  espClient.setInsecure();
  mqttClient.setServer(CFG_MQTT_HOST, CFG_MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(3200);
  mqttClient.setKeepAlive(30);
  mqttClient.setSocketTimeout(10);
}

unsigned long lastMqttAttempt = 0;
unsigned long lastHealthTime = 0;

void maintainConnections() {
  if (pendingWifiReconnect) {
    pendingWifiReconnect = false;
    mqttClient.disconnect();
    connectStation();
    return;
  }

  if (WiFi.status() != WL_CONNECTED) {
    bool longDown = millis() - lastWifiConnectedAt > 300000UL;
    unsigned long wait = longDown ? WIFI_RETRY_SLOW_MS : WIFI_RETRY_MS;
    if (WIFI_SSID[0] && millis() - lastWifiAttempt >= wait) {
      Serial.println("WiFi not connected, retrying...");
      connectStation();
    }
    return;
  }
  lastWifiConnectedAt = millis();

  if (mqttClient.connected())
    return;
  if (lastMqttAttempt != 0 && millis() - lastMqttAttempt < MQTT_RETRY_MS)
    return;
  lastMqttAttempt = millis();

  // Last Will: Laravel langsung tahu device offline kalau koneksi putus
  char will[96];
  snprintf(will, sizeof(will), "{\"device_id\":\"%s\",\"status\":\"offline\"}",
           DEVICE_ID);

  if (mqttClient.connect(DEVICE_ID, CFG_MQTT_USER, CFG_MQTT_PASSWORD, data_topic, 0, false,
                         will)) {
    mqttClient.subscribe(command_topic);
    mqttClient.subscribe(control_topic);
    Serial.println("MQTT Connected");

    if (getRecState().active)
      publishRecordingProgress();
    else
      publishRecordingStatus("idle");
    pendingHealth = true;
  } else {
    Serial.printf("MQTT connect failed, rc=%d\n", mqttClient.state());
  }
}

// ============================================================================
// Setup & Loop
// ============================================================================

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0); // Disable Brownout Detector
  delay(1000);                               // Wait for power to stabilize

  Serial.begin(115200);
  Serial.println("\n\nNoisemon ESP32 - Recording System v" FW_VERSION
                 " (SD + Server + Local Dashboard)");

  loadConfiguration();
  setupWiFi();
  configTzTime(TZ_INFO, NTP_SERVER_1, NTP_SERVER_2);

  ensureSD();
  setupI2S();

  recCmdQueue = xQueueCreate(4, sizeof(RecCmd));
  recEvtQueue = xQueueCreate(8, sizeof(RecEvt));
  // Core 1, prioritas di atas loop() (1) supaya I2S/SD tidak pernah tertunda
  xTaskCreatePinnedToCore(audioTask, "audio", 8192, NULL, 5, NULL, 1);

  setupMQTT();
  setupWeb();
  nextAutoUploadAt = millis() + 15000; // upload sisa file setelah boot
  Serial.printf("Free heap after setup: %u bytes\n", ESP.getFreeHeap());
}

void loop() {
  serviceWeb(); // dashboard lokal + DNS hotspot
  maintainConnections();
  mqttClient.loop();

  processRecEvents();

  // FFT & dB SPL tiap 1 detik
  if (millis() - lastFFTTime >= FFT_INTERVAL_MS) {
    lastFFTTime = millis();
    fftFrameRequested = true;
  }
  if (fftFrameReady)
    publishAudioData();

  // Progress rekaman tiap 5 detik
  if (getRecState().active &&
      millis() - lastProgressTime >= PROGRESS_INTERVAL_MS) {
    lastProgressTime = millis();
    publishRecordingProgress();
  }

  // Perintah yang ditunda dari callback MQTT
  if (pendingDeleteUploaded) {
    pendingDeleteUploaded = false;
    deleteAllUploaded();
  }
  if (pendingListFiles) {
    pendingListFiles = false;
    publishFileList();
  }
  if (pendingRestart) {
    Serial.println("Restart requested");
    publishRecordingStatus("restarting");
    stopRecordingAndWait();
    ESP.restart();
  }

  // Health tiap 60 detik (atau segera jika diminta)
  if (pendingHealth || millis() - lastHealthTime >= HEALTH_INTERVAL_MS) {
    pendingHealth = false;
    lastHealthTime = millis();
    publishHealth();
  }

  if (gainDirty && millis() - gainChangedAt >= GAIN_SAVE_DELAY_MS) {
    gainDirty = false;
    saveGain(micGain);
  }

  // Upload on-demand / otomatis (blocking, tapi audioTask tetap merekam)
  if (!isUploading)
    uploadManager();

  delay(5);
}
