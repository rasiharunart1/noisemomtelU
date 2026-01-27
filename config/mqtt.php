<?php

return [
    'host' => env('MQTT_HOST'),
    'port' => env('MQTT_PORT'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'topic_pattern' => env('MQTT_TOPIC_PATTERN', 'audio/+/data'),
];
