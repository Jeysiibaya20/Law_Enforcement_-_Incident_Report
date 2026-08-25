<?php
/**
 * Quezon City District and Barangay API
 * Provides complete structured address dataset for dropdowns and autocomplete
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$barangaysByDistrict = [
    '1' => [
        'Vasra', 'Bagong Pag-asa', 'Sto. Cristo', 'Project 6', 'Ramon Magsaysay', 'Alicia',
        'Bahay Toro', 'Katipunan', 'San Antonio', 'Veterans Village', 'Bungad', 'Phil-Am',
        'West Triangle', 'Sta. Cruz', 'Nayong Kanluran', 'Paltok', 'Paraiso', 'Mariblo',
        'Damayan', 'Del Monte', 'Masambong', 'Talayan', 'Sto. Domingo', 'Siena',
        'St. Peter', 'San Jose', 'Manresa', 'Damar', 'Pag-ibig sa Nayon', 'Balingasa',
        'Sta. Teresita', 'San Isidro Labrador', 'Paang Bundok', 'Salvacion', 'N.S Amoranto',
        'Maharlikha', 'Lourdes'
    ],
    '2' => [
        'Bagong Silangan', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'
    ],
    '3' => [
        'Silangan', 'Socorro', 'E. Rodriguez', 'West Kamias', 'East Kamias', 'Quirino 2-A',
        'Quirino 2-B', 'Quirino 2-C', 'Quirino 3-A', 'Claro (Quirino 3-B)', 'Duyan-Duyan',
        'Amihan', 'Matandang Balara', 'Pansol', 'Loyola Heights', 'San Roque', 'Mangga',
        'Masagana', 'Villa Maria Clara', 'Bayanihan', 'Camp Aguinaldo', 'White Plains',
        'Libis', 'Ugong Norte', 'Bagumbayan', 'Blue Ridge A', 'Blue Ridge B', 'St. Ignatius',
        'Milagrosa', 'Escopa I', 'Escopa II', 'Escopa III', 'Escopa IV', 'Marilag',
        'Bagumbuhay', 'Tagumpay', 'Dioquino Zobel'
    ],
    '4' => [
        'Sacred Heart', 'Laging Handa', 'Obrero', 'Paligsahan', 'Roxas', 'Kamuning',
        'South Triangle', 'Pinagkaisahan', 'Immaculate Concepcion', 'San Martin De Porres',
        'Kaunlaran', 'Bagong Lipunan ng Crame', 'Horseshoe', 'Valencia', 'Tatalon',
        'Kalusugan', 'Kristong Hari', 'Damayang Lagi', 'Mariana', 'Doña Imelda', 'Santol',
        'Sto. Niño', 'San Isidro Galas', 'Doña Aurora', 'Don Manuel', 'Doña Josefa',
        'UP Village', 'Old Capitol Site', 'UP Campus', 'San Vicente', 'Teachers Village East',
        'Teachers Village West', 'Central', 'Pinyahan', 'Malaya', 'Sikatuna Village', 'Botocan',
        'Krus Na Ligas'
    ],
    '5' => [
        'Bagbag', 'Capri', 'Greater Lagro', 'Gulod', 'Kaligayahan', 'Nagkaisang Nayon',
        'North Fairview', 'Novaliches Proper', 'Pasong Putik Proper', 'San Agustin',
        'San Bartolome', 'Sta. Lucia', 'Sta. Monica', 'Fairview'
    ],
    '6' => [
        'Apolonio Samson', 'Baesa', 'Balon Bato', 'Culiat', 'New Era', 'Pasong Tamo',
        'Sangandaan', 'Tandang Sora', 'Unang Sigaw', 'Sauyo', 'Talipapa'
    ]
];

$district = $_GET['district'] ?? null;

if ($district && isset($barangaysByDistrict[$district])) {
    echo json_encode([
        'success' => true,
        'district' => $district,
        'barangays' => $barangaysByDistrict[$district],
        'count' => count($barangaysByDistrict[$district])
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Return all
$allBarangays = [];
foreach ($barangaysByDistrict as $distNum => $bList) {
    foreach ($bList as $bName) {
        $allBarangays[] = [
            'barangay' => $bName,
            'district' => $distNum,
            'city' => 'Quezon City'
        ];
    }
}

echo json_encode([
    'success' => true,
    'districts' => array_keys($barangaysByDistrict),
    'barangays_by_district' => $barangaysByDistrict,
    'all_barangays' => $allBarangays,
    'total_barangays' => count($allBarangays)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
