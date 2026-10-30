# Dot-source this script to select Laragon PHP for this terminal session only.
$barberflowPhpDirectory = 'C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64'
$barberflowPhpExecutable = Join-Path $barberflowPhpDirectory 'php.exe'
if (-not (Test-Path -LiteralPath $barberflowPhpExecutable)) {
    throw "PHP 8.5 Laragon tidak ditemukan: $barberflowPhpExecutable"
}

$env:Path = $barberflowPhpDirectory + [IO.Path]::PathSeparator + $env:Path

# The scan directory is inherited by PHP children (Artisan serve/test and Composer).
# Keep Laragon's global php.ini unchanged and avoid loading the extension twice.
& $barberflowPhpExecutable -r "exit(extension_loaded('pdo_pgsql') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) {
    $barberflowIniDirectory = Join-Path $PSScriptRoot 'php'
    if ([string]::IsNullOrEmpty($env:PHP_INI_SCAN_DIR)) {
        $env:PHP_INI_SCAN_DIR = $barberflowIniDirectory
    } else {
        $env:PHP_INI_SCAN_DIR = $env:PHP_INI_SCAN_DIR + [IO.Path]::PathSeparator + $barberflowIniDirectory
    }
}

& $barberflowPhpExecutable -r "exit(extension_loaded('pdo_pgsql') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) { throw 'Ekstensi pdo_pgsql gagal dimuat untuk sesi terminal ini.' }

& $barberflowPhpExecutable --version
if ($LASTEXITCODE -ne 0) { throw 'PHP Laragon gagal dijalankan.' }
