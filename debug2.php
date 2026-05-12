<?php
$name = 'subluxation][cervical][Axis (C2)]';
$path_str = str_replace(['][', ']', '['], '||', $name);
$keys = array_filter(explode('||', $path_str));

print_r($keys);

$prev_data = [
    'subluxation' => [
        'cervical' => [
            'Axis (C2)' => 'R'
        ]
    ]
];

$curr_prev = $prev_data;
foreach ($keys as $k) {
    if (is_array($curr_prev) && isset($curr_prev[$k])) {
        $curr_prev = $curr_prev[$k];
    } else {
        $curr_prev = [];
        break;
    }
}
$prev_arr = is_array($curr_prev) ? $curr_prev : ($curr_prev ? (array)$curr_prev : []);

print_r($prev_arr);
