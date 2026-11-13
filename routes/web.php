<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ContactController::class, 'index']);
Route::post('/contacts/confirm', [ContactController::class, 'confirm']);
Route::post('/contacts', [ContactController::class, 'store']);
Route::get('/thanks', function () {
    return view('contact.thanks');
});

Route::middleware('auth')->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::get('/admin/contacts/{contact}', [AdminController::class, 'show']);
    Route::delete('/admin/contacts/{contact}', [AdminController::class, 'destroy']);
    Route::get('/admin/tags/{tag}/edit', [TagController::class, 'edit']);
    Route::put('/admin/tags/{tag}', [TagController::class, 'update']);
    Route::post('/admin/tags', [TagController::class, 'store']);
    Route::delete('/admin/tags/{tag}', [TagController::class, 'destroy']);
    Route::get('/contacts/export', [AdminController::class, 'export']);
});
