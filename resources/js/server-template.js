/**
 * Text delimiters for the Vue app that compiles the server HTML in #app.
 *
 * Vue reads the HTML inside #app as a template. Blade escapes user text, but
 * it keeps "{{" and "}}". With the default delimiters, Vue runs that text as
 * JavaScript. The Blade views use no Vue text interpolation.
 *
 * These delimiters start and end with U+00A0. The browser writes U+00A0 as
 * "&nbsp;" in innerHTML, which is the template that Vue reads. Thus the
 * template never contains these delimiters, and Vue never finds an
 * interpolation in the server HTML. Precompiled .vue files are not affected.
 */
export const serverTemplateDelimiters = [' {{', '}} ']
