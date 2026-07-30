import sys

filename = 'modules/medical/follow_up_v2.php'
with open(filename, 'r') as f:
    content = f.read()

replacements = {
    '<th width="15%">T (li)</th>': '<th width="15%"><?php echo $is_de ? \'li\' : \'T\'; ?></th>',
    '<th width="15%">P (re)</th>': '<th width="15%"><?php echo $is_de ? \'re\' : \'P\'; ?></th>',
    '<th width="30%">Đốt sống (Wirbel)</th>': '<th width="30%"><?php echo $is_de ? \'Wirbel\' : \'Đốt sống\'; ?></th>',
    '<th width="20%">Trước (Ventral)</th>': '<th width="20%"><?php echo $is_de ? \'Ventral\' : \'Trước\'; ?></th>',
    '<th width="20%">Sau (Dorsal)</th>': '<th width="20%"><?php echo $is_de ? \'Dorsal\' : \'Sau\'; ?></th>',
}

for k, v in replacements.items():
    if k in content:
        content = content.replace(k, v)
        print(f"Replaced: {k[:30]}...")
    else:
        print(f"NOT FOUND: {k}")

with open(filename, 'w') as f:
    f.write(content)
