<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Shared publisher and replacement verification for 108design plugins.
 *
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license See the containing product LICENSE.md.
 */
namespace design108\update_guard\v1;
defined('MOODLE_INTERNAL') || die();
final class guard {
    private const DOMAIN = "108design-plugin-release-v1\n";
    private const MANIFEST = 'release-manifest.json';
    private const SIGNATURE = 'release-signature.txt';
    public const IMPLEMENTATION = 3;
    private static bool $entered = false;
    private static array $owners = [];
    private static string $context = '';
    private static string $languageowner = '';
    private static bool $nativewriter = false;
    public static function verify(string $zipfile, array $policy, string $component, ?int $expected = null): array {
        self::validate_policy($policy, $component);
        $zip = new \ZipArchive();
        if (!is_file($zipfile) || filesize($zipfile) > 16777216 || $zip->open($zipfile) !== true) {
            throw new \RuntimeException('invalid-archive');
        }
        try {
            if ($zip->numFiles > 2048) { throw new \RuntimeException('archive-limit'); }
            // Archive wrappers (including GitHub's) are not plugin identities.
            // The signed manifest and literal version.php establish the component.
            $root = null;
            $entries = []; $case = []; $paths = []; $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i); $name = $st['name'];
                $separator = strpos($name, '/');
                if ($separator === false || $separator === 0) { throw new \RuntimeException('unsafe-path'); }
                $root = $root ?? substr($name, 0, $separator + 1);
                if (strlen($name) > 240 || !str_starts_with($name, $root)
                        || str_contains($name, '\\') || str_contains($name, ':') || str_contains($name, '//')
                        || preg_match('~(^|/)(\.{1,2}|[^/]*[. ])(/|$)~', $name)
                        || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                    throw new \RuntimeException('unsafe-path');
                }
                $relative = substr($name, strlen($root));
                foreach (explode('/', rtrim($name, '/')) as $segment) {
                    if (preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/iD', $segment)) {
                        throw new \RuntimeException('unsafe-path');
                    }
                }
                if ($relative === '' && str_ends_with($name, '/')) { continue; }
                $fold = strtolower(rtrim($name, '/'));
                if (isset($case[$fold])) { throw new \RuntimeException('duplicate-path'); }
                $case[$fold] = true;
                $paths[strtolower(rtrim($relative, '/'))] = str_ends_with($name, '/');
                $opsys = 0; $attrs = 0; $zip->getExternalAttributesIndex($i, $opsys, $attrs);
                if ($opsys === 3 && (($attrs >> 16) & 0170000) === 0120000) {
                    throw new \RuntimeException('symlink');
                }
                if (str_ends_with($name, '/')) { continue; }
                $total += $st['size'];
                if ($st['size'] > 4194304 || $total > 33554432) { throw new \RuntimeException('archive-limit'); }
                $entries[$relative] = $i;
            }
            foreach (array_keys($paths) as $path) {
                $parts = explode('/', $path); array_pop($parts); $prefix = '';
                foreach ($parts as $part) {
                    $prefix .= $part;
                    if (isset($paths[$prefix]) && $paths[$prefix] === false) {
                        throw new \RuntimeException('file-directory-conflict');
                    }
                    $prefix .= '/';
                }
            }
            if (!isset($entries[self::MANIFEST], $entries[self::SIGNATURE])) { throw new \RuntimeException('missing-signature'); }
            $raw = $zip->getFromIndex($entries[self::MANIFEST]);
            $signature = base64_decode(trim($zip->getFromIndex($entries[self::SIGNATURE])), true);
            if (!is_string($raw) || strlen($raw) > 524288 || !is_string($signature) || strlen($signature) !== 64) {
                throw new \RuntimeException('invalid-signature');
            }
            $m = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $encodedkey = $policy['keys'][$m['keyid'] ?? ''] ?? '';
            $key = base64_decode($encodedkey, true);
            if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                    || !sodium_crypto_sign_verify_detached($signature, self::DOMAIN . $raw, $key)) {
                throw new \RuntimeException('invalid-signature');
            }
            if (($m['schema'] ?? null) !== 1 || ($m['component'] ?? '') !== $component
                    || !is_int($m['version'] ?? null) || ($expected !== null && $m['version'] !== $expected)
                    || ($m['edition'] ?? '') !== $policy['edition']
                    || ($m['upgrade_family'] ?? 'default') !== ($policy['upgrade_family'] ?? 'default')
                    || !in_array($m['profile'] ?? '', $policy['profiles'], true)
                    || !is_array($m['files'] ?? null)) { throw new \RuntimeException('release-policy'); }
            unset($entries[self::MANIFEST], $entries[self::SIGNATURE]);
            $listed = array_keys($m['files']); $actual = array_keys($entries); sort($listed); sort($actual);
            if ($listed !== $actual) { throw new \RuntimeException('file-set-mismatch'); }
            foreach ($m['files'] as $file => $sha) {
                if (!is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha)
                        || !is_string($body = $zip->getFromIndex($entries[$file]))
                        || !hash_equals($sha, hash('sha256', $body))) {
                    throw new \RuntimeException('file-hash-mismatch');
                }
            }
            foreach (['version.php', 'update-guard.php', 'release-guard-policy.json',
                    'classes/local/update_guard.php', 'db/hooks.php'] as $required) {
                if (!isset($entries[$required])) { throw new \RuntimeException('guard-continuity-missing'); }
            }
            $actual = self::metadata($zip->getFromIndex($entries['version.php']));
            $deps = $m['dependencies'] ?? null;
            if (!is_array($deps)) { throw new \RuntimeException('version-mismatch'); }
            ksort($deps);
            if ($actual['component'] !== $component || $actual['version'] !== $m['version']
                    || !is_int($m['requires'] ?? null) || $actual['requires'] !== $m['requires']
                    || $actual['dependencies'] !== $deps) { throw new \RuntimeException('version-mismatch'); }
            $nextpolicy = json_decode($zip->getFromIndex($entries['release-guard-policy.json']), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($nextpolicy)) { throw new \RuntimeException('incoming-policy-invalid'); }
            self::validate_policy($nextpolicy, $component);
            if ($nextpolicy['edition'] !== $m['edition']
                    || ($nextpolicy['upgrade_family'] ?? 'default') !== ($m['upgrade_family'] ?? 'default')) { throw new \RuntimeException('incoming-policy-invalid'); }
            return $m;
        } finally { $zip->close(); }
    }


    /** Literal metadata only: ignores comments/strings and never evaluates candidate PHP. */
    public static function metadata(string $source): array {
        if (strlen($source) > 131072) { throw new \RuntimeException('version-source-limit'); }
        $tokens = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)) { continue; }
                $tokens[] = [$token[0], $token[1]];
            } else { $tokens[] = [null, $token]; }
        }
        $out = [];
        for ($i = 0, $count = count($tokens); $i + 3 < $count; $i++) {
            if ($tokens[$i] !== [T_VARIABLE, '$plugin'] || $tokens[$i + 1][1] !== '->'
                    || $tokens[$i + 2][0] !== T_STRING) { continue; }
            $field = $tokens[$i + 2][1];
            if (!in_array($field, ['component', 'version', 'requires', 'dependencies'], true)) { continue; }
            if ($tokens[$i + 3][1] !== '=') { throw new \RuntimeException('dynamic-version-field'); }
            if (array_key_exists($field, $out)) { throw new \RuntimeException('duplicate-version-field'); }
            $cursor = $i + 4;
            $value = self::literal($tokens, $cursor);
            if (($tokens[$cursor][1] ?? '') !== ';') { throw new \RuntimeException('dynamic-version-field'); }
            $out[$field] = $value; $i = $cursor;
        }
        if (!is_string($out['component'] ?? null)
                || !preg_match('/^[a-z][a-z0-9]*_[a-z][a-z0-9_]*$/D', $out['component'])
                || !is_int($out['version'] ?? null) || $out['version'] <= 0
                || !is_int($out['requires'] ?? null) || $out['requires'] <= 0) {
            throw new \RuntimeException('version-metadata-invalid');
        }
        $out['dependencies'] ??= [];
        if (!is_array($out['dependencies'])) { throw new \RuntimeException('version-dependencies-invalid'); }
        foreach ($out['dependencies'] as $component => $version) {
            if (!is_string($component) || !preg_match('/^[a-z][a-z0-9]*_[a-z][a-z0-9_]*$/D', $component)
                    || !is_int($version) || $version < 0) { throw new \RuntimeException('version-dependencies-invalid'); }
        }
        ksort($out['dependencies']);
        return $out;
    }
    private static function literal(array $tokens, int &$cursor): mixed {
        [$kind, $value] = $tokens[$cursor] ?? [null, ''];
        if ($kind === T_LNUMBER && preg_match('/^(?:0|[1-9][0-9]*(?:_[0-9]+)*)$/D', $value)) {
            $cursor++; return (int)str_replace('_', '', $value);
        }
        if ($kind === T_CONSTANT_ENCAPSED_STRING && strlen($value) >= 2) {
            $body = substr($value, 1, -1);
            if (str_contains($body, '\\') || str_contains($body, '$')) { throw new \RuntimeException('dynamic-version-field'); }
            $cursor++; return $body;
        }
        if ($kind === T_STRING && $value === 'ANY_VERSION') { $cursor++; return 0; }
        if ($value === '[' || $kind === T_ARRAY) {
            $end = $value === '[' ? ']' : ')'; $cursor++;
            if ($kind === T_ARRAY) {
                if (($tokens[$cursor][1] ?? '') !== '(') { throw new \RuntimeException('dynamic-version-field'); }
                $cursor++;
            }
            $result = [];
            while (($tokens[$cursor][1] ?? '') !== $end) {
                $key = self::literal($tokens, $cursor);
                if (!is_string($key) || ($tokens[$cursor][1] ?? '') !== '=>') {
                    throw new \RuntimeException('version-dependencies-invalid');
                }
                $cursor++; $item = self::literal($tokens, $cursor);
                if (array_key_exists($key, $result)) { throw new \RuntimeException('duplicate-dependency'); }
                $result[$key] = $item;
                if (($tokens[$cursor][1] ?? '') === ',') { $cursor++; }
                else if (($tokens[$cursor][1] ?? '') !== $end) { throw new \RuntimeException('dynamic-version-field'); }
            }
            $cursor++; return $result;
        }
        throw new \RuntimeException('dynamic-version-field');
    }
    /** A batch may satisfy its own dependencies; no protected file may be replaced first. */
    public static function assert_requirements(array $metadata, array $installed, int $coreversion): void {
        $available = $installed;
        foreach ($metadata as $component => $release) { $available[$component] = $release['version']; }
        foreach ($metadata as $component => $release) {
            if ($release['requires'] > $coreversion) { throw new \RuntimeException('moodle-requirement:' . $component); }
            foreach ($release['dependencies'] as $dependency => $required) {
                if (!isset($available[$dependency]) || (int)$available[$dependency] < $required) {
                    throw new \RuntimeException('dependency-requirement:' . $component . ':' . $dependency);
                }
            }
        }
    }
    public static function archive_metadata(string $path, string $component): array {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { throw new \RuntimeException('invalid-archive'); }
        try {
            $entry = substr($component, strpos($component, '_') + 1) . '/version.php';
            $stat = $zip->statName($entry);
            if (!$stat || $stat['size'] > 131072) { throw new \RuntimeException('version-metadata-invalid'); }
            $body = $zip->getFromName($entry);
            if (!is_string($body)) { throw new \RuntimeException('version-metadata-invalid'); }
            $metadata = self::metadata($body);
            if ($metadata['component'] !== $component) { throw new \RuntimeException('version-mismatch'); }
            return $metadata;
        } finally { $zip->close(); }
    }

    /** Validate only installed trusted policy; incoming keys never establish trust. */
    private static function validate_policy(array $policy, string $component): void {
        if (($policy['schema'] ?? null) !== 1 || ($policy['component'] ?? '') !== $component
                || ($policy['engine'] ?? 1) !== 1
                || !is_int($policy['minimum_guard_version'] ?? 1)
                || ($policy['minimum_guard_version'] ?? 1) > self::IMPLEMENTATION
                || !is_string($policy['edition'] ?? null)
                || !is_string($policy['upgrade_family'] ?? 'default')
                || !preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $policy['upgrade_family'] ?? 'default')
                || !preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $policy['edition'])
                || !is_array($policy['profiles'] ?? null) || !$policy['profiles']
                || !is_array($policy['channels'] ?? null) || !$policy['channels']
                || !is_array($policy['keys'] ?? null) || !$policy['keys']) {
            throw new \RuntimeException('installed-policy-invalid');
        }
        foreach ($policy['keys'] as $id => $encoded) {
            if (!is_string($id) || !is_string($encoded)
                    || strlen(base64_decode($encoded, true) ?: '') !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                throw new \RuntimeException('installed-key-invalid');
            }
        }
        foreach ($policy['profiles'] as $profile) {
            if (!is_string($profile) || !preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $profile)) {
                throw new \RuntimeException('installed-profile-invalid');
            }
        }
        foreach ($policy['channels'] as $channel) {
            if (!in_array($channel, ['zip', 'marketplace', 'bi'], true)) {
                throw new \RuntimeException('installed-channel-invalid');
            }
        }
    }
    /** Presence is independent of policy readability so missing metadata fails closed. */
    public static function is_protected(string $rootdir, string $component): bool {
        clearstatcache(true);
        return isset(self::$owners[$component]) || file_exists($rootdir . '/update-guard.php')
            || file_exists($rootdir . '/release-guard-policy.json');
    }
    public static function load_policy(string $rootdir, string $component): array {
        $path = $rootdir . '/release-guard-policy.json';
        clearstatcache(true, $path);
        if (!is_file($path) || !is_readable($path) || filesize($path) > 65536) {
            throw new \RuntimeException('installed-policy-missing');
        }
        $policy = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($policy)) { throw new \RuntimeException('installed-policy-invalid'); }
        self::validate_policy($policy, $component);
        return $policy;
    }
    /** Called by native and BI adapters while holding the same installation lock. */
    public static function verify_replacement(string $zipfile, string $installedroot, string $component,
            ?int $expected = null, string $channel = 'bi'): array {
        $policy = self::load_policy($installedroot, $component);
        if (!in_array($channel, $policy['channels'], true)) { throw new \RuntimeException('channel-rejected'); }
        return self::verify($zipfile, $policy, $component, $expected);
    }
    public static function acquire_lock(int $timeout = 5): object {
        $lock = \core\lock\lock_config::get_lock_factory('design108_update_guard')
            ->get_lock('native-installation', $timeout);
        if (!$lock) { throw new \RuntimeException('installation-busy'); }
        return $lock;
    }
    /**
     * Use Core's own writer, then invalidate opcode entries before the next web bootstrap.
     * Keeping old ZIP timestamps must never leave old trusted PHP executing after replacement.
     */
    public static function install_plugins(array $installable, bool $confirmed, string $heading = '',
            mixed $continue = null, mixed $return = null): never {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/upgradelib.php');
        if (!$confirmed) {
            upgrade_install_plugins($installable, false, $heading, $continue, $return);
            exit;
        }
        $return ??= $PAGE->url;
        if (!empty($CFG->disableupdateautodeploy) || !$installable) { redirect($return); }
        $previousphp = self::installed_php_files($installable);
        try {
            $installed = \core_plugin_manager::instance()->install_plugins($installable, true, true);
        } catch (\Throwable $error) {
            // Core may already have replaced part of the batch. Preserve its original failure.
            try { self::refresh_installed($installable, $previousphp); } catch (\Throwable $ignored) { }
            throw $error;
        }
        self::refresh_installed($installable, $previousphp);
        if (!$installed) { throw new \moodle_exception('install_plugins_failed', 'core_plugin', $return); }
        $mustgoto = new \moodle_url(self::admin_route('/admin/index.php'), ['cache' => 0, 'confirmplugincheck' => 0]);
        if ($mustgoto->compare($PAGE->url, URL_MATCH_PARAMS)) { redirect($PAGE->url); }
        redirect($mustgoto);
    }
    /** Include pre-write paths so deleted PHP cannot survive in the opcode cache. */
    private static function installed_php_files(array $installable): array {
        $pm = \core_plugin_manager::instance();
        $paths = [];
        foreach ($installable as $target) {
            [$type, $name] = \core_component::normalize_component($target->component);
            $root = $pm->get_plugintype_root($type) . '/' . $name;
            clearstatcache(true);
            if (!is_dir($root)) { continue; }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isLink() && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $paths[realpath($file->getPathname()) ?: $file->getPathname()] = true;
                }
            }
        }
        return array_keys($paths);
    }
    /** Current-request classes remain loaded; later requests must read installed bytes. */
    public static function refresh_installed(array $installable, array $previousphp = []): void {
        clearstatcache(true);
        $paths = array_unique(array_merge($previousphp, self::installed_php_files($installable)));
        if (function_exists('opcache_invalidate')) {
            foreach ($paths as $path) { @opcache_invalidate($path, true); }
        }
        clearstatcache(true);
        \core_plugin_manager::reset_caches();
    }

    /** Use server-resolved executed script, including legitimate PATH_INFO forms. */
    public static function request_route(string $dirroot, array $server, string $admin = 'admin'): ?string {
        $executed = realpath($server['SCRIPT_FILENAME'] ?? '');
        if (!$executed) { return null; }
        foreach (['/admin/plugins.php', '/admin/index.php', '/admin/tool/installaddon/index.php'] as $route) {
            $target = realpath($dirroot . '/' . trim($admin, '/') . substr($route, 6));
            if ($target && strcasecmp(str_replace('\\', '/', $executed), str_replace('\\', '/', $target)) === 0) {
                return $route;
            }
        }
        return null;
    }
    private static function admin_route(string $logical): string {
        global $CFG;
        return '/' . trim($CFG->admin ?? 'admin', '/') . substr($logical, 6);
    }
    private static function root_for(string $component): ?string {
        $root = \core_component::get_component_directory($component);
        return is_string($root) && $root !== '' ? $root : null;
    }
    private static function protected_target(string $component): bool {
        $root = self::root_for($component);
        return $root !== null && self::is_protected($root, $component);
    }
    /** Register the calling component even if its required metadata was removed. */
    public static function enter(string $owner = '', string $ownerroot = ''): void {
        global $CFG, $PAGE, $SESSION, $USER, $DB;
        if ($owner !== '' && $ownerroot !== '') { self::$owners[$owner] = $ownerroot; self::$languageowner = $owner; }
        if (self::$entered || CLI_SCRIPT) { return; }
        $route = self::request_route($CFG->dirroot, $_SERVER, $CFG->admin ?? 'admin');
        if ($route === null) { return; }
        $families = [];
        foreach (['installzipcomponent', 'installremote', 'installupdate', 'installupdatex',
                'installdep', 'installdepx', 'abortupgrade', 'abortupgradex'] as $name) {
            if (!empty($_REQUEST[$name])) { $families[] = $name; }
        }
        if (!$families) { return; }
        self::$entered = true;
        try {
            if (count($families) !== 1) { throw new \RuntimeException('ambiguous-operation'); }
            $operation = $families[0];
            $upgradeparams = [];
            if ($route === '/admin/index.php') {
                require_once($CFG->libdir . '/upgradelib.php');
                $upgradeparams['cache'] = optional_param('cache', 0, PARAM_BOOL);
                foreach (['confirmupgrade', 'confirmrelease', 'confirmplugincheck', 'showallplugins'] as $parameter) {
                    if (isset($_REQUEST[$parameter])) { $upgradeparams[$parameter] = optional_param($parameter, false, PARAM_BOOL); }
                }
                $upgradekeyhash = optional_param('upgradekeyhash', null, PARAM_ALPHANUM);
                if ($upgradekeyhash !== null) { $upgradeparams['upgradekeyhash'] = $upgradekeyhash; }
                $PAGE->set_context(\context_system::instance());
                $PAGE->set_url(new \moodle_url(self::admin_route($route), $upgradeparams));
                $PAGE->set_pagelayout('maintenance');
                check_upgrade_key($upgradekeyhash);
            }

            $allowed = $route === '/admin/tool/installaddon/index.php'
                ? ['installzipcomponent', 'installremote']
                : ($route === '/admin/plugins.php' ? ['installupdate', 'installupdatex']
                    : ['installupdate', 'installupdatex', 'installdep', 'installdepx', 'abortupgrade', 'abortupgradex']);
            if (!in_array($operation, $allowed, true)) { throw new \RuntimeException('operation-route-mismatch'); }
            if (!str_ends_with($operation, 'x')) {
                $requested = required_param($operation, PARAM_COMPONENT);
                self::$context = $requested;
                if (!self::protected_target($requested)) { return; }
            }
            // Pending Core links may lack sesskey at preview. Confirmations always require it.
            $confirmkey = match ($operation) {
                'installzipcomponent' => 'installzipconfirm', 'installremote' => 'installremoteconfirm',
                'installdep', 'installdepx' => 'confirminstalldep',
                'abortupgrade', 'abortupgradex' => 'confirmabortupgrade', default => 'confirminstallupdate',
            };
            $confirmed = optional_param($confirmkey, false, PARAM_BOOL);
            if (!empty($CFG->disableupdateautodeploy)) { return; }
            if ($route !== '/admin/index.php') {
                require_login(); require_capability('moodle/site:config', \context_system::instance()); require_sesskey();
            } else if (!isloggedin() && !moodle_needs_upgrading()) {
                // Preserve Core's ordinary login path without pre-auth remote discovery on a current site.
                return;
            }
            $lock = self::acquire_lock();
            register_shutdown_function(static function() use ($lock) { $lock->release(); });
            // Bootstrap may have already cached plugin info; clear it only after acquiring the lock.
            \core_plugin_manager::reset_caches();
            $pm = \core_plugin_manager::instance();
            $candidates = []; $params = ['sesskey' => sesskey()] + $upgradeparams;
            $channel = 'marketplace'; $rollback = false;
            if ($operation === 'installzipcomponent') {
                $component = required_param('installzipcomponent', PARAM_COMPONENT);
                $storage = required_param('installzipstorage', PARAM_FILE);
                $candidates[] = (object)['component' => $component,
                    'zipfilepath' => $CFG->tempdir . '/tool_installaddon/' . $storage . '/plugin.zip'];
                $params += ['installzipcomponent' => $component, 'installzipstorage' => $storage];
                $channel = 'zip';
            } else if ($operation === 'installupdatex') {
                $candidates = $pm->filter_installable($pm->available_updates());
                $params[$operation] = 1;
            } else if (in_array($operation, ['installdep', 'installdepx'], true)) {
                $candidates = $pm->filter_installable($pm->missing_dependencies(true));
                if ($operation === 'installdep') {
                    $component = required_param($operation, PARAM_COMPONENT);
                    $candidates = isset($candidates[$component]) ? [$candidates[$component]] : [];
                    $params[$operation] = $component;
                } else { $params[$operation] = 1; }
            } else if (in_array($operation, ['abortupgrade', 'abortupgradex'], true)) {
                $candidates = $pm->list_restorable_archives();
                if ($operation === 'abortupgrade') {
                    $component = required_param($operation, PARAM_COMPONENT);
                    $candidates = isset($candidates[$component]) ? [$candidates[$component]] : [];
                    $params[$operation] = $component;
                } else { $params[$operation] = 1; }
                $channel = 'zip'; $rollback = true;
            } else {
                $component = required_param($operation, PARAM_COMPONENT);
                $version = required_param($operation . 'version', PARAM_INT);
                // An explicitly forbidden channel must not depend on a remote response being available.
                if (self::protected_target($component)) {
                    $policy = self::load_policy(self::root_for($component), $component);
                    if (!in_array('marketplace', $policy['channels'], true)) { throw new \RuntimeException('channel-rejected'); }
                }
                if (!$pm->is_remote_plugin_installable($component, $version)) {
                    throw new \RuntimeException('not-installable');
                }
                $candidates[] = $pm->get_remote_plugin_info($component, $version, true);
                $params += [$operation => $component, $operation . 'version' => $version];
            }
            $policies = [];
            foreach ($candidates as $candidate) {
                self::$context = $candidate->component;
                if (self::protected_target($candidate->component)) {
                    $policies[$candidate->component] = self::load_policy(self::root_for($candidate->component), $candidate->component);
                }
            }
            if (!$policies) { return; }
            require_login(); require_capability('moodle/site:config', \context_system::instance());
            if ($route !== '/admin/index.php' || $confirmed) { require_sesskey(); }
            $local = []; $binding = []; $states = []; $metadata = []; $planned = []; $temp = make_request_directory();
            foreach ($candidates as $candidate) {
                $component = $candidate->component; self::$context = $component;
                $source = isset($candidate->zipfilepath) ? $candidate->zipfilepath
                    : $pm->get_remote_plugin_zip($candidate->version->downloadurl, $candidate->version->downloadmd5);
                $frozen = $temp . '/' . $component . '.zip';
                if (!$source || !copy($source, $frozen)) { throw new \RuntimeException('package-unavailable'); }
                if (isset($policies[$component])) {
                    $policy = $policies[$component];
                    if (!in_array($channel, $policy['channels'], true)) { throw new \RuntimeException('channel-rejected'); }
                    $expected = isset($candidate->version) ? (int)$candidate->version->version : null;
                    $m = self::verify($frozen, $policy, $component, $expected);
                    $metadata[$component] = $m;
                    $dbversion = (int)$DB->get_field('config_plugins', 'value', ['plugin' => $component, 'name' => 'version']);
                    $root = self::root_for($component);
                    $diskversion = self::metadata(file_get_contents($root . '/version.php'))['version'];
                    if ($rollback) {
                        if ($m['version'] !== $dbversion || $diskversion <= $dbversion) {
                            throw new \RuntimeException('rollback-state-invalid');
                        }
                    } else if ($m['version'] <= max($dbversion, $diskversion)) {
                        throw new \RuntimeException('not-newer');
                    }
                    $states[$component] = ['policy' => hash_file('sha256', $root . '/release-guard-policy.json'),
                        'dbversion' => $dbversion, 'diskversion' => $diskversion];
                }
                if (!isset($policies[$component])) {
                    // Keep native third-party metadata semantics; no new restricted parser for neighbours.
                    $planned[$component] = isset($candidate->version)
                        ? (int)$candidate->version->version
                        : (int)$DB->get_field('config_plugins', 'value', ['plugin' => $component, 'name' => 'version']);
                }
                $binding[$component] = hash_file('sha256', $frozen);
                $local[] = (object)['component' => $component, 'zipfilepath' => $frozen];
            }
            $installed = [];
            foreach ($pm->get_plugins() as $type => $plugins) {
                foreach ($plugins as $name => $plugin) {
                    if ($plugin->versiondisk !== null) { $installed[$type . '_' . $name] = (int)$plugin->versiondisk; }
                }
            }
            $installed = array_replace($installed, $planned);
            self::assert_requirements($metadata, $installed, (int)$CFG->version);
            ksort($binding); ksort($states);
            $digest = hash('sha256', json_encode(['site' => $CFG->wwwroot, 'user' => (int)$USER->id,
                'route' => self::admin_route($route), 'operation' => $operation, 'channel' => $channel,
                'packages' => $binding, 'installed' => $states]));
            if ($confirmed) {
                $token = optional_param('guardapproval', '', PARAM_ALPHANUM);
                $approval = $SESSION->design108guardv1[$token] ?? null;
                if (!$approval || $approval['expires'] < time() || !hash_equals($approval['digest'], $digest)) {
                    throw new \RuntimeException('approval-package-changed');
                }
                unset($SESSION->design108guardv1[$token]);
            } else {
                $existing = (array)($SESSION->design108guardv1 ?? []);
                $existing = array_filter($existing, static fn($value) => $value['expires'] >= time());
                $existing = array_slice($existing, -9, null, true);
                $token = bin2hex(random_bytes(16));
                $existing[$token] = ['digest' => $digest, 'expires' => time() + 1800];
                $SESSION->design108guardv1 = $existing;
            }
            require_once($CFG->libdir . '/adminlib.php'); require_once($CFG->libdir . '/upgradelib.php');
            if ($route === '/admin/index.php') {
                $PAGE->set_context(\context_system::instance());
            } else {
                admin_externalpage_setup($route === '/admin/tool/installaddon/index.php'
                    ? 'tool_installaddon_index' : 'pluginsoverview');
            }
            $PAGE->set_url(new \moodle_url(self::admin_route($route), $params));
            $PAGE->set_pagelayout('maintenance'); $PAGE->set_popup_notification_allowed(false);
            $params += [$confirmkey => 1, 'guardapproval' => $token];
            self::$nativewriter = $confirmed;
            self::install_plugins($local, $confirmed, get_string('plugincheck'),
                new \moodle_url(self::admin_route($route), $params), new \moodle_url(self::admin_route('/admin/plugins.php')));
            exit;
        } catch (\Throwable $e) {
            if (self::$nativewriter) { throw $e; }
            self::stop($e->getMessage());
        }
    }
    private static function message(string $key, mixed $a = null): string {
        if (self::$languageowner !== '' && get_string_manager()->string_exists($key, self::$languageowner)) {
            return get_string($key, self::$languageowner, $a);
        }
        // Packaging supplies language strings; fallback remains clear on an interrupted older source tree.
        return $key === 'updateguard_component' ? 'Package rejected: ' . (string)$a
            : match ($key) {
                'updateguard_heading' => 'Update stopped',
                'updateguard_hint' => 'Use an approved matching package or review the operation again.',
                'updateguard_unchanged' => 'This request did not replace plugin files.',
                'updateguard_help' => 'The installed plugin verifies publisher, content, channel and compatibility before replacement. One rejected package stops the whole batch.',
                'updateguard_back' => 'Return to plugin overview',
                default => 'The update could not be verified. No installation was started.',
            };
    }
    private static function stop(string $reason): never {
        global $PAGE, $OUTPUT;
        $parts = explode(':', $reason);
        if (in_array($parts[0], ['dependency-requirement', 'moodle-requirement'], true)
                && preg_match('/^[a-z][a-z0-9]*_[a-z][a-z0-9_]*$/D', $parts[1] ?? '')) {
            self::$context = $parts[1];
        }
        $code = $parts[0];
        $group = match (true) {
            in_array($code, ['invalid-signature', 'missing-signature', 'file-set-mismatch', 'file-hash-mismatch',
                'unsafe-path', 'duplicate-path', 'file-directory-conflict', 'symlink', 'archive-limit', 'invalid-archive'], true) => 'signature',
            $code === 'channel-rejected' => 'channel',
            in_array($code, ['release-policy', 'incoming-policy-invalid', 'guard-continuity-missing', 'not-newer', 'rollback-state-invalid'], true) => 'policy',
            str_starts_with($code, 'installed-') => 'installed',
            in_array($code, ['dependency-requirement', 'moodle-requirement'], true) => 'dependency',
            $code === 'approval-package-changed' => 'approval',
            $code === 'installation-busy' => 'busy',
            in_array($code, ['ambiguous-operation', 'operation-route-mismatch'], true) => 'operation',
            str_contains($code, 'version') || str_contains($code, 'dependency') => 'metadata',
            default => 'failed',
        };
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '', $code);
        $component = preg_match('/^[a-z][a-z0-9]*_[a-z][a-z0-9_]*$/D', self::$context)
            ? self::$context : 'selected package';
        http_response_code(409);
        $PAGE->set_context(\context_system::instance());
        if (!$PAGE->has_set_url()) { $PAGE->set_url(self::admin_route('/admin/plugins.php')); }
        $PAGE->set_pagelayout('maintenance');
        $PAGE->set_title(self::message('updateguard_heading'));
        echo $OUTPUT->header();
        echo '<!-- UPDATE_GUARD_BLOCKED:' . $safe . ':' . $component . ' -->';
        echo $OUTPUT->heading(self::message('updateguard_heading'));
        echo \html_writer::tag('p', self::message('updateguard_component', $component));
        echo $OUTPUT->notification(self::message('updateguard_' . $group), 'error');
        echo \html_writer::tag('p', self::message('updateguard_unchanged'));
        echo \html_writer::tag('p', self::message('updateguard_hint'));
        echo \html_writer::tag('p', self::message('updateguard_help'));
        echo \html_writer::link(new \moodle_url(self::admin_route('/admin/plugins.php')), self::message('updateguard_back'));
        echo $OUTPUT->footer();
        exit;
    }
}
