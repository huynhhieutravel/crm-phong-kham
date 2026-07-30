import json
import re

files = [
    'modules/medical/chiro_history_v2.php',
    'modules/medical/follow_up_v2.php'
]

with open('translate_dict_en.json', 'r') as f:
    data = json.load(f)

# Build a lookup table from (DE, VI) -> EN
lookup = {}
for item in data:
    lookup[(item['de'], item['vi'])] = item['en']

helper_function = """<?php
$current_lang_v2 = $_SESSION['lang'] ?? 'vi';
if (!function_exists('_t_v2')) {
    function _t_v2($vi, $de, $en) {
        global $current_lang_v2;
        if ($current_lang_v2 === 'de') return $de;
        if ($current_lang_v2 === 'en') return $en;
        return $vi;
    }
}
?>"""

for file in files:
    with open(file, 'r') as f:
        content = f.read()

    # Step 1: Inject helper function at the top, right after <?php session_start(); or similar
    # The files usually start with some HTML or PHP includes. Let's just insert it right after the first <?php
    if "function _t_v2" not in content:
        content = content.replace("<?php", "<?php\n" + helper_function.replace("<?php\n", "").replace("?>", ""), 1)

    # Step 2: Replace <?php echo $is_de ? 'DE' : 'VI'; ?>
    def replace_echo(match):
        de = match.group(1)
        vi = match.group(2)
        en = lookup.get((de, vi), de)  # Fallback to DE if EN not found somehow
        # Escape quotes just in case, though they shouldn't contain unescaped single quotes
        de_esc = de.replace("'", "\\'")
        vi_esc = vi.replace("'", "\\'")
        en_esc = en.replace("'", "\\'")
        return f"<?php echo _t_v2('{vi_esc}', '{de_esc}', '{en_esc}'); ?>"
        
    content = re.sub(r"<\?php echo \$is_de \? '([^']+)' : '([^']+)'; \?>", replace_echo, content)
    
    # Step 3: Replace $is_de ? 'DE' : 'VI'
    def replace_inline(match):
        de = match.group(1)
        vi = match.group(2)
        en = lookup.get((de, vi), de)
        de_esc = de.replace("'", "\\'")
        vi_esc = vi.replace("'", "\\'")
        en_esc = en.replace("'", "\\'")
        return f"_t_v2('{vi_esc}', '{de_esc}', '{en_esc}')"
        
    content = re.sub(r"\$is_de \? '([^']+)' : '([^']+)'", replace_inline, content)

    with open(file, 'w') as f:
        f.write(content)

print("Refactoring complete.")
