const scheduleFields = ["date", "start", "end_date", "end"];

export function bookingForm() {
  return {
    roomId: "",
    hasPlan: false,
    confirmed: false,
    checking: false,
    availabilityMessage: "",
    availabilityError: false,
    limits: null,
    requestVersion: 0,
    summary: "",

    init() {
      this.roomId = this.$root.elements.room_id.value;
      this.hasPlan = Boolean(this.$root.elements.planner.value);
    },

    changed(event) {
      const field = event.target.name;
      if (field === "room_id") {
        this.roomId = event.target.value;
      }
      if (["room_id", "participants", "committee"].includes(field)) {
        this.removePlan();
      }
      if (scheduleFields.includes(field) || field === "room_id") {
        this.requestVersion++;
        this.limits = null;
        this.checking = false;
        this.availabilityMessage = "";
      }
    },

    removePlan() {
      this.$refs.plan.value = "";
      this.hasPlan = false;
    },

    plannerUrl() {
      const url = new URL(this.$root.dataset.plannerUrl, window.location.origin);
      url.searchParams.set("room", this.roomId);
      for (const field of ["participants", "committee"]) {
        const input = this.$root.elements[field];
        if (input.validity.valid) {
          url.searchParams.set(field, input.value);
        }
      }
      return url.toString();
    },

    quantityLabel(id, defaultStock) {
      return this.limits ? (this.limits[id] ?? 0) + " available" : defaultStock + " usable units";
    },

    async check() {
      for (const field of scheduleFields) {
        if (!this.$root.elements[field].reportValidity()) return;
      }

      const version = ++this.requestVersion;
      const url = new URL(this.$root.dataset.availabilityUrl, window.location.origin);
      for (const field of scheduleFields) {
        url.searchParams.set(field, this.$root.elements[field].value);
      }

      this.checking = true;
      this.availabilityMessage = "";
      this.availabilityError = false;
      try {
        const response = await fetch(url, {
          headers: { Accept: "application/json" },
          signal: AbortSignal.timeout(12000),
        });
        const data = await response.json();
        if (!response.ok) {
          throw new Error(response.status === 422 ? data.message : "Availability could not be checked.");
        }
        if (version !== this.requestVersion) return;

        this.limits = data.equipment;
        if (this.roomId) {
          const available = Boolean(data.rooms?.[this.roomId]);
          this.availabilityError = !available;
          this.availabilityMessage = available ? "Room available for this period" : "Room unavailable for this period";
        } else {
          this.availabilityMessage = "Equipment quantities updated below.";
        }
      } catch (error) {
        if (version !== this.requestVersion) return;
        this.availabilityError = true;
        this.availabilityMessage = error.name === "TimeoutError"
          ? "Availability check timed out. Try again." : error.message;
      } finally {
        if (version === this.requestVersion) this.checking = false;
      }
    },

    review(event) {
      if (this.confirmed) return;
      event.preventDefault();
      const form = this.$root.elements;
      const equipment = [...this.$root.querySelectorAll("[data-equipment-name]")]
        .filter((input) => Number(input.value) > 0)
        .map((input) => input.dataset.equipmentName + " × " + input.value);

      this.summary = [
        form.title.value,
        form.room_id.selectedOptions[0].textContent.trim(),
        form.date.value + " " + form.start.value + " – " + form.end_date.value + " " + form.end.value + " WIB",
        form.participants.value + " participants · " + form.committee.value + " committee",
        ...(this.hasPlan ? ["Room plan attached"] : []),
        ...equipment,
      ].join("\n");
      this.$refs.reviewDialog.showModal();
    },

    submitConfirmed() {
      this.$refs.reviewDialog.close();
      if (!this.$root.reportValidity()) return;

      this.confirmed = true;
      this.$root.requestSubmit();
    },
  };
}
