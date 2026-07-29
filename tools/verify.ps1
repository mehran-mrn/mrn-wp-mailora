$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$phpFiles = Get-ChildItem -LiteralPath $root -Recurse -Filter '*.php' |
	Where-Object { $_.FullName -notmatch '[\\/](vendor|dist)[\\/]' }

foreach ($file in $phpFiles) {
	$result = & php -l $file.FullName
	if ($LASTEXITCODE -ne 0) {
		throw "PHP syntax failed: $($file.FullName)`n$result"
	}
}

& php (Join-Path $root 'tests\run.php')
if ($LASTEXITCODE -ne 0) {
	throw 'Core smoke tests failed.'
}

Write-Host "Verified $($phpFiles.Count) PHP files and core smoke tests." -ForegroundColor Green

