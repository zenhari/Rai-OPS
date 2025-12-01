<?php
/**
 * Generate TAF (Terminal Aerodrome Forecast) HTML for PDF conversion
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>TAF Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #0ea5e9;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #0ea5e9;
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
        .taf-raw {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 15px 0;
            word-break: break-all;
            white-space: pre-wrap;
        }
        .taf-parsed {
            background: white;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            margin: 15px 0;
        }
        .taf-parsed h4 {
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 16px;
        }
        .taf-parsed p {
            margin: 5px 0;
            color: #4b5563;
            line-height: 1.6;
        }
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
        <h2>WEATHER FORECAST (TAF)</h2>
        <p>Route: <?php echo htmlspecialchars($route ?? 'N/A'); ?></p>
        <p>Date: <?php echo htmlspecialchars(date('Y-m-d', strtotime($fltDate ?? 'now'))); ?></p>
        <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <?php if (!empty($allTAFData)): ?>
        <?php foreach ($allTAFData as $data): ?>
        <div class="station">
            <div class="station-header">
                <h2><?php echo htmlspecialchars($data['icaoId'] ?? 'N/A'); ?></h2>
                <p style="margin: 5px 0 0 0; font-size: 14px;">
                    <?php if (!empty($data['name'])): ?>
                        <?php echo htmlspecialchars($data['name']); ?>
                    <?php endif; ?>
                    <?php if (isset($data['issueTime'])): ?>
                        | Issued: <?php 
                            $issueTime = $data['issueTime'];
                            if (is_numeric($issueTime)) {
                                echo date('Y-m-d H:i UTC', (int)$issueTime);
                            } elseif (is_string($issueTime)) {
                                // Try to parse ISO 8601 format or other date formats
                                $timestamp = strtotime($issueTime);
                                if ($timestamp !== false) {
                                    echo date('Y-m-d H:i UTC', $timestamp);
                                } else {
                                    echo htmlspecialchars($issueTime);
                                }
                            }
                        ?>
                    <?php endif; ?>
                    <?php if (isset($data['validTime'])): ?>
                        | Valid: <?php 
                            $validTime = $data['validTime'];
                            if (is_numeric($validTime)) {
                                echo date('Y-m-d H:i', (int)$validTime);
                            } elseif (is_string($validTime)) {
                                $timestamp = strtotime($validTime);
                                if ($timestamp !== false) {
                                    echo date('Y-m-d H:i', $timestamp);
                                } else {
                                    echo htmlspecialchars($validTime);
                                }
                            }
                        ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="station-info">
                <?php if (!empty($data['rawTAF'])): ?>
                <div class="taf-raw">
                    <?php echo htmlspecialchars($data['rawTAF']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($data['rawOb'])): ?>
                <div class="taf-raw">
                    <?php echo htmlspecialchars($data['rawOb']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($data['forecast']) && is_array($data['forecast']) && !empty($data['forecast'])): ?>
                <div class="taf-parsed">
                    <h4>Forecast Details:</h4>
                    <?php foreach ($data['forecast'] as $forecast): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: #f9fafb; border-left: 3px solid #0ea5e9;">
                            <?php if (isset($forecast['fcstTimeFrom']) && isset($forecast['fcstTimeTo'])): ?>
                            <p><strong>Valid Period:</strong> 
                                <?php 
                                $timeFrom = $forecast['fcstTimeFrom'];
                                $timeTo = $forecast['fcstTimeTo'];
                                if (is_numeric($timeFrom)) {
                                    echo date('Y-m-d H:i', (int)$timeFrom);
                                } elseif (is_string($timeFrom)) {
                                    $timestamp = strtotime($timeFrom);
                                    echo $timestamp !== false ? date('Y-m-d H:i', $timestamp) : htmlspecialchars($timeFrom);
                                } else {
                                    echo 'N/A';
                                }
                                ?> - 
                                <?php 
                                if (is_numeric($timeTo)) {
                                    echo date('Y-m-d H:i', (int)$timeTo);
                                } elseif (is_string($timeTo)) {
                                    $timestamp = strtotime($timeTo);
                                    echo $timestamp !== false ? date('Y-m-d H:i', $timestamp) : htmlspecialchars($timeTo);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (isset($forecast['windDir']) && isset($forecast['windSpeed'])): ?>
                            <p><strong>Wind:</strong> 
                                <?php 
                                $wdir = $forecast['windDir'] ?? null;
                                $wspd = $forecast['windSpeed'] ?? null;
                                if ($wdir !== null && $wspd !== null) {
                                    echo htmlspecialchars($wdir . '° at ' . $wspd . 'KT');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (isset($forecast['visibility'])): ?>
                            <p><strong>Visibility:</strong> <?php echo htmlspecialchars($forecast['visibility']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (isset($forecast['wxString'])): ?>
                            <p><strong>Weather:</strong> <?php echo htmlspecialchars($forecast['wxString']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (isset($forecast['skyCondition']) && is_array($forecast['skyCondition'])): ?>
                            <p><strong>Sky Condition:</strong>
                                <?php 
                                $skyConditions = [];
                                foreach ($forecast['skyCondition'] as $sky) {
                                    if (isset($sky['cover']) && isset($sky['base'])) {
                                        $skyConditions[] = $sky['cover'] . ' at ' . $sky['base'] . 'ft';
                                    }
                                }
                                echo htmlspecialchars(implode(', ', $skyConditions));
                                ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <table>
                    <tr>
                        <th>ICAO Code</th>
                        <td><?php echo htmlspecialchars($data['icaoId'] ?? 'N/A'); ?></td>
                        <th>Station Name</th>
                        <td><?php echo htmlspecialchars($data['name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php if (isset($data['lat']) && isset($data['lon'])): ?>
                    <tr>
                        <th>Latitude</th>
                        <td><?php echo number_format($data['lat'], 4) . '°N'; ?></td>
                        <th>Longitude</th>
                        <td><?php echo number_format($data['lon'], 4) . '°E'; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($data['elev'])): ?>
                    <tr>
                        <th>Elevation</th>
                        <td><?php echo number_format($data['elev']) . ' ft'; ?></td>
                        <th></th>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <p>No TAF data available for the selected route.</p>
        </div>
    <?php endif; ?>
</body>
</html>

