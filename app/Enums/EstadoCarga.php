<?php

namespace App\Enums;

enum EstadoCarga: string
{
    case Pendiente = 'pendiente';
    case Validando = 'validando';
    case Validada = 'validada';
    case ConErrores = 'con_errores';
    case Guardado = 'guardado';
    case Rechazado = 'rechazado';
}
