import os

files = [
    "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/chiro_history_v2.php",
    "/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/pathologie_v2.php"
]

for file_path in files:
    if not os.path.exists(file_path): continue
    with open(file_path, "r") as f:
        content = f.read()

    # TMJ section replacement
    tmj_old = '''<div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;"><label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc'); ?>> <?php echo _t_v2('lục cục', 'Knacken', 'Cracking'); ?></label> <label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;"><label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau'); ?>> <?php echo _t_v2('đau', 'Schmerzen', 'Pain'); ?></label> <label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;"><label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc'); ?>> <?php echo _t_v2('hạn chế vận động', 'eingeschränkt', 'Restricted Movement'); ?></label> <label style="margin-bottom: 0; margin-right: 0.5rem;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label> <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label></div>
                                    </div>'''
    tmj_new = '''<div style="display: grid; grid-template-columns: max-content auto auto; gap: 0.6rem 1rem; align-items: center;">
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc'); ?>> <?php echo _t_v2('lục cục', 'Knacken', 'Cracking'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="luc_cuc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'luc_cuc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>

                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau'); ?>> <?php echo _t_v2('đau', 'Schmerzen', 'Pain'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="dau_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'dau_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>

                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc'); ?>> <?php echo _t_v2('hạn chế vận động', 'eingeschränkt', 'Restricted Movement'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_p" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_p'); ?>> <?php echo _t_v2('Phải', 're', 'R'); ?></label>
                                        <label style="margin-bottom: 0;"><input type="checkbox" name="exam[akt_path][tmj][trieuchung][]" value="hc_t" <?php echo checked_v('exam[akt_path][tmj][trieuchung]', 'hc_t'); ?>> <?php echo _t_v2('Trái', 'li', 'L'); ?></label>
                                    </div>'''
    content = content.replace(tmj_old, tmj_new)

    # Kopf frequency section replacement (with _t_v2)
    kopf_old_chiro = '''<div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> <?php echo _t_v2('hằng ngày', 'täglich', 'Daily'); ?></label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> <?php echo _t_v2('2-3 lần/tuần', '2-3x/W', '2-3x/Week'); ?></label></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> <?php echo _t_v2('1 lần/tuần', '1x/W', '1x/Week'); ?></label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_thang'); ?>> <?php echo _t_v2('2-3 lần/tháng', '2-3x/M', '2-3x/Month'); ?></label></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> <?php echo _t_v2('1 lần/tháng', '1x/M', '1x/Month'); ?></label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="chi_tuy_luc" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'chi_tuy_luc'); ?>> <?php echo _t_v2('chỉ tùy lúc', 'nur ab und zu', 'Occasionally'); ?></label></div>
                                    </div>'''
    kopf_new_chiro = '''<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1rem; margin-top: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> <?php echo _t_v2('hằng ngày', 'täglich', 'Daily'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> <?php echo _t_v2('2-3 lần/tuần', '2-3x/W', '2-3x/Week'); ?></label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> <?php echo _t_v2('1 lần/tuần', '1x/W', '1x/Week'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_thang'); ?>> <?php echo _t_v2('2-3 lần/tháng', '2-3x/M', '2-3x/Month'); ?></label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> <?php echo _t_v2('1 lần/tháng', '1x/M', '1x/Month'); ?></label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="chi_tuy_luc" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'chi_tuy_luc'); ?>> <?php echo _t_v2('chỉ tùy lúc', 'nur ab und zu', 'Occasionally'); ?></label>
                                    </div>'''
    content = content.replace(kopf_old_chiro, kopf_new_chiro)
    
    # Kopf frequency section replacement (old version in pathologie_v2.php, without _t_v2)
    kopf_old_path = '''<div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> hằng ngày</label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> 2-3 lần/tuần</label></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> 1 lần/tuần</label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_thang'); ?>> 2-3 lần/tháng</label></div>
                                        <div style="display: flex; gap: 1rem;"><label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> 1 lần/tháng</label> <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="chi_tuy_luc" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'chi_tuy_luc'); ?>> chỉ tùy lúc</label></div>
                                    </div>'''
    kopf_new_path = '''<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1rem; margin-top: 0.5rem;">
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="hang_ngay" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'hang_ngay'); ?>> hằng ngày</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_tuan'); ?>> 2-3 lần/tuần</label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_tuan" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_tuan'); ?>> 1 lần/tuần</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="2_3_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '2_3_lan_thang'); ?>> 2-3 lần/tháng</label>
                                        
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="1_lan_thang" <?php echo checked_v('exam[akt_path][kopf][tansuat]', '1_lan_thang'); ?>> 1 lần/tháng</label>
                                        <label><input type="checkbox" name="exam[akt_path][kopf][tansuat][]" value="chi_tuy_luc" <?php echo checked_v('exam[akt_path][kopf][tansuat]', 'chi_tuy_luc'); ?>> chỉ tùy lúc</label>
                                    </div>'''
    content = content.replace(kopf_old_path, kopf_new_path)

    with open(file_path, "w") as f:
        f.write(content)

print("Grid alignment applied to chiro_history_v2.php and pathologie_v2.php.")
