<!doctype html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Push Test — FORCE MODE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: system-ui;
            padding: 16px;
            background: #fafafa
        }

        #wrap {
            max-width: 900px;
            margin: auto
        }

        #log {
            background: #fff;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            max-height: 70vh;
            overflow: auto
        }

        .msg {
            padding: 10px;
            margin: 6px 0;
            border-radius: 6px;
            background: #e8f5e9;
            border-left: 4px solid #2e7d32
        }

        .err {
            padding: 10px;
            margin: 6px 0;
            border-radius: 6px;
            background: #ffebee;
            border-left: 4px solid #c62828
        }
    </style>
</head>

<body>
    <div id="wrap">

        <h2>Push Notification Test (FORCE MODE)</h2>

        <p>Device ID:
            <strong id="did">{{ $deviceId }}</strong>
        </p>

        <button onclick="registerDevice()">Register</button>
        <button onclick="requestPerm()">Notification Permission</button>

        <div id="wsStatus" class="msg">WebSocket: connecting…</div>

        <h3>Logs</h3>
        <div id="log"></div>

    </div>

    <script>
        /* -------------------------
       Config from environment
       ------------------------- */
        // Use env values directly (blade renders env into JS)
        const WS_KEY = "{{ env('REVERB_APP_KEY') }}"; // your app key
        const WS_HOST = "{{ env('REVERB_HOST', request()->getHost()) }}"; // PC LAN IP
        const WS_PORT = "{{ env('REVERB_PORT', 8080) }}";
        const WS_SCHEME = "{{ env('REVERB_SCHEME', 'http') }}";
        const WS_PROTO = WS_SCHEME === 'https' ? 'wss' : 'ws';

        window.deviceId = "{{ $deviceId }}";
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const LOG = document.getElementById("log");

        function log(msg, type = "msg") {
            const div = document.createElement("div");
            div.className = type;
            div.innerText = new Date().toLocaleTimeString() + " — " + msg;
            LOG.prepend(div);
            console.log(msg);
        }
        document.getElementById("did").innerText = window.deviceId;

        /* -------------------------
           Device registration
           ------------------------- */
        async function registerDevice() {
            try {
                const res = await fetch("/api/devices/register", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": CSRF
                    },
                    body: JSON.stringify({
                        device_id: window.deviceId,
                        platform: "web",
                        token: null,
                        capabilities: {
                            realtime: true
                        }
                    })
                });
                const j = await res.json();
                log("REGISTER RESPONSE: " + JSON.stringify(j));
            } catch (err) {
                log("REGISTER ERROR: " + err, "err");
            }
        }

        /* -------------------------
           Notification permission
           ------------------------- */
        async function requestPerm() {
            const p = await Notification.requestPermission();
            log("Browser Notification Permission: " + p);
        }

        /* -------------------------
           WebSocket connection (no Echo)
           ------------------------- */
        // Build a WS URL using your PC LAN IP (WS_KEY must match server)
        const wsUrl = `${WS_PROTO}://${WS_HOST}:${WS_PORT}/app/${WS_KEY}?protocol=7&client=js&version=8.1.0`;
        log("WebSocket URL: " + wsUrl);
        document.getElementById("wsStatus").innerText = "WebSocket: connecting…";

        let socket;

        function startSocket() {
            try {
                socket = new WebSocket(wsUrl);

                socket.onopen = function() {
                    log("WEBSOCKET CONNECTED");
                    document.getElementById("wsStatus").innerText = "WebSocket: Connected";

                    // subscribe to device channel
                    const subscribeMessage = {
                        event: "pusher:subscribe",
                        data: {
                            channel: "devices." + window.deviceId
                        }
                    };
                    socket.send(JSON.stringify(subscribeMessage));
                    log("Sent subscribe for devices." + window.deviceId);
                };

                socket.onerror = function(e) {
                    log("WEBSOCKET ERROR: " + JSON.stringify(e), "err");
                };

                socket.onclose = function() {
                    log("WEBSOCKET CLOSED", "err");
                    document.getElementById("wsStatus").innerText = "WebSocket: Disconnected";
                    // try reconnect after a short delay
                    setTimeout(() => {
                        log("Reconnecting...");
                        startSocket();
                    }, 2000);
                };

                socket.onmessage = function(event) {
                    // show raw message in log for debugging
                    log("RAW MESSAGE: " + event.data);

                    let msg;
                    try {
                        msg = JSON.parse(event.data);
                    } catch (e) {
                        // not JSON — ignore
                        log("raw parse error", "err");
                        return;
                    }

                    // Handle pusher/internal events quickly
                    if (msg.event && msg.event.indexOf('pusher_internal') === 0) {
                        log("Internal event: " + msg.event);
                        if (msg.event === 'pusher_internal:subscription_succeeded') {
                            log("Subscription confirmed for channel devices." + window.deviceId);
                        }
                        return;
                    }

                    // If server returns error
                    if (msg.event === 'pusher:error' || msg.event === 'error') {
                        log("PUSHER ERROR: " + JSON.stringify(msg.data || msg), "err");
                        return;
                    }

                    // Determine the event name (some servers namespace it)
                    const eventName = msg.event || (msg.data && msg.data.event) || null;
                    log("Event name: " + (eventName || '(none)'));

                    // Extract payload candidate(s)
                    // Possible shapes:
                    // 1) { event: "PushNotificationBroadcast", data: { title:..., body:... } }
                    // 2) { event: "PushNotificationBroadcast", data: "{\"title\":...,\"body\":...}" } (stringified)
                    // 3) { event: "App\\Events\\PushNotificationBroadcast", data: { /* ... */ } }
                    // 4) { event: "...", data: { data: { title:..., body:... } } } (double-wrapped)
                    // 5) { event: "...", data: "{\"data\":{...}}" } (stringified wrapper)
                    let payload = null;

                    // helper to try parse string -> object safely
                    function tryParse(s) {
                        if (!s) return null;
                        if (typeof s === 'object') return s;
                        try {
                            return JSON.parse(s);
                        } catch (e) {
                            return null;
                        }
                    }

                    // try a few locations
                    payload = tryParse(msg.data) || tryParse(msg.data && msg.data.data) || tryParse(msg.data && msg.data
                        .payload) || tryParse(msg);

                    // If payload contains an inner "data" property (Laravel sometimes wraps), unwrap it
                    if (payload && payload.data && typeof payload.data === 'object' && (payload.title === undefined &&
                            payload.body === undefined)) {
                        payload = payload.data;
                    }

                    // final fallback: if we still have no object but msg has 'message' or 'notification' fields
                    if (!payload) {
                        payload = {};
                        if (msg.notification) payload = msg.notification;
                        if (msg.message) payload.body = msg.message;
                        if (msg.title) payload.title = msg.title;
                    }

                    // Extract title/body
                    const title = (payload && (payload.title || payload.notification?.title)) || 'Notification';
                    const body = (payload && (payload.body || payload.notification?.body || payload.message || JSON
                        .stringify(payload))) || '';

                    // ALWAYS show an alert (force visible)
                    try {
                        alert("PUSH RECEIVED:\n" + title + "\n" + (body || ''));
                    } catch (e) {
                        console.warn('alert failed', e);
                    }

                    // Add visible card
                    try {
                        const div = document.createElement('div');
                        div.style.padding = '10px';
                        div.style.border = '1px solid #ddd';
                        div.style.background = '#fff';
                        div.style.marginTop = '6px';
                        div.innerText = "[RECEIVED] " + title + " — " + (body || JSON.stringify(payload));
                        logEl.prepend(div);
                    } catch (e) {
                        console.warn('render card failed', e);
                    }

                    // Try show OS notification if permission granted (works on HTTPS / ngrok)
                    if (Notification.permission === 'granted') {
                        try {
                            new Notification(title, {
                                body
                            });
                        } catch (e) {
                            console.warn('Notification API error', e);
                        }
                    }

                    // Also constantly print the parsed payload to console for debugging
                    console.log("PARSED PAYLOAD:", payload);
                };

            } catch (e) {
                log("Start socket failed: " + e, "err");
            }
        }

        /* -------------------------
           Auto start actions
           ------------------------- */
        window.addEventListener("load", async () => {
            log("Page loaded; device_id=" + window.deviceId);
            await registerDevice(); // best-effort
            startSocket();
        });

        // optional UI
    </script>
</body>

</html>
