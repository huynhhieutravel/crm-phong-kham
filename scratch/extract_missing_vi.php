<?php
$vi_content = file_get_contents('lang/vi.php');
// Simple extraction of values from vi.php
preg_match_all('/=>\s*\'(.*?)\',/', $vi_content, $matches_vi1);
preg_match_all('/=>\s*"(.*?)",/', $vi_content, $matches_vi2);
$vi_values = array_merge($matches_vi1[1] ?? [], $matches_vi2[1] ?? []);

$view_content = file_get_contents('modules/medical/view_form.php');
preg_match_all("/__\(\s*'([^']+)'\s*(?:,[^)]+)?\)/", $view_content, $matches1);
preg_match_all('/__\(\s*"([^"]+)"\s*(?:,[^)]+)?\)/', $view_content, $matches2);

$found_strings = array_unique(array_merge($matches1[1] ?? [], $matches2[1] ?? []));
$missing = [];
foreach ($found_strings as $str) {
    if (!in_array($str, $vi_values) && !preg_match('/^[a-z0-9_.]+$/', $str)) {
        // Assume if it has spaces or uppercase/unicode, it's a native string to be reverse-looked up
        $missing[] = $str;
    }
}
echo json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
