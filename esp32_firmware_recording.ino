/**
 * Noisemon ESP32 Firmware Example (Recording + Upload Version)
 *
 * Hardware: ESP32 + INMP441 Microphone + SD Card Module
 * Optimized: 16-bit / 16000Hz
 */

#include <ArduinoJson.h>
#include <FS.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <SD.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <driver/i2s.h>

// --- Configuration ---
const char *ssid = "YOUR_WIFI_SSID";
const char *password = "YOUR_WIFI_PASSWORD";

// API (Laravel Server)
const char *upload_url = "http://your-laravel-domain.com/api/upload-audio";

// MQTT
const char *mqtt_server = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
const int mqtt_port = 8883;

// Device Info
const char *DEVICE_ID = "ESP32-KTC-01";
const char *DEVICE_TOKEN = "YOUR_DEVICE_TOKEN";

// I2S & SD Pins
#define I2S_WS 25
#define I2S_SD 33
#define I2S_SCK 32
#define SD_CS 5

// Audio Constants
#define SAMPLING_FREQ 16000
#define RECORD_TIME 10 // seconds

WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);
double micGain = 1.0;

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
}

void setupSD() {
  if (!SD.begin(SD_CS)) {
    Serial.println("SD Card Mount Failed");
    return;
  }
}

void recordAudio(const char *filename) {
  File file = SD.open(filename, FILE_WRITE);
  if (!file)
    return;

  Serial.println("Recording...");

  uint32_t samples_to_record = SAMPLING_FREQ * RECORD_TIME;
  uint32_t samples_recorded = 0;
  int16_t sample;
  size_t bytes_read;

  while (samples_recorded < samples_to_record) {
    i2s_read(I2S_NUM_0, &sample, 2, &bytes_read, portMAX_DELAY);

    // Apply Digital Gain
    sample = (int16_t)(sample * micGain);

    file.write((uint8_t *)&sample, 2);
    samples_recorded++;
  }

  file.close();
  Serial.println("Recording Finished!");
}

void uploadFile(const char *filename) {
  if (WiFi.status() != WL_CONNECTED)
    return;

  File file = SD.open(filename);
  if (!file)
    return;

  HTTPClient http;
  http.begin(upload_url);
  String boundary = "--------------------------321321123";
  http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);

  Serial.println("Uploading...");
  int httpResponseCode = http.sendRequest("POST", &file, file.size());

  if (httpResponseCode > 0) {
    Serial.printf("Response: %d\n", httpResponseCode);
  } else {
    Serial.printf("Error: %s\n", http.errorToString(httpResponseCode).c_str());
  }

  http.end();
  file.close();
}

void mqttCallback(char *topic, byte *payload, unsigned int length) {
  JsonDocument doc;
  deserializeJson(doc, payload, length);
  if (doc["action"] == "set_gain") {
    micGain = doc["value"];
    Serial.printf("Gain updated: %.1f\n", micGain);
  }
}

void setup() {
  Serial.begin(115200);
  setupI2S();
  setupSD();
  mqttClient.setBufferSize(2048); // INCREASE BUFFER SIZE FOR LARGE JSON
  // setupWiFi() and subscribe to control topic for micGain...
}

void loop() {
  // Logic for recording...
  delay(10000);
}
