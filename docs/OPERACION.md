# Recuperación de accesos — WebCargaREM (T-024, LIM-021/022)

## Password (LIM-021)

1. **Admin disponible:** desde `Administración → Cuentas internas (Usuarios internos)` editar usuario y fijar nueva contraseña temporal (a rotar en el primer login).
2. **Sin Admin disponible (standby):** en el servidor, `storage/framework` no interviene; usar artisan:

   ```bash
   php artisan tinker --execute='
   $u = \App\Models\User::where("email", "admin@redsaludosorno.cl")->first();
   $u->forceFill(["password" => bcrypt("<NUEVA_TEMPORAL>")])->save();
   '
   ```

3. La password inicial del seeder proviene de `.env → REM_ADMIN_PASSWORD` (nunca en código).

## MFA perdido (LIM-022)

1. **Admin disponible:** reset del MFA del afectado desde `Administración → Usuarios internos`
   (borra `mfa_secret` y `mfa_habilitado=0`; el afectado re-enrola en el próximo login).
2. **Admin sin Admin disponible:** por artisan:

   ```bash
   php artisan tinker --execute='
   \App\Models\User::where("email","<correo>")->first()->forceFill(["mfa_habilitado"=>false,"mfa_secret"=>null])->save();
   '
   ```

3. El middleware `EnforceMfa` retiene la cuenta sin TOTP en producción hasta completar
   el enrolamiento (`/mfa`); en desarrollo/staging el control es no-op (RN-014).

## Bloqueo por intentos (DAT-03)

`users.bloqueado_hasta` / `intentos_fallidos` — el desbloqueo manual sigue el mismo patrón
artisan del paso 2 (T-083 definirá la política final de expiración).
