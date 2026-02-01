<?php

use App\Http\Controllers\FormController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/f/{form:slug}', [PublicFormController::class, 'show'])->name('forms.public.show');
Route::post('/f/{form:slug}', [PublicFormController::class, 'submit'])->name('forms.public.submit');

Route::get('/dashboard', [FormController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Form routes
    Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
    Route::get('/forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
    Route::get('/forms/{form}/responses', [FormController::class, 'responses'])->name('forms.responses');
    Route::get('/forms/{form}/responses/data', [FormController::class, 'responsesData'])->name('forms.responses.data');
    Route::get('/forms/{form}/responses/export/summary', [FormController::class, 'exportSummary'])->name('forms.responses.export.summary');
    Route::get('/forms/{form}/responses/export/individual', [FormController::class, 'exportIndividual'])->name('forms.responses.export.individual');
    Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
    Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
    Route::delete('/forms/{form}/answer-templates/{template}', [FormController::class, 'destroyAnswerTemplate'])->name('forms.answer-templates.destroy');
    Route::delete('/forms/{form}/result-rules/{rule}', [FormController::class, 'destroyResultRule'])->name('forms.result-rules.destroy');
    Route::post('/forms/{form}/rules', [FormController::class, 'updateRules'])->name('forms.rules.store');
    Route::delete('/forms/{form}/setting-results/{ruleGroupId}', [FormController::class, 'destroySettingResultsByGroup'])->name('forms.setting-results.destroy-group');
});

require __DIR__.'/auth.php';
