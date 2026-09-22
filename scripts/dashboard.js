document.addEventListener("DOMContentLoaded", function () {
  function updateAiRecommendationNumbers() {
    document.querySelectorAll(".ai-recommendation-item").forEach(function (item, index) {
      const numberSpan = item.querySelector(".ai-recommendation-header h3 > span");
      if (numberSpan) {
        numberSpan.textContent = `${index + 1}. `;
      }
    });
  }

  document.querySelectorAll(".ai-recommendation-item").forEach(function (item) {
    item.addEventListener("click", function () {
      item.classList.toggle("expanded");
    });
  });

  document.querySelectorAll(".delete-ai-recommendation").forEach(function (el) {
    el.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const item = el.closest(".ai-recommendation-item");
      const id = item.getAttribute("data-id");

      fetch("endpoints/ai/delete_recommendation.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.csrfToken,
        },
        body: JSON.stringify({ id: id }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            item.remove();
            updateAiRecommendationNumbers();
            showSuccessMessage(translate("success"));
          } else {
            showErrorMessage(data.message || translate("failed_delete_ai_recommendation"));
          }
        })
        .catch(error => {
          console.error(error);
          showErrorMessage(translate("unknown_error"));
        });
    });
  });

  initDashboardWidgetEditor();
});

function initDashboardWidgetEditor() {
  const dashboard = document.querySelector("section.contain.dashboard");
  const list = document.getElementById("dashboard-widgets-list");
  const editButton = document.getElementById("editDashboardWidgets");
  const doneButton = document.getElementById("doneDashboardWidgets");
  const hint = document.querySelector(".dashboard-edit-hint");

  if (!dashboard || !list || !editButton || !doneButton || typeof Sortable === "undefined") {
    return;
  }

  let sortable = null;
  let editing = false;

  function collectLayout() {
    return Array.from(list.querySelectorAll(".dashboard-widget")).map(function (el) {
      return {
        widget_id: el.getAttribute("data-widget-id"),
        enabled: el.getAttribute("data-enabled") === "1",
      };
    });
  }

  function saveLayout() {
    return fetch("endpoints/settings/dashboard_widgets.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.csrfToken,
      },
      body: JSON.stringify({ widgets: collectLayout() }),
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.success) {
          showSuccessMessage(data.message);
        } else {
          showErrorMessage(data.message || translate("unknown_error"));
        }
        return data;
      })
      .catch(function (error) {
        console.error(error);
        showErrorMessage(translate("unknown_error"));
      });
  }

  function setToggleVisual(widget, enabled) {
    const button = widget.querySelector(".dashboard-widget-toggle");
    if (!button) {
      return;
    }
    const icon = button.querySelector("i");
    button.setAttribute("aria-pressed", enabled ? "true" : "false");
    if (icon) {
      icon.classList.toggle("fa-eye", enabled);
      icon.classList.toggle("fa-eye-slash", !enabled);
    }
  }

  function enterEditMode() {
    editing = true;
    dashboard.classList.add("editing-widgets");
    editButton.hidden = true;
    doneButton.hidden = false;
    if (hint) {
      hint.hidden = false;
    }

    sortable = Sortable.create(list, {
      handle: ".drag-icon",
      ghostClass: "sortable-ghost",
      animation: 150,
      delay: 150,
      delayOnTouchOnly: true,
      touchStartThreshold: 5,
      onEnd: function () {
        saveLayout();
      },
    });
  }

  function exitEditMode() {
    editing = false;
    dashboard.classList.remove("editing-widgets");
    editButton.hidden = false;
    doneButton.hidden = true;
    if (hint) {
      hint.hidden = true;
    }
    if (sortable) {
      sortable.destroy();
      sortable = null;
    }
  }

  editButton.addEventListener("click", function () {
    enterEditMode();
  });

  doneButton.addEventListener("click", function () {
    saveLayout().finally(function () {
      exitEditMode();
    });
  });

  list.addEventListener("click", function (event) {
    const toggle = event.target.closest(".dashboard-widget-toggle");
    if (!toggle || !editing) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();

    const widget = toggle.closest(".dashboard-widget");
    if (!widget) {
      return;
    }

    const enabled = widget.getAttribute("data-enabled") !== "1";
    widget.setAttribute("data-enabled", enabled ? "1" : "0");
    setToggleVisual(widget, enabled);
    saveLayout();
  });
}
