param(
	[string]$Version = '1.0.0'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$parent = Split-Path -Parent $root
$dist = Join-Path $root 'dist'
$stageRoot = Join-Path $env:TEMP ('mrn-mailora-' + [guid]::NewGuid().ToString('N'))
$stage = Join-Path $stageRoot 'mrn-mailora'
$zip = Join-Path $dist "mrn-mailora-$Version.zip"

New-Item -ItemType Directory -Force -Path $stage | Out-Null
New-Item -ItemType Directory -Force -Path $dist | Out-Null

$exclude = @('.git', '.github', '.gitignore', 'dist', 'tests', 'tools', 'vendor', 'composer.json', 'composer.lock', 'phpcs.xml.dist', 'README.md')
Get-ChildItem -LiteralPath $root -Force | Where-Object { $_.Name -notin $exclude } | ForEach-Object {
	Copy-Item -LiteralPath $_.FullName -Destination $stage -Recurse -Force
}
$sourceLogo = Join-Path $stage 'assets\images\mailora-logo-source.png'
if (Test-Path -LiteralPath $sourceLogo) {
	[System.IO.File]::Delete($sourceLogo)
}

if (Test-Path -LiteralPath $zip) {
	Remove-Item -LiteralPath $zip -Force
}
Push-Location $stageRoot
try {
	& tar.exe -a -c -f $zip 'mrn-mailora'
	if ($LASTEXITCODE -ne 0) {
		throw "Cross-platform ZIP creation failed with exit code $LASTEXITCODE."
	}
}
finally {
	Pop-Location
}
Remove-Item -LiteralPath $stageRoot -Recurse -Force

Write-Host "Package created: $zip" -ForegroundColor Green
