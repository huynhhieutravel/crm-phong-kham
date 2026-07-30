spawn ssh -o StrictHostKeyChecking=no ubutu@112.78.15.2 echo '&m5L9[eUv' | sudo -S cat /var/www/crm_phong_kham/includes/invoice_popup.php
ubutu@112.78.15.2's password: 
[sudo] password for ubutu: <!-- includes/invoice_popup.php — Shared Popup Component for Invoice -->
<div id="invoiceModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.7); backdrop-filter:blur(6px); overflow-y:auto; animation: ipFadeIn 0.25s ease-out;">
<div style="max-width:650px; margin:2rem auto; background:white; border-radius:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; animation: ipSlideUp 0.3s ease-out; max-height:calc(100vh - 4rem); overflow-y:auto;">

<!-- HEADER -->
<div id="ipHeader" style="background:linear-gradient(135deg,#0f172a,#1e293b); padding:1.5rem 2rem; color:white; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:2px; color:#94a3b8; font-weight:700;">SIMON CENTER</div>
        <h2 style="margin:0.25rem 0 0; font-size:1.3rem; font-weight:800;" id="ipTitle">Phiếu Tính Tiền</h2>
    </div>
    <button onclick="closeInvoicePopup()" style="background:rgba(255,255,255,0.1); border:none; color:white; width:36px; height:36px; border-radius:10px; cursor:pointer; font-size:1.1rem;">✕</button>
</div>

<!-- CREATE MODE -->
<div id="ipCreateMode" style="padding:1.5rem 2rem;">
    <!-- Customer (static display when patient pre-selected) -->
    <div id="ipCustomerStatic" style="display:flex; align-items:center; gap:1rem; padding:1rem; background:#f8fafc; border-radius:14px; margin-bottom:1.5rem;">
        <div style="width:42px; height:42px; background:#eef2ff; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#6366f1; font-size:1.1rem;">
            <i class="fas fa-user"></i>
        </div>
        <div style="flex:1;">
            <div style="font-size:0.7rem; color:#64748b; font-weight:700; text-transform:uppercase;">Khách hàng</div>
            <div id="ipCustomerName" style="font-weight:800; font-size:1.05rem; color:#1e293b;">—</div>
        </div>
    </div>
    <!-- Customer (inline select when creating from billing page) -->
    <div id="ipCustomerSelect" style="display:none; padding:1rem; background:#f8fafc; border-radius:14px; margin-bottom:1.5rem;">
        <div style="font-size:0.7rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-bottom:0.5rem;">
            <i class="fas fa-user-plus" style="color:#6366f1;"></i> Chọn khách hàng
        </div>
        <select id="ipPatientSelect" style="width:100%;">
            <option value="">-- Gõ tên hoặc SĐT để tìm --</option>
        </select>
    </div>
    <input type="hidden" id="ipPatientId" value="">

    <!-- Dịch vụ & Sản phẩm -->
    <div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
        <i class="fas fa-list"></i> Bảng giá
    </div>

    <!-- Catalog list -->
    <div id="ipCatalog" style="max-height:350px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:1.5rem;">
        <div style="padding:2rem; text-align:center; color:#94a3b8; font-size:0.85rem;">
            <i class="fas fa-spinner fa-spin"></i> Đang tải...
        </div>
    </div>

    <!-- Cart -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.5rem;">
            <i class="fas fa-shopping-cart"></i> Giỏ hàng
        </div>
        <div id="ipCart" style="border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; min-height:50px;">
            <div id="ipCartEmpty" style="padding:1rem; text-align:center; color:#cbd5e1; font-size:0.85rem;">Chưa chọn mục nào</div>
            <div id="ipCartItems"></div>
        </div>
    </div>

    <!-- Subtotal + Discount -->
    <div style="background:#f8fafc; border-radius:14px; padding:1rem; margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
            <span style="color:#64748b; font-weight:600;">Tổng cộng:</span>
            <span id="ipSubtotal" style="font-weight:800; color:#1e293b;">0 đ</span>
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem;">
            <label style="color:#64748b; font-weight:600; font-size:0.85rem; white-space:nowrap;">Giảm giá:</label>
            <input type="text" id="ipDiscountAmount" value="0" style="flex:1; padding:0.4rem 0.6rem; border:1px solid #e2e8f0; border-radius:8px; font-weight:700; font-size:0.9rem; text-align:right;" oninput="formatMoneyInput(this); recalcInvoice()">
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="color:#64748b; font-weight:600; font-size:0.85rem; white-space:nowrap;">Lý do:</label>
            <input type="text" id="ipDiscountNote" placeholder="VD: KM khai trương" style="flex:1; padding:0.4rem 0.6rem; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem;">
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:1rem; padding-top:0.75rem; border-top:2px solid #e2e8f0;">
            <span style="font-weight:800; font-size:1.1rem; color:#0f172a;">THÀNH TIỀN:</span>
            <span id="ipTotal" style="font-weight:900; font-size:1.3rem; color:#4f46e5;">0 đ</span>
        </div>
    </div>

    <!-- Payment split -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.75rem;">
            <i class="fas fa-money-check-alt"></i> Thanh toán
        </div>
        
        <!-- Coin Payment Area -->
        <div style="background:linear-gradient(to right, #fefce8, #fffbeb); border:1px solid #fde047; border-radius:12px; padding:1.25rem; margin-bottom:1rem; box-shadow:0 4px 6px -1px rgba(250, 204, 21, 0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px dashed #fde047; padding-bottom:0.5rem;">
                <label style="font-size:0.9rem; font-weight:800; color:#b45309; display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-coins" style="color:#eab308; font-size:1.1rem;"></i> Trừ Ví Coin
                </label>
                <a href="<?php echo $base_url ?? '/'; ?>modules/sales/topup.php" target="_blank" style="font-size:0.75rem; font-weight:700; color:#b45309; background:#fef08a; border:1px solid #fde047; padding:0.3rem 0.75rem; border-radius:8px; text-decoration:none; display:flex; align-items:center; gap:0.25rem; transition:0.2s;" onmouseover="this.style.background='#fde047'" onmouseout="this.style.background='#fef08a'">
                    <i class="fas fa-plus-circle"></i> Nạp nhanh
                </a>
            </div>
            <style>
                .ip-coin-select-wrap .select2-container--default .select2-selection--single {
                    border: 2px solid #fde047; border-radius: 8px; height: 39px; display: flex; align-items: center; background: white;
                }
                .ip-coin-select-wrap .select2-container--default.select2-container--focus .select2-selection--single {
                    border-color: #eab308;
                }
                .ip-coin-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow { height: 37px; }
            </style>
            <div style="display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:2; min-width:200px;">
                    <label style="font-size:0.75rem; color:#b45309; font-weight:700; display:block; margin-bottom:0.35rem;">Khách hàng bị trừ</label>
                    <div class="ip-coin-select-wrap">
                        <select id="ipCoinPatientId" style="width:100%; border:2px solid #fde047; border-radius:8px; height:39px; background:white; color:#b45309; font-weight:600; padding:0 0.5rem; outline:none;">
                            <option value="">-- Chọn khách hàng --</option>
                        </select>
                    </div>
                </div>
                <div style="flex:1; min-width:80px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                        <label style="font-size:0.75rem; color:#b45309; font-weight:700;">Số Coin trừ</label>
                    </div>
                    <div style="display:flex; gap:0.25rem; margin-bottom:0.35rem;">
                        <button type="button" onclick="setIpCoinFast(2, 600000)" style="flex:1; padding:0.2rem; font-size:0.7rem; background:#fef08a; border:1px solid #fde047; border-radius:4px; color:#b45309; font-weight:700; cursor:pointer;" title="Đông Y 60 phút - 2 Coins">ĐY60</button>
                        <button type="button" onclick="setIpCoinFast(3, 900000)" style="flex:1; padding:0.2rem; font-size:0.7rem; background:#fef08a; border:1px solid #fde047; border-radius:4px; color:#b45309; font-weight:700; cursor:pointer;" title="Đông Y 90 phút - 3 Coins">ĐY90</button>
                        <button type="button" onclick="setIpCoinFast(3, 900000)" style="flex:1; padding:0.2rem; font-size:0.7rem; background:#fef08a; border:1px solid #fde047; border-radius:4px; color:#b45309; font-weight:700; cursor:pointer;" title="Chiropractic - 3 Coins">Chiro</button>
                    </div>
                    <input type="number" id="ipCoinCount" value="0" min="0" step="0.1" style="width:100%; padding:0.45rem; border:2px solid #fde047; border-radius:8px; font-weight:800; font-size:1.05rem; color:#b45309; text-align:center; background:white; outline:none; transition:border-color 0.2s; box-sizing:border-box; height:39px;" onfocus="this.style.borderColor='#eab308'" onblur="this.style.borderColor='#fde047'">
                </div>
                <div style="flex:1; min-width:120px;">
                    <label style="font-size:0.75rem; color:#b45309; font-weight:700; display:block; margin-bottom:0.35rem;">Quy đổi (VNĐ)</label>
                    <input type="text" id="ipCoinAmount" value="0" style="width:100%; padding:0.45rem; border:2px solid #fde047; border-radius:8px; font-weight:800; font-size:1.05rem; color:#b45309; text-align:right; background:white; outline:none; transition:border-color 0.2s; box-sizing:border-box; height:39px;" onfocus="this.style.borderColor='#eab308'" onblur="this.style.borderColor='#fde047'" oninput="formatMoneyInput(this); recalcPayment()">
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:0.5rem;">
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:#10b981;">ðµ Tiền mặt</label>
                <input type="text" id="ipCash" value="0" style="width:100%; padding:0.5rem; border:1px solid #e2e8f0; border-radius:8px; font-weight:700; font-size:0.95rem;" oninput="formatMoneyInput(this); recalcPayment()">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:#3b82f6;">ð¦ CK Cá nhân</label>
                <input type="text" id="ipTransferPersonal" value="0" style="width:100%; padding:0.5rem; border:1px solid #e2e8f0; border-radius:8px; font-weight:700; font-size:0.95rem;" oninput="formatMoneyInput(this); recalcPayment()">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:#8b5cf6;">ð¦ TK Công ty</label>
                <input type="text" id="ipTransferCompany" value="0" style="width:100%; padding:0.5rem; border:1px solid #e2e8f0; border-radius:8px; font-weight:700; font-size:0.95rem;" oninput="formatMoneyInput(this); recalcPayment()">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:#f59e0b;">ð³ Quẹt thẻ</label>
                <input type="text" id="ipCard" value="0" style="width:100%; padding:0.5rem; border:1px solid #e2e8f0; border-radius:8px; font-weight:700; font-size:0.95rem;" oninput="formatMoneyInput(this); recalcPayment()">
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.75rem; padding:0.75rem; background:#fef2f2; border-radius:10px; border:1px solid #fecaca;">
            <span style="font-weight:700; color:#dc2626; font-size:0.85rem;">ð Ghi nợ:</span>
            <span id="ipDebt" style="font-weight:800; color:#dc2626; font-size:1.1rem;">0 đ</span>
        </div>
    </div>

    <!-- Note -->
    <div class="form-group" style="margin-bottom:1.5rem;">
        <label style="font-size:0.75rem; font-weight:700; color:#64748b;">Ghi chú (tuỳ chọn)</label>
        <input type="text" id="ipNote" placeholder="Ghi chú thêm..." style="width:100%; padding:0.5rem; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem;">
    </div>

    <!-- Actions -->
    <div style="display:flex; gap:1rem;">
        <button onclick="closeInvoicePopup()" style="flex:1; padding:0.75rem; border:1px solid #e2e8f0; background:white; border-radius:12px; font-weight:700; cursor:pointer; color:#64748b;">
            ❌ Hủy
        </button>
        <button onclick="submitInvoice()" id="ipSubmitBtn" style="flex:2; padding:0.75rem; border:none; background:linear-gradient(135deg,#4f46e5,#6366f1); color:white; border-radius:12px; font-weight:800; cursor:pointer; font-size:1rem; box-shadow:0 4px 12px rgba(99,102,241,0.3);">
            ✅ Chốt & Tạo Phiếu
        </button>
    </div>
</div>

<!-- VIEW MODE (after create or when viewing existing) -->
<div id="ipViewMode" style="display:none; padding:1.5rem 2rem;">
    <div id="ipReceiptContent" style="font-family:'Courier New',monospace; max-width:400px; margin:0 auto;">
        <!-- Filled by JS -->
    </div>
    <div style="display:flex; gap:0.75rem; margin-top:1.5rem; max-width:500px; margin-left:auto; margin-right:auto;">
        <button onclick="printPDFA4()" style="flex:2; padding:0.65rem; border:none; background:linear-gradient(135deg,#4f46e5,#6366f1); color:white; border-radius:10px; font-weight:800; cursor:pointer; font-size:0.9rem; box-shadow:0 4px 12px rgba(99,102,241,0.3);">
            ð In PDF (A4)
        </button>
        <button onclick="printInvoice()" style="flex:1; padding:0.65rem; border:none; background:#10b981; color:white; border-radius:10px; font-weight:700; cursor:pointer; font-size:0.85rem;">
            ð¨ In nhỏ
        </button>
        <button id="ipCancelBtn" onclick="cancelCurrentInvoice()" style="flex:1; padding:0.65rem; border:none; background:#ef4444; color:white; border-radius:10px; font-weight:700; cursor:pointer; font-size:0.85rem;">
            ✕ Hủy Phiếu
        </button>
        <button onclick="closeInvoicePopup()" style="flex:1; padding:0.65rem; border:1px solid #e2e8f0; background:white; border-radius:10px; font-weight:700; cursor:pointer; color:#64748b; font-size:0.85rem;">
            Đóng
        </button>
    </div>
</div>

</div>
</div>

<style>
@keyframes ipFadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes ipSlideUp { from { transform:translateY(30px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.ip-catalog-item { display:flex; justify-content:space-between; align-items:center; padding:0.6rem 1rem; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background 0.15s; }
.ip-catalog-item:hover { background:#f8fafc; }
.ip-catalog-item:last-child { border-bottom:none; }
.ip-cart-row { display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.75rem; border-bottom:1px solid #f1f5f9; }
.ip-cart-row:last-child { border-bottom:none; }
@media print {
    body * { visibility: hidden !important; }
    #ipReceiptContent, #ipReceiptContent * { visibility: visible !important; }
    #ipReceiptContent { position: absolute; top: 0; left: 0; width: 100%; }
    #invoiceModal > div > div:first-child { display: none !important; }
    #invoiceModal button { display: none !important; }
}
</style>

<script>
var ipCatalogData = { services: [], products: [], packages: [] };
var ipCartItems = [];
var ipCurrentTab = 'services';
var ipBaseUrl = '<?php echo $base_url ?? '/'; ?>';

function loadSelect2AndInitCoin() {
    if (typeof jQuery === 'undefined') {
        console.warn('jQuery is missing. Loading dynamically...');
        var jqScript = document.createElement('script');
        jqScript.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
        jqScript.onload = function() { loadSelect2Scripts(); };
        document.head.appendChild(jqScript);
    } else {
        loadSelect2Scripts();
    }
}

function loadSelect2Scripts() {
    if (typeof jQuery.fn.select2 === 'undefined') {
        var cssId = 'select2-css';
        if (!document.getElementById(cssId)) {
            var head  = document.getElementsByTagName('head')[0];
            var link  = document.createElement('link');
            link.id   = cssId;
            link.rel  = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            head.appendChild(link);
        }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
        script.onload = function() { initCoinSelect2(); };
        document.head.appendChild(script);
    } else {
        initCoinSelect2();
    }
}

function initCoinSelect2() {
    var $coinSel2 = jQuery('#ipCoinPatientId');
    if ($coinSel2.length === 0) return;
    
    // Destroy if already exists to ensure clean state
    if ($coinSel2.hasClass("select2-hidden-accessible")) {
        $coinSel2.select2('destroy');
    }
    
    $coinSel2.select2({
        width: '100%',
        dropdownParent: jQuery('#invoiceModal'),
        placeholder: '-- Gõ tên hoặc SĐT để tìm --',
        allowClear: true,
        ajax: {
            url: (ipBaseUrl || '/') + 'modules/billing/search_coin_patients_api.php',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term || '' }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });
}

function openInvoicePopup(opts) {
    opts = opts || {};
    ipCartItems = [];
    
    document.getElementById('ipDiscountAmount').value = '0';
    document.getElementById('ipCoinAmount').value = '0';
    document.getElementById('ipCoinCount').value = '0';
    if (typeof $ !== 'undefined' && $('#ipCoinPatientId').data('select2')) {
        $('#ipCoinPatientId').val(null).trigger('change');
    } else {
        var c = document.getElementById('ipCoinPatientId');
        if(c) c.innerHTML = '<option value="">-- Chọn khách hàng --</option>';
    }
    document.getElementById('ipDiscountNote').value = '';
    document.getElementById('ipCash').value = '0';
    document.getElementById('ipTransferPersonal').value = '0';
    document.getElementById('ipTransferCompany').value = '0';
    document.getElementById('ipCard').value = '0';
    document.getElementById('ipNote').value = '';
    document.getElementById('ipCreateMode').style.display = 'block';
    document.getElementById('ipViewMode').style.display = 'none';
    document.getElementById('ipTitle').textContent = 'Tạo Phiếu Tính Tiền';
    document.getElementById('invoiceModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Determine mode: inline select or pre-selected patient
    var hasPatient = opts.patient_id && opts.patient_id !== '';
    
    if (hasPatient) {
        // Pre-selected patient (from appointments, patient view, etc.)
        document.getElementById('ipPatientId').value = opts.patient_id;
        document.getElementById('ipCustomerName').textContent = opts.patient_name || '—';
        document.getElementById('ipCustomerStatic').style.display = 'flex';
        document.getElementById('ipCustomerSelect').style.display = 'none';
        loadCatalog();
    } else if (opts.inline_select) {
        // Inline select mode (from billing page)
        document.getElementById('ipPatientId').value = '';
        document.getElementById('ipCustomerStatic').style.display = 'none';
        document.getElementById('ipCustomerSelect').style.display = 'block';
        
        // Populate select options
        var sel = document.getElementById('ipPatientSelect');
        sel.innerHTML = '<option value="">-- Gõ tên hoặc SĐT để tìm --</option>';
        if (opts.patients && opts.patients.length) {
            opts.patients.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name + ' — ' + p.phone;
                opt.setAttribute('data-name', p.name);
                sel.appendChild(opt);
            });
        }
        
        // Init Select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            var $sel = $(sel);
            if ($sel.data('select2')) $sel.select2('destroy');
            $sel.val('').select2({
                width: '100%',
                dropdownParent: $('#invoiceModal'),
                placeholder: '-- Gõ tên hoặc SĐT để tìm --',
                allowClear: true,
                language: { noResults: function() { return "Không tìm thấy"; } }
            }).off('change.ipselect').on('change.ipselect', function() {
                var pid = $(this).val();
                if (pid) {
                    var pname = $(this).find(':selected').attr('data-name') || '';
                    document.getElementById('ipPatientId').value = pid;
                    document.getElementById('ipCustomerName').textContent = pname;
                    loadCatalog();
                }
            });
        }
        
        // Show empty catalog message
        document.getElementById('ipCatalog').innerHTML = '<div style="padding:1.5rem; text-align:center; color:#94a3b8; font-size:0.85rem;"><i class="fas fa-user-plus" style="margin-right:0.5rem;"></i>Chọn khách hàng để xem bảng giá</div>';
    }
    
    // Init Select2 for Coin Patient with dynamic loader
    loadSelect2AndInitCoin();
    
    // Prefill items
    if (opts.prefill_items && opts.prefill_items.length) {
        opts.prefill_items.forEach(function(item) {
            ipCartItems.push({ name: item.name, qty: item.qty || 1, price: parseFloat(item.price) || 0, type: item.type || 'service' });
        });
    }
    
    // Store extra context
    window._ipContext = {
        treatment_id: opts.treatment_id || null,
        appointment_id: opts.appointment_id || null,
        patient_package_id: opts.patient_package_id || null,
        payment_id: opts.payment_id || null,
        technician_id: opts.technician_id || null
    };
    
    renderCart();
}

function openInvoiceView(invoiceId) {
    ipCurrentInvoiceId = invoiceId;
    document.getElementById('invoiceModal').style.display = 'block';
    document.getElementById('ipCreateMode').style.display = 'none';
    document.getElementById('ipViewMode').style.display = 'block';
    document.getElementById('ipTitle').textContent = 'Phiếu Tính Tiền';
    document.body.style.overflow = 'hidden';
    
    fetch(ipBaseUrl + 'modules/billing/get_invoice_api.php?id=' + invoiceId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) renderReceiptView(data.invoice);
            else alert('Lỗi: ' + (data.error || 'Không tải được phiếu'));
        });
}

function closeInvoicePopup() {
    document.getElementById('invoiceModal').style.display = 'none';
    document.body.style.overflow = '';
    if (ipCurrentInvoiceId) {
        window.location.reload();
    }
}

function loadCatalog() {
    var patientId = document.getElementById('ipPatientId').value || '';
    fetch(ipBaseUrl + 'modules/billing/get_catalog_api.php?patient_id=' + patientId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                ipCatalogData = data;
                renderCatalog();
            }
        })
        .catch(function() {
            document.getElementById('ipCatalog').innerHTML = '<div style="padding:1rem;text-align:center;color:#ef4444;">Lỗi kết nối</div>';
        });
}

function renderCatalog() {
    var activePkgs = ipCatalogData.active_packages || [];
    var services = ipCatalogData.services || [];
    var products = ipCatalogData.products || [];
    var pkgs = ipCatalogData.packages || [];
    
    var container = document.getElementById('ipCatalog');
    var html = '';
    
    // 1. Gói đang sở hữu
    if (activePkgs.length > 0) {
        html += '<div style="background:#eef2ff; padding:0.5rem 1rem; font-size:0.75rem; font-weight:800; color:#4f46e5; text-transform:uppercase; border-bottom:1px solid #c7d2fe;">ð Gói Của Khách (Dùng Buổi)</div>';
        activePkgs.forEach(function(item) {
            var label = 'Dùng 1 buổi - ' + item.name;
            var payload = '{patient_package_id:' + item.patient_package_id + '}'; // We use custom syntax to store IDs if needed, or we attach to context.
            // Wait, for simplicity, we can pass patient_package_id in the name or handle it below.
            html += '<div class="ip-catalog-item" onclick="addToCart(\'' + label.replace(/'/g, "\\'") + '\', 0, \'use_package\', ' + item.patient_package_id + ')">'
                + '<div style="display:flex; align-items:center; gap:0.75rem;">'
                + '<i class="fas fa-ticket-alt" style="color:#4f46e5; width:16px;"></i>'
                + '<div><div style="font-weight:800;color:#3730a3;font-size:0.9rem;">' + item.name + '</div>'
                + '<div style="font-size:0.7rem;color:#4f46e5;font-weight:700;">Còn lại: ' + item.remaining_sessions + ' buổi</div></div></div>'
                + '<div style="display:flex;align-items:center;gap:0.75rem;">'
                + '<span style="font-weight:800;color:#10b981;font-size:0.9rem;">Miễn phí</span>'
                + '<span style="background:#4f46e5;color:white;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;">+</span>'
                + '</div></div>';
        });
    }
    
    // Merge arrays for Retail items
    var retailItems = [];
    services.forEach(function(item) { item._ipType = 'service'; retailItems.push(item); });
    products.forEach(function(item) { item._ipType = 'product'; retailItems.push(item); });
    
    if (retailItems.length > 0) {
        if (activePkgs.length > 0) {
            // spacer if followed by another section
            html += '<div style="background:#f8fafc; padding:0.5rem 1rem; font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; border-top:2px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">ð Mua Lẻ (Dịch vụ / Sản phẩm)</div>';
        }
        retailItems.forEach(function(item) {
            var price = parseFloat(item.price) || 0;
            var icon = item._ipType === 'service' ? '<i class="fas fa-stethoscope" style="color:#3b82f6; width:16px;"></i>' : '<i class="fas fa-box" style="color:#f59e0b; width:16px;"></i>';
            html += '<div class="ip-catalog-item" onclick="addToCart(\'' + item.name.replace(/'/g, "\\'") + '\',' + price + ',\'' + item._ipType + '\')">'
                + '<div style="display:flex; align-items:center; gap:0.75rem;">'
                + icon
                + '<div><div style="font-weight:700;color:#1e293b;font-size:0.9rem;">' + item.name + '</div>'
                + '<div style="font-size:0.7rem;color:#94a3b8;">' + (item.category || '') + '</div></div></div>'
                + '<div style="display:flex;align-items:center;gap:0.75rem;">'
                + '<span style="font-weight:800;color:#4f46e5;font-size:0.9rem;">' + formatVND(price) + '</span>'
                + '<span style="background:#eef2ff;color:#4f46e5;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;">+</span>'
                + '</div></div>';
        });
    }

    // 3. Bán gói mới
    if (pkgs.length > 0) {
        html += '<div style="background:#fef3c7; padding:0.5rem 1rem; font-size:0.75rem; font-weight:800; color:#d97706; text-transform:uppercase; border-top:2px solid #fde68a; border-bottom:1px solid #fde68a;">ð Bán Gói Trị Liệu</div>';
        pkgs.forEach(function(item) {
            var price = parseFloat(item.price) || 0;
            html += '<div class="ip-catalog-item" style="background:#fffbeb;" onclick="addToCart(\'[Mua Mới] ' + item.name.replace(/'/g, "\\'") + '\',' + price + ',\'buy_package\', ' + item.id + ')">'
                + '<div style="display:flex; align-items:center; gap:0.75rem;">'
                + '<i class="fas fa-gift" style="color:#d97706; width:16px;"></i>'
                + '<div><div style="font-weight:800;color:#92400e;font-size:0.9rem;">' + item.name + '</div>'
                + '<div style="font-size:0.7rem;color:#d97706;font-weight:700;">Gói ' + item.total_sessions + ' buổi</div></div></div>'
                + '<div style="display:flex;align-items:center;gap:0.75rem;">'
                + '<span style="font-weight:800;color:#d97706;font-size:0.9rem;">' + formatVND(price) + '</span>'
                + '<span style="background:#fde68a;color:#d97706;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;">+</span>'
                + '</div></div>';
        });
    }
    
    if (html === '') {
        html = '<div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:0.85rem;">Chưa có dữ liệu</div>';
    }
    container.innerHTML = html;
}

function addToCart(name, price, type, itemId) {
    // Check if already in cart
    var found = false;
    ipCartItems.forEach(function(item) {
        if (item.name === name && item.type === type && item.itemId === itemId) {
            item.qty++;
            found = true;
        }
    });
    if (!found) {
        ipCartItems.push({ name: name, qty: 1, price: price, type: type, itemId: itemId });
    }
    renderCart();
}

function removeFromCart(index) {
    ipCartItems.splice(index, 1);
    renderCart();
}

function changeQty(index, delta) {
    ipCartItems[index].qty += delta;
    if (ipCartItems[index].qty < 1) ipCartItems[index].qty = 1;
    renderCart();
}

function toggleUseNow(index, checked) {
    ipCartItems[index].useNow = checked;
}

function renderCart() {
    var container = document.getElementById('ipCartItems');
    var empty = document.getElementById('ipCartEmpty');
    
    if (!ipCartItems.length) {
        container.innerHTML = '';
        empty.style.display = 'block';
        recalcInvoice();
        return;
    }
    empty.style.display = 'none';
    
    var html = '';
    ipCartItems.forEach(function(item, i) {
        var lineTotal = item.qty * item.price;
        var extra = '';
        if (item.type === 'buy_package') {
            extra = '<div style="font-size:0.75rem; margin-top:0.25rem; color:#475569;"><label style="cursor:pointer; display:flex; align-items:center; gap:0.25rem;"><input type="checkbox" onchange="toggleUseNow(' + i + ', this.checked)" ' + (item.useNow ? 'checked' : '') + '> ⚡️ Trừ ngay 1 buổi (Dùng hôm nay)</label></div>';
        }

        html += '<div class="ip-cart-row">'
            + '<div style="flex:1;"><div style="font-weight:700;font-size:0.85rem;color:#1e293b;">' + item.name + '</div>' + extra + '</div>'
            + '<div style="display:flex;align-items:center;gap:0.25rem;">'
            + '<button onclick="changeQty(' + i + ',-1)" style="width:22px;height:22px;border:1px solid #e2e8f0;background:white;border-radius:6px;cursor:pointer;font-weight:700;color:#64748b;">−</button>'
            + '<span style="width:24px;text-align:center;font-weight:800;font-size:0.85rem;">' + item.qty + '</span>'
            + '<button onclick="changeQty(' + i + ',1)" style="width:22px;height:22px;border:1px solid #e2e8f0;background:white;border-radius:6px;cursor:pointer;font-weight:700;color:#64748b;">+</button>'
            + '</div>'
            + '<div style="width:90px;text-align:right;font-weight:700;color:#1e293b;font-size:0.85rem;">' + formatVND(lineTotal) + '</div>'
            + '<button onclick="removeFromCart(' + i + ')" style="border:none;background:none;color:#ef4444;cursor:pointer;font-size:0.8rem;padding:0.2rem;"><i class="fas fa-trash-alt"></i></button>'
            + '</div>';
    });
    container.innerHTML = html;
    recalcInvoice();
}

function getRawValue(id) {
    var v = document.getElementById(id).value || '';
    return parseInt(v.replace(/\./g, '')) || 0;
}

function formatMoneyOnly(num) {
    return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function formatMoneyInput(el) {
    var val = el.value.replace(/[^\d]/g, '');
    if (val === '') {
        el.value = '';
        return;
    }
    el.value = formatMoneyOnly(val);
}

function setIpCoinFast(coins, amount) {
    document.getElementById('ipCoinCount').value = coins;
    document.getElementById('ipCoinAmount').value = formatMoneyOnly(amount);
    recalcPayment();
}

function recalcInvoice() {
    var subtotal = 0;
    ipCartItems.forEach(function(item) { subtotal += item.qty * item.price; });
    
    var discount = getRawValue('ipDiscountAmount');
    if (discount > subtotal) {
        discount = subtotal;
        document.getElementById('ipDiscountAmount').value = formatMoneyOnly(discount);
    }
    var total = subtotal - discount;
    
    document.getElementById('ipSubtotal').textContent = formatVND(subtotal);
    document.getElementById('ipTotal').textContent = formatVND(total);
    
    // Auto-fill cash to total
    var cash = getRawValue('ipCash');
    var transferPersonal = getRawValue('ipTransferPersonal');
    var transferCompany = getRawValue('ipTransferCompany');
    var card = getRawValue('ipCard');
    if (cash === 0 && transferPersonal === 0 && transferCompany === 0 && card === 0) {
        document.getElementById('ipCash').value = formatMoneyOnly(total);
    }
    recalcPayment();
}

function recalcPayment() {
    var subtotal = 0;
    ipCartItems.forEach(function(item) { subtotal += item.qty * item.price; });
    var discount = getRawValue('ipDiscountAmount');
    var total = subtotal - discount;
    if (total < 0) total = 0;
    
    var cash = getRawValue('ipCash');
    var transferPersonal = getRawValue('ipTransferPersonal');
    var transferCompany = getRawValue('ipTransferCompany');
    var card = getRawValue('ipCard');
    var coinVal = getRawValue('ipCoinAmount');
    var paid = cash + transferPersonal + transferCompany + card + coinVal;
    var debt = total - paid;
    if (debt < 0) debt = 0;
    
    document.getElementById('ipDebt').textContent = formatVND(debt);
    var debtBox = document.getElementById('ipDebt').parentElement;
    if (debt > 0) {
        debtBox.style.background = '#fef2f2'; debtBox.style.borderColor = '#fecaca';
    } else {
        debtBox.style.background = '#f0fdf4'; debtBox.style.borderColor = '#bbf7d0';
        document.getElementById('ipDebt').style.color = '#10b981';
        document.getElementById('ipDebt').textContent = '0 đ ✓';
    }
}

function submitInvoice() {
    if (!ipCartItems.length) { alert('Vui lòng chọn ít nhất 1 sản phẩm/dịch vụ'); return; }
    var patientId = document.getElementById('ipPatientId').value;
    if (!patientId) { alert('Thiếu thông tin khách hàng'); return; }
    
    var subtotal = 0;
    ipCartItems.forEach(function(item) { subtotal += item.qty * item.price; });
    var discount = getRawValue('ipDiscountAmount');
    var total = subtotal - discount;
    var cash = getRawValue('ipCash');
    var transferPersonal = getRawValue('ipTransferPersonal');
    var transferCompany = getRawValue('ipTransferCompany');
    var card = getRawValue('ipCard');
    var coinVal = getRawValue('ipCoinAmount');
    var transfer = transferPersonal + transferCompany;
    var debt = total - cash - transfer - card - coinVal;
    if (debt < 0) debt = 0;
    
    var btn = document.getElementById('ipSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tạo...';
    
    var ctx = window._ipContext || {};
    var apiUrl = (ipBaseUrl || '/') + 'modules/billing/create_invoice_api.php';
    
    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            patient_id: patientId,
            items: ipCartItems,
            subtotal: subtotal,
            discount_amount: discount,
            discount_note: document.getElementById('ipDiscountNote').value,
            total_amount: total,
            cash_amount: cash,
            transfer_amount: transfer,
            transfer_personal_amount: transferPersonal,
            transfer_company_amount: transferCompany,
            card_amount: card,
            package_deduct: coinVal,
            coin_deduct_patient_id: document.getElementById('ipCoinPatientId').value,
            coin_deduct_count: parseFloat(document.getElementById('ipCoinCount').value) || 0,
            debt_amount: debt,
            note: document.getElementById('ipNote').value,
            treatment_id: ctx.treatment_id,
            appointment_id: ctx.appointment_id,
            patient_package_id: ctx.patient_package_id,
            payment_id: ctx.payment_id,
            technician_id: ctx.technician_id
        })
    })
    .then(function(r) {
        if (!r.ok) {
            return r.text().then(function(txt) {
                throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200));
            });
        }
        var ct = r.headers.get('content-type') || '';
        if (ct.indexOf('application/json') === -1) {
            return r.text().then(function(txt) {
                throw new Error('Server trả về HTML thay vì JSON. Kiểm tra đăng nhập. Response: ' + txt.substring(0, 200));
            });
        }
        return r.json();
    })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '✅ Chốt & Tạo Phiếu';
        if (data.success) {
            openInvoiceView(data.invoice_id);
        } else {
            alert('Lỗi tạo phiếu: ' + (data.error || 'Không rõ nguyên nhân'));
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '✅ Chốt & Tạo Phiếu';
        console.error('Invoice API Error:', err);
        alert('Lỗi: ' + (err.message || 'Kết nối mạng thất bại'));
    });
}

function renderReceiptView(inv) {
    var items = inv.items || [];
    var html = '<div style="text-align:center; padding:1rem 0; border-bottom:2px dashed #e2e8f0;">'
        + '<div style="font-weight:900; font-size:1.2rem; color:#0f172a;">SIMON CENTER</div>'
        + '<div style="font-size:0.75rem; color:#64748b;">Chăm sóc sức khỏe toàn diện</div>'
        + '</div>';
    
    html += '<div style="padding:0.75rem 0; border-bottom:1px dashed #e2e8f0;">'
        + '<div style="font-weight:800; text-align:center; color:#4f46e5; font-size:1rem; margin-bottom:0.5rem;">PHIẾU TÍNH TIỀN</div>'
        + '<div style="display:flex; justify-content:space-between; font-size:0.8rem;">'
        + '<span style="color:#64748b;">Mã:</span><span style="font-weight:800;">' + inv.invoice_no + '</span></div>'
        + '<div style="display:flex; justify-content:space-between; font-size:0.8rem;">'
        + '<span style="color:#64748b;">Ngày:</span><span style="font-weight:600;">' + (inv.created_at || '').replace(' ', ' — ').substring(0, 21) + '</span></div>'
        + '</div>';
    
    html += '<div style="padding:0.75rem 0; border-bottom:1px dashed #e2e8f0;">'
        + '<div style="display:flex; justify-content:space-between; font-size:0.85rem;">'
        + '<span style="color:#64748b;">Khách hàng:</span><span style="font-weight:800;">' + (inv.patient_name || '') + '</span></div>'
        + '<div style="display:flex; justify-content:space-between; font-size:0.85rem;">'
        + '<span style="color:#64748b;">SĐT:</span><span style="font-weight:600;">' + (inv.patient_phone || '') + '</span></div>'
        + '</div>';
    
    // Items
    html += '<div style="padding:0.75rem 0; border-bottom:1px dashed #e2e8f0;">';
    items.forEach(function(item) {
        var lineTotal = (item.qty || 1) * (item.price || 0);
        html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.25rem 0;">'
            + '<span>' + item.name + ' x' + (item.qty || 1) + '</span>'
            + '<span style="font-weight:700;">' + formatVND(lineTotal) + '</span></div>';
    });
    html += '</div>';
    
    // Totals
    html += '<div style="padding:0.75rem 0; border-bottom:1px dashed #e2e8f0;">';
    html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span style="color:#64748b;">Tổng cộng:</span><span style="font-weight:700;">' + formatVND(inv.subtotal) + '</span></div>';
    if (parseFloat(inv.discount_amount) > 0) {
        html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0; color:#ef4444;"><span>Giảm giá' + (inv.discount_note ? ' (' + inv.discount_note + ')' : '') + ':</span><span style="font-weight:700;">- ' + formatVND(inv.discount_amount) + '</span></div>';
    }
    html += '<div style="display:flex; justify-content:space-between; font-size:1.1rem; padding:0.5rem 0; font-weight:900; color:#4f46e5;"><span>THÀNH TIỀN:</span><span>' + formatVND(inv.total_amount) + '</span></div>';
    html += '</div>';
    
    // Payment breakdown
    html += '<div style="padding:0.75rem 0; border-bottom:1px dashed #e2e8f0;">';
    if (parseFloat(inv.cash_amount) > 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span>ðµ Tiền mặt:</span><span style="font-weight:700;">' + formatVND(inv.cash_amount) + '</span></div>';
    if (parseFloat(inv.transfer_personal_amount) > 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span>ð¦ CK Cá nhân:</span><span style="font-weight:700;">' + formatVND(inv.transfer_personal_amount) + '</span></div>';
    if (parseFloat(inv.transfer_company_amount) > 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span>ð¦ TK Công ty:</span><span style="font-weight:700;">' + formatVND(inv.transfer_company_amount) + '</span></div>';
    if (parseFloat(inv.card_amount) > 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span>ð³ Quẹt thẻ:</span><span style="font-weight:700;">' + formatVND(inv.card_amount) + '</span></div>';
    if (parseFloat(inv.transfer_amount) > 0 && parseFloat(inv.transfer_personal_amount) == 0 && parseFloat(inv.transfer_company_amount) == 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0;"><span>ð¦ Chuyển khoản (Cũ):</span><span style="font-weight:700;">' + formatVND(inv.transfer_amount) + '</span></div>';
    if (parseFloat(inv.debt_amount) > 0) html += '<div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:0.15rem 0; color:#dc2626;"><span>ð Ghi nợ:</span><span style="font-weight:700;">' + formatVND(inv.debt_amount) + '</span></div>';
    html += '</div>';
    
    html += '<div style="padding:0.75rem 0; text-align:center; font-size:0.8rem; color:#64748b;">'
        + '<div>Thu ngân: <strong>' + (inv.cashier_name || '') + '</strong></div>'
        + '<div style="margin-top:0.5rem;">Cảm ơn quý khách! ð</div>'
        + '</div>';
    
    document.getElementById('ipReceiptContent').innerHTML = html;
    
    // Toggle Cancel button
    var cancelBtn = document.getElementById('ipCancelBtn');
    if (cancelBtn) {
        if (inv.status === 'cancelled') {
            cancelBtn.style.display = 'none';
        } else {
            cancelBtn.style.display = 'block';
        }
    }
}

var ipCurrentInvoiceId = null;

function printInvoice() {
    window.print();
}

function printPDFA4() {
    if (ipCurrentInvoiceId) {
        window.open(ipBaseUrl + 'modules/billing/print_invoice.php?id=' + ipCurrentInvoiceId, '_blank');
    }
}

function cancelCurrentInvoice() {
    if (!ipCurrentInvoiceId) return;
    if (!confirm('Bạn có chắc chắn muốn hủy phiếu tính tiền này? Hệ thống sẽ ghi nhận trạng thái Hủy và trừ khỏi báo cáo doanh thu.')) return;
    
    var btn = document.getElementById('ipCancelBtn');
    if(btn) { btn.disabled = true; btn.innerHTML = 'Đang xử lý...'; }
    
    fetch(ipBaseUrl + 'modules/billing/update_invoice_status_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_id: ipCurrentInvoiceId, status: 'cancelled' })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Đã hủy phiếu tính tiền thành công!');
            window.location.reload();
        } else {
            alert('Lỗi: ' + (data.error || 'Không thể hủy phiếu'));
            if(btn) { btn.disabled = false; btn.innerHTML = '✕ Hủy Phiếu'; }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi kết nối');
        if(btn) { btn.disabled = false; btn.innerHTML = '✕ Hủy Phiếu'; }
    });
}

function formatVND(num) {
    if (!num) return '0 đ';
    return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
}
</script>
