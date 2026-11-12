const svgNamespace = "http://www.w3.org/2000/svg";
const itemColors = {
  chair: "#719af2", table: "#4ca69e", fixed: "#9b7ccf", blocked: "#bb6671",
  clearance: "#50667d", stage: "#315c91", registration: "#b89055",
};

function addElement(svg, tag, attributes, label = null) {
  const node = document.createElementNS(svgNamespace, tag);
  for (const [name, value] of Object.entries(attributes)) {
    node.setAttribute(name, String(value));
  }
  if (label) node.textContent = label;
  svg.append(node);
}

function draw(svg, result) {
  svg.replaceChildren();
  if (!result?.layout) return;

  const { width, depth, door } = result.layout;
  svg.setAttribute("viewBox", [-0.35, -0.35, width + 0.7, depth + 0.7].join(" "));
  addElement(svg, "rect", {
    x: 0, y: 0, width, height: depth, rx: 0.13,
    fill: "#151f2c", stroke: "#547086", "stroke-width": 0.055,
  });

  for (let x = 1; x < width; x++) {
    addElement(svg, "line", { x1: x, x2: x, y1: 0, y2: depth, stroke: "#243342", "stroke-width": 0.018 });
  }
  for (let y = 1; y < depth; y++) {
    addElement(svg, "line", { x1: 0, x2: width, y1: y, y2: y, stroke: "#243342", "stroke-width": 0.018 });
  }

  for (const item of result.items) {
    addElement(svg, "rect", {
      x: item.x, y: item.y, width: item.width, height: item.depth,
      rx: item.kind === "chair" ? 0.07 : 0.09,
      fill: itemColors[item.kind], opacity: item.kind === "clearance" ? 0.30 : 0.9,
      stroke: "#c4d7f5", "stroke-width": 0.018,
    });
    const label = item.label ?? (item.kind === "stage" ? "Presentation" : item.kind === "registration" ? "Check-in" : null);
    if (label && item.width > 1) {
      addElement(svg, "text", {
        x: item.x + item.width / 2, y: item.y + item.depth / 2, "text-anchor": "middle",
        "dominant-baseline": "central", fill: "#f3f6fa", "font-size": Math.min(0.25, item.width / (label.length * 0.72)),
      }, label);
    }
  }

  const horizontal = door.wall === "north" || door.wall === "south";
  const edge = door.wall === "south" ? depth : door.wall === "east" ? width : 0;
  addElement(svg, "line", {
    x1: horizontal ? door.offset : edge,
    x2: horizontal ? door.offset + door.width : edge,
    y1: horizontal ? edge : door.offset,
    y2: horizontal ? edge : door.offset + door.width,
    stroke: "#79cbc3", "stroke-width": 0.13, "stroke-linecap": "round",
  });
}

export function roomPlanner() {
  return {
    result: null,
    error: "",
    loading: false,
    requestVersion: 0,

    init() {
      this.result = JSON.parse(this.$root.dataset.initial);
      this.$nextTick(() => draw(this.$refs.drawing, this.result));
    },

    dimensions() {
      const layout = this.result?.layout;
      return layout ? layout.width + " m × " + layout.depth + " m · measured room dimensions" : "Room dimensions unavailable";
    },

    async calculate() {
      if (!this.$refs.planForm.reportValidity()) {
        this.requestVersion++;
        this.loading = false;
        this.error = "Check the event and equipment quantities.";
        return;
      }
      const version = ++this.requestVersion;
      const fields = Object.fromEntries(new FormData(this.$refs.planForm));
      for (const field of ["presentation", "registrationDesk", "discussion"]) {
        fields[field] = fields[field] === "1";
      }
      this.loading = true;
      this.error = "";

      try {
        const response = await fetch(this.$root.dataset.previewUrl, {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(fields),
          signal: AbortSignal.timeout(12000),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Unable to calculate the room plan.");
        if (version !== this.requestVersion) return;

        this.result = data;
        draw(this.$refs.drawing, this.result);
      } catch (error) {
        if (version !== this.requestVersion) return;
        this.error = error.name === "TimeoutError" ? "The planner request timed out. Try again." : error.message;
      } finally {
        if (version === this.requestVersion) this.loading = false;
      }
    },

    resetQuantities() {
      for (const input of this.$refs.planForm.querySelectorAll("[data-override]")) {
        input.value = "";
      }
      this.$refs.planForm.elements.arrangement.value = "";
      this.calculate();
    },

    usePlan() {
      if (this.loading || this.error || !this.result?.layout || this.result.error) return;

      const url = new URL(this.$root.dataset.bookUrl, window.location.origin);
      url.searchParams.set("room", this.$refs.planForm.elements.room_id.value);
      url.searchParams.set("plan", JSON.stringify(this.result.plan));
      window.location.assign(url);
    },
  };
}
