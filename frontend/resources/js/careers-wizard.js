/**
 * Marketing /meslekler adım adım sihirbazı.
 * Veri: resources/data/careers-catalog.json (PHP üzerinden enjekte).
 */

function radarPoint(i, total, score, cx, cy, maxR) {
    const angle = (2 * Math.PI * i) / total - Math.PI / 2;
    const r = (score / 100) * maxR;
    return [cx + r * Math.cos(angle), cy + r * Math.sin(angle)];
}

function labelPoint(i, total, cx, cy, maxR) {
    const angle = (2 * Math.PI * i) / total - Math.PI / 2;
    const r = maxR + 22;
    return [cx + r * Math.cos(angle), cy + r * Math.sin(angle)];
}

export function renderTargetRadar(skills, { title, subtitle, labels }) {
    const n = skills.length;
    const cx = 160;
    const cy = 160;
    const maxR = 105;
    const rings = [25, 50, 75, 100];

    const poly = skills
        .map((skill, i) => {
            const [x, y] = radarPoint(i, n, skill.level, cx, cy, maxR);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');

    const ringPaths = rings
        .map((ring) => {
            const pts = Array.from({ length: n }, (_, i) => {
                const [x, y] = radarPoint(i, n, ring, cx, cy, maxR);
                return `${x.toFixed(1)},${y.toFixed(1)}`;
            }).join(' ');
            return `<polygon points="${pts}" fill="none" stroke="rgb(51 65 85)" stroke-width="1" opacity="0.6"/>`;
        })
        .join('');

    const axes = Array.from({ length: n }, (_, i) => {
        const [x, y] = radarPoint(i, n, 100, cx, cy, maxR);
        return `<line x1="${cx}" y1="${cy}" x2="${x.toFixed(1)}" y2="${y.toFixed(1)}" stroke="rgb(51 65 85)" stroke-width="1" opacity="0.5"/>`;
    }).join('');

    const skillLabels = skills
        .map((skill, i) => {
            const [lx, ly] = labelPoint(i, n, cx, cy, maxR);
            const anchor = lx < cx - 5 ? 'end' : lx > cx + 5 ? 'start' : 'middle';
            return `<text x="${lx.toFixed(1)}" y="${ly.toFixed(1)}" text-anchor="${anchor}" dominant-baseline="middle" class="fill-slate-400 text-[9px]">${skill.label}</text>`;
        })
        .join('');

    const legend = `
        <div class="mt-3 flex items-center gap-4 text-xs text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                ${labels.target_profile}
            </span>
        </div>`;

    const skillList = skills
        .map(
            (s) => `
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="text-slate-400">${s.label}</span>
            <span class="font-semibold tabular-nums text-emerald-400">${s.level}%</span>
        </div>`
        )
        .join('');

    return `
    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <header class="mb-4">
            <h3 class="text-lg font-semibold text-white">${title}</h3>
            ${subtitle ? `<p class="mt-1 text-sm text-slate-400">${subtitle}</p>` : ''}
        </header>
        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <svg viewBox="0 0 320 320" class="mx-auto h-auto w-full max-w-xs" role="img" aria-label="${title}">
                    ${ringPaths}
                    ${axes}
                    <polygon points="${poly}" fill="rgb(16 185 129 / 0.25)" stroke="rgb(52 211 153)" stroke-width="2"/>
                    ${skills.map((skill, i) => {
                        const [x, y] = radarPoint(i, n, skill.level, cx, cy, maxR);
                        return `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3.5" fill="rgb(52 211 153)"/>`;
                    }).join('')}
                    ${skillLabels}
                </svg>
                ${legend}
            </div>
            <div class="space-y-2 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">${labels.skill_schema}</p>
                ${skillList}
            </div>
        </div>
    </article>`;
}

export function initCareersWizard(root, catalog, labels) {
    const steps = ['main', 'current', 'target', 'salary'];
    let step = 0;
    let showResults = false;
    let autoAdvanceTimer = null;

    const els = {
        stepper: root.querySelector('[data-careers-stepper]'),
        panels: root.querySelectorAll('[data-careers-step]'),
        mainSelect: root.querySelector('[data-careers-main]'),
        currentSelect: root.querySelector('[data-careers-current]'),
        targetList: root.querySelector('[data-careers-target]'),
        salarySelect: root.querySelector('[data-careers-salary]'),
        results: root.querySelector('[data-careers-results]'),
        resultsGrid: root.querySelector('[data-careers-results-grid]'),
        summary: root.querySelector('[data-careers-summary]'),
        btnShow: root.querySelector('[data-careers-show]'),
        btnBack: root.querySelector('[data-careers-back]'),
        btnNext: root.querySelector('[data-careers-next]'),
        btnResultsBack: root.querySelector('[data-careers-results-back]'),
        actions: root.querySelector('[data-careers-actions]'),
        wizard: root.querySelector('[data-careers-wizard]'),
    };

    const state = {
        mainId: '',
        currentRoleId: '',
        targetIds: new Set(),
        salaryId: '',
    };

    const careerById = (id) => catalog.careers.find((c) => c.id === id);

    const currentSubRole = () => {
        const career = careerById(state.mainId);
        if (!career || !state.currentRoleId) return null;
        return career.sub_roles.find((s) => s.id === state.currentRoleId) ?? null;
    };

    const availableTargets = () => {
        const career = careerById(state.mainId);
        const sub = currentSubRole();
        if (!career || !sub) return [];
        const ids = new Set(sub.target_role_ids);
        return career.target_roles.filter((t) => ids.has(t.id));
    };

    const fillMainSelect = () => {
        els.mainSelect.innerHTML = `<option value="">${labels.select_main}</option>`;
        catalog.careers.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.label;
            els.mainSelect.appendChild(opt);
        });
    };

    const fillCurrentSelect = () => {
        const career = careerById(state.mainId);
        els.currentSelect.innerHTML = `<option value="">${labels.select_current}</option>`;
        if (!career) return;
        career.sub_roles.forEach((s) => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.label;
            opt.selected = s.id === state.currentRoleId;
            els.currentSelect.appendChild(opt);
        });
    };

    const fillSalarySelect = () => {
        els.salarySelect.innerHTML = `<option value="">${labels.select_salary}</option>`;
        catalog.salary_ranges.forEach((r) => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.label;
            els.salarySelect.appendChild(opt);
        });
    };

    const canAdvance = () => {
        if (step === 0) return Boolean(state.mainId);
        if (step === 1) return Boolean(state.currentRoleId);
        if (step === 2) return true;
        if (step === 3) return Boolean(state.salaryId);
        return true;
    };

    const goToStep = (nextStep) => {
        step = Math.max(0, Math.min(nextStep, steps.length - 1));
        updateUI();
    };

    const autoAdvance = (delayMs = 0) => {
        clearTimeout(autoAdvanceTimer);
        autoAdvanceTimer = setTimeout(() => {
            if (showResults || !canAdvance() || step >= steps.length - 1) return;
            goToStep(step + 1);
        }, delayMs);
    };

    const renderCheckboxList = (container, items, selectedSet) => {
        container.innerHTML = '';
        if (!items.length) {
            container.innerHTML = `<p class="text-sm text-slate-500">${labels.empty_options}</p>`;
            return;
        }
        items.forEach((item) => {
            const id = `careers-target-${item.id}`;
            const wrap = document.createElement('label');
            wrap.className =
                'flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/50 p-3 transition hover:border-slate-600 has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-500/5';
            wrap.innerHTML = `
                <input type="checkbox" id="${id}" value="${item.id}" class="mt-0.5 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40" ${selectedSet.has(item.id) ? 'checked' : ''} />
                <span class="text-sm text-slate-200">${item.label}</span>`;
            const input = wrap.querySelector('input');
            input.addEventListener('change', () => {
                if (input.checked) selectedSet.add(item.id);
                else selectedSet.delete(item.id);
                updateUI();
            });
            container.appendChild(wrap);
        });
    };

    const updateStepper = () => {
        els.stepper.querySelectorAll('[data-step-index]').forEach((el) => {
            const idx = Number(el.dataset.stepIndex);
            el.classList.toggle('border-emerald-500/60', idx === step);
            el.classList.toggle('bg-emerald-500/10', idx === step);
            el.classList.toggle('text-emerald-400', idx === step);
            el.classList.toggle('text-slate-500', idx > step);
            el.classList.toggle('text-slate-300', idx < step);
        });
    };

    const updatePanels = () => {
        els.panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.careersStep) !== step;
        });
        const onLastStep = step === steps.length - 1;
        els.btnBack.hidden = step === 0;
        els.btnNext.hidden = onLastStep;
        els.btnShow.hidden = !onLastStep;
    };

    const updateUI = () => {
        const career = careerById(state.mainId);
        fillCurrentSelect();
        if (career && state.currentRoleId) {
            renderCheckboxList(els.targetList, availableTargets(), state.targetIds);
        } else if (career) {
            els.targetList.innerHTML = `<p class="text-sm text-slate-500">${labels.pick_current_first}</p>`;
        } else {
            els.targetList.innerHTML = `<p class="text-sm text-slate-500">${labels.pick_main_first}</p>`;
        }
        els.btnNext.disabled = !canAdvance();
        els.btnShow.disabled = !canAdvance();
        updateStepper();
        updatePanels();
    };

    const renderResults = () => {
        const career = careerById(state.mainId);
        const salary = catalog.salary_ranges.find((r) => r.id === state.salaryId);
        const sub = currentSubRole();
        const targets = availableTargets().filter((t) => state.targetIds.has(t.id));

        els.summary.innerHTML = `
            <p class="text-sm text-slate-400">
                <span class="text-slate-200">${career.label}</span>
                · ${labels.current_label}: <span class="text-slate-200">${sub?.label ?? '—'}</span>
                · ${labels.salary}: <span class="text-slate-200">${salary?.label ?? '—'}</span>
            </p>`;

        if (!targets.length) {
            els.resultsGrid.innerHTML = `<p class="rounded-xl border border-slate-800 bg-slate-900/80 p-6 text-sm text-slate-400">${labels.no_targets_selected}</p>`;
        } else {
            els.resultsGrid.innerHTML = targets
                .map((target) =>
                    renderTargetRadar(target.skills, {
                        title: target.label,
                        subtitle: labels.radar_subtitle,
                        labels,
                    })
                )
                .join('');
        }

        els.wizard.hidden = true;
        els.results.hidden = false;
        els.results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const backFromResults = () => {
        showResults = false;
        els.results.hidden = true;
        els.wizard.hidden = false;
        goToStep(steps.length - 1);
    };

    const resetWizard = () => {
        clearTimeout(autoAdvanceTimer);
        state.mainId = '';
        state.currentRoleId = '';
        state.targetIds = new Set();
        state.salaryId = '';
        step = 0;
        showResults = false;
        els.mainSelect.value = '';
        els.currentSelect.value = '';
        els.salarySelect.value = '';
        els.results.hidden = true;
        els.wizard.hidden = false;
        updateUI();
    };

    fillMainSelect();
    fillSalarySelect();

    els.mainSelect.addEventListener('change', () => {
        state.mainId = els.mainSelect.value;
        state.currentRoleId = '';
        state.targetIds = new Set();
        updateUI();
        if (state.mainId && step === 0) {
            autoAdvance(150);
        }
    });

    els.currentSelect.addEventListener('change', () => {
        state.currentRoleId = els.currentSelect.value;
        state.targetIds = new Set(
            [...state.targetIds].filter((tid) => availableTargets().some((t) => t.id === tid))
        );
        updateUI();
        if (state.currentRoleId && step === 1) {
            autoAdvance(150);
        }
    });

    els.salarySelect.addEventListener('change', () => {
        state.salaryId = els.salarySelect.value;
        updateUI();
    });

    els.btnNext.addEventListener('click', () => {
        clearTimeout(autoAdvanceTimer);
        if (!canAdvance() || step >= steps.length - 1) return;
        goToStep(step + 1);
    });

    els.btnBack.addEventListener('click', () => {
        clearTimeout(autoAdvanceTimer);
        if (step <= 0) return;
        goToStep(step - 1);
    });

    els.btnShow.addEventListener('click', () => {
        if (!canAdvance()) return;
        showResults = true;
        renderResults();
    });

    els.btnResultsBack?.addEventListener('click', backFromResults);

    root.querySelector('[data-careers-reset]')?.addEventListener('click', resetWizard);

    updateUI();
}
