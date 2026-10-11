(function () {
  var card = document.getElementById("ask-card");
  if (!card) {
    return;
  }
  var form = document.getElementById("ask-form");
  var input = document.getElementById("ask-input");
  var button = document.getElementById("ask-button");
  var result = document.getElementById("ask-result");
  var errorBox = document.getElementById("ask-error");
  var output = document.getElementById("ask-output");
  var tableBlock = document.getElementById("ask-table-block");
  var current = null;

  function text(id, value) {
    document.getElementById(id).textContent = value;
  }

  function showError(message) {
    result.hidden = false;
    output.hidden = true;
    errorBox.hidden = false;
    errorBox.textContent = message;
  }

  function cells(row) {
    return row.labels.concat(row.cells ? row.cells : [row.n === null ? "<5" : row.n.toLocaleString(), row.pct === null ? "—" : row.pct + "%"]);
  }

  function render(data) {
    current = data;
    result.hidden = false;
    errorBox.hidden = true;
    output.hidden = false;
    text("ask-summary", data.summary);
    text("ask-interpretation", "Understood as: " + data.interpretation.join(" · "));
    text("ask-notes", data.notes.join(" "));

    tableBlock.hidden = !data.show_table;
    if (!data.show_table) {
      return;
    }
    var head = document.querySelector("#ask-table thead");
    var body = document.querySelector("#ask-table tbody");
    head.textContent = "";
    body.textContent = "";
    var headRow = document.createElement("tr");
    data.columns.forEach(function (name) {
      var th = document.createElement("th");
      th.textContent = name;
      headRow.appendChild(th);
    });
    head.appendChild(headRow);
    data.rows.forEach(function (row) {
      var tr = document.createElement("tr");
      cells(row).forEach(function (cell) {
        var td = document.createElement("td");
        td.textContent = cell;
        tr.appendChild(td);
      });
      body.appendChild(tr);
    });
  }

  function ask(question) {
    button.disabled = true;
    button.textContent = "Analysing…";
    fetch(card.dataset.endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": card.dataset.csrf },
      body: JSON.stringify({ question: question })
    })
      .then(function (response) {
        return response.json().then(function (data) { return data; }, function () { return { ok: false, error: "Unexpected response. Please sign in again and retry." }; });
      })
      .then(function (data) {
        if (data.ok) {
          render(data);
        } else {
          if (data.debug) {
            console.error("AI analysis error:", data.debug);
          }
          showError(data.error || "The analysis failed.");
        }
      })
      .catch(function () {
        showError("The server could not be reached. Check your connection and try again.");
      })
      .then(function () {
        button.disabled = false;
        button.textContent = "Analyse";
      });
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var question = input.value.trim();
    if (question !== "") {
      ask(question);
    }
  });

  card.querySelectorAll("[data-example]").forEach(function (chip) {
    chip.addEventListener("click", function () {
      input.value = chip.dataset.example;
      ask(input.value);
    });
  });

  function csvCell(value) {
    var cell = String(value);
    if (/^[=+\-@\t\r]/.test(cell)) {
      cell = "'" + cell;
    }
    return '"' + cell.replace(/"/g, '""') + '"';
  }

  document.getElementById("ask-csv").addEventListener("click", function () {
    if (!current) {
      return;
    }
    var lines = [current.columns.map(csvCell).join(",")];
    current.rows.forEach(function (row) {
      lines.push(row.labels.concat([row.n === null ? "<5" : row.n, row.pct === null ? "" : row.pct]).map(csvCell).join(","));
    });
    lines.push("", csvCell("Question: " + current.question), csvCell("Exported: " + new Date().toISOString()));
    var url = URL.createObjectURL(new Blob(["\ufeff" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" }));
    var link = document.createElement("a");
    link.href = url;
    link.download = "analysis.csv";
    link.click();
    URL.revokeObjectURL(url);
  });
})();
