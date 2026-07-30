import json

def checked_v(path, val):
    return f"<?php echo checked_v('{path}', '{val}'); ?>"

def get_v(path):
    return f"<?php echo get_v('{path}'); ?>"

def generate_checkbox(name, label, value):
    return f'<label><input type="checkbox" name="{name}[]" value="{value}" {checked_v(name, value)}> {label}</label>'

def generate_radio(name, label, value):
    return f'<label><input type="radio" name="{name}" value="{value}" {checked_v(name, value)}> {label}</label>'

def generate_input(name, placeholder=""):
    return f'<input type="text" name="{name}" placeholder="{placeholder}" value="{get_v(name)}">'

html = """
            <!-- ============================================== -->
            <!-- PHẦN 2: BỆNH LÝ HIỆN TẠI (AKTUELLE PATHOLOGIE) -->
            <!-- ============================================== -->
            <div style="margin-top: 3.5rem; margin-bottom: 2rem; border-top: 1px solid #e2e8f0; padding-top: 2.5rem;">
                <h3 style="font-weight: 800; font-size: 1.15rem; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-stethoscope" style="color: #3b82f6;"></i> PHẦN 2: BỆNH LÝ HIỆN TẠI (AKTUELLE PATHOLOGIE)
                </h3>

                <!-- Tabs Navigation -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; background: rgba(248,250,252,0.8); padding: 0.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <button type="button" class="tab-btn active" data-target="tab-kopf">Đầu (Kopf)</button>
                    <button type="button" class="tab-btn" data-target="tab-hws">Cột sống cổ</button>
                    <button type="button" class="tab-btn" data-target="tab-bws">CS ngực</button>
                    <button type="button" class="tab-btn" data-target="tab-lws">CS thắt lưng</button>
                    <button type="button" class="tab-btn" data-target="tab-schulter">Vai</button>
                    <button type="button" class="tab-btn" data-target="tab-obere">Chi trên</button>
                    <button type="button" class="tab-btn" data-target="tab-untere">Chi dưới</button>
                    <button type="button" class="tab-btn" data-target="tab-fuss">Bàn & cổ chân</button>
                </div>

                <style>
                    .tab-btn { background: transparent; border: none; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: #64748b; cursor: pointer; transition: all 0.2s; }
                    .tab-btn:hover { background: #f1f5f9; color: #1d1d1f; }
                    .tab-btn.active { background: white; color: #3b82f6; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
                    .tab-content { display: none; background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
                    .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
                    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
                    .path-table { width: 100%; border-collapse: collapse; }
                    .path-table th { background: #f8fafc; padding: 0.8rem 1rem; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #475569; border: 1px solid #e2e8f0; }
                    .path-table td { padding: 1rem; border: 1px solid #e2e8f0; vertical-align: top; font-size: 0.9rem; color: #1e293b; }
                    .path-table strong { color: #0f172a; display: block; margin-bottom: 0.5rem; font-weight: 700; font-size: 0.95rem; }
                    .path-table label { display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; margin-bottom: 0.5rem; margin-right: 0.8rem; }
                    .path-table input[type="checkbox"], .path-table input[type="radio"] { transform: scale(1.15); margin: 0; cursor: pointer; accent-color: #3b82f6; }
                    .path-table input[type="text"] { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.85rem; outline: none; transition: border 0.2s; background: #f8fafc; }
                    .path-table input[type="text"]:focus { border-color: #3b82f6; background: white; }
                    .path-table input[type="number"] { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.3rem 0.5rem; font-size: 0.85rem; outline: none; transition: border 0.2s; background: #f8fafc; width: 60px; text-align: center; }
                    .path-table input[type="number"]:focus { border-color: #3b82f6; background: white; }
                </style>
"""

# Tab 1: Đầu
html += """
                <div id="tab-kopf" class="tab-content active">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Triệu chứng / Vùng</th>
                                <th style="width: 40%;">Tính chất / Vị trí</th>
                                <th style="width: 40%;">Tần suất / Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Đầu</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        {kopf_vitri_sau}
                                        {kopf_vitri_truoc}
                                        <div>một bên: {kopf_vitri_p} {kopf_vitri_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 0.5rem; font-weight: 600;">Tần suất:</div>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem;">{kopf_tan_suat_hang_ngay} {kopf_tan_suat_23}</div>
                                        <div style="display: flex; gap: 1rem;">{kopf_tan_suat_1tuan} {kopf_tan_suat_1thang}</div>
                                        {kopf_tan_suat_thinh_thoang}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Chóng mặt</strong></td>
                                <td>{chong_mat_input}</td>
                                <td>{chong_mat_ts}</td>
                            </tr>
                            <tr>
                                <td><strong>Ù tai</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        {utai_tieng_u}
                                        {utai_tieng_rit}
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">Từ khi nào: {utai_tu_khi_nao}</div>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">{utai_ngay} {utai_tuan} {utai_thang} {utai_nam}</div>
                                    <div style="display: flex; flex-direction: column;">
                                        {utai_lien_tuc}
                                        {utai_luc_nhieu}
                                        {utai_het_han}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khớp thái dương hàm<br>(TMJ)</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div>lục cục: {tmj_luc_cuc_p} {tmj_luc_cuc_t}</div>
                                        <div>đau: {tmj_dau_p} {tmj_dau_t}</div>
                                        <div>hạn chế vận động: {tmj_hc_p} {tmj_hc_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">Từ khi nào: {tmj_tu_khi_nao}</div>
                                    <div style="display: flex; flex-direction: column;">
                                        {tmj_dang_nieng}
                                        {tmj_da_tung}
                                        <div style="display: flex; gap: 1rem;">{tmj_cau_rang} {tmj_mao_rang}</div>
                                        {tmj_cay_ghep}
                                        {tmj_dieu_tri_tuy}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    kopf_vitri_sau=generate_checkbox('exam[akt_path][kopf][vitri]', 'phía sau cột sống cổ', 'sau_csc'),
    kopf_vitri_truoc=generate_checkbox('exam[akt_path][kopf][vitri]', 'phía trước (trán)', 'truoc_tran'),
    kopf_vitri_p=generate_checkbox('exam[akt_path][kopf][vitri]', 'P', 'mot_ben_p'),
    kopf_vitri_t=generate_checkbox('exam[akt_path][kopf][vitri]', 'T', 'mot_ben_t'),
    
    kopf_tan_suat_hang_ngay=generate_checkbox('exam[akt_path][kopf][tansuat]', 'hằng ngày', 'hang_ngay'),
    kopf_tan_suat_23=generate_checkbox('exam[akt_path][kopf][tansuat]', '2-3 lần/tuần', '2_3_lan_tuan'),
    kopf_tan_suat_1tuan=generate_checkbox('exam[akt_path][kopf][tansuat]', '1 lần/tuần', '1_lan_tuan'),
    kopf_tan_suat_1thang=generate_checkbox('exam[akt_path][kopf][tansuat]', '1 lần/tháng', '1_lan_thang'),
    kopf_tan_suat_thinh_thoang=generate_checkbox('exam[akt_path][kopf][tansuat]', 'thỉnh thoảng', 'thinh_thoang'),
    
    chong_mat_input=generate_input('exam[akt_path][chongmat][vitri]', 'Ghi chú vị trí...'),
    chong_mat_ts=generate_input('exam[akt_path][chongmat][tansuat]', 'Ghi chú tần suất...'),
    
    utai_tieng_u=generate_checkbox('exam[akt_path][utai][vitri]', 'tiếng ù (rào rào)', 'tieng_u'),
    utai_tieng_rit=generate_checkbox('exam[akt_path][utai][vitri]', 'tiếng rít (huýt)', 'tieng_rit'),
    
    utai_tu_khi_nao=generate_input('exam[akt_path][utai][tu_khi_nao]'),
    utai_ngay=generate_checkbox('exam[akt_path][utai][donvi]', 'ngày', 'ngay'),
    utai_tuan=generate_checkbox('exam[akt_path][utai][donvi]', 'tuần', 'tuan'),
    utai_thang=generate_checkbox('exam[akt_path][utai][donvi]', 'tháng', 'thang'),
    utai_nam=generate_checkbox('exam[akt_path][utai][donvi]', 'năm', 'nam'),
    utai_lien_tuc=generate_checkbox('exam[akt_path][utai][trangthai]', 'liên tục', 'lien_tuc'),
    utai_luc_nhieu=generate_checkbox('exam[akt_path][utai][trangthai]', 'lúc nhiều lúc ít', 'luc_nhieu_luc_it'),
    utai_het_han=generate_checkbox('exam[akt_path][utai][trangthai]', 'có lúc hết hẳn', 'co_luc_het_han'),
    
    tmj_luc_cuc_p=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'P', 'luc_cuc_p'),
    tmj_luc_cuc_t=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'T', 'luc_cuc_t'),
    tmj_dau_p=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'P', 'dau_p'),
    tmj_dau_t=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'T', 'dau_t'),
    tmj_hc_p=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'P', 'hc_p'),
    tmj_hc_t=generate_checkbox('exam[akt_path][tmj][trieuchung]', 'T', 'hc_t'),
    
    tmj_tu_khi_nao=generate_input('exam[akt_path][tmj][tu_khi_nao]'),
    tmj_dang_nieng=generate_checkbox('exam[akt_path][tmj][trangthai]', 'đang niềng răng', 'dang_nieng'),
    tmj_da_tung=generate_checkbox('exam[akt_path][tmj][trangthai]', 'đã từng niềng răng', 'da_tung'),
    tmj_cau_rang=generate_checkbox('exam[akt_path][tmj][trangthai]', 'có cầu răng', 'cau_rang'),
    tmj_mao_rang=generate_checkbox('exam[akt_path][tmj][trangthai]', 'có mão răng', 'mao_rang'),
    tmj_cay_ghep=generate_checkbox('exam[akt_path][tmj][trangthai]', 'có cấy ghép răng', 'cay_ghep'),
    tmj_dieu_tri_tuy=generate_checkbox('exam[akt_path][tmj][trangthai]', 'có răng đã điều trị tủy', 'dieu_tri_tuy')
)

html += """
                <div id="tab-hws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSC – Cột sống cổ</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Halswirbelsäule (HWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{hws_thang_tren} thang trên <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_tren_val]" value="<?php echo get_v('akt_path.hws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{hws_thang_giua} thang giữa <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_giua_val]" value="<?php echo get_v('akt_path.hws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{hws_thang_duoi} thang dưới <input type="number" min="1" max="10" name="exam[akt_path][hws][thang_duoi_val]" value="<?php echo get_v('akt_path.hws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;">{hws_p} {hws_t}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: {hws_khi_vd} {hws_khi_nghi}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">{hws_dau_nhoi} {hws_dau_am_i} {hws_te_bi} {hws_liet}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> {hws_yeu_co}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: {hws_hc_p} {hws_hc_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{hws_cap_tinh} cấp tính [từ {hws_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{hws_man_tinh} mạn tính [từ {hws_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{hws_tai_phat} tái phát [từ {hws_tai_phat_tu}]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau lan: {hws_lan_p} {hws_lan_t}</div>
                                        <div style="margin-left: 0.5rem;">- {hws_lan_canh_tay} đến cánh tay trên</div>
                                        <div style="margin-left: 0.5rem;">- {hws_lan_ban_tay} đến bàn tay</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    hws_thang_tren=generate_checkbox('exam[akt_path][hws][trieuchung]', '', 'thang_tren'),
    hws_thang_giua=generate_checkbox('exam[akt_path][hws][trieuchung]', '', 'thang_giua'),
    hws_thang_duoi=generate_checkbox('exam[akt_path][hws][trieuchung]', '', 'thang_duoi'),
    hws_p=generate_checkbox('exam[akt_path][hws][trieuchung]', 'P', 'p'),
    hws_t=generate_checkbox('exam[akt_path][hws][trieuchung]', 'T', 't'),
    hws_khi_vd=generate_checkbox('exam[akt_path][hws][trieuchung]', 'vận động', 'khi_vd'),
    hws_khi_nghi=generate_checkbox('exam[akt_path][hws][trieuchung]', 'nghỉ', 'khi_nghi'),
    hws_dau_nhoi=generate_checkbox('exam[akt_path][hws][trieuchung]', 'đau nhói', 'dau_nhoi'),
    hws_dau_am_i=generate_checkbox('exam[akt_path][hws][trieuchung]', 'đau âm ỉ', 'dau_am_i'),
    hws_te_bi=generate_checkbox('exam[akt_path][hws][trieuchung]', 'tê bì', 'te_bi'),
    hws_liet=generate_checkbox('exam[akt_path][hws][trieuchung]', 'liệt', 'liet'),
    hws_yeu_co=generate_checkbox('exam[akt_path][hws][trieuchung]', 'yếu cơ', 'yeu_co'),
    hws_hc_p=generate_checkbox('exam[akt_path][hws][trieuchung]', 'P', 'hc_p'),
    hws_hc_t=generate_checkbox('exam[akt_path][hws][trieuchung]', 'T', 'hc_t'),
    
    hws_cap_tinh=generate_checkbox('exam[akt_path][hws][trangthai]', '', 'cap_tinh'),
    hws_cap_tinh_tu=generate_input('exam[akt_path][hws][cap_tinh_tu]'),
    hws_man_tinh=generate_checkbox('exam[akt_path][hws][trangthai]', '', 'man_tinh'),
    hws_man_tinh_tu=generate_input('exam[akt_path][hws][man_tinh_tu]'),
    hws_tai_phat=generate_checkbox('exam[akt_path][hws][trangthai]', '', 'tai_phat'),
    hws_tai_phat_tu=generate_input('exam[akt_path][hws][tai_phat_tu]'),
    hws_lan_p=generate_checkbox('exam[akt_path][hws][lan]', 'P', 'lan_p'),
    hws_lan_t=generate_checkbox('exam[akt_path][hws][lan]', 'T', 'lan_t'),
    hws_lan_canh_tay=generate_checkbox('exam[akt_path][hws][lan]', '', 'lan_canh_tay'),
    hws_lan_ban_tay=generate_checkbox('exam[akt_path][hws][lan]', '', 'lan_ban_tay')
)

html += """
                <div id="tab-bws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSN – Cột sống ngực</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Brustwirbelsäule (BWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{bws_thang_tren} thang trên <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_tren_val]" value="<?php echo get_v('akt_path.bws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{bws_thang_giua} thang giữa <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_giua_val]" value="<?php echo get_v('akt_path.bws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{bws_thang_duoi} thang dưới <input type="number" min="1" max="10" name="exam[akt_path][bws][thang_duoi_val]" value="<?php echo get_v('akt_path.bws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;">{bws_p} {bws_t}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: {bws_khi_vd} {bws_khi_nghi}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">{bws_dau_nhoi} {bws_dau_am_i} {bws_te_bi} {bws_liet}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> {bws_yeu_co}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: {bws_hc_p} {bws_hc_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bws_cap_tinh} cấp tính [từ {bws_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bws_man_tinh} mạn tính [từ {bws_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bws_tai_phat} tái phát [từ {bws_tai_phat_tu}]</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau lan: {bws_lan_p} {bws_lan_t}</div>
                                        <div style="margin-left: 0.5rem;">- {bws_lan_canh_tay} đến cánh tay trên</div>
                                        <div style="margin-left: 0.5rem;">- {bws_lan_ban_tay} đến bàn tay</div>
                                        <div style="margin-top: 0.5rem; font-weight: 700;">Đau TK liên sườn (ICN): {bws_icn_p} {bws_icn_t}</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    bws_thang_tren=generate_checkbox('exam[akt_path][bws][trieuchung]', '', 'thang_tren'),
    bws_thang_giua=generate_checkbox('exam[akt_path][bws][trieuchung]', '', 'thang_giua'),
    bws_thang_duoi=generate_checkbox('exam[akt_path][bws][trieuchung]', '', 'thang_duoi'),
    bws_p=generate_checkbox('exam[akt_path][bws][trieuchung]', 'P', 'p'),
    bws_t=generate_checkbox('exam[akt_path][bws][trieuchung]', 'T', 't'),
    bws_khi_vd=generate_checkbox('exam[akt_path][bws][trieuchung]', 'vận động', 'khi_vd'),
    bws_khi_nghi=generate_checkbox('exam[akt_path][bws][trieuchung]', 'nghỉ', 'khi_nghi'),
    bws_dau_nhoi=generate_checkbox('exam[akt_path][bws][trieuchung]', 'đau nhói', 'dau_nhoi'),
    bws_dau_am_i=generate_checkbox('exam[akt_path][bws][trieuchung]', 'đau âm ỉ', 'dau_am_i'),
    bws_te_bi=generate_checkbox('exam[akt_path][bws][trieuchung]', 'tê bì', 'te_bi'),
    bws_liet=generate_checkbox('exam[akt_path][bws][trieuchung]', 'liệt', 'liet'),
    bws_yeu_co=generate_checkbox('exam[akt_path][bws][trieuchung]', 'yếu cơ', 'yeu_co'),
    bws_hc_p=generate_checkbox('exam[akt_path][bws][trieuchung]', 'P', 'hc_p'),
    bws_hc_t=generate_checkbox('exam[akt_path][bws][trieuchung]', 'T', 'hc_t'),
    
    bws_cap_tinh=generate_checkbox('exam[akt_path][bws][trangthai]', '', 'cap_tinh'),
    bws_cap_tinh_tu=generate_input('exam[akt_path][bws][cap_tinh_tu]'),
    bws_man_tinh=generate_checkbox('exam[akt_path][bws][trangthai]', '', 'man_tinh'),
    bws_man_tinh_tu=generate_input('exam[akt_path][bws][man_tinh_tu]'),
    bws_tai_phat=generate_checkbox('exam[akt_path][bws][trangthai]', '', 'tai_phat'),
    bws_tai_phat_tu=generate_input('exam[akt_path][bws][tai_phat_tu]'),
    bws_lan_p=generate_checkbox('exam[akt_path][bws][lan]', 'P', 'lan_p'),
    bws_lan_t=generate_checkbox('exam[akt_path][bws][lan]', 'T', 'lan_t'),
    bws_lan_canh_tay=generate_checkbox('exam[akt_path][bws][lan]', '', 'lan_canh_tay'),
    bws_lan_ban_tay=generate_checkbox('exam[akt_path][bws][lan]', '', 'lan_ban_tay'),
    bws_icn_p=generate_checkbox('exam[akt_path][bws][lan]', 'P', 'icn_p'),
    bws_icn_t=generate_checkbox('exam[akt_path][bws][lan]', 'T', 'icn_t')
)

html += """
                <div id="tab-lws" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & chi tiết</th>
                                <th style="width: 40%;">Trạng thái / Lan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>CSTL – Cột sống thắt lưng</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Lendenwirbelsäule (LWS)</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{lws_thang_tren} thang trên <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_tren_val]" value="<?php echo get_v('akt_path.lws.thang_tren_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{lws_thang_giua} thang giữa <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_giua_val]" value="<?php echo get_v('akt_path.lws.thang_giua_val'); ?>">/10</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">{lws_thang_duoi} thang dưới <input type="number" min="1" max="10" name="exam[akt_path][lws][thang_duoi_val]" value="<?php echo get_v('akt_path.lws.thang_duoi_val'); ?>">/10</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem;">{lws_p} {lws_t}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center;">khi: {lws_khi_vd} {lws_khi_nghi}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">{lws_dau_nhoi} {lws_dau_am_i} {lws_te_bi} {lws_liet}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;"><i class="fas fa-arrows-alt-h" style="color: #94a3b8;"></i> {lws_yeu_co}</div>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 1rem; align-items: center; font-style: italic;">Hạn chế vận động về phía: {lws_hc_p} {lws_hc_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="font-weight: 700;">Đau lan: {lws_lan_p} {lws_lan_t}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;">{lws_lan_ben} / {lws_lan_den_goi}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;">{lws_lan_phia_sau} / {lws_lan_den_ban_chan}</div>
                                        <div style="display: flex; gap: 1rem; align-items: center;">{lws_lan_phia_trong} / {lws_lan_phia_truoc}</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    lws_thang_tren=generate_checkbox('exam[akt_path][lws][trieuchung]', '', 'thang_tren'),
    lws_thang_giua=generate_checkbox('exam[akt_path][lws][trieuchung]', '', 'thang_giua'),
    lws_thang_duoi=generate_checkbox('exam[akt_path][lws][trieuchung]', '', 'thang_duoi'),
    lws_p=generate_checkbox('exam[akt_path][lws][trieuchung]', 'P', 'p'),
    lws_t=generate_checkbox('exam[akt_path][lws][trieuchung]', 'T', 't'),
    lws_khi_vd=generate_checkbox('exam[akt_path][lws][trieuchung]', 'vận động', 'khi_vd'),
    lws_khi_nghi=generate_checkbox('exam[akt_path][lws][trieuchung]', 'nghỉ', 'khi_nghi'),
    lws_dau_nhoi=generate_checkbox('exam[akt_path][lws][trieuchung]', 'đau nhói', 'dau_nhoi'),
    lws_dau_am_i=generate_checkbox('exam[akt_path][lws][trieuchung]', 'đau âm ỉ', 'dau_am_i'),
    lws_te_bi=generate_checkbox('exam[akt_path][lws][trieuchung]', 'tê bì', 'te_bi'),
    lws_liet=generate_checkbox('exam[akt_path][lws][trieuchung]', 'liệt', 'liet'),
    lws_yeu_co=generate_checkbox('exam[akt_path][lws][trieuchung]', 'yếu cơ', 'yeu_co'),
    lws_hc_p=generate_checkbox('exam[akt_path][lws][trieuchung]', 'P', 'hc_p'),
    lws_hc_t=generate_checkbox('exam[akt_path][lws][trieuchung]', 'T', 'hc_t'),
    
    lws_lan_p=generate_checkbox('exam[akt_path][lws][lan]', 'P', 'lan_p'),
    lws_lan_t=generate_checkbox('exam[akt_path][lws][lan]', 'T', 'lan_t'),
    lws_lan_ben=generate_checkbox('exam[akt_path][lws][lan]', 'bên', 'lan_ben'),
    lws_lan_den_goi=generate_checkbox('exam[akt_path][lws][lan]', 'đến gối', 'lan_den_goi'),
    lws_lan_phia_sau=generate_checkbox('exam[akt_path][lws][lan]', 'phía sau', 'lan_phia_sau'),
    lws_lan_den_ban_chan=generate_checkbox('exam[akt_path][lws][lan]', 'đến bàn chân', 'lan_den_ban_chan'),
    lws_lan_phia_trong=generate_checkbox('exam[akt_path][lws][lan]', 'phía trong', 'lan_phia_trong'),
    lws_lan_phia_truoc=generate_checkbox('exam[akt_path][lws][lan]', 'phía trước', 'lan_phia_truoc')
)

html += """
                <div id="tab-schulter" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng / Vị trí</th>
                                <th style="width: 40%;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Vai</strong></td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            {vai_p} {vai_t} 
                                            <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][vai][thang_dau]" value="<?php echo get_v('akt_path.vai.thang_dau'); ?>"> /10</span>
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                            {vai_truoc} {vai_sau} {vai_ben_ngoai}
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            khi: {vai_vd} {vai_nghi} {vai_lan}
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                            {vai_dau_nhoi} {vai_dau_am_i} {vai_te_bi} {vai_yeu_co} {vai_liet}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{vai_cap_tinh} cấp tính [từ {vai_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{vai_man_tinh} mạn tính [từ {vai_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{vai_tai_phat} tái phát [từ {vai_tai_phat_tu}]</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    vai_p=generate_checkbox('exam[akt_path][vai][vitri]', 'P', 'p'),
    vai_t=generate_checkbox('exam[akt_path][vai][vitri]', 'T', 't'),
    vai_truoc=generate_checkbox('exam[akt_path][vai][vitri]', 'phía trước (ventral)', 'truoc'),
    vai_sau=generate_checkbox('exam[akt_path][vai][vitri]', 'phía sau (dorsal)', 'sau'),
    vai_ben_ngoai=generate_checkbox('exam[akt_path][vai][vitri]', 'bên ngoài (lateral)', 'ben_ngoai'),
    vai_vd=generate_checkbox('exam[akt_path][vai][khi]', 'vận động', 'vd'),
    vai_nghi=generate_checkbox('exam[akt_path][vai][khi]', 'nghỉ', 'nghi'),
    vai_lan=generate_checkbox('exam[akt_path][vai][khi]', 'lan theo hướng nhất định', 'lan'),
    vai_dau_nhoi=generate_checkbox('exam[akt_path][vai][trieuchung]', 'đau nhói', 'dau_nhoi'),
    vai_dau_am_i=generate_checkbox('exam[akt_path][vai][trieuchung]', 'đau âm ỉ', 'dau_am_i'),
    vai_te_bi=generate_checkbox('exam[akt_path][vai][trieuchung]', 'tê bì', 'te_bi'),
    vai_yeu_co=generate_checkbox('exam[akt_path][vai][trieuchung]', 'yếu cơ', 'yeu_co'),
    vai_liet=generate_checkbox('exam[akt_path][vai][trieuchung]', 'liệt', 'liet'),
    vai_cap_tinh=generate_checkbox('exam[akt_path][vai][trangthai]', '', 'cap_tinh'),
    vai_cap_tinh_tu=generate_input('exam[akt_path][vai][cap_tinh_tu]'),
    vai_man_tinh=generate_checkbox('exam[akt_path][vai][trangthai]', '', 'man_tinh'),
    vai_man_tinh_tu=generate_input('exam[akt_path][vai][man_tinh_tu]'),
    vai_tai_phat=generate_checkbox('exam[akt_path][vai][trangthai]', '', 'tai_phat'),
    vai_tai_phat_tu=generate_input('exam[akt_path][vai][tai_phat_tu]')
)

html += """
                <div id="tab-obere" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Khớp / Vùng</th>
                                <th style="width: 40%;">Triệu chứng / Định khu</th>
                                <th style="width: 40%;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Khuỷu tay</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Ellenbogen</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                        {khuyu_p} {khuyu_t} 
                                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][khuyu][thang_dau]" value="<?php echo get_v('akt_path.khuyu.thang_dau'); ?>"> /10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column;">
                                        {khuyu_tennis}
                                        {khuyu_golf}
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{khuyu_cap_tinh} cấp tính [từ {khuyu_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{khuyu_man_tinh} mạn tính [từ {khuyu_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{khuyu_tai_phat} tái phát [từ {khuyu_tai_phat_tu}]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Bàn tay & cổ tay</strong></td>
                                <td>
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.8rem;">
                                        {ban_tay_p} {ban_tay_t} 
                                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][ban_tay][thang_dau]" value="<?php echo get_v('akt_path.ban_tay.thang_dau'); ?>"> /10</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; flex-wrap: wrap;"><strong>Cổ tay:</strong> {cotay_mu} {cotay_gan} {cotay_ngoai} {cotay_trong} (phía quay – phía ngón cái)</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Xương bàn tay (MTC):</strong> {mtc_2} {mtc_3} {mtc_4} {mtc_5} (mặt gan)</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Ngón cái (Pollicis):</strong> {pollicis_sg} {pollicis_gg}</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Ngón tay (Digiti):</strong> {digiti_2} {digiti_3} {digiti_4} {digiti_5}</div>
                                        <div><strong>Hội chứng ống cổ tay (CTS):</strong> {cts}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{ban_tay_cap_tinh} cấp tính [từ {ban_tay_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{ban_tay_man_tinh} mạn tính [từ {ban_tay_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{ban_tay_tai_phat} tái phát [từ {ban_tay_tai_phat_tu}]</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    khuyu_p=generate_checkbox('exam[akt_path][khuyu][vitri]', 'P', 'p'),
    khuyu_t=generate_checkbox('exam[akt_path][khuyu][vitri]', 'T', 't'),
    khuyu_tennis=generate_checkbox('exam[akt_path][khuyu][loai]', 'viêm lồi cầu ngoài – khuỷu tay tennis (TA)', 'tennis'),
    khuyu_golf=generate_checkbox('exam[akt_path][khuyu][loai]', 'viêm lồi cầu trong – khuỷu tay golf (GA)', 'golf'),
    khuyu_cap_tinh=generate_checkbox('exam[akt_path][khuyu][trangthai]', '', 'cap_tinh'),
    khuyu_cap_tinh_tu=generate_input('exam[akt_path][khuyu][cap_tinh_tu]'),
    khuyu_man_tinh=generate_checkbox('exam[akt_path][khuyu][trangthai]', '', 'man_tinh'),
    khuyu_man_tinh_tu=generate_input('exam[akt_path][khuyu][man_tinh_tu]'),
    khuyu_tai_phat=generate_checkbox('exam[akt_path][khuyu][trangthai]', '', 'tai_phat'),
    khuyu_tai_phat_tu=generate_input('exam[akt_path][khuyu][tai_phat_tu]'),
    
    ban_tay_p=generate_checkbox('exam[akt_path][ban_tay][vitri]', 'P', 'p'),
    ban_tay_t=generate_checkbox('exam[akt_path][ban_tay][vitri]', 'T', 't'),
    cotay_mu=generate_checkbox('exam[akt_path][ban_tay][cotay]', 'mu', 'mu'),
    cotay_gan=generate_checkbox('exam[akt_path][ban_tay][cotay]', 'gan', 'gan'),
    cotay_ngoai=generate_checkbox('exam[akt_path][ban_tay][cotay]', 'bên ngoài', 'ngoai'),
    cotay_trong=generate_checkbox('exam[akt_path][ban_tay][cotay]', 'bên trong', 'trong'),
    mtc_2=generate_checkbox('exam[akt_path][ban_tay][mtc]', '2', '2'),
    mtc_3=generate_checkbox('exam[akt_path][ban_tay][mtc]', '3', '3'),
    mtc_4=generate_checkbox('exam[akt_path][ban_tay][mtc]', '4', '4'),
    mtc_5=generate_checkbox('exam[akt_path][ban_tay][mtc]', '5', '5'),
    pollicis_sg=generate_checkbox('exam[akt_path][ban_tay][pollicis]', 'khớp yên (SG)', 'sg'),
    pollicis_gg=generate_checkbox('exam[akt_path][ban_tay][pollicis]', 'khớp bàn-ngón (GG)', 'gg'),
    digiti_2=generate_checkbox('exam[akt_path][ban_tay][digiti]', '2', '2'),
    digiti_3=generate_checkbox('exam[akt_path][ban_tay][digiti]', '3', '3'),
    digiti_4=generate_checkbox('exam[akt_path][ban_tay][digiti]', '4', '4'),
    digiti_5=generate_checkbox('exam[akt_path][ban_tay][digiti]', '5', '5'),
    cts=generate_checkbox('exam[akt_path][ban_tay][cts]', '', 'cts'),
    
    ban_tay_cap_tinh=generate_checkbox('exam[akt_path][ban_tay][trangthai]', '', 'cap_tinh'),
    ban_tay_cap_tinh_tu=generate_input('exam[akt_path][ban_tay][cap_tinh_tu]'),
    ban_tay_man_tinh=generate_checkbox('exam[akt_path][ban_tay][trangthai]', '', 'man_tinh'),
    ban_tay_man_tinh_tu=generate_input('exam[akt_path][ban_tay][man_tinh_tu]'),
    ban_tay_tai_phat=generate_checkbox('exam[akt_path][ban_tay][trangthai]', '', 'tai_phat'),
    ban_tay_tai_phat_tu=generate_input('exam[akt_path][ban_tay][tai_phat_tu]')
)

html += """
                <div id="tab-untere" class="tab-content">
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Vùng</th>
                                <th style="width: 40%;">Triệu chứng & phát hiện</th>
                                <th style="width: 40%;">Hạn chế</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Cẳng chân</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Bein</div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            {bein_p} {bein_t} 
                                            <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][bein][thang_dau]" value="<?php echo get_v('akt_path.bein.thang_dau'); ?>"> /10</span>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
                                            {bein_sau} {bein_truoc} {bein_ngoai} {bein_trong}
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                                            {bein_nghi} {bein_vd}
                                        </div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Chênh lệch chiều dài chân đã biết:</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">chân ngắn: {bein_ngan_p} / {bein_ngan_t}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bein_cap_tinh} cấp tính [từ {bein_cap_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bein_man_tinh} mạn tính [từ {bein_man_tinh_tu}]</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">{bein_tai_phat} tái phát [từ {bein_tai_phat_tu}]</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Khớp gối</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">Knie</div>
                                </td>
                                <td colspan="2">
                                    <div style="display: flex; flex-direction: column;">
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">{knie_p} {knie_t}</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                            {knie_sct} {knie_scn}
                                        </div>
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                            {knie_mac} {knie_sc} {knie_toan_bo} {knie_sau}
                                        </div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Hạn chế vận động:</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem;">
                                            - {knie_hc_dau}  - {knie_hc_ket}
                                        </div>
                                        <div style="font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center;">Phù nề (Ödem) {knie_phu_ne}</div>
                                        <div style="font-weight: 700; margin-top: 0.5rem;">Khó khăn khi:</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;">{knie_len_cau} / {knie_xuong_cau}</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;">{knie_di} / {knie_dung} / {knie_ngoi}</div>
                                        <div style="display: flex; gap: 1rem; margin-bottom: 0.2rem;">{knie_nghi} / {knie_vd}</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    bein_p=generate_checkbox('exam[akt_path][bein][vitri]', 'P', 'p'),
    bein_t=generate_checkbox('exam[akt_path][bein][vitri]', 'T', 't'),
    bein_sau=generate_checkbox('exam[akt_path][bein][huong]', 'phía sau', 'sau'),
    bein_truoc=generate_checkbox('exam[akt_path][bein][huong]', 'phía trước', 'truoc'),
    bein_ngoai=generate_checkbox('exam[akt_path][bein][huong]', 'bên ngoài', 'ngoai'),
    bein_trong=generate_checkbox('exam[akt_path][bein][huong]', 'bên trong', 'trong'),
    bein_nghi=generate_checkbox('exam[akt_path][bein][khi]', 'khi nghỉ', 'nghi'),
    bein_vd=generate_checkbox('exam[akt_path][bein][khi]', 'khi vận động', 'vd'),
    bein_ngan_p=generate_checkbox('exam[akt_path][bein][chenh_lech]', 'P', 'p'),
    bein_ngan_t=generate_checkbox('exam[akt_path][bein][chenh_lech]', 'T', 't'),
    bein_cap_tinh=generate_checkbox('exam[akt_path][bein][trangthai]', '', 'cap_tinh'),
    bein_cap_tinh_tu=generate_input('exam[akt_path][bein][cap_tinh_tu]'),
    bein_man_tinh=generate_checkbox('exam[akt_path][bein][trangthai]', '', 'man_tinh'),
    bein_man_tinh_tu=generate_input('exam[akt_path][bein][man_tinh_tu]'),
    bein_tai_phat=generate_checkbox('exam[akt_path][bein][trangthai]', '', 'tai_phat'),
    bein_tai_phat_tu=generate_input('exam[akt_path][bein][tai_phat_tu]'),
    
    knie_p=generate_checkbox('exam[akt_path][knie][vitri]', 'P', 'p'),
    knie_t=generate_checkbox('exam[akt_path][knie][vitri]', 'T', 't'),
    knie_sct=generate_checkbox('exam[akt_path][knie][vung]', 'trong (sụn chêm trong)', 'trong'),
    knie_scn=generate_checkbox('exam[akt_path][knie][vung]', 'ngoài (sụn chêm ngoài)', 'ngoai'),
    knie_mac=generate_checkbox('exam[akt_path][knie][vung]', 'xương mác (Fibula)', 'mac'),
    knie_sc=generate_checkbox('exam[akt_path][knie][vung]', 'sụn chêm', 'sun_chem'),
    knie_toan_bo=generate_checkbox('exam[akt_path][knie][vung]', 'toàn bộ', 'toan_bo'),
    knie_sau=generate_checkbox('exam[akt_path][knie][vung]', 'phía sau', 'sau'),
    knie_hc_dau=generate_checkbox('exam[akt_path][knie][han_che]', 'do đau', 'dau'),
    knie_hc_ket=generate_checkbox('exam[akt_path][knie][han_che]', 'do kẹt khớp', 'ket'),
    knie_phu_ne=generate_checkbox('exam[akt_path][knie][phu_ne]', '', 'co'),
    knie_len_cau=generate_checkbox('exam[akt_path][knie][kho_khan]', 'lên cầu thang', 'len_cau'),
    knie_xuong_cau=generate_checkbox('exam[akt_path][knie][kho_khan]', 'xuống cầu thang', 'xuong_cau'),
    knie_di=generate_checkbox('exam[akt_path][knie][kho_khan]', 'đi', 'di'),
    knie_dung=generate_checkbox('exam[akt_path][knie][kho_khan]', 'đứng', 'dung'),
    knie_ngoi=generate_checkbox('exam[akt_path][knie][kho_khan]', 'ngồi', 'ngoi'),
    knie_nghi=generate_checkbox('exam[akt_path][knie][kho_khan]', 'khi nghỉ', 'nghi'),
    knie_vd=generate_checkbox('exam[akt_path][knie][kho_khan]', 'khi vận động', 'vd')
)

html += """
                <div id="tab-fuss" class="tab-content">
                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                        <strong>Bàn chân:</strong> {fuss_p} {fuss_t} 
                        <span style="margin-left: 1rem;">Thang đau: 1 <input type="number" min="1" max="10" name="exam[akt_path][fuss][thang_dau]" value="<?php echo get_v('akt_path.fuss.thang_dau'); ?>"> /10</span>
                    </div>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px dashed #cbd5e1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">{fuss_cap_tinh} cấp tính [từ {fuss_cap_tinh_tu}]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">{fuss_man_tinh} mạn tính [từ {fuss_man_tinh_tu}]</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">{fuss_tai_phat} tái phát [từ {fuss_tai_phat_tu}]</div>
                    </div>
                    <table class="path-table">
                        <thead>
                            <tr>
                                <th style="width: 33%;">Hình ảnh lâm sàng</th>
                                <th style="width: 33%;">Định khu & giải phẫu</th>
                                <th style="width: 34%;">Biến dạng bàn chân</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <strong>Gai gót chân (Fersensporn)</strong>
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">{fuss_gan} / {fuss_mu}</div>
                                        
                                        <strong>Ngón cái vẹo ngoài (Hallux valgus):</strong>
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">{fuss_hallux_p} {fuss_hallux_t}</div>
                                        
                                        <strong>U thần kinh Morton (Morton Neurom)</strong>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">Ngón: {fuss_morton_1} {fuss_morton_2} {fuss_morton_3} {fuss_morton_4} {fuss_morton_5}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        {fuss_dinhkhu_mu}
                                        {fuss_dinhkhu_gan}
                                        {fuss_dinhkhu_gan_proximal}
                                        {fuss_dinhkhu_xa}
                                        {fuss_dinhkhu_trong}
                                        {fuss_dinhkhu_ngoai}
                                        <div style="display: flex; gap: 0.5rem;">{fuss_dinhkhu_nghi} / {fuss_dinhkhu_vd}</div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        {fuss_bien_bet}
                                        {fuss_bien_lom}
                                        {fuss_bien_liem}
                                        {fuss_bien_sup}
                                        {fuss_bien_xoe}
                                        {fuss_bien_veo}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khớp cổ chân (Sprunggelenk)</strong></td>
                                <td colspan="2">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Khớp cổ chân trên (OSG)</strong> {fuss_osg}</div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;"><strong>Khớp cổ chân dưới (USG):</strong> {fuss_usg_ngua} / {fuss_usg_sap}</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
""".format(
    fuss_p=generate_checkbox('exam[akt_path][fuss][vitri]', 'P', 'p'),
    fuss_t=generate_checkbox('exam[akt_path][fuss][vitri]', 'T', 't'),
    fuss_cap_tinh=generate_checkbox('exam[akt_path][fuss][trangthai]', '', 'cap_tinh'),
    fuss_cap_tinh_tu=generate_input('exam[akt_path][fuss][cap_tinh_tu]'),
    fuss_man_tinh=generate_checkbox('exam[akt_path][fuss][trangthai]', '', 'man_tinh'),
    fuss_man_tinh_tu=generate_input('exam[akt_path][fuss][man_tinh_tu]'),
    fuss_tai_phat=generate_checkbox('exam[akt_path][fuss][trangthai]', '', 'tai_phat'),
    fuss_tai_phat_tu=generate_input('exam[akt_path][fuss][tai_phat_tu]'),
    
    fuss_gan=generate_checkbox('exam[akt_path][fuss][gai]', 'mặt gan (plantar)', 'gan'),
    fuss_mu=generate_checkbox('exam[akt_path][fuss][gai]', 'mặt mu (dorsal)', 'mu'),
    fuss_hallux_p=generate_checkbox('exam[akt_path][fuss][hallux]', 'P', 'p'),
    fuss_hallux_t=generate_checkbox('exam[akt_path][fuss][hallux]', 'T', 't'),
    fuss_morton_1=generate_checkbox('exam[akt_path][fuss][morton]', '1', '1'),
    fuss_morton_2=generate_checkbox('exam[akt_path][fuss][morton]', '2', '2'),
    fuss_morton_3=generate_checkbox('exam[akt_path][fuss][morton]', '3', '3'),
    fuss_morton_4=generate_checkbox('exam[akt_path][fuss][morton]', '4', '4'),
    fuss_morton_5=generate_checkbox('exam[akt_path][fuss][morton]', '5', '5'),
    
    fuss_dinhkhu_mu=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'mặt mu (dorsal)', 'mu'),
    fuss_dinhkhu_gan=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'mặt gan (plantar)', 'gan'),
    fuss_dinhkhu_gan_proximal=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'gần (proximal)', 'gan_proximal'),
    fuss_dinhkhu_xa=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'xa (distal)', 'xa'),
    fuss_dinhkhu_trong=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'phía trong (medial)', 'trong'),
    fuss_dinhkhu_ngoai=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'phía ngoài (lateral)', 'ngoai'),
    fuss_dinhkhu_nghi=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'khi nghỉ', 'nghi'),
    fuss_dinhkhu_vd=generate_checkbox('exam[akt_path][fuss][dinhkhu]', 'khi vận động', 'vd'),
    
    fuss_bien_bet=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Bàn chân bẹt (Plattfuß)', 'bet'),
    fuss_bien_lom=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Bàn chân lõm/vòm cao (Hohlfuß)', 'lom'),
    fuss_bien_liem=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Bàn chân hình liềm (Sichelfuß)', 'liem'),
    fuss_bien_sup=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Sụp vòm (Senkfuß)', 'sup'),
    fuss_bien_xoe=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Bàn chân xòe/bè (Spreizfuß)', 'xoe'),
    fuss_bien_veo=generate_checkbox('exam[akt_path][fuss][bien_dang]', 'Bàn chân vẹo (Knickfuß)', 'veo'),
    
    fuss_osg=generate_checkbox('exam[akt_path][fuss][khop]', '', 'osg'),
    fuss_usg_ngua=generate_checkbox('exam[akt_path][fuss][khop]', 'xoay ngửa (sup)', 'usg_ngua'),
    fuss_usg_sap=generate_checkbox('exam[akt_path][fuss][khop]', 'xoay sấp (pron)', 'usg_sap')
)

html += """
                <div style="margin-top: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: #1e293b;">Ô ghi chú chung Bệnh lý hiện tại:</label>
                    <textarea name="exam[akt_path][notes]" class="form-premium-input" rows="3" placeholder="Nhập ghi chú thêm cho phần Bệnh sử..."><?php echo get_v('akt_path.notes'); ?></textarea>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.4rem; font-style: italic;">Ghi chú trong phần Bệnh sử sẽ được gom hiển thị lại trên các bản Tái khám (Follow-Up) về sau.</div>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const tabBtns = document.querySelectorAll('.tab-btn');
                const tabContents = document.querySelectorAll('.tab-content');
                
                tabBtns.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Xóa active
                        tabBtns.forEach(b => b.classList.remove('active'));
                        tabContents.forEach(c => c.classList.remove('active'));
                        
                        // Active tab được chọn
                        this.classList.add('active');
                        const targetId = this.getAttribute('data-target');
                        const targetContent = document.getElementById(targetId);
                        if(targetContent) {
                            targetContent.classList.add('active');
                        }
                    });
                });
            });
            </script>
"""

with open('new_section.html', 'w', encoding='utf-8') as f:
    f.write(html)
