<?php

namespace App\Domains\Rrhh\DataTransfer;

readonly class LreLineaData
{
    public function __construct(
        // Identificación trabajador (11xx)
        public string   $rutTrabajador,            // cod 1101
        public string   $fechaInicioContrato,      // cod 1102 dd/mm/YYYY
        public ?string  $fechaTerminoContrato,     // cod 1103 nullable
        public ?int     $causalTermino,            // cod 1104 nullable
        public ?int     $codigoRegion,             // cod 1105
        public ?string  $codigoComuna,             // cod 1106
        public ?int     $codigoJornada,            // cod 1107
        public bool     $pensionadoInvalidez,      // cod 1108
        public bool     $pensionadoVejez,          // cod 1109
        public ?int     $codigoCcaf,               // cod 1110
        public int      $numCargasFamiliares,      // cod 1111
        public int      $numCargasMaternales,      // cod 1112
        public int      $numCargasInvalidez,       // cod 1113
        public int      $tramoCargasFamiliares,    // cod 1114 (1-3)
        public int      $diasTrabajados,           // cod 1115
        public int      $diasLicenciaMedica,       // cod 1116
        public int      $diasVacaciones,           // cod 1117
        public string   $codigoAfp,                // cod 1141 (código institución)
        public string   $codigoSalud,              // cod 1143 (FONASA=07 o código ISAPRE)
        public string   $codigoAfc,                // cod 1151 (AFC según tipo contrato)
        public ?int     $codigoMutual,             // cod 1152
        // Haberes imponibles tributables (21xx)
        public int      $sueldo,                   // cod 2101
        public int      $sobresueldo,              // cod 2102
        public int      $gratificacion,            // cod 2106
        public int      $otrosHaberesImponibles,   // cod 2111 (suma de bonos custom)
        // Haberes no imponibles (23xx)
        public int      $colacion,                 // cod 2301
        public int      $movilizacion,             // cod 2302
        public int      $asignacionFamiliar,       // cod 2311
        public int      $otrosNoImponibles,        // suma resto
        // Descuentos (31xx)
        public int      $cotizacionAfp,            // cod 3141 (cotización + comisión)
        public int      $cotizacionSalud,          // cod 3143
        public int      $cotizacionSaludVoluntaria,// cod 3144
        public int      $cotizacionAfc,            // cod 3151
        public int      $impuestoRetenido,         // cod 3161
        public int      $otrosDescuentos,          // cod 3183 (suma descuentos voluntarios)
        // Aportes empleador (41xx)
        public int      $aporteAfcEmpleador,       // cod 4151
        public int      $aporteMutual,             // cod 4152
        public int      $aporteSis,                // cod 4155
        // Totales (5xxx)
        public int      $totalHaberes,             // cod 5201
        public int      $totalHaberesImponibles,   // cod 5210
        public int      $totalHaberesNoImponibles, // cod 5230
        public int      $totalDescuentos,          // cod 5301
        public int      $totalDescuentosLegales,   // cod 5341
        public int      $totalAportesEmpleador,    // cod 5410
        public int      $liquidoPagar,             // cod 5501
    ) {}
}
