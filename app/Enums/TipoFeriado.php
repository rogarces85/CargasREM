<?php

namespace App\Enums;

enum TipoFeriado: string
{
    case Nacional = 'nacional';
    case RegionalLosLagos = 'regional_los_lagos';
    case Otro = 'otro';
}
