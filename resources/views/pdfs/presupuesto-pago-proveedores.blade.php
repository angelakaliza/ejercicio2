<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto de pago a proveedores</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        h1 { margin: 0; font-size: 18px; }
        .totales { margin-top: 10px; text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Presupuesto de pago a proveedores</h1>
    <p>Reporte consolidado generado desde el módulo de Solicitudes de Pago y Aprobaciones.</p>

    <table>
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Sucursal</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Emisión</th>
                <th>Vencimiento</th>
                <th>Moneda</th>
                <th>Saldo ML</th>
                <th>Saldo ME</th>
            </tr>
        </thead>
        <tbody>
        @foreach($registros as $registro)
            <tr>
                <td>{{ $registro['empresa'] }}</td>
                <td>{{ $registro['sucursal'] }}</td>
                <td>{{ $registro['proveedor'] }}</td>
                <td>{{ $registro['factura'] }}</td>
                <td>{{ $registro['emision'] }}</td>
                <td>{{ $registro['vencimiento'] }}</td>
                <td>{{ $registro['moneda'] }}</td>
                <td>{{ number_format($registro['saldo'], 2, '.', ',') }}</td>
                <td>{{ number_format($registro['saldo_mext'], 2, '.', ',') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="totales">Total seleccionado: {{ number_format($total, 2, '.', ',') }}</p>
</body>
</html>
