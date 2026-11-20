$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$htdocs = Split-Path -Parent $root
try {
    if ((Split-Path -Leaf $htdocs) -ne 'htdocs') { throw 'Coloca primero la carpeta del proyecto directamente dentro de htdocs de XAMPP.' }
    $xampp = Split-Path -Parent $htdocs
    $php = Join-Path $xampp 'php\php.exe'
    if (!(Test-Path $php)) { throw 'No se encontro PHP en la instalacion de XAMPP.' }
    $ini = Join-Path $xampp 'mysql\bin\my.ini'
    $env:MERCOPAN_PORT = '3306'
    if (Test-Path $ini) {
        $section = ''
        foreach ($line in Get-Content -LiteralPath $ini) {
            if ($line -match '^\s*\[([^\]]+)\]') { $section = $Matches[1] }
            if ($section -eq 'mysqld' -and $line -match '^\s*port\s*=\s*(\d+)') { $env:MERCOPAN_PORT = $Matches[1] }
        }
    }
    if (!(Test-Path (Join-Path $root 'app\config.local.php'))) {
        Write-Host 'Inicia Apache y MySQL en XAMPP antes de continuar.'
        $sql = Read-Host 'Clave root de MySQL (Enter si tu XAMPP usa clave vacia)' -AsSecureString
        $admin = Read-Host 'Elegi una clave para Administracion de Mercopan (12 caracteres o mas)' -AsSecureString
        $confirm = Read-Host 'Repeti la clave de Mercopan' -AsSecureString
        $env:MERCOPAN_ADMIN = [Net.NetworkCredential]::new('', $admin).Password
        if ($env:MERCOPAN_ADMIN -cne [Net.NetworkCredential]::new('', $confirm).Password) { throw 'Las claves no coinciden.' }
        $env:MERCOPAN_SQL = [Net.NetworkCredential]::new('', $sql).Password
    }
    & $php (Join-Path $root 'app\instalar.php')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    $folder = [uri]::EscapeDataString((Split-Path -Leaf $root))
    Write-Host "Administracion en esta PC: http://localhost/$folder/public/admin/ (Apache en puerto 80)"
    & (Join-Path $PSScriptRoot 'verificar-red.ps1')
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
} finally {
    Remove-Item Env:MERCOPAN_SQL, Env:MERCOPAN_ADMIN -ErrorAction SilentlyContinue
}
