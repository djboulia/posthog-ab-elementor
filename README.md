# PostHog A/B Testing for Elementor

Run PostHog feature-flag–driven A/B tests on Elementor widgets, sections, and full pages — no extra third-party service required.

---

## Requirements

- WordPress 6.0+
- Elementor (free) 3.10+
- A PostHog account (Cloud or self-hosted) with at least one Feature Flag created

---

## Installation

1. Copy the `posthog-ab-elementor/` folder into `wp-content/plugins/`.
2. Activate **PostHog A/B Testing for Elementor** in *Plugins → Installed Plugins*.
3. Go to **Settings → PostHog A/B** and enter your PostHog **Project API Key** and **Host URL**.
4. Save. PostHog is now initialised on every front-end page.

---

## Configuration

### Settings → PostHog A/B

| Field | Description |
|-------|-------------|
| **Project API Key** | Your PostHog public key (starts with `phc_`). Found in PostHog → Project Settings → Project API Key. |
| **PostHog Host** | `https://app.posthog.com` (US Cloud), `https://eu.posthog.com` (EU Cloud), or your self-hosted URL. |
| **Registered Experiments** | Optional reference list. Doesn't affect live behaviour — purely for documentation. |

---

## Widget A/B Tests (section/widget-level)

Use this when you want to swap out a section or widget on a page.

1. In the Elementor editor, search for **A/B Section** in the widget panel (under the *PostHog A/B* category).
2. Drag it onto your canvas.
3. In the **Experiment** tab:
   - **Feature Flag Key** — the exact key from PostHog (e.g. `homepage-hero-test`).
   - **Variant Key** — the value PostHog returns for the B group (default: `test`). Must match the variant key in your PostHog flag definition.
   - **Preview in Editor** — choose to preview Control, Variant, or both stacked.
4. Build your **Control (A)** content in the *Control Content* tab.
5. Build your **Variant (B)** content in the *Variant Content* tab.
6. Publish/update the page.

### How it works

Both variants are rendered server-side in the HTML. On page load, `phab-frontend.js` hides all A/B containers immediately, then waits for the PostHog SDK to resolve feature flags. Once flags are ready it shows only the matching variant and hides the other. A 3-second safety timeout falls back to the control variant if PostHog fails to load.

---

## Page-Level A/B Tests

Use this when you want to redirect visitors to an entirely different page layout.

1. Open the page you want to be the **Control (A)** in the WP editor.
2. In the sidebar meta box **PostHog A/B Page Test**:
   - **Feature Flag Key** — e.g. `checkout-page-test`
   - **Variant Key** — e.g. `test`
   - **Variant Page URL** — the full URL of the B page (must already exist).
3. Update/publish.

### How it works

A small inline script is injected into `<head>`. After PostHog resolves feature flags, if the visitor is in the variant group, `window.location.replace()` redirects them to the Variant Page URL before the page is rendered. A 2.5-second timeout prevents indefinite blank-page flash if PostHog stalls.

> **Tip:** Build your variant page as a separate Elementor page. You don't need to add the A/B meta box to the variant page itself.

---

## PostHog Setup

1. In PostHog, go to **Feature Flags → New Feature Flag**.
2. Set the **Key** to match what you enter in WordPress (e.g. `homepage-hero-test`).
3. Under **Variants**, add a variant named `test` (or whatever you used as Variant Key). Set rollout percentages (e.g. 50% control / 50% test).
4. Enable the flag.

PostHog automatically assigns visitors to groups and caches the assignment. The WordPress plugin reads the resolved flag value; no extra code is needed.

---

## File Structure

```
posthog-ab-elementor/
├── posthog-ab-elementor.php        # Main plugin bootstrap & autoloader
├── includes/
│   ├── PostHog.php                 # Snippet injection & JS enqueue
│   ├── PageVariant.php             # Page-level redirect script injection
│   └── Admin/
│       ├── Settings.php            # Settings → PostHog A/B admin page
│       └── PageMeta.php            # Per-page meta box
├── widgets/
│   └── class-ab-section-widget.php # Elementor A/B Section widget
└── assets/
    └── js/
        └── phab-frontend.js        # Client-side variant reveal logic
```

---

## Frequently Asked Questions

**Will there be a flash of the wrong variant?**
For widget-level tests: all containers are hidden immediately on `DOMContentLoaded` before PostHog resolves, so there is no visible flash in practice. For page-level tests: the redirect fires in `<head>` before body content renders.

**What happens if PostHog is slow or blocked?**
Both mechanisms have safety timeouts (3 s for widget tests, 2.5 s for page tests) that fall back to showing the control variant.

**Can I track conversions?**
Yes — call `posthog.capture('button_clicked')` (or any event name) from any custom JS on your page. PostHog automatically associates it with the feature flag experiment.

**Does this work with Elementor Pro?**
Yes. The widget is compatible with both free Elementor and Elementor Pro.
