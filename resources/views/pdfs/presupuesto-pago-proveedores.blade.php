<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto de pago a proveedores</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        h2 { font-size: 16px; margin: 12px 0 6px; }
        h3 { font-size: 14px; margin: 8px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
        .totales { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Presupuesto de pago a proveedores</h1>
    @foreach($grouped as $empresa => $sucursales)
        <h2>Empresa: {{ $empresa }}</h2>
        @foreach($sucursales as $sucursal => $proveedores)
            <h3>Sucursal: {{ $sucursal }}</h3>
            @foreach($proveedores as $proveedor => $detalle)
                <p><strong>Proveedor:</strong> {{ $proveedor }}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th>Monto</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($detalle['facturas'] as $factura)
                            <tr>
                                <td>{{ $factura->numero_factura }}</td>
                                <td>{{ optional($factura->fecha_emision)->format('Y-m-d') }}</td>
                                <td>{{ optional($factura->fecha_vencimiento)->format('Y-m-d') }}</td>
                                <td class="totales">{{ number_format($factura->monto, 2, '.', ',') }}</td>
                                <td class="totales">{{ number_format($factura->saldo, 2, '.', ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="totales">Total proveedor</td>
                            <td class="totales">{{ number_format($detalle['total'], 2, '.', ',') }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endforeach
        @endforeach
    @endforeach
</body>
</html>
