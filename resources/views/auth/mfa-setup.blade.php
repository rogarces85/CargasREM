<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MFA requerido — WebCargaREM</title>
    <style>body { font-family: sans-serif; max-width: 540px; margin: 60px auto; }</style>
</head>
<body>
    <h1>MFA requerido</h1>
    <p>
        Tu cuenta (Admin/Supervisor) requiere autenticación TOTP obligatoria en producción
        (RN-014/CA-SEG-05). El enrolamiento con QR y códigos se construye con la librería
        definida en PD-PLAN-01 antes de FASE-7. Si perdiste tu MFA, pide el reset a otro
        Admin (LIM-022).
    </p>
    <form method="post" action="{{ route('mfa.guardar') }}">
        @csrf
        <input type="hidden" name="habilitar" value="1">
        <button type="submit">Declarar enrolamiento completado (provisional)</button>
    </form>
</body>
</html>
