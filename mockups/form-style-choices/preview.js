(function () {
  const form = document.getElementById("styleForm");
  const choices = form.querySelectorAll(".choice");
  const panelOfficial = document.getElementById("panelOfficial");
  const panelClean = document.getElementById("panelClean");
  const panelPrint = document.getElementById("panelPrint");
  const tabs = document.querySelectorAll(".tab");
  const btnSave = document.getElementById("btnSave");
  const saveHint = document.getElementById("saveHint");
  const navItems = document.querySelectorAll(".form-nav-item");
  const fieldsEl = document.getElementById("cleanFormFields");
  const stepLabel = document.getElementById("cleanStepLabel");
  const formTitle = document.getElementById("cleanFormTitle");
  const formHelp = document.getElementById("cleanFormHelp");
  const btnPrev = document.getElementById("btnPrevForm");
  const btnNext = document.getElementById("btnNextForm");

  const KEY = "rhu_mock_form_style";
  let activeTab = "screen";
  let style = localStorage.getItem(KEY) || "official";
  let formIndex = 0;

  // Same 7 pages as current Official RHU Forms sidebar
  const FORMS = [
    {
      id: "pcsf",
      title: "PhilHealth Primary Care Selection Form (PCSF)",
      help: "Same PCSF page as current system — spaced fields for easier encoding.",
      fields: [
        ["PhilHealth Identification Number (PIN)", "text", "e.g. 12-345678901-2"],
        ["Member type", "select", "Member|Dependent"],
        ["Primary care facility", "text", "RHU Magallanes"],
        ["Date of selection", "date", "2026-11-25"],
        ["Reason for registration", "textarea", "Walk-in / transfer / others…"]
      ]
    },
    {
      id: "itr",
      title: "Individual Treatment Record",
      help: "Same ITR content as today. Cleaner spacing on screen; print stays official.",
      fields: [
        ["Date of consultation", "date", "2026-11-25"],
        ["Mode of transaction", "select", "Walk-in|Visited|Referral"],
        ["Blood pressure", "text", "e.g. 120/80"],
        ["Temperature", "text", "e.g. 36.5"],
        ["Chief complaints", "textarea", "Describe the main complaint…"]
      ]
    },
    {
      id: "enrollment",
      title: "Patient Enrollment Record",
      help: "Same enrollment fields — demographics & contact, less crowded layout.",
      fields: [
        ["Last name", "text", ""],
        ["First name", "text", ""],
        ["Sex", "select", "Female|Male"],
        ["Birth date", "date", ""],
        ["Address / barangay", "textarea", ""]
      ]
    },
    {
      id: "history",
      title: "Past Medical / Surgical / Family History",
      help: "Same history page — medical, surgical, and family history sections.",
      fields: [
        ["Past medical history", "textarea", "Illnesses, hospitalizations…"],
        ["Past surgical history", "textarea", "Operations / procedures…"],
        ["Family history", "textarea", "Relevant family conditions…"]
      ]
    },
    {
      id: "social",
      title: "Personal / Social History, NCD & Photo Consent",
      help: "Same personal/social, NCD risk, and photo consent page.",
      fields: [
        ["Smoking status", "select", "Never|Former|Current"],
        ["Alcohol use", "select", "Never|Occasional|Regular"],
        ["NCD risk notes", "textarea", "Hypertension, diabetes risk, etc."],
        ["Photo consent", "select", "Yes|No"]
      ]
    },
    {
      id: "ekas",
      title: "Electronic Konsulta Availment Slip (EKAS)",
      help: "Same EKAS availment slip — screen cleaner; printed slip unchanged.",
      fields: [
        ["Availment date", "date", "2026-11-25"],
        ["Service availed", "text", "e.g. Consultation"],
        ["Provider / facility", "text", "RHU Magallanes"],
        ["Remarks", "textarea", ""]
      ]
    },
    {
      id: "epress",
      title: "Electronic Prescription Slip (EPRESS)",
      help: "Same EPRESS page — prescription lines clearer on screen.",
      fields: [
        ["Medicine / generic name", "text", ""],
        ["Dosage / frequency", "text", "e.g. 1 tab BID"],
        ["Quantity", "text", ""],
        ["Instructions", "textarea", "Take after meals…"]
      ]
    }
  ];

  function fieldHtml(label, type, placeholder) {
    const id = label.toLowerCase().replace(/[^a-z0-9]+/g, "-");
    if (type === "select") {
      const opts = String(placeholder || "").split("|").map((o) => `<option>${o}</option>`).join("");
      return `<label><span>${label}</span><select>${opts}</select></label>`;
    }
    if (type === "textarea") {
      return `<label class="full"><span>${label}</span><textarea rows="3" placeholder="${placeholder || ""}"></textarea></label>`;
    }
    if (type === "date") {
      return `<label><span>${label}</span><input type="date" value="${placeholder || ""}" /></label>`;
    }
    return `<label><span>${label}</span><input type="text" placeholder="${placeholder || ""}" id="${id}" /></label>`;
  }

  function showForm(index) {
    formIndex = Math.max(0, Math.min(FORMS.length - 1, index));
    const f = FORMS[formIndex];

    navItems.forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.form === f.id);
    });

    stepLabel.textContent = `Form ${formIndex + 1} of ${FORMS.length}`;
    formTitle.textContent = f.title;
    formHelp.textContent = f.help;
    fieldsEl.innerHTML = f.fields.map(([label, type, ph]) => fieldHtml(label, type, ph)).join("");

    btnPrev.disabled = formIndex === 0;
    btnNext.textContent = formIndex === FORMS.length - 1 ? "Save (last form)" : "Save & Next form";
  }

  function setChoiceUI(value) {
    choices.forEach((el) => {
      const on = el.dataset.style === value;
      el.classList.toggle("is-active", on);
      const input = el.querySelector('input[type="radio"]');
      if (input) input.checked = on;
    });
  }

  function showScreenPanels() {
    const isClean = style === "clean";
    panelOfficial.hidden = isClean;
    panelClean.hidden = !isClean;
    panelPrint.hidden = true;
  }

  function showPrintPanel() {
    panelOfficial.hidden = true;
    panelClean.hidden = true;
    panelPrint.hidden = false;
  }

  function render() {
    setChoiceUI(style);
    if (activeTab === "print") {
      showPrintPanel();
    } else {
      showScreenPanels();
    }
  }

  choices.forEach((el) => {
    el.addEventListener("click", () => {
      style = el.dataset.style;
      render();
    });
  });

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      activeTab = tab.dataset.panel;
      tabs.forEach((t) => t.classList.toggle("is-active", t === tab));
      render();
    });
  });

  navItems.forEach((btn) => {
    btn.addEventListener("click", () => {
      const idx = FORMS.findIndex((f) => f.id === btn.dataset.form);
      if (idx >= 0) showForm(idx);
    });
  });

  btnPrev.addEventListener("click", () => showForm(formIndex - 1));
  btnNext.addEventListener("click", () => {
    if (formIndex < FORMS.length - 1) showForm(formIndex + 1);
  });

  btnSave.addEventListener("click", () => {
    localStorage.setItem(KEY, style);
    saveHint.hidden = false;
    setTimeout(() => {
      saveHint.hidden = true;
    }, 3200);
  });

  showForm(0);
  render();
})();
