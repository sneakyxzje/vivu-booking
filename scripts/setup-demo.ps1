param(
    [ValidatePattern('^vivu_demo_[a-z0-9_]{1,40}$')]
    [string]$Database = 'vivu_demo_defense',
    [switch]$SkipInstall
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

function Invoke-Checked {
    param([string]$Program, [string[]]$Arguments)
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Program failed (exit $LASTEXITCODE). Fix the error above before continuing."
    }
}

Get-Command php -ErrorAction Stop | Out-Null
Get-Command npm.cmd -ErrorAction Stop | Out-Null
if (!$SkipInstall) { Get-Command composer -ErrorAction Stop | Out-Null }

Push-Location $projectRoot
try {
    Set-Location (Join-Path $projectRoot 'server')
    if (!(Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
    if (!$SkipInstall) { Invoke-Checked 'composer' @('install', '--no-interaction', '--prefer-dist') }
    Invoke-Checked 'php' @('artisan', 'config:clear')
    $environmentText = Get-Content -LiteralPath '.env' -Raw
    $keyMatch = [regex]::Match($environmentText, '(?m)^APP_KEY=([^\r\n]*)')
    $configuredKey = $keyMatch.Groups[1].Value.Trim().Trim([char]34).Trim([char]39)
    if ([string]::IsNullOrWhiteSpace($configuredKey)) {
        Invoke-Checked 'php' @('artisan', 'key:generate')
    }

    Set-Location (Join-Path $projectRoot 'client')
    if (!(Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
    if (!$SkipInstall) { Invoke-Checked 'npm.cmd' @('ci') }
    Invoke-Checked 'npm.cmd' @('run', 'build')

    Set-Location (Join-Path $projectRoot 'server')
    Invoke-Checked 'php' @('artisan', 'demo:restore', "--database=$Database", '--activate')
    Write-Host ''
    Write-Host 'Demo ready. Follow docs/DEMO_LAPTOP.md to start the app and background workers.'
}
finally {
    Pop-Location
}
