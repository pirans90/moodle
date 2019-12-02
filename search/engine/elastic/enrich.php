<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Elastic search engine settings.
 *
 * @package    search_elastic
 * @copyright  2017 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

admin_externalpage_setup('search_elastic_enrichsettings');

$context = context_system::instance();

$PAGE->requires->js_call_amd('search_elastic/enrich', 'init', array($context->id));

$config = get_config('search_elastic');
$form = new \search_elastic\enrich_form(null, null, 'post', '', ['class' => 'ignoredirty']);

if ($data = $form->get_data()) {

    // Save plugin config.
    foreach ($data as $name => $value) {
        set_config($name, $value, 'search_elastic');
    }

    redirect(new moodle_url('/search/engine/elastic/enrich.php'), get_string('changessaved'));
}

// Build the page output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrichsettings', 'search_elastic'));
echo html_writer::div(get_string('enrichdesc', 'search_elastic'), 'enrich_description');
echo html_writer::div($form->render(), 'form_container');
echo $OUTPUT->footer();
