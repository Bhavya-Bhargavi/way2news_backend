$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$port = 8000
$phpDirectory = Split-Path (Get-Command php).Source
$extensionDirectory = Join-Path $phpDirectory 'ext'
$sqliteExtension = Join-Path $extensionDirectory 'php_sqlite3.dll'
$pdoSqliteExtension = Join-Path $extensionDirectory 'php_pdo_sqlite.dll'

if (-not (Test-Path $sqliteExtension) -or -not (Test-Path $pdoSqliteExtension)) {
    throw 'This local demo needs php_sqlite3.dll and php_pdo_sqlite.dll in PHP ext.'
}

if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
    throw "Port $port is already in use. Stop the existing server before running this script."
}

$env:APP_ENV = 'local'
$env:APP_DEBUG = 'true'
$env:APP_URL = "http://127.0.0.1:$port"
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = Join-Path $projectRoot 'database/database.sqlite'
$env:DB_URL = ''
$env:OTP_TEST_MODE = 'true'
$env:INFINITO_SMS_ENABLED = 'true'
$env:ADMIN_PHONE = '+15550002222'
$env:ADMIN_NAME = 'Local Postman Admin'

$phpArguments = @(
    '-d', "extension=$sqliteExtension",
    '-d', "extension=$pdoSqliteExtension"
)

& php @phpArguments artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& php @phpArguments artisan migrate --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& php @phpArguments artisan db:seed --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Way2News local API: http://127.0.0.1:$port/api"
Write-Host 'OTP test mode is enabled for local requests; no SMS is sent.'
& php @phpArguments -S "127.0.0.1:$port" -t (Join-Path $projectRoot 'public')