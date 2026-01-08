<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto de pago a proveedores</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h2 {
            font-size: 14px;
            margin: 0;
            text-align: center;
            font-weight: normal;
        }

        h3 {
            font-size: 12px;
            margin: 0;
            text-align: center;
            font-weight: normal;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            padding: 6px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-transform: uppercase;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            padding: 8px;
            margin-top: 12px;
            font-weight: 700;
            text-align: right;
        }

        .subtitle {
            margin-top: 2px;
            font-size: 12px;
            text-align: center;
            color: #4b5563;
        }

        .section-title {
            margin-top: 18px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .totals-table th {
            background: #1f2937;
            color: #f9fafb;
        }
    </style>
</head>

<body>
    <h1>GRUPO EMPRESARIAL ADMG</h1>
    <h2>SALDOS PENDIENTES A PAGAR A PROVEEDORES</h2>
    <h3>{{ $descripcionReporte }}</h3>
    <p class="subtitle">Elaborado por: {{ $usuario ?? 'N/D' }}</p>

    @forelse ($empresas as $empresa)
        <div class="section-title">{{ $empresa['conexion_nombre'] }} - {{ $empresa['empresa_nombre'] }}</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40%">Proveedor</th>
                    <th style="width: 30%">Descripción</th>
                    <th style="width: 10%" class="text-center">Área</th>
                    <th style="width: 20%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($empresa['proveedores'] as $proveedor)
                    <tr>
                        <td>
                            {{ $proveedor['nombre'] }}
                            @if (!empty($proveedor['ruc']))
                                <br><span style="font-size:11px; color:#4b5563">RUC: {{ $proveedor['ruc'] }}</span>
                            @endif
                        </td>
                        <td>{{ $proveedor['descripcion'] }}</td>
                        <td class="text-center">{{ $proveedor['area'] }}</td>
                        <td class="text-right">
                            ${{ number_format((float) ($proveedor['subtotal'] ?? 0), 2, '.', ',') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No existen proveedores seleccionados.</td>
                    </tr>
                @endforelse

                <tr>
                    <td colspan="3" class="text-right" style="font-weight:700; background:#e5e7eb">Subtotal empresa
                    </td>
                    <td class="text-right" style="font-weight:700; background:#e5e7eb">
                        ${{ number_format((float) ($empresa['subtotal'] ?? 0), 2, '.', ',') }}
                    </td>
                </tr>
            </tbody>
        </table>
    @empty
        <table>
            <tr>
                <td class="text-center">No existen proveedores seleccionados.</td>
            </tr>
        </table>
    @endforelse

    @if (!empty($resumenEmpresas ?? []))
        <div class="section-title">Resumen de totales por empresa</div>
        <table class="totals-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resumenEmpresas as $resumen)
                    <tr>
                        <td>{{ $resumen['empresa'] }}</td>
                        <td class="text-right">${{ number_format((float) $resumen['total'], 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight:700">Total general</td>
                    <td class="text-right" style="font-weight:700">${{ number_format((float) $total, 2, '.', ',') }}
                    </td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="summary">Total general: ${{ number_format((float) $total, 2, '.', ',') }}</div>
    @endif
</body>

</html>
