<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto de pago a proveedores</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        h2 { font-size: 14px; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 6px; border: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .text-right { text-align: right; }
        .summary { background: #fef3c7; border: 1px solid #fcd34d; padding: 8px; margin-top: 12px; font-weight: 700; }
    </style>
</head>
<body>
    <h1>Presupuesto de pago a proveedores</h1>

    @forelse ($proveedores as $proveedor)
        <div style="margin-bottom: 16px;">
            <h2>{{ $proveedor['empresa_nombre'] ?? $proveedor['empresa_codigo'] }} / {{ $proveedor['sucursal_nombre'] ?? $proveedor['sucursal_codigo'] }}</h2>
            <div><strong>Proveedor:</strong> {{ $proveedor['proveedor_nombre'] ?? $proveedor['proveedor_codigo'] }}</div>
            @if(!empty($proveedor['proveedor_ruc']))
                <div><strong>RUC:</strong> {{ $proveedor['proveedor_ruc'] }}</div>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Emisión</th>
                        <th>Vencimiento</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proveedor['facturas'] as $factura)
                        <tr>
                            <td>{{ $factura['numero'] ?? '' }}</td>
                            <td>{{ $factura['fecha_emision'] ?? '' }}</td>
                            <td>{{ $factura['fecha_vencimiento'] ?? '' }}</td>
                            <td class="text-right">${{ number_format((float) ($factura['saldo'] ?? 0), 2, '.', ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="summary">Total proveedor: ${{ number_format((float) ($proveedor['total'] ?? 0), 2, '.', ',') }}</div>
        </div>
    @empty
        <p>No existen proveedores seleccionados.</p>
    @endforelse

    <div class="summary">Total general seleccionado: ${{ number_format((float) $total, 2, '.', ',') }}</div>
</body>
</html>
