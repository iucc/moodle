var HTMLEDITORNAME = "htmleditor",
    HTMLEDITOR;

/**
 * Provides an in browser PDF editor.
 *
 * @module moodle-assignfeedback_editpdf-editor
 */

/**
 * This is a searchable dialogue of comments.
 *
 * @namespace M.assignfeedback_editpdf
 * @class htmleditor
 * @constructor
 * @extends M.core.dialogue
 */
HTMLEDITOR = function(config) {
    config.draggable = true;
    config.centered = true;
    config.width = 'auto';
    config.visible = false;
    config.headerContent = M.util.get_string('htmleditor', 'assignfeedback_editpdf');
    config.footerContent = '';
    HTMLEDITOR.superclass.constructor.apply(this, [config]);
};

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
            bb,
            button;
        this.editor = config.editor || null;
        bb = this.get('boundingBox');
        bb.addClass('assignfeedback_editpdf_htmleditor');

        editorr = this.get('editor');
        container = Y.Node.create('<div/>');
        textarea = Y.one('#editorcontainer');
        textarea.removeClass('hidden');
        container.append(textarea);

        // Set the body content.
        this.set('bodyContent', container);
        HTMLEDITOR.superclass.initializer.call(this, config);

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
