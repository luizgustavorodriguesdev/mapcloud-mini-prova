<#
Demo tests PowerShell script for MapCloud Mini‑Prova
Run from the project root (where this file lives).

Usage (PowerShell):
  cd D:\xampp\htdocs\mapcloud-mini-prova
  # allow script for this session if needed:
  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process
  .\demo_tests.ps1

This script will:
 - attempt to upload a sample NF-e XML
 - send a sample JSON events file
 - query the rastreamento API for the sample chave
 - list entregas (page 1)
 - query metrics (gargalo)

It prefers curl.exe (Windows 10+). If curl is not available, the script will fall back to Invoke-RestMethod for JSON uploads.
#>

$BaseUrl = 'http://localhost/mapcloud-mini-prova/backend'
$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

# Samples (adjust filenames if you renamed them)
$xmlSample = 'sample_data/nfe_cenario1_entregue.xml'
$eventsSample = 'sample_data/eventos_cenario1_entregue.json'
$chaveSample = '35250900000000000101550010000000011000000011'

Write-Host "Project root: $ProjectRoot" -ForegroundColor Cyan
Write-Host "Base API: $BaseUrl" -ForegroundColor Cyan

function Has-Curl {
  try {
    $null = & curl.exe --version 2>$null
    return $true
  } catch {
    return $false
  }
}

# 1) Upload NF-e (multipart) - prefer curl for file upload
Write-Host "\n1) Uploading NF-e XML: $xmlSample" -ForegroundColor Yellow
if (Test-Path $xmlSample) {
  if (Has-Curl) {
    Write-Host "Using curl.exe to POST multipart/form-data..."
    & curl.exe -i -X POST "$BaseUrl/webhook_nfe_upload.php" -F "xml=@$xmlSample;type=application/xml"
  } else {
    Write-Host "curl.exe not found. Falling back to raw POST (may fail if server expects multipart)."
    $xml = Get-Content -Raw $xmlSample
    try {
      $r = Invoke-RestMethod -Uri "$BaseUrl/webhook_nfe_upload.php" -Method Post -Body $xml -ContentType 'text/xml'
      Write-Output $r | ConvertTo-Json -Depth 5
    } catch {
      Write-Host "Error posting XML via Invoke-RestMethod: $_" -ForegroundColor Red
    }
  }
} else {
  Write-Host "Sample XML not found: $xmlSample" -ForegroundColor Red
}

Start-Sleep -Seconds 1

# 2) Send events JSON
Write-Host "\n2) Sending events JSON: $eventsSample" -ForegroundColor Yellow
if (Test-Path $eventsSample) {
  $json = Get-Content -Raw $eventsSample
  if (Has-Curl) {
    Write-Host "Using curl.exe to POST JSON..."
    & curl.exe -i -X POST "$BaseUrl/webhook_evento.php" -H "Content-Type: application/json" -d "@$eventsSample"
  } else {
    try {
      $r = Invoke-RestMethod -Uri "$BaseUrl/webhook_evento.php" -Method Post -Body $json -ContentType 'application/json'
      Write-Output $r | ConvertTo-Json -Depth 5
    } catch {
      Write-Host "Error posting JSON via Invoke-RestMethod: $_" -ForegroundColor Red
    }
  }
} else {
  Write-Host "Sample events JSON not found: $eventsSample" -ForegroundColor Red
}

Start-Sleep -Seconds 1

# 3) Query rastreamento
Write-Host "\n3) Querying tracking (rastreamento) for chave: $chaveSample" -ForegroundColor Yellow
try {
  $r = Invoke-RestMethod -Uri "$BaseUrl/api_rastreamento.php?chave=$chaveSample" -Method Get
  Write-Output $r | ConvertTo-Json -Depth 6
} catch {
  Write-Host "Error querying api_rastreamento: $_" -ForegroundColor Red
}

Start-Sleep -Seconds 1

# 4) List entregas (page 1)
Write-Host "\n4) Listing entregas (page=1, limit=10)" -ForegroundColor Yellow
try {
  $r = Invoke-RestMethod -Uri "$BaseUrl/api_entregas.php?page=1&limit=10" -Method Get
  Write-Output $r | ConvertTo-Json -Depth 4
} catch {
  Write-Host "Error querying api_entregas: $_" -ForegroundColor Red
}

Start-Sleep -Seconds 1

# 5) Query metrics (gargalo)
Write-Host "\n5) Querying metrics (gargalo)" -ForegroundColor Yellow
try {
  $r = Invoke-RestMethod -Uri "$BaseUrl/api_metricas_gargalo.php" -Method Get
  Write-Output $r | ConvertTo-Json -Depth 4
} catch {
  Write-Host "Error querying api_metricas_gargalo: $_" -ForegroundColor Red
}

Write-Host "\nDone. If you want to re-run only a step, open the script and comment/uncomment sections." -ForegroundColor Green
