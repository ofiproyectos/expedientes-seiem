param([string]$Destination)
$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
if (-not $Destination) { $Destination = Join-Path $env:TEMP 'seiem-expedientes-source.tar.gz' }
$packagePath = [System.IO.Path]::GetFullPath($Destination)
if ($packagePath.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'El paquete debe guardarse fuera del proyecto.' }
$sourceEntries = @('app','bootstrap','config','database','resources','public','routes','lang','artisan','composer.json','composer.lock','deploy','README.md','tests','phpunit.xml')
& tar -czf $packagePath --exclude=bootstrap/cache/*.php --exclude=database/*.sqlite* --exclude=public/storage --exclude=__pycache__ -C $projectRoot @sourceEntries
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el paquete.' }
$entries = @(& tar -tzf $packagePath)
if ($entries -match '^vendor/' -or $entries -contains '.env' -or $entries -match '^storage/') { throw 'El paquete contiene archivos privados o dependencias instaladas.' }
Write-Output "Paquete verificado, sin datos ni secretos: $packagePath"
