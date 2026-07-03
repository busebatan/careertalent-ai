<template x-for="sectionKey in locales[editLang].enabledOptional || []" :key="sectionKey">
  <details open class="panel-card" data-optional-section>
    <summary class="flex cursor-pointer items-center justify-between gap-2 px-5 py-4 font-semibold">
      <span x-text="optionalSectionLabel(sectionKey)"></span>
      <span class="flex shrink-0 items-center gap-3 text-xs font-normal">
        <button type="button" @click.prevent="addOptionalEntry(sectionKey)"
          class="text-emerald-600 hover:underline dark:text-emerald-400"
          x-text="uiLabels[editLang].form.add"></button>
        <button type="button" @click.prevent="removeOptionalSection(sectionKey)"
          class="panel-btn-danger"
          x-text="uiLabels[editLang].optional_section_remove"></button>
      </span>
    </summary>
    <div class="panel-section-divider space-y-4 px-5 py-4">
      <template x-for="(entry, idx) in locales[editLang].optional[sectionKey]" :key="entry.id">
        <div class="panel-entry">
          <div class="mb-2 flex justify-between">
            <span class="panel-muted text-xs" x-text="uiLabels[editLang].form.record + ' ' + (idx + 1)"></span>
            <button type="button" @click="removeOptionalEntry(sectionKey, entry.id)"
              class="panel-btn-danger" x-text="uiLabels[editLang].form.delete"></button>
          </div>

          <template x-if="sectionKey === 'awards'">
            <div class="space-y-2">
              <input type="text" x-model="entry.title" :placeholder="uiLabels[editLang].form.award_title" class="panel-input-block">
              <div class="grid gap-2 sm:grid-cols-2">
                <input type="text" x-model="entry.issuer" :placeholder="uiLabels[editLang].form.issuer" class="panel-input">
                <input type="text" x-model="entry.date" :placeholder="uiLabels[editLang].form.date" class="panel-input">
              </div>
              <textarea x-model="entry.details" rows="2" :placeholder="uiLabels[editLang].form.details" class="panel-input-block"></textarea>
            </div>
          </template>

          <template x-if="sectionKey === 'volunteer' || sectionKey === 'leadership'">
            <div class="space-y-2">
              <input type="text" x-model="entry.organization" :placeholder="uiLabels[editLang].form.organization" class="panel-input-block">
              <input type="text" x-model="entry.role" :placeholder="uiLabels[editLang].form.role" class="panel-input-block">
              <div class="grid grid-cols-3 gap-2">
                <input type="text" x-model="entry.location" :placeholder="uiLabels[editLang].form.location" class="panel-input">
                <input type="text" x-model="entry.start" :placeholder="uiLabels[editLang].form.start" class="panel-input">
                <input type="text" x-model="entry.end" :placeholder="uiLabels[editLang].form.end" class="panel-input">
              </div>
              <template x-for="(bullet, bIdx) in entry.bullets" :key="bIdx">
                <div class="flex gap-2">
                  <input type="text" x-model="entry.bullets[bIdx]" :placeholder="uiLabels[editLang].form.bullet" class="panel-input flex-1">
                  <button type="button" @click="entry.bullets.splice(bIdx, 1)" class="panel-btn-danger text-xs" x-show="entry.bullets.length > 1">×</button>
                </div>
              </template>
              <button type="button" @click="entry.bullets.push('')" class="text-xs text-emerald-600 dark:text-emerald-400" x-text="uiLabels[editLang].form.add_bullet"></button>
            </div>
          </template>

          <template x-if="sectionKey === 'publications'">
            <div class="space-y-2">
              <input type="text" x-model="entry.title" :placeholder="uiLabels[editLang].form.publication_title" class="panel-input-block">
              <div class="grid gap-2 sm:grid-cols-2">
                <input type="text" x-model="entry.publisher" :placeholder="uiLabels[editLang].form.publisher" class="panel-input">
                <input type="text" x-model="entry.date" :placeholder="uiLabels[editLang].form.date" class="panel-input">
              </div>
              <input type="text" x-model="entry.link" :placeholder="uiLabels[editLang].form.link_optional" class="panel-input-block">
              <textarea x-model="entry.description" rows="2" :placeholder="uiLabels[editLang].form.description" class="panel-input-block"></textarea>
            </div>
          </template>

          <template x-if="sectionKey === 'courses'">
            <div class="space-y-2">
              <input type="text" x-model="entry.name" :placeholder="uiLabels[editLang].form.course_name" class="panel-input-block">
              <div class="grid gap-2 sm:grid-cols-2">
                <input type="text" x-model="entry.institution" :placeholder="uiLabels[editLang].form.institution" class="panel-input">
                <input type="text" x-model="entry.date" :placeholder="uiLabels[editLang].form.date" class="panel-input">
              </div>
              <textarea x-model="entry.description" rows="2" :placeholder="uiLabels[editLang].form.description" class="panel-input-block"></textarea>
            </div>
          </template>

          <template x-if="sectionKey === 'languages'">
            <div class="grid gap-2 sm:grid-cols-2">
              <input type="text" x-model="entry.language" :placeholder="uiLabels[editLang].form.language" class="panel-input">
              <input type="text" x-model="entry.level" :placeholder="uiLabels[editLang].form.level" class="panel-input">
            </div>
          </template>

          <template x-if="sectionKey === 'affiliations'">
            <div class="space-y-2">
              <input type="text" x-model="entry.name" :placeholder="uiLabels[editLang].form.affiliation_name" class="panel-input-block">
              <input type="text" x-model="entry.role" :placeholder="uiLabels[editLang].form.role" class="panel-input-block">
              <div class="grid grid-cols-2 gap-2">
                <input type="text" x-model="entry.start" :placeholder="uiLabels[editLang].form.start" class="panel-input">
                <input type="text" x-model="entry.end" :placeholder="uiLabels[editLang].form.end" class="panel-input">
              </div>
            </div>
          </template>

          <template x-if="sectionKey === 'references'">
            <div class="space-y-2">
              <input type="text" x-model="entry.name" :placeholder="uiLabels[editLang].form.reference_name" class="panel-input-block">
              <input type="text" x-model="entry.title" :placeholder="uiLabels[editLang].form.reference_title" class="panel-input-block">
              <input type="text" x-model="entry.organization" :placeholder="uiLabels[editLang].form.organization" class="panel-input-block">
              <input type="text" x-model="entry.contact" :placeholder="uiLabels[editLang].form.reference_contact" class="panel-input-block">
            </div>
          </template>

          <template x-if="sectionKey === 'interests'">
            <textarea x-model="entry.items" rows="2" :placeholder="uiLabels[editLang].form.interests_items" class="panel-input-block"></textarea>
          </template>

          <template x-if="sectionKey === 'research'">
            <div class="space-y-2">
              <input type="text" x-model="entry.title" :placeholder="uiLabels[editLang].form.research_title" class="panel-input-block">
              <input type="text" x-model="entry.institution" :placeholder="uiLabels[editLang].form.institution" class="panel-input-block">
              <div class="grid grid-cols-2 gap-2">
                <input type="text" x-model="entry.start" :placeholder="uiLabels[editLang].form.start" class="panel-input">
                <input type="text" x-model="entry.end" :placeholder="uiLabels[editLang].form.end" class="panel-input">
              </div>
              <textarea x-model="entry.description" rows="2" :placeholder="uiLabels[editLang].form.description" class="panel-input-block"></textarea>
            </div>
          </template>

          <template x-if="sectionKey === 'additional'">
            <textarea x-model="entry.body" rows="4" :placeholder="uiLabels[editLang].form.additional_body" class="panel-input-block"></textarea>
          </template>
        </div>
      </template>
    </div>
  </details>
</template>

<div class="panel-card p-4">
  <label class="panel-muted mb-2 block text-xs font-medium uppercase tracking-wide"
    x-text="uiLabels[editLang].optional_section_prompt"></label>
  <div class="flex flex-col gap-2 sm:flex-row">
    <select x-model="optionalSectionPick" class="panel-input flex-1">
      <option value="" x-text="uiLabels[editLang].optional_section_placeholder"></option>
      <template x-for="key in availableOptionalSections()" :key="key">
        <option :value="key" x-text="optionalSectionLabel(key)"></option>
      </template>
    </select>
    <button type="button" @click="addOptionalSectionFromDropdown()"
      :disabled="!optionalSectionPick"
      class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
      x-text="uiLabels[editLang].optional_section_add"></button>
  </div>
</div>
