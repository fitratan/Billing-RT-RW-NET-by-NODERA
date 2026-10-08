<?php
$pageTitle = 'Chat';
$activeTab = 'chat';
$pageFab   = []; // No FAB on Chat page
require_once __DIR__ . '/../include/header.php';

$data = getBkData();
$categories = $data['categories'];
$catsJson = json_encode($categories);
$chatHistory = getBkChatHistory();
$chatAutoDelete = getBkSetting('chat_autodelete', 'never');
$aiProvider     = getBkSetting('ai_provider', 'none');
$aiApiKey       = getBkSetting('ai_api_key', '');
$aiBaseUrl      = getBkSetting('ai_base_url', '');
$aiModel        = getBkSetting('ai_model', '');
?>

<!-- Tesseract OCR: lazy-loaded only when user picks a receipt image -->
<script>
var _tesseractLoaded = false;
var _tesseractLoading = false;
function loadTesseractLazy(cb) {
    if (_tesseractLoaded) { if(cb) cb(); return; }
    if (_tesseractLoading) { document.addEventListener('tesseract_ready', function(){ if(cb) cb(); }, {once:true}); return; }
    _tesseractLoading = true;
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    s.onload = function() { _tesseractLoaded = true; _tesseractLoading = false; document.dispatchEvent(new Event('tesseract_ready')); if(cb) cb(); };
    s.onerror = function() { _tesseractLoading = false; if(cb) cb(); };
    document.head.appendChild(s);
}
</script>

<!-- Chat Main Card Shell -->
<div class="card" style="display:flex;flex-direction:column;height:calc(100dvh - 170px);min-height:460px;overflow:hidden;border-radius:16px;">
    <!-- Chat Top Header Bar -->
    <div style="padding:10px 16px;background:var(--card);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;z-index:2;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:10px;background:var(--primary);color:var(--primary-fg);display:flex;align-items:center;justify-content:center;font-size:16px;">
                <i class="bi bi-robot"></i>
            </div>
            <div>
                <h3 style="margin:0;font-size:13px;font-weight:700;color:var(--fg);">Bot Chat Pembukuan AI</h3>
                <p style="margin:0;font-size:11px;color:#22c55e;font-weight:500;">● Online · Siap mencatat</p>
            </div>
        </div>
        <button type="button" onclick="openModal('modalChatSettings')" class="hdr-btn" title="Pengaturan Chat & Hapus Otomatis" style="width:34px;height:34px;border-radius:10px;background:var(--muted);border:1px solid var(--border);color:var(--muted-fg);display:flex;align-items:center;justify-content:center;cursor:pointer;">
            <i class="bi bi-gear-fill" style="font-size:15px;"></i>
        </button>
    </div>

    <!-- Chat Messages Stream -->
    <div id="chatMessages" class="no-scrollbar" style="flex:1;overflow-y:auto;overflow-x:hidden;padding:16px;display:flex;flex-direction:column;gap:14px;position:relative;">
        <!-- Drag and Drop Overlay -->
        <div id="chatDropZone" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;background:color-mix(in srgb, var(--primary) 85%, transparent);backdrop-filter:blur(6px);z-index:20;border-radius:14px;flex-direction:column;align-items:center;justify-content:center;color:#fff;gap:10px;pointer-events:none;">
            <i class="bi bi-cloud-arrow-up-fill" style="font-size:48px;"></i>
            <span style="font-size:15px;font-weight:700;">Lepaskan Foto Nota Di Sini 📷</span>
            <span style="font-size:12px;opacity:.9;">Bot akan membaca nominal & item otomatis!</span>
        </div>

        <?php if (empty($chatHistory)): ?>
            <div style="display:flex;gap:10px;max-width:88%;min-width:0;">
                <div style="width:34px;height:34px;border-radius:10px;background:var(--primary);color:var(--primary-fg);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px;">
                    <i class="bi bi-robot"></i>
                </div>
                <div style="background:var(--muted);border:1px solid var(--border);padding:14px 16px;border-radius:14px;border-top-left-radius:4px;max-width:100%;min-width:0;word-break:break-word;overflow-wrap:anywhere;">
                    <div style="font-weight:700;font-size:14px;color:var(--fg);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <span>Halo! Selamat datang di Bot Asisten Pembukuan AI</span> 👋
                    </div>
                    <p style="margin:0 0 10px;font-size:12.5px;line-height:1.55;color:var(--fg);">
                        Saya siap membantu Anda mencatat transaksi dan menganalisis laporan keuangan usaha secara praktis & otomatis.
                    </p>

                    <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;line-height:1.5;margin-bottom:4px;">
                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <div style="width:22px;height:22px;border-radius:6px;background:rgba(37,99,235,.12);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;margin-top:1px;"><i class="bi bi-chat-left-text-fill"></i></div>
                            <div><b>Ketik Teks & Kategori:</b> Tulis transaksi lengkap dengan kategori (contoh: <code style="background:var(--card);padding:2px 6px;border-radius:4px;border:1px solid var(--border);color:var(--primary);white-space:normal;word-break:break-word;">"Beli pentol 5000 kategori Rumah Tangga"</code> atau <code style="background:var(--card);padding:2px 6px;border-radius:4px;border:1px solid var(--border);color:var(--primary);white-space:normal;word-break:break-word;">"Beli bensin 50rb"</code>). Bot akan mengelompokkan kategori otomatis!</div>
                        </div>

                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <div style="width:22px;height:22px;border-radius:6px;background:rgba(34,197,94,.12);color:#22c55e;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;margin-top:1px;"><i class="bi bi-camera-fill"></i></div>
                            <div><b>Unggah Foto Nota (OCR 📷):</b> Klik ikon klip <i class="bi bi-paperclip"></i> atau <b>Drag & Drop foto</b>. Bot akan membaca nominal & item otomatis!</div>
                        </div>

                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <div style="width:22px;height:22px;border-radius:6px;background:rgba(234,179,8,.12);color:#eab308;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;margin-top:1px;"><i class="bi bi-mic-fill"></i></div>
                            <div><b>Input Suara / Bicara 🎙️:</b> Klik ikon mikrofon <i class="bi bi-mic-fill"></i> lalu ucapkan transaksi Anda secara lisan.</div>
                        </div>

                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <div style="width:22px;height:22px;border-radius:6px;background:rgba(168,85,247,.12);color:#a855f7;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;margin-top:1px;"><i class="bi bi-cpu-fill"></i></div>
                            <div><b>Tanya & AI Provider (⚙️):</b> Tanya statistik keuangan atau hubungkan API Key AI (DeepSeek, Gemini, Groq) via tombol ⚙️ di kanan atas.</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($chatHistory as $msg): ?>
                <?php $isUser = !empty($msg['is_user']); ?>
                <div style="display:flex;gap:10px;max-width:88%;min-width:0;<?= $isUser ? 'align-self:flex-end;flex-direction:row-reverse;' : '' ?>">
                    <div style="width:34px;height:34px;border-radius:10px;<?= $isUser ? 'background:var(--muted);color:var(--fg);' : 'background:var(--primary);color:var(--primary-fg);' ?>display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px;">
                        <i class="bi <?= $isUser ? 'bi-person-fill' : 'bi-robot' ?>"></i>
                    </div>
                    <div style="padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.5;min-width:0;word-break:break-word;overflow-wrap:anywhere;<?= $isUser ? 'background:var(--primary);color:var(--primary-fg);border-top-right-radius:4px;' : 'background:var(--muted);color:var(--fg);border:1px solid var(--border);border-top-left-radius:4px;' ?>">
                        <?php if (!empty($msg['is_html'])): ?>
                            <?= $msg['text'] ?>
                        <?php else: ?>
                            <?= htmlspecialchars($msg['text']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Chat Input Footer -->
    <div style="padding:12px;background:color-mix(in srgb, var(--card) 90%, transparent);border-top:1px solid var(--border);backdrop-filter:blur(8px);">
        <form id="chatForm" style="display:flex;gap:8px;align-items:center;" onsubmit="handleChatSubmit(event)">
            <label id="paperclipBtn" style="width:42px;height:42px;border-radius:10px;background:var(--muted);border:1px solid var(--border);color:var(--muted-fg);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:all .15s;" title="Lampirkan foto nota">
                <i class="bi bi-paperclip" style="font-size:18px;"></i>
                <input type="file" id="chatFile" accept="image/*,.pdf" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;" onchange="previewChatFile(this)">
            </label>
            <button type="button" id="voiceBtn" onclick="toggleVoiceInput()" style="width:42px;height:42px;border-radius:10px;background:var(--muted);border:1px solid var(--border);color:var(--muted-fg);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:all .15s;" title="Input Suara (Bicara)">
                <i class="bi bi-mic-fill" id="voiceIcon" style="font-size:18px;"></i>
            </button>
            <div style="flex:1;position:relative;">
                <input type="text" id="chatInput" placeholder="Ketik pesan / foto nota / bicara 🎙️..." autocomplete="off"
                    style="width:100%;height:42px;padding:0 14px;border-radius:10px;border:1px solid var(--border);background:var(--muted);color:var(--fg);outline:none;font-size:13px;transition:border-color .15s, box-shadow .15s;">
                <div id="chatFilePreview" style="display:none;position:absolute;bottom:calc(100% + 8px);left:0;background:var(--card);border:1px solid var(--border);padding:6px 12px;border-radius:10px;font-size:12px;color:var(--fg);align-items:center;gap:10px;box-shadow:0 6px 20px rgba(0,0,0,.18);z-index:10;max-width:calc(100vw - 40px);">
                    <img id="chatFileThumb" src="" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid var(--border);display:none;flex-shrink:0;">
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:600;font-size:11px;color:#22c55e;display:flex;align-items:center;gap:4px;">
                            <i class="bi bi-check-circle-fill"></i> Foto Nota Terlampir
                        </div>
                        <div id="chatFileName" style="font-size:11px;color:var(--muted-fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;"></div>
                    </div>
                    <button type="button" onclick="clearChatFile()" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;padding:4px;font-size:16px;line-height:1;" title="Batal lampirkan">&times;</button>
                </div>
            </div>
            <button type="submit" style="width:42px;height:42px;border-radius:10px;background:var(--primary);color:var(--primary-fg);border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;box-shadow:0 3px 10px color-mix(in srgb,var(--primary) 30%,transparent);transition:transform .1s;" title="Kirim">
                <i class="bi bi-send-fill" style="font-size:15px;margin-left:-1px;"></i>
            </button>
        </form>
    </div>
</div>

<script>
const categories = <?= $catsJson ?>;
let _pendingOcrFiles = {};

function saveMessageToServer(text, isUser, isHtml) {
    var fd = new FormData();
    fd.append('action', 'save_chat_message');
    fd.append('text', text);
    fd.append('is_user', isUser ? '1' : '0');
    fd.append('is_html', isHtml ? '1' : '0');
    fetch('index.php', { method: 'POST', body: fd }).catch(function(){});
}

function clearChatHistory() {
    if (confirm('Hapus seluruh riwayat percakapan chat?')) {
        var fd = new FormData();
        fd.append('action', 'clear_chat_history');
        fetch('index.php', { method: 'POST', body: fd }).then(function() {
            var chat = document.getElementById('chatMessages');
            if (chat) {
                chat.innerHTML = `
                    <div style="display:flex;gap:10px;max-width:88%;">
                        <div style="width:34px;height:34px;border-radius:10px;background:var(--primary);color:var(--primary-fg);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px;">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div style="background:var(--muted);border:1px solid var(--border);padding:10px 14px;border-radius:14px;border-top-left-radius:4px;">
                            <p style="margin:0;font-size:13px;line-height:1.5;">Riwayat chat telah dibersihkan! Ketik transaksi baru, bicara 🎙️, atau unggah foto nota. 📷</p>
                        </div>
                    </div>
                `;
            }
            closeModal('modalChatSettings');
        });
    }
}

function saveChatAutoDeleteConfig(val) {
    var fd = new FormData();
    fd.append('action', 'save_chat_autodelete');
    fd.append('value', val);
    fetch('index.php', { method: 'POST', body: fd }).then(function() {
        alert('Pengaturan hapus otomatis riwayat chat disimpan: ' + (val === 'never' ? 'Jangan Pernah' : val === '1d' ? '1 Hari' : val === '1w' ? '1 Minggu' : '1 Bulan'));
        closeModal('modalChatSettings');
    });
}

function appendMessage(text, isUser, isHtml=false) {
    appendMessageUI(text, isUser, isHtml);
    saveMessageToServer(text, isUser, isHtml);
}

function appendMessageUI(text, isUser, isHtml=false) {
    var chat = document.getElementById('chatMessages');
    if (!chat) return;
    var wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.gap = '10px';
    wrap.style.maxWidth = '88%';
    wrap.style.minWidth = '0';
    
    if (isUser) {
        wrap.style.alignSelf = 'flex-end';
        wrap.style.flexDirection = 'row-reverse';
    }
    
    var avatar = document.createElement('div');
    avatar.style.width = '34px';
    avatar.style.height = '34px';
    avatar.style.borderRadius = '10px';
    avatar.style.flexShrink = '0';
    avatar.style.display = 'flex';
    avatar.style.alignItems = 'center';
    avatar.style.justifyContent = 'center';
    avatar.style.fontSize = '16px';
    
    if (isUser) {
        avatar.style.background = 'var(--muted)';
        avatar.style.color = 'var(--fg)';
        avatar.innerHTML = '<i class="bi bi-person-fill"></i>';
    } else {
        avatar.style.background = 'var(--primary)';
        avatar.style.color = 'var(--primary-fg)';
        avatar.innerHTML = '<i class="bi bi-robot"></i>';
    }
    
    var bubble = document.createElement('div');
    bubble.style.padding = '10px 14px';
    bubble.style.borderRadius = '14px';
    bubble.style.fontSize = '13px';
    bubble.style.lineHeight = '1.5';
    bubble.style.minWidth = '0';
    bubble.style.wordBreak = 'break-word';
    bubble.style.overflowWrap = 'anywhere';
    
    if (isUser) {
        bubble.style.background = 'var(--primary)';
        bubble.style.color = 'var(--primary-fg)';
        bubble.style.borderTopRightRadius = '4px';
    } else {
        bubble.style.background = 'var(--muted)';
        bubble.style.color = 'var(--fg)';
        bubble.style.border = '1px solid var(--border)';
        bubble.style.borderTopLeftRadius = '4px';
    }
    
    if (isHtml) bubble.innerHTML = text;
    else bubble.textContent = text;
    
    wrap.appendChild(avatar);
    wrap.appendChild(bubble);
    chat.appendChild(wrap);
    chat.scrollTop = chat.scrollHeight;
}

// ── Voice Input (Speech Recognition) ──
let _speechRec = null;
let _isListening = false;

function toggleVoiceInput() {
    var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRec) {
        alert('Fitur Suara (Voice to Text) membutuhkan browser Google Chrome atau Microsoft Edge.');
        return;
    }

    var btn = document.getElementById('voiceBtn');
    var input = document.getElementById('chatInput');

    if (_isListening) {
        if (_speechRec) _speechRec.stop();
        return;
    }

    _speechRec = new SpeechRec();
    _speechRec.lang = 'id-ID';
    _speechRec.continuous = false;
    _speechRec.interimResults = true;

    _speechRec.onstart = function() {
        _isListening = true;
        if (btn) {
            btn.style.background = '#ef4444';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#ef4444';
        }
        if (input) input.placeholder = 'Mendengarkan suara Anda... 🎙️';
    };

    _speechRec.onresult = function(event) {
        var text = '';
        for (var i = event.resultIndex; i < event.results.length; i++) {
            text += event.results[i][0].transcript;
        }
        if (input) input.value = text;
    };

    _speechRec.onerror = function() { stopVoiceState(); };
    _speechRec.onend = function() {
        stopVoiceState();
        var input = document.getElementById('chatInput');
        if (input && input.value.trim().length > 0) {
            handleChatSubmit(new Event('submit'));
        }
    };

    _speechRec.start();
}

function stopVoiceState() {
    _isListening = false;
    var btn = document.getElementById('voiceBtn');
    var input = document.getElementById('chatInput');
    if (btn) {
        btn.style.background = 'var(--muted)';
        btn.style.color = 'var(--muted-fg)';
        btn.style.borderColor = 'var(--border)';
    }
    if (input && !input.value) {
        input.placeholder = 'Ketik pesan / foto nota / bicara 🎙️...';
    }
}

function previewChatFile(input) {
    var file = (input && input.files && input.files[0]) ? input.files[0] : null;
    if (file) {
        setChatFileAttachedState(file);
    }
}

function setChatFileAttachedState(file) {
    var pName = document.getElementById('chatFileName');
    var pBox  = document.getElementById('chatFilePreview');
    var pImg  = document.getElementById('chatFileThumb');
    var clipBtn = document.getElementById('paperclipBtn');
    
    if (pName) pName.textContent = file.name;
    if (pBox)  pBox.style.display = 'flex';
    
    if (clipBtn) {
        clipBtn.style.background = 'rgba(34, 197, 94, 0.15)';
        clipBtn.style.color = '#22c55e';
        clipBtn.style.borderColor = '#22c55e';
    }

    if (file.type && file.type.startsWith('image/') && pImg) {
        var reader = new FileReader();
        reader.onload = function(e) {
            pImg.src = e.target.result;
            pImg.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else if (pImg) {
        pImg.style.display = 'none';
    }

    loadTesseractLazy();
}

function clearChatFile() {
    var fileInp = document.getElementById('chatFile');
    var clipBtn = document.getElementById('paperclipBtn');
    var pBox    = document.getElementById('chatFilePreview');
    var pImg    = document.getElementById('chatFileThumb');

    if (fileInp) fileInp.value = '';
    if (pBox)  pBox.style.display = 'none';
    if (pImg)  pImg.src = '';
    
    if (clipBtn) {
        clipBtn.style.background = 'var(--muted)';
        clipBtn.style.color = 'var(--muted-fg)';
        clipBtn.style.borderColor = 'var(--border)';
    }
}

// Setup Drag and Drop for Chat Photo Uploads
setTimeout(function() {
    var container = document.getElementById('chatMessages');
    var overlay   = document.getElementById('chatDropZone');
    if (!container || !overlay) return;

    ['dragenter', 'dragover'].forEach(function(evt) {
        container.addEventListener(evt, function(e) {
            e.preventDefault(); e.stopPropagation();
            overlay.style.display = 'flex';
        }, false);
    });

    ['dragleave', 'drop'].forEach(function(evt) {
        container.addEventListener(evt, function(e) {
            e.preventDefault(); e.stopPropagation();
            overlay.style.display = 'none';
        }, false);
    });

    container.addEventListener('drop', function(e) {
        var dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            var fileInp = document.getElementById('chatFile');
            if (fileInp) {
                fileInp.files = dt.files;
                previewChatFile(fileInp);
            }
        }
    }, false);
}, 200);

function handleChatSubmit(e) {
    e.preventDefault();
    var inp = document.getElementById('chatInput');
    var fileInp = document.getElementById('chatFile');
    var text = inp.value.trim();
    var fileObj = (fileInp.files && fileInp.files[0]) ? fileInp.files[0] : null;
    
    if (!text && !fileObj) return;
    
    inp.value = '';
    
    if (fileObj && !text) {
        appendMessage('<small style="opacity:.9"><i class="bi bi-file-earmark-image"></i> ' + fileObj.name + '</small>', true, true);
    } else if (fileObj && text) {
        appendMessage(text + '<br><small style="opacity:.8"><i class="bi bi-paperclip"></i> ' + fileObj.name + '</small>', true, true);
    } else {
        appendMessage(text, true);
    }
    clearChatFile();
    
    setTimeout(() => {
        parseTransaction(text, fileObj);
    }, 300);
}

function parseTransaction(text, fileObj) {
    const originalText = text;
    let amount = 0;
    
    if (text) {
        let processedText = text.toLowerCase();
        // 1. Parse composite million + thousand phrases first (e.g. 1juta500 / 1 juta 500 / 1juta 500rb -> 1500000)
        processedText = processedText.replace(/(\d+)\s*(?:jt|juta)\s*(\d{1,3})\s*(?:rb|ribu|k)?/gi, (m, p1, p2) => {
            let jt = parseInt(p1, 10) * 1000000;
            let rb = parseInt(p2, 10) * 1000;
            return (jt + rb).toString();
        });
        // 2. Strip thousand separator dots first (e.g. 10.000rb -> 10000rb)
        processedText = processedText.replace(/(\d+)\.(\d{3})(?!\d)/g, '$1$2').replace(/(\d+)\.(\d{3})(?!\d)/g, '$1$2');
        // 3. Parse million (jt / juta)
        processedText = processedText.replace(/(\d+(?:[.,]\d+)?)\s*(?:jt|juta)/gi, (m, p1) => {
            let val = parseFloat(p1.replace(',', '.'));
            return Math.round(val * 1000000);
        });
        // 4. Parse thousand (k / rb / ribu)
        processedText = processedText.replace(/(\d+(?:[.,]\d+)?)\s*(?:k|rb|ribu)/gi, (m, p1) => {
            let val = parseFloat(p1.replace(',', '.'));
            if (val >= 1000) return Math.round(val); // Already full nominal (e.g. 10000rb -> 10000)
            return Math.round(val * 1000);
        });

        const numMatches = processedText.match(/\d+/g);
        if (numMatches) {
            let nums = numMatches.map(n => parseInt(n, 10)).filter(n => n > 0);
            if (nums.length > 0) amount = Math.max(...nums);
        }
    }
    
    // If no text or no amount found, but image is attached -> Use OCR AI!
    if (amount <= 0 && fileObj) {
        processReceiptOCR(fileObj, originalText);
        return;
    }
    
    if (amount <= 0) {
        appendMessage("Maaf, saya tidak menemukan nominal. Mohon sertakan angka atau unggah foto nota.", false, true);
        return;
    }
    
    saveParsedTx(amount, originalText, fileObj);
}

function processReceiptOCR(fileObj, originalText) {
    let ocrId = 'ocr_' + Date.now();
    _pendingOcrFiles[ocrId] = fileObj;
    
    let loadingHtml = `
        <div id="${ocrId}">
            <div style="font-weight:600;margin-bottom:4px;color:var(--primary);"><i class="bi bi-cpu" style="animation:spin-once 1s infinite;"></i> Membaca Foto Struk/Nota (OCR)...</div>
            <p style="margin:0;font-size:12px;color:var(--muted-fg);">Mengekstrak teks & nominal angka dari gambar secara otomatis...</p>
        </div>
    `;
    appendMessage(loadingHtml, false, true);
    
    // Load Tesseract lazily then run OCR
    loadTesseractLazy(function() {
        if (typeof Tesseract !== 'undefined') {
            Tesseract.recognize(fileObj, 'ind+eng', { logger: function(){} }).then(function(r) {
                let parsed = parseOCRText(r.data.text);
                if (parsed.amount > 0) {
                    saveParsedTx(parsed.amount, parsed.desc, fileObj, parsed.category, ocrId);
                } else {
                    showOcrFallbackInput(ocrId, fileObj);
                }
            }).catch(function() { showOcrFallbackInput(ocrId, fileObj); });
        } else {
            showOcrFallbackInput(ocrId, fileObj);
        }
    });
}

function parseOCRText(ocrText) {
    // Normalize
    let lines = ocrText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    let amount = 0;
    let itemDesc = '';
    let category = 'Pengeluaran Lainnya';

    // Parse a money string to integer rupiah
    // Handles: 15.000 / 15,000 / Rp15.000 / 15.000,00 / 15000
    function parseMoney(str) {
        let s = str.toLowerCase().replace(/rp\.?\s*/g, '').trim();
        // Strip trailing cent-decimals like ,00 or .00
        s = s.replace(/[.,]00$/, '');
        let dotCount   = (s.match(/\./g) || []).length;
        let commaCount = (s.match(/,/g)  || []).length;
        if (dotCount > 1) {
            s = s.replace(/\./g, '');                  // 1.234.567 => 1234567
        } else if (commaCount > 1) {
            s = s.replace(/,/g, '');                   // 1,234,567 => 1234567
        } else if (dotCount === 1 && commaCount === 0) {
            s = s.replace('.', '');                    // 15.000 => 15000
        } else if (commaCount === 1 && dotCount === 0) {
            s = s.replace(',', '');                    // 15,000 => 15000
        } else if (dotCount === 1 && commaCount === 1) {
            // 1.234,56 => strip dot, strip comma+decimals
            s = s.replace(/\./g, '').replace(/,.*$/, '');
        }
        let n = parseInt(s.replace(/[^\d]/g, ''), 10);
        return isNaN(n) ? 0 : n;
    }

    // Extract ALL money-like values from a line
    function extractAllMoney(line) {
        let pattern = /(?:rp\.?\s*)?([\d]{1,3}(?:[.,]\d{3})+(?:[.,]\d{2})?|\d{4,})/gi;
        let simple   = /(?:rp\.?\s*)?(\d+)/gi;
        let results  = [];
        let m;
        // First try structured patterns (with separators)
        while ((m = pattern.exec(line)) !== null) {
            let v = parseMoney(m[0]);
            if (v >= 500 && v <= 500000000) results.push(v);
        }
        // If nothing found, try plain numbers >= 1000
        if (results.length === 0) {
            while ((m = simple.exec(line)) !== null) {
                let v = parseInt(m[1], 10);
                if (v >= 1000 && v <= 500000000) results.push(v);
            }
        }
        return results;
    }

    // Noise lines to skip entirely
    const NOISE = /(kembali|kembalian|change|bayar\s*tunai|tunai|cash\s*paid|telp|phone|hp:|npwp|no\.\s*(nota|trx)|tanggal|date:|waktu|jam:|kasir|cashier|alamat|address|thank|terima\s*kasih|member|ppn|dpp|diskon|disc|promo|poin|points?|nett:|include)/i;

    const TOTAL_P1 = /(grand\s*total|total\s*bayar|total\s*akhir|total\s*all|total\s*keseluruhan|net\s*total|total\s*nett)/i;
    const TOTAL_P2 = /\btotal\b/i;
    const TOTAL_P3 = /(subtotal|jumlah\s*tagihan|jumlah\s*bayar|jumlah\s*total|tagihan)/i;
    const TOTAL_P4 = /(subtotal|jumlah)/i;

    let validLines = lines.filter(l => !NOISE.test(l));

    // P1 – grand total variants
    for (let l of validLines) {
        if (!TOTAL_P1.test(l)) continue;
        let nums = extractAllMoney(l);
        if (nums.length > 0) { amount = nums[nums.length - 1]; break; }
    }
    // P2 – plain TOTAL (skip subtotal)
    if (!amount) {
        for (let l of validLines) {
            if (!TOTAL_P2.test(l) || /(subtotal|sub\s*total)/i.test(l)) continue;
            let nums = extractAllMoney(l);
            if (nums.length > 0) { amount = nums[nums.length - 1]; break; }
        }
    }
    // P3 – subtotal / jumlah tagihan
    if (!amount) {
        for (let l of validLines) {
            if (!TOTAL_P3.test(l)) continue;
            let nums = extractAllMoney(l);
            if (nums.length > 0) { amount = nums[nums.length - 1]; break; }
        }
    }
    // P4 – generic subtotal / jumlah
    if (!amount) {
        for (let l of validLines) {
            if (!TOTAL_P4.test(l)) continue;
            let nums = extractAllMoney(l);
            if (nums.length > 0) { amount = nums[nums.length - 1]; break; }
        }
    }
    // Fallback – largest money value across all valid lines
    if (!amount) {
        let all = [];
        validLines.forEach(l => extractAllMoney(l).forEach(v => all.push(v)));
        if (all.length > 0) amount = Math.max(...all);
    }

    // Item description: take meaningful text lines (skip store header & address)
    let descLines = lines.filter(l => {
        if (NOISE.test(l)) return false;
        if (/^\d+$/.test(l) || /^[*\-=]+$/.test(l)) return false;
        if (TOTAL_P1.test(l) || TOTAL_P2.test(l)) return false;
        if (/(jl\.|jalan|rt\s*\/?\s*rw|kel\.|kec\.|kota\s|prov\.|telp)/i.test(l)) return false;
        return /[a-zA-Z]{3,}/.test(l);
    });
    // Skip the very first line (usually store name)
    let bodyLines = descLines.length > 1 ? descLines.slice(1) : descLines;
    // Pick lines that look like item names (no pure price lines)
    let itemLines = bodyLines.filter(l => /[a-zA-Z]{3,}/.test(l)).slice(0, 3);
    itemDesc = itemLines.map(l => l.replace(/[\d.,]+/g, '').replace(/\s+/g, ' ').trim()).filter(l => l.length > 2).join(', ');

    // Category detection
    let textLow = ocrText.toLowerCase();
    if (/(pln|listrik|token listrik|pembayaran pln)/.test(textLow))                category = 'Biaya Operasional & Listrik';
    else if (/(pertamina|spbu|bensin|pertalite|solar|bbm)/.test(textLow))          category = 'Biaya Operasional & Listrik';
    else if (/(wifi|indihome|biznet|bandwidth|voucher\s*internet|internet)/.test(textLow)) category = 'Pembelian Bandwidth';
    else if (/(service|jasa teknik|perbaikan|servis)/.test(textLow))               category = 'Service & Jasa Teknik';
    else if (/(makan|minum|kopi|cafe|kafe|resto|restoran|warung|nasi|ayam|bakso|mie|pizza|burger|sate|food|beverage)/.test(textLow)) category = 'Makan & Minum';
    else if (/(atk|alat tulis|kertas|printer|tinta|fotocopy)/.test(textLow))      category = 'Perlengkapan Kantor';
    else if (/(apotek|klinik|rumah sakit|dokter|medical|obat)/.test(textLow))     category = 'Kesehatan';
    else if (/(tol|parkir|grab|gojek|taxi|bus|ojek)/.test(textLow))               category = 'Transportasi';
    else if (/(indomaret|alfamart|supermarket|minimarket|carrefour|hypermart|swalayan)/.test(textLow)) category = 'Belanja Harian';

    return {
        amount,
        category,
        desc: itemDesc ? 'Struk: ' + itemDesc : 'Struk Foto (OCR Auto)'
    };
}

function showOcrFallbackInput(ocrId, fileObj) {
    let container = document.getElementById(ocrId);
    if (container) {
        container.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:8px;width:230px;">
                <div style="font-weight:600;color:var(--fg);"><i class="bi bi-file-earmark-check" style="color:var(--primary);"></i> Foto Struk Terlampir</div>
                <p style="margin:0;font-size:12px;color:var(--muted-fg);">Foto nota tersimpan. Teks nominal pada foto kurang jelas, silakan ketik nominal angkanya:</p>
                <div style="display:flex;gap:6px;margin-top:4px;">
                    <input type="number" id="input_${ocrId}" placeholder="50000" style="flex:1;padding:6px 10px;border-radius:8px;border:1px solid var(--border);font-size:13px;background:var(--muted);color:var(--fg);">
                    <button type="button" onclick="submitOcrFallback('${ocrId}')" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </div>
        `;
    }
}

function submitOcrFallback(ocrId) {
    let input = document.getElementById('input_' + ocrId);
    let amount = parseFloat(input ? input.value : 0);
    let fileObj = _pendingOcrFiles[ocrId];
    if (amount > 0) {
        saveParsedTx(amount, 'Struk Foto', fileObj, 'Biaya Operasional & Listrik', ocrId);
    } else {
        alert('Masukkan nominal angka transaksi.');
    }
}

function saveParsedTx(amount, originalText, fileObj, forceCategory=null, ocrContainerId=null) {
    let type = 'expense';
    let typeLabel = 'Pengeluaran';
    let color = '#ef4444';
    let text = (originalText || '').toLowerCase();
    
    if (text.match(/(dapat|terima|jual|bayar tagihan|pemasukan|masuk|laba)/)) {
        type = 'income';
        typeLabel = 'Pemasukan';
        color = '#22c55e';
    }
    
    let category = forceCategory;
    let explicitCategoryName = null;

    if (!category) {
        // 1. Check if user explicitly wrote "kategori [nama_kategori]" or "masukin kategori [nama_kategori]"
        let explicitMatch = (originalText || '').match(/(?:masukin\s+|masuk\s+|ke\s+|pilih\s+|pakai\s+|dengan\s+)?kategori\s+([a-zA-Z0-9\s&/-]+)/i);
        if (explicitMatch && explicitMatch[1]) {
            let rawCatName = explicitMatch[1].trim();
            // Stop boundary at action verbs or numbers to separate category from description
            let stopMatch = rawCatName.match(/^([a-zA-Z\s&/-]+?)(?:\s+\b(?:beli|bayar|dapat|terima|jual|untuk|ke|dari|sebesar|sejumlah|\d+)\b|$)/i);
            if (stopMatch && stopMatch[1]) {
                rawCatName = stopMatch[1].trim();
            }
            if (rawCatName.length > 0) {
                explicitCategoryName = rawCatName;
                category = rawCatName.replace(/\b\w/g, l => l.toUpperCase());
            }
        }
        
        // 2. If no explicit category found, search by keyword matching
        if (!category) {
            let bestMatch = null;
            for (let c of categories) {
                let keywords = c.name.toLowerCase().split(' ');
                for (let k of keywords) {
                    if (k.length > 3 && text.includes(k)) {
                        bestMatch = c.name;
                        type = c.type;
                        typeLabel = type === 'income' ? 'Pemasukan' : 'Pengeluaran';
                        color = type === 'income' ? '#22c55e' : '#ef4444';
                        break;
                    }
                }
                if (bestMatch) break;
            }
            category = bestMatch || (type === 'income' ? 'Pemasukan Lainnya' : 'Pengeluaran Lainnya');
        }
    }

    // Auto-create category in DB if it doesn't exist yet
    if (category) {
        let exists = categories.some(c => c.name.toLowerCase() === category.toLowerCase());
        if (!exists) {
            let catFd = new FormData();
            catFd.append('action', 'add_category');
            catFd.append('name', category);
            catFd.append('type', type);
            fetch('index.php', { method: 'POST', body: catFd }).catch(e => {});
            categories.push({ name: category, type: type });
        }
    }

    let description = originalText || 'Struk Foto';
    if (explicitCategoryName) {
        let catRegex = new RegExp('(?:masukin\\s+|masuk\\s+|ke\\s+|pilih\\s+|pakai\\s+|dengan\\s+)?kategori\\s+' + explicitCategoryName.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'gi');
        let cleanDesc = (originalText || '').replace(catRegex, '').replace(/\s+/g, ' ').trim();
        if (cleanDesc.length > 0) {
            description = cleanDesc.replace(/\b\w/g, l => l.toUpperCase());
        }
    }

    let formData = new FormData();
    formData.append('action', 'add_transaction');
    formData.append('type', type);
    formData.append('category', category);
    formData.append('amount', amount);
    formData.append('description', description);
    formData.append('transaction_date', new Date().toISOString().split('T')[0]);
    formData.append('ajax', '1');
    if (fileObj) formData.append('receipt', fileObj);

    if (!ocrContainerId) {
        let loadingHtml = `
            <div style="display:flex;flex-direction:column;gap:8px;max-width:100%;width:210px;box-sizing:border-box;">
                <div style="font-weight:600;margin-bottom:4px;"><i class="bi bi-hourglass-split" style="animation:spin-once 1s infinite;"></i> Menyimpan...</div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Jenis</span><span style="color:${color};font-weight:600;">${typeLabel}</span></div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Kategori</span><span style="font-weight:500;text-align:right;word-break:break-word;min-width:0;">${category}</span></div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Nominal</span><span style="font-weight:700;">Rp ${amount.toLocaleString('id-ID')}</span></div>
            </div>
        `;
        appendMessage(loadingHtml, false, true);
    }

    fetch('index.php', {
        method: 'POST',
        body: formData
    }).then(function(res) {
        if (window.clearSPACache) window.clearSPACache();
        return res.json();
    }).then(function(data) {
        let targetEl = null;
        if (ocrContainerId) {
            targetEl = document.getElementById(ocrContainerId);
        } else {
            let msgNodes = document.getElementById('chatMessages').children;
            if (msgNodes.length > 0) {
                targetEl = msgNodes[msgNodes.length - 1].querySelector('div:last-child');
            }
        }
        
        let receiptHtml = '';
        if (data && data.receipt_path) {
            receiptHtml = `
                <div style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);">
                    <div onclick="openImageLightbox('${data.receipt_path}')" style="cursor:pointer;position:relative;display:inline-block;max-width:100%;">
                        <img src="${data.receipt_path}" style="max-height:130px;max-width:100%;object-fit:cover;border-radius:8px;border:1px solid var(--border);box-shadow:0 2px 8px rgba(0,0,0,.15);">
                        <div style="font-size:10.5px;color:var(--primary);margin-top:3px;font-weight:600;"><i class="bi bi-arrows-angle-expand"></i> Lihat Struk Foto</div>
                    </div>
                </div>
            `;
        } else if (fileObj) {
            receiptHtml = '<div style="font-size:11px;color:var(--muted-fg);margin-top:2px;word-break:break-all;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="bi bi-paperclip"></i> Struk: ' + fileObj.name + '</div>';
        }

        let successHtml = `
            <div style="display:flex;flex-direction:column;gap:6px;max-width:100%;width:220px;box-sizing:border-box;">
                <div style="font-weight:600;margin-bottom:2px;color:#22c55e;"><i class="bi bi-check-circle-fill"></i> Tersimpan Otomatis</div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Jenis</span><span style="color:${color};font-weight:600;">${typeLabel}</span></div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Kategori</span><span style="font-weight:500;text-align:right;word-break:break-word;min-width:0;">${category}</span></div>
                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;"><span style="color:var(--muted-fg);flex-shrink:0;">Nominal</span><span style="font-weight:700;">Rp ${amount.toLocaleString('id-ID')}</span></div>
                ${receiptHtml}
            </div>
        `;
        if (targetEl) targetEl.innerHTML = successHtml;
        saveMessageToServer(successHtml, 0, 1);
    }).catch(function(err) {
        appendMessage("Gagal menyimpan transaksi. Silakan coba lagi.", false);
    });
}

// Load persistent chat history on page init
loadChatHistory();
</script>

<script>
const AI_PRESETS = {
    none: { url: '', model: '', guide: '🤖 Bot Internal Bawaan Aktif — Tidak memerlukan API Key' },
    deepseek: { url: 'https://api.deepseek.com/v1', model: 'deepseek-chat', guide: '🔗 DeepSeek API Endpoint. API Key: platform.deepseek.com' },
    gemini: { url: 'https://generativelanguage.googleapis.com/v1beta/openai/', model: 'gemini-1.5-flash', guide: '🔗 Google Gemini OpenAI Endpoint. API Key: aistudio.google.com' },
    groq: { url: 'https://api.groq.com/openai/v1', model: 'llama-3.3-70b-versatile', guide: '🔗 Groq Super-Fast Endpoint. API Key gratis: console.groq.com' },
    openrouter: { url: 'https://openrouter.ai/api/v1', model: 'deepseek/deepseek-chat', guide: '🔗 OpenRouter Endpoint. API Key: openrouter.ai' },
    custom: { url: 'https://api.your-provider.com/v1', model: 'gpt-4o-mini', guide: '🔗 Custom OpenAI-Compatible Base URL & Model Name' }
};

function handleAiProviderChange(prov) {
    var formBox = document.getElementById('aiCredentialsForm');
    var urlInp = document.getElementById('aiBaseUrlInput');
    var modelInp = document.getElementById('aiModelInput');
    var guideEl = document.getElementById('aiUrlGuide');

    if (prov === 'none') {
        if (formBox) formBox.style.display = 'none';
        saveAiConfigAjax('none', '', '', '');
    } else {
        if (formBox) formBox.style.display = 'flex';
        var preset = AI_PRESETS[prov] || AI_PRESETS.custom;
        if (urlInp && (prov !== 'custom' || !urlInp.value)) urlInp.value = preset.url;
        if (modelInp && (prov !== 'custom' || !modelInp.value)) modelInp.value = preset.model;
        if (guideEl) guideEl.innerText = preset.guide;
    }
}

function toggleApiKeyVisibility() {
    var inp = document.getElementById('aiApiKeyInput');
    var icon = document.getElementById('eyeIcon');
    if (!inp || !icon) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function saveAiSettings() {
    var prov = document.getElementById('aiProviderSelect').value;
    var key  = document.getElementById('aiApiKeyInput').value.trim();
    var url  = document.getElementById('aiBaseUrlInput').value.trim();
    var mdl  = document.getElementById('aiModelInput').value.trim();

    saveAiConfigAjax(prov, key, url, mdl, function() {
        var msg = document.getElementById('aiSaveMsg');
        if (msg) {
            msg.style.display = 'block';
            setTimeout(function(){ msg.style.display = 'none'; }, 2500);
        }
    });
}

function saveAiConfigAjax(prov, key, url, mdl, cb) {
    let fd = new FormData();
    fd.append('action', 'save_ai_config');
    fd.append('provider', prov);
    fd.append('api_key', key);
    fd.append('base_url', url);
    fd.append('model', mdl);
    fetch('index.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(() => { if (cb) cb(); });
}
</script>

<!-- ── Modal: Pengaturan Chat & AI Assistant ── -->
<div id="modalChatSettings" class="modal-backdrop">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <span class="modal-title"><i class="bi bi-robot" style="color:var(--primary);"></i> Pengaturan Chat Bot & AI API</span>
            <button onclick="closeModal('modalChatSettings')" style="background:none;border:none;color:var(--muted-fg);cursor:pointer;font-size:22px;line-height:1;">&times;</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- AI Provider Selector -->
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:var(--fg);margin-bottom:6px;">Pilih AI Provider / API Gateway *</label>
                <select id="aiProviderSelect" onchange="handleAiProviderChange(this.value)" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--muted);color:var(--fg);font-size:13px;font-family:'Poppins',sans-serif;outline:none;">
                    <option value="none" <?= $aiProvider==='none'?'selected':'' ?>>🤖 Bot Internal Bawaan (Non-aktif / Tanpa API Key)</option>
                    <option value="deepseek" <?= $aiProvider==='deepseek'?'selected':'' ?>>🐋 DeepSeek AI (Murah & Cerdas)</option>
                    <option value="gemini" <?= $aiProvider==='gemini'?'selected':'' ?>>♊ Google Gemini (OpenAI Endpoint)</option>
                    <option value="groq" <?= $aiProvider==='groq'?'selected':'' ?>>⚡ Groq AI (Super Cepat & Gratis/Murah)</option>
                    <option value="openrouter" <?= $aiProvider==='openrouter'?'selected':'' ?>>🌐 OpenRouter AI (Multi-Model)</option>
                    <option value="custom" <?= $aiProvider==='custom'?'selected':'' ?>>⚙️ Custom OpenAI-Compatible API</option>
                </select>
                <span id="aiProviderHelp" style="font-size:11px;color:var(--muted-fg);margin-top:6px;display:block;">
                    Gunakan Bot bawaan gratis tanpa API Key, atau hubungkan API Key AI favorit Anda untuk percakapan cerdas.
                </span>
            </div>

            <!-- AI Credentials Form -->
            <div id="aiCredentialsForm" style="display:<?= $aiProvider==='none'?'none':'flex' ?>;flex-direction:column;gap:12px;padding:14px;background:var(--bg);border:1px solid var(--border);border-radius:12px;">
                <div class="field">
                    <label style="font-size:11px;font-weight:600;color:var(--fg);">API Key *</label>
                    <div style="position:relative;">
                        <input type="password" id="aiApiKeyInput" value="<?= htmlspecialchars($aiApiKey) ?>" placeholder="sk-..." style="width:100%;padding:9px 36px 9px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--fg);font-size:12px;outline:none;">
                        <button type="button" onclick="toggleApiKeyVisibility()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted-fg);cursor:pointer;padding:0;">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="field">
                    <label style="font-size:11px;font-weight:600;color:var(--fg);">API Gateway Base URL *</label>
                    <input type="text" id="aiBaseUrlInput" value="<?= htmlspecialchars($aiBaseUrl) ?>" placeholder="https://api.deepseek.com/v1" style="width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--fg);font-size:12px;outline:none;">
                    <span id="aiUrlGuide" style="font-size:10px;color:var(--primary);margin-top:4px;display:block;word-break:break-all;"></span>
                </div>

                <div class="field">
                    <label style="font-size:11px;font-weight:600;color:var(--fg);">Model Name *</label>
                    <input type="text" id="aiModelInput" value="<?= htmlspecialchars($aiModel) ?>" placeholder="deepseek-chat" style="width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--fg);font-size:12px;outline:none;">
                </div>

                <div id="aiSaveMsg" style="display:none;font-size:11px;color:#22c55e;font-weight:600;"><i class="bi bi-check-circle-fill"></i> Konfigurasi AI Berhasil Disimpan!</div>

                <button type="button" onclick="saveAiSettings()" class="btn btn-primary btn-sm w-full" style="margin-top:4px;">
                    <i class="bi bi-check-circle"></i> Simpan Konfigurasi AI
                </button>
            </div>

            <!-- Chat Auto Delete -->
            <div class="field" style="border-top:1px dashed var(--border);padding-top:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--fg);margin-bottom:6px;">Hapus Otomatis Riwayat Chat *</label>
                <select id="chatAutoDeleteSelect" onchange="saveChatAutoDeleteConfig(this.value)" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--muted);color:var(--fg);font-size:13px;">
                    <option value="never" <?= $chatAutoDelete==='never'?'selected':'' ?>>Jangan Pernah Hapus (Simpan Selamanya)</option>
                    <option value="1d" <?= $chatAutoDelete==='1d'?'selected':'' ?>>Hapus Otomatis Setelah 1 Hari</option>
                    <option value="1w" <?= $chatAutoDelete==='1w'?'selected':'' ?>>Hapus Otomatis Setelah 1 Minggu</option>
                    <option value="1m" <?= $chatAutoDelete==='1m'?'selected':'' ?>>Hapus Otomatis Setelah 1 Bulan</option>
                </select>
                <span style="font-size:11px;color:var(--muted-fg);margin-top:6px;display:block;">Pesan chat yang lebih lama dari jangka waktu yang dipilih akan dihapus secara otomatis.</span>
            </div>

            <div style="border-top:1px dashed var(--border);padding-top:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#ef4444;margin-bottom:6px;">Bersihkan Percakapan</label>
                <button type="button" onclick="clearChatHistory()" class="btn btn-outline w-full" style="color:#ef4444;border-color:rgba(239,68,68,.3);justify-content:center;">
                    <i class="bi bi-trash"></i> Hapus Riwayat Chat Sekarang
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal('modalChatSettings')" class="btn btn-outline w-full">Tutup</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
