<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/caa/daily_report.php');

$current_user = getCurrentUser();

// Load vendor autoload (needed for PhpSpreadsheet)
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die('Error: Composer autoloader not found. Please run "composer install" to install dependencies.');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (isset($_POST['submit'])) {
    // Load jdf.php - check in current directory or parent directories
    $jdfPath = __DIR__ . '/jdf.php';
    if (!file_exists($jdfPath)) {
        $jdfPath = __DIR__ . '/../../jdf.php';
    }
    if (file_exists($jdfPath)) {
        require $jdfPath;
    } else {
        die('Error: jdf.php not found. Please ensure the file exists.');
    }

    // -----------------------------------------
    // INPUT & DATE CONVERSIONS
    // -----------------------------------------
    $input_date = $_POST['persian_date'];          // e.g. 1404/01/31 (Jalali)
    [$jy, $jm, $jd] = explode('/', $input_date);
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd); // → Gregorian parts
    $date_for_link = sprintf('%04d%02d%02d', $gy, $gm, $gd); // 20250408

    // File-system safe version of the input date
    $safe_input_date = str_replace('/', '-', $input_date);    // 1404-01-31

    // -----------------------------------------
    // FETCH FLIGHT DATA FROM DATABASE
    // -----------------------------------------
    $db = getDBConnection();
    $gregorianDate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd); // 2025-12-08
    
    $stmt = $db->prepare("
        SELECT 
            FltDate,
            FlightNo,
            Rego,
            Route,
            TaskStart,
            TaskEnd,
            adult,
            child,
            infant,
            weight,
            uplift_fuel,
            off_block,
            takeoff,
            on_block,
            landed
        FROM flights 
        WHERE DATE(FltDate) = ?
        ORDER BY TaskStart ASC
    ");
    $stmt->execute([$gregorianDate]);
    $dbFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert database format to API-like format
    $flights = [];
    foreach ($dbFlights as $dbFlight) {
        // Parse Route (e.g., "RAS-THR" or "THR-AZD")
        $routeParts = explode('-', $dbFlight['Route'] ?? '');
        $fromAirport = !empty($routeParts[0]) ? trim($routeParts[0]) : '';
        $toAirport = !empty($routeParts[1]) ? trim($routeParts[1]) : '';
        
        // Extract date from FltDate (format: YYYY-MM-DD)
        $fltDateRaw = $dbFlight['FltDate'] ?? $gregorianDate;
        if (is_string($fltDateRaw)) {
            // Extract date part only (YYYY-MM-DD) from datetime string
            $fltDate = substr($fltDateRaw, 0, 10);
        } else {
            $fltDate = $gregorianDate;
        }
        
        $std = null;
        $takeoff = null;
        $chocksout = null;
        
        // TaskStart -> STD (format: YYYY-MM-DD HH:MM:SS)
        if (!empty($dbFlight['TaskStart'])) {
            $std = $dbFlight['TaskStart'];
        } elseif (!empty($fltDate)) {
            // If TaskStart is empty, use FltDate with default time
            $std = $fltDate . ' 00:00:00';
        }
        
        // takeoff field -> Takeoff
        if (!empty($dbFlight['takeoff'])) {
            $takeoffTime = $dbFlight['takeoff'];
            // Convert HHMM to HH:MM:SS
            if (strlen($takeoffTime) == 4 && is_numeric($takeoffTime)) {
                $hour = substr($takeoffTime, 0, 2);
                $minute = substr($takeoffTime, 2, 2);
                $takeoff = $fltDate . ' ' . $hour . ':' . $minute . ':00';
            }
        }
        
        // off_block -> ChocksOut
        if (!empty($dbFlight['off_block'])) {
            $offBlockTime = $dbFlight['off_block'];
            // Convert HHMM to HH:MM:SS
            if (strlen($offBlockTime) == 4 && is_numeric($offBlockTime)) {
                $hour = substr($offBlockTime, 0, 2);
                $minute = substr($offBlockTime, 2, 2);
                $chocksout = $fltDate . ' ' . $hour . ':' . $minute . ':00';
            }
        }
        
        $flights[] = [
            'Date' => $fltDate,
            'STD' => $std,
            'Takeoff' => $takeoff,
            'ChocksOut' => $chocksout,
            'FlightNumber' => $dbFlight['FlightNo'] ?? '',
            'Register' => $dbFlight['Rego'] ?? '',
            'FromAirportIATA' => $fromAirport,
            'ToAirportIATA' => $toAirport,
            'PaxAdult' => intval($dbFlight['adult'] ?? 0),
            'PaxChild' => intval($dbFlight['child'] ?? 0),
            'PaxInfant' => intval($dbFlight['infant'] ?? 0),
            'BaggageWeight' => floatval($dbFlight['weight'] ?? 0),
            'FuelUplift' => floatval($dbFlight['uplift_fuel'] ?? 0),
            'PaxTransit' => 0,
            'PaxJetway' => 0,
        ];
    }
    
    // If no flights found, show error
    if (empty($flights)) {
        die("No flights found for date {$input_date} (Gregorian: {$gregorianDate}).");
    }

    // IATA → ICAO map (only used for “flight_data_final_*.xlsx”)
    $iata_to_icao = [
        'ABD' => 'OIAW',
        'ACP' => 'OIAP',
        'AJK' => 'OIAJ',
        'AWZ' => 'OIAW',
        'AZD' => 'OIYY',
        'BND' => 'OIKB',
        'BUZ' => 'OIBB',
        'CKT' => 'OICK',
        'DEF' => 'OIDB',
        'FAZ' => 'OISF',
        'GBT' => 'OIMN',
        'IFN' => 'OIFM',
        'IKA' => 'OIIE',
        'KER' => 'OIKK',
        'KHD' => 'OICK',
        'KIH' => 'OIBK',
        'KSH' => 'OICC',
        'LRR' => 'OISL',
        'LRX' => 'OISY',
        'MHD' => 'OIMM',
        'MRX' => 'OIRM',
        'OMH' => 'OITR',
        'PFQ' => 'OITP',
        'PGU' => 'OIBP',
        'PYK' => 'OIIQ',
        'RAS' => 'OIGG',
        'RJN' => 'OIKR',
        'SDG' => 'OISD',
        'SRY' => 'OINZ',
        'SYZ' => 'OISS',
        'TBZ' => 'OITT',
        'THR' => 'OIII',
        'XBJ' => 'OIMB',
        'ZBR' => 'OIZB',
        'ZAH' => 'OIZH',
        'ACZ' => 'OIZC',
        'AFZ' => 'OIMF',
        'BDH' => 'OIBD',
        'BXR' => 'OIKM',
        'BSM' => 'OIBS',
        'HDM' => 'OIMJ',
        'IHR' => 'OISI',
        'JAR' => 'OISR',
        'NSH' => 'OINN',
        'QMJ' => 'OISO',
        'RZR' => 'OINR',
        'SYJ' => 'OISI',
        'TCX' => 'OITL',
        'YES' => 'OISY',
    ];

    // =====================================================================
    // FORM 1  →  “flight_data_final_YYYY-MM-DD.xlsx” (converted to real XLSX)
    // =====================================================================
    $filename1 = "flight_data_final_{$safe_input_date}.xlsx"; // *** NOW .xlsx ***

    $sheet1 = new Spreadsheet();
    $ws1 = $sheet1->getActiveSheet();

    // --- Header row ---
    $headers = [
        'ردیف',
        'شرکت هواپيمايي بهره بردار(کد ایکائو)',
        'شماره پرواز',
        'رجیستر',
        'تاريخ برنامه اي',
        'زمان برنامه اي',
        'مبدا(کد ایکائو)',
        'مقصد(کد ایکائو)',
        'وضعیت برنامه ای(0 یا 1)',
        'تاريخ واقعي',
        'زمان واقعی',
        'مسافر بزرگسال',
        'مسافر خردسال',
        'مسافر نوزاد',
        'بار هوايي',
        'تاريخ تاکسي',
        'زمان تاکسي',
        'علت تاخیر',
        'سوخت',
        'پست هوایی',
        'مسافر ترانزیت',
        'مسافر jet',
        'مسافر ترانسفر',
        'مسافر cip',
        'بار ترانزیت',
        'بار ترانسفر',
        'توضیحات',
    ];
    foreach ($headers as $i => $header) {
        $columnLetter = Coordinate::stringFromColumnIndex($i + 1);
        $ws1->setCellValue($columnLetter . '1', $header);
    }
    $ws1->getStyle('A1:AA1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD8E0F2');

    // --- Data rows ---
    $rowNumber = 2;
    $flightCount = 0;
    foreach ($flights as $flight) {
        $flightCount++;
        $date = $flight['Date'] ?? null;
        $std = $flight['STD'] ?? null;
        $takeoff = $flight['Takeoff'] ?? null;
        $chocksout = $flight['ChocksOut'] ?? null;

        $persianDate = $date ? gregorian_to_jalali(substr($date, 0, 4), substr($date, 5, 2), substr($date, 8, 2), '/') : '';
        $stdTime = $std ? date('H:i', strtotime($std) + 3 * 3600 + 30 * 60) : '';
        $takeoffTime = $takeoff ? date('H:i', strtotime($takeoff) + 3 * 3600 + 30 * 60) : '';
        $chocksoutDate = $chocksout ? gregorian_to_jalali(substr($chocksout, 0, 4), substr($chocksout, 5, 2), substr($chocksout, 8, 2), '/') : '';
        $chocksoutTime = $chocksout ? date('H:i', strtotime($chocksout) + 3 * 3600 + 30 * 60) : '';

        $from_icao = $iata_to_icao[$flight['FromAirportIATA']] ?? ($flight['FromAirportIATA'] ?? '');
        $to_icao = $iata_to_icao[$flight['ToAirportIATA']] ?? ($flight['ToAirportIATA'] ?? '');
        $baggage = $flight['BaggageWeight'] ?? 0;

        $rowData = [
            $rowNumber - 1,
            'RAI',
            $flight['FlightNumber'] ?? '',
            $flight['Register'] ?? '',
            $persianDate,
            $stdTime,
            $from_icao,
            $to_icao,
            0,
            $persianDate,
            $takeoffTime,
            $flight['PaxAdult'] ?? 0,
            $flight['PaxChild'] ?? 0,
            $flight['PaxInfant'] ?? 0,
            $baggage . 'k',
            $chocksoutDate,
            $chocksoutTime,
            'nil',
            $flight['FuelUplift'] ?? 0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            ''
        ];
        // Write data row using fromArray
        $ws1->fromArray([$rowData], null, 'A' . $rowNumber);
        $ws1->getStyle('A' . $rowNumber . ':AA' . $rowNumber)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        $rowNumber++;
    }

    foreach (range('A', 'AA') as $col)
        $ws1->getColumnDimension($col)->setAutoSize(true);
    
    // Debug: Check if any flights were processed
    if ($flightCount == 0) {
        // Add a message in row 2
        $ws1->setCellValue('A2', 'No flights found for this date');
        $ws1->mergeCells('A2:AA2');
    }
    
    (new Xlsx($sheet1))->save($filename1);

    // =====================================================================
    // FORM 2  →  “flight_summary_YYYY-MM-DD.xlsx”
    //      * D5/F5 dates with slashes
    //      * I5/J5 codes in IATA
    // =====================================================================
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ---- Title
    $sheet->mergeCells('C1:P2');
    $sheet->setCellValue('C1', "آمارروزانه پروازهای بازرگانی رایمون {$input_date}");
    $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle('C1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // ---- Header rows (unchanged)
    $sheet->mergeCells('A3:A4');
    $sheet->setCellValue('A3', 'ردیف');
    $sheet->setCellValue('B3', 'شماره');
    $sheet->setCellValue('B4', 'پرواز');
    $sheet->mergeCells('C3:C4');
    $sheet->setCellValue('C3', 'چارتر');
    $sheet->mergeCells('D3:E3');
    $sheet->setCellValue('D3', 'برنامه ای');
    $sheet->setCellValue('D4', 'تاریخ');
    $sheet->setCellValue('E4', 'ساعت');
    $sheet->mergeCells('F3:G3');
    $sheet->setCellValue('F3', 'واقعی');
    $sheet->setCellValue('F4', 'تاریخ');
    $sheet->setCellValue('G4', 'ساعت');
    $sheet->mergeCells('H3:H4');
    $sheet->setCellValue('H3', 'رجیستر');
    $sheet->mergeCells('I3:J3');
    $sheet->setCellValue('I3', 'فرودگاه');
    $sheet->setCellValue('I4', 'مبدا');
    $sheet->setCellValue('J4', 'مقصد');
    $sheet->mergeCells('K3:M3');
    $sheet->setCellValue('K3', 'تعداد مسافر');
    $sheet->setCellValue('K4', 'ADL');
    $sheet->setCellValue('L4', 'CHD');
    $sheet->setCellValue('M4', 'INF');
    $sheet->setCellValue('N3', 'مسافر');
    $sheet->setCellValue('N4', 'ترانزیت');
    $sheet->setCellValue('O3', 'مسافر');
    $sheet->setCellValue('O4', 'جت وی');
    $sheet->setCellValue('P3', 'سوخت تحویلی');
    $sheet->setCellValue('P4', '(لیتر)');
    $sheet->setCellValue('Q3', 'میزان بار');
    $sheet->setCellValue('Q4', 'kg');

    $sheet->getStyle('A1:Q1000')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3:Q4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');

    // ---- Data rows ----
    $rowNumber = 5;
    $flightCount2 = 0;
    foreach ($flights as $flight) {
        $flightCount2++;
        $date = $flight['Date'] ?? null;
        $std = $flight['STD'] ?? null;
        $takeoff = $flight['Takeoff'] ?? null;

        $programDate = $date ? str_replace('-', '/', substr($date, 0, 10)) : '0'; // yyyy/mm/dd
        $stdTime = $std ? date('H:i', strtotime($std) + 3 * 3600 + 30 * 60) : '0';
        $realDate = $takeoff ? str_replace('-', '/', substr($takeoff, 0, 10)) : '0';
        $realTime = $takeoff ? date('H:i', strtotime($takeoff) + 3 * 3600 + 30 * 60) : '0';

        $from_iata = $flight['FromAirportIATA'] ?? '0';
        $to_iata = $flight['ToAirportIATA'] ?? '0';

        $register = $flight['Register'] ?? '0';
        if (strpos($register, '-') !== false) {
            [, $suffix] = explode('-', $register);
            $register = 'EP-' . $suffix;
        }

        $baggage = $flight['BaggageWeight'] ?? 0;

        $rowData = [
            $rowNumber - 4,
            $flight['FlightNumber'] ?? '0',
            '',
            $programDate,
            $stdTime,
            $realDate,
            $realTime,
            $register,
            $from_iata,
            $to_iata,
            $flight['PaxAdult'] ?? 0,
            $flight['PaxChild'] ?? 0,
            $flight['PaxInfant'] ?? 0,
            $flight['PaxTransit'] ?? 0,
            $flight['PaxJetway'] ?? 0,
            $flight['FuelUplift'] ?? 0,
            $baggage . 'kg',
        ];
        // Write data row using fromArray
        $sheet->fromArray([$rowData], null, 'A' . $rowNumber);
        $sheet->getStyle('A' . $rowNumber . ':Q' . $rowNumber)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        $rowNumber++;
    }

    foreach (range('A', 'Q') as $col)
        $sheet->getColumnDimension($col)->setAutoSize(true);
    
    // Debug: Check if any flights were processed
    if ($flightCount2 == 0) {
        // Add a message in row 5
        $sheet->setCellValue('A5', 'No flights found for this date');
        $sheet->mergeCells('A5:Q5');
    }

    $filename2 = "flight_summary_{$safe_input_date}.xlsx";
    (new Xlsx($spreadsheet))->save($filename2);

    // =====================================================================
    // ZIP & DOWNLOAD
    // =====================================================================
    $zipFilename = "flight_reports_{$safe_input_date}.zip";
    $zip = new ZipArchive();

    if ($zip->open($zipFilename, ZipArchive::CREATE) === true) {
        $zip->addFile($filename1, $filename1);
        $zip->addFile($filename2, $filename2);
        $zip->close();
    }

    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$zipFilename\"");
    header('Cache-Control: max-age=0');
    readfile($zipFilename);

    // Clean-up temp files
    unlink($filename1);
    unlink($filename2);
    unlink($zipFilename);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Flight Reports - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Daily Flight Reports</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Generate and download daily flight reports in Excel format</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- Form Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 sm:p-8">
                        <form method="post" class="space-y-6">
                            <div>
                                <label for="persian_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-calendar-alt mr-2"></i>Persian Date (Example: 1404/01/31)
                                </label>
                                <input type="text" 
                                       name="persian_date" 
                                       id="persian_date" 
                                       required 
                                       placeholder="1404/01/31"
                                       class="w-full px-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-all">
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Enter the date in Persian calendar format (YYYY/MM/DD)</p>
                            </div>

                            <button type="submit" 
                                    name="submit"
                                    class="w-full bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800 transition-all flex items-center justify-center">
                                <i class="fas fa-download mr-2"></i>
                                Download Reports (ZIP)
                            </button>
                        </form>
                    </div>

                    <!-- Info Card -->
                    <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-3"></i>
                            <div class="text-sm text-blue-800 dark:text-blue-300">
                                <p class="font-semibold mb-1">Report Contents:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Flight Data Final (Excel format with Persian headers)</li>
                                    <li>Flight Summary (Excel format with Persian headers)</li>
                                </ul>
                                <p class="mt-2">Both reports will be packaged in a ZIP file for download.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>