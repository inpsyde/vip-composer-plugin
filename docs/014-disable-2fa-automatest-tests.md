---
title: Disable 2FA for Automated Tests
nav_order: 14
---

# Disable 2FA for Automated Tests

[VIP enforces 2FA on administrators](Two-factor authentication).

Via filters it is possible to change the minimum capability required to enforce 2FA, which usually ends up enlarging the affected user base. 

During automated tests, it is extremely hard to deal with 2FA, considering it is designed for human interaction.

That is why VIP disables 2FA when the constant `WP_RUN_CORE_TESTS` is defined and *truthy*. However, it is not possible to set a constant acting as an end-user in end-to-end/browser tests.

To overcome this issue, this plugins supports setting that constant when an HTTP request contains a special secret key either in:

- `$_REQUEST['inpsyde_autotest_key']`
- The `X_INPSYDE_AUTOTEST_KEY` HTTP header

The secret must be previously set in a `INPSYDE_AUTOTEST_KEY` environment variable or PHP constant.

After the first "key-enriched" request is executed, the plugin sets a session cookie, so the following request does not need to also contain the secret.

That means that, usually, the automated tests workflow for the admin will be:

1. The browser test visits the WP login URL adding the secret to the URL or HTTP header
2. It fills the username and password fields and submits the form
3. Continues executing tests not caring of setting secret anymore for the current browser session



## Not for Production

Because this feature reduces the overall site security, it is **not available for production**. As long as [`wp_get_environment_type()`](https://developer.wordpress.org/reference/functions/wp_get_environment_type/) returns "*production*" the feature is not available.
