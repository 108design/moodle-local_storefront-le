<?php
/**
 * Contextual settings help, with native drawer and keyboard support.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen
 * @license   See LICENSE.md for the full terms.
 */

// Copyright (C) 2026 Andreas Giesen. See LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Contextual settings help, with native drawer and keyboard support.
 */
final class admin_help {
    /**
     * @var bool Prepared.
     */
    private static bool $prepared = false;
    /**
     * @var string Defaulttopic.
     */
    private static string $defaulttopic = 'shopping';

    /**
     * Register only the permitted settings page before its header is rendered.
     *
     * @param mixed $hook Native Moodle output hook.
     * @return void
     */
    public static function prepare($hook = null): void {
        global $CFG, $PAGE;
        $ownpage = str_contains($PAGE->url->get_path(), '/local/storefront/');
        $enrolpage = str_ends_with($PAGE->url->get_path(), '/enrol/editinstance.php')
            && optional_param('type', '', PARAM_PLUGIN) === 'storefront';
        if (self::$prepared || (!$ownpage && !$enrolpage)) {
            return;
        }
        self::$prepared = true;
        $PAGE->add_body_class('local-storefront-admin-help-page');
        $PAGE->requires->css(new \moodle_url('/local/storefront/styles/admin_help.css'));
        $module = (int)$CFG->version < 2025041400 ? 'admin_help_legacy' : 'admin_help';
        $PAGE->requires->js_call_amd('local_storefront/admin_help_form', 'init');
        $topics = self::topics();
        $requested = optional_param('storefronthelp', '', PARAM_ALPHA);
        $route = basename($PAGE->url->get_path(), '.php');
        $default = match ($route) {
            'my'=>'purchases', 'products', 'product_edit'=>'products', 'offers', 'offer_edit'=>'offers',
            'categories', 'category_edit', 'category_to_tag', 'tags', 'tag_edit', 'ordering'=>'taxonomy',
            'course_mapping', 'editinstance'=>'delivery', 'orders', 'communication_rules'=>'operations',
            'configuration'=>'settings', default=>str_starts_with($route, 'legal') ? 'legal' : 'shopping',
        };
        self::$defaulttopic = in_array($default, $topics, true) ? $default : 'shopping';
        // Only an explicit help link opens the panel; the native selector stores the page default.
        $topic = in_array($requested, $topics, true) ? $requested : '';
        $PAGE->requires->js_call_amd('local_storefront/' . $module, 'init', [['topic' => $topic]]);
    }

    /**
     * Native popover with concise guidance and a direct link to the extended topic.
     *
     * @param string $key Stable lookup key.
     * @return string
     */
    public static function icon(string $key): string {
        global $CFG, $PAGE;
        if (!in_array($key, self::topics(), true)) {
            throw new \coding_exception('Unknown Administration help topic.');
        }
        $url = new \moodle_url($PAGE->url);
        $url->param('storefronthelp', $key);
        $help = new \help_icon($key, 'local_storefront', $url->out(false));
        $renderer = $PAGE->get_renderer('core');
        $data = (array)$help->export_for_template($renderer);
        $data['completedoclink'] = \html_writer::link($url, get_string('adminhelpmore', 'local_storefront'));
        $data['legacy'] = (int)$CFG->version < 2025041400;
        $data['infoicon'] = (int)$CFG->version < 2026100500 ? 'i/info' : 'i/circleinfo';
        return $renderer->render_from_template('local_storefront/settings_help_icon', $data);
    }

    /**
     * Always available in the native Topnav user area on the registered page.
     *
     * @param \renderer_base $renderer Native renderer used for output.
     * @return string
     */
    public static function navbar(\renderer_base $renderer): string {
        global $CFG;
        if (!self::$prepared) {
            return '';
        }
        $label = get_string('adminhelp', 'local_storefront');
        return \html_writer::tag('button', $renderer->pix_icon(
            (int)$CFG->version < 2026100500 ? 'i/info' : 'i/circleinfo',
            ''
        ), [
                'type' => 'button', 'class' => 'nav-link icon-no-margin local-storefront-help-navbar',
                'data-storefront-help-open' => '', 'aria-label' => $label, 'title' => $label,
                'aria-controls' => 'local-storefront-context-help', 'aria-expanded' => 'false',
            ]);
    }

    /**
     * Shared header, topic chooser and article layout for contextual help.
     *
     * @return string
     */
    public static function render(): string {
        global $PAGE;
        if (!self::$prepared) {
            return '';
        }
        $title = get_string('adminhelp', 'local_storefront');
        $close = \html_writer::tag('button', \html_writer::span('&#215;', '', ['aria-hidden' => 'true']), [
            'type' => 'button', 'class' => 'btn btn-secondary btn-sm local-storefront-help-close',
            'aria-label' => get_string('closebuttontitle', 'moodle'), 'data-storefront-help-close' => '1',
        ]);
        $options = '';
        $articles = '';
        foreach (self::topics() as $topic) {
            $label = get_string('adminhelp_' . $topic, 'local_storefront');
            $articlekey = $topic === 'legal'
            && !has_capability('local/storefront:managelegal', \context_system::instance())
                ? 'adminhelp_legal_customer_article' : 'adminhelp_' . $topic . '_article';
            $attributes = ['value' => $topic];
            if ($topic === self::$defaulttopic) {
                $attributes['selected'] = 'selected';
            }
            $options .= \html_writer::tag('option', $label, $attributes);
            $articles .= \html_writer::tag(
                'article',
                \html_writer::tag('h3', $label, [ 'class' => 'h5', 'tabindex' => '-1' ])
                . get_string($articlekey, 'local_storefront'),
                [ 'data-storefront-help-article' => $topic, 'hidden' => 'hidden' ]
            );
        }
        $choice = \html_writer::tag('label', get_string('adminhelptopics', 'local_storefront'), [
            'for' => 'local-storefront-help-topic', 'class' => 'form-label',
        ]) . \html_writer::tag('select', $options, [
            'id' => 'local-storefront-help-topic', 'class' => 'form-select custom-select',
        ]);
        // Shared help-panel identity: no link or action; close remains beside the topic selector.
        $brand = \html_writer::tag('span', \html_writer::empty_tag('img', [
            'src' => $PAGE->get_renderer('core')->image_url('helpbrand', 'local_storefront')->out(false),
            'alt' => '', 'width' => 25, 'height' => 24,
        ]), [
            'class' => 'local-storefront-help-brand', 'role' => 'img', 'aria-label' => '108design',
            'title' => '108design', 'tabindex' => '0', 'data-toggle' => 'tooltip', 'data-bs-toggle' => 'tooltip',
        ]);
        $header = \html_writer::tag(
            'header',
            \html_writer::tag('h2', $title, [
            'id' => 'local-storefront-context-help-title', 'class' => 'sr-only visually-hidden', 'tabindex' => '-1',
            ]) . \html_writer::div($choice, 'local-storefront-help-choices') . $brand . $close,
            ['class' => 'local-storefront-help-header local-storefront-help-header-branded']
        );
        return \html_writer::tag('aside', $header
            . \html_writer::div($articles, 'local-storefront-help-content', ['tabindex' => '0']), [
                'id' => 'local-storefront-context-help', 'class' => 'local-storefront-context-help',
                'aria-labelledby' => 'local-storefront-context-help-title', 'hidden' => 'hidden',
                'data-storefront-help-url' => $PAGE->url->out(false),
            ]);
    }

    /**
     * Append the registered panel without changing the page's controls.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook Native Moodle output hook.
     * @return void
     */
    public static function footer(\core\hook\output\before_standard_footer_html_generation $hook): void {
        $hook->add_html(self::render());
    }

    /**
     * Do not render administration guidance for customers lacking its capability.
     *
     * @return array
     */
    private static function topics(): array {
        $topics = ['shopping', 'purchases', 'legal'];
        $context = \context_system::instance();
        if (has_capability('local/storefront:managecatalogue', $context)) {
            $topics = array_merge($topics, ['products', 'offers', 'taxonomy', 'delivery']);
        }
        if (has_capability('local/storefront:manageorders', $context)) {
            $topics[] = 'operations';
        }
        global $PAGE;
        if (
            $PAGE->context->contextlevel === CONTEXT_COURSE && has_capability('enrol/storefront:config', $PAGE->context)
                && !in_array('delivery', $topics, true)
        ) {
            $topics[] = 'delivery';
        }
        if (has_capability('moodle/site:config', $context)) {
            $topics[] = 'settings';
        }
        return $topics;
    }
}
