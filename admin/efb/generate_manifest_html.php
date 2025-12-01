<?php
/**
 * Generate Manifest HTML for PDF conversion
 * This file is included by index.php when generating manifest
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Crew Manifest</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            border: 1px solid #000;
        }
        td { 
            border: 1px solid #000; 
            padding: 4px; 
        }
        .bg-header { background-color: #f9fafb; }
        .bg-purple { background-color: #61207f; color: white; }
        .bg-gray { background-color: #d9d9d9; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
<table border="1" cellpadding="4" cellspacing="0" width="100%">
    <!-- HEADER -->
    <tr>
        <td colspan="2" class="bg-header text-center" style="width:20%; vertical-align: middle; padding: 12px;">
            <img src="<?php echo base_url(); ?>/assets/raimon.png" alt="RAIMON AIRWAYS" style="max-width: 100%; max-height: 120px;">
        </td>
        <td colspan="4" class="bg-header text-center" style="vertical-align: middle; padding: 12px;">
            <div style="font-size: 28px; font-weight: bold; color: #61207f; letter-spacing: 1px;">CREW LIST</div>
        </td>
        <td colspan="2" class="bg-header text-center" style="vertical-align: middle; padding: 12px;">
            <div style="font-size: 16px; font-weight: bold; color: #61207f;">RAIMON AIRWAYS</div>
        </td>
    </tr>
    
    <!-- Purple line -->
    <tr>
        <td colspan="8" style="height: 2px; background-color: #61207f; padding: 0;"></td>
    </tr>
    
    <!-- DEPARTURE / ARRIVAL -->
    <tr>
        <td colspan="4" class="bg-gray text-center text-bold" style="padding: 8px;">DEPARTURE</td>
        <td colspan="4" class="bg-gray text-center text-bold" style="padding: 8px;">ARRIVAL</td>
    </tr>
    
    <!-- DATES -->
    <tr>
        <td colspan="4" class="bg-header" style="padding: 4px;">DATE OF DEP: <?php echo $dateFormatted; ?></td>
        <td colspan="4" class="bg-header" style="padding: 4px;">DATE OF ARR: <?php echo $dateFormatted; ?></td>
    </tr>
    
    <!-- SCHEDULE TIMES -->
    <tr>
        <td colspan="4" class="bg-header" style="padding: 4px;">SCHEDULE DEP TIME: <?php echo $scheduleDepTime; ?></td>
        <td colspan="4" class="bg-header" style="padding: 4px;">SCHEDULE ARR TIME: <?php echo $scheduleArrTime; ?></td>
    </tr>
    
    <!-- ROUTE & FLIGHT -->
    <tr>
        <td style="width:5%; border: 0;">&nbsp;</td>
        <td style="width:15%; border: 0; font-weight: bold;">ROUTE</td>
        <td colspan="3" style="border: 0;">&nbsp;</td>
        <td colspan="2" style="border: 0; text-align: right;"><strong>FLIGHT NO:</strong> <?php echo htmlspecialchars($taskName ? 'RAI' . $taskName : 'N/A'); ?></td>
        <td style="border: 0;">&nbsp;</td>
    </tr>
    
    <!-- A/C REG -->
    <tr>
        <td style="border: 0;">&nbsp;</td>
        <td style="border: 0;">&nbsp;</td>
        <td colspan="3" style="border: 0;">&nbsp;</td>
        <td colspan="2" style="border: 0; text-align: right;"><strong>A/C REG:</strong> <?php echo htmlspecialchars($aircraftRego); ?></td>
        <td style="border: 0;">&nbsp;</td>
    </tr>
    
    <!-- ROUTE SECTORS -->
    <tr>
        <td style="border: 0;">&nbsp;</td>
        <?php
        $routeParts = explode('-', $route);
        $sectors = [];
        for ($i = 0; $i < count($routeParts) - 1; $i++) {
            $sectors[] = $routeParts[$i] . '-' . $routeParts[$i + 1];
        }
        foreach (array_slice($sectors, 0, 3) as $sector):
        ?>
        <td class="bg-header text-center" style="padding: 4px;"><?php echo htmlspecialchars($sector); ?></td>
        <?php endforeach; ?>
        <?php for ($i = count($sectors); $i < 3; $i++): ?>
        <td style="border: 0;">&nbsp;</td>
        <?php endfor; ?>
        <td colspan="2" style="border: 0;">&nbsp;</td>
    </tr>
    
    <!-- Empty row -->
    <tr>
        <td colspan="8" style="border: 0; height: 8px;">&nbsp;</td>
    </tr>
    
    <!-- CREW TITLE -->
    <tr>
        <td colspan="8" class="bg-gray text-center text-bold" style="padding: 10px; font-size: 18px;">CREW</td>
    </tr>
    
    <!-- CREW HEADER -->
    <tr>
        <td class="bg-header text-center text-bold" style="padding: 6px;">NO</td>
        <td class="bg-header text-center text-bold" style="padding: 6px;">POSITION</td>
        <td class="bg-header text-center text-bold" style="padding: 6px;">ID NO</td>
        <td colspan="3" class="bg-purple text-center text-bold" style="padding: 6px;">NAME OF CREW</td>
        <td colspan="2" class="bg-header text-center text-bold" style="padding: 6px;">PASS NO</td>
    </tr>
    
    <!-- CREW ROWS -->
    <?php for ($i = 0; $i < 9; $i++): ?>
    <?php $member = $crewMembers[$i] ?? null; ?>
    <tr>
        <td class="text-center" style="padding: 4px;"><?php echo $i + 1; ?></td>
        <td class="text-center" style="padding: 4px;"><?php echo $member ? htmlspecialchars(strtoupper($member['role'] ?? '')) : ''; ?></td>
        <td class="text-center" style="padding: 4px;"></td>
        <td colspan="3" class="text-center" style="padding: 4px;"><?php echo $member ? htmlspecialchars(strtoupper($member['name'] ?? '')) : ''; ?></td>
        <td colspan="2" class="text-center" style="padding: 4px;"><?php echo $member ? htmlspecialchars($member['national_id'] ?? '') : ''; ?></td>
    </tr>
    <?php endfor; ?>
    
    <!-- FM & D/H SECTION -->
    <tr>
        <td class="bg-header text-center text-bold" style="padding: 6px;">NO</td>
        <td class="bg-header text-center text-bold" style="padding: 6px;">POSITION</td>
        <td class="bg-header text-center text-bold" style="padding: 6px;">ID NO</td>
        <td colspan="3" class="bg-purple text-center text-bold" style="padding: 6px;">FM &amp; D/H</td>
        <td colspan="2" class="bg-header text-center text-bold" style="padding: 6px;">PASS NO</td>
    </tr>
    
    <?php for ($i = 0; $i < 3; $i++): ?>
    <tr>
        <td class="text-center" style="padding: 4px;"><?php echo $i + 1; ?></td>
        <td style="padding: 4px;"></td>
        <td style="padding: 4px;"></td>
        <td colspan="3" style="padding: 4px;"></td>
        <td colspan="2" style="padding: 4px;"></td>
    </tr>
    <?php endfor; ?>
    
    <!-- DISPATCH & STAMP -->
    <tr>
        <td colspan="5" valign="top" style="padding: 4px;">
            RAIMON AIRWAYS DISPATCH: <?php echo htmlspecialchars(strtoupper($dispatchName ?: 'N/A')); ?><br>
            LICENCE NUMBER: <?php echo htmlspecialchars($dispatchLicense); ?><br><br>
            SIGNATURE:<br><br><br>
        </td>
        <td colspan="3" valign="top" class="text-center" style="padding: 4px;">
            STAMP:<br><br><br>
        </td>
    </tr>
</table>
</body>
</html>

