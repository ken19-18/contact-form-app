<?php

use App\Http\Controllers\Api\V1\ContactController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // API01: お問合せ一覧
    Route::get('/contacts', [ContactController::class, 'index']);

    // API02: お問合せ詳細
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);

    // API03: お問合せ登録
    Route::post('/contacts', [ContactController::class, 'store']);

    // API04: お問合せ更新
    Route::put('/contacts/{contact}', [ContactController::class, 'update']);

    // API05: お問合せ削除
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
});
