<?php

use App\Http\Middleware\EnforceMfa;
use Illuminate\Support\Facades\Route;

// T-022 (provisional hasta FASE-7/PD-PLAN-01): página de retención de enrolamiento.
Route::view('mfa', 'auth.mfa-setup')->name('mfa.setup');

// Guarda el flag del enrolamiento (sin TOTP concreto: stub acepta y prepara secreto)
Route::post('mfa', static function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    abort_unless((bool) $user, 403);

    $user->forceFill(['mfa_habilitado' => $request->boolean('habilitar')])->save();

    return redirect()->intended($user->esInterno() ? 'backoffice' : 'portal');
})->name('mfa.guardar')->middleware([EnforceMfa::class]);
