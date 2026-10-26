<?php

namespace App\Auth;

/**
 * T-022 / TEC-15 / RN-014 — Contrato del generador/verificador TOTP.
 * PD-PLAN-01: la implementación concreta (librería) se define antes de FASE-7;
 * el middleware EnforceMfa se escribirá contra esta interfaz.
 */
interface TOTPProvider
{
    /** Secreto base32 para el QR del enrolamiento (Google Authenticator compatible). */
    public function generateSecret(): string;

    /** Verifica un código de 6 dígitos con ventana de ±1 período. */
    public function verify(string $secret, string $code): bool;

    /** URL otpauth:// para el QR. */
    public function qrUrl(string $secret, string $account): string;
}
