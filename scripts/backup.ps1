param(
    [string]$Database = $env:DB_DATABASE,
    [string]$User = $env:DB_USERNAME,
    [string]$Password = $env:DB_PASSWORD,
    [string]$DbHost = '127.0.0.1',
    [int]$Port = 3306,
    [string]$OutputDir = (Join-Path $PSScriptRoot '..\storage\backups'),
    [int]$Keep = 7
)

if (-not $Database) { Write-Error "Database is required"; exit 1 }
if (-not $User) { Write-Error "DB username is required"; exit 1 }
if ($DbHost -eq '' -and $env:DB_HOST) { $DbHost = $env:DB_HOST }
if ($Port -eq 0 -and $env:DB_PORT) { $Port = [int]$env:DB_PORT }
if (-not $Password -and $env:DB_PASSWORD) { $Password = $env:DB_PASSWORD }

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$sqlFile = Join-Path $OutputDir "$Database`_$timestamp.sql"
$gzFile = "$sqlFile.gz"

$env:MYSQL_PWD = $Password
try {
    & mysqldump --single-transaction --routines --triggers --host=$DbHost --port=$Port --user=$User --result-file=$sqlFile $Database
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

# compress with GZipStream
$in = [System.IO.File]::OpenRead($sqlFile)
$out = [System.IO.File]::Create($gzFile)
$gzip = New-Object System.IO.Compression.GZipStream($out, [System.IO.Compression.CompressionMode]::Compress)
$in.CopyTo($gzip)
$gzip.Close()
$in.Close()
$out.Close()
Remove-Item $sqlFile -Force

Get-ChildItem $OutputDir -Filter "$Database`_*.sql.gz" | Sort-Object LastWriteTime -Descending | Select-Object -Skip $Keep | Remove-Item -Force

if (Test-Path $gzFile) { Write-Host "Backup written: $gzFile" } else { Write-Error "Backup failed"; exit 1 }