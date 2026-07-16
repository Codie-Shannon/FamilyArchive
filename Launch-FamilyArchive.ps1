$ErrorActionPreference = 'Stop'
Set-Location 'C:\Projects\FamilyArchive'

Write-Host 'Starting Family Archive...' -ForegroundColor Cyan
Write-Host 'Open http://127.0.0.1:8000/login after the servers start.' -ForegroundColor Green

composer run dev