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
 * Block definition for block_ace.
 *
 * Embeds the ACE dashboard inside a course or on the user's My page.
 *
 * @package    block_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * ACE dashboard block.
 *
 * Displays gamification stats (XP, levels, engagement scores, quests)
 * from the local_ace plugin on course pages and the user dashboard.
 *
 * @package    block_ace
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ace extends block_base {
    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_ace');
    }

    /**
     * Get the block content.
     *
     * @return stdClass The block content.
     */
    public function get_content() {
        global $USER, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        if (!get_config('local_ace', 'enableplugin')) {
            return $this->content;
        }

        $courseid = $this->page->course->id;
        $issitecontext = ($courseid == SITEID);

        if ($issitecontext) {
            return $this->get_my_dashboard_content();
        }

        return $this->get_course_dashboard_content();
    }

    /**
     * Build content for the course-level dashboard.
     *
     * @return stdClass The block content.
     */
    private function get_course_dashboard_content(): stdClass {
        global $USER, $COURSE, $OUTPUT, $CFG;

        require_once($CFG->dirroot . '/local/ace/lib.php');

        if (!local_ace_is_enabled_for_course($COURSE->id)) {
            return $this->content;
        }

        $context = context_course::instance($COURSE->id);
        if (!has_capability('local/ace:viewdashboard', $context)) {
            return $this->content;
        }

        // Load dashboard data and render.
        $dashboard = new \local_ace\output\dashboard($USER->id, $COURSE->id);
        $renderer = $this->page->get_renderer('local_ace');
        $this->content->text = $renderer->render_from_template(
            'local_ace/dashboard',
            $dashboard->export_for_template($OUTPUT)
        );

        // Initialise JS for quest completion.
        $this->page->requires->js_call_amd('local_ace/dashboard', 'init', [$COURSE->id]);

        // Add a footer link to the full dashboard page.
        $url = new moodle_url('/local/ace/index.php', ['courseid' => $COURSE->id]);
        $this->content->footer = html_writer::link(
            $url,
            get_string('viewfulldashboard', 'block_ace'),
            ['class' => 'btn btn-outline-primary btn-sm btn-block mt-2']
        );

        return $this->content;
    }

    /**
     * Build content for the My page (site dashboard) - summary across all courses.
     *
     * @return stdClass The block content.
     */
    private function get_my_dashboard_content(): stdClass {
        global $USER, $DB, $CFG;

        require_once($CFG->dirroot . '/local/ace/lib.php');

        $enrolledcourses = enrol_get_users_courses($USER->id, true, 'id, fullname, shortname');

        $courses = [];
        $totalxp = 0;
        $highestlevel = 1;
        $totalengagement = 0;
        $totalmastery = 0;
        $coursecount = 0;
        $totalactivequests = 0;
        $totalcompleted = 0;

        foreach ($enrolledcourses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            if (!local_ace_is_enabled_for_course($course->id)) {
                continue;
            }

            $context = context_course::instance($course->id, IGNORE_MISSING);
            if (!$context || !has_capability('local/ace:viewdashboard', $context)) {
                continue;
            }

            // Load XP/level.
            $xprecord = $DB->get_record('local_ace_xp', [
                'userid' => $USER->id,
                'courseid' => $course->id,
            ]);
            $xp = $xprecord ? (int) $xprecord->xp : 0;
            $level = $xprecord ? (int) $xprecord->level : 1;

            // Load scores.
            $engagement = $DB->get_record('local_ace_engagement', [
                'userid' => $USER->id,
                'courseid' => $course->id,
            ]);
            $engscore = $engagement ? round((float) $engagement->score) : 0;

            $mastery = $DB->get_record('local_ace_mastery', [
                'userid' => $USER->id,
                'courseid' => $course->id,
            ]);
            $mastscore = $mastery ? round((float) $mastery->score) : 0;

            // Count active quests.
            $activequests = $DB->count_records('local_ace_quests', [
                'userid' => $USER->id,
                'courseid' => $course->id,
                'status' => 'active',
            ]);

            // Count completed quests.
            $completed = $DB->count_records('local_ace_quests', [
                'userid' => $USER->id,
                'courseid' => $course->id,
                'status' => 'completed',
            ]);

            $totalxp += $xp;
            if ($level > $highestlevel) {
                $highestlevel = $level;
            }
            $totalengagement += $engscore;
            $totalmastery += $mastscore;
            $totalactivequests += $activequests;
            $totalcompleted += $completed;
            $coursecount++;

            $courses[] = [
                'courseid' => $course->id,
                'coursename' => format_string($course->fullname),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'dashboardurl' => (new moodle_url('/local/ace/index.php', ['courseid' => $course->id]))->out(false),
                'xp' => $xp,
                'level' => $level,
                'engagement' => $engscore,
                'mastery' => $mastscore,
                'activequests' => $activequests,
                'completed' => $completed,
                'hasactivequests' => $activequests > 0,
            ];
        }

        $avgengagement = $coursecount > 0 ? round($totalengagement / $coursecount) : 0;
        $avgmastery = $coursecount > 0 ? round($totalmastery / $coursecount) : 0;

        $data = [
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'totalxp' => $totalxp,
            'highestlevel' => $highestlevel,
            'avgengagement' => $avgengagement,
            'avgmastery' => $avgmastery,
            'totalactivequests' => $totalactivequests,
            'totalcompleted' => $totalcompleted,
            'coursecount' => $coursecount,
        ];

        $renderer = $this->page->get_renderer('core');
        $this->content->text = $renderer->render_from_template('block_ace/my_dashboard', $data);

        return $this->content;
    }

    /**
     * This block is applicable to course pages and the My page.
     *
     * @return array The applicable formats.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'my' => true,
        ];
    }

    /**
     * Allow only one instance of this block per page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * This block uses global config.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }
}
