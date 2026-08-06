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

$projectDirectory = Split-Path -Parent $PSScriptRoot
$taskTempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$qaBrowserDirectory = Join-Path $taskTempRoot ('jokiinlah-data-cleaner-qa-' + [guid]::NewGuid().ToString('N'))
$serverPort = Get-AvailableTcpPort
do {
    $debuggerPort = Get-AvailableTcpPort
} while ($debuggerPort -eq $serverPort)

$phpProcess = $null
$browserProcess = $null
$serverListenerProcessId = $null
$browserListenerProcessId = $null
$previousSessionDriver = $env:SESSION_DRIVER
$previousCacheStore = $env:CACHE_STORE
$previousQueueConnection = $env:QUEUE_CONNECTION
$previousDatabaseConnection = $env:DB_CONNECTION
$previousDatabasePath = $env:DB_DATABASE

try {
    $env:SESSION_DRIVER = 'array'
    $env:CACHE_STORE = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    New-Item -ItemType Directory -Path $qaBrowserDirectory | Out-Null
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = Join-Path $qaBrowserDirectory 'qa.sqlite'
    New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null
    php artisan migrate --force --quiet
    if ($LASTEXITCODE -ne 0) {
        throw "Migrasi database sementara QA gagal dengan exit code $LASTEXITCODE"
    }
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
    $pageReady = $false
    $debuggerReady = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (-not $pageReady) {
            try {
            $page = Invoke-WebRequest -UseBasicParsing ($baseUrl + '/fitur-gratis/pembersih-data') -TimeoutSec 2
                $pageReady = $page.StatusCode -eq 200
            } catch {}
        }

        if (-not $debuggerReady) {
            try {
                $debugger = Invoke-WebRequest -UseBasicParsing ($debuggerUrl + '/json/version') -TimeoutSec 2
                $debuggerReady = $debugger.StatusCode -eq 200
            } catch {}
        }

        if ($pageReady -and $debuggerReady) {
            $ready = $true
            break
        }

        Start-Sleep -Milliseconds 300
    }

    if (-not $ready) {
        throw "Server atau browser QA tidak siap. HTTP=$pageReady; CDP=$debuggerReady; PHP exited=$($phpProcess.HasExited); browser exited=$($browserProcess.HasExited)."
    }

    $serverListenerProcessId = (Get-NetTCPConnection -LocalPort $serverPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    $browserListenerProcessId = (Get-NetTCPConnection -LocalPort $debuggerPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    $env:QA_BASE_URL = $baseUrl
    $env:QA_DEBUGGER_URL = $debuggerUrl
    $env:QA_BROWSER_NAME = $browser.Name
    node scripts\data-cleaner-qa.mjs
    if ($LASTEXITCODE -ne 0) {
        throw "Browser QA gagal dengan exit code $LASTEXITCODE"
    }
} finally {
    $serverListenerProcessId = (Get-NetTCPConnection -LocalPort $serverPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    $browserListenerProcessId = (Get-NetTCPConnection -LocalPort $debuggerPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess
    @($phpProcess.Id, $browserProcess.Id, $serverListenerProcessId, $browserListenerProcessId) |
        Where-Object { $_ } |
        Sort-Object -Unique |
        ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

    $resolvedBrowserDirectory = [System.IO.Path]::GetFullPath($qaBrowserDirectory)
    if ($resolvedBrowserDirectory.StartsWith($taskTempRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedBrowserDirectory)) {
        Remove-Item -LiteralPath $resolvedBrowserDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }

    $env:SESSION_DRIVER = $previousSessionDriver
    $env:CACHE_STORE = $previousCacheStore
    $env:QUEUE_CONNECTION = $previousQueueConnection
    $env:DB_CONNECTION = $previousDatabaseConnection
    $env:DB_DATABASE = $previousDatabasePath
}
