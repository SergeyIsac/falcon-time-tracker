(function () {
  // padStart polyfill for older browsers
  if (!String.prototype.padStart) {
    String.prototype.padStart = function (targetLength, padString) {
      targetLength = targetLength >> 0;
      padString = String(typeof padString !== "undefined" ? padString : " ");
      if (this.length >= targetLength) return String(this);
      targetLength = targetLength - this.length;
      while (padString.length < targetLength) {
        padString += padString;
      }
      return padString.slice(0, targetLength) + String(this);
    };
  }

  var flashEl = document.getElementById("flash");
  var employeeSelect = document.getElementById("timer-employee");
  var reportEmployee = document.getElementById("report-employee");
  var employeeList = document.getElementById("employee-list");
  var totalsEl = document.getElementById("totals");
  var entriesBody = document.getElementById("entries-body");
  var fromInput = document.getElementById("report-from");
  var toInput = document.getElementById("report-to");

  function todayISO() {
    var d = new Date();
    var m = String(d.getMonth() + 1).padStart(2, "0");
    var day = String(d.getDate()).padStart(2, "0");
    return d.getFullYear() + "-" + m + "-" + day;
  }

  fromInput.value = todayISO();
  toInput.value = todayISO();

  function flash(message, isError) {
    flashEl.textContent = message;
    flashEl.className = "flash show" + (isError ? " error" : "");
    setTimeout(function () {
      flashEl.className = "flash";
    }, 4000);
  }

  function formatDuration(seconds) {
    seconds = Math.max(0, parseInt(seconds, 10) || 0);
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    return (
      String(h).padStart(2, "0") +
      ":" +
      String(m).padStart(2, "0") +
      ":" +
      String(s).padStart(2, "0")
    );
  }

  function formatWhen(iso) {
    if (!iso) return "—";
    var d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString();
  }

  function api(action, options) {
    options = options || {};
    var url = "api.php?action=" + encodeURIComponent(action);
    if (options.query) {
      Object.keys(options.query).forEach(function (key) {
        if (options.query[key] !== "" && options.query[key] != null) {
          url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(options.query[key]);
        }
      });
    }
    var conf = {
      method: options.method || "GET",
      headers: {},
    };
    if (options.body) {
      conf.headers["Content-Type"] = "application/json";
      conf.body = JSON.stringify(options.body);
    }
    return fetch(url, conf).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          throw new Error(data.error || "Request failed");
        }
        return data;
      });
    });
  }

  function fillEmployeeOptions(employees) {
    var currentTimer = employeeSelect.value;
    var currentReport = reportEmployee.value;

    employeeSelect.innerHTML = "";
    reportEmployee.innerHTML = '<option value="">All employees</option>';

    employees.forEach(function (emp) {
      var opt1 = document.createElement("option");
      opt1.value = emp.id;
      opt1.textContent = emp.name + (emp.active_entry ? " (running)" : "");
      employeeSelect.appendChild(opt1);

      var opt2 = document.createElement("option");
      opt2.value = emp.id;
      opt2.textContent = emp.name;
      reportEmployee.appendChild(opt2);
    });

    if (currentTimer) employeeSelect.value = currentTimer;
    if (currentReport) reportEmployee.value = currentReport;
  }

  function renderEmployees(employees) {
    employeeList.innerHTML = "";
    if (!employees.length) {
      employeeList.innerHTML = '<li class="empty">No employees yet. Add the first one above.</li>';
      return;
    }
    employees.forEach(function (emp) {
      var li = document.createElement("li");
      var left = document.createElement("div");
      left.innerHTML = "<strong>" + escapeHtml(emp.name) + "</strong>";
      if (emp.active_entry) {
        left.innerHTML +=
          '<div class="hint" style="margin:4px 0 0">Task: ' +
          escapeHtml(emp.active_entry.task) +
          "</div>";
      }
      var badge = document.createElement("span");
      badge.className = "badge" + (emp.active_entry ? " running" : "");
      badge.textContent = emp.active_entry ? "Timer running" : "Idle";
      li.appendChild(left);
      li.appendChild(badge);
      employeeList.appendChild(li);
    });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderReport(report) {
    totalsEl.innerHTML = "";
    if (!report.totals.length || report.grand_total_seconds === 0 && !report.entries.length) {
      totalsEl.innerHTML = '<div class="empty">Empty report — no time entries in this period.</div>';
    } else {
      report.totals.forEach(function (t) {
        var card = document.createElement("div");
        card.className = "total-card";
        card.innerHTML =
          '<span class="hint">' +
          escapeHtml(t.employee_name) +
          "</span><strong>" +
          formatDuration(t.total_seconds) +
          "</strong><span class="hint">' +
          t.entry_count +
          " entr" +
          (t.entry_count === 1 ? "y" : "ies") +
          "</span>";
        totalsEl.appendChild(card);
      });
      var grand = document.createElement("div");
      grand.className = "total-card";
      grand.innerHTML =
        '<span class="hint">Grand total</span><strong>' +
        formatDuration(report.grand_total_seconds) +
        "</strong>";
      totalsEl.appendChild(grand);
    }

    entriesBody.innerHTML = "";
    if (!report.entries.length) {
      entriesBody.innerHTML =
        '<tr><td colspan="5" class="empty">No records for the selected period.</td></tr>';
      return;
    }
    report.entries.forEach(function (e) {
      var tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" +
        escapeHtml(e.employee_name) +
        "</td><td>" +
        escapeHtml(e.task) +
        "</td><td>" +
        formatWhen(e.started_at) +
        "</td><td>" +
        (e.is_running ? "Running…" : formatWhen(e.ended_at)) +
        "</td><td>" +
        formatDuration(e.duration_seconds) +
        (e.is_running ? " *" : "") +
        "</td>";
      entriesBody.appendChild(tr);
    });
  }

  function refreshEmployees() {
    return api("employees").then(function (data) {
      fillEmployeeOptions(data.employees);
      renderEmployees(data.employees);
      return data.employees;
    });
  }

  function refreshReport() {
    return api("report", {
      query: {
        from: fromInput.value,
        to: toInput.value,
        employee_id: reportEmployee.value,
      },
    }).then(renderReport);
  }

  function refreshAll() {
    return refreshEmployees().then(refreshReport);
  }

  document.getElementById("form-add-employee").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var name = document.getElementById("employee-name").value;
    api("employees", { method: "POST", body: { name: name } })
      .then(function () {
        document.getElementById("employee-name").value = "";
        flash("Employee added");
        return refreshAll();
      })
      .catch(function (err) {
        flash(err.message, true);
      });
  });

  document.getElementById("btn-start").addEventListener("click", function () {
    var employeeId = employeeSelect.value;
    var task = document.getElementById("timer-task").value;
    api("timer/start", {
      method: "POST",
      body: { employee_id: parseInt(employeeId, 10), task: task },
    })
      .then(function () {
        flash("Timer started");
        return refreshAll();
      })
      .catch(function (err) {
        flash(err.message, true);
      });
  });

  document.getElementById("btn-stop").addEventListener("click", function () {
    var employeeId = employeeSelect.value;
    api("timer/stop", {
      method: "POST",
      body: { employee_id: parseInt(employeeId, 10) },
    })
      .then(function () {
        flash("Timer stopped");
        return refreshAll();
      })
      .catch(function (err) {
        flash(err.message, true);
      });
  });

  document.getElementById("form-report").addEventListener("submit", function (ev) {
    ev.preventDefault();
    refreshReport().catch(function (err) {
      flash(err.message, true);
    });
  });

  refreshAll().catch(function (err) {
    flash(err.message, true);
  });
})();
