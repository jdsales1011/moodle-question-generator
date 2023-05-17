<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_questiongenerator
 * @copyright   2023 Jivielyn Sales <jivielyn.sales@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_questiongenerator';
$plugin->release = '0.1.0';
$plugin->version = 2023030800;
$plugin->requires = 2020061500;
$plugin->maturity = MATURITY_ALPHA;

// $plugin->dependencies = array(
//     'block_repo_filemanager' => ANY_VERSION
// );

// Create temporary directory with restricted access
$tempdir = __DIR__.'/temp/';
if (!file_exists($tempdir)) {
    mkdir($tempdir, 0700, true);
}
