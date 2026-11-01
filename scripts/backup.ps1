param(
    [string]$Database = $env:DB_DATABASE,
    [string]$User = $env:DB_USERNAME,
    [string]$Password = $env:DB_PASSWORD,
    [string]$Host = ($env:DB_HOST ?? '127.0.0.1'),
    [int]$Port = [int]($env:DB_PORT ?? 3306),
    [string]$OutputDir = (Join-Path $PSScriptRoot '..\storage\backups'),
    [int]$Keep = 7
)

if (-not $Database) { Write-Error "Database is required"; exit 1 }
if (-not $User) { Write-Error "DB username is required"; exit 1 }

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$file = Join-Path $OutputDir "$Database`_$timestamp.sql.gz"

$env:MYSQL_PWD = $Password
try {
    $dump = & mysqldump --single-transaction --routines --triggers --host=$Host --port=$Port --user=$User $Database 2>$null
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed" }
    $dump | gzip > $file
    if ($LASTEXITCODE -ne 0) { throw "gzip failed" }
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

Get-ChildItem $OutputDir -Filter "$Database`_*.sql.gz" | Sort-Object LastWriteTime -Descending | Select-Object -Skip $Keep | Remove-Item -Force

if (Test-Path $file) { Write-Host "Backup written: $file" } else { Write-Error "Backup failed"; exit 1 }