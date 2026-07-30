[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string[]] $EvidencePath,

    [ValidateRange(1, 10000)]
    [int] $MinimumWidth = 1800,

    [ValidateRange(1, 10000)]
    [int] $MinimumHeight = 1000
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$forbiddenChunks = @('tEXt', 'zTXt', 'iTXt', 'eXIf')

foreach ($candidate in $EvidencePath) {
    $resolved = Resolve-Path -LiteralPath $candidate
    $image = [System.Drawing.Image]::FromFile($resolved.Path)

    try {
        $width = $image.Width
        $height = $image.Height
    } finally {
        $image.Dispose()
    }

    if ($width -lt $MinimumWidth -or $height -lt $MinimumHeight) {
        throw "$candidate is ${width}x${height}; desktop evidence must be at least ${MinimumWidth}x${MinimumHeight}."
    }

    $bytes = [System.IO.File]::ReadAllBytes($resolved.Path)
    $offset = 8
    $foundForbiddenChunks = [System.Collections.Generic.List[string]]::new()

    while ($offset -lt $bytes.Length) {
        if (($offset + 12) -gt $bytes.Length) {
            throw "$candidate has a truncated PNG chunk."
        }

        $lengthBytes = [byte[]] $bytes[$offset..($offset + 3)]
        [Array]::Reverse($lengthBytes)
        $length = [BitConverter]::ToUInt32($lengthBytes, 0)
        $type = [Text.Encoding]::ASCII.GetString($bytes, $offset + 4, 4)

        if ($type -in $forbiddenChunks) {
            $foundForbiddenChunks.Add($type)
        }

        $offset += 12 + [int] $length

        if ($type -eq 'IEND') {
            break
        }
    }

    if ($foundForbiddenChunks.Count -gt 0) {
        $chunkList = $foundForbiddenChunks -join ', '
        throw "$candidate contains forbidden PNG metadata chunks: $chunkList."
    }

    Write-Host "OK  $candidate  ${width}x${height}  metadata clean" -ForegroundColor Green
}
