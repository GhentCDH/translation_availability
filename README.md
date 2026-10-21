# Translation availability

Shows which translations of content are available, missing, unpublished or
much shorter than another language, and helps visitors to the languages that
are.

Extracted from `bestor_content_helper` (Bestor) and made configurable, so it
can be used on any multilingual Drupal site.

## What it does

- **Counts the words of every translation** in the fields you choose per
  content type, also through paragraphs (`field_content.field_text`,
  `field_tabs.field_content.field_text`). A paragraph that is not translated
  into a language does not count for that language. Counts are stored on save
  (table `translation_availability`), one row per content item and site language,
  also for missing translations.
- **States** per translation: `missing`, `unpublished`, `empty`, `short`,
  `partial`, `full` (thresholds in words, global defaults that a content type
  can override).
- **Report**: *Reports › Translation availability* (a view on the table): filter on
  language, state and content type, e.g. "everything still missing in Dutch".
- **Visitors**, per content type:
  - a missing translation: show the original (Drupal's default), or the
    "not available in this language" page with links to the languages it is
    available in (editors also get a link to add the translation);
  - an unpublished translation: that page, or access denied;
  - a **Translation notice** block when another language is substantially
    better (place it under *Block layout*);
  - the language switcher marks the languages a page is not available in
    (class `translation-availability--unavailable`, and a title).
- **Twig**: `translation_state(node)`, `translation_suggestions(node)`,
  `translation_availability(node)`, `reading_time(node)`.
- **Submodule `sapi_language_fallback`**: a language filter with fallback for
  Search API views (see its README). Without a toggle, Search API's own
  "Language (with fallback)" processor is simpler and keeps pagers exact.

## Setup

1. `composer require drupal/translation_availability` (add this repository as a
   `vcs` repository) and `drush en translation_availability`.
2. Per content type (*Structure › Content types › Edit*, tab *Translation
   availability*): enable tracking, tick the fields that count as content, choose
   the behaviour.
3. `drush translation-availability:refresh` to count the existing content (also
   after changing fields or thresholds, or adding a language).
4. Optional: place the *Translation notice* block; the defaults are under
   *Configuration › Regional and language › Translation availability*.

## When is another language "substantially better"?

With the thresholds `empty` (10), `short` (200), `full` (1000) and `ratio`
(10), as in Bestor: when the current translation has fewer than `short` words,
another published translation is suggested if

- the current one is missing, unpublished or has no words, or
- the current one has fewer than `empty` words and the other one `ratio` times
  more, or
- the other one has at least `full` words.

Of several suggestions, those far below the best one are left out.

## Extending

Words are read by *text extractor* plugins (`src/Plugin/TextExtractor`), one
per field type: formatted text (`text`, `text_long`, `text_with_summary`) and
plain text (`string`, `string_long`). Add a plugin with the
`#[TextExtractor]` attribute to count other field types. References
(paragraphs, entity references) are followed by the field paths.

## Translations

Dutch and French translations of the texts for visitors are in
`translations/`; Drupal imports them when the module is installed in
`modules/contrib` (see `interface translation server pattern` in the info
file).

## Requirements

Drupal 10.3 or 11, with Language, Content Translation and Node. Views for the
report, Search API for the submodule.
