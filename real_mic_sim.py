import os
import time
import json
import numpy as np
import pyaudio
import paho.mqtt.client as mqtt
from dotenv import load_dotenv

# Load .env for MQTT credentials
load_dotenv()

# MQTT Configuration
MQTT_HOST = os.getenv('MQTT_HOST')
MQTT_PORT = int(os.getenv('MQTT_PORT', 8883))
MQTT_USER = os.getenv('MQTT_USERNAME')
MQTT_PASS = os.getenv('MQTT_PASSWORD')

# Audio Configuration
CHUNK = 1024 * 2             # Samples per frame
FORMAT = pyaudio.paInt16     # Audio format
CHANNELS = 1                # Mono
RATE = 44100                # Sample rate (Hz)
BINS = 64                   # Target spectrum bins

print("----------------------------------------")
print(" Noisemon Real Mic Simulator (Python)")
print("----------------------------------------")

device_id = input("Enter Device ID (e.g., ESP32-XXXX): ")
token = input("Enter Device Token: ")

if not device_id or not token:
    print("Device ID and Token are required!")
    exit()

# Initialize PyAudio
p = pyaudio.PyAudio()
stream = p.open(
    format=FORMAT,
    channels=CHANNELS,
    rate=RATE,
    input=True,
    frames_per_buffer=CHUNK
)

# MQTT Setup
client = mqtt.Client(client_id=f"python_mic_{device_id}")
client.username_pw_set(MQTT_USER, MQTT_PASS)
client.tls_set() # Enable TLS for HiveMQ

def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to MQTT Broker!")
        client.publish(f"audio/{device_id}/status", json.dumps({"status": "online", "device_id": device_id}), qos=1, retain=True)
    else:
        print(f"Failed to connect, return code {rc}")

client.on_connect = on_connect

print(f"Connecting to {MQTT_HOST}...")
client.connect(MQTT_HOST, MQTT_PORT)
client.loop_start()

print("\nListening to Microphone... (Press Ctrl+C to stop)")

try:
    while True:
        # Read audio data
        data = stream.read(CHUNK, exception_on_overflow=False)
        samples = np.frombuffer(data, dtype=np.int16).astype(np.float64)

        # Calculate RMS
        rms = np.sqrt(np.mean(samples**2)) if len(samples) > 0 else 0
        
        # Calculate Peak Amplitude
        peak = np.max(np.abs(samples)) if len(samples) > 0 else 0

        # Perform FFT
        fft_data = np.abs(np.fft.rfft(samples))
        
        # Normalize and group into 64 bins
        # We take the first half of FFT (up to Nyquist)
        # and average them into 64 bins
        bin_size = len(fft_data) // BINS
        spectrum = []
        for i in range(BINS):
            start = i * bin_size
            end = (i + 1) * bin_size
            avg_mag = np.mean(fft_data[start:end]) if end > start else 0
            # Scale for visualization (0-100)
            scaled = min(100, int(avg_mag / 2000)) 
            spectrum.append(scaled)

        # Build payload
        payload = {
            "device_id": device_id,
            "token": token,
            "audio": {
                "rms": round(float(rms/1000), 3),
                "peak_amplitude": round(float(peak/1000), 2),
                "noise_floor": 2.5,
                "gain": 1.0
            },
            "fft": {
                "peak_frequency": int(np.argmax(fft_data) * (RATE / CHUNK)),
                "peak_magnitude": round(float(np.max(fft_data)/1000), 1),
                "total_energy": int(np.sum(fft_data)/10000),
                "band_energy": {
                    "low": int(np.sum(spectrum[:20])),
                    "mid": int(np.sum(spectrum[20:40])),
                    "high": int(np.sum(spectrum[40:]))
                },
                "spectral_centroid": 1500, # Placeholder logic
                "zcr": 0.05,
                "spectrum": spectrum
            }
        }

        # Publish
        client.publish(f"audio/{device_id}/data", json.dumps(payload))
        
        # small delay to prevent saturating MQTT
        time.sleep(0.2)

except KeyboardInterrupt:
    print("\nStopping...")
finally:
    client.publish(f"audio/{device_id}/status", json.dumps({"status": "offline", "device_id": device_id}), qos=1, retain=True)
    client.loop_stop()
    stream.stop_stream()
    stream.close()
    p.terminate()
