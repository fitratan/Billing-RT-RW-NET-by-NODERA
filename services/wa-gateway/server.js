require('dotenv').config();
const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const pino = require('pino');
const QRCode = require('qrcode');
const multer = require('multer');
const axios = require('axios');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    delay
} = require('@whiskeysockets/baileys');

const app = express();
const upload = multer({ limits: { fileSize: 30 * 1024 * 1024 } }); // 30MB

app.use(cors());
app.use(express.json({ limit: '30mb' }));
app.use(express.urlencoded({ extended: true, limit: '30mb' }));

// Configuration
const PORT = parseInt(process.env.PORT || '3000', 10);
const HOST = process.env.HOST || '0.0.0.0';
const MASTER_API_KEY = process.env.API_KEY || 'nodera_wa_universal_secret_2026';
const AUTH_DIR = path.resolve(__dirname, process.env.AUTH_DIR || './auth_sessions');
const DELAY_MIN = parseInt(process.env.MESSAGE_DELAY_MIN || '1500', 10);
const DELAY_MAX = parseInt(process.env.MESSAGE_DELAY_MAX || '3000', 10);

// Ensure base auth directory exists
if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
}

// Global logger
const logger = pino({ level: 'silent' });

/**
 * Multi-Session Manager Map
 * Key: sessionId (string) -> Value: Session Object
 */
const sessions = new Map();

/**
 * Message Queue per session
 */
const messageQueues = new Map();
const queueProcessing = new Map();

function formatToJid(phone) {
    if (!phone) return null;
    let clean = String(phone).replace(/[^0-9]/g, '');

    // Indonesia prefix conversion
    if (clean.startsWith('08')) {
        clean = '628' + clean.slice(2);
    } else if (clean.startsWith('8') && clean.length >= 9 && clean.length <= 13) {
        clean = '62' + clean;
    }

    // Côte d'Ivoire (+225) 10-digit
    if (clean.length === 10 && (clean.startsWith('01') || clean.startsWith('05') || clean.startsWith('07') || clean.startsWith('27'))) {
        clean = '225' + clean;
    }

    if (!clean.endsWith('@s.whatsapp.net')) {
        clean = clean + '@s.whatsapp.net';
    }

    return clean;
}

function getSessionAuthDir(sessionId) {
    const cleanId = String(sessionId).replace(/[^a-zA-Z0-9_-]/g, '_');
    const dir = path.join(AUTH_DIR, cleanId);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return dir;
}

/**
 * Initialize or get WhatsApp Session
 */
async function initSession(sessionId = 'default', options = {}) {
    const cleanId = String(sessionId).replace(/[^a-zA-Z0-9_-]/g, '_');
    
    if (sessions.has(cleanId)) {
        const existing = sessions.get(cleanId);
        if (existing.status === 'CONNECTED' || existing.status === 'CONNECTING') {
            return existing;
        }
    }

    const sessionDir = getSessionAuthDir(cleanId);
    const sessionObj = {
        id: cleanId,
        sock: null,
        status: 'INITIALIZING', // INITIALIZING, QR_READY, CONNECTING, CONNECTED, DISCONNECTED
        qrCodeRaw: null,
        qrCodeDataUrl: null,
        phone: null,
        name: null,
        pairingCode: null,
        webhookUrl: options.webhookUrl || null,
        apiKey: options.apiKey || MASTER_API_KEY,
        retries: 0,
        createdAt: new Date().toISOString()
    };

    sessions.set(cleanId, sessionObj);

    try {
        const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
        const { version } = await fetchLatestBaileysVersion();

        const sock = makeWASocket({
            version,
            logger,
            printQRInTerminal: false,
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, logger),
            },
            browser: ['NODERA Universal Gateway', 'Chrome', '3.0.0'],
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
            generateHighQualityLinkPreview: true,
        });

        sessionObj.sock = sock;

        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                sessionObj.qrCodeRaw = qr;
                try {
                    sessionObj.qrCodeDataUrl = await QRCode.toDataURL(qr, { margin: 2, scale: 8 });
                    sessionObj.status = 'QR_READY';
                    console.log(`[WA-GATEWAY] [${cleanId}] New QR Code ready.`);
                } catch (err) {
                    console.error(`[WA-GATEWAY] [${cleanId}] Error generating QR:`, err.message);
                }
            }

            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                sessionObj.status = 'DISCONNECTED';
                sessionObj.qrCodeRaw = null;
                sessionObj.qrCodeDataUrl = null;
                sessionObj.phone = null;
                sessionObj.name = null;
                sessionObj.pairingCode = null;

                console.log(`[WA-GATEWAY] [${cleanId}] Connection closed (code: ${statusCode}). Reconnect: ${shouldReconnect}`);

                if (shouldReconnect) {
                    setTimeout(() => {
                        initSession(cleanId, { webhookUrl: sessionObj.webhookUrl, apiKey: sessionObj.apiKey });
                    }, 4000);
                } else {
                    console.log(`[WA-GATEWAY] [${cleanId}] Logged out. Resetting auth directory...`);
                    try {
                        fs.rmSync(sessionDir, { recursive: true, force: true });
                    } catch (e) {}
                    setTimeout(() => {
                        initSession(cleanId, { webhookUrl: sessionObj.webhookUrl, apiKey: sessionObj.apiKey });
                    }, 2000);
                }
            } else if (connection === 'open') {
                sessionObj.status = 'CONNECTED';
                sessionObj.qrCodeRaw = null;
                sessionObj.qrCodeDataUrl = null;
                sessionObj.pairingCode = null;

                const userJid = sock.user?.id || '';
                sessionObj.phone = userJid.split(':')[0] || userJid.split('@')[0];
                sessionObj.name = sock.user?.name || `Session ${cleanId}`;

                console.log(`[WA-GATEWAY] [${cleanId}] WhatsApp Connected: +${sessionObj.phone} (${sessionObj.name})`);
                processQueue(cleanId);
            }
        });

        // Inbound Message Webhook Dispatcher
        sock.ev.on('messages.upsert', async (chatUpdate) => {
            try {
                if (chatUpdate.type !== 'notify') return;
                for (const msg of chatUpdate.messages) {
                    if (msg.key.fromMe) continue;

                    const senderJid = msg.key.remoteJid;
                    const senderPhone = senderJid?.split('@')[0];
                    const pushName = msg.pushName || '';
                    const messageContent = msg.message?.conversation || 
                                           msg.message?.extendedTextMessage?.text || 
                                           msg.message?.imageMessage?.caption || 
                                           msg.message?.videoMessage?.caption || '';

                    const webhookUrl = sessionObj.webhookUrl || process.env.GLOBAL_WEBHOOK_URL;
                    if (webhookUrl) {
                        axios.post(webhookUrl, {
                            event: 'message.received',
                            session: cleanId,
                            sender: senderPhone,
                            sender_jid: senderJid,
                            push_name: pushName,
                            message: messageContent,
                            timestamp: msg.messageTimestamp,
                            raw: msg
                        }, { timeout: 10000 }).catch(err => {
                            console.error(`[WA-GATEWAY] [${cleanId}] Webhook dispatch error:`, err.message);
                        });
                    }
                }
            } catch (err) {
                console.error(`[WA-GATEWAY] [${cleanId}] messages.upsert error:`, err.message);
            }
        });

        return sessionObj;
    } catch (error) {
        console.error(`[WA-GATEWAY] [${cleanId}] Failed to init session:`, error.message);
        sessionObj.status = 'DISCONNECTED';
        return sessionObj;
    }
}

/**
 * Message Queue processor per session
 */
async function processQueue(sessionId) {
    if (queueProcessing.get(sessionId)) return;

    const queue = messageQueues.get(sessionId) || [];
    if (queue.length === 0) return;

    const session = sessions.get(sessionId);
    if (!session || session.status !== 'CONNECTED' || !session.sock) return;

    queueProcessing.set(sessionId, true);

    while (queue.length > 0) {
        const item = queue.shift();
        const { jid, payload, resolve, reject, retries = 0 } = item;

        try {
            const randomDelay = Math.floor(Math.random() * (DELAY_MAX - DELAY_MIN + 1)) + DELAY_MIN;
            await delay(randomDelay);

            // Simulation of typing presence
            try { await session.sock.sendPresenceUpdate('composing', jid); } catch (e) {}
            await delay(400);

            const result = await session.sock.sendMessage(jid, payload);
            try { await session.sock.sendPresenceUpdate('paused', jid); } catch (e) {}

            resolve({
                success: true,
                session: sessionId,
                messageId: result?.key?.id,
                jid,
                timestamp: Date.now()
            });
        } catch (error) {
            console.error(`[WA-GATEWAY] [${sessionId}] Send error to ${jid}:`, error.message);
            if (retries < 2) {
                queue.push({ jid, payload, resolve, reject, retries: retries + 1 });
            } else {
                reject(error);
            }
        }
    }

    queueProcessing.set(sessionId, false);
}

function enqueueMessage(sessionId, jid, payload) {
    return new Promise((resolve, reject) => {
        if (!messageQueues.has(sessionId)) {
            messageQueues.set(sessionId, []);
        }
        messageQueues.get(sessionId).push({ jid, payload, resolve, reject, retries: 0 });
        processQueue(sessionId);
    });
}

// Auth Middleware
function authenticate(req, res, next) {
    const key = req.headers['x-api-key'] || req.query.api_key || req.headers['authorization']?.replace('Bearer ', '') || req.body.token || req.body.api_key || req.body.apikey;
    
    // Check master key
    if (MASTER_API_KEY && key === MASTER_API_KEY) {
        return next();
    }

    // Check session-level key
    const targetSession = req.params.sessionId || req.body.session || req.body.device || req.body.device_id || 'default';
    if (sessions.has(targetSession)) {
        const session = sessions.get(targetSession);
        if (session.apiKey && key === session.apiKey) {
            return next();
        }
    }

    return res.status(401).json({
        success: false,
        error: 'Unauthorized: Invalid API Key. Provide valid X-API-Key header or token parameter.'
    });
}

// ── REST API ROUTES ──

/**
 * List all sessions & status
 */
app.get('/api/sessions', authenticate, (req, res) => {
    const list = Array.from(sessions.values()).map(s => ({
        id: s.id,
        status: s.status,
        connected: s.status === 'CONNECTED',
        phone: s.phone,
        name: s.name,
        webhookUrl: s.webhookUrl,
        queue_length: (messageQueues.get(s.id) || []).length,
        createdAt: s.createdAt
    }));

    res.json({
        success: true,
        total: list.length,
        data: list
    });
});

/**
 * Create or initialize new session
 */
app.post('/api/sessions/create', authenticate, async (req, res) => {
    const sessionId = req.body.sessionId || req.body.session || req.body.name || `session_${Date.now()}`;
    const webhookUrl = req.body.webhookUrl || req.body.webhook || null;
    const apiKey = req.body.apiKey || null;

    const session = await initSession(sessionId, { webhookUrl, apiKey });
    res.json({
        success: true,
        message: `Session '${session.id}' initialized.`,
        session: {
            id: session.id,
            status: session.status,
            phone: session.phone
        }
    });
});

/**
 * Get QR Code for a session
 */
app.get('/api/sessions/:sessionId/qr', authenticate, async (req, res) => {
    const sessionId = req.params.sessionId;
    let session = sessions.get(sessionId);

    if (!session) {
        session = await initSession(sessionId);
    }

    if (session.status === 'CONNECTED') {
        return res.json({
            success: true,
            status: 'CONNECTED',
            message: 'Device is already connected.',
            phone: session.phone,
            name: session.name
        });
    }

    if (!session.qrCodeDataUrl) {
        return res.json({
            success: false,
            status: session.status,
            message: 'QR Code is generating, please retry in 2 seconds...'
        });
    }

    res.json({
        success: true,
        status: 'QR_READY',
        session: sessionId,
        qr_image: session.qrCodeDataUrl,
        qr_raw: session.qrCodeRaw
    });
});

/**
 * Request Pairing Code for phone number linking
 */
app.post('/api/sessions/:sessionId/pairing-code', authenticate, async (req, res) => {
    const sessionId = req.params.sessionId;
    const { phone, number } = req.body;
    const targetPhone = String(phone || number || '').replace(/[^0-9]/g, '');

    if (!targetPhone) {
        return res.status(400).json({ success: false, error: 'Phone number is required for pairing code.' });
    }

    let session = sessions.get(sessionId);
    if (!session) {
        session = await initSession(sessionId);
    }

    if (session.status === 'CONNECTED') {
        return res.json({ success: true, status: 'CONNECTED', message: 'Device is already connected.' });
    }

    try {
        if (!session.sock) {
            return res.status(500).json({ success: false, error: 'Socket not initialized.' });
        }

        const code = await session.sock.requestPairingCode(targetPhone);
        session.pairingCode = code;

        res.json({
            success: true,
            status: 'PAIRING_CODE_READY',
            session: sessionId,
            phone: targetPhone,
            pairing_code: code
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message || 'Failed to generate pairing code.'
        });
    }
});

/**
 * Logout session
 */
app.post('/api/sessions/:sessionId/logout', authenticate, async (req, res) => {
    const sessionId = req.params.sessionId;
    const session = sessions.get(sessionId);

    if (session) {
        if (session.sock) {
            try { await session.sock.logout(); } catch (e) {}
            try { session.sock.end(); } catch (e) {}
        }
        const sessionDir = getSessionAuthDir(sessionId);
        try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch (e) {}

        session.status = 'INITIALIZING';
        session.phone = null;
        session.name = null;
        session.qrCodeRaw = null;
        session.qrCodeDataUrl = null;

        setTimeout(() => {
            initSession(sessionId, { webhookUrl: session.webhookUrl, apiKey: session.apiKey });
        }, 1500);

        return res.json({ success: true, message: `Session '${sessionId}' logged out successfully.` });
    }

    res.status(404).json({ success: false, error: 'Session not found.' });
});

/**
 * Delete session completely
 */
app.delete('/api/sessions/:sessionId', authenticate, async (req, res) => {
    const sessionId = req.params.sessionId;
    const session = sessions.get(sessionId);

    if (session) {
        if (session.sock) {
            try { await session.sock.logout(); } catch (e) {}
            try { session.sock.end(); } catch (e) {}
        }
        const sessionDir = getSessionAuthDir(sessionId);
        try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch (e) {}

        sessions.delete(sessionId);
        messageQueues.delete(sessionId);
        queueProcessing.delete(sessionId);

        return res.json({ success: true, message: `Session '${sessionId}' deleted.` });
    }

    res.status(404).json({ success: false, error: 'Session not found.' });
});

/**
 * Universal Send Message Handler
 */
async function handleSendMessage(req, res) {
    try {
        const { phone, target, number, to, receiver, nohp, no_hp, message, text, msg, pesan, body, session, device, device_id } = req.body;
        const targetPhone = phone || target || number || to || receiver || nohp || no_hp;
        const messageText = message || text || msg || pesan || body;
        const sessionId = req.params.sessionId || session || device || device_id || 'default';

        if (!targetPhone) {
            return res.status(400).json({ success: false, error: 'Target phone number is required (field: phone).' });
        }
        if (!messageText) {
            return res.status(400).json({ success: false, error: 'Message content is required (field: message).' });
        }

        const jid = formatToJid(targetPhone);
        if (!jid) {
            return res.status(400).json({ success: false, error: 'Invalid phone number format.' });
        }

        let currentSession = sessions.get(sessionId);
        if (!currentSession) {
            // Auto fallback to first connected session if 'default' requested but not connected
            for (const s of sessions.values()) {
                if (s.status === 'CONNECTED') {
                    currentSession = s;
                    break;
                }
            }
        }

        if (!currentSession || currentSession.status !== 'CONNECTED') {
            return res.status(503).json({
                success: false,
                status: currentSession ? currentSession.status : 'NOT_FOUND',
                error: `WhatsApp session '${sessionId}' is not connected. Please scan QR Code first.`
            });
        }

        const result = await enqueueMessage(currentSession.id, jid, { text: String(messageText) });

        res.json({
            success: true,
            message: 'Message queued and sent successfully.',
            session: currentSession.id,
            data: result
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message || 'Internal error sending message.'
        });
    }
}

/**
 * Universal Send Media Handler
 */
async function handleSendMedia(req, res) {
    try {
        const { phone, target, number, to, caption, media_url, type, filename, session, device, device_id } = req.body;
        const targetPhone = phone || target || number || to;
        const sessionId = req.params.sessionId || session || device || device_id || 'default';

        if (!targetPhone) {
            return res.status(400).json({ success: false, error: 'Target phone number is required.' });
        }

        const jid = formatToJid(targetPhone);
        let currentSession = sessions.get(sessionId);
        if (!currentSession || currentSession.status !== 'CONNECTED') {
            for (const s of sessions.values()) {
                if (s.status === 'CONNECTED') {
                    currentSession = s;
                    break;
                }
            }
        }

        if (!currentSession || currentSession.status !== 'CONNECTED') {
            return res.status(503).json({ success: false, error: `WhatsApp session '${sessionId}' not connected.` });
        }

        let mediaPayload = null;

        if (req.file) {
            const mimetype = req.file.mimetype;
            const buffer = req.file.buffer;
            const originalname = filename || req.file.originalname || 'document.pdf';

            if (mimetype.startsWith('image/')) {
                mediaPayload = { image: buffer, caption: caption || '' };
            } else if (mimetype.startsWith('video/')) {
                mediaPayload = { video: buffer, caption: caption || '' };
            } else {
                mediaPayload = {
                    document: buffer,
                    mimetype,
                    fileName: originalname,
                    caption: caption || ''
                };
            }
        } else if (media_url) {
            const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(media_url);
            const isVideo = /\.(mp4|3gp|mov)$/i.test(media_url);

            if (isImage || type === 'image') {
                mediaPayload = { image: { url: media_url }, caption: caption || '' };
            } else if (isVideo || type === 'video') {
                mediaPayload = { video: { url: media_url }, caption: caption || '' };
            } else {
                mediaPayload = {
                    document: { url: media_url },
                    mimetype: 'application/pdf',
                    fileName: filename || 'document.pdf',
                    caption: caption || ''
                };
            }
        } else {
            return res.status(400).json({ success: false, error: 'File upload or media_url is required.' });
        }

        const result = await enqueueMessage(currentSession.id, jid, mediaPayload);
        res.json({
            success: true,
            message: 'Media message queued successfully.',
            session: currentSession.id,
            data: result
        });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
}

// Universal Send Endpoints
app.post('/api/send-message', authenticate, handleSendMessage);
app.post('/api/send', authenticate, handleSendMessage);
app.post('/send-message', authenticate, handleSendMessage);
app.post('/send', authenticate, handleSendMessage);
app.post('/api/sessions/:sessionId/send-message', authenticate, handleSendMessage);

app.post('/api/send-media', authenticate, upload.single('file'), handleSendMedia);
app.post('/send-media', authenticate, upload.single('file'), handleSendMedia);
app.post('/api/sessions/:sessionId/send-media', authenticate, upload.single('file'), handleSendMedia);

/**
 * Global Gateway Health & Telemetry
 */
app.get('/api/status', (req, res) => {
    const totalSessions = sessions.size;
    const connectedSessions = Array.from(sessions.values()).filter(s => s.status === 'CONNECTED').length;

    res.json({
        success: true,
        engine: 'NODERA Universal Baileys WA Engine v2.0',
        uptime_seconds: process.uptime(),
        total_sessions: totalSessions,
        connected_sessions: connectedSessions,
        timestamp: new Date().toISOString()
    });
});

/**
 * Auto-discover & restore existing sessions from disk
 */
async function autoRestoreSessions() {
    try {
        const entries = fs.readdirSync(AUTH_DIR, { withFileTypes: true });
        const sessionDirs = entries.filter(e => e.isDirectory()).map(e => e.name);

        if (sessionDirs.length === 0) {
            console.log('[WA-GATEWAY] No saved sessions found. Initializing default session...');
            await initSession('default');
        } else {
            console.log(`[WA-GATEWAY] Found ${sessionDirs.length} saved session(s). Restoring:`, sessionDirs.join(', '));
            for (const dir of sessionDirs) {
                await initSession(dir);
            }
        }
    } catch (err) {
        console.error('[WA-GATEWAY] Error auto-restoring sessions:', err.message);
        await initSession('default');
    }
}

// Start Server
app.listen(PORT, HOST, () => {
    console.log(`\n========================================================`);
    console.log(`  🚀 NODERA UNIVERSAL WHATSAPP GATEWAY (MULTI-SESSION)`);
    console.log(`  ------------------------------------------------------`);
    console.log(`  Server Listening on : http://${HOST}:${PORT}`);
    console.log(`  Master API Key      : ${MASTER_API_KEY}`);
    console.log(`  Auth Directory      : ${AUTH_DIR}`);
    console.log(`========================================================\n`);

    autoRestoreSessions();
});
