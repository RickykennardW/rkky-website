export function roomMonitoring() {
  return {
    snapshot: null,
    error: "",
    time: 0,
    anchor: 0,
    retryAt: 0,
    controller: null,
    tickTimer: null,
    refreshTimer: null,
    visibilityListener: null,
    clockFormat: null,

    init() {
      this.visibilityListener = () => {
        if (!document.hidden) this.refresh();
      };
      document.addEventListener("visibilitychange", this.visibilityListener);
      this.tickTimer = setInterval(() => this.tick(), 1000);
      this.refreshTimer = setInterval(() => {
        if (!document.hidden) this.refresh();
      }, 45000);
      this.refresh();
    },

    destroy() {
      clearInterval(this.tickTimer);
      clearInterval(this.refreshTimer);
      document.removeEventListener("visibilitychange", this.visibilityListener);
      this.controller?.abort();
    },

    async refresh() {
      if (this.controller) return;
      const started = performance.now();
      const controller = new AbortController();
      this.controller = controller;
      const timeout = setTimeout(() => controller.abort(), 8000);
      try {
        const response = await fetch(this.$root.dataset.url, {
          headers: { Accept: "application/json" }, cache: "no-store", signal: controller.signal,
        });
        if (!response.ok || response.redirected) throw new Error("Room status could not be refreshed.");
        const snapshot = await response.json();
        if (!Number.isFinite(snapshot.server_time) || !Array.isArray(snapshot.rooms)) {
          throw new Error("Room status could not be refreshed.");
        }
        const received = performance.now();
        this.anchor = received - (received - started) / 2;
        this.clockFormat = new Intl.DateTimeFormat("id-ID", {
          timeZone: snapshot.timezone, day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit",
        });
        this.snapshot = snapshot;
        this.error = "";
        this.time = snapshot.server_time + received - this.anchor;
      } catch {
        this.error = "Status ruangan belum dapat diperbarui. Kami akan mencoba lagi.";
      } finally {
        clearTimeout(timeout);
        this.controller = null;
        this.retryAt = performance.now() + (this.error ? 5000 : 1000);
      }
    },

    tick() {
      if (document.hidden) return;
      if (!this.snapshot) {
        if (performance.now() >= this.retryAt) this.refresh();
        return;
      }
      this.time = this.snapshot.server_time + performance.now() - this.anchor;
      if (performance.now() >= this.retryAt && (this.error || this.snapshot.rooms.some(room => this.crossedBoundary(room)))) {
        this.refresh();
      }
    },

    crossedBoundary(room) {
      if (room.status === "unavailable") return false;
      const boundary = room.current?.end ?? room.next?.start;
      return boundary !== undefined && this.time >= boundary;
    },

    statusLabel(room) {
      if (this.crossedBoundary(room)) return "Memeriksa…";
      return { "in use": "Sedang Digunakan", available: "Tersedia", upcoming: "Segera Digunakan", unavailable: "Tidak Tersedia" }[room.status];
    },

    badgeClass(room) {
      if (this.crossedBoundary(room)) return "bg-raised text-muted";
      return {
        "in use": "bg-[#3a252d] text-negative", available: "bg-[#17332a] text-positive",
        upcoming: "bg-[#382c1b] text-warning", unavailable: "bg-raised text-muted",
      }[room.status];
    },

    remaining(end) {
      const seconds = Math.max(0, Math.ceil((end - this.time) / 1000));
      return [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60]
        .map(value => String(value).padStart(2, "0")).join(":");
    },

    windowLabel(period) {
      return this.clockFormat.formatRange(new Date(period.start), new Date(period.end)) + " WIB";
    },

    timeLabel(timestamp) {
      return this.clockFormat.format(new Date(timestamp)) + " WIB";
    },
  };
}
