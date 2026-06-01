@php
    $colorNivel = match (true) {
        in_array($nivel, ['VENCIDO', 'CRITICA_T1'], true) => 'error',
        $nivel === 'CRITICA_T7'                            => 'error',
        $nivel === 'ALTA_T15'                              => 'warning',
        default                                            => 'primary',
    };

    $titularNivel = match ($nivel) {
        'VENCIDO'    => 'Tu certificado digital SII esta VENCIDO',
        'CRITICA_T1' => 'Tu certificado SII vence MANANA',
        'CRITICA_T7' => 'Tu certificado SII vence muy pronto',
        'ALTA_T15'   => 'Tu certificado SII vence en menos de 15 dias',
        'MEDIA_T30'  => 'Aviso: tu certificado SII vence en menos de 30 dias',
        'BAJA_T60'   => 'Recordatorio: tu certificado SII vence en menos de 60 dias',
        default      => 'Aviso de vencimiento del certificado SII',
    };

    $fechaVenceFormato = $cert->valido_hasta ? $cert->valido_hasta->format('d-m-Y') : '—';
@endphp

@component('mail::message')
# {{ $titularNivel }}

Hola{{ $cert->subject_common_name ? ', ' . $cert->subject_common_name : '' }},

@if ($dias < 0)
El certificado digital de tu empresa **vencio hace {{ abs($dias) }} dia(s)**. Sin un certificado vigente,
**no podras emitir Documentos Tributarios Electronicos (DTE)** al SII.
@elseif ($dias === 0)
El certificado digital de tu empresa **vence HOY**. Renuevalo cuanto antes para evitar la interrupcion
de la emision de DTE.
@else
El certificado digital de tu empresa vence en **{{ $dias }} dia(s)**.
@endif

@component('mail::table')
| Campo              | Valor                                                       |
|:-------------------|:------------------------------------------------------------|
| Titular            | {{ $cert->subject_common_name ?? '—' }}                     |
| RUT del titular    | {{ $cert->subject_rut ?? '—' }}                             |
| Entidad emisora    | {{ $cert->issuer_common_name ?? '—' }}                      |
| Fecha de venc.     | {{ $fechaVenceFormato }}                                    |
| Dias restantes     | {{ $dias }}                                                 |
| Nivel de alerta    | {{ $nivel }}                                                |
@endcomponent

@component('mail::button', ['url' => rtrim(config('app.url'), '/').'/sii/certificado', 'color' => $colorNivel])
Renovar Certificado
@endcomponent

@if ($dias < 0)
> **IMPORTANTE:** mientras el certificado este vencido, el modulo SII no podra firmar nuevos DTE.
> Todas las emisiones quedaran en estado *folio reservado* hasta que cargues un certificado vigente.
@endif

Gracias,
{{ config('app.name') }}
@endcomponent
