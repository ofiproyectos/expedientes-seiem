<?php

use App\Http\Controllers\AccessGrantController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/expedientes');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::middleware(['auth', EnsureActiveUser::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::resource('expedientes', ExpedienteController::class)->only('index', 'create', 'store', 'show', 'update');
    Route::post('/expedientes/{expediente}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'replace'])->name('documents.replace');
    Route::post('/documents/{document}/retire', [DocumentController::class, 'retire'])->name('documents.retire');
    Route::post('/versions/{version}/restore', [DocumentController::class, 'restore'])->name('versions.restore');
    Route::get('/versions/{version}/visor', [DocumentController::class, 'viewer'])->name('documents.viewer');
    Route::get('/files/{version}', [DocumentController::class, 'file'])->name('documents.file');

    Route::post('/expedientes/{expediente}/grants', [AccessGrantController::class, 'store'])->name('grants.store');
    Route::delete('/grants/{grant}', [AccessGrantController::class, 'revoke'])->name('grants.revoke');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'save'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'save'])->name('users.update');
    Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
});
