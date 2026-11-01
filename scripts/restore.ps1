param(
    [Parameter(Mandatory=$true)][string]$File,
    [Parameter(Mandatory=$true)][string]$TargetDatabase,
    [string]$User = '',
    [AllowEmptyString()][string]$Password = '',
    [string]$DbHost = '',
    [int]$Port = 0,
    [string]$MysqlBin = '',
    [switch]$Force
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

if (-not $User) { $User = Get-EnvValue 'DB_USERNAME' }
if (-not $Password) { $Password = Get-EnvValue 'DB_PASSWORD' }
if (-not $DbHost) { $DbHost = Get-EnvValue 'DB_HOST' }
if (-not $DbHost) { $DbHost = '127.0.0.1' }
if ($Port -eq 0) {
    $portValue = Get-EnvValue 'DB_PORT'
    if ($portValue) { $Port = [int]$portValue } else { $Port = 3306 }
}
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

$mysql = Find-Binary 'mysql' $MysqlBin

$protected = @('dukaan_os', (Get-EnvValue 'DB_DATABASE'))
if ($protected -contains $TargetDatabase -and -not $Force) {
    Write-Error "Refusing to restore into protected database '$TargetDatabase'. Use -Force and typed confirmation."
    exit 1
}
if ($protected -contains $TargetDatabase) {
    $confirm = Read-Host "Type RESTORE to confirm overwrite of $TargetDatabase"
    if ($confirm -ne 'RESTORE') { Write-Error "Confirmation failed"; exit 1 }
}

if (-not (Test-Path $File)) { Write-Error "Backup file not found: $File"; exit 1 }

$tempSql = [System.IO.Path]::GetTempFileName() + '.sql'
$env:MYSQL_PWD = $Password
try {
    $gz = [System.IO.File]::OpenRead($File)
    $out = [System.IO.File]::Create($tempSql)
    $gzip = New-Object System.IO.Compression.GZipStream($gz, [System.IO.Compression.CompressionMode]::Decompress)
    try {
        $gzip.CopyTo($out)
    } finally {
        $gzip.Close()
        $gz.Close()
        $out.Close()
    }
    if (-not (Test-Path $tempSql) -or (Get-Item $tempSql).Length -eq 0) {
        throw 'Decompression produced an empty SQL file'
    }

    $command = "`"$mysql`" --host=$DbHost --port=$Port --user=$User $TargetDatabase < `"$tempSql`""
    cmd /c $command
    if ($LASTEXITCODE -ne 0) { throw "mysql restore failed with exit code $LASTEXITCODE" }

    $tableCount = & $mysql --host=$DbHost --port=$Port --user=$User -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TargetDatabase';"
    if ($LASTEXITCODE -ne 0) { throw "table count query failed with exit code $LASTEXITCODE" }
    $triggerCount = & $mysql --host=$DbHost --port=$Port --user=$User -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$TargetDatabase';"
    if ($LASTEXITCODE -ne 0) { throw "trigger count query failed with exit code $LASTEXITCODE" }

    if ([int]$tableCount -eq 0) { throw "Restore produced 0 tables" }

    Write-Host "Restore OK: $tableCount tables, $triggerCount triggers"
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    if (Test-Path $tempSql) { Remove-Item $tempSql -Force -ErrorAction SilentlyContinue }
}