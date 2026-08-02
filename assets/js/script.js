document.addEventListener("DOMContentLoaded", function () {
  /* Message volume chart -------------------------------------------------- */
  const canvas = document.getElementById("volumeChart");
  if (canvas && window.Chart) {
    const ctx = canvas.getContext("2d");
    const gradient = ctx.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, "rgba(255,90,31,0.28)");
    gradient.addColorStop(1, "rgba(255,90,31,0)");

    new Chart(ctx, {
      type: "line",
      data: {
        labels: window.__volumeLabels || [],
        datasets: [
          {
            label: "Inbound",
            data: window.__volumeInbound || [],
            borderColor: "#ff5a1f",
            backgroundColor: gradient,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 2.5,
          },
          {
            label: "Outbound",
            data: window.__volumeOutbound || [],
            borderColor: "#15130f",
            backgroundColor: "transparent",
            fill: false,
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 2,
            borderDash: [4, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: {
            position: "top",
            align: "end",
            labels: {
              boxWidth: 8,
              boxHeight: 8,
              usePointStyle: true,
              font: { family: "Inter", size: 11 },
              color: "#56504a",
            },
          },
          tooltip: {
            backgroundColor: "#15130f",
            titleFont: { family: "IBM Plex Mono", size: 11 },
            bodyFont: { family: "IBM Plex Mono", size: 11 },
            padding: 10,
            cornerRadius: 8,
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              font: { family: "IBM Plex Mono", size: 10 },
              color: "#948d84",
            },
          },
          y: {
            grid: { color: "#f2eee8" },
            ticks: {
              font: { family: "IBM Plex Mono", size: 10 },
              color: "#948d84",
            },
            beginAtZero: true,
          },
        },
      },
    });
  }

  /* Live conversation search filter --------------------------------------- */
  const searchInput = document.getElementById("convoSearch");
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll(".convo-row").forEach((row) => {
        const name = row.dataset.name || "";
        row.style.display = name.includes(q) ? "" : "none";
      });
    });
  }

  /* Sidebar nav active state (demo only, no routing) ----------------------- */
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", function (e) {
      if (this.getAttribute("href") === "#") e.preventDefault();
      document
        .querySelectorAll(".nav-link")
        .forEach((l) => l.classList.remove("active"));
      this.classList.add("active");
    });
  });
});
