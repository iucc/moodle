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
 * External assignfeedback editpdf API
 * @package    assignfeedback_editpdf
 * @since      Moodle 3.10
 * @copyright  2021 SysBind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Assignfeedback editpdf functions
 * @copyright 2021 SysBind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignfeedback_editpdf_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function htmleditor_parameters() {
        // htmleditor_parameters() always return an external_function_parameters().
        // The external_function_parameters constructor expects an array of external_description.
        return new external_function_parameters(
        // a external_description can be: external_value, external_single_structure or external_multiple structure
                array('textareaid' => new external_value(PARAM_INT, 'The textarea id'))
        );
    }

    /**
     * Htmleditor return the editor html
     * @return string welcome message
     */
    public static function htmleditor($textareaid) {
        global $USER, $PAGE, $CFG;
        //Parameters validation
        $params = self::validate_parameters(self::htmleditor_parameters(),
                array('textareaid' => $textareaid));
        $textareaid = $params['textareaid'];
        $context = context_user::instance($USER->id);
        require_capability('mod/assign:grade', $context);
        $enabled = editors_get_enabled();
        $editor = array_key_first($enabled);

        function get_init_params_atto($elementid, array $options = null, array $fpoptions = null, $plugins = null) {
            global $PAGE;

            $directionality = get_string('thisdirection', 'langconfig');
            $strtime        = get_string('strftimetime');
            $strdate        = get_string('strftimedaydate');
            $lang           = current_language();
            $autosave       = true;
            $autosavefrequency = get_config('editor_atto', 'autosavefrequency');
            if (isset($options['autosave'])) {
                $autosave       = $options['autosave'];
            }
            $contentcss     = $PAGE->theme->editor_css_url()->out(false);

            // Autosave disabled for guests and not logged in users.
            if (isguestuser() OR !isloggedin()) {
                $autosave = false;
            }
            // Note <> is a safe separator, because it will not appear in the output of s().
            $pagehash = sha1($PAGE->url . '<>' );
            $params = array(
                    'elementid' => $elementid,
                    'content_css' => $contentcss,
                    'contextid' => $options['context']->id,
                    'autosaveEnabled' => $autosave,
                    'autosaveFrequency' => $autosavefrequency,
                    'language' => $lang,
                    'directionality' => $directionality,
                    'filepickeroptions' => array(),
                    'plugins' => $plugins,
                    'pageHash' => $pagehash,
            );
            if ($fpoptions) {
                $params['filepickeroptions'] = $fpoptions;
            }
            return $params;
        }

        function get_init_params_tinymcel($elementid, array $options=null) {
            global $CFG, $PAGE, $OUTPUT;

            //TODO: we need to implement user preferences that affect the editor setup too

            $directionality = get_string('thisdirection', 'langconfig');
            $strtime        = get_string('strftimetime');
            $strdate        = get_string('strftimedaydate');
            $lang           = current_language();
            $contentcss     = $PAGE->theme->editor_css_url()->out(false);

            $context = empty($options['context']) ? context_system::instance() : $options['context'];

            $config = get_config('editor_tinymce');
            if (!isset($config->disabledsubplugins)) {
                $config->disabledsubplugins = '';
            }

            // Remove the manage files button if requested.
            if (isset($options['enable_filemanagement']) && !$options['enable_filemanagement']) {
                if (!strpos($config->disabledsubplugins, 'managefiles')) {
                    $config->disabledsubplugins .= ',managefiles';
                }
            }

            $fontselectlist = empty($config->fontselectlist) ? '' : $config->fontselectlist;

            $langrev = -1;
            if (!empty($CFG->cachejs)) {
                $langrev = get_string_manager()->get_revision();
            }

            $params = array(
                    'moodle_config' => $config,
                    'mode' => "exact",
                    'elements' => $elementid,
                    'relative_urls' => false,
                    'document_base_url' => $CFG->wwwroot,
                    'moodle_plugin_base' => "$CFG->wwwroot/lib/editor/tinymce/plugins/",
                    'content_css' => $contentcss,
                    'language' => $lang,
                    'directionality' => $directionality,
                    'plugin_insertdate_dateFormat ' => $strdate,
                    'plugin_insertdate_timeFormat ' => $strtime,
                    'theme' => "advanced",
                    'skin' => "moodle",
                    'apply_source_formatting' => true,
                    'remove_script_host' => false,
                    'entity_encoding' => "raw",
                    'plugins' => 'lists,table,style,layer,advhr,advlink,emotions,inlinepopups,' .
                            'searchreplace,paste,directionality,fullscreen,nonbreaking,contextmenu,' .
                            'insertdatetime,save,iespell,preview,print,noneditable,visualchars,' .
                            'xhtmlxtras,template,pagebreak',
                    'gecko_spellcheck' => true,
                    'theme_advanced_font_sizes' => "1,2,3,4,5,6,7",
                    'theme_advanced_layout_manager' => "SimpleLayout",
                    'theme_advanced_toolbar_align' => "left",
                    'theme_advanced_fonts' => $fontselectlist,
                    'theme_advanced_resize_horizontal' => true,
                    'theme_advanced_resizing' => true,
                    'theme_advanced_resizing_min_height' => 30,
                    'min_height' => 30,
                    'theme_advanced_toolbar_location' => "top",
                    'theme_advanced_statusbar_location' => "bottom",
                    'language_load' => false, // We load all lang strings directly from Moodle.
                    'langrev' => $langrev,
            );

            // Should we override the default toolbar layout unconditionally?
            if (!empty($config->customtoolbar) and $customtoolbar = self::parse_toolbar_setting($config->customtoolbar)) {
                $i = 1;
                foreach ($customtoolbar as $line) {
                    $params['theme_advanced_buttons'.$i] = $line;
                    $i++;
                }
            } else {
                // At least one line is required.
                $params['theme_advanced_buttons1'] = '';
            }

            if (!empty($config->customconfig)) {
                $config->customconfig = trim($config->customconfig);
                $decoded = json_decode($config->customconfig, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k=>$v) {
                        $params[$k] = $v;
                    }
                }
            }

            if (!empty($options['legacy']) or !empty($options['noclean']) or !empty($options['trusted'])) {
                // now deal somehow with non-standard tags, people scream when we do not make moodle code xtml strict,
                // but they scream even more when we strip all tags that are not strict :-(
                $params['valid_elements'] = 'script[src|type],*[*]'; // for some reason the *[*] does not inlcude javascript src attribute MDL-25836
                $params['invalid_elements'] = '';
            }
            // Add unique moodle elements - unfortunately we have to decide if these are SPANs or DIVs.
            $params['extended_valid_elements'] = 'nolink,tex,algebra,lang[lang]';
            $params['custom_elements'] = 'nolink,~tex,~algebra,lang';

            //Add onblur event for client side text validation
            if (!empty($options['required'])) {
                $params['init_instance_callback'] = 'M.editor_tinymce.onblur_event';
            }

            // Allow plugins to adjust parameters.
            editor_tinymce_plugin::all_update_init_params($params, $context, $options);

            // Remove temporary parameters.
            unset($params['moodle_config']);

            return $params;
        }

        $options = array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 0, 'changeformat' => 0,
                'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED, 'context' => null, 'noclean' => 0, 'trusttext' => 0,
                'return_types' => 15, 'enable_filemanagement' => true, 'removeorphaneddrafts' => false, 'autosave' => true);
        if ($editor == 'tinymce') {
            $tinymce = new tinymce_texteditor();
            if ($CFG->debugdeveloper) {
                $PAGE->requires->js(new moodle_url('/lib/editor/tinymce/tiny_mce/'.$tinymce->version.'/tiny_mce_src.js'));
            } else {
                $PAGE->requires->js(new moodle_url('/lib/editor/tinymce/tiny_mce/'.$tinymce->version.'/tiny_mce.js'));
            }
            $PAGE->requires->js_init_call('M.editor_tinymce.init_editor',
                    array($textareaid, get_init_params_tinymcel($textareaid, $options)), true);
        } else {
            if (array_key_exists('atto:toolbar', $options)) {
                $configstr = $options['atto:toolbar'];
            } else {
                $configstr = get_config('editor_atto', 'toolbar');
            }

            $grouplines = explode("\n", $configstr);

            $groups = array();

            foreach ($grouplines as $groupline) {
                $line = explode('=', $groupline);
                if (count($line) > 1) {
                    $group = trim(array_shift($line));
                    $plugins = array_map('trim', explode(',', array_shift($line)));
                    $groups[$group] = $plugins;
                }
            }

            $modules = array('moodle-editor_atto-editor');
            $options['context'] = empty($options['context']) ? context_system::instance() : $options['context'];

            $jsplugins = array();
            foreach ($groups as $group => $plugins) {
                $groupplugins = array();
                foreach ($plugins as $plugin) {
                    // Do not die on missing plugin.
                    if (!core_component::get_component_directory('atto_' . $plugin))  {
                        continue;
                    }

                    // Remove manage files if requested.
                    if ($plugin == 'managefiles' && isset($options['enable_filemanagement']) && !$options['enable_filemanagement']) {
                        continue;
                    }

                    $jsplugin = array();
                    $jsplugin['name'] = $plugin;
                    $jsplugin['params'] = array();
                    $modules[] = 'moodle-atto_' . $plugin . '-button';

                    component_callback('atto_' . $plugin, 'strings_for_js');
                    $extra = component_callback('atto_' . $plugin, 'params_for_js',
                            array($textareaid, $options, null));

                    if ($extra) {
                        $jsplugin = array_merge($jsplugin, $extra);
                    }
                    // We always need the plugin name.
                    $PAGE->requires->string_for_js('pluginname', 'atto_' . $plugin);
                    $groupplugins[] = $jsplugin;
                }
                $jsplugins[] = array('group'=>$group, 'plugins'=>$groupplugins);
            }

            $PAGE->requires->strings_for_js(array(
                    'editor_command_keycode',
                    'editor_control_keycode',
                    'plugin_title_shortcut',
                    'textrecovered',
                    'autosavefailed',
                    'autosavesucceeded',
                    'errortextrecovery'
            ), 'editor_atto');
            $PAGE->requires->strings_for_js(array(
                    'warning',
                    'info'
            ), 'moodle');
            $PAGE->requires->yui_module($modules,
                    'Y.M.editor_atto.Editor.init',
                    array(get_init_params_atto($textareaid, $options, null, $jsplugins)));

        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function htmleditor_returns() {
        return new external_value();
    }
}




