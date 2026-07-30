import re

file_path = "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/follow_up_v2.php"
with open(file_path, "r") as f:
    content = f.read()

# Viêm lồi cầu ngoài TA
content = content.replace('''<td class="label-col">
                                    <?php echo _t_v2('Viêm lồi cầu ngoài (Tennisarm - TA):', 'Tennisarm (TA):', 'Tennis Elbow (TA):'); ?> 
                                    <span style="font-weight: 400; font-size: 0.75rem; margin-left: 0.5rem;">
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sau" <?php echo checked_v("upper.ta.opt", "sau"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sauben" <?php echo checked_v("upper.ta.opt", "sauben"); ?>> sau-bên (post-lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="ben" <?php echo checked_v("upper.ta.opt", "ben"); ?>> bên (lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="truoc" <?php echo checked_v("upper.ta.opt", "truoc"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label>
                                    </span>
                                </td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Viêm lồi cầu ngoài (Tennisarm - TA):', 'Tennisarm (TA):', 'Tennis Elbow (TA):'); ?></div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sau" <?php echo checked_v("upper.ta.opt", "sau"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="sauben" <?php echo checked_v("upper.ta.opt", "sauben"); ?>> sau-bên (post-lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="ben" <?php echo checked_v("upper.ta.opt", "ben"); ?>> bên (lat)</label>
                                        <label><input type="checkbox" name="fu[upper][ta][opt][]" value="truoc" <?php echo checked_v("upper.ta.opt", "truoc"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label>
                                    </div>
                                </td>''')

# Viêm lồi cầu trong GA
content = content.replace('''<td class="label-col">
                                    <?php echo _t_v2('Viêm lồi cầu trong (Golferarm - GA):', 'Golferarm (GA):', 'Golfer\\'s Elbow (GA):'); ?> 
                                    <span style="font-weight: 400; font-size: 0.75rem; margin-left: 0.5rem;">
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="trong" <?php echo checked_v("upper.ga.opt", "trong"); ?>> <?php echo _t_v2('trong (med)', 'med', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="ngoai" <?php echo checked_v("upper.ga.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (lat)', 'lat', 'Lateral'); ?></label>
                                    </span>
                                </td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Viêm lồi cầu trong (Golferarm - GA):', 'Golferarm (GA):', 'Golfer\\'s Elbow (GA):'); ?></div>
                                    <div style="display: flex; gap: 1rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="trong" <?php echo checked_v("upper.ga.opt", "trong"); ?>> <?php echo _t_v2('trong (med)', 'med', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][ga][opt][]" value="ngoai" <?php echo checked_v("upper.ga.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (lat)', 'lat', 'Lateral'); ?></label>
                                    </div>
                                </td>''')

# Xương bàn tay MTC
content = content.replace('''<td class="label-col"><?php echo _t_v2('Xương bàn tay (Metacarpalia):', 'Metacarpalia:', 'Metacarpals:'); ?> <span style="font-weight:400; font-size:0.75rem;">
                                    <?php foreach([2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[upper][mtc][opt][]" value="<?php echo $v; ?>" <?php echo checked_v("upper.mtc.opt", $v); ?>> <?php echo $v; ?></label> <?php endforeach; ?>
                                </span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương bàn tay (Metacarpalia):', 'Metacarpalia:', 'Metacarpals:'); ?></div>
                                    <div style="display: flex; gap: 1rem; font-weight: 400; font-size: 0.75rem;">
                                        <?php foreach([2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[upper][mtc][opt][]" value="<?php echo $v; ?>" <?php echo checked_v("upper.mtc.opt", $v); ?>> <?php echo $v; ?></label><?php endforeach; ?>
                                    </div>
                                </td>''')

# Ngón cái
content = content.replace('''<td class="label-col"><?php echo _t_v2('Ngón cái (Daumen):', 'Daumen:', 'Thumb:'); ?> <span style="font-weight:400; font-size:0.75rem;">
                                    <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="sg" <?php echo checked_v("upper.thumb.opt", "sg"); ?>> <?php echo _t_v2('khớp yên (Sattelgelenk)', 'Sattelgelenk', 'Saddle Joint'); ?></label>
                                    <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="gg" <?php echo checked_v("upper.thumb.opt", "gg"); ?>> <?php echo _t_v2('khớp bàn ngón (Grundgelenk)', 'Grundgelenk', 'Metacarpophalangeal Joint'); ?></label>
                                </span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Ngón cái (Daumen):', 'Daumen:', 'Thumb:'); ?></div>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="sg" <?php echo checked_v("upper.thumb.opt", "sg"); ?>> <?php echo _t_v2('khớp yên (Sattelgelenk)', 'Sattelgelenk', 'Saddle Joint'); ?></label>
                                        <label><input type="checkbox" name="fu[upper][thumb][opt][]" value="gg" <?php echo checked_v("upper.thumb.opt", "gg"); ?>> <?php echo _t_v2('khớp bàn ngón (Grundgelenk)', 'Grundgelenk', 'Metacarpophalangeal Joint'); ?></label>
                                    </div>
                                </td>''')

# USG
content = content.replace('''<td class="label-col"><?php echo _t_v2('Khớp cổ chân dưới (USG):', 'USG:', 'Lower Ankle Joint (USG):'); ?> <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][usg][opt][]" value="sup" <?php echo checked_v("lower.usg.opt", "sup"); ?>> <?php echo _t_v2('ngửa (sup)', 'sup', 'Supination (sup)'); ?></label> <label><input type="checkbox" name="fu[lower][usg][opt][]" value="pron" <?php echo checked_v("lower.usg.opt", "pron"); ?>> <?php echo _t_v2('sấp (pron)', 'pron', 'Pronation (pron)'); ?></label></span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Khớp cổ chân dưới (USG):', 'USG:', 'Lower Ankle Joint (USG):'); ?></div>
                                    <div style="display: flex; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][usg][opt][]" value="sup" <?php echo checked_v("lower.usg.opt", "sup"); ?>> <?php echo _t_v2('ngửa (sup)', 'sup', 'Supination (sup)'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][usg][opt][]" value="pron" <?php echo checked_v("lower.usg.opt", "pron"); ?>> <?php echo _t_v2('sấp (pron)', 'pron', 'Pronation (pron)'); ?></label>
                                    </div>
                                </td>''')

# OSG
content = content.replace('''<td class="label-col"><?php echo _t_v2('Khớp cổ chân trên (OSG):', 'OSG:', 'Upper Ankle Joint (OSG):'); ?> <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][osg][opt][]" value="post" <?php echo checked_v("lower.osg.opt", "post"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label> <label><input type="checkbox" name="fu[lower][osg][opt][]" value="ant" <?php echo checked_v("lower.osg.opt", "ant"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label></span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Khớp cổ chân trên (OSG):', 'OSG:', 'Upper Ankle Joint (OSG):'); ?></div>
                                    <div style="display: flex; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][osg][opt][]" value="post" <?php echo checked_v("lower.osg.opt", "post"); ?>> <?php echo _t_v2('sau (post)', 'post', 'Posterior'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][osg][opt][]" value="ant" <?php echo checked_v("lower.osg.opt", "ant"); ?>> <?php echo _t_v2('trước (ant)', 'ant', 'Anterior'); ?></label>
                                    </div>
                                </td>''')

# Cuneiforme
content = content.replace('''<td class="label-col"><?php echo _t_v2('Xương chêm (Ossa cuneiformia):', 'Ossa cuneiformia:', 'Cuneiform Bones:'); ?> <span style="font-weight:400; font-size:0.75rem;"><label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="trong" <?php echo checked_v("lower.cuneiforme.opt", "trong"); ?>> <?php echo _t_v2('trong (mediale)', 'mediale', 'Medial'); ?></label> <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="giua" <?php echo checked_v("lower.cuneiforme.opt", "giua"); ?>> <?php echo _t_v2('giữa (intermedium)', 'intermedium', 'Intermediate'); ?></label> <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="ngoai" <?php echo checked_v("lower.cuneiforme.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (laterale)', 'laterale', 'Lateral'); ?></label></span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương chêm (Ossa cuneiformia):', 'Ossa cuneiformia:', 'Cuneiform Bones:'); ?></div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="trong" <?php echo checked_v("lower.cuneiforme.opt", "trong"); ?>> <?php echo _t_v2('trong (mediale)', 'mediale', 'Medial'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="giua" <?php echo checked_v("lower.cuneiforme.opt", "giua"); ?>> <?php echo _t_v2('giữa (intermedium)', 'intermedium', 'Intermediate'); ?></label>
                                        <label><input type="checkbox" name="fu[lower][cuneiforme][opt][]" value="ngoai" <?php echo checked_v("lower.cuneiforme.opt", "ngoai"); ?>> <?php echo _t_v2('ngoài (laterale)', 'laterale', 'Lateral'); ?></label>
                                    </div>
                                </td>''')

# MTT
content = content.replace('''<td class="label-col"><?php echo _t_v2('Xương bàn chân (Metatarsalia - MTT):', 'Metatarsalia (MTT):', 'Metatarsals (MTT):'); ?> <span style="font-weight:400; font-size:0.75rem;">
                                    <?php foreach([1,2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[lower][mtt][num][]" value="<?php echo $v; ?>" <?php echo checked_v("lower.mtt.num", $v); ?>><?php echo $v; ?></label> <?php endforeach; ?> - 
                                    <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="mu" <?php echo checked_v("lower.mtt.opt", "mu"); ?>> <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label> <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="gan" <?php echo checked_v("lower.mtt.opt", "gan"); ?>> <?php echo _t_v2('gan (plantar)', 'plantar', 'Plantar'); ?></label>
                                </span></td>''',
'''<td class="label-col">
                                    <div style="margin-bottom: 0.5rem;"><?php echo _t_v2('Xương bàn chân (Metatarsalia - MTT):', 'Metatarsalia (MTT):', 'Metatarsals (MTT):'); ?></div>
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; font-weight: 400; font-size: 0.75rem;">
                                        <div style="display: flex; gap: 1rem;">
                                            <?php foreach([1,2,3,4,5] as $v): ?><label><input type="checkbox" name="fu[lower][mtt][num][]" value="<?php echo $v; ?>" <?php echo checked_v("lower.mtt.num", $v); ?>><?php echo $v; ?></label><?php endforeach; ?>
                                        </div>
                                        <span style="color:#cbd5e1;">|</span>
                                        <div style="display: flex; gap: 1rem;">
                                            <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="mu" <?php echo checked_v("lower.mtt.opt", "mu"); ?>> <?php echo _t_v2('mu (dorsal)', 'dorsal', 'Dorsal'); ?></label>
                                            <label><input type="checkbox" name="fu[lower][mtt][opt][]" value="gan" <?php echo checked_v("lower.mtt.opt", "gan"); ?>> <?php echo _t_v2('gan (plantar)', 'plantar', 'Plantar'); ?></label>
                                        </div>
                                    </div>
                                </td>''')

with open(file_path, "w") as f:
    f.write(content)

print("Alignment fixed.")
