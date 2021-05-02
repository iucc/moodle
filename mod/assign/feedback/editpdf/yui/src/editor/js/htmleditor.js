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
 * Provides an in browser PDF editor.
 *
 * @module moodle-assignfeedback_editpdf-editor
 */

/**
 * Class representing a list of reach text.
 *
 * @namespace M.assignfeedback_editpdf
 * @class htmleditor
 * @constructor
 * @extends M.core.dialogue
 */
var HTMLEDITOR = function(config) {
    config.draggable = true;
    config.centered = true;
    config.width = 'auto';
    config.visible = false;
    config.headerContent = M.util.get_string('htmleditor', 'assignfeedback_editpdf');
    config.footerContent = '';
    HTMLEDITOR.superclass.constructor.apply(this, [config]);
};

var HTMLEDITORNAME = "htmleditor";


Y.extend(HTMLEDITOR, M.core.dialogue, {
    /**
     * Initialise the menu.
     *
     * @method initializer
     * @return void
     */
    editor: null,
    initializer: function(config) {
        var editorr,
            container,
            textarea,
            bb;
        this.editor = config.editor || null;
        bb = this.get('boundingBox');
        bb.addClass('assignfeedback_editpdf_htmleditor');
        editorr = this.get('editor');
        container = Y.Node.create('<div/>');
        textarea = Y.one('#editorcontainer');
        textarea.removeClass('hidden');
        container.append(textarea);

        Y.one('[name="savechanges"]').on('click', this.removeeditor);
        Y.one('[name="saveandshownext"]').on('click', this.removeeditor);
        Y.one('[name="saveandshownext"]').on('click', this.removeeditor);
        // Set the body content.
        this.set('bodyContent', container);
        HTMLEDITOR.superclass.initializer.call(this, config);

    },
    removeeditor: function () {
        if (Y.one(".assignfeedback_editpdf_htmleditor")) {
            Y.one(".assignfeedback_editpdf_htmleditor").remove();
        }
    }
},{
    NAME: HTMLEDITORNAME,
    ATTRS: {
        /**
         * The editor this search window is attached to.
         *
         * @attribute editor
         * @type M.assignfeedback_editpdf.editor
         * @default null
         */
        editor: {
            value: null
        }

    }
});
Y.Base.modifyAttrs(HTMLEDITOR, {
    /**
     * Whether the widget should be modal or not.
     *
     * Moodle override: We override this for commentsearch to force it always true.
     *
     * @attribute Modal
     * @type Boolean
     * @default true
     */
    modal: {
        getter: function() {
            return true;
        }
    }
});
M.assignfeedback_editpdf = M.assignfeedback_editpdf || {};
M.assignfeedback_editpdf.htmleditor = HTMLEDITOR;
