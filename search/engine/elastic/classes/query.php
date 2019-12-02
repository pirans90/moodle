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
 * Elasticsearch engine.
 *
 * Provides an interface between Moodles Global search functionality
 * and the Elasticsearch (https://www.elastic.co/products/elasticsearch)
 * search engine.
 *
 * Elasticsearch presents a REST Webservice API that we communicate with
 * via Curl.
 *
 * @package     search_elastic
 * @copyright   Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_elastic;

defined('MOODLE_INTERNAL') || die();

/**
 * Elasticsearch engine.
 *
 * @package     search_elastic
 * @copyright   Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query  {

    /**
     * @var number of records to return to Global Search.
     */
    private $limit = 0;

    /**
     * @var query string to pass to Elastic search.
     */
    private $query = array();

    /**
     * Highlighting fragsize. Slightly larger than output size (500) to allow for ... appending.
     */
    const FRAGMENT_SIZE = 510;

    /**
     * Marker for the start of a highlight.
     */
    const HIGHLIGHT_START = '@@HI_S@@';

    /**
     * Marker for the end of a highlight.
     */
    const HIGHLIGHT_END = '@@HI_E@@';

    /**
     * @var array Fields that can be highlighted.
     */
    protected $highlightfields = array('title', 'content', 'description1', 'description2');

    /** @var float Boost value for matching course in location-ordered searches */
    const COURSE_BOOST = 2;

    /** @var float Boost value for matching context (in addition to course boost) */
    const CONTEXT_BOOST = 1.5;


    /**
     * construct basic query structure
     */
    public function __construct() {

        $returnlimit = \core_search\manager::MAX_RESULTS;

        // Basic object to build query from.
        $this->query = array('query' => array(
                                'bool' => array(
                                    'must' => array(),
                                    'should' => array(),
                                    'filter' => array('bool' => array('must' => array()))
                            )),
                             'size' => $returnlimit,
                             '_source' => array('excludes' => array('filetext'))
        );
    }

    /**
     * Gets an array of fields to search.
     * The returned fields are what the 'q' string is matched against in a search.
     * It makes sense to not search every field here, so some are removed.
     *
     * @return array
     */
    private function get_search_fields() {
        $allfields = array_keys( \core_search\document::get_default_fields_definition());
        array_push($allfields, 'filetext');
        $excludedfields = array('itemid',
                'areaid',
                'courseid',
                'contextid',
                'userid',
                'owneruserid',
                'modified',
                'type',
                'groupid'
        );
        $searchfields = array_diff($allfields, $excludedfields);

        return array_values($searchfields);
    }

    /**
     * Add * wildcard to beginning and/or end of search string if required.
     *
     * @param string $q The query string.
     * @param bool $start Add a wildcard at the start?
     * @param bool $end Add a wildcard at the end?

     * @return string $q The query string with added wildcards.
     */
    private function add_wildcards($q, $start = false, $end = false) {

        $terms = explode(" ", $q); // Break search string into individual words.
        $wildcardterms = array();

        // Add wildcards to start and end of words.
        foreach ($terms as $term) {

            if (empty($term)) {
                continue;
            }

            $term = str_replace(' ', '', $term);

            // Ignore words: and, or.
            if (strtolower($term) == 'and' || strtolower($term) == 'or') {
                $wildcardterms[] = $term;
                continue;
            }

            if ($start) {
                // Add wild card to start of word.
                $term = '*' . ltrim($term, '*');
            }

            if ($end) {
                // Add wild card to end of word.
                $term = rtrim($term, '*') . '*';
            }

            $wildcardterms[] = $term;
        }

        // Reconstruct search query.
        if (!empty($wildcardterms)) {
            $q = implode(' ', $wildcardterms);
        }

        return $q;
    }

    /**
     * Takes the search string the user has entered
     * and constructs the corresponding part of the
     * search query.
     *
     * @param string $q The query string.
     * @return array
     */
    private function construct_q($q) {
        $q = $this->add_wildcards(
            $q,
            get_config('search_elastic', 'wildcardstart'),
            get_config('search_elastic', 'wildcardend')
        );

        $searchfields = $this->get_search_fields();
        $qobj = array('query_string' => array('query' => $q, 'fields' => $searchfields));

        return $qobj;
    }

    /**
     * Construct all reesult wildcard query
     * @return string[][]
     */
    private function construct_q_all() {
        return array('query_string' => array('query' => '*', 'fields' => $this->get_search_fields()));
    }

    /**
     * Takes supplied user contexts from Moodle search core
     * and constructs the corresponding part of the
     * search query.
     *
     * @param array $usercontexts
     * @return array
     */
    private function construct_contexts($usercontexts) {
        $contextobj = array('terms' => array('contextid' => array()));
        $contexts = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($usercontexts));

        foreach ($iterator as $key => $value) {
            array_push ($contexts, $value);
        }
        $contexts = array_values(array_unique ($contexts));
        $contextobj['terms']['contextid'] = $contexts;
        return $contextobj;
    }

    /**
     * Takes the form submission filter data and given a key value
     * constructs a single match component for the search query.
     *
     * @param string $title
     * @return array
     */
    private function construct_title($title) {
        $titleobj = array('multi_match' => array('query' => $title,
                                                 'fields' => array('title'),
                                                 'type' => "phrase_prefix")
        );

        return $titleobj;
    }

    /**
     * Takes the form submission filter data and given a key value
     * constructs an array of match components for the search query.
     *
     * @param array $filters
     * @param string $key
     * @param string $match
     * @return array
     */
    private function construct_array($filters, $key, $match) {
        $arrayobj = array('terms' => array($match => array()));
        $values = $filters->$key;

        foreach ($values as $value) {
            array_push ($arrayobj['terms'][$match], $value);
        }

        return $arrayobj;
    }

    /**
     * Takes the form submission filter data and
     * constructs the time range components for the search query.
     *
     * @param array $filters
     * @return array
     */
    private function construct_time_range($filters) {
        $contextobj = array('range' => array('modified' => array()));

        if (isset($filters->timestart) && $filters->timestart != 0) {
            $contextobj['range']['modified']['gte'] = $filters->timestart;
        }
        if (isset($filters->timeend) && $filters->timeend != 0) {
            $contextobj['range']['modified']['lte'] = $filters->timeend;
        }

        return $contextobj;
    }

    /**
     * Take array of search areas to be boosted and constructs the query.
     *
     * @param array $boostedareas The areas that need to be boosted at query time.
     * @return array $boostedarray Array of areas that should be boosted.
     */
    private function consruct_boosting($boostedareas) {

        $boostarray = array();

        foreach ($boostedareas as $area => $value) {
            array_push($boostarray, array('match' => array('areaid' => array('query' => $area, 'boost' => $value))));
        }

        return $boostarray;
    }

    /**
     * Construct boosting query portion for location prioritised search.
     *
     * @param string $area The area to boost.
     * @param string|int $query The area id.
     * @param int $boost The boosting ammount.
     * @return array $boostarray Query fragment.
     */
    private function consruct_location_boosting($area, $query, $boost) {

        $boostarray = array(array('match' => array($area => array('query' => $query, 'boost' => $boost))));

        return $boostarray;
    }

    /**
     * Returns an array of the configured boosted areas and boost values.
     * @return array $boostedareas An array of the boosted areas and boost values.
     */
    public function get_boosted_areas() {
        $configitems = get_config('search_elastic');
        $boostedareas = array();
        $query = 'boost_';

        foreach ($configitems as $item => $value) {
            if (substr($item, 0, strlen($query)) === $query && $value > 10) {
                $area = substr($item, strlen($query));
                $boostedareas[$area] = ($value / 10);
            }
        }

        return $boostedareas;
    }

    /**
     * Add highlighting elements to query array.
     *
     * @param array $query query array.
     * @return array $query updated query array with highlighting elements.
     */
    public function set_highlighting($query) {
        $hightlighting = array(
                'pre_tags' => array(self::HIGHLIGHT_START),
                'post_tags' => array(self::HIGHLIGHT_END),
                'fragment_size' => self::FRAGMENT_SIZE,
                'encoder' => 'html',
                'fields' => array()
        );

        foreach ($this->highlightfields as $field) {
            $hightlighting['fields'][$field] = new \stdClass();
        }

        $query['highlight'] = $hightlighting;

        return $query;
    }

    /**
     * Construct the Elasticsearch query
     *
     * @param object $filters
     * @param mixed $accessinfo Information about the contexts the user can access.
     *                          Depending on the Moodle version accessinfo could be
     *                          as true, array or object.
     *
     * @return \search_elastic\query
     */
    public function get_query($filters, $accessinfo) {
        $query = $this->query;
        $boostedareas = $this->get_boosted_areas();

        // Add query text.
        if ($filters->q != '*') {
            $q = $this->construct_q($filters->q);
        } else {
            $q = $this->construct_q_all();
        }
        array_push($query['query']['bool']['must'], $q);

        // Add contexts.
        $usercontexts = $this->extract_usercontexts($accessinfo);
        if (gettype($usercontexts) == 'array') {
            $contexts = $this->construct_contexts($usercontexts);
            array_push ($query['query']['bool']['filter']['bool']['must'], $contexts);
        }

        // Add filters.
        if (isset($filters->title) && $filters->title != null) {
            $title = $this->construct_title($filters->title);
            $query['query']['bool']['must'][] = $title;
        }
        if (isset($filters->areaids) && $filters->areaids != null && !empty($filters->areaids)) {
            $areaids = $this->construct_array($filters, 'areaids', 'areaid');
            array_push ($query['query']['bool']['filter']['bool']['must'], $areaids);
        }
        if (isset($filters->courseids) && $filters->courseids != null && !empty($filters->courseids)) {
            $courseids = $this->construct_array($filters, 'courseids', 'courseid');
            array_push ($query['query']['bool']['filter']['bool']['must'], $courseids);
        }
        if (isset($filters->userids) && $filters->userids != null && !empty($filters->userids)) {
            $userids = $this->construct_array($filters, 'userids', 'userid');
            array_push ($query['query']['bool']['filter']['bool']['must'], $userids);
        }
        if (isset($filters->groupids) && $filters->groupids != null && !empty($filters->groupids)) {
            $groupids = $this->construct_array($filters, 'groupids', 'groupid');
            array_push ($query['query']['bool']['filter']['bool']['must'], $groupids);
        }
        if ($filters->timestart != 0  || $filters->timeend != 0) {
            $timerange = $this->construct_time_range($filters);
            array_push ($query['query']['bool']['filter']['bool']['must'], $timerange);
        }

        // Add highlighting.
        $query = $this->set_highlighting($query);

        // Get search order filters and work it in with boosting.
        if (!empty($filters->order) && $filters->order === 'location') {
            // Boost course in all cases when we are prioritising location.
            $coursecontext = $filters->context->get_course_context();
            $courseid = $coursecontext->instanceid;

            $courseboost = $this->consruct_location_boosting('courseid', $courseid, self::COURSE_BOOST);
            array_push ($query['query']['bool']['should'], $courseboost);

            if ($filters->context->contextlevel !== CONTEXT_COURSE) {
                // If it's a block or activity, also add a boost for the specific context id.
                $contextid = $filters->context->id;
                $contextboost = $this->consruct_location_boosting('contextid', $contextid, self::CONTEXT_BOOST);
                array_push ($query['query']['bool']['should'], $contextboost);
            }
        }

        // Add boosting.
        if ($boostedareas) {
            $boosting = $this->consruct_boosting($boostedareas);
            array_push ($query['query']['bool']['should'], $boosting);
        }

        // Add date based sorting.
        if (!empty($filters->order) && ($filters->order === 'asc' || $filters->order === 'desc')) {
            $query['sort'] = array('modified' => array('order' => $filters->order));
        }

        return $query;
    }

    /**
     * Extract usercontexts data from access info.
     *
     * @param mixed $accessinfo Information about the contexts the user can access.
     *                          Depending on the Moodle version accessinfo could be
     *                          as true, array or object.
     *
     * @return mixed
     */
    private function extract_usercontexts($accessinfo) {
        $usercontexts = null;

        if (is_array($accessinfo)) {
            $usercontexts = $accessinfo;
        }

        if (is_object($accessinfo)) {
            // If everything is set to false, then user should have limited access.
            if (empty($accessinfo->everything) && isset($accessinfo->usercontexts)) {
                $usercontexts = $accessinfo->usercontexts;
            }
        }

        return $usercontexts;
    }
}