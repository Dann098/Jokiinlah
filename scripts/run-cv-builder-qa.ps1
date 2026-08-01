$ErrorActionPreference = 'Stop'

$qaBrowserDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('jokiinlah-cv-qa-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $qaBrowserDirectory | Out-Null

$phpProcess = Start-Process -FilePath 'php' `
    -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8012') `
    -WorkingDirectory 'D:\website\jokiinlah' `
    -WindowStyle Hidden `
    -PassThru

$edgePath = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
$edgeProcess = Start-Process -FilePath $edgePath `
    -ArgumentList @(
        '--headless=new',
        '--remote-debugging-port=9235',
        ('--user-data-dir=' + $qaBrowserDirectory),
        '--no-first-run',
        '--disable-gpu',
        '--disable-extensions',
        'about:blank'
    ) `
    -WindowStyle Hidden `
    -PassThru

try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $page = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8012/fitur-gratis/pembuat-cv' -TimeoutSec 2
            $debugger = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:9235/json/version' -TimeoutSec 2
            if ($page.StatusCode -eq 200 -and $debugger.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {
            Start-Sleep -Milliseconds 300
        }
    }

    if (-not $ready) {
        throw 'Server atau browser QA tidak siap.'
    }

    $env:QA_BASE_URL = 'http://127.0.0.1:8012'
    $env:QA_DEBUGGER_URL = 'http://127.0.0.1:9235'
    node scripts\cv-builder-qa.mjs
    if ($LASTEXITCODE -ne 0) {
        throw "Browser QA gagal dengan exit code $LASTEXITCODE"
    }
} finally {
    if ($phpProcess -and -not $phpProcess.HasExited) {
        Stop-Process -Id $phpProcess.Id -Force
    }
    if ($edgeProcess -and -not $edgeProcess.HasExited) {
        Stop-Process -Id $edgeProcess.Id -Force
    }
    Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
        Where-Object { $_.LocalPort -in 8012, 9235 } |
        ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
}
