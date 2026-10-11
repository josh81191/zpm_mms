document.addEventListener("DOMContentLoaded", function () {
  var mapElement = document.getElementById("map");
  var script = document.querySelector("script[data-base-path]");
  if (!mapElement || !script || typeof L === "undefined") {
    return;
  }

  var basePath = script.dataset.basePath;

  var map = L.map(mapElement).setView([23.3, 92.8], 8);
  L.tileLayer(
    "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
    {
      attribution:
        "Tiles &copy; Esri &mdash; Source: Esri, OpenStreetMap contributors, and the GIS User Community",
    maxZoom: 19,
    }
  ).addTo(map);

  fetch(basePath + "/gis/units.geojson")
    .then(function (response) {
      if (!response.ok) {
        throw new Error("GIS data could not be loaded.");
      }
      return response.json();
    })
    .then(function (data) {
      var layer = L.geoJSON(data, {
        onEachFeature: function (feature, marker) {
          var properties = feature.properties;
          marker.bindPopup(
            "<strong>" +
              properties.name +
              "</strong><br>" +
              properties.district +
              " / " +
              properties.block +
              "<br>Recorded members: " +
              properties.member_count,
          );
        },
      }).addTo(map);
      if (layer.getBounds().isValid()) {
        map.fitBounds(layer.getBounds(), { padding: [20, 20] });
      }
    })
    .catch(function () {
      mapElement.insertAdjacentHTML(
        "afterend",
        '<p class="alert" role="alert">The GIS data could not be loaded.</p>',
      );
    });
});
