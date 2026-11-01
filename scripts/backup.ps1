param(
    [string]$Database = '',
    [string]$User = '',
    [AllowEmptyString()][string]$Password = '',
    [string]$DbHost = '',
    [int]$Port = 0,
    [string]$MysqlBin = '',
    [string]$OutputDir = '',
    [int]$Keep = 7
)

$ErrorActionPreference = 'Stop'

function Get-EnvValue {
    param([string]$Key)
    $envFile = Join-Path $PSScriptRoot '..\.env'
    if (Test-Path $envFile) {
        foreach ($line in Get-Content $envFile) {
            if ($line -match "^\s*$Key\s*=\s*(.*)$") {
                $value = $matches[1].Trim()
                if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
                return $value
            }
        }
    }
    return $null
}

if (-not $Database) { $Database = Get-EnvValue 'DB_DATABASE' }
if (-not $User) { $User = Get-EnvValue 'DB_USERNAME' }
if (-not $Password) { $Password = Get-EnvValue 'DB_PASSWORD' }
if (-not $DbHost) { $DbHost = Get-EnvValue 'DB_HOST' }
if (-not $DbHost) { $DbHost = '127.0.0.1' }
if ($Port -eq 0) {
    $portValue = Get-EnvValue 'DB_PORT'
    if ($portValue) { $Port = [int]$portValue } else { $Port = 3306 }
}
if (-not $OutputDir) { $OutputDir = Join-Path $PSScriptRoot '..\storage\backups' }
if (-not $MysqlBin -and $env:MYSQL_BIN) { $MysqlBin = $env:MYSQL_BIN }

function Find-Binary {
    param([string]$Name, [string]$Explicit)
    if ($Explicit) {
        if (Test-Path $Explicit) { return (Resolve-Path $Explicit).Path }
        throw "Specified $Name binary not found: $Explicit"
    }
    $command = Get-Command "$Name.exe" -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    $roots = @(
        'C:\laragon\bin\mysql',
        'C:\xampp\mysql',
        'C:\Program Files\MySQL',
        'C:\Program Files\MariaDB'
    )
    foreach ($root in $roots) {
        if (Test-Path $root) {
            $found = Get-ChildItem $root -Recurse -Filter "$Name.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($found) { return $found.FullName }
        }
    }
    throw "Unable to locate $Name.exe. Searched PATH and: $($roots -join ', ')"
}

$mysqldump = Find-Binary 'mysqldump' $MysqlBin

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$sqlFile = Join-Path $OutputDir "$Database`_$timestamp.sql"
$gzFile = "$sqlFile.gz"

$env:MYSQL_PWD = $Password
try {
    & $mysqldump --single-transaction --routines --triggers --host=$DbHost --port=$Port --user=$User --result-file=$sqlFile $Database
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }
    if (-not (Test-Path $sqlFile) -or (Get-Item $sqlFile).Length -eq 0) {
        throw 'mysqldump produced an empty output file'
    }
    $in = [System.IO.File]::OpenRead($sqlFile)
    $out = [System.IO.File]::Create($gzFile)
    $gzip = New-Object System.IO.Compression.GZipStream($out, [System.IO.Compression.CompressionMode]::Compress)
    try {
        $in.CopyTo($gzip)
    } finally {
        $gzip.Close()
        $in.Close()
        $out.Close()
    }
    if (-not (Test-Path $gzFile) -or (Get-Item $gzFile).Length -eq 0) {
        throw 'gzip compression produced an empty output file'
    }
    Remove-Item $sqlFile -Force
} catch {
    if (Test-Path $sqlFile) { Remove-Item $sqlFile -Force -ErrorAction SilentlyContinue }
    if (Test-Path $gzFile) { Remove-Item $gzFile -Force -ErrorAction SilentlyContinue }
    throw
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

Get-ChildItem $OutputDir -Filter "$Database`_*.sql.gz" | Sort-Object LastWriteTime -Descending | Select-Object -Skip $Keep | Remove-Item -Force

if (Test-Path $gzFile) { Write-Host "Backup written: $gzFile" } else { Write-Error 'Backup failed'; exit 1 }