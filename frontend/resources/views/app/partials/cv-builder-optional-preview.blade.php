<template x-for="sectionKey in locales[previewLang].enabledOptional || []" :key="sectionKey">
  <div x-show="optionalPreviewVisible(sectionKey)" class="cv-optional-preview-block">
      <h2 x-text="uiLabels[previewLang].sections[sectionKey]"></h2>

      <template x-if="sectionKey === 'awards'">
        <template x-for="entry in optionalPreviewEntries('awards')" :key="entry.id">
          <p class="mb-1">
            <strong x-text="entry.title"></strong>
            <span x-show="entry.issuer"> · <span x-text="entry.issuer"></span></span>
            <span x-show="entry.date"> (<span x-text="entry.date"></span>)</span>
            <span x-show="entry.details"> — <span x-text="entry.details"></span></span>
          </p>
        </template>
      </template>

      <template x-if="sectionKey === 'volunteer' || sectionKey === 'leadership'">
        <template x-for="entry in optionalPreviewEntries(sectionKey)" :key="entry.id">
          <div class="mb-2">
            <div class="entry-header">
              <span x-text="entry.organization"></span>
              <span x-text="entry.location"></span>
            </div>
            <div class="entry-sub">
              <span x-text="entry.role"></span>
              <span x-text="entry.start + (entry.end ? ' - ' + entry.end : '')"></span>
            </div>
            <ul>
              <template x-for="b in (entry.bullets || []).filter(x => x.trim())" :key="b">
                <li x-text="b"></li>
              </template>
            </ul>
          </div>
        </template>
      </template>

      <template x-if="sectionKey === 'publications'">
        <template x-for="entry in optionalPreviewEntries('publications')" :key="entry.id">
          <div class="mb-2">
            <p><strong x-text="entry.title"></strong><span x-show="entry.publisher">, <span x-text="entry.publisher"></span></span><span x-show="entry.date"> (<span x-text="entry.date"></span>)</span></p>
            <p x-show="entry.link" class="text-[10pt]" x-text="entry.link"></p>
            <p x-show="entry.description" x-text="entry.description"></p>
          </div>
        </template>
      </template>

      <template x-if="sectionKey === 'courses'">
        <template x-for="entry in optionalPreviewEntries('courses')" :key="entry.id">
          <div class="mb-2">
            <p><strong x-text="entry.name"></strong><span x-show="entry.institution">, <span x-text="entry.institution"></span></span><span x-show="entry.date"> (<span x-text="entry.date"></span>)</span></p>
            <p x-show="entry.description" x-text="entry.description"></p>
          </div>
        </template>
      </template>

      <template x-if="sectionKey === 'languages'">
        <template x-for="entry in optionalPreviewEntries('languages')" :key="entry.id">
          <p><span x-text="entry.language"></span><span x-show="entry.level"> — <span x-text="entry.level"></span></span></p>
        </template>
      </template>

      <template x-if="sectionKey === 'affiliations'">
        <template x-for="entry in optionalPreviewEntries('affiliations')" :key="entry.id">
          <p class="mb-1">
            <span x-text="entry.name"></span>
            <span x-show="entry.role">, <span x-text="entry.role"></span></span>
            <span x-show="entry.start || entry.end"> (<span x-text="entry.start + (entry.end ? ' - ' + entry.end : '')"></span>)</span>
          </p>
        </template>
      </template>

      <template x-if="sectionKey === 'references'">
        <template x-for="entry in optionalPreviewEntries('references')" :key="entry.id">
          <p class="mb-1">
            <strong x-text="entry.name"></strong>
            <span x-show="entry.title">, <span x-text="entry.title"></span></span>
            <span x-show="entry.organization"> — <span x-text="entry.organization"></span></span>
            <span x-show="entry.contact"> · <span x-text="entry.contact"></span></span>
          </p>
        </template>
      </template>

      <template x-if="sectionKey === 'interests'">
        <template x-for="entry in optionalPreviewEntries('interests')" :key="entry.id">
          <p x-text="entry.items"></p>
        </template>
      </template>

      <template x-if="sectionKey === 'research'">
        <template x-for="entry in optionalPreviewEntries('research')" :key="entry.id">
          <div class="mb-2">
            <div class="entry-header">
              <span x-text="entry.title"></span>
              <span x-text="entry.start + (entry.end ? ' - ' + entry.end : '')"></span>
            </div>
            <div class="entry-sub" x-show="entry.institution">
              <span x-text="entry.institution"></span>
            </div>
            <p x-show="entry.description" x-text="entry.description"></p>
          </div>
        </template>
      </template>

      <template x-if="sectionKey === 'additional'">
        <template x-for="entry in optionalPreviewEntries('additional')" :key="entry.id">
          <p x-text="entry.body"></p>
        </template>
      </template>
  </div>
</template>
