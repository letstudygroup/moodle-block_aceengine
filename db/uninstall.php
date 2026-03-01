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
 * Uninstall handler for block_aceengine.
 *
 * Part of the ACE plugin family. This block displays the ACE Dashboard
 * and depends on the local_aceengine plugin for all data.
 *
 * @package    block_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall the block_aceengine plugin.
 *
 * @return bool True on success.
 */
function xmldb_block_aceengine_uninstall() {
    unset_all_config_for_plugin('block_aceengine');
    return true;
}
