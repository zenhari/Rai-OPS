<?php
/**
 * Generate METAR HTML for PDF conversion
 */
// Helper functions
function formatTemperature($temp) {
    if ($temp === null) return 'N/A';
    return $temp > 0 ? "+{$temp}°C" : "{$temp}°C";
}

function formatWind($wdir, $wspd) {
    if ($wdir === null || $wspd === null) return 'N/A';
    
    $direction = '';
    if ($wdir >= 337.5 || $wdir < 22.5) $direction = 'N';
    elseif ($wdir >= 22.5 && $wdir < 67.5) $direction = 'NE';
    elseif ($wdir >= 67.5 && $wdir < 112.5) $direction = 'E';
    elseif ($wdir >= 112.5 && $wdir < 157.5) $direction = 'SE';
    elseif ($wdir >= 157.5 && $wdir < 202.5) $direction = 'S';
    elseif ($wdir >= 202.5 && $wdir < 247.5) $direction = 'SW';
    elseif ($wdir >= 247.5 && $wdir < 292.5) $direction = 'W';
    elseif ($wdir >= 292.5 && $wdir < 337.5) $direction = 'NW';
    
    return "{$direction} {$wspd}KT";
}

function formatVisibility($visib) {
    if (empty($visib)) return 'N/A';
    return $visib === '6+' ? '10KM+' : $visib . 'KM';
}

function formatPressure($altim) {
    if ($altim === null) return 'N/A';
    return $altim . ' hPa';
}

function formatFlightCategory($fltCat) {
    if (empty($fltCat)) return 'N/A';
    return $fltCat;
}

function formatCloudCover($cover) {
    if (empty($cover)) return 'N/A';
    
    $covers = [
        'CAVOK' => 'Clear and Visibility OK',
        'CLR' => 'Clear',
        'FEW' => 'Few',
        'SCT' => 'Scattered',
        'BKN' => 'Broken',
        'OVC' => 'Overcast'
    ];
    
    return $covers[$cover] ?? $cover;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>METAR Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #61207f;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #61207f;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .station {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .station-header {
            background: linear-gradient(to right, #0ea5e9, #0284c7);
            color: white;
            padding: 15px;
            border-radius: 5px 5px 0 0;
        }
        .station-header h2 {
            margin: 0;
            font-size: 22px;
        }
        .station-info {
            background: #f9fafb;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .weather-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .weather-item {
            background: white;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
        }
        .weather-item-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .weather-item-value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .raw-metar {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 15px 0;
            word-break: break-all;
        }
        .flight-category {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
        }
        .vfr { background: #d1fae5; color: #065f46; }
        .mvfr { background: #dbeafe; color: #1e40af; }
        .ifr { background: #fef3c7; color: #92400e; }
        .lifr { background: #fee2e2; color: #991b1b; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RAIMON AIRWAYS</h1>
        <h2>ACTUAL WEATHER (METAR)</h2>
        <p>Route: <?php echo htmlspecialchars($route ?? 'N/A'); ?></p>
        <p>Date: <?php echo htmlspecialchars(date('Y-m-d', strtotime($fltDate ?? 'now'))); ?></p>
        <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <?php if (!empty($allWeatherData)): ?>
        <?php foreach ($allWeatherData as $data): ?>
        <div class="station">
            <div class="station-header">
                <h2><?php echo htmlspecialchars($data['icaoId'] ?? 'N/A'); ?></h2>
                <p style="margin: 5px 0 0 0; font-size: 14px;">
                    <?php echo htmlspecialchars($data['name'] ?? ''); ?> | 
                    Last Updated: <?php echo isset($data['obsTime']) ? date('H:i UTC', $data['obsTime']) : 'N/A'; ?>
                </p>
            </div>
            
            <div class="station-info">
                <div class="weather-grid">
                    <div class="weather-item">
                        <div class="weather-item-label">Temperature</div>
                        <div class="weather-item-value"><?php echo formatTemperature($data['temp'] ?? null); ?></div>
                        <?php if (isset($data['dewp']) && $data['dewp'] !== null): ?>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">
                            Dew Point: <?php echo formatTemperature($data['dewp']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="weather-item">
                        <div class="weather-item-label">Wind</div>
                        <div class="weather-item-value"><?php echo formatWind($data['wdir'] ?? null, $data['wspd'] ?? null); ?></div>
                    </div>
                    
                    <div class="weather-item">
                        <div class="weather-item-label">Visibility</div>
                        <div class="weather-item-value"><?php echo formatVisibility($data['visib'] ?? ''); ?></div>
                    </div>
                    
                    <div class="weather-item">
                        <div class="weather-item-label">Pressure</div>
                        <div class="weather-item-value"><?php echo formatPressure($data['altim'] ?? null); ?></div>
                    </div>
                </div>
                
                <div style="margin: 15px 0;">
                    <strong>Flight Category:</strong>
                    <span class="flight-category <?php echo strtolower(formatFlightCategory($data['fltCat'] ?? '')); ?>">
                        <?php echo formatFlightCategory($data['fltCat'] ?? ''); ?>
                    </span>
                </div>
                
                <div style="margin: 15px 0;">
                    <strong>Cloud Cover:</strong> <?php echo formatCloudCover($data['cover'] ?? ''); ?>
                </div>
                
                <div class="raw-metar">
                    <?php echo htmlspecialchars($data['rawOb'] ?? 'N/A'); ?>
                </div>
                
                <table>
                    <tr>
                        <th>Latitude</th>
                        <td><?php echo isset($data['lat']) ? number_format($data['lat'], 4) . '°N' : 'N/A'; ?></td>
                        <th>Longitude</th>
                        <td><?php echo isset($data['lon']) ? number_format($data['lon'], 4) . '°E' : 'N/A'; ?></td>
                        <th>Elevation</th>
                        <td><?php echo isset($data['elev']) ? number_format($data['elev']) . ' ft' : 'N/A'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <p>No weather data available for the selected route.</p>
        </div>
    <?php endif; ?>
</body>
</html>

