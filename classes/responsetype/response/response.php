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
 * This defines a structured class to hold responses.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package response
 * @copyright 2019, onwards Poet
 */

namespace mod_questionnaire\responsetype\response;
use mod_questionnaire\responsetype\answer\answer;

defined('MOODLE_INTERNAL') || die();

class response {

    // Class properties.

    /** @var int $id The id of the response this applies to. */
    public $id;

    /** @var int $questionnaireid The id of the questionnaire this response applies to. */
    public $questionnaireid;

    /** @var int $userid The id of the user for this response. */
    public $userid;

    /** @var int $submitted The most recent submission date of this response. */
    public $submitted;

    /** @var boolean $complete Flag for final submission of this response. */
    public $complete;

    /** @var int $grade Numeric grade for this response (if applicable). */
    public $grade;

    /** @var array $answers Array by question of array of answer objects. */
    public $answers;

    /**
     * Choice constructor.
     * @param null $id
     * @param null $questionnaireid
     * @param null $userid
     * @param null $submitted
     * @param null $complete
     * @param null $grade
     * @param bool $addanswers
     */
    public function __construct($id = null, $questionnaireid = null, $userid = null, $submitted = null, $complete = null,
                                $grade = null, $addanswers = true) {
        global $DB;

        $this->id = $id;
        $this->questionnaireid = $questionnaireid;
        $this->userid = $userid;
        $this->submitted = $submitted;
        $this->complete = $complete;
        $this->grade = $grade;

        // Add answers by questions that exist.
        if ($addanswers) {
            $this->add_questions_answers();
        }
    }

    /**
     * Create and return a response object from data.
     *
     * @param object | array $responsedata The data to load.
     * @return response
     */
    public static function create_from_data($responsedata) {
        if (!is_array($responsedata)) {
            $responsedata = (array)$responsedata;
        }

        $properties = array_keys(get_class_vars(__CLASS__));
        foreach ($properties as $property) {
            if (!isset($responsedata[$property])) {
                $responsedata[$property] = null;
            }
        }

        return new response($responsedata['id'], $responsedata['questionnaireid'], $responsedata['userid'],
            $responsedata['submitted'], $responsedata['complete'], $responsedata['grade']);
    }

    /**
     * Provide a response object from web form data to the question.
     *
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param array \mod_questionnaire\question\question $questions
     * @return bool|response A response object.
     * @throws \coding_exception
     */
    static public function response_from_webform($responsedata, $questions) {
        global $USER;

        $questionnaireid = isset($responsedata->questionnaire_id) ? $responsedata->questionnaire_id :
            (isset($responsedata->a) ? $responsedata->a : 0);
        $response = new response($responsedata->rid, $questionnaireid, $USER->id, null, null, null, false);
        foreach ($questions as $question) {
            if ($question->supports_responses()) {
                $response->answers[$question->id] = $question->responsetype::answers_from_webform($responsedata, $question);
            }
        }
        return $response;
    }

    /**
     * Provide a response object from mobile app data to the question.
     *
     * @param $questionnaireid
     * @param $responseid
     * @param \stdClass $responsedata All of the responsedata as an object.
     * @param $questions Array of question objects.
     * @return bool|response A response object.
     */
    static public function response_from_appdata($questionnaireid, $responseid, $responsedata, $questions) {
        global $USER;

        $response = new response($responseid, $questionnaireid, $USER->id, null, null, null, false);

        // Process app data by question and choice and create a webform structure.
        $processedresponses = new \stdClass();
        $processedresponses->rid = $responseid;
        foreach ($responsedata as $questiondata) {
            $parts = explode('_', $questiondata['name']);
            $property = 'q' . $parts[2];
            if (!isset($processedresponses->{$property})) {
                $processedresponses->{$property} = [];
            }
            if (isset($parts[3])) {
                $cidx = $parts[3];
            } else {
                $cidx = 0;
            }
            $processedresponses->{$property}[$cidx] = $questiondata['value'];
        }

        foreach ($questions as $question) {
            if ($question->supports_responses()) {
                $response->answers[$question->id] = $question->responsetype::answers_from_appdata($processedresponses, $question);
            }
        }
        return $response;
    }

    /**
     * Add the answers to each question in a question array of answers structure.
     */
    public function add_questions_answers() {
        $this->answers = [];
        $this->answers += \mod_questionnaire\responsetype\multiple::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\responsetype\single::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\responsetype\rank::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\responsetype\boolean::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\responsetype\date::response_answers_by_question($this->id);
        $this->answers += \mod_questionnaire\responsetype\text::response_answers_by_question($this->id);
    }
}