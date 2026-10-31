# Dot-source this script to select Laragon PHP for this terminal session only.
$barberflowPhpDirectory = 'C:\laragon\bin\php\php-8.5.6-Win32-vs17-x64'
$barberflowPhpExecutable = Join-Path $barberflowPhpDirectory 'php.exe'
if (-not (Test-Path -LiteralPath $barberflowPhpExecutable)) {
    throw "PHP 8.5 Laragon tidak ditemukan: $barberflowPhpExecutable"
}

$env:Path = $barberflowPhpDirectory + [IO.Path]::PathSeparator + $env:Path

& $barberflowPhpExecutable -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) { throw 'Ekstensi pdo_mysql gagal dimuat untuk sesi terminal ini.' }

& $barberflowPhpExecutable --version
if ($LASTEXITCODE -ne 0) { throw 'PHP Laragon gagal dijalankan.' }
