document.addEventListener("DOMContentLoaded", function () {
  var mapElement = document.getElementById("map");
  var script = document.querySelector("script[data-base-path]");
  var modeSelect = document.getElementById("map-mode");
  if (!mapElement || !script || typeof L === "undefined") {
    return;
  }

  var basePath = script.dataset.basePath;
  var modes = {
    total: { color: "#1f3a5f", value: function (p) { return p.member_count; } },
    zpm: { color: "#0b6b5d", value: function (p) { return p.zpm_count; } },
    non_zpm: { color: "#6b4c9a", value: function (p) { return p.non_zpm_count; } },
    green: { color: "#1e8449", value: function (p) { return p.classification ? p.classification.green : 0; } },
    yellow: { color: "#d4a017", value: function (p) { return p.classification ? p.classification.yellow : 0; } },
    red: { color: "#c0392b", value: function (p) { return p.classification ? p.classification.red : 0; } }
  };

  function escapeHtml(value) {
    var div = document.createElement("div");
    div.textContent = String(value);
    return div.innerHTML;
  }

  var map = L.map(mapElement).setView([23.3, 92.8], 8);
  L.tileLayer(
    "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
    {
      attribution:
        "Tiles &copy; Esri &mdash; Source: Esri, OpenStreetMap contributors, and the GIS User Community",
      maxZoom: 19
    }
  ).addTo(map);

  var data = null;
  var layer = null;

  function popup(p) {
    var html = "<strong>" + escapeHtml(p.name) + "</strong><br>" +
      escapeHtml(p.district) + " / " + escapeHtml(p.block) +
      "<br>Registered individuals: " + p.member_count +
      "<br>ZPM: " + p.zpm_count + " &middot; Non-ZPM: " + p.non_zpm_count;
    if (p.classification) {
      html += "<br>Green: " + p.classification.green +
        " &middot; Yellow: " + p.classification.yellow +
        " &middot; Red: " + p.classification.red;
    } else if (p.non_zpm_count > 0) {
      html += "<br><em>Classification hidden (small group)</em>";
    }
    return html;
  }

  function render() {
    var mode = modes[modeSelect ? modeSelect.value : "total"] || modes.total;
    if (layer) {
      map.removeLayer(layer);
    }
    var max = 1;
    data.features.forEach(function (f) { max = Math.max(max, mode.value(f.properties)); });
    layer = L.geoJSON(data, {
      pointToLayer: function (feature, latlng) {
        var value = mode.value(feature.properties);
        return L.circleMarker(latlng, {
          radius: value === 0 ? 3 : 5 + 13 * Math.sqrt(value / max),
          color: "#ffffff",
          weight: 1.5,
          fillColor: mode.color,
          fillOpacity: value === 0 ? 0.25 : 0.8
        });
      },
      onEachFeature: function (feature, marker) {
        marker.bindPopup(popup(feature.properties));
      }
    }).addTo(map);
    return layer;
  }

  fetch(basePath + "/gis/units.geojson")
    .then(function (response) {
      if (!response.ok) {
        throw new Error("GIS data could not be loaded.");
      }
      return response.json();
    })
    .then(function (geojson) {
      data = geojson;
      var drawn = render();
      if (drawn.getBounds().isValid()) {
        map.fitBounds(drawn.getBounds(), { padding: [20, 20] });
      }
      if (modeSelect) {
        modeSelect.addEventListener("change", render);
      }
    })
    .catch(function () {
      mapElement.insertAdjacentHTML(
        "afterend",
        '<p class="alert" role="alert">The GIS data could not be loaded.</p>'
      );
    });
});
