<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DAT-04 series — PK codigo Minsal: A, BS, BM, D, P, F.
 * Helpers de negocio: mapeo Excel → serie interna (RN-013/LIM-005) y descripción legible.
 */
class Serie extends Model
{
    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    public function establecimientoSeries(): HasMany
    {
        return $this->hasMany(EstablecimientoSerie::class, 'serie', 'codigo');
    }

    /** Nombre completo tipo legacy: "SERIE BM" */
    public function getNombreMinsalAttribute(): string
    {
        return 'SERIE ' . $this->codigo;
    }

    /**
     * RN-013 — serie interna a partir de la serie del Excel, vía cat_versiones
     * (mapeo vivo del Minsal). Ej.: "SERIE BM" + mes 3/2026 → "BM" (tabla guarda "SERIE BM1"→...).
     */
    public static function buscarPorExcel(string $serieExcel, int $mes, int $anio): ?string
    {
        $mapeo = CatVersion::query()
            ->where('serie_excel', $serieExcel)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->first();

        return $mapeo?->serie ?: null;
    }

    /** Si la serie interna tiene variante con número ("BM1") en cat_versiones. */
    public static function serieInternaConVariante(string $serieInterna, int $mes, int $anio): ?string
    {
        $v = CatVersion::query()
            ->where('serie', $serieInterna)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->first();

        // el campo `serie` de cat_versiones ya trae la variante exacta ("SERIE BM1")
        return $v?->serie ?: null;
    }
}
