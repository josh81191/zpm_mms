document.addEventListener("DOMContentLoaded", function () {
  if (typeof Chart === "undefined") {
    return;
  }
  var styles = {
    zpm: { label: "ZPM", color: "#0b6b5d" },
    mnf: { label: "MNF", color: "#2563eb" },
    congress: { label: "Congress", color: "#7c3aed" },
    bjp: { label: "BJP", color: "#ea580c" },
    other: { label: "Other", color: "#64748b" }
  };

  function draw(id, pick, yTitle) {
    var canvas = document.getElementById(id);
    if (!canvas) {
      return;
    }
    var data = JSON.parse(canvas.dataset.chart);
    var datasets = Object.keys(styles).map(function (key) {
      return {
        label: styles[key].label,
        data: pick(data, key),
        borderColor: styles[key].color,
        backgroundColor: styles[key].color,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 3
      };
    });
    new Chart(canvas, {
      type: "line",
      data: { labels: data.labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: { legend: { position: "bottom" } },
        scales: { y: { title: { display: true, text: yTitle }, ticks: { precision: 0 } } }
      }
    });
  }

  draw("party-population-chart", function (d, k) { return d.population[k]; }, "Registered individuals");
  draw("party-movement-chart", function (d, k) { return d.series[k].net; }, "Net individuals");
});
