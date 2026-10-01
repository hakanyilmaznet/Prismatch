# AI Agent Skill: PHP Architecture (SOLID), Mobile-First Design & Modern Technical SEO

This document serves as the binding technical contract and behavioral guideline for all AI agents creating, refactoring, or reviewing code in this PHP web project. Every generated artifact must strictly uphold Object-Oriented SOLID principles, modern PHP type safety, Google's Mobile-First Indexing standards, and Core Web Vitals performance criteria.

---

## 1. Modern PHP Standards & Strict Typing

All generated PHP code must adhere to modern PHP standards (PHP 8.2+):

* **Strict Typing Declaration:** Every single PHP file must begin with:
  ```php
  <?php

  declare(strict_types=1);
  ```
* **Coding Standards:** Strict compliance with PER Coding Style 2.0 (PSR-12 successor), PSR-1, and PSR-4 for autoloading.
* **Modern Language Constructs:**
  * Constructor property promotion for clean dependency injection.
  * `readonly` classes and properties for Value Objects and Data Transfer Objects (DTOs).
  * Backed Enums for state machines, status flags, and constant sets.
  * Explicit return types and parameter types (using Union and Intersection types where applicable).
  * `match` expressions preferred over complex `switch-case` blocks.

---

## 2. SOLID Principles Enforcement

### S — Single Responsibility Principle (SRP)
* A class must have only one reason to change and serve a single actor.
* **Controllers:** Only handle HTTP request validation, dispatch to services, and return responses. No business logic.
* **Services:** Encapsulate domain rules. They must not perform raw database queries or direct HTML rendering.
* **Repositories:** Manage data persistence and abstract queries.
* **SEO & Layout Generators:** Keep metadata resolution, Open Graph tags, viewport setups, and Schema.org JSON-LD generation in dedicated builder/transformer classes (e.g., `MetaTagBuilder`, `JsonLdGenerator`).

### O — Open/Closed Principle (OCP)
* Software entities must be open for extension, but closed for modification.
* Never use long `switch` or `if/else` chains to handle different content formats, Schema types, or renderers.
* Use the Strategy Pattern, Polymorphism, or Event Listeners so new content types, responsive layout adapters, or SEO schema decorators can be added by implementing an interface without altering existing code.

### L — Liskov Substitution Principle (LSP)
* Subtypes must be substitutable for their base types without altering program correctness.
* Derived classes must fulfill base contracts without throwing unexpected exceptions (e.g., avoid `throw new NotImplementedException()`).
* Method signatures and return types in inherited classes must remain entirely faithful to contract specifications.

### I — Interface Segregation Principle (ISP)
* Clients should never be forced to depend on methods they do not use.
* Break "fat" interfaces into cohesive, granular contracts:
  ```php
  // BAD: Bloated interface
  interface PageInterface {
      public function getContent(): string;
      public function getJsonLd(): array;
      public function getCanonicalUrl(): string;
      public function getSitemapPriority(): float;
      public function getResponsiveBreakpoints(): array;
  }

  // GOOD: Segregated interfaces
  interface RenderableContentInterface {
      public function getContent(): string;
  }

  interface SchemaOrgAwareInterface {
      public function toSchemaOrgJsonLd(): array;
  }

  interface CanonicalRoutableInterface {
      public function getCanonicalUrl(): string;
  }

  interface ResponsiveViewInterface {
      public function getViewportMeta(): string;
  }
  ```

### D — Dependency Inversion Principle (DIP)
* High-level modules must not depend on low-level modules; both must depend on abstractions.
* Never instantiate concrete service or persistence dependencies using `new` inside domain classes.
* Inject dependencies via class constructors using interface contracts.
* Abstract external HTTP clients, cache adapters, and template engines behind ports/adapters.

---

## 3. Mandatory Mobile-First & Responsive Design Standards

Because Google indexes websites exclusively using **Mobile-First Indexing**, all generated frontend templates, components, and CSS must strictly be responsive and mobile-first by default.

### Viewport Configuration
* Every HTML page must include the standard responsive viewport meta tag in the `<head>`:
  ```html
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  ```
* Never disable user zooming (avoid `user-scalable=no` or `maximum-scale=1.0` unless an explicit web app accessibility exemption applies).

### Mobile-First Layout & Styling Rules
* **Mobile-First CSS:** Base styles must target the smallest screen first; use `@media (min-width: ...)` breakpoints to scale up for tablets and desktops. Never write desktop-first styles overwritten by `max-width` hacks.
* **No Horizontal Scrolling:** Elements must fit comfortably within the viewport width ($100\text{vw}$). Fixed pixel widths that exceed mobile viewport widths are strictly prohibited (`width: 100%` or `max-width: 100%` must be enforced).
* **Responsive Media:** All `<img>`, `<video>`, `<canvas>`, and `<iframe>` elements must be fluid (`max-width: 100%; height: auto; display: block;`). Use `<picture>` and `srcset`/`sizes` attributes for responsive image delivery.
* **Fluid Grids & Flexbox:** Use CSS Grid or Flexbox with relative units (`rem`, `em`, `%`, `clamp()`) rather than static pixel containers.

### Touch Ergonomics & Readability
* **Touch Targets:** All interactive elements (buttons, navigation links, form inputs) must have a minimum tap area of $48 \times 48\text{ px}$ (or $44 \times 44\text{ px}$ with sufficient spacing) to prevent accidental mis-taps.
* **Typography:** Base body text size must be at least $16\text{ px}$ to prevent automatic iOS zooming on focus, with a relative line height of $\ge 1.5$.
* **Form Usability:** Form controls must use mobile-appropriate `inputmode` and `type` attributes (e.g., `type="tel"`, `type="email"`, `inputmode="numeric"`).

### Complete Mobile Parity (Google Mobile-First Indexing Requirement)
* **Equivalent Content:** Never remove critical content, headings, or structured data from the mobile view just to save space. What is visible on desktop must exist and be indexable on mobile.
* **Hidden Content:** Critical SEO text must not be tucked inside unindexed or inaccessible accordions unless fully crawlable in standard DOM.
* **Equivalent Metadata:** Mobile and desktop views must deliver identical title tags, meta descriptions, canonical URLs, and Schema.org markup.

---

## 4. Modern Technical SEO & Google Search Guidelines

### Semantic HTML & Document Architecture
* **Single `<h1>` Rule:** Exactly one unique and descriptive `<h1>` per page reflecting the primary topic.
* **Logical Headings:** Maintain strict hierarchical order (`<h2>` $\rightarrow$ `<h3>` $\rightarrow$ `<h4>`). Never skip levels for styling purposes.
* **Semantic Tagging:** Wrap layouts in semantic HTML5 tags: `<header>`, `<nav>`, `<main>`, `<article>`, `<section>`, `<aside>`, `<footer>`. Avoid excessive `<div>` nesting.
* **Accessible Links:** Always provide descriptive anchor text (avoid generic terms like "click here"). Never use empty `href="#"` attributes.

### Structured Data (Schema.org JSON-LD)
* Dynamic views must generate contextual Schema.org metadata rendered as a single JSON-LD script block in the `<head>` or before the closing `</body>`:
  ```html
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Example Headline",
    "datePublished": "2026-10-01T12:00:00+00:00",
    "dateModified": "2026-10-01T14:30:00+00:00",
    "author": {
      "@type": "Person",
      "name": "Author Name"
    }
  }
  </script>
  ```
* Support schemas according to content type (`Article`, `BreadcrumbList`, `Product`, `FAQPage`, `Organization`, etc.).

### Critical Metadata & Canonicalization
* **Canonical Link:** Every indexable URL must specify an absolute, self-referential canonical URL:
  ```html
  <link rel="canonical" href="https://example.com/clean-slug-url">
  ```
* **Title & Description:**
  * Title tag: Unique, concise (recommended: 50–60 characters).
  * Meta description: Informative summary (recommended: 120–155 characters).
* **Robots Directives:** Explicit `<meta name="robots" content="index, follow">` (or `noindex, nofollow` on restricted/admin pages).
* **Social Graph:** Complete Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`) and Twitter Card tags.
* **Clean URLs:** Lowercase, hyphen-separated, parameter-free URL routes.

### Core Web Vitals (CWV) & Performance Standards
* **Largest Contentful Paint (LCP):**
  * Above-the-fold hero images must include `fetchpriority="high"`.
  * Do not lazy-load the primary LCP image.
* **Cumulative Layout Shift (CLS):**
  * Every image and video element must specify explicit `width` and `height` attributes or CSS `aspect-ratio` to reserve space before rendering.
  * Avoid layout-shifting DOM injections after page load.
* **Interaction to Next Paint (INP):**
  * Generate optimized server-rendered HTML from PHP to avoid client-side rendering bottlenecks.
  * Defer non-critical JavaScript (`<script defer src="...">`).

---

## 5. Forbidden Anti-Patterns

1. **Desktop-Only or Fixed Layouts:** Using fixed pixel widths (e.g., `width: 1200px`) that cause horizontal scrollbars on mobile devices is strictly forbidden.
2. **Missing or Broken Viewport:** Rendering pages without `<meta name="viewport" ...>` or setting `user-scalable=no`.
3. **Desktop/Mobile Content Disparity:** Hiding substantive paragraphs, canonical links, or schema data on mobile viewports.
4. **Global State Access:** Using `global $var;` or accessing `$_GET`, `$_POST`, `$_SERVER` superglobals directly within business or presentation logic. Use PSR-7 Request interfaces or framework request abstractions.
5. **Error Suppression:** The `@` error-control operator is strictly forbidden.
6. **Spaghetti Code:** Mixing raw database queries or complex PHP business logic directly within view templates. Views must only consume prepared ViewModels or DTOs.
7. **Hardcoded Metadata:** Never hardcode SEO title tags, canonical URLs, or JSON-LD properties directly into templates.
8. **Untyped Declarations:** Leaving parameters or return types untyped or relying on unvalidated `mixed`.

---

## 6. Verification Checklist for AI Agents

Before submitting any code changes, verify:
* [ ] Is `declare(strict_types=1);` present at the top of the PHP file?
* [ ] Are all dependencies injected via the constructor as interfaces?
* [ ] Does the class strictly fulfill a single domain responsibility?
* [ ] Is the responsive viewport meta tag present in the `<head>`?
* [ ] Is the design fully mobile-first with zero horizontal overflow on small screens?
* [ ] Are interactive touch targets appropriately sized ($\ge 44 \times 44\text{ px}$)?
* [ ] Does the HTML output contain exactly one semantic `<h1>`?
* [ ] Do all image tags have valid `alt`, `width`, and `height` (or `aspect-ratio`) attributes?
* [ ] Are the canonical link tag and Schema.org JSON-LD identical and present on both mobile and desktop?
* [ ] Are there zero direct references to PHP superglobals?