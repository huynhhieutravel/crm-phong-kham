/**
 * assets/js/confirm-modal.js
 * Custom confirm modal toàn hệ thống — thay thế window.confirm() bị browser chặn.
 * Cách dùng:
 *   confirmAndGo(url, 'Bạn có chắc?')         — cho link GET (xóa bệnh nhân, v.v.)
 *   confirmAndSubmit(formEl, 'Bạn có chắc?')   — cho form POST (xóa trong form)
 *   confirmAndRun(callback, 'Bạn có chắc?')    — cho callback JS bất kỳ
 */
(function() {
    // Inject CSS
    var style = document.createElement('style');
    style.textContent = [
        '.cm-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);z-index:99999;justify-content:center;align-items:center}',
        '.cm-overlay.active{display:flex}',
        '.cm-box{background:#fff;border-radius:16px;padding:2rem;max-width:380px;width:90%;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.25);animation:cmPop .2s ease}',
        '@keyframes cmPop{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}',
        '.cm-box h4{margin:0 0 .5rem;font-size:1.1rem;color:#1e293b;display:flex;align-items:center;justify-content:center;gap:.5rem}',
        '.cm-box p{margin:0 0 1.5rem;color:#64748b;font-size:.9rem;line-height:1.5}',
        '.cm-btns{display:flex;gap:.75rem;justify-content:center}',
        '.cm-btns button{padding:.55rem 1.5rem;border-radius:10px;font-weight:700;cursor:pointer;border:none;font-size:.85rem;transition:all .15s}',
        '.cm-btn-no{background:#f1f5f9;color:#475569}',
        '.cm-btn-no:hover{background:#e2e8f0}',
        '.cm-btn-yes{background:#ef4444;color:#fff}',
        '.cm-btn-yes:hover{background:#dc2626}'
    ].join('\n');
    document.head.appendChild(style);

    // Inject HTML
    var overlay = document.createElement('div');
    overlay.className = 'cm-overlay';
    overlay.id = 'cmOverlay';
    overlay.innerHTML = '<div class="cm-box">' +
        '<h4><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i> Xác nhận</h4>' +
        '<p id="cmMsg">Bạn có chắc chắn?</p>' +
        '<div class="cm-btns">' +
        '<button class="cm-btn-no" id="cmBtnNo">Hủy</button>' +
        '<button class="cm-btn-yes" id="cmBtnYes"><i class="fas fa-check"></i> Đồng ý</button>' +
        '</div></div>';
    document.addEventListener('DOMContentLoaded', function() {
        document.body.appendChild(overlay);
        // Click outside to close
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) cmClose();
        });
        document.getElementById('cmBtnNo').addEventListener('click', cmClose);
    });

    var cmCallback = null;

    function cmClose() {
        cmCallback = null;
        document.getElementById('cmOverlay').classList.remove('active');
    }

    function cmShow(msg, cb) {
        cmCallback = cb;
        document.getElementById('cmMsg').textContent = msg || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
        document.getElementById('cmOverlay').classList.add('active');
        // Re-bind yes button each time to avoid stale closures
        var yesBtn = document.getElementById('cmBtnYes');
        var newBtn = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newBtn, yesBtn);
        newBtn.addEventListener('click', function() {
            document.getElementById('cmOverlay').classList.remove('active');
            if (cmCallback) cmCallback();
            cmCallback = null;
        });
    }

    // Public API
    window.confirmAndGo = function(url, msg) {
        cmShow(msg || 'Bạn có chắc chắn muốn xóa?', function() {
            window.location.href = url;
        });
    };

    window.confirmAndSubmit = function(formEl, msg) {
        cmShow(msg || 'Bạn có chắc chắn?', function() {
            formEl.submit();
        });
    };

    window.confirmAndRun = function(cb, msg) {
        cmShow(msg || 'Bạn có chắc chắn?', cb);
    };

    // Helper: create a dynamic form POST and submit it
    window.confirmAndPost = function(action, data, msg) {
        cmShow(msg || 'Bạn có chắc chắn muốn xóa?', function() {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.style.display = 'none';
            for (var key in data) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            }
            
            // Tự động nhúng CSRF token
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta && !data['_csrf_token']) {
                var csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_csrf_token';
                csrfInput.value = csrfMeta.getAttribute('content');
                form.appendChild(csrfInput);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
    };
})();
