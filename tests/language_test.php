<?php
/**
 * Regression tests for language.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront;

defined('MOODLE_INTERNAL') || die();
/**
 * Regression tests for language.
 */
final class language_test extends \basic_testcase {
    /**
     * Verify that shipped page and help strings exist in both languages.
     *
     * @return void
     */
    public function test_shipped_page_and_help_strings_exist_in_both_languages(): void {
        // Conditional and data-driven page labels do not appear as literal get_string calls.
        $required = [
        'adminhelp_legal_customer_article',
         'editlegaldocument',
         'addlegaldocument',
         'legaleditingintro',
         'legalcreatingintro',

            'showincheckout', 'showincheckout_help', 'requireconfirmation', 'requireconfirmation_help',
            'checkoutuse', 'checkoutlinked', 'checkoutnotshown', 'checkoutconfirmationrequired',
            'coursepluginenabled', 'courseplugindisabled',
        ];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/..'));
        foreach ($iterator as $file) {
            if (
                $file->getExtension() !== 'php'
                || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }
            preg_match_all(
                "/get_string\('([^']+)',\s*'local_storefront'/",
                file_get_contents($file->getPathname()),
                $matches
            );
            $required = array_merge($required, $matches[1]);
            preg_match_all(
                "/addHelpButton\('[^']+',\s*'([^']+)',\s*'local_storefront'/",
                file_get_contents($file->getPathname()),
                $help
            );
            foreach ($help[1] as $key) {
                $required[] = $key;
                $required[] = $key . '_help';
            }
        }
        foreach (
            [
            'shopping',
            'purchases',
            'legal',
            'products',
            'offers',
            'taxonomy',
            'delivery',
            'operations',
            'settings',
            ] as $topic
        ) {
            $required[] = 'adminhelp_' . $topic;
            $required[] = 'adminhelp_' . $topic . '_article';
        }
        foreach (['en', 'de'] as $lang) {
            $string = [];
            include(__DIR__ . '/../lang/' . $lang . '/local_storefront.php');
            foreach (array_unique($required) as $key) {
                $this->assertArrayHasKey($key, $string, $lang . ':' . $key);
            }
        }
        global $CFG;
        foreach (['en', 'de'] as $lang) {
            $string = [];
            include($CFG->dirroot . '/enrol/storefront/lang/' . $lang . '/enrol_storefront.php');
            foreach (['product', 'product_help', 'enrolperiod', 'enrolperiod_help'] as $key) {
                $this->assertArrayHasKey($key, $string, 'enrol_storefront ' . $lang . ':' . $key);
            }
        }
    }
}
