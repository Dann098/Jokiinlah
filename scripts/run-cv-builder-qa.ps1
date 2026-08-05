$ErrorActionPreference = 'Stop'

function Get-AvailableTcpPort {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    try {
        $listener.Start()

        return $listener.LocalEndpoint.Port
    } finally {
        $listener.Stop()
    }
}

$browserCandidates = @(
    @{ Name = 'Google Chrome'; Path = 'C:\Program Files\Google\Chrome\Application\chrome.exe' },
    @{ Name = 'Google Chrome'; Path = 'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe' },
    @{ Name = 'Microsoft Edge Chromium'; Path = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' }
)
$browser = $browserCandidates | Where-Object { Test-Path -LiteralPath $_.Path } | Select-Object -First 1
if (-not $browser) {
    throw 'Google Chrome atau Microsoft Edge Chromium tidak ditemukan.'
}

$pdfTextCandidates = @(
    (Get-Command 'pdftotext.exe' -ErrorAction SilentlyContinue).Source,
    'C:\Program Files\Git\mingw64\bin\pdftotext.exe'
) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }
$pdfTextPath = $pdfTextCandidates | Select-Object -First 1
if (-not $pdfTextPath) {
    throw 'pdftotext tidak ditemukan. Browser QA memerlukannya untuk memeriksa isi setiap halaman PDF.'
}

$projectDirectory = Split-Path -Parent $PSScriptRoot
$taskTempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$qaBrowserDirectory = Join-Path $taskTempRoot ('jokiinlah-cv-qa-' + [guid]::NewGuid().ToString('N'))
$serverPort = Get-AvailableTcpPort
do {
    $debuggerPort = Get-AvailableTcpPort
} while ($debuggerPort -eq $serverPort)

$phpProcess = $null
$browserProcess = $null
$serverListenerProcessId = $null
$browserListenerProcessId = $null

try {
    New-Item -ItemType Directory -Path $qaBrowserDirectory | Out-Null

    $phpProcess = Start-Process -FilePath 'php' `
        -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', ('--port=' + $serverPort)) `
        -WorkingDirectory $projectDirectory `
        -WindowStyle Hidden `
        -PassThru

    $browserProcess = Start-Process -FilePath $browser.Path `
        -ArgumentList @(
            '--headless=new',
            ('--remote-debugging-port=' + $debuggerPort),
            ('--user-data-dir=' + $qaBrowserDirectory),
            '--no-first-run',
            '--disable-gpu',
            '--disable-extensions',
            'about:blank'
        ) `
        -WindowStyle Hidden `
        -PassThru

    $baseUrl = 'http://127.0.0.1:' + $serverPort
    $debuggerUrl = 'http://127.0.0.1:' + $debuggerPort
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $page = Invoke-WebRequest -UseBasicParsing ($baseUrl + '/fitur-gratis/pembuat-cv') -TimeoutSec 2
            $debugger = Invoke-WebRequest -UseBasicParsing ($debuggerUrl + '/json/version') -TimeoutSec 2
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

    $serverListenerProcessId = (Get-NetTCPConnection -LocalPort $serverPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    $browserListenerProcessId = (Get-NetTCPConnection -LocalPort $debuggerPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    $env:QA_BASE_URL = $baseUrl
    $env:QA_DEBUGGER_URL = $debuggerUrl
    $env:QA_BROWSER_NAME = $browser.Name
    $env:QA_PDFTOTEXT_PATH = $pdfTextPath
    node scripts\cv-builder-qa.mjs
    if ($LASTEXITCODE -ne 0) {
        throw "Browser QA gagal dengan exit code $LASTEXITCODE"
    }
} finally {
    @($phpProcess.Id, $browserProcess.Id, $serverListenerProcessId, $browserListenerProcessId) |
        Where-Object { $_ } |
        Sort-Object -Unique |
        ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

    $resolvedBrowserDirectory = [System.IO.Path]::GetFullPath($qaBrowserDirectory)
    if ($resolvedBrowserDirectory.StartsWith($taskTempRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedBrowserDirectory)) {
        Remove-Item -LiteralPath $resolvedBrowserDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }
}
