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
 * This file contains the definition for the library class for edit PDF renderer.
 *
 * @package   assignfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the editpdf feedback plugin.
 *
 * @package assignfeedback_editpdf
 * @copyright 2013 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignfeedback_editpdf_renderer extends plugin_renderer_base {

    /**
     * Return the PDF button shortcut.
     *
     * @param string $name the name of a specific button.
     * @return string the specific shortcut.
     */
    private function get_shortcut($name) {

        $shortcuts = array('navigate-previous-button' => 'j',
            'rotateleft' => 'q',
            'rotateright' => 'w',
            'navigate-page-select' => 'k',
            'navigate-next-button' => 'l',
            'searchcomments' => 'h',
            'expcolcomments' => 'g',
            'comment' => 'z',
            'commentcolour' => 'x',
            'select' => 'c',
            'drag' => 'd',
            'pen' => 'y',
            'line' => 'u',
            'rectangle' => 'i',
            'oval' => 'o',
            'highlight' => 'p',
            'annotationcolour' => 'r',
            'stamp' => 'n',
            'currentstamp' => 'm');


        // Return the shortcut.
        return $shortcuts[$name];
    }

    /**
     * Render a single colour button.
     *
     * @param string $icon - The key for the icon
     * @param string $tool - The key for the lang string.
     * @param string $accesskey Optional - The access key for the button.
     * @param bool $disabled Optional - Is this button disabled.
     * @return string
     */
    private function render_toolbar_button($icon, $tool, $accesskey = null, $disabled=false) {

        // Build button alt text.
        $alttext = new stdClass();
        $alttext->tool = get_string($tool, 'assignfeedback_editpdf');
        if (!empty($accesskey)) {
            $alttext->shortcut = '(Alt/Shift-Alt/Ctrl-Option + ' . $accesskey . ')';
        } else {
            $alttext->shortcut = '';
        }
        $iconalt = get_string('toolbarbutton', 'assignfeedback_editpdf', $alttext);

        $iconhtml = $this->image_icon($icon, $iconalt, 'assignfeedback_editpdf');
        $iconparams = array('data-tool'=>$tool, 'class'=>$tool . 'button');
        if ($disabled) {
            $iconparams['disabled'] = 'true';
        }
        if (!empty($accesskey)) {
            $iconparams['accesskey'] = $accesskey;
        }

        return html_writer::tag('button', $iconhtml, $iconparams);
    }

    /**
     * Render the editpdf widget in the grading form.
     *
     * @param assignfeedback_editpdf_widget $widget - Renderable widget containing assignment, user and attempt number.
     * @return string
     */
    public function render_assignfeedback_editpdf_widget(assignfeedback_editpdf_widget $widget) {
        global $CFG, $USER;

        $html = '';

        $html .= html_writer::div(get_string('jsrequired', 'assignfeedback_editpdf'), 'hiddenifjs');
        $linkid = html_writer::random_id();
        if ($widget->readonly) {
            $launcheditorlink = html_writer::tag('a',
                                              get_string('viewfeedbackonline', 'assignfeedback_editpdf'),
                                              array('id'=>$linkid, 'class'=>'btn', 'href'=>'#'));
        } else {
            $launcheditorlink = html_writer::tag('a',
                                              get_string('launcheditor', 'assignfeedback_editpdf'),
                                              array('id'=>$linkid, 'class'=>'btn', 'href'=>'#'));
        }
        $links = $launcheditorlink;
        $html .= '<input type="hidden" name="assignfeedback_editpdf_haschanges" value="false"/>';

        $html .= html_writer::div($links, 'visibleifjs');
        $header = get_string('pluginname', 'assignfeedback_editpdf');
        $body = '';
        // Create the page navigation.
        $navigation1 = '';
        $navigation2 = '';
        $navigation3 = '';

        // Pick the correct arrow icons for right to left mode.
        if (right_to_left()) {
            $nav_prev = 'nav_next';
            $nav_next = 'nav_prev';
        } else {
            $nav_prev = 'nav_prev';
            $nav_next = 'nav_next';
        }

        $iconshortcut = $this->get_shortcut('navigate-previous-button');
        $iconalt = get_string('navigateprevious', 'assignfeedback_editpdf', $iconshortcut);
        $iconhtml = $this->image_icon($nav_prev, $iconalt, 'assignfeedback_editpdf');
        $navigation1 .= html_writer::tag('button', $iconhtml, array('disabled'=>'true',
            'class'=>'navigate-previous-button', 'accesskey' => $this->get_shortcut('navigate-previous-button')));
        $navigation1 .= html_writer::tag('select', null, array('disabled'=>'true',
            'aria-label' => get_string('gotopage', 'assignfeedback_editpdf'), 'class'=>'navigate-page-select',
            'accesskey' => $this->get_shortcut('navigate-page-select')));
        $iconshortcut = $this->get_shortcut('navigate-next-button');
        $iconalt = get_string('navigatenext', 'assignfeedback_editpdf', $iconshortcut);
        $iconhtml = $this->image_icon($nav_next, $iconalt, 'assignfeedback_editpdf');
        $navigation1 .= html_writer::tag('button', $iconhtml, array('disabled'=>'true',
            'class'=>'navigate-next-button', 'accesskey' => $this->get_shortcut('navigate-next-button')));

        $navigation1 = html_writer::div($navigation1, 'navigation', array('role'=>'navigation'));

        $navigation2 .= $this->render_toolbar_button('comment_search', 'searchcomments', $this->get_shortcut('searchcomments'));
        $navigation2 = html_writer::div($navigation2, 'navigation-search', array('role'=>'navigation'));

        $navigation3 .= $this->render_toolbar_button('comment_expcol', 'expcolcomments', $this->get_shortcut('expcolcomments'));
        $navigation3 = html_writer::div($navigation3, 'navigation-expcol', array('role' => 'navigation'));

        $rotationtools = '';
        if (!$widget->readonly) {
            $rotationtools .= $this->render_toolbar_button('rotate_left', 'rotateleft', $this->get_shortcut('rotateleft'));
            $rotationtools .= $this->render_toolbar_button('rotate_right', 'rotateright', $this->get_shortcut('rotateright'));
            $rotationtools = html_writer::div($rotationtools, 'toolbar', array('role' => 'toolbar'));
        }

        $toolbargroup = '';
        $clearfix = html_writer::div('', 'clearfix');
        if (!$widget->readonly) {
            // Comments.
            $toolbar1 = '';
            $toolbar1 .= $this->render_toolbar_button('comment', 'comment', $this->get_shortcut('comment'));
            $toolbar1 .= $this->render_toolbar_button('background_colour_clear', 'commentcolour', $this->get_shortcut('commentcolour'));
            $toolbar1 = html_writer::div($toolbar1, 'toolbar', array('role' => 'toolbar'));

            // Select Tool.
            $toolbar2 = '';
            $toolbar2 .= $this->render_toolbar_button('drag', 'drag', $this->get_shortcut('drag'));
            $toolbar2 .= $this->render_toolbar_button('select', 'select', $this->get_shortcut('select'));
            $toolbar2 = html_writer::div($toolbar2, 'toolbar', array('role' => 'toolbar'));

            // Other Tools.
            $toolbar3 = '';
            $toolbar3 .= $this->render_toolbar_button('pen', 'pen', $this->get_shortcut('pen'));
            $toolbar3 .= $this->render_toolbar_button('line', 'line', $this->get_shortcut('line'));
            $toolbar3 .= $this->render_toolbar_button('rectangle', 'rectangle', $this->get_shortcut('rectangle'));
            $toolbar3 .= $this->render_toolbar_button('oval', 'oval', $this->get_shortcut('oval'));
            $toolbar3 .= $this->render_toolbar_button('highlight', 'highlight', $this->get_shortcut('highlight'));
            $toolbar3 .= $this->render_toolbar_button('background_colour_clear', 'annotationcolour', $this->get_shortcut('annotationcolour'));
            $toolbar3 = html_writer::div($toolbar3, 'toolbar', array('role' => 'toolbar'));

            // Stamps.
            $toolbar4 = '';
            $toolbar4 .= $this->render_toolbar_button('stamp', 'stamp', $this->get_shortcut('stamp'));
            $toolbar4 .= $this->render_toolbar_button('background_colour_clear', 'currentstamp', $this->get_shortcut('currentstamp'));
            $toolbar4 = html_writer::div($toolbar4, 'toolbar', array('role'=>'toolbar'));

            // Html
            $toolbar5 = '';
            $toolbar5 .= $this->render_toolbar_button('math', 'htmleditor');
            $toolbar5 = html_writer::div($toolbar5, 'toolbar', array('role'=>'toolbar'));

            // Add toolbars to toolbar_group in order of display, and float the toolbar_group right.
            $toolbars = $rotationtools . $toolbar1 . $toolbar2 . $toolbar3 . $toolbar4 . $toolbar5;
            $toolbargroup = html_writer::div($toolbars, 'toolbar_group', array('role' => 'toolbar_group'));
        }

        $pageheader = html_writer::div($navigation1 .
                                       $navigation2 .
                                       $navigation3 .
                                       $toolbargroup .
                                       $clearfix,
                                       'pageheader');
        $body = $pageheader;

        // Loading progress bar.
        $progressbar = html_writer::div('', 'bar', array('style' => 'width: 0%'));
        $progressbar = html_writer::div($progressbar, 'progress progress-info progress-striped active',
            array('title' => get_string('loadingeditor', 'assignfeedback_editpdf'),
                  'role'=> 'progressbar', 'aria-valuenow' => 0, 'aria-valuemin' => 0,
                  'aria-valuemax' => 100));
        $progressbarlabel = html_writer::div(get_string('generatingpdf', 'assignfeedback_editpdf'),
            'progressbarlabel');
        $loading = html_writer::div($progressbar . $progressbarlabel, 'loading');

        $canvas = html_writer::div($loading, 'drawingcanvas');
        $canvas = html_writer::div($canvas, 'drawingregion');
        // Place for messages, but no warnings displayed yet.
        $changesmessage = html_writer::div('', 'warningmessages');
        $canvas .= $changesmessage;

        $infoicon = $this->image_icon('i/info', '');
        $infomessage = html_writer::div($infoicon, 'infoicon');
        $canvas .= $infomessage;

        $body .= $canvas;
        $textarea = html_writer::tag('textarea', '',["id" => "html_editor",
                "class" => "editor", "rows" => "20", "cols" => "50"] );
        $addbutton = html_writer::tag('button', get_string('add', 'assignfeedback_editpdf'),
                ["class" => "addhtml"]);
        $textcontainer =  html_writer::tag('div', $textarea.$addbutton);
        $body .= html_writer::tag('div', $textcontainer, ["id" => "editorcontainer", "class" => "hidden"]);
        $footer = '';

        $editorparams = array(
            array(
                'header' => $header,
                'body' => $body,
                'footer' => $footer,
                'linkid' => $linkid,
                'assignmentid' => $widget->assignment,
                'userid' => $widget->userid,
                'attemptnumber' => $widget->attemptnumber,
                'stampfiles' => $widget->stampfiles,
                'readonly' => $widget->readonly,
            )
        );

        $this->page->requires->yui_module('moodle-assignfeedback_editpdf-editor',
                                          'M.assignfeedback_editpdf.editor.init',
                                          $editorparams);

        $this->page->requires->strings_for_js(array(
            'yellow',
            'white',
            'red',
            'blue',
            'green',
            'black',
            'clear',
            'colourpicker',
            'loadingeditor',
            'pagexofy',
            'deletecomment',
            'addtoquicklist',
            'filter',
            'searchcomments',
            'commentcontextmenu',
            'deleteannotation',
            'stamp',
            'stamppicker',
            'cannotopenpdf',
            'pagenumber',
            'partialwarning',
            'draftchangessaved',
            'add',
            'htmleditor'
        ), 'assignfeedback_editpdf');



        $textareaid = 'html_editor';
        $context = \context_user::instance($USER->id);
        require_capability('mod/assign:grade', $context);
        $enabled = editors_get_enabled();
        $editor = array_key_first($enabled);

        $options = array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 0, 'changeformat' => 0,
                'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED, 'context' => null, 'noclean' => 0, 'trusttext' => 0,
                'return_types' => 15, 'enable_filemanagement' => true, 'removeorphaneddrafts' => false, 'autosave' => true);
        if ($editor == 'tinymce') {
            $tinymce = new \tinymce_texteditor();
            if ($CFG->debugdeveloper) {
                $this->page->requires->js(new \moodle_url('/lib/editor/tinymce/tiny_mce/'.$tinymce->version.'/tiny_mce_src.js'));
            } else {
                $this->page->requires->js(new \moodle_url('/lib/editor/tinymce/tiny_mce/'.$tinymce->version.'/tiny_mce.js'));
            }
            $this->page->requires->js_init_call('M.editor_tinymce.init_editor',
                    array($textareaid, $this->get_init_params_tinymcel($textareaid, $options)), true);
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
            $options['context'] = empty($options['context']) ? \context_system::instance() : $options['context'];

            $jsplugins = array();
            foreach ($groups as $group => $plugins) {
                $groupplugins = array();
                foreach ($plugins as $plugin) {
                    // Do not die on missing plugin.
                    if (!\core_component::get_component_directory('atto_' . $plugin))  {
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
                    $this->page->requires->string_for_js('pluginname', 'atto_' . $plugin);
                    $groupplugins[] = $jsplugin;
                }
                $jsplugins[] = array('group'=>$group, 'plugins'=>$groupplugins);
            }

            $this->page->requires->strings_for_js(array(
                    'editor_command_keycode',
                    'editor_control_keycode',
                    'plugin_title_shortcut',
                    'textrecovered',
                    'autosavefailed',
                    'autosavesucceeded',
                    'errortextrecovery'
            ), 'editor_atto');
            $this->page->requires->strings_for_js(array(
                    'warning',
                    'info'
            ), 'moodle');
            $this->page->requires->yui_module($modules,
                    'Y.M.editor_atto.Editor.init',
                    array($this->get_init_params_atto($textareaid, $options, null, $jsplugins)));

        }

        return $html;
    }

    function get_init_params_tinymcel($elementid, array $options=null) {
        global $CFG, $PAGE, $USER;
        $PAGE->set_context(\context_system::instance());
        //TODO: we need to implement user preferences that affect the editor setup too
        $context = \context_user::instance($USER->id);
        require_capability('mod/assign:grade', $context);
        $directionality = get_string('thisdirection', 'langconfig');
        $strtime        = get_string('strftimetime');
        $strdate        = get_string('strftimedaydate');
        $lang           = current_language();
        $contentcss     = $PAGE->theme->editor_css_url()->out(false);
        $context = empty($options['context']) ? \context_system::instance() : $options['context'];

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
        if (!empty($config->customtoolbar) and $customtoolbar = $this->parse_toolbar_setting($config->customtoolbar)) {
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
        \editor_tinymce_plugin::all_update_init_params($params, $context, $options);

        // Remove temporary parameters.
        unset($params['moodle_config']);

        return $params;
    }
    /**
     * Parse the custom toolbar setting.
     * @param string $customtoolbar
     * @return array csv toolbar lines
     */
    function parse_toolbar_setting($customtoolbar) {
        $result = array();
        $customtoolbar = trim($customtoolbar);
        if ($customtoolbar === '') {
            return $result;
        }
        $customtoolbar = str_replace("\r", "\n", $customtoolbar);
        $customtoolbar = strtolower($customtoolbar);
        $i = 0;
        foreach (explode("\n", $customtoolbar) as $line) {
            $line = preg_replace('/[^a-z0-9_,\|\-]/', ',', $line);
            $line = str_replace('|', ',|,', $line);
            $line = preg_replace('/,,+/', ',', $line);
            $line = trim($line, ',|');
            if ($line === '') {
                continue;
            }
            if ($i == 10) {
                // Maximum is ten lines, merge the rest to the last line.
                $result[9] = $result[9].','.$line;
            } else {
                $result[] = $line;
                $i++;
            }
        }
        return $result;
    }

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
}
