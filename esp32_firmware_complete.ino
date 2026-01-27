/**
 * Noisemon ESP32 Firmware - Complete Recording System + Real-time FFT
 *
 * Hardware: ESP32 + INMP441 Microphone + SD Card Module
 * Features:
 * - Remote recording control via MQTT (Non-blocking)
 * - WAV file generation with proper headers
 * - Automatic upload to Laravel server
 * - Real-time FFT & dB SPL analysis over MQTT
 *
 * Optimized: 16-bit / 16000Hz Mono
 */

#include <ArduinoJson.h>
#include <FS.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <SD.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
// ... (previous includes)
#include "soc/rtc_cntl_reg.h" // BROWN-OUT FIX
#include "soc/soc.h"          // BROWN-OUT FIX
#include <Preferences.h>
#include <WiFiManager.h> // https://github.com/tzapu/WiFiManager
#include <arduinoFFT.h>
#include <driver/i2s.h>

// --- Configuration ---
const char *ssid = "harun";
const char *password = "harun3211";

// API (Laravel Server)
const char *upload_url = "http://192.168.97.91:8000/api/upload-audio";

// MQTT
const char *mqtt_server = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
const int mqtt_port = 8883;
const char *mqtt_user = "kalicupak";
const char *mqtt_pass = "Kalicupak321";

// Server Config (Will be loaded from Preferences)
char SERVER_IP[64] = "192.168.97.91";
int SERVER_PORT = 8000;

// Device Info (Will be loaded from Preferences)
char DEVICE_ID[32] = "ESP32-NOISEMON";
char DEVICE_TOKEN[64] = "DEFAULT_TOKEN";

// WiFiManager & Preferences
WiFiManager wm;
Preferences preferences;
bool shouldSaveConfig = false;

// I2S & SD Pins
#define I2S_WS 25
#define I2S_SD 33
#define I2S_SCK 32
#define SD_CS 5

// Audio Constants
#define SAMPLES 1024        // Must be a power of 2 for FFT
#define SAMPLING_FREQ 16000 // 16kHz
#define BITS_PER_SAMPLE 16
#define NUM_CHANNELS 1

// FFT Calibrations
const float DB_OFFSET = 94.0;
const float REFERENCE_AMPLITUDE = 32768.0;

// MQTT Topics
char data_topic[50];
char command_topic[50];
char status_topic[50];
char recording_status_topic[50];

WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);
ArduinoFFT<float> FFT = ArduinoFFT<float>(); // OPTIMIZATION: Use float

// Buffers
float vReal[SAMPLES]; // OPTIMIZATION: Use float
float vImag[SAMPLES]; // OPTIMIZATION: Use float
int16_t i2sBuffer[SAMPLES];

double micGain = 1.0;

// FFT Throttling
unsigned long lastFFTTime = 0;
const unsigned long FFT_INTERVAL = 1000; // 1 second interval

// Recording State
bool isRecording = false;
File audioFile;
uint32_t recordedSamples = 0;
uint32_t totalSamplesToRecord = 0;
String currentFilename = "";

// WAV Header Structure
struct WAVHeader {
  char riff[4] = {'R', 'I', 'F', 'F'};
  uint32_t fileSize;
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
  uint32_t dataSize;
};

// Function Prototypes
void stopRecording();
void uploadFile(const char *filename);

void saveConfigCallback() {
  Serial.println("Should save config");
  shouldSaveConfig = true;
}

void loadConfiguration() {
  preferences.begin("noisemon", false);
  String savedId = preferences.getString("device_id", "ESP32-8UQ7HQ");
  String savedToken =
      preferences.getString("token", "0ymJiyM4OjSM5HV1QvZiKqPMKquAbY3n");
  String savedIp = preferences.getString("server_ip", "192.168.97.91");
  SERVER_PORT = preferences.getInt("server_port", 8000);

  strncpy(DEVICE_ID, savedId.c_str(), sizeof(DEVICE_ID));
  strncpy(DEVICE_TOKEN, savedToken.c_str(), sizeof(DEVICE_TOKEN));
  strncpy(SERVER_IP, savedIp.c_str(), sizeof(SERVER_IP));

  preferences.end();
  Serial.printf("Loaded Config: ID=%s, Server=%s:%d\n", DEVICE_ID, SERVER_IP,
                SERVER_PORT);
}

void saveConfiguration(const char *id, const char *token, const char *ip,
                       int port) {
  preferences.begin("noisemon", false);
  preferences.putString("device_id", id);
  preferences.putString("token", token);
  preferences.putString("server_ip", ip);
  preferences.putInt("server_port", port);
  preferences.end();
  Serial.println("Config saved to NVS");
}

void setupWiFi() {
  loadConfiguration();

  // Custom parameters for WiFiManager
  WiFiManagerParameter custom_device_id("device_id", "Device ID", DEVICE_ID,
                                        32);
  WiFiManagerParameter custom_device_token("token", "Device Token",
                                           DEVICE_TOKEN, 64);
  WiFiManagerParameter custom_server_ip("server_ip", "Server IP", SERVER_IP,
                                        64);
  char portStr[6];
  itoa(SERVER_PORT, portStr, 10);
  WiFiManagerParameter custom_server_port("server_port", "Server Port", portStr,
                                          6);

  wm.addParameter(&custom_device_id);
  wm.addParameter(&custom_device_token);
  wm.addParameter(&custom_server_ip);
  wm.addParameter(&custom_server_port);
  wm.setSaveConfigCallback(saveConfigCallback);

  // Use a unique name for the AP
  String apName = "Noisemon_" + String(DEVICE_ID);
  if (!wm.autoConnect(apName.c_str())) {
    Serial.println("failed to connect and hit timeout");
    delay(3000);
    ESP.restart();
  }

  // Save the custom parameters to Preferences if changed
  if (shouldSaveConfig) {
    strncpy(DEVICE_ID, custom_device_id.getValue(), sizeof(DEVICE_ID));
    strncpy(DEVICE_TOKEN, custom_device_token.getValue(), sizeof(DEVICE_TOKEN));
    strncpy(SERVER_IP, custom_server_ip.getValue(), sizeof(SERVER_IP));
    SERVER_PORT = atoi(custom_server_port.getValue());
    saveConfiguration(DEVICE_ID, DEVICE_TOKEN, SERVER_IP, SERVER_PORT);
  }

  Serial.println("\nWiFi connected!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
}

void setupI2S() {
  const i2s_config_t i2s_config = {
      .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX),
      .sample_rate = SAMPLING_FREQ,
      .bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT,
      .channel_format = I2S_CHANNEL_FMT_ONLY_LEFT,
      .communication_format =
          (i2s_comm_format_t)(I2S_COMM_FORMAT_I2S | I2S_COMM_FORMAT_I2S_MSB),
      .intr_alloc_flags = ESP_INTR_FLAG_LEVEL1,
      .dma_buf_count = 8,
      .dma_buf_len = 128,
      .use_apll = false};

  const i2s_pin_config_t pin_config = {.bck_io_num = I2S_SCK,
                                       .ws_io_num = I2S_WS,
                                       .data_out_num = -1,
                                       .data_in_num = I2S_SD};

  i2s_driver_install(I2S_NUM_0, &i2s_config, 0, NULL);
  i2s_set_pin(I2S_NUM_0, &pin_config);
  Serial.println("I2S initialized");
}

void setupSD() {
  if (!SD.begin(SD_CS)) {
    Serial.println("SD Card Mount Failed!");
    return;
  }
  Serial.println("SD Card initialized");
}

void publishRecordingStatus(const char *status) {
  JsonDocument doc;
  doc["status"] = status;
  doc["device_id"] = DEVICE_ID;
  doc["timestamp"] = millis();

  char buffer[128];
  serializeJson(doc, buffer);
  mqttClient.publish(recording_status_topic, buffer);
  Serial.printf("Published recording status: %s\n", status);
}

void startRecording(const char *filename) {
  if (isRecording)
    return;

  if (SD.exists(filename)) {
    SD.remove(filename);
  }

  audioFile = SD.open(filename, FILE_WRITE);
  if (!audioFile) {
    Serial.println("Failed to open file for writing");
    return;
  }

  // Write placeholder header
  WAVHeader header;
  audioFile.write((uint8_t *)&header, sizeof(header));

  recordedSamples = 0;
  isRecording = true;
  currentFilename = String(filename);

  publishRecordingStatus("recording");
  Serial.printf("Started recording: %s (Manual Stop)\n", filename);
}

void stopRecording() {
  if (!isRecording)
    return;

  isRecording = false;

  // Update WAV header
  uint32_t dataSize = recordedSamples * 2;
  WAVHeader header;
  header.dataSize = dataSize;
  header.fileSize = dataSize + sizeof(WAVHeader) - 8;

  audioFile.seek(0);
  audioFile.write((uint8_t *)&header, sizeof(header));
  audioFile.close();

  publishRecordingStatus("idle");
  Serial.printf("Recording finished! %d samples.\n", recordedSamples);

  // Trigger Upload (This will block for a moment, but that's acceptable AFTER
  // recording)
  uploadFile(currentFilename.c_str());
}

void processFFTAndPublish(double rms) {
  // 1. Calculate dB SPL (Always do this as it's cheap)
  float dbSPL = 0;
  if (rms > 0.0001) {
    dbSPL = 20.0 * log10(rms * REFERENCE_AMPLITUDE) + 30.0;
  }

  // 2. Optimization: Skip heavy FFT if recording to save CPU/Network
  // Or at least only do it every 3 seconds instead of 1
  if (isRecording) {
    static unsigned long lastRecFFT = 0;
    if (millis() - lastRecFFT < 3000) {
      // Still send minimal status over MQTT so UI knows we are alive
      JsonDocument doc;
      doc["device_id"] = DEVICE_ID;
      doc["token"] = DEVICE_TOKEN;
      doc["status"] = "recording";
      doc["audio"]["db_spl"] = dbSPL;

      char buffer[256];
      serializeJson(doc, buffer);
      mqttClient.publish(data_topic, buffer);
      return;
    }
    lastRecFFT = millis();
  }

  // Compute FFT (Heavy)
  FFT.windowing(vReal, SAMPLES, FFT_WIN_TYP_HAMMING, FFT_FORWARD);
  FFT.compute(vReal, vImag, SAMPLES, FFT_FORWARD);
  FFT.complexToMagnitude(vReal, vImag, SAMPLES);

  double peak_freq = FFT.majorPeak(vReal, SAMPLES, SAMPLING_FREQ);

  // Spectrum & Band Energy
  int spectrum[64];
  int bin_size = (SAMPLES / 2) / 64;
  long low = 0, mid = 0, high = 0;

  for (int i = 0; i < 64; i++) {
    double avg = 0;
    for (int j = 0; j < bin_size; j++) {
      avg += vReal[i * bin_size + j];
    }
    avg /= bin_size;
    spectrum[i] = constrain((int)(avg * 100), 0, 100);

    if (i < 20)
      low += spectrum[i];
    else if (i < 40)
      mid += spectrum[i];
    else
      high += spectrum[i];
  }

  // Build JSON
  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["token"] = DEVICE_TOKEN;

  JsonObject audio = doc["audio"].to<JsonObject>();
  audio["rms"] = rms;
  audio["db_spl"] = dbSPL;

  JsonObject fft = doc["fft"].to<JsonObject>();
  fft["peak_frequency"] = (int)peak_freq;
  fft["total_energy"] = (int)(rms * 1000); // Approximation

  JsonObject band = fft["band_energy"].to<JsonObject>();
  band["low"] = low;
  band["mid"] = mid;
  band["high"] = high;

  JsonArray specArr = fft["spectrum"].to<JsonArray>();
  for (int i = 0; i < 64; i++)
    specArr.add(spectrum[i]);

  if (isRecording) {
    doc["status"] = "recording"; // Add status flag so UI knows
  }

  char buffer[2048];
  serializeJson(doc, buffer);
  mqttClient.publish(data_topic, buffer);
  // Serial.println("Published FFT data");
}

void uploadFile(const char *filename) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected, cannot upload.");
    return;
  }

  if (!SD.exists(filename)) {
    Serial.printf("File does not exist: %s\n", filename);
    return;
  }

  File file = SD.open(filename, FILE_READ);
  if (!file) {
    Serial.println("Failed to open file for reading");
    return;
  }

  uint32_t fileSize = file.size();
  Serial.printf("Uploading %s (%d bytes)...\n", filename, fileSize);

  if (fileSize == 0) {
    Serial.println("File is empty, skipping upload.");
    file.close();
    return;
  }

  // Raw WiFiClient for manual multipart POST
  WiFiClient client;
  const char *host = SERVER_IP;     // DYNAMIC
  const int port = SERVER_PORT;     // DYNAMIC
  String url = "/api/upload-audio"; // API Endpoint

  if (!client.connect(host, port)) {
    Serial.printf("Connection failed to %s:%d!\n", host, port);
    file.close();
    return;
  }

  String boundary = "----WebKitFormBoundary7MA4YWxkTrZu0gW";

  // Construct Multipart Head
  String head = "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"device_id\"\r\n\r\n" +
          String(DEVICE_ID) + "\r\n";
  head += "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"token\"\r\n\r\n" +
          String(DEVICE_TOKEN) + "\r\n";
  head += "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"duration\"\r\n\r\n10\r\n";
  head += "--" + boundary + "\r\n";
  head += "Content-Disposition: form-data; name=\"audio\"; filename=\"" +
          String(filename) + "\"\r\n";
  head += "Content-Type: audio/wav\r\n\r\n";

  String tail = "\r\n--" + boundary + "--\r\n";

  uint32_t contentLength = head.length() + fileSize + tail.length();

  // Send HTTP Headers
  client.println("POST " + url + " HTTP/1.1");
  client.println("Host: " + String(host) + ":" + String(port));
  client.println("Content-Type: multipart/form-data; boundary=" + boundary);
  client.print("Content-Length: ");
  client.println(contentLength);
  client.println("Connection: close");
  client.println(); // End of headers

  // Send Body
  client.print(head);

  // Stream File
  uint8_t buffer[1024];
  size_t bytesRead;
  while ((bytesRead = file.read(buffer, sizeof(buffer))) > 0) {
    client.write(buffer, bytesRead);
  }

  client.print(tail);

  // Read Response
  unsigned long timeout = millis();
  while (client.available() == 0) {
    if (millis() - timeout > 5000) {
      Serial.println("Timeout waiting for response");
      client.stop();
      file.close();
      return;
    }
  }

  // Skip headers
  while (client.connected()) {
    String line = client.readStringUntil('\n');
    if (line == "\r")
      break;
  }

  String responseLine = client.readStringUntil('\n');
  Serial.println("Response: " + responseLine);

  client.stop();
  file.close();
  SD.remove(filename);
  Serial.println("Upload finished.");
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  JsonDocument doc;
  deserializeJson(doc, payload, length);
  const char *action = doc["action"];

  if (strcmp(topic, command_topic) == 0) {
    if (strcmp(action, "start_recording") == 0) {
      String fname =
          "/rec_" + String(DEVICE_ID) + "_" + String(millis()) + ".wav";
      startRecording(fname.c_str());
    } else if (strcmp(action, "stop_recording") == 0) {
      stopRecording();
    } else if (strcmp(action, "reset_wifi") == 0) {
      Serial.println("Command: Resetting WiFi (Web Trigger)");
      wm.resetSettings();
      delay(1000);
      ESP.restart();
    } else if (strcmp(action, "update_wifi") == 0) {
      const char *new_ssid = doc["ssid"];
      const char *new_pass = doc["password"];
      const char *new_ip = doc["server_ip"];
      int new_port = doc["server_port"];

      if (new_ssid && new_pass && new_ip && new_port) {
        Serial.printf("Command: Update. SSID:%s, Server:%s:%d\n", new_ssid,
                      new_ip, new_port);

        // Save new config
        strncpy(SERVER_IP, new_ip, sizeof(SERVER_IP));
        SERVER_PORT = new_port;
        saveConfiguration(DEVICE_ID, DEVICE_TOKEN, SERVER_IP, SERVER_PORT);

        // Connect to new WiFi
        WiFi.begin(new_ssid, new_pass);
        // WiFiManager will typically reconnect to the last used credentials on
        // reboot or we can let wm.autoConnect handle it next time. For
        // immediate change:
        delay(2000);
        ESP.restart();
      }
    }
  } else if (strstr(topic, "/control")) {
    if (strcmp(action, "set_gain") == 0)
      micGain = doc["value"];
  }
}

void setupMQTT() {
  snprintf(data_topic, sizeof(data_topic), "audio/%s/data", DEVICE_ID);
  snprintf(command_topic, sizeof(command_topic), "audio/%s/command", DEVICE_ID);
  snprintf(status_topic, sizeof(status_topic), "audio/%s/status", DEVICE_ID);
  snprintf(recording_status_topic, sizeof(recording_status_topic),
           "audio/%s/recording_status", DEVICE_ID);

  espClient.setInsecure();
  mqttClient.setServer(mqtt_server, mqtt_port);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(2048);
}

void reconnectMQTT() {
  if (mqttClient.connect(DEVICE_ID, mqtt_user, mqtt_pass)) {
    mqttClient.subscribe(command_topic);

    // Subscribe to control topic for Gain Slider
    char control_topic[50];
    snprintf(control_topic, sizeof(control_topic), "audio/%s/control",
             DEVICE_ID);
    mqttClient.subscribe(control_topic);

    Serial.println("MQTT Connected");
    publishRecordingStatus(isRecording ? "recording" : "idle");
  }
}

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0); // Disable Brownout Detector
  delay(1000);                               // Wait for power to stabilize

  Serial.begin(115200);
  Serial.println("\n\nNoisemon ESP32 - Recording System v2");

  setupWiFi();
  setupI2S();
  setupSD();
  setupMQTT();
}

void loop() {
  if (!mqttClient.connected())
    reconnectMQTT();
  mqttClient.loop();

  // 1. Read I2S Data Chunk
  size_t bytes_read;
  i2s_read(I2S_NUM_0, &i2sBuffer, sizeof(i2sBuffer), &bytes_read,
           portMAX_DELAY);
  int samples_read = bytes_read / sizeof(int16_t);

  // 2. Process Buffer (Gain + Stats)
  double sum_sq = 0;
  for (int i = 0; i < samples_read; i++) {
    int16_t sample = i2sBuffer[i];
    sample = (int16_t)(sample * micGain); // Apply Gain
    i2sBuffer[i] = sample;                // Update buffer for recording

    // FFT Data Prep
    double norm_sample = (double)sample / REFERENCE_AMPLITUDE;
    vReal[i] = norm_sample;
    vImag[i] = 0;
    sum_sq += (norm_sample * norm_sample);
  }

  // 3. Write to SD if Recording
  if (isRecording) {
    if (audioFile) {
      audioFile.write((uint8_t *)i2sBuffer, bytes_read);
      recordedSamples += samples_read;
      // Manual stop only
    }
  }

  // 4. Calculate RMS & Send FFT (THROTTLED)
  if (millis() - lastFFTTime >= FFT_INTERVAL) {
    lastFFTTime = millis();
    double rms = sqrt(sum_sq / samples_read);
    processFFTAndPublish(rms);
  }
}
