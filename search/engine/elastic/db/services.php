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
 * Elasticsearch web service external functions and service definitions.
 *
 * @package    search_elastic
 * @copyright  2017 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = array(
        'search_elastic_search' => array(
                'classname'   => 'search_elastic_external',
                'methodname'  => 'search',
                'classpath'   => 'search/engine/elastic/externallib.php',
                'description' => 'Returns search results based on provided search query',
                'type'        => 'read',
                'capabilities'  => 'moodle/search:query',
                'ajax' => true
        ),
        'search_elastic_search_areas' => array(
                'classname'   => 'search_elastic_external',
                'methodname'  => 'search_areas',
                'classpath'   => 'search/engine/elastic/externallib.php',
                'description' => 'Returns search areas',
                'type'        => 'read',
                'capabilities'  => 'moodle/search:query',
                'ajax' => true
        )
);


// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
        'Search service' => array(
                'functions' => array('search_elastic_search', 'search_elastic_search_areas'),
                'restrictedusers' => 0,
                'enabled' => 1,
        )
);