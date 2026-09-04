<?php

use App\Modules\Automation\Http\Controllers\AutomationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app'])->prefix('app/automations')->name('client.automations.')->group(function () {
    Route::get('/', [AutomationController::class, 'index'])->name('index');
    Route::get('/templates', [\App\Modules\Automation\Http\Controllers\AutomationTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates/{key}/install', [\App\Modules\Automation\Http\Controllers\AutomationTemplateController::class, 'install'])->name('templates.install');
    Route::post('/', [AutomationController::class, 'store'])->name('store');
    Route::post('/generate', [AutomationController::class, 'generate'])->name('generate');
    Route::get('/{automation}/edit', [AutomationController::class, 'edit'])->name('edit');
    Route::get('/{automation}/builder', [AutomationController::class, 'edit'])->name('builder');
    Route::put('/{automation}', [AutomationController::class, 'update'])->name('update');
    Route::delete('/{automation}', [AutomationController::class, 'destroy'])->name('destroy');
    Route::get('/{automation}/runs', [AutomationController::class, 'runs'])->name('runs');
    Route::post('/{automation}/activate', [AutomationController::class, 'activate'])->name('activate');
    Route::post('/{automation}/pause', [AutomationController::class, 'pause'])->name('pause');
    Route::post('/{automation}/duplicate', [AutomationController::class, 'duplicate'])->name('duplicate');
    Route::post('/{automation}/test', [AutomationController::class, 'test'])->name('test');
    Route::post('/{automation}/token', [AutomationController::class, 'generateToken'])->name('generate-token');
});
