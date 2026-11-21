<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Package replacement compatibility.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace design108\release_metadata\v1;
defined('MOODLE_INTERNAL') || die();
final class guard {
    private const DOMAIN = "108design-plugin-release-v1\n";
    private const MANIFEST = 'release-manifest.json';
    private const SIGNATURE = 'release-signature.txt';
    public const IMPLEMENTATION = 3;
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
        return file_exists($rootdir . '/update-guard.php')
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
}
