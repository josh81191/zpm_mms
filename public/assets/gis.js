document.addEventListener("DOMContentLoaded", function () {
  var mapElement = document.getElementById("map");
  var script = document.querySelector("script[data-base-path]");
  var levelSelect = document.getElementById("map-level");
  var areaSelect = document.getElementById("map-area");
  var mapMessage = document.getElementById("gis-map-message");
  if (!mapElement || !script || !levelSelect || !areaSelect || typeof L === "undefined") {
    return;
  }

  var basePath = script.dataset.basePath;
  var parties = [
    { key: "zpm", label: "ZPM", color: "#137a68" },
    { key: "mnf", label: "MNF", color: "#b42318" },
    { key: "congress", label: "Congress", color: "#2563eb" },
    { key: "bjp", label: "BJP", color: "#f59e0b" },
    { key: "other", label: "Other", color: "#64748b" },
    { key: "suppressed", label: "Suppressed (under 5)", color: "#aab4c2" }
  ];
  var map = L.map(mapElement).setView([23.3, 92.8], 8);
  L.tileLayer(
    "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
    {
      attribution:
        "Tiles &copy; Esri &mdash; OpenStreetMap contributors, Esri, and the GIS User Community",
      maxZoom: 19
    }
  ).addTo(map);

  var legend = L.control({ position: "topright" });
  legend.onAdd = function () {
    var container = L.DomUtil.create("div", "party-map-legend");
    container.innerHTML = "<strong>Party population</strong>" + parties.map(function (party) {
      return '<span><i class="party-color-' + party.key + '"></i>' + party.label + "</span>";
    }).join("");
    L.DomEvent.disableClickPropagation(container);
    return container;
  };
  legend.addTo(map);

  var areas = [];
  var areaLayer = null;
  var pieMarkers = null;
  var pieMarkersById = {};

  function escapeHtml(value) {
    var div = document.createElement("div");
    div.textContent = String(value);
    return div.innerHTML;
  }

  function selectedArea() {
    return areas.find(function (area) {
      return String(area.id) === areaSelect.value;
    }) || null;
  }

  function selectArea(area) {
    areaSelect.value = String(area.id);
    updateAreaHighlight();
    focusArea(area);
  }

  function focusArea(area) {
    if (!area) {
      if (areaLayer && areaLayer.getBounds().isValid()) {
        map.fitBounds(areaLayer.getBounds(), { padding: [40, 40], maxZoom: 11 });
      }
      return;
    }
    var bounds = null;
    if (areaLayer) {
      areaLayer.eachLayer(function (layer) {
        if (layer.feature && String(layer.feature.properties.id) === String(area.id)) {
          bounds = layer.getBounds ? layer.getBounds() : L.latLngBounds([layer.getLatLng()]);
        }
      });
    }
    if (bounds && bounds.isValid()) {
      var size = bounds.getNorthEast().distanceTo(bounds.getSouthWest());
      if (size === 0) {
        map.setView(bounds.getCenter(), 12);
      } else {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
      }
    }
    var marker = pieMarkersById[String(area.id)];
    if (marker) {
      marker.openPopup();
    } else if (bounds && bounds.isValid()) {
      L.popup().setLatLng(bounds.getCenter()).setContent(popup(area)).openOn(map);
    }
  }

  function updateAreaHighlight() {
    if (areaLayer) {
      areaLayer.setStyle(function (feature) {
        var selected = String(feature.properties.id) === areaSelect.value;
        return {
          color: selected ? "#1d4ed8" : "#64748b",
          weight: selected ? 3 : 1.5,
          fillColor: selected ? "#60a5fa" : "#cbd5e1",
          fillOpacity: selected ? 0.28 : 0.12
        };
      });
    }
  }

  function popup(area) {
    var lines = parties
      .filter(function (party) { return area.parties[party.key] > 0; })
      .map(function (party) {
        return escapeHtml(party.label) + ": " + area.parties[party.key].toLocaleString();
      });
    var title = escapeHtml(area.name) +
      (levelSelect.value === "block" ? " Block — " + escapeHtml(area.district) + " District" : " District");
    return "<strong>" + title + "</strong><br>Total registered: " +
      area.total.toLocaleString() +
      (lines.length ? "<br>" + lines.join("<br>") : "<br>No party counts to display.");
  }

  function pieIcon(area) {
    var total = parties.reduce(function (sum, party) {
      return sum + area.parties[party.key];
    }, 0);
    var stops = [];
    var angle = 0;
    if (total > 0) {
      parties.forEach(function (party) {
        var value = area.parties[party.key];
        if (value <= 0) {
          return;
        }
        var nextAngle = angle + value / total * 360;
        stops.push(party.color + " " + angle + "deg " + nextAngle + "deg");
        angle = nextAngle;
      });
    }
    var fill = stops.length ? "conic-gradient(" + stops.join(", ") + ")" : "#dce4ee";
    var district = levelSelect.value === "district";
    var html = '<div class="' + (district ? "party-pie-large" : "") + '"><span class="party-pie"></span>' +
      '<b class="party-pie-total">' + area.total.toLocaleString() + "</b></div>";
    var icon = L.divIcon({
      className: "party-pie-marker",
      html: html,
      iconSize: district ? [80, 68] : [64, 46],
      iconAnchor: district ? [40, 34] : [32, 23]
    });
    icon.partyFill = fill;
    return icon;
  }

  function renderMap(fitBounds) {
    if (areaLayer) {
      map.removeLayer(areaLayer);
    }
    if (pieMarkers) {
      map.removeLayer(pieMarkers);
    }

    var selectedId = areaSelect.value;
    var features = areas.filter(function (area) { return area.geometry !== null; }).map(function (area) {
      return {
        type: "Feature",
        geometry: area.geometry,
        properties: area
      };
    });
    if (features.length === 0) {
      mapMessage.textContent = areas.length === 0
        ? "No districts or blocks are available."
        : "No approved district or block boundaries or representative locations are available to display. Party population pie markers require an area boundary or representative location.";
      return;
    }

    mapMessage.textContent = "";
    areaLayer = L.geoJSON({ type: "FeatureCollection", features: features }, {
      pointToLayer: function (feature, latlng) {
        return L.circleMarker(latlng, { radius: 6 });
      },
      style: function (feature) {
        var selected = String(feature.properties.id) === selectedId;
        return {
          color: selected ? "#1d4ed8" : "#64748b",
          weight: selected ? 3 : 1.5,
          fillColor: selected ? "#60a5fa" : "#cbd5e1",
          fillOpacity: selected ? 0.28 : 0.12
        };
      },
      onEachFeature: function (feature, layer) {
        var area = feature.properties;
        layer.on("click", function () {
          selectArea(area);
        });
      }
    }).addTo(map);

    pieMarkers = L.layerGroup().addTo(map);
    pieMarkersById = {};
    areas.forEach(function (area) {
      if (!area.chart_location) {
        return;
      }
      var coordinates = area.chart_location.coordinates;
      var marker = L.marker([coordinates[1], coordinates[0]], {
        icon: pieIcon(area),
        keyboard: true,
        title: area.name + " party population"
      });
      marker.on("add", function () {
        marker.getElement().querySelector(".party-pie").style.background = marker.options.icon.partyFill;
      });
      marker.bindPopup(popup(area));
      marker.on("click", function () {
        selectArea(area);
      });
      pieMarkersById[String(area.id)] = marker;
      marker.addTo(pieMarkers);
    });

    if (fitBounds) {
      var bounds = areaLayer.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 11 });
      }
    }
  }

  function loadLevel() {
    areaSelect.innerHTML = '<option value="">Loading areas…</option>';
    fetch(basePath + "/gis/party-data?level=" + encodeURIComponent(levelSelect.value), {
      headers: { Accept: "application/json" },
      credentials: "same-origin"
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("GIS data could not be loaded.");
        }
        return response.json();
      })
      .then(function (result) {
        areas = result.areas;
        areaSelect.innerHTML = "";
        var allOption = document.createElement("option");
        allOption.value = "";
        allOption.textContent = levelSelect.value === "block" ? "All blocks" : "All districts";
        areaSelect.appendChild(allOption);
        areas.forEach(function (area) {
          var option = document.createElement("option");
          option.value = area.id;
          option.textContent = levelSelect.value === "block"
            ? area.name + " — " + area.district
            : area.name;
          areaSelect.appendChild(option);
        });
        if (areas.length === 0) {
          var emptyOption = document.createElement("option");
          emptyOption.value = "";
          emptyOption.textContent = "No areas available";
          areaSelect.appendChild(emptyOption);
          mapMessage.textContent = "No districts or blocks are available.";
        }
        renderMap(true);
      })
      .catch(function () {
        areaSelect.innerHTML = '<option value="">Unable to load areas</option>';
        mapMessage.textContent = "The GIS party data could not be loaded. Please try again.";
      });
  }

  levelSelect.addEventListener("change", loadLevel);
  areaSelect.addEventListener("change", function () {
    updateAreaHighlight();
    focusArea(selectedArea());
  });
  loadLevel();
});
