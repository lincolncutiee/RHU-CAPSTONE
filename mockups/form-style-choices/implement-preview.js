(function () {
  const pages = [
    { id: "pcsf" },
    { id: "itr" },
    { id: "enrollment" },
    { id: "history" },
    { id: "social" },
    { id: "ekas" },
    { id: "epress" }
  ];

  const navItems = document.querySelectorAll(".nav-item");
  const pageEls = document.querySelectorAll(".page");
  const btnPrev = document.getElementById("btnPrev");
  const btnNext = document.getElementById("btnNext");
  const sidePrev = document.getElementById("sidePrev");
  const sideNext = document.getElementById("sideNext");
  const pageOf = document.getElementById("pageOf");
  let index = 0;
  const sectionIndex = {};

  function activePageEl() {
    return document.querySelector(`.page[data-page="${pages[index].id}"]`);
  }

  function updateSideButtons(pageEl) {
    if (!pageEl || pageEl.dataset.stepped !== "1") {
      sidePrev.disabled = true;
      sideNext.disabled = true;
      sidePrev.hidden = true;
      sideNext.hidden = true;
      return;
    }
    sidePrev.hidden = false;
    sideNext.hidden = false;
    const current = sectionIndex[pageEl.dataset.page] || 0;
    const max = pageEl.querySelectorAll(".step-pane").length - 1;
    sidePrev.disabled = current <= 0;
    sideNext.disabled = current >= max;
  }

  function setSection(pageEl, step) {
    if (!pageEl || pageEl.dataset.stepped !== "1") {
      updateSideButtons(pageEl);
      return;
    }
    const panes = [...pageEl.querySelectorAll(".step-pane")];
    const max = panes.length - 1;
    const s = Math.max(0, Math.min(max, step));
    sectionIndex[pageEl.dataset.page] = s;

    panes.forEach((pane) => {
      const on = Number(pane.dataset.step) === s;
      pane.hidden = !on;
      pane.classList.toggle("is-active", on);
    });

    pageEl.querySelectorAll(".step-dot").forEach((dot) => {
      const g = Number(dot.dataset.goto);
      dot.classList.toggle("is-active", g === s);
      dot.classList.toggle("is-done", g < s);
    });

    updateSideButtons(pageEl);
  }

  function show(i) {
    index = Math.max(0, Math.min(pages.length - 1, i));
    const meta = pages[index];

    navItems.forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.page === meta.id);
    });

    pageEls.forEach((el) => {
      const on = el.dataset.page === meta.id;
      el.hidden = !on;
      el.classList.toggle("is-active", on);
    });

    btnPrev.disabled = index === 0;
    if (pageOf) pageOf.textContent = `Page ${index + 1} of ${pages.length}`;
    if (btnNext) {
      btnNext.textContent = index === pages.length - 1 ? "🩺 Send to Doctor" : "Next form →";
    }

    const pageEl = activePageEl();
    if (pageEl && pageEl.dataset.stepped === "1") {
      setSection(pageEl, sectionIndex[meta.id] || 0);
    } else {
      updateSideButtons(pageEl);
    }
  }

  navItems.forEach((btn) => {
    btn.addEventListener("click", () => {
      const i = pages.findIndex((p) => p.id === btn.dataset.page);
      if (i >= 0) show(i);
    });
  });

  btnPrev.addEventListener("click", () => show(index - 1));
  btnNext.addEventListener("click", () => {
    if (index < pages.length - 1) show(index + 1);
  });

  sidePrev.addEventListener("click", () => {
    const pageEl = activePageEl();
    if (!pageEl || pageEl.dataset.stepped !== "1") return;
    const current = sectionIndex[pageEl.dataset.page] || 0;
    setSection(pageEl, current - 1);
  });

  sideNext.addEventListener("click", () => {
    const pageEl = activePageEl();
    if (!pageEl || pageEl.dataset.stepped !== "1") return;
    const current = sectionIndex[pageEl.dataset.page] || 0;
    const max = pageEl.querySelectorAll(".step-pane").length - 1;
    setSection(pageEl, Math.min(max, current + 1));
  });

  document.addEventListener("click", (e) => {
    const gotoDot = e.target.closest(".step-dot[data-goto]");
    const pageEl = activePageEl();
    if (!gotoDot || !pageEl || !pageEl.contains(gotoDot)) return;
    setSection(pageEl, Number(gotoDot.dataset.goto));
  });

  show(0);
})();
