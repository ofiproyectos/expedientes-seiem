param([string]$Destination, [string]$Ref = 'HEAD')
$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
if (-not $Destination) { $Destination = Join-Path $env:TEMP 'seiem-expedientes-source.tar.gz' }
$packagePath = [System.IO.Path]::GetFullPath($Destination)
if ($packagePath.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'El paquete debe guardarse fuera del proyecto.' }
$dirty = & git -C $projectRoot status --porcelain --untracked-files=normal
if ($LASTEXITCODE -ne 0 -or $dirty) { throw 'Confirma los cambios antes de empaquetar una version identificada.' }
$revision = & git -C $projectRoot rev-parse --verify "$Ref^{commit}"
if ($LASTEXITCODE -ne 0) { throw 'La referencia no identifica un commit.' }
& git -C $projectRoot archive --format=tar.gz "--output=$packagePath" $revision
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el paquete.' }
$entries = @(& tar -tzf $packagePath)
if ($LASTEXITCODE -ne 0) { throw 'No se pudo inspeccionar el paquete.' }
$privateEntries = $entries | Where-Object {
    $_ -match '(^|/)(vendor|storage|composer-cache)/|\.(sqlite|sql)($|[.-])|^expedientes$' -or
    ($_ -match '(^|/)\.env($|\.)' -and $_ -notmatch '(^|/)\.env\.example$')
}
if ($privateEntries) { throw 'El paquete contiene archivos privados.' }
$hash = (Get-FileHash -LiteralPath $packagePath -Algorithm SHA256).Hash.ToLowerInvariant()
@{ revision = $revision; sha256 = $hash; file = [System.IO.Path]::GetFileName($packagePath) } | ConvertTo-Json | Set-Content -Encoding UTF8 -LiteralPath ($packagePath + '.manifest.json')
Write-Output "Version: $revision"
Write-Output "Paquete: $packagePath"
Write-Output "SHA256: $hash"
