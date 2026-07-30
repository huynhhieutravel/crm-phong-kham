import re

files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
]

new_helper = """<?php
if (!function_exists('_t_v2')) {
    function _t_v2($vi, $de, $en) {
        $lang = 'vi';
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en', 'de'])) {
            $lang = $_GET['lang'];
        } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['vi', 'en', 'de'])) {
            $lang = $_SESSION['lang'];
        }
        
        if ($lang === 'de') return $de;
        if ($lang === 'en') return $en;
        return $vi;
    }
}
"""

old_helper = r"<\?php\n\$current_lang_v2 = \$_SESSION\['lang'\] \?\? 'vi';\nif \(!function_exists\('_t_v2'\)\) \{\n    function _t_v2\(\$vi, \$de, \$en\) \{\n        global \$current_lang_v2;\n        if \(\$current_lang_v2 === 'de'\) return \$de;\n        if \(\$current_lang_v2 === 'en'\) return \$en;\n        return \$vi;\n    \}\n\}\n"

for file in files:
    with open(file, 'r') as f:
        content = f.read()
    
    content = re.sub(old_helper, new_helper, content)
    
    with open(file, 'w') as f:
        f.write(content)

print("Fixed helper function.")
