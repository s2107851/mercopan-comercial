# Mercopan COMERCIAL: crea repo privado y publica los archivos de la VERSION REAL.
# Ejecutar desde PowerShell en la carpeta que contiene app/, public/ y database/.
# Requiere Git y GitHub CLI (gh) autenticado.
$ErrorActionPreference = 'Stop'
$repoCompleto = 's2107851/mercopan-comercial'
$carpeta = $PSScriptRoot
Set-Location -LiteralPath $carpeta

function Fallar([string]$mensaje) {
    Write-Host ('ERROR: ' + $mensaje) -ForegroundColor Red
    exit 1
}
function Requerir([string]$comando, [string]$ayuda) {
    if (-not (Get-Command $comando -ErrorAction SilentlyContinue)) { Fallar "$comando no está instalado. $ayuda" }
}
Write-Host 'MERCOPAN COMERCIAL — CREAR REPOSITORIO PRIVADO' -ForegroundColor Cyan

if (-not ((Test-Path -LiteralPath './app' -PathType Container) -and
           (Test-Path -LiteralPath './public' -PathType Container) -and
           (Test-Path -LiteralPath './database/001_schema.sql' -PathType Leaf) -and
           (Test-Path -LiteralPath './public/admin' -PathType Container) -and
           (Test-Path -LiteralPath './public/deposito' -PathType Container))) {
    Fallar 'Esta carpeta no parece ser Mercopan COMERCIAL. Debe incluir app/, public/admin/, public/deposito/ y database/001_schema.sql. No uses Mercopan-utu.'
}
Requerir 'git' 'Descarga: https://git-scm.com/downloads/win'
Requerir 'gh' 'Descarga: https://cli.github.com/'
& gh auth status 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) { Fallar 'Primero iniciá sesión desde esta consola con: gh auth login' }

# Protección: si ya tiene remoto, no modificarlo ni crear el repo en otro lugar.
if (Test-Path -LiteralPath './.git') {
    $remoto = (& git remote) -join ','
    if ($remoto.Trim().Length -gt 0) { Fallar "Esta carpeta ya tiene un remoto Git ($remoto). Revisalo antes de continuar; no sobrescribimos repos existentes." }
} else {
    & git init -b main
    if ($LASTEXITCODE -ne 0) { Fallar 'No se pudo iniciar Git.' }
}

& git add --all
if ($LASTEXITCODE -ne 0) { Fallar 'No se pudieron preparar los archivos.' }

# Defensa adicional: bloquear rutas privadas si un .gitignore previo no las cubre.
$lista = (& git diff --cached --name-only) -join "`n"
if ($LASTEXITCODE -ne 0) { Fallar 'No se pudo verificar qué archivos se subirían.' }
if ($lista -match '(?im)(^|/)(config\.local\.php|\.env(?:\.[^/]+)?|storage/facturas/|uploads/|node_modules/|vendor/)') {
    & git reset --quiet 2>$null
    Fallar 'Se detectaron archivos privados o generados listos para subir. Corregí el .gitignore y volvé a intentarlo.'
}

# No crea ningún repo si los archivos no son suficientes para la versión real.
if ($lista.Trim().Length -eq 0) {
    $hayCommit = (& git rev-parse --verify HEAD 2>$null)
    if (-not $hayCommit) { Fallar 'No hay archivos para el primer commit.' }
} else {
    $nombreGit = & git config user.name
    $emailGit = & git config user.email
    if ([string]::IsNullOrWhiteSpace($nombreGit) -or [string]::IsNullOrWhiteSpace($emailGit)) {
        Fallar 'Configurá primero tu nombre y correo para Git: git config --global user.name "Tu nombre" ; git config --global user.email "tu@correo"'
    }
    & git commit -m 'Inicial: Mercopan Comercial y guias de colaboracion'
    if ($LASTEXITCODE -ne 0) { Fallar 'No se pudo crear el primer commit.' }
}

& gh repo view $repoCompleto 1>$null 2>$null
if ($LASTEXITCODE -eq 0) {
    Fallar "El repositorio $repoCompleto ya existe. No vamos a crear otro ni modificarlo a ciegas."
}

Write-Host 'Creando repositorio PRIVADO y subiendo el proyecto...' -ForegroundColor Yellow
& gh repo create $repoCompleto --private --source . --remote origin --push
if ($LASTEXITCODE -ne 0) { Fallar 'GitHub no terminó el proceso. Revisá en GitHub si el repo se creó antes de volver a ejecutar el script.' }
Write-Host ''
Write-Host "LISTO: https://github.com/$repoCompleto" -ForegroundColor Green
Write-Host 'Para invitar a tu compañero: Settings > Collaborators > Add people.'
