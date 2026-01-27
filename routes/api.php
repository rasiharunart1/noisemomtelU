<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AudioUploadController;

Route::post('/upload-audio', [AudioUploadController::class, 'upload']);
