<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto de pago a proveedores</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        h2 { font-size: 14px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        .total { text-align: right; font-weight: 700; margin-top: 12px; padding: 10px; background: #fef3c7; border: 1px solid #fcd34d; }
        .muted { color: #6b7280; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Presupuesto de pago a proveedores</h1>
    @php
        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;
        $desdeTexto = $desde ? \Carbon\Carbon::parse($desde)->format('Y-m-d') : 'N/D';
        $hastaTexto = $hasta ? \Carbon\Carbon::parse($hasta)->format('Y-m-d') : 'N/D';
    @endphp
    <div class="muted">Rango de fechas: {{ $desdeTexto }} — {{ $hastaTexto }}</div>

    @foreach($agrupado as $empresa)
        <h2>{{ $empresa['nombre'] ?? $empresa['codigo'] }}</h2>
        @foreach($empresa['sucursales'] as $sucursal)
            <strong>Sucursal:</strong> {{ $sucursal['nombre'] ?? $sucursal['codigo'] }}
            @foreach($sucursal['proveedores'] as $proveedor)
                <table>
                    <thead>
                        <tr>
                            <th colspan="4">Proveedor: {{ $proveedor['nombre'] ?? $proveedor['codigo'] }} @if(!empty($proveedor['ruc'])) ({{ $proveedor['ruc'] }}) @endif</th>
                        </tr>
                        <tr>
                            <th>Factura</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th style="text-align: right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proveedor['facturas'] as $factura)
                            <tr>
                                <td>{{ $factura['numero'] }}</td>
                                <td>{{ $factura['fecha_emision'] }}</td>
                                <td>{{ $factura['fecha_vencimiento'] }}</td>
                                <td style="text-align: right">${{ number_format($factura['saldo'] ?? 0, 2, '.', ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align: right">Subtotal proveedor</th>
                            <th style="text-align: right">${{ number_format($proveedor['total'] ?? 0, 2, '.', ',') }}</th>
                        </tr>
                    </tfoot>
                </table>
            @endforeach
        @endforeach
    @endforeach

    <div class="total">Total seleccionado: ${{ number_format($total ?? 0, 2, '.', ',') }}</div>
</body>
</html>
