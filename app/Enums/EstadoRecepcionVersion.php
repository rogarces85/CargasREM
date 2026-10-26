<?php

namespace App\Enums;

enum EstadoRecepcionVersion: string
{
    case Recibida = 'recibida';
    case Validando = 'validando';
    case Validada = 'validada';
    case ConErrores = 'con_errores';
    case EnRevision = 'en_revision';
    case Aceptada = 'aceptada';
    case Observada = 'observada';
    case Rechazada = 'rechazada';
}
