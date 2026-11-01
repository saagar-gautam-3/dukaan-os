param(
    [Parameter(Mandatory=$true)][string]$File,
    [Parameter(Mandatory=$true)][string]$TargetDatabase,
    [switch]$Force
)

$protected = @('dukaan_os', $env:DB_DATABASE)
if ($protected -contains $TargetDatabase -and -not $Force) {
    Write-Error "Refusing to restore into protected database '$TargetDatabase'. Use -Force and typed confirmation."
    exit 1
}
if ($protected -contains $TargetDatabase) {
    $confirm = Read-Host "Type RESTORE to confirm overwrite of $TargetDatabase"
    if ($confirm -ne 'RESTORE') { Write-Error "Confirmation failed"; exit 1 }
}

if (-not (Test-Path $File)) { Write-Error "Backup file not found"; exit 1 }

$hostName = $env:DB_HOST
if (-not $hostName) { $hostName = '127.0.0.1' }
$port = $env:DB_PORT
if (-not $port) { $port = 3306 }
$user = $env:DB_USERNAME
$pass = $env:DB_PASSWORD

$tempSql = [System.IO.Path]::GetTempFileName() + '.sql'
$gz = [System.IO.File]::OpenRead($File)
$out = [System.IO.File]::Create($tempSql)
$gzip = New-Object System.IO.Compression.GZipStream($gz, [System.IO.Compression.CompressionMode]::Decompress)
$gzip.CopyTo($out)
$gzip.Close()
$gz.Close()
$out.Close()

$env:MYSQL_PWD = $pass
try {
    & mysql --host=$hostName --port=$port --user=$user -e "source $tempSql" $TargetDatabase
    if ($LASTEXITCODE -ne 0) { throw "restore failed with exit code $LASTEXITCODE" }
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    Remove-Item $tempSql -ErrorAction SilentlyContinue
}

$triggerCount = & mysql --host=$hostName --port=$port --user=$user -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$TargetDatabase';"
Write-Host "Triggers after restore: $triggerCount"