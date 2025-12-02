import Echo from "laravel-echo";
import Pusher from "pusher-js";
window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: "reverb",
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST || "localhost",
  wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
  wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME || "http") === "https",
  enabledTransports: ["ws","wss"],
});

if (window.deviceId) {
  window.Echo.private(`devices.${window.deviceId}`)
    .listen('PushNotificationBroadcast', (e) => {
      console.log("Realtime Push Received:", e);
      // show toast / notification UI here
    });

  window.Echo.connector.socket.on("connect", () => console.log("Echo connected"));
  window.Echo.connector.socket.on("disconnect", () => console.log("Echo disconnected"));
} else {
  console.warn("deviceId not set - realtime subscription not started");
}
