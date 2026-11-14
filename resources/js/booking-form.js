import { drawRoomPlan } from "./room-planner";

export function bookingForm() {
  return {
    roomId: "",
    hasPlan: false,
    checking: false,
    availabilityMessage: "",
    availabilityError: false,
    roomUnavailable: false,
    limits: null,
    requestVersion: 0,
    planVersion: 0,
    planPreview: null,
    planError: "",
    planning: false,
    summary: {},

    init() {
      this.roomId = this.$root.elements.room_id.value;
      this.hasPlan = Boolean(this.$refs.plan.value);
      this.readSummary();
      this.check();
    },

    period() {
      const fields = this.$root.elements;
      return { date: fields.date.value, start: fields.start.value, end_date: fields.end_date.value || fields.date.value, end: fields.end.value };
    },

    changed(event) {
      const field = event.target.name;
      if (field === "room_id") this.roomId = event.target.value;
      if (["room_id", "participants", "committee"].includes(field)) {
        this.removePlan();
        this.clearPreview();
      }
      if ([...Object.keys(this.period()), "room_id", "participants", "committee"].includes(field)) this.check();
      this.readSummary();
    },

    removePlan() {
      this.$refs.plan.value = "";
      this.hasPlan = false;
    },

    clearPreview() {
      this.planVersion++;
      this.planPreview = null;
      this.planError = "";
      this.planning = false;
    },

    alternativeUrl(kind) {
      const form = this.$root.elements;
      const url = new URL(kind === "time" ? this.$root.dataset.schedulingUrl : this.$root.dataset.roomsUrl);
      for (const [key, value] of Object.entries(this.period())) url.searchParams.set(key, value);
      if (kind === "time") {
        url.hash = "pilihan-jadwal";
        url.searchParams.set("alternatives", "1");
        url.searchParams.set("room", this.roomId);
        url.searchParams.set("participants", form.participants.value);
        url.searchParams.set("committee", form.committee.value);
      } else {
        url.searchParams.set("capacity", Number(form.participants.value) + Number(form.committee.value));
        url.searchParams.set("committee", form.committee.value);
      }
      for (const facility of this.$root.querySelectorAll('[name="required_facilities[]"]:checked')) {
        url.searchParams.append(kind === "time" ? "required_facilities[]" : "facility[]", facility.value);
      }
      for (const quantity of this.$root.querySelectorAll('[data-equipment-name]')) {
        if (Number(quantity.value) > 0) url.searchParams.set(quantity.name, quantity.value);
      }
      return url.toString();
    },

    quantityLabel(id, stock) {
      return this.limits ? (this.limits[id] ?? 0) + " unit tersedia" : stock + " unit siap pakai";
    },

    async check() {
      const version = ++this.requestVersion;
      const period = this.period();
      this.limits = null;
      this.roomUnavailable = false;
      this.availabilityError = false;
      this.availabilityMessage = "";
      this.checking = false;
      if (Object.values(period).some(value => !value)) return;

      if (period.date + period.start >= period.end_date + period.end) {
        this.availabilityError = true;
        this.availabilityMessage = "Waktu selesai harus setelah waktu mulai.";
        return;
      }

      const form = this.$root.elements;
      const option = form.room_id.selectedOptions[0];
      if (this.roomId && Number(form.participants.value) + Number(form.committee.value) > Number(option.dataset.capacity)) {
        this.roomUnavailable = this.availabilityError = true;
        this.availabilityMessage = "Jumlah peserta dan panitia melebihi kapasitas ruangan.";
        return;
      }

      const url = new URL(this.$root.dataset.availabilityUrl);
      url.search = new URLSearchParams(period);
      this.checking = true;
      try {
        const response = await fetch(url, { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(8000) });
        if (!response.ok || response.redirected) throw new Error();
        const data = await response.json();
        if (version !== this.requestVersion) return;

        this.limits = data.equipment;
        if (!this.roomId) return;
        this.roomUnavailable = this.availabilityError = !data.rooms?.[this.roomId];
        this.availabilityMessage = this.roomUnavailable
          ? "Ruangan tidak tersedia pada jam tersebut."
          : "Ruangan tersedia pada jadwal yang dipilih.";
      } catch {
        if (version !== this.requestVersion) return;
        this.availabilityError = true;
        this.availabilityMessage = "Jadwal belum dapat diperiksa. Coba lagi.";
      } finally {
        if (version === this.requestVersion) this.checking = false;
      }
    },

    async previewPlan() {
      const form = this.$root.elements;
      if (!form.participants.reportValidity() || !form.committee.reportValidity()) return;
      this.clearPreview();
      const version = this.planVersion;
      this.planning = true;
      try {
        const response = await fetch(this.$root.dataset.previewUrl, {
          method: "POST",
          headers: {
            Accept: "application/json", "Content-Type": "application/json",
            "X-CSRF-TOKEN": form._token.value,
          },
          body: JSON.stringify({
            room_id: this.roomId, eventType: "General Event", presentation: false, registrationDesk: false, discussion: false,
            participants: form.participants.value, committee: form.committee.value,
            arrangement: this.$refs.arrangement.value || null,
          }),
          signal: AbortSignal.timeout(8000),
        });
        if (!response.ok || response.redirected) throw new Error();
        const result = await response.json();
        if (version !== this.planVersion) return;
        if (result.error) {
          this.planError = "Susunan ini tidak muat. Coba susunan lain.";
          return;
        }
        this.planPreview = result;
        this.$nextTick(() => drawRoomPlan(this.$refs.drawing, result));
      } catch {
        if (version === this.planVersion) this.planError = "Denah belum dapat dibuat. Coba lagi.";
      } finally {
        if (version === this.planVersion) this.planning = false;
      }
    },

    attachPlan() {
      this.$refs.plan.value = JSON.stringify(this.planPreview.plan);
      this.hasPlan = true;
      this.readSummary();
    },

    readSummary() {
      const form = this.$root.elements;
      const period = this.period();
      const equipment = [...this.$root.querySelectorAll("[data-equipment-name]")]
        .filter(input => Number(input.value) > 0)
        .map(input => input.dataset.equipmentName + " × " + input.value);
      const format = date => date.split("-").reverse().join("/");
      this.summary = {
        title: form.title.value || "Pengajuan baru",
        room: form.room_id.selectedOptions[0].textContent.trim(),
        period: period.date ? format(period.date) + " · " + period.start + "–" +
          (period.date !== period.end_date ? format(period.end_date) + " " : "") + period.end + " WIB" : "",
        attendance: form.participants.value + " peserta" + (Number(form.committee.value) ? " + " + form.committee.value + " panitia" : ""),
        extras: [...equipment, ...(this.hasPlan ? ["Denah terlampir"] : [])].join(" · "),
      };
    },
  };
}
