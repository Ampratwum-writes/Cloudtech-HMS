// ============================================
// GCTU 9 Hostel — Shared interactivity
// Used across all entity pages (Students, Rooms,
// Bookings, Payments, Staff, Maintenance, Visitors)
// ============================================

// Reads the CSRF token rendered into <meta name="csrf-token"> by partials/header.php.
// ajax-form submissions already carry their own hidden csrf_token field;
// this covers the JS-only quick-action calls (delete/resolve/checkout),
// which have no <form> of their own to hold one.
function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function showToast(message, type) {
    var host = document.getElementById("toastHost");
    if (!host) return;
    var toast = document.createElement("div");
    toast.className = "toast " + (type === "error" ? "error" : "success");
    var icon = type === "error"
        ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
        : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>';
    toast.innerHTML = icon + "<span>" + message + "</span>";
    host.appendChild(toast);
    setTimeout(function () {
        toast.style.transition = "opacity 0.3s ease";
        toast.style.opacity = "0";
        setTimeout(function () { toast.remove(); }, 300);
    }, 3800);
}

// Keeps the "No." column showing a clean 1, 2, 3... sequence
// regardless of what the underlying database ID is, so deleting
// row 5 doesn't leave a gap where "5" used to be.
function renumberTable(table) {
    if (!table) return;
    var rows = table.querySelectorAll("tbody tr");
    var n = 1;
    rows.forEach(function (row) {
        var cell = row.querySelector(".row-num");
        if (cell) {
            cell.textContent = n;
            n++;
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {

    // ---------- Mobile sidebar ----------
    var menuToggle = document.getElementById("menuToggle");
    var sidebar = document.getElementById("sidebar");
    if (menuToggle && sidebar) {
        menuToggle.addEventListener("click", function () {
            sidebar.classList.toggle("open");
        });
    }

    // ---------- Live search (any input[data-search-target]) ----------
    document.querySelectorAll("[data-search-target]").forEach(function (input) {
        var table = document.getElementById(input.getAttribute("data-search-target"));
        if (!table) return;
        var countPill = document.getElementById(input.getAttribute("data-count-target") || "");
        input.addEventListener("input", function () {
            var q = input.value.trim().toLowerCase();
            var rows = table.querySelectorAll("tbody tr");
            var visible = 0;
            rows.forEach(function (row) {
                var match = row.textContent.toLowerCase().indexOf(q) !== -1;
                row.style.display = match ? "" : "none";
                if (match) visible++;
            });
            if (countPill) countPill.textContent = visible + " shown";
        });
    });

    // ---------- Sortable columns (any table th.sortable) ----------
    document.querySelectorAll("table").forEach(function (table) {
        var allHeaders = Array.prototype.slice.call(table.querySelectorAll("thead th"));
        var headers = table.querySelectorAll("th.sortable");
        headers.forEach(function (th) {
            var colIndex = allHeaders.indexOf(th);
            th.addEventListener("click", function () {
                var tbody = table.querySelector("tbody");
                var rowsArr = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
                var asc = th.getAttribute("data-dir") !== "asc";

                headers.forEach(function (h) {
                    h.removeAttribute("data-dir");
                    var arrow = h.querySelector(".sort-arrow");
                    if (arrow) arrow.textContent = "↕";
                });
                th.setAttribute("data-dir", asc ? "asc" : "desc");
                var arrowEl = th.querySelector(".sort-arrow");
                if (arrowEl) arrowEl.textContent = asc ? "↑" : "↓";

                rowsArr.sort(function (a, b) {
                    var aText = a.children[colIndex] ? a.children[colIndex].textContent.trim() : "";
                    var bText = b.children[colIndex] ? b.children[colIndex].textContent.trim() : "";
                    var aNum = parseFloat(aText.replace(/[^0-9.\-]/g, "")), bNum = parseFloat(bText.replace(/[^0-9.\-]/g, ""));
                    var cmp;
                    if (!isNaN(aNum) && !isNaN(bNum) && aText !== "" && bText !== "") {
                        cmp = aNum - bNum;
                    } else {
                        cmp = aText.localeCompare(bText);
                    }
                    return asc ? cmp : -cmp;
                });
                rowsArr.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    });

    // ---------- Modals ----------
    document.querySelectorAll("[data-modal-open]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var modal = document.getElementById(btn.getAttribute("data-modal-open"));
            if (modal) modal.classList.add("open");
        });
    });
    document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var modal = btn.closest(".modal-overlay");
            if (modal) modal.classList.remove("open");
        });
    });
    document.querySelectorAll(".modal-overlay").forEach(function (overlay) {
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay) overlay.classList.remove("open");
        });
    });

    // ---------- AJAX "add record" forms ----------
    // Any <form class="ajax-form" data-target="tbodyId"> posts itself via
    // fetch, expects JSON: { success: true, rowHtml: "<tr>...</tr>" }
    // or { success: false, error: "message" }
    document.querySelectorAll("form.ajax-form").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn ? submitBtn.innerHTML : "";
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading-spin"></span> Saving...';
            }

            fetch(form.getAttribute("action") || window.location.href, {
                method: "POST",
                body: new FormData(form),
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    var targetId = form.getAttribute("data-target");
                    var tbody = targetId ? document.querySelector("#" + targetId + " tbody") : null;
                    if (tbody && data.rowHtml) {
                        var emptyRow = tbody.querySelector(".empty-state");
                        if (emptyRow) emptyRow.closest("tr").remove();
                        tbody.insertAdjacentHTML("afterbegin", data.rowHtml);
                        var newRow = tbody.firstElementChild;
                        newRow.classList.add("row-new");
                        renumberTable(tbody.closest("table"));
                    }
                    var countPill = document.querySelector(".count-pill");
                    if (countPill && data.count !== undefined) {
                        countPill.textContent = data.count + " shown";
                    }
                    showToast(data.message || "Saved successfully.", "success");
                    form.reset();
                    var modal = form.closest(".modal-overlay");
                    if (modal) modal.classList.remove("open");
                } else {
                    showToast(data.error || "Something went wrong.", "error");
                }
            })
            .catch(function () {
                showToast("Network error — check your connection and try again.", "error");
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    });

    // ---------- Quick actions (resolve maintenance / check out visitor / delete) ----------
    document.querySelectorAll("[data-quick-action]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var confirmMsg = btn.getAttribute("data-confirm");
            if (confirmMsg && !window.confirm(confirmMsg)) return;

            var action = btn.getAttribute("data-quick-action");
            var id = btn.getAttribute("data-id");
            btn.disabled = true;
            var original = btn.innerHTML;
            btn.innerHTML = '<span class="loading-spin"></span>';

            var body = new FormData();
            body.append("quick_action", action);
            body.append("id", id);
            body.append("csrf_token", getCsrfToken());

            fetch(window.location.href, {
                method: "POST",
                body: body,
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast(data.message || "Updated.", "success");
                    var row = btn.closest("tr");
                    if (data.removed && row) {
                        row.style.transition = "opacity 0.25s ease";
                        row.style.opacity = "0";
                        setTimeout(function () {
                            var tbody = row.parentElement;
                            var table = tbody ? tbody.closest("table") : null;
                            row.remove();
                            var countPill = document.getElementById("rowCount");
                            if (countPill && data.count !== undefined) countPill.textContent = data.count + " shown";
                            if (tbody && tbody.children.length === 0) {
                                var colCount = table.querySelectorAll("thead th").length;
                                tbody.innerHTML = '<tr><td colspan="' + colCount + '"><div class="empty-state">No records left.</div></td></tr>';
                            } else if (table) {
                                renumberTable(table);
                            }
                        }, 250);
                    } else if (row && data.rowHtml) {
                        var existingNum = row.querySelector(".row-num");
                        var preservedNum = existingNum ? existingNum.textContent : "";
                        var temp = document.createElement("tbody");
                        temp.innerHTML = data.rowHtml.trim();
                        var newRow = temp.firstElementChild;
                        if (newRow) {
                            if (preservedNum !== "") {
                                var newNumCell = newRow.querySelector(".row-num");
                                if (newNumCell) newNumCell.textContent = preservedNum;
                            }
                            row.replaceWith(newRow);
                        }
                    }
                } else {
                    showToast(data.error || "Could not update.", "error");
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            })
            .catch(function () {
                showToast("Network error — try again.", "error");
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });
    });

    // ---------- Auto-dismiss inline alert banners ----------
    var alertBox = document.querySelector(".alert");
    if (alertBox) {
        setTimeout(function () {
            alertBox.style.transition = "opacity 0.4s ease";
            alertBox.style.opacity = "0";
            setTimeout(function () { alertBox.style.display = "none"; }, 400);
        }, 4000);
    }
});
