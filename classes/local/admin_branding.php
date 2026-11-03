<?php
/**
 * This file is part of a 108design source-available software product.
 *
 * Use and modification are permitted only under the Software License included
 * with this distribution. See LICENSE.md for the full terms.
 *
 * File documentation for classes/local/admin_branding.php.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen
 * @license   See LICENSE.md for the full terms.
 */
// Copyright (C) 2026 Andreas Giesen. See the plugin's LICENSE or LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Decorative product identity on explicitly selected administration surfaces only. */
final class admin_branding {
    /** Native page paths and their existing system-level administration capabilities. */
    private const PAGES = ['/local/storefront/products.php' => 'local/storefront:managecatalogue', '/local/storefront/product_edit.php' => 'local/storefront:managecatalogue', '/local/storefront/offers.php' => 'local/storefront:managecatalogue', '/local/storefront/offer_edit.php' => 'local/storefront:managecatalogue', '/local/storefront/course_mapping.php' => 'local/storefront:managecatalogue', '/local/storefront/categories.php' => 'local/storefront:managecatalogue', '/local/storefront/category_edit.php' => 'local/storefront:managecatalogue', '/local/storefront/category_to_tag.php' => 'local/storefront:managecatalogue', '/local/storefront/tags.php' => 'local/storefront:managecatalogue', '/local/storefront/tag_edit.php' => 'local/storefront:managecatalogue', '/local/storefront/ordering.php' => 'local/storefront:managecatalogue', '/local/storefront/orders.php' => 'local/storefront:manageorders', '/local/storefront/communication_rules.php' => 'local/storefront:manageorders', '/local/storefront/legal_documents.php' => 'local/storefront:managelegal', '/local/storefront/legal_edit.php' => 'local/storefront:managelegal', '/local/storefront/legal_settings.php' => 'local/storefront:managelegal', '/local/storefront/legal_delete.php' => 'local/storefront:managelegal', '/local/storefront/legal_preview.php' => 'local/storefront:managelegal', '/local/storefront/configuration.php' => 'moodle/site:config'];
    /** Existing native settings sections; these are never customer routes. */
    private const SECTIONS = [];
    /** Existing administration links, without changing their labels or destinations. */
    private const LINKS = ['/local/storefront/products.php' => 'local/storefront:managecatalogue'];

    /** Give native external pages a product heading when Core supplied only the site name. */
    public static function prepare(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE, $SITE;
        if (self::page_capability() !== null
                && $PAGE->url->get_path() !== (new \moodle_url('/' . $CFG->admin . '/settings.php'))->get_path()
                && trim(strip_tags($PAGE->heading)) === trim(strip_tags($SITE->fullname))) {
            $PAGE->set_heading(get_string('pluginname', 'local_storefront'));
        }
    }

    public static function head(\core\hook\output\before_standard_head_html_generation $hook): void {
        $hook->add_html(self::html($hook->renderer));
    }

    /** Allowlist shared with this plugin's contextual administration help, if present. */
    public static function page_capability(): ?string {
        global $CFG, $PAGE;
        if (during_initial_install() || !isloggedin() || isguestuser()
                || (int)$PAGE->context->contextlevel !== CONTEXT_SYSTEM) {
            return null;
        }
        $path = $PAGE->url->get_path();
        $system = \context_system::instance();
        foreach (self::PAGES as $route => $capability) {
            if ($path === (new \moodle_url($route))->get_path() && has_capability($capability, $system)) {
                return $capability;
            }
        }
        if ($path === (new \moodle_url('/' . $CFG->admin . '/settings.php'))->get_path()
                && in_array((string)$PAGE->url->get_param('section'), self::SECTIONS, true)
                && has_capability('moodle/site:config', $system)) {
            return 'moodle/site:config';
        }
        return null;
    }

    /** Also used by the pre-hook fallback on supported older Moodle versions. */
    public static function html(\renderer_base $renderer): string {
        global $CFG, $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return '';
        }
        $path = $PAGE->url->get_path();
        $admin = '/' . $CFG->admin;
        $nativepath = static fn(string $value): string => (new \moodle_url($value))->get_path();
        $system = \context_system::instance();
        $css = '';
        $image = json_encode($renderer->image_url('adminbrand', 'local_storefront')->out(false),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        // An administrator looking at a customer or course page still sees no product branding.
        if (self::page_capability() !== null && $path !== $nativepath($admin . '/settings.php')) {
            $css .= self::title_css('#page-header h1', $image);
        } else if (self::page_capability() !== null) {
            // Native settings retain the site name in H1; the product title is the settings-form H2.
            $css .= self::title_css('#adminsettings .settingsform > h2', $image);
        }
        if ($path === $nativepath($admin . '/plugins.php')
                && has_capability('moodle/site:config', $system)) {
            // Do not replace pix/icon or monologo: those may also identify a learner-facing activity.
            $row = 'body#page-admin-plugins tr.name-local_storefront .pluginname .displayname';
            $css .= $row . ' > img.icon { display: none; }' . "\n";
            $css .= self::icon_css($row . '::before', $image, '1em');
        }
        if ($path === $nativepath($admin . '/index.php')
                && (int)$PAGE->context->contextlevel === CONTEXT_SYSTEM
                && has_capability('moodle/site:config', $system)) {
            // Upgrade checks have different markup from the ordinary plugin overview.
            // Keep the activity's icon/monologo assets unchanged for course pages.
            $row = 'body#page-admin-index #plugins-check tr.name-local_storefront td.displayname';
            $css .= $row . ' > img.pluginicon { display: none; }' . "\n";
            $css .= self::icon_css($row . ' > .pluginname::before', $image, '1em');
        }
        if ($path === $nativepath($admin . '/search.php')) {
            foreach (self::LINKS as $target => $capability) {
                if (!has_capability($capability, $system)) {
                    continue;
                }
                $target = str_replace('{admin}', $CFG->admin, $target);
                $link = json_encode((new \moodle_url($target))->out(false),
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $css .= self::icon_css('body#page-admin-search #region-main a[href=' . $link . ']::before',
                    $image, '1em');
            }
        }
        return $css === '' ? '' : \html_writer::tag('style', $css, ['id' => 'local_storefront-admin-branding']);
    }

    private static function icon_css(string $selector, string $image, string $size): string {
        return $selector . ' { content: ""; display: inline-block; width: ' . $size . '; height: ' . $size
            . '; margin-inline-end: .35em; vertical-align: -.125em; background: url(' . $image
            . ') center / contain no-repeat; }' . "\n";
    }

    private static function title_css(string $selector, string $image): string {
        return self::icon_css($selector . '::before', $image, '1em') . <<<CSS
@media (max-width: 575.98px) {
    #page-header .d-flex:has(> .header-actions-container) { flex-wrap: wrap; row-gap: .5rem; }
    #page-header .d-flex:has(> .header-actions-container) > .me-auto { flex: 1 0 100%; }
    #page-header .header-actions-container { order: -1; flex: 0 0 100%; display: flex; justify-content: flex-end; }
}
CSS;
    }
}
