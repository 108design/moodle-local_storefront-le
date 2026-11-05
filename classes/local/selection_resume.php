<?php
/**
 * Short-lived, single-use continuation for a public product selection across login devices.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen
 * @license   108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. See LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Short-lived, single-use continuation for a public product selection across login devices.
 */
final class selection_resume {
    /**
     * Validate the local destination allowed by selection resumption.
     *
     * @param string $url Local or validated destination URL.
     * @return string
     */
    public static function local_return(string $url): string {
        $url = clean_param($url, PARAM_LOCALURL);
        if ($url === '' || str_contains($url, '\\')) {
            return '/local/storefront/selection.php';
        }
        $url = new \moodle_url($url);
        // Only presentation routes, never arbitrary administration/action endpoints.
        $path = $url->get_path();
        if (!preg_match('~/(local/storefront/(index|product|cart|selection|selection_complete)\.php)$~D', $path)) {
            return '/local/storefront/selection.php';
        }
        $url->remove_params('sesskey', 'resume', 'action', 'choice', 'product', 'offerid', 'intent', 'returnurl');
        if (!str_ends_with($path, '/selection_complete.php')) {
            $url->param('selection', 1);
        }
        return $url->out_as_local_url(false);
    }

    /**
     * Store a one-use cart-resumption token for a validated local return.
     *
     * @param string $returnurl Local URL to return to.
     * @return string
     */
    public static function create(string $returnurl): string {
        global $SESSION;
        $cache = \cache::make('local_storefront', 'selection_resume');
        if (!empty($SESSION->local_storefront_resumehash)) {
            $cache->delete($SESSION->local_storefront_resumehash);
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $cache->set($hash, ['selection' => product_selection::snapshot(),
            'returnurl' => self::local_return($returnurl), 'expires' => time() + HOURSECS]);
        $SESSION->local_storefront_resumehash = $hash;
        return $token;
    }

    /**
     * Consume the stored request and return its validated destination.
     *
     * @param string $token Token.
     * @return string
     */
    public static function consume(string $token): string {
        if (!isloggedin() || isguestuser() || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new \moodle_exception('selectionexpired', 'local_storefront');
        }
        $hash = hash('sha256', $token);
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock('selection:' . $hash, 5);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $cache = \cache::make('local_storefront', 'selection_resume');
            $value = $cache->get($hash);
            if (!$value || $value['expires'] < time()) {
                throw new \moodle_exception('selectionexpired', 'local_storefront');
            }
            product_selection::restore($value['selection']);
            $cache->delete($hash);
            return self::local_return($value['returnurl']);
        } finally {
            $lock->release();
        }
    }
}
