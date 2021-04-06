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
 * ci comment created event.
 *
 * @package    ci
 * @copyright  2021 SysBind Ltd. <service@sysbind.co.il>
 * @auther     avi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../local/ci/phplib/clilib.php');

// now get cli options
list($options, $unrecognized) = cli_get_params(array(
    'help'   => false,
    'basedir' => '',
    'absolute'=> 'true',
    'pathtochangedfiles' => ''),
    array(
        'h' => 'help',
        'b' => 'basedir',
        'a' => 'absolute',
        'p' => 'pathtochangedfiles',
    ));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unrecognised options:\n{$unrecognized}\n Please use --help option.");
}

if ($options['help']) {
    $help =
        "Generate a list of valid components for a given Moodle's root directory.

Options:
-h, --help            Print out this help.
--basedir             Full path to the moodle base dir to look for components.
--absolute            Return absolute (true, default) or relative (false) paths.
--pathtochangedfiles  The pathe to text file where each line is changed file

Example:
\$sudo -u www-data /usr/bin/php local/ci/list_valid_components/list_valid_components.php --basedir=/home/moodle/git --absoulte=false --pathtochangedfiles=php_files
";

    echo $help;
    exit(0);
}

if (empty($options['basedir'])) {
    cli_error('Missing basedir param. Please use --help option.');
}
if (!file_exists($options['basedir'])) {
    cli_error('Incorrect directory: ' . $options['basedir']);
}
if (!is_writable($options['basedir'])) {
    cli_error('Non-writable directory: ' . $options['basedir']);
}
if (!file_exists($options['pathtochangedfiles'])) {
    cli_error('Incorrect file: ' . $options['pathtochangedfiles']);
}
$changedfiles = file($options['pathtochangedfiles'], FILE_IGNORE_NEW_LINES);
if ($options['absolute'] == 'true') {
    $options['absolute'] = true;
} else if ($options['absolute'] == 'false') {
    $options['absolute'] = false;
}

if (!is_bool($options['absolute'])) {
    cli_error('Incorrect absolute value, bool expected: ' . $options['absolute']);
}
$components = '';
// For Moodle 2.6 and upwards, we execute this specific code that does not require
// the site to be installed (relying on new classes only available since then).
if (load_core_component_from_moodle($options['basedir'])) {
    // Get all the plugins and subplugin types.
    $types = core_component::get_plugin_types();
    // Sort types in reverse order, so we get subplugins earlier than plugins.
    $types = array_reverse($types);
    // For each type, get their available implementations.
    foreach ($types as $type => $fullpath) {
        $plugins = core_component::get_plugin_list($type);
        // For each plugin, let's calculate the proper component name and output it.
        foreach ($plugins as $plugin => $pluginpath) {
            $exist = false;
            foreach ($changedfiles as $file) {
                if(strpos($file, $pluginpath) !== false) {
                    $exist = true;
                    break;
                }
            }
            if ($exist) {
                $component = $type . '_' . $plugin;
                if (!$options['absolute']) {
                    $pluginpath = str_replace($options['basedir'] . '/', '', $pluginpath);
                }

                $components .= $component . '_testsuite,';
            }
        }
    }
    $components = trim($components, ',');
    $report = $options['basedir']. "/changed_plugins";
    if (file_exists($report)) {
        unlink($report);
    }
    file_put_contents($options['basedir']. "/changed_plugins", $components);
    exit(0);
}
