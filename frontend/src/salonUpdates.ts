export function notifySalonChange() {
  if (typeof BroadcastChannel === "undefined") return;
  const channel = new BroadcastChannel("remi-salon-updates");
  channel.postMessage("refresh");
  channel.close();
}
