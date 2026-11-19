# Diagnóstico de red local sin alterar firewall ni configuración.
$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot
$carpetaWeb = Split-Path -Leaf $raiz
Write-Host 'Mercopan Comercial — direcciones para probar desde otra computadora' -ForegroundColor Cyan

if (-not (Test-Path -LiteralPath (Join-Path $raiz 'public/admin'))) {
    Write-Host 'Aviso: no se encontró public/admin en esta carpeta.' -ForegroundColor Yellow
}

$abierto = Test-NetConnection -ComputerName '127.0.0.1' -Port 80 -InformationLevel Quiet -WarningAction SilentlyContinue
if ($abierto) { Write-Host 'Apache / Puerto 80: hay un servicio escuchando' -ForegroundColor Green }
else { Write-Host 'No se detectó un servicio en el puerto 80; iniciá Apache en XAMPP.' -ForegroundColor Yellow }

$interfaces = @()
try {
    $rutas = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction Stop | Sort-Object RouteMetric
    foreach ($ruta in $rutas) {
        $interfaces += @(Get-NetIPAddress -InterfaceIndex $ruta.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' })
    }
} catch {
    $interfaces = @(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' })
}
$ips = @($interfaces | ForEach-Object IPAddress | Select-Object -Unique)
if ($ips.Count -eq 0) { Write-Host 'No se detectó IP IPv4 activa en la red.' -ForegroundColor Yellow; exit 1 }
foreach ($ip in $ips) {
    Write-Host "`nDirección posible de esta PC: $ip" -ForegroundColor Cyan
    Write-Host "  Administración: http://$ip/$carpetaWeb/public/admin/"
    Write-Host "  Depósito:       http://$ip/$carpetaWeb/public/deposito/"
    Write-Host "  Mostrador:      http://$ip/$carpetaWeb/public/mostrador/"
}
Write-Host "`nAsegurate de que la otra PC esté en el mismo Wi-Fi y que Windows permita Apache solo en la red privada."
Write-Host 'No hace falta abrir el puerto MySQL 3306 a la red.'
