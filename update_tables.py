import re

file_path = "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/follow_up_v2.php"
with open(file_path, "r") as f:
    content = f.read()

# Replace upper header
content = content.replace(
    '<thead><tr><th width="20%"><?php echo _t_v2(\'T | P\', \'li | re\', \'L | R\'); ?></th><th><?php echo _t_v2(\'Cấu trúc / Khớp (Struktur / Gelenk)\', \'Struktur / Gelenk\', \'Structure / Joint\'); ?></th></tr></thead>',
    '<thead><tr><th width="15%"><?php echo _t_v2(\'T\', \'li\', \'L\'); ?></th><th width="15%"><?php echo _t_v2(\'P\', \'re\', \'R\'); ?></th><th><?php echo _t_v2(\'Cấu trúc / Khớp (Struktur / Gelenk)\', \'Struktur / Gelenk\', \'Structure / Joint\'); ?></th></tr></thead>'
)

# Replace upper standard loop
content = content.replace(
    '''                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("upper.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("upper.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.R", "1"); ?>>
                                    </div>
                                </td>''',
    '''                                <td><input type="checkbox" name="fu[upper][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("upper.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("upper.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("upper.$k.R", "1"); ?>></td>'''
)

# Replace upper ta
content = content.replace(
    '''                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][ta][L]" value="1" <?php echo checked_v("upper.ta.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][ta][R]" value="1" <?php echo checked_v("upper.ta.R", "1"); ?>>
                                    </div>
                                </td>''',
    '''                                <td><input type="checkbox" name="fu[upper][ta][L]" value="1" <?php echo checked_v("upper.ta.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][ta][R]" value="1" <?php echo checked_v("upper.ta.R", "1"); ?>></td>'''
)

# Replace upper ga
content = content.replace(
    '''                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][ga][L]" value="1" <?php echo checked_v("upper.ga.L", "1"); ?>>
                                        <span style="color:#94a3b8;">|</span>
                                        <input type="checkbox" name="fu[upper][ga][R]" value="1" <?php echo checked_v("upper.ga.R", "1"); ?>>
                                    </div>
                                </td>''',
    '''                                <td><input type="checkbox" name="fu[upper][ga][L]" value="1" <?php echo checked_v("upper.ga.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][ga][R]" value="1" <?php echo checked_v("upper.ga.R", "1"); ?>></td>'''
)

# Replace upper mtc
content = content.replace(
    '''                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][mtc][L]" value="1" <?php echo checked_v("upper.mtc.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[upper][mtc][R]" value="1" <?php echo checked_v("upper.mtc.R", "1"); ?>>
                                    </div>
                                </td>''',
    '''                                <td><input type="checkbox" name="fu[upper][mtc][L]" value="1" <?php echo checked_v("upper.mtc.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][mtc][R]" value="1" <?php echo checked_v("upper.mtc.R", "1"); ?>></td>'''
)

# Replace upper thumb
content = content.replace(
    '''                                <td>
                                    <div style="display:flex; justify-content:center; gap:0.5rem;">
                                        <input type="checkbox" name="fu[upper][thumb][L]" value="1" <?php echo checked_v("upper.thumb.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[upper][thumb][R]" value="1" <?php echo checked_v("upper.thumb.R", "1"); ?>>
                                    </div>
                                </td>''',
    '''                                <td><input type="checkbox" name="fu[upper][thumb][L]" value="1" <?php echo checked_v("upper.thumb.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[upper][thumb][R]" value="1" <?php echo checked_v("upper.thumb.R", "1"); ?>></td>'''
)

# Replace lower usg
content = content.replace(
    '''                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][usg][L]" value="1" <?php echo checked_v("lower.usg.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][usg][R]" value="1" <?php echo checked_v("lower.usg.R", "1"); ?>></div></td>''',
    '''                                <td><input type="checkbox" name="fu[lower][usg][L]" value="1" <?php echo checked_v("lower.usg.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][usg][R]" value="1" <?php echo checked_v("lower.usg.R", "1"); ?>></td>'''
)

# Replace lower osg
content = content.replace(
    '''                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][osg][L]" value="1" <?php echo checked_v("lower.osg.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][osg][R]" value="1" <?php echo checked_v("lower.osg.R", "1"); ?>></div></td>''',
    '''                                <td><input type="checkbox" name="fu[lower][osg][L]" value="1" <?php echo checked_v("lower.osg.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][osg][R]" value="1" <?php echo checked_v("lower.osg.R", "1"); ?>></td>'''
)

# Replace lower standard loop
content = content.replace(
    '''                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("lower.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("lower.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.R", "1"); ?>></div></td>''',
    '''                                <td><input type="checkbox" name="fu[lower][<?php echo $k; ?>][L]" value="1" class="<?php echo checked_prev("lower.$k.L", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][<?php echo $k; ?>][R]" value="1" class="<?php echo checked_prev("lower.$k.R", "1") ? 'was-checked-prev' : ''; ?>" <?php echo checked_v("lower.$k.R", "1"); ?>></td>'''
)

# Replace lower cuneiforme
content = content.replace(
    '''                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][cuneiforme][L]" value="1" <?php echo checked_v("lower.cuneiforme.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][cuneiforme][R]" value="1" <?php echo checked_v("lower.cuneiforme.R", "1"); ?>></div></td>''',
    '''                                <td><input type="checkbox" name="fu[lower][cuneiforme][L]" value="1" <?php echo checked_v("lower.cuneiforme.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][cuneiforme][R]" value="1" <?php echo checked_v("lower.cuneiforme.R", "1"); ?>></td>'''
)

# Replace lower mtt
content = content.replace(
    '''                                <td><div style="display:flex; justify-content:center; gap:0.5rem;"><input type="checkbox" name="fu[lower][mtt][L]" value="1" <?php echo checked_v("lower.mtt.L", "1"); ?>> <span style="color:#94a3b8;">|</span> <input type="checkbox" name="fu[lower][mtt][R]" value="1" <?php echo checked_v("lower.mtt.R", "1"); ?>></div></td>''',
    '''                                <td><input type="checkbox" name="fu[lower][mtt][L]" value="1" <?php echo checked_v("lower.mtt.L", "1"); ?>></td>
                                <td><input type="checkbox" name="fu[lower][mtt][R]" value="1" <?php echo checked_v("lower.mtt.R", "1"); ?>></td>'''
)

with open(file_path, "w") as f:
    f.write(content)

print("Replacement complete.")
