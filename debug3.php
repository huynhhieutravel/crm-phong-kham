<?php
$data_v2 = [
    'subluxation' => [
        'cervical' => [
            'Axis (C2)' => ['L', 'R'],
            'Occiput_general' => ['1']
        ],
        'thoracic' => [
            'T1' => ['Interior']
        ]
    ]
];

$bone_history = [];
$date_str = '12/04';

$flatten = function($array, $prefix = '') use (&$flatten, &$bone_history, $date_str) {
    foreach ($array as $k => $v) {
        $new_prefix = $prefix === '' ? $k : $prefix . '||' . $k;
        // Check if $v is associative array
        $is_assoc = false;
        if (is_array($v)) {
            foreach(array_keys($v) as $key) {
                if (!is_int($key)) { $is_assoc = true; break; }
            }
        }
        
        if (is_array($v) && $is_assoc) {
            $flatten($v, $new_prefix);
        } else if (is_array($v)) { // Indexed array (e.g. ['L', 'R'])
            foreach ($v as $val) {
                $full_path = $new_prefix . '||' . ltrim(trim($val), '||');
                if (!isset($bone_history[$full_path])) $bone_history[$full_path] = [];
                $bone_history[$full_path][] = $date_str;
            }
        } else if ($v !== null && $v !== '') { // Scalar
            $full_path = $new_prefix . '||' . ltrim(trim($v), '||');
            if (!isset($bone_history[$full_path])) $bone_history[$full_path] = [];
            $bone_history[$full_path][] = $date_str;
        }
    }
};

$flatten(['subluxation' => $data_v2['subluxation']]);

echo "<pre>";
print_r($bone_history);

// Check renderV2Cb simulation
$name = "subluxation][cervical][Axis (C2)]";
$val = "R";
$path_str = str_replace(['][', ']', '['], '||', $name);
$path_str = trim($path_str, '||'); // "subluxation||cervical||Axis (C2)"
$full_path = $path_str . '||' . $val;

echo "Checking: $full_path\n";
if (isset($bone_history[$full_path])) {
    echo "DATES: " . implode(', ', $bone_history[$full_path]);
} else {
    echo "NOT FOUND";
}
