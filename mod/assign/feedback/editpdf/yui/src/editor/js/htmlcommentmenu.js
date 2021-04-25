var HTMLCOMMENTMENUNAME = "Htmlcommentmenu",
    HTMLCOMMENTMENU;

/**
 * Provides an in browser PDF editor.
 *
 * @module moodle-assignfeedback_editpdf-editor
 */

/**
 * HTMLCOMMENTMENU
 * This is a drop down list of comment context functions.
 *
 * @namespace M.assignfeedback_editpdf
 * @class commentmenu
 * @constructor
 * @extends M.assignfeedback_editpdf.dropdown
 */
HTMLCOMMENTMENU = function(config) {
    HTMLCOMMENTMENU.superclass.constructor.apply(this, [config]);
};

Y.extend(HTMLCOMMENTMENU, M.assignfeedback_editpdf.dropdown, {

    /**
     * Initialise the menu.
     *
     * @method initializer
     * @return void
     */
    initializer: function(config) {
        var htmlcommentlinks,
            link,
            body,
            htmlcomment;

        htmlcomment = this.get('htmlcomment');
        // Build the list of menu items.
        htmlcommentlinks = Y.Node.create('<ul role="menu" class="assignfeedback_editpdf_menu"/>');

        link = Y.Node.create('<li><a tabindex="-1" href="#">' +
               M.util.get_string('edithtml', 'assignfeedback_editpdf') +
               '</a></li>');
        link.on('click', htmlcomment.edit_htmlcomment, htmlcomment);
        link.on('key', htmlcomment.edit_htmlcomment, 'enter,space', htmlcomment);

        htmlcommentlinks.append(link);

        link = Y.Node.create('<li><a tabindex="-1" href="#">' +
               M.util.get_string('deletecomment', 'assignfeedback_editpdf') +
               '</a></li>');
        link.on('click', function(e) {
            e.preventDefault();
            this.menu.hide();
            this.remove();
        }, htmlcomment);

        link.on('key', function() {
            htmlcomment.menu.hide();
            htmlcomment.remove();
        }, 'enter,space', htmlcomment);

        htmlcommentlinks.append(link);

        link = Y.Node.create('<li><hr/></li>');
        htmlcommentlinks.append(link);

        // Set the accessible header text.
        this.set('headerText', M.util.get_string('commentcontextmenu', 'assignfeedback_editpdf'));

        body = Y.Node.create('<div/>');

        // Set the body content.
        body.append(htmlcommentlinks);
        this.set('bodyContent', body);

        HTMLCOMMENTMENU.superclass.initializer.call(this, config);
    }
}, {
    NAME: HTMLCOMMENTMENUNAME,
    ATTRS: {
        /**
         * The comment this menu is attached to.
         *
         * @attribute comment
         * @type M.assignfeedback_editpdf.comment
         * @default null
         */
        htmlcomment: {
            value: null
        }

    }
});

M.assignfeedback_editpdf = M.assignfeedback_editpdf || {};
M.assignfeedback_editpdf.htmlcommentmenu = HTMLCOMMENTMENU;
