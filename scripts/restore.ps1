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

$env:MYSQL_PWD = $env:DB_PASSWORD
try {
    Get-Content $File | gunzip | mysql --host=$env:DB_HOST --port=$env:DB_PORT --user=$env:DB_USERNAME $TargetDatabase
    if ($LASTEXITCODE -ne 0) { throw "restore failed" }
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

$triggerCount = mysql --host=$env:DB_HOST --port=$env:DB_PORT --user=$env:DB_USERNAME -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$TargetDatabase';"
Write-Host "Triggers after restore: $triggerCount"