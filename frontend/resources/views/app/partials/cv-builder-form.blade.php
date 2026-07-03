<div x-show="mode === 'edit'" class="space-y-4">
  <div class="panel-card p-4">
    <p class="panel-muted mb-3 text-xs font-medium uppercase tracking-wide"
      x-text="uiLabels[panelLocale].content_lang"></p>
    <div class="flex gap-2">
      <button type="button" @click="editLang = 'tr'"
        :class="editLang === 'tr' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200'"
        class="rounded-lg px-4 py-2 text-sm font-medium"
        x-text="uiLabels[panelLocale].tab_tr"></button>
      <button type="button" @click="editLang = 'en'"
        :class="editLang === 'en' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200'"
        class="rounded-lg px-4 py-2 text-sm font-medium"
        x-text="uiLabels[panelLocale].tab_en"></button>
    </div>
  </div>

  <details open class="panel-card">
    <summary class="cursor-pointer px-5 py-4 font-semibold" x-text="uiLabels[editLang].form.personal"></summary>
    <div class="panel-section-divider space-y-3 px-5 py-4">
      <input type="text" x-model="locales[editLang].personal.full_name" :placeholder="uiLabels[editLang].form.full_name" class="panel-input-block">
      <div class="grid gap-3 sm:grid-cols-2">
        <input type="email" x-model="locales[editLang].personal.email" :placeholder="uiLabels[editLang].form.email" class="panel-input-block">
        <input type="text" x-model="locales[editLang].personal.phone" :placeholder="uiLabels[editLang].form.phone" class="panel-input-block">
      </div>
      <input type="text" x-model="locales[editLang].personal.location" :placeholder="uiLabels[editLang].form.location" class="panel-input-block">
      <input type="text" x-model="locales[editLang].personal.linkedin" :placeholder="uiLabels[editLang].form.linkedin" class="panel-input-block">
      <div>
        <label class="panel-muted mb-1 block text-xs" x-text="uiLabels[editLang].form.summary"></label>
        <textarea x-model="locales[editLang].personal.summary" rows="3" class="panel-input-block"></textarea>
        <button type="button" @click="aiPolish('summary')"
          class="mt-2 text-xs text-violet-600 hover:text-violet-500 dark:text-violet-300"
          x-text="uiLabels[editLang].form.ai_summary"></button>
      </div>
    </div>
  </details>

  <details open class="panel-card">
    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 font-semibold">
      <span x-text="uiLabels[editLang].form.education"></span>
      <button type="button" @click.prevent="addEducation()" class="text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400" x-text="uiLabels[editLang].form.add"></button>
    </summary>
    <div class="panel-section-divider space-y-4 px-5 py-4">
      <template x-for="(edu, idx) in locales[editLang].education" :key="edu.id">
        <div class="panel-entry">
          <div class="flex justify-between">
            <span class="panel-muted text-xs" x-text="uiLabels[editLang].form.record + ' ' + (idx + 1)"></span>
            <button type="button" @click="removeEducation(editLang, edu.id)" class="panel-btn-danger" x-text="uiLabels[editLang].form.delete"></button>
          </div>
          <input type="text" x-model="edu.institution" :placeholder="uiLabels[editLang].form.institution" class="panel-input-block">
          <input type="text" x-model="edu.degree" :placeholder="uiLabels[editLang].form.degree" class="panel-input-block">
          <div class="grid grid-cols-3 gap-2">
            <input type="text" x-model="edu.location" :placeholder="uiLabels[editLang].form.city" class="panel-input">
            <input type="text" x-model="edu.start" :placeholder="uiLabels[editLang].form.start" class="panel-input">
            <input type="text" x-model="edu.end" :placeholder="uiLabels[editLang].form.end" class="panel-input">
          </div>
          <textarea x-model="edu.details" rows="2" :placeholder="uiLabels[editLang].form.edu_notes" class="panel-input-block"></textarea>
        </div>
      </template>
    </div>
  </details>

  <details open class="panel-card">
    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 font-semibold">
      <span x-text="uiLabels[editLang].form.experience"></span>
      <button type="button" @click.prevent="addExperience()" class="text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400" x-text="uiLabels[editLang].form.add"></button>
    </summary>
    <div class="panel-section-divider space-y-4 px-5 py-4">
      <template x-for="(exp, idx) in locales[editLang].experience" :key="exp.id">
        <div class="panel-entry">
          <div class="flex justify-between">
            <span class="panel-muted text-xs" x-text="uiLabels[editLang].form.position + ' ' + (idx + 1)"></span>
            <button type="button" @click="removeExperience(editLang, exp.id)" class="panel-btn-danger" x-text="uiLabels[editLang].form.delete"></button>
          </div>
          <input type="text" x-model="exp.organization" :placeholder="uiLabels[editLang].form.organization" class="panel-input-block">
          <input type="text" x-model="exp.title" :placeholder="uiLabels[editLang].form.title" class="panel-input-block">
          <div class="grid grid-cols-3 gap-2">
            <input type="text" x-model="exp.location" :placeholder="uiLabels[editLang].form.location" class="panel-input">
            <input type="text" x-model="exp.start" :placeholder="uiLabels[editLang].form.start" class="panel-input">
            <input type="text" x-model="exp.end" :placeholder="uiLabels[editLang].form.end" class="panel-input">
          </div>
          <template x-for="(bullet, bIdx) in exp.bullets" :key="bIdx">
            <div class="flex gap-2">
              <input type="text" x-model="exp.bullets[bIdx]" :placeholder="uiLabels[editLang].form.bullet" class="panel-input flex-1">
              <button type="button" @click="exp.bullets.splice(bIdx, 1)" class="panel-btn-ghost">×</button>
            </div>
          </template>
          <div class="flex flex-wrap gap-2">
            <button type="button" @click="exp.bullets.push('')" class="text-xs text-emerald-600 dark:text-emerald-400" x-text="uiLabels[editLang].form.add_bullet"></button>
            <button type="button" @click="aiPolishExperience(editLang, exp)" class="text-xs text-violet-600 dark:text-violet-300" x-text="uiLabels[editLang].form.ai_edit"></button>
          </div>
        </div>
      </template>
    </div>
  </details>

  <details class="panel-card">
    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 font-semibold">
      <span x-text="uiLabels[editLang].form.skills"></span>
      <button type="button" @click.prevent="addSkill()" class="text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400" x-text="uiLabels[editLang].form.add"></button>
    </summary>
    <div class="panel-section-divider space-y-3 px-5 py-4">
      <template x-for="skill in locales[editLang].skills" :key="skill.id">
        <div class="flex gap-2">
          <input type="text" x-model="skill.category" :placeholder="uiLabels[editLang].form.category" class="panel-input w-28 shrink-0">
          <input type="text" x-model="skill.items" :placeholder="uiLabels[editLang].form.skill_items" class="panel-input flex-1">
          <button type="button" @click="removeSkill(editLang, skill.id)" class="panel-btn-ghost">×</button>
        </div>
      </template>
    </div>
  </details>

  <details class="panel-card">
    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 font-semibold">
      <span x-text="uiLabels[editLang].form.projects"></span>
      <button type="button" @click.prevent="addProject()" class="text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400" x-text="uiLabels[editLang].form.add"></button>
    </summary>
    <div class="panel-section-divider space-y-4 px-5 py-4">
      <template x-for="prj in locales[editLang].projects" :key="prj.id">
        <div class="panel-entry">
          <div class="flex justify-between">
            <span class="panel-muted text-xs" x-text="uiLabels[editLang].form.project"></span>
            <button type="button" @click="removeProject(editLang, prj.id)" class="panel-btn-danger" x-text="uiLabels[editLang].form.delete"></button>
          </div>
          <input type="text" x-model="prj.name" :placeholder="uiLabels[editLang].form.project_name" class="panel-input-block">
          <input type="text" x-model="prj.link" :placeholder="uiLabels[editLang].form.link_optional" class="panel-input-block">
          <textarea x-model="prj.description" rows="2" class="panel-input-block"></textarea>
          <button type="button" @click="aiPolishProject(editLang, prj)" class="text-xs text-violet-600 dark:text-violet-300" x-text="uiLabels[editLang].form.ai_edit"></button>
        </div>
      </template>
    </div>
  </details>

  <details class="panel-card">
    <summary class="flex cursor-pointer items-center justify-between px-5 py-4 font-semibold">
      <span x-text="uiLabels[editLang].form.certificates"></span>
      <button type="button" @click.prevent="addCertificate()" class="text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400" x-text="uiLabels[editLang].form.add"></button>
    </summary>
    <div class="panel-section-divider space-y-3 px-5 py-4">
      <template x-for="cert in locales[editLang].certificates" :key="cert.id">
        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
          <input type="text" x-model="cert.name" :placeholder="uiLabels[editLang].form.cert_name" class="panel-input">
          <input type="text" x-model="cert.issuer" :placeholder="uiLabels[editLang].form.issuer" class="panel-input">
          <button type="button" @click="removeCertificate(editLang, cert.id)" class="panel-btn-ghost">×</button>
          <input type="text" x-model="cert.date" :placeholder="uiLabels[editLang].form.date" class="panel-input sm:col-span-2">
        </div>
      </template>
    </div>
  </details>

  @include('app.partials.cv-builder-optional-sections')

  <div class="panel-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="panel-muted text-sm" x-text="uiLabels[panelLocale].save_hint"></p>
    <button type="button" @click="saveCv()"
      class="rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-60"
      :disabled="saveStatus === 'saving'"
      x-text="saveStatus === 'saved' ? uiLabels[panelLocale].saved : (saveStatus === 'saving' ? uiLabels[panelLocale].saving : uiLabels[panelLocale].save)">
    </button>
  </div>
</div>
