param(
  [string]$Source = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
  [string]$Target = 'C:\xampp\htdocs\ntpc-foodmap'
)

$ErrorActionPreference = 'Stop'

New-Item -ItemType Directory -Force -Path $Target | Out-Null

robocopy $Source $Target /MIR /XD .git .runtime node_modules /XF config.php /R:2 /W:1
$code = $LASTEXITCODE

if ($code -le 7) {
  Write-Output "Synced $Source to $Target (robocopy code $code)."
  exit 0
}

Write-Error "XAMPP sync failed (robocopy code $code)."
exit $code
