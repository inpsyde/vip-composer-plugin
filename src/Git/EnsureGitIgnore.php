<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Git;

final class EnsureGitIgnore
{
    /**
     * @param string $dir
     * @return bool
     */
    public function ensure(string $dir): bool
    {
        if (file_exists("{$dir}/.gitignore")) {
            return true;
        }

        return file_put_contents("{$dir}/.gitignore", $this->gitIgnoreContent()) !== false;
    }

    /**
     * @return string
     */
    private function gitIgnoreContent(): string
    {
        return <<<'GITIGNORE'
# Development files
/themes/twentytwentyone/.stylelintrc.json
/themes/twentytwentyone/.stylelintrc-css.json
/themes/twentytwentyone/.stylelintignore
/themes/twentytwentyone/assets/sass
/themes/twentytwentyone/postcss.config.js
package.json
package-lock.json

# Built-in; ship with vip-go-mu-plugins
/plugins/akismet/
/plugins/advanced-post-cache/
/plugins/cron-control/
/plugins/debug-bar/
/plugins/debug-bar-cron/
/plugins/gutenberg-ramp/
/plugins/jetpack/
/plugins/jetpack-force-2fa/
/plugins/lightweight-term-count-update/
/plugins/query-monitor/
/plugins/rewrite-rules-inspector/
/plugins/two-factor/
/plugins/vaultpress/

# Uploads directory
/uploads/

# Leftover core/plugin upgrade files
/upgrade/

# mu-plugins; these are managed at the platform-level
/mu-plugins/

# drop-ins; these are managed at the platform-level
/object-cache.php
/db.php

# Ignore temporary OS files
.DS_Store
.DS_Store?
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db
GITIGNORE;
    }
}
