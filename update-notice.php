<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Package replacement compatibility.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace design108\package_notice\v1;

defined('MOODLE_INTERNAL') || die();

/** Adds information only; Core retains every installation decision and operation. */
final class notice {
    private static array $intents = [];
    private static array $shown = [];
    private static array $batches = [];

    /** Remember a native preview request without output or changes to its parameters. */
    public static function remember(string $owner, string $root): void {
        global $CFG;
        try {
            unset(self::$intents[$owner]);
            if (CLI_SCRIPT || !empty($CFG->disableupdateautodeploy)) {
                return;
            }
            $route = self::route($CFG->dirroot, $_SERVER, $CFG->admin ?? 'admin');
            if ($route === null) {
                return;
            }
            if ($route === '/admin/plugins.php' && (optional_param('uninstall', '', PARAM_COMPONENT)
                    || (optional_param('delete', '', PARAM_COMPONENT) && optional_param('confirm', false, PARAM_BOOL)))) {
                return;
            }
            if ($route === '/admin/index.php' && (optional_param('abortinstall', '', PARAM_COMPONENT)
                    || optional_param('abortinstallx', false, PARAM_BOOL))) {
                return;
            }
            $operations = match ($route) {
                '/admin/tool/installaddon/index.php' => ['installremote', 'installzipcomponent'],
                '/admin/plugins.php' => ['installupdatex', 'installupdate'],
                default => ['abortupgradex', 'abortupgrade', 'installdepx', 'installdep', 'installupdatex', 'installupdate'],
            };
            foreach ($operations as $operation) {
                $batch = str_ends_with($operation, 'x');
                $requested = optional_param($operation, $batch ? false : '', $batch ? PARAM_BOOL : PARAM_COMPONENT);
                if (!$requested) {
                    continue;
                }
                $version = optional_param($operation . 'version', 0, PARAM_INT);
                $storage = optional_param('installzipstorage', '', PARAM_FILE);
                if ((in_array($operation, ['installremote', 'installupdate'], true) && !$version)
                        || ($operation === 'installzipcomponent' && !$storage)) {
                    continue;
                }
                if (!$batch && $requested !== $owner) {
                    return;
                }
                $confirmation = match ($operation) {
                    'installzipcomponent' => 'installzipconfirm', 'installremote' => 'installremoteconfirm',
                    'installdep', 'installdepx' => 'confirminstalldep',
                    'abortupgrade', 'abortupgradex' => 'confirmabortupgrade', default => 'confirminstallupdate',
                };
                if (optional_param($confirmation, false, PARAM_BOOL)) {
                    return;
                }
                self::$intents[$owner] = compact('owner', 'root', 'operation', 'batch', 'version', 'storage');
                return;
            }
        } catch (\Throwable $ignored) {
            // An optional notice cannot change how Core handles malformed requests.
        }
    }

    /** Core calls this while rendering its header, before native validation/confirmation. */
    public static function before_body(string $owner, \core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        if (!isset(self::$intents[$owner]) || isset(self::$shown[$owner])) {
            return;
        }
        try {
            // Header hooks also run on error and overview pages. Only the native
            // preview function has reached its authorised, unconfirmed package flow.
            $preview = false;
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 64) as $frame) {
                if (($frame['function'] ?? '') === 'upgrade_install_plugins' && empty($frame['class'])) {
                    $preview = true;
                    break;
                }
            }
            if (!$preview) {
                return;
            }
            $intent = self::$intents[$owner];
            $candidate = self::candidate($intent);
            if ($candidate === null) {
                return;
            }
            $key = 'updateinfo_unavailable';
            $style = 'info';
            try {
                $module = $intent['root'] . '/release-metadata.php';
                if (!class_exists(\design108\release_metadata\v1\guard::class, false)) {
                    if (!is_readable($module)) {
                        throw new \RuntimeException('information-unavailable');
                    }
                    require_once($module);
                }
                $verifier = \design108\release_metadata\v1\guard::class;
                $policy = $verifier::load_policy($intent['root'], $owner);
                $pm = \core_plugin_manager::instance();
                $path = $candidate->zipfilepath ?? $pm->get_remote_plugin_zip(
                    $candidate->version->downloadurl, $candidate->version->downloadmd5);
                $expected = isset($candidate->version) ? (int)$candidate->version->version : null;
                $verifier::verify((string)$path, $policy, $owner, $expected);
                $key = 'updateinfo_verified';
            } catch (\Throwable $error) {
                [$key, $style] = match ($error->getMessage()) {
                    'missing-signature' => ['updateinfo_missing', 'info'],
                    'release-policy', 'incoming-policy-invalid' => ['updateinfo_line', 'warning'],
                    'invalid-signature', 'file-set-mismatch', 'file-hash-mismatch', 'version-mismatch' => ['updateinfo_mismatch', 'warning'],
                    default => ['updateinfo_unavailable', 'info'],
                };
            }
            $html = \html_writer::tag('strong', s(get_string('updateinfo_heading', $owner, $owner)));
            $html .= \html_writer::div(s(get_string($key, $owner)));
            $html .= \html_writer::div(s(get_string('updateinfo_choice', $owner, get_string('cancel'))));
            $hook->add_html(\html_writer::div($html, 'alert alert-' . $style . ' design108-package-information',
                ['role' => 'note', 'data-package-information' => $owner]));
            self::$shown[$owner] = true;
        } catch (\Throwable $ignored) {
            // No notice, verifier or renderer failure may stop the native request.
        }
    }

    /** Resolve the candidate selected by Core, without replacing its descriptor or writer. */
    private static function candidate(array $intent): ?object {
        global $CFG;
        $operation = $intent['operation'];
        if ($operation === 'installzipcomponent') {
            return (object)['component' => $intent['owner'],
                'zipfilepath' => $CFG->tempdir . '/tool_installaddon/' . $intent['storage'] . '/plugin.zip'];
        }
        $pm = \core_plugin_manager::instance();
        if ($intent['batch'] || in_array($operation, ['installdep', 'abortupgrade'], true)) {
            if (!isset(self::$batches[$operation])) {
                $items = match ($operation) {
                    'abortupgrade', 'abortupgradex' => $pm->list_restorable_archives(),
                    'installdep', 'installdepx' => $pm->filter_installable($pm->missing_dependencies(true)),
                    default => $pm->filter_installable($pm->available_updates()),
                };
                self::$batches[$operation] = [];
                foreach ($items as $item) {
                    self::$batches[$operation][$item->component] = $item;
                }
            }
            return self::$batches[$operation][$intent['owner']] ?? null;
        }
        $candidate = $pm->get_remote_plugin_info($intent['owner'], $intent['version'], true);
        return is_object($candidate) && !empty($candidate->version) ? $candidate : null;
    }

    /** Use the executed controller path, including a configured admin directory. */
    private static function route(string $dirroot, array $server, string $admin): ?string {
        $executed = realpath($server['SCRIPT_FILENAME'] ?? '');
        if (!$executed) {
            return null;
        }
        foreach (['/admin/plugins.php', '/admin/index.php', '/admin/tool/installaddon/index.php'] as $route) {
            $target = realpath($dirroot . '/' . trim($admin, '/') . substr($route, 6));
            if ($target && strcasecmp(str_replace('\\', '/', $executed), str_replace('\\', '/', $target)) === 0) {
                return $route;
            }
        }
        return null;
    }
}
