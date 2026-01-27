/**
 * Noisemon ESP32 Firmware - Integrated Version
 *
 * Hardware: ESP32 + INMP441 Microphone (I2S)
 * Features:
 *   - 16-bit / 16kHz Sampling (User Optimized)
 *   - dB SPL Calculation & Calibration
 *   - FFT Real-time Analysis (64 Bins)
 *   - MQTT Connectivity (HiveMQ Cloud)
 *   - Remote Digital Gain Control
 */

#include <ArduinoJson.h>
#include <PubSubClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <arduinoFFT.h>
#include <driver/i2s.h>

// --- Configuration ---
const char *ssid = "YOUR_WIFI_SSID";
const char *password = "YOUR_WIFI_PASSWORD";

// MQTT (HiveMQ Cloud)
const char *mqtt_server = "9b7d755e8d024ad08d0c39177e53c908.s1.eu.hivemq.cloud";
const int mqtt_port = 8883;
const char *mqtt_user = "kalicupak";
const char *mqtt_pass = "Kalicupak321";

// Device Info
const char *DEVICE_ID = "ESP32-KTC-01";
const char *DEVICE_TOKEN = "YOUR_DEVICE_TOKEN";

// I2S Pins (INMP441)
#define I2S_WS 25
#define I2S_SD 33
#define I2S_SCK 32
#define I2S_PORT I2S_NUM_0

// Audio Constants
#define SAMPLES 1024        // Must be a power of 2 for FFT
#define SAMPLING_FREQ 16000 // 16kHz (As per user request)
const float DB_OFFSET = 94.0;
const float REFERENCE_AMPLITUDE = 32768.0;

// Variables
WiFiClientSecure espClient;
PubSubClient client(espClient);
ArduinoFFT<double> FFT = ArduinoFFT<double>();

double vReal[SAMPLES];
double vImag[SAMPLES];
int16_t Buffer[SAMPLES]; // 16-bit buffer
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

  i2s_driver_install(I2S_PORT, &i2s_config, 0, NULL);
  i2s_set_pin(I2S_PORT, &pin_config);
}

void setupWiFi() {
  Serial.print("Connecting to WiFi...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  espClient.setInsecure();
}

void callback(char *topic, byte *payload, unsigned int length) {
  JsonDocument doc;
  deserializeJson(doc, payload, length);
  if (doc["action"] == "set_gain") {
    micGain = doc["value"];
    Serial.printf("Gain updated: %.1f\n", micGain);
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Connecting to MQTT...");
    if (client.connect(DEVICE_ID, mqtt_user, mqtt_pass)) {
      Serial.println("Connected!");
      char statusTopic[50];
      sprintf(statusTopic, "audio/%s/status", DEVICE_ID);
      client.publish(statusTopic, "{\"status\":\"online\"}", true);

      char controlTopic[50];
      sprintf(controlTopic, "audio/%s/control", DEVICE_ID);
      client.subscribe(controlTopic);
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  setupI2S();
  setupWiFi();
  client.setServer(mqtt_server, mqtt_port);
  client.setBufferSize(2048); // INCREASE BUFFER SIZE FOR LARGE JSON
  client.setCallback(callback);
}

void loop() {
  if (!client.connected())
    reconnect();
  client.loop();

  // 1. Read I2S Data
  size_t bytes_read;
  i2s_read(I2S_PORT, &Buffer, sizeof(Buffer), &bytes_read, portMAX_DELAY);
  int samples_read = bytes_read / sizeof(int16_t);

  // 2. Calculate RMS & Prepare FFT
  double sum_sq = 0;
  for (int i = 0; i < samples_read; i++) {
    double sample = (double)Buffer[i] / REFERENCE_AMPLITUDE;
    sample *= micGain; // Apply Remote Gain
    vReal[i] = sample;
    vImag[i] = 0;
    sum_sq += (sample * sample);
  }

  double rms = sqrt(sum_sq / samples_read);

  // Convert to dB SPL (Approximate calibration)
  float dbSPL = 0;
  if (rms > 0.0001) { // Threshold to avoid log(0)
    dbSPL = 20.0 * log10(rms * REFERENCE_AMPLITUDE) + 30.0;
  }

  // 3. Compute FFT
  FFT.windowing(vReal, SAMPLES, FFT_WIN_TYP_HAMMING, FFT_FORWARD);
  FFT.compute(vReal, vImag, SAMPLES, FFT_FORWARD);
  FFT.complexToMagnitude(vReal, vImag, SAMPLES);

  double peak_freq = FFT.majorPeak(vReal, SAMPLES, SAMPLING_FREQ);

  // 4. Spectrum & Band Energy
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

  // 5. Build & Send JSON
  JsonDocument doc;
  doc["device_id"] = DEVICE_ID;
  doc["token"] = DEVICE_TOKEN;

  JsonObject audio = doc["audio"].to<JsonObject>();
  audio["rms"] = rms;
  audio["db_spl"] = dbSPL; // NEW: sending dB SPL metadata

  JsonObject fft = doc["fft"].to<JsonObject>();
  fft["peak_frequency"] = (int)peak_freq;
  fft["total_energy"] = (int)(sum_sq * 10);

  JsonObject band = fft["band_energy"].to<JsonObject>();
  band["low"] = low;
  band["mid"] = mid;
  band["high"] = high;

  JsonArray specArr = fft["spectrum"].to<JsonArray>();
  for (int i = 0; i < 64; i++)
    specArr.add(spectrum[i]);

  char buffer[2048];
  serializeJson(doc, buffer);

  char dataTopic[50];
  sprintf(dataTopic, "audio/%s/data", DEVICE_ID);
  client.publish(dataTopic, buffer);

  Serial.printf("Published: RMS: %.4f, dB SPL: %.1f, Peak: %.1f Hz\n", rms,
                dbSPL, peak_freq);

  delay(200);
}
