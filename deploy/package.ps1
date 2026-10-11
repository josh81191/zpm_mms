param(
    [string]$Output = "zpm-release.zip"
)

# Builds a deployable archive: no .git, no local config, no development-only scripts.
$root = Split-Path -Parent $PSScriptRoot
$stage = Join-Path ([IO.Path]::GetTempPath()) ("zpm-release-" + [guid]::NewGuid())
New-Item -ItemType Directory -Path $stage | Out-Null

foreach ($item in 'app', 'public', 'templates', 'database', 'deploy', 'scripts', 'README.md', '.htaccess') {
    Copy-Item (Join-Path $root $item) (Join-Path $stage $item) -Recurse
}

New-Item -ItemType Directory -Path (Join-Path $stage 'config') | Out-Null
Copy-Item (Join-Path $root 'config\config.example.php') (Join-Path $stage 'config\config.example.php')

foreach ($dev in 'create-demo-users.php', 'seed-demo-data.php', 'seed-demo-families.php',
    'seed-party-movement.php', 'seed-unit-profiles.php', 'rename-demo-individuals.php', 'dev-router.php') {
    Remove-Item (Join-Path $stage "scripts\$dev") -ErrorAction SilentlyContinue
}

$target = if ([IO.Path]::IsPathRooted($Output)) { $Output } else { Join-Path $root $Output }
if (Test-Path $target) { Remove-Item $target }
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $target
Remove-Item $stage -Recurse -Force
Write-Host "Created $target"
