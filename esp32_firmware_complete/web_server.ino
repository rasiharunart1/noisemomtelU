/**
 * Dashboard lokal Noisemon (bagian dari sketch esp32_firmware_complete).
 *
 * Bisa dibuka dari hotspot ESP32 (http://192.168.4.1) maupun dari jaringan
 * WiFi yang sama (http://<IP device>). Login: CFG_WEB_USER / CFG_WEB_PASSWORD.
 *
 * Catatan memori:
 *  - Halaman (web_ui.h) dikirim langsung dari flash dalam bentuk gzip.
 *  - Daftar file & unduhan WAV dikirim bertahap (streaming), tidak pernah
 *    ditampung utuh di RAM.
 *  - Handler hanya menjadwalkan pekerjaan berat (upload, hapus massal,
 *    restart) untuk loop(); tidak ada upload di dalam handler.
 */

// ---------------------------------------------------------------- helpers

bool webAuth() {
  if (web.authenticate(CFG_WEB_USER, CFG_WEB_PASSWORD))
    return true;
  web.requestAuthentication(BASIC_AUTH, "Noisemon");
  return false;
}

void sendJsonDoc(JsonDocument &doc, int code = 200) {
  String out;
  out.reserve(1536);
  serializeJson(doc, out);
  web.sendHeader("Cache-Control", "no-store");
  web.send(code, "application/json", out);
}

void sendOk(const char *msg) {
  JsonDocument d;
  d["ok"] = true;
  d["msg"] = msg;
  sendJsonDoc(d);
}

void sendErr(int code, const char *msg) {
  JsonDocument d;
  d["ok"] = false;
  d["msg"] = msg;
  sendJsonDoc(d, code);
}

// Hanya file rekaman di /rec (termasuk /rec/up) yang boleh diakses
bool validRecPath(const String &p) {
  return p.startsWith(REC_DIR "/") && p.endsWith(".wav") &&
         p.indexOf("..") < 0 && p.length() < MAX_PATH_LEN;
}

bool validHost(const String &h) {
  if (h.length() == 0 || h.length() >= sizeof(SERVER_HOST))
    return false;
  for (size_t i = 0; i < h.length(); i++) {
    char c = h[i];
    if (!isalnum((unsigned char)c) && c != '.' && c != '-')
      return false;
  }
  return true;
}

bool validDeviceId(const String &id) {
  if (id.length() == 0 || id.length() >= sizeof(DEVICE_ID))
    return false;
  for (size_t i = 0; i < id.length(); i++) {
    char c = id[i];
    if (!isalnum((unsigned char)c) && c != '-' && c != '_')
      return false;
  }
  return true;
}

// Layani dashboard (dipanggil dari loop dan selama upload)
void serviceWeb() {
  dns.processNextRequest();
  if (webBusy)
    return;
  webBusy = true;
  web.handleClient();
  webBusy = false;
}

// ---------------------------------------------------------------- pages

void handleRoot() {
  if (!webAuth())
    return;
  web.sendHeader("Content-Encoding", "gzip");
  web.sendHeader("Cache-Control", "no-cache");
  web.send_P(200, "text/html; charset=utf-8", (const char *)WEB_UI_GZ,
             WEB_UI_GZ_LEN);
}

// Hotspot: arahkan semua domain ke dashboard (captive portal)
void handleNotFound() {
  String host = web.hostHeader();
  if (host != AP_IP.toString() && host != WiFi.localIP().toString()) {
    web.sendHeader("Location", String("http://") + AP_IP.toString() + "/",
                   true);
    web.send(302, "text/plain", "");
    return;
  }
  web.send(404, "text/plain", "Not found");
}

// ---------------------------------------------------------------- API

void handleStatus() {
  if (!webAuth())
    return;

  JsonDocument doc;
  doc["id"] = DEVICE_ID;
  doc["fw"] = FW_VERSION;
  doc["time_ok"] = timeSynced();
  doc["time"] = timeSynced() ? (uint32_t)time(nullptr) : 0;
  doc["uptime"] = millis() / 1000;
  doc["heap"] = ESP.getFreeHeap();
  doc["heap_min"] = ESP.getMinFreeHeap();
  doc["heap_max"] = ESP.getMaxAllocHeap(); // blok terbesar (TLS butuh ~40 KB)
  doc["reset"] = resetReasonText();
  doc["boot"] = bootCount;

  bool sta = WiFi.status() == WL_CONNECTED;
  JsonObject w = doc["wifi"].to<JsonObject>();
  w["ssid"] = WIFI_SSID;
  w["connected"] = sta;
  w["rssi"] = sta ? WiFi.RSSI() : 0;
  w["ip"] = sta ? WiFi.localIP().toString() : String("-");

  JsonObject ap = doc["ap"].to<JsonObject>();
  ap["ssid"] = apSsid;
  ap["ip"] = AP_IP.toString();
  ap["clients"] = WiFi.softAPgetStationNum();

  doc["mqtt"] = mqttClient.connected();
  char server[96];
  snprintf(server, sizeof(server), "%s://%s:%d",
           serverUsesTls() ? "https" : "http", SERVER_HOST, SERVER_PORT);
  doc["server"] = server;
  doc["auto_upload"] = autoUpload;
  doc["gain"] = micGain;

  JsonObject live = doc["live"].to<JsonObject>();
  live["db"] = liveDb;
  live["rms"] = liveRms;
  live["peak"] = livePeak;
  live["low"] = liveLow;
  live["mid"] = liveMid;
  live["high"] = liveHigh;
  live["age"] = liveAt ? millis() - liveAt : 999999;
  JsonArray spec = live["spec"].to<JsonArray>();
  for (int i = 0; i < 64; i++)
    spec.add(liveSpectrum[i]);

  RecState r = getRecState();
  JsonObject rec = doc["rec"].to<JsonObject>();
  rec["active"] = r.active;
  rec["file"] = r.active ? r.filename : "";
  rec["elapsed"] = r.samples / SAMPLING_FREQ;
  rec["duration"] = r.plannedSeconds;

  JsonObject up = doc["up"].to<JsonObject>();
  up["active"] = isUploading;
  up["file"] = isUploading ? currentUploadPath : "";
  up["progress"] = uploadProgress;
  up["all"] = uploadAllActive;
  up["error"] = lastUploadError;
  long retryIn = (long)(nextAutoUploadAt - millis()) / 1000;
  up["retry_s"] = retryIn > 0 ? retryIn : 0;

  SdSummary s = getSdSummary();
  JsonObject sd = doc["sd"].to<JsonObject>();
  sd["ok"] = sdReady;
  sd["total_mb"] = s.totalMb;
  sd["free_mb"] = s.freeMb;
  sd["pending"] = s.pendingFiles;
  sd["pending_mb"] = (uint32_t)(s.pendingBytes / (1024 * 1024));
  sd["uploaded"] = s.uploadedFiles;
  sd["uploaded_mb"] = (uint32_t)(s.uploadedBytes / (1024 * 1024));
  sd["max_write"] = maxWriteMs;
  sd["slow"] = slowWrites;
  sd["errors"] = sdWriteErrors;

  sendJsonDoc(doc);
}

// Daftar file dikirim per baris (chunked) supaya RAM tetap kecil
void handleFiles() {
  if (!webAuth())
    return;
  if (!sdReady) {
    sendErr(503, "SD card not available");
    return;
  }

  web.setContentLength(CONTENT_LENGTH_UNKNOWN);
  web.sendHeader("Cache-Control", "no-store");
  web.send(200, "application/json", "");
  web.sendContent("{\"files\":[");

  bool first = true;
  char line[MAX_PATH_LEN + 64];
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
      // Lewati file selain WAV dan nama yang perlu di-escape di JSON
      if (isDir || !endsWith(path, ".wav") || strpbrk(path, "\"\\"))
        continue;
      snprintf(line, sizeof(line), "%s{\"n\":\"%s\",\"s\":%u,\"u\":%d,\"r\":%d}",
               first ? "" : ",", path, size, isUploadedPath(path) ? 1 : 0,
               isCurrentRecording(path) ? 1 : 0);
      web.sendContent(line);
      first = false;
    }
    dir.close();
  }
  web.sendContent("]}");
  web.sendContent(""); // akhir chunked response
}

// Putar / unduh WAV dari SD. Mendukung Range supaya player bisa di-seek.
void handleSdFile() {
  if (!webAuth())
    return;
  String path = web.arg("path");
  if (!validRecPath(path)) {
    sendErr(400, "Invalid path");
    return;
  }
  File f = SD.open(path, FILE_READ);
  if (!f || f.isDirectory()) {
    sendErr(404, "File not found");
    return;
  }

  uint32_t size = f.size();
  uint32_t start = 0, end = size ? size - 1 : 0;
  bool partial = false;
  String range = web.header("Range");
  if (range.startsWith("bytes=") && size > 0) {
    int dash = range.indexOf('-');
    start = range.substring(6, dash).toInt();
    String e = range.substring(dash + 1);
    if (e.length())
      end = e.toInt();
    if (end >= size)
      end = size - 1;
    if (start > end) {
      f.close();
      web.sendHeader("Content-Range", "bytes */" + String(size));
      web.send(416, "text/plain", "");
      return;
    }
    partial = true;
  }
  uint32_t len = size ? end - start + 1 : 0;

  web.sendHeader("Accept-Ranges", "bytes");
  if (partial)
    web.sendHeader("Content-Range", "bytes " + String(start) + "-" +
                                        String(end) + "/" + String(size));
  if (web.hasArg("dl"))
    web.sendHeader("Content-Disposition", String("attachment; filename=\"") +
                                              baseName(path.c_str()) + "\"");
  web.setContentLength(len);
  web.send(partial ? 206 : 200, "audio/wav", "");

  // Stream manual (bukan streamFile) supaya MQTT & rekaman tetap dilayani
  WiFiClient client = web.client();
  static uint8_t buf[2048];
  f.seek(start);
  uint32_t left = len;
  unsigned long lastService = millis();
  while (left > 0 && client.connected()) {
    size_t n = f.read(buf, left < sizeof(buf) ? left : sizeof(buf));
    if (n == 0 || client.write(buf, n) != n)
      break;
    left -= n;
    if (millis() - lastService >= 100) {
      lastService = millis();
      serviceBackground();
    }
  }
  f.close();
}

void handleCmd() {
  if (!webAuth())
    return;
  String a = web.arg("action");

  if (a == "start") {
    if (getRecState().active)
      return sendErr(409, "Already recording");
    if (!requestStartRecording(web.arg("duration").toInt()))
      return sendErr(500, "Could not start: SD card missing or full");
    return sendOk("Recording started");
  }
  if (a == "stop") {
    requestStopRecording();
    return sendOk("Recording stopped");
  }
  if (a == "upload" || a == "delete") {
    String path = web.arg("path");
    if (!validRecPath(path))
      return sendErr(400, "Invalid path");
    if (a == "upload") {
      if (!enqueueJob(JOB_UPLOAD, path.c_str()))
        return sendErr(503, "Upload queue is full");
      return sendOk(WiFi.status() == WL_CONNECTED
                        ? "Upload queued"
                        : "Queued; uploads when the internet is back");
    }
    if (isBusyFile(path.c_str()))
      return sendErr(409, "File is being recorded or uploaded");
    if (!deleteRecordingFile(path.c_str()))
      return sendErr(500, "Delete failed");
    publishRecordingStatus("file_deleted", path.c_str());
    return sendOk("File deleted");
  }
  if (a == "upload_all") {
    startUploadAll(web.arg("force") == "1");
    return sendOk("Uploading all pending recordings");
  }
  if (a == "delete_uploaded") {
    pendingDeleteUploaded = true;
    return sendOk("Deleting uploaded recordings from the SD card");
  }
  if (a == "restart") {
    sendOk("Restarting...");
    pendingRestart = true;
    return;
  }
  if (a == "reset_config") {
    resetOverrides();
    sendOk("Restarting with the values from config.h...");
    pendingRestart = true;
    return;
  }
  if (a == "time") {
    // Jam dari browser bila perangkat belum mendapat waktu dari NTP
    long epoch = web.arg("epoch").toInt();
    if (!timeSynced() && epoch > 1700000000) {
      struct timeval tv = {(time_t)epoch, 0};
      settimeofday(&tv, nullptr);
      Serial.println("Clock set from browser");
    }
    return sendOk("Clock updated");
  }
  sendErr(400, "Unknown action");
}

void handleConfigGet() {
  if (!webAuth())
    return;
  JsonDocument doc;
  doc["wifi_ssid"] = WIFI_SSID;
  doc["server_host"] = SERVER_HOST;
  doc["server_port"] = SERVER_PORT;
  doc["device_id"] = DEVICE_ID;
  size_t tl = strlen(DEVICE_TOKEN);
  doc["token_hint"] = tl > 4 ? String(DEVICE_TOKEN + tl - 4) : String("");
  doc["auto_upload"] = autoUpload;
  doc["gain"] = micGain;
  doc["ap_ssid"] = apSsid;
  listOverrides(doc["overrides"].to<JsonArray>());
  sendJsonDoc(doc);
}

void handleConfigPost() {
  if (!webAuth())
    return;

  bool wifiChanged = false, idChanged = false;
  String v;

  if (web.hasArg("wifi_ssid")) {
    v = web.arg("wifi_ssid");
    v.trim();
    if (v.length() > 32)
      return sendErr(400, "SSID too long");
    if (v != WIFI_SSID) {
      strlcpy(WIFI_SSID, v.c_str(), sizeof(WIFI_SSID));
      saveOverride("wifi_ssid", WIFI_SSID);
      wifiChanged = true;
    }
  }
  v = web.arg("wifi_pass");
  if (v.length()) {
    if (v.length() > 64)
      return sendErr(400, "WiFi password too long");
    strlcpy(WIFI_PASS, v.c_str(), sizeof(WIFI_PASS));
    saveOverride("wifi_pass", WIFI_PASS);
    wifiChanged = true;
  }

  if (web.hasArg("server_host")) {
    String host = web.arg("server_host");
    host.trim();
    host.toLowerCase();
    int port = web.hasArg("server_port") ? web.arg("server_port").toInt()
                                         : SERVER_PORT;
    if (!validHost(host))
      return sendErr(400, "Server host must be a domain or IP only");
    if (port < 1 || port > 65535)
      return sendErr(400, "Invalid server port");
    if (host != SERVER_HOST || port != SERVER_PORT)
      applyServer(host.c_str(), port);
  }

  v = web.arg("device_id");
  v.trim();
  if (v.length() && v != DEVICE_ID) {
    if (!validDeviceId(v))
      return sendErr(400, "Device ID may only contain letters, digits, - and _");
    saveOverride("device_id", v.c_str());
    idChanged = true; // topik MQTT & nama hotspot ikut berubah -> restart
  }
  v = web.arg("token");
  if (v.length()) {
    if (v.length() >= sizeof(DEVICE_TOKEN))
      return sendErr(400, "Token too long");
    strlcpy(DEVICE_TOKEN, v.c_str(), sizeof(DEVICE_TOKEN));
    saveOverride("token", DEVICE_TOKEN);
  }

  if (web.hasArg("auto_upload")) {
    bool en = web.arg("auto_upload") == "1";
    if (en != autoUpload) {
      autoUpload = en;
      saveAutoUpload(en);
      if (en)
        nextAutoUploadAt = millis();
    }
  }
  if (web.hasArg("gain")) {
    float g = constrain(web.arg("gain").toFloat(), 0.1f, 50.0f);
    if (fabsf(g - micGain) > 0.01f) {
      micGain = g;
      saveGain(g);
    }
  }
  pendingHealth = true;

  if (idChanged) {
    sendOk("Saved. Restarting with the new device ID...");
    pendingRestart = true;
  } else if (wifiChanged) {
    sendOk("Saved. Reconnecting WiFi (the hotspot may blink briefly)...");
    pendingWifiReconnect = true;
  } else {
    sendOk("Settings saved");
  }
}

// ---------------------------------------------------------------- setup

void setupWeb() {
  const char *headers[] = {"Range"};
  web.collectHeaders(headers, 1);

  web.on("/", HTTP_GET, handleRoot);
  web.on("/api/status", HTTP_GET, handleStatus);
  web.on("/api/files", HTTP_GET, handleFiles);
  web.on("/sd/file", HTTP_GET, handleSdFile);
  web.on("/api/config", HTTP_GET, handleConfigGet);
  web.on("/api/config", HTTP_POST, handleConfigPost);
  web.on("/api/cmd", HTTP_POST, handleCmd);
  web.onNotFound(handleNotFound);
  web.begin();

  // Semua domain dijawab dengan IP hotspot (captive portal)
  dns.setErrorReplyCode(DNSReplyCode::NoError);
  dns.start(53, "*", AP_IP);

  Serial.printf("Local dashboard: http://%s (hotspot)\n",
                AP_IP.toString().c_str());
}
