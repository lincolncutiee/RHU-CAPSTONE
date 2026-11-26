/**
 * Clean guided encoding — side arrows + step dots for .clean-guided panes.
 * Admin form_display_style = clean only.
 */
(function () {
  const cfg = window.RHU_FORM || {};
  if ((cfg.formDisplayStyle || 'official') !== 'clean') return;

  const root = document.getElementById('emrEncodingRoot');
  const wizardMain = document.getElementById('wizardMain');
  if (!root || !wizardMain) return;

  const stepState = new WeakMap();

  const sidePrev = document.createElement('button');
  sidePrev.type = 'button';
  sidePrev.className = 'clean-side-btn clean-side-prev';
  sidePrev.title = 'Previous section';
  sidePrev.setAttribute('aria-label', 'Previous section');
  sidePrev.innerHTML = '<i class="bi bi-chevron-left" aria-hidden="true"></i>';

  const sideNext = document.createElement('button');
  sideNext.type = 'button';
  sideNext.className = 'clean-side-btn clean-side-next';
  sideNext.title = 'Next section';
  sideNext.setAttribute('aria-label', 'Next section');
  sideNext.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';

  /* Match mockup stage: prev | scroll | next */
  wizardMain.insertBefore(sidePrev, wizardMain.firstChild);
  wizardMain.appendChild(sideNext);

  /* Mirror mockup text "Consultation types" ↔ checkbox values for save/load */
  function syncConsultFromPills(rootEl) {
    const mirror = rootEl.querySelector('[data-clean-consult-mirror]');
    const pills = rootEl.querySelector('[data-clean-consult-pills]');
    if (!mirror || !pills) return;
    const checked = [...pills.querySelectorAll('input[type="checkbox"]:checked')].map((cb) => cb.value);
    mirror.value = checked.join(', ');
  }

  function syncConsultFromMirror(rootEl) {
    const mirror = rootEl.querySelector('[data-clean-consult-mirror]');
    const pills = rootEl.querySelector('[data-clean-consult-pills]');
    if (!mirror || !pills) return;
    const tokens = mirror.value.split(/[,;]+/).map((s) => s.trim().toLowerCase()).filter(Boolean);
    pills.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
      const v = cb.value.toLowerCase();
      cb.checked = tokens.some((t) => v === t || v.includes(t) || t.includes(v));
    });
  }

  root.addEventListener('input', (e) => {
    const mirror = e.target.closest('[data-clean-consult-mirror]');
    if (!mirror) return;
    const guided = mirror.closest('.clean-guided');
    if (guided) syncConsultFromMirror(guided);
  });
  root.addEventListener('change', (e) => {
    if (!e.target.matches('[data-clean-consult-pills] input')) return;
    const guided = e.target.closest('.clean-guided');
    if (guided) syncConsultFromPills(guided);
  });

  function activePane() {
    return root.querySelector('.wizard-page-pane.active');
  }

  function guidedRoot(pane) {
    return pane?.querySelector('.clean-guided') || null;
  }

  function setStep(pane, index) {
    const guided = guidedRoot(pane);
    if (!guided) {
      sidePrev.disabled = true;
      sideNext.disabled = true;
      return;
    }
    const steps = [...guided.querySelectorAll('.clean-step')];
    if (!steps.length) {
      sidePrev.disabled = true;
      sideNext.disabled = true;
      return;
    }
    const max = steps.length - 1;
    const i = Math.max(0, Math.min(max, index));
    stepState.set(pane, i);

    steps.forEach((el, idx) => {
      const on = idx === i;
      el.classList.toggle('is-active', on);
      el.hidden = !on;
    });
    guided.querySelectorAll('.clean-step-dot').forEach((dot) => {
      const g = Number(dot.dataset.goto);
      dot.classList.toggle('is-active', g === i);
      dot.classList.toggle('is-done', g < i);
    });

    sidePrev.disabled = i <= 0;
    sideNext.disabled = i >= max;
    steps[i].scrollTop = 0;
  }

  function syncActive() {
    const pane = activePane();
    if (!pane || pane.dataset.isReview === '1' || root.classList.contains('is-review-workspace')) {
      sidePrev.style.display = 'none';
      sideNext.style.display = 'none';
      return;
    }
    const guided = guidedRoot(pane);
    if (!guided || guided.querySelectorAll('.clean-step').length <= 1) {
      sidePrev.style.display = guided ? 'none' : 'none';
      sideNext.style.display = 'none';
      if (guided) setStep(pane, 0);
      return;
    }
    sidePrev.style.display = '';
    sideNext.style.display = '';
    setStep(pane, stepState.get(pane) || 0);
    syncConsultFromPills(guided);
  }

  sidePrev.addEventListener('click', () => {
    const pane = activePane();
    if (pane) setStep(pane, (stepState.get(pane) || 0) - 1);
  });
  sideNext.addEventListener('click', () => {
    const pane = activePane();
    if (pane) setStep(pane, (stepState.get(pane) || 0) + 1);
  });

  root.addEventListener('click', (e) => {
    const dot = e.target.closest('.clean-step-dot[data-goto]');
    if (!dot) return;
    const pane = activePane();
    const guided = guidedRoot(pane);
    if (!guided || !guided.contains(dot)) return;
    setStep(pane, Number(dot.dataset.goto));
  });

  const pages = document.getElementById('wizardPages');
  if (pages) {
    let t = 0;
    new MutationObserver(() => {
      window.clearTimeout(t);
      t = window.setTimeout(syncActive, 40);
    }).observe(pages, {
      attributes: true,
      subtree: true,
      attributeFilter: ['class']
    });
  }

  syncActive();
})();
