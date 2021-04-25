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
 * Class representing a list of htmlcomments.
 *
 * @namespace M.assignfeedback_editpdf
 * @class htmlcomment
 * @param M.assignfeedback_editpdf.editor editor
 * @param Int gradeid
 * @param Int pageno
 * @param Int x
 * @param Int y
 * @param Int width
 * @param String colour
 * @param String rawtext
 */
var HTMLCOMMENT = function(editor, gradeid, pageno, x, y, width, colour, rawtext) {

    /**
     * Reference to M.assignfeedback_editpdf.editor.
     * @property editor
     * @type M.assignfeedback_editpdf.editor
     * @public
     */
    this.editor = editor;

    /**
     * Grade id
     * @property gradeid
     * @type Int
     * @public
     */
    this.gradeid = gradeid || 0;

    /**
     * X position
     * @property x
     * @type Int
     * @public
     */
    this.x = parseInt(x, 10) || 0;

    /**
     * Y position
     * @property y
     * @type Int
     * @public
     */
    this.y = parseInt(y, 10) || 0;

    /**
     * Htmlcomment width
     * @property width
     * @type Int
     * @public
     */
    this.width = parseInt(width, 10) || 0;

    /**
     * Htmlcomment rawtext
     * @property rawtext
     * @type String
     * @public
     */
    this.rawtext = rawtext || '';

    /**
     * Htmlcomment page number
     * @property pageno
     * @type Int
     * @public
     */
    this.pageno = pageno || 0;

    /**
     * Htmlcomment background colour.
     * @property colour
     * @type String
     * @public
     */
    this.colour = colour || 'yellow';

    /**
     * Reference to M.assignfeedback_editpdf.drawable
     * @property drawable
     * @type M.assignfeedback_editpdf.drawable
     * @public
     */
    this.drawable = false;

    /**
     * Boolean used by a timeout to delete empty htmlcomments after a short delay.
     * @property deleteme
     * @type Boolean
     * @public
     */
    this.deleteme = false;

    /**
     * Reference to the link that opens the menu.
     * @property menulink
     * @type Y.Node
     * @public
     */
    this.menulink = null;

    /**
     * Reference to the dialogue that is the context menu.
     * @property menu
     * @type M.assignfeedback_editpdf.dropdown
     * @public
     */
    this.menu = null;

    /**
     * Clean a htmlcomment record, returning an oject with only fields that are valid.
     * @public
     * @method clean
     * @return {}
     */
    this.clean = function() {
        return {
            gradeid: this.gradeid,
            x: parseInt(this.x, 10),
            y: parseInt(this.y, 10),
            width: parseInt(this.width, 10),
            rawtext: this.rawtext,
            pageno: parseInt(this.pageno, 10),
            colour: this.colour
        };
    };

    /**
     * Draw a htmlcomment.
     * @public
     * @method draw_htmlcomment
     * @return M.assignfeedback_editpdf.drawable
     */
    this.draw = function() {
        var drawable = new M.assignfeedback_editpdf.drawable(this.editor),
            node,
            drawingcanvas = this.editor.get_dialogue_element(SELECTOR.DRAWINGCANVAS),
            container,
            menu,
            position,
            scrollheight,
            textarea;
        menu = Y.Node.create('<a href="#"><img src="' + M.util.image_url('t/contextmenu', 'core') + '"/></a>');
        // // Lets add a contenteditable div.
        node = Y.Node.create('<div/>');
        node.addClass('htmlcomment');
        container = Y.Node.create('<div class="htmlcommentdrawable"/>');
        this.menulink = menu;
        if (this.rawtext.replace(/^\s+|\s+$/g, "") === '') {
            textarea = Y.one('#html_editor');
            this.rawtext = textarea.get('value');
        }
        menu.setAttribute('tabindex', '0');
        if (!this.editor.get('readonly')) {
            container.append(menu);
        } else {
            node.setAttribute('readonly', 'readonly');
        }
        if (this.width < 100) {
            this.width = 100;
        }
        node.set('innerHTML', this.rawtext);
        Y.use('mathjax', function() {
            MathJax.Hub.Queue(["Typeset", MathJax.Hub, node.getDOMNode()]);
        });
        scrollheight = node.get('scrollHeight');
        node.setStyles({
            'height': scrollheight + 'px'
        });
        position = this.editor.get_window_coordinates(new M.assignfeedback_editpdf.point(this.x, this.y));
        node.setStyles({
            width: this.width + 'px'
        });
        container.append(node);
        drawingcanvas.append(container);
        container.setStyle('position', 'absolute');
        container.setX(position.x);
        container.setY(position.y);
        drawable.store_position(container, position.x, position.y);

        // Bind events only when editing.
        if (!this.editor.get('readonly')) {
            // Pass through the event handlers on the div.
            node.on('gesturemovestart', this.editor.edit_start, null, this.editor);
            node.on('gesturemove', this.editor.edit_move, null, this.editor);
            node.on('gesturemoveend', this.editor.edit_end, null, this.editor);
        }
        drawable.nodes.push(container);

        this.attach_events(node, menu);
        this.drawable = drawable;

        node.focus();
        this.width = parseInt(node.getStyle('width'), 10);

        // Trim.
        if (this.rawtext.replace(/^\s+|\s+$/g, "") === '') {
            // Delete empty htmlcomments.
            this.deleteme = true;
            Y.later(400, this, this.delete_htmlcomment_later);
            Y.one('#html_editoreditable').set('innerHTML', ' ');
            this.editor.htmleditorwindow.show();
        }
        node.active = false;
        if (this.rawtext.replace(/^\s+|\s+$/g, "") !== '') {
            this.editor.save_current_page();
            this.drawable = drawable;
            if (textarea) {
                textarea.set('value', ' ');
                Y.one('#html_editoreditable').set('innerHTML', ' ');
            }
        }
        return drawable;
    };

    /**
     * Delete an empty htmlcomment if it's menu hasn't been opened in time.
     * @method delete_htmlcomment_later
     */
    this.delete_htmlcomment_later = function() {
        if (this.deleteme) {
            this.remove();
        }
    };

    /**
     * Htmlomment nodes have a bunch of event handlers attached to them directly.
     * This is all done here for neatness.
     *
     * @protected
     * @method attach_htmlcomment_events
     * @param node - The Y.Node representing the htmlcomment.
     * @param menu - The Y.Node representing the menu.
     */
    this.attach_events = function(node, menu) {
        var container = node.ancestor('div');

        if (!this.editor.get('readonly')) {
            // For delegated event handler.
            menu.setData('htmlcomment', this);

            node.on('gesturemovestart', function(e) {
                if (editor.currentedit.tool === 'select') {
                    e.preventDefault();
                    node.setData('offsetx', e.clientX - container.getX());
                    node.setData('offsety', e.clientY - container.getY());
                }
            });
            node.on('gesturemove', function(e) {
                if (editor.currentedit.tool === 'select') {
                    var x = e.clientX - node.getData('offsetx'),
                        y = e.clientY - node.getData('offsety'),
                        newlocation,
                        windowlocation,
                        bounds;

                    if (node.getData('clicking') !== true) {
                        node.setData('clicking', true);
                    }

                    newlocation = this.editor.get_canvas_coordinates(new M.assignfeedback_editpdf.point(x, y));
                    bounds = this.editor.get_canvas_bounds(true);
                    bounds.x = 0;
                    bounds.y = 0;

                    bounds.width -= 24;
                    bounds.height -= 24;
                    // Clip to the window size - the comment icon size.
                    newlocation.clip(bounds);

                    this.x = newlocation.x;
                    this.y = newlocation.y;

                    windowlocation = this.editor.get_window_coordinates(newlocation);
                    container.setX(windowlocation.x);
                    container.setY(windowlocation.y);
                    this.drawable.store_position(container, windowlocation.x, windowlocation.y);
                }
            }, null, this);
            this.menu = new M.assignfeedback_editpdf.htmlcommentmenu({
                buttonNode: this.menulink,
                htmlcomment: this
            });
        }
    };

    /**
     * Delete a htmlcomment.
     * @method remove
     */
    this.remove = function() {
        var i = 0;
        var htmlcomments;

        htmlcomments = this.editor.pages[this.editor.currentpage].htmlcomments;
        for (i = 0; i < htmlcomments.length; i++) {
            if (htmlcomments[i] === this) {
                htmlcomments.splice(i, 1);
                this.drawable.erase();
                this.editor.save_current_page();
                return;
            }
        }
    };

    /**
     * Draw the in progress edit.
     *
     * @public
     * @method draw_current_edit
     * @param M.assignfeedback_editpdf.edit edit
     */
    this.draw_current_edit = function(edit) {
        var bounds = new M.assignfeedback_editpdf.rect(),
            drawable = new M.assignfeedback_editpdf.drawable(this.editor),
            drawingregion = this.editor.get_dialogue_element(SELECTOR.DRAWINGREGION),
            node,
            position;

        bounds.bound([edit.start, edit.end]);
        position = this.editor.get_window_coordinates(new M.assignfeedback_editpdf.point(bounds.x, bounds.y));

        node = Y.Node.create('<div/>');
        node.setStyles({
            'position': 'absolute',
            'display': 'inline-block',
            'width': bounds.width,
            'height': bounds.height,
            'backgroundSize': '100% 100%'
        });

        drawingregion.append(node);
        node.setX(position.x);
        node.setY(position.y);
        drawable.store_position(node, position.x, position.y);

        drawable.nodes.push(node);

        return drawable;
    };

    /**
     * Promote the current edit to a real htmlcomment.
     *
     * @public
     * @method init_from_edit
     * @param M.assignfeedback_editpdf.edit edit
     * @return bool true if htmlcomment bound is more than min width/height, else false.
     */
    this.init_from_edit = function(edit) {
        var bounds = new M.assignfeedback_editpdf.rect();
        bounds.bound([edit.start, edit.end]);

        if (bounds.width < 40) {
            bounds.width = 40;
        }
        if (bounds.height < 40) {
            bounds.height = 40;
        }
        this.gradeid = this.editor.get('gradeid');
        this.pageno = this.editor.currentpage;
        this.x = bounds.x;
        this.y = bounds.y;
        this.endx = bounds.x + bounds.width;
        this.endy = bounds.y + bounds.height;
        this.rawtext = '';

        // Min width and height is always more than 40px.
        return true;
    };

    /**
     * Update htmlcomment position when rotating page.
     * @public
     * @method updatePosition
     */
    this.updatePosition = function() {
        var node = this.drawable.nodes[0].one('div');
        var container = node.ancestor('div');

        var newlocation = new M.assignfeedback_editpdf.point(this.x, this.y);
        var windowlocation = this.editor.get_window_coordinates(newlocation);

        container.setX(windowlocation.x);
        container.setY(windowlocation.y);
        this.drawable.store_position(container, windowlocation.x, windowlocation.y);
    };

    this.edit_htmlcomment = function(e) {
        var htmleditor,
            htmlcont,
            textarea;
        e.preventDefault();
        var node = this.drawable.nodes[0].one('div');
        this.menu.hide();
        htmleditor = Y.one('#html_editoreditable');
        htmleditor.set('innerHTML', this.rawtext);
        if (!this.editor.htmleditorwindow) {
            this.htmleditorwindow = new M.assignfeedback_editpdf.htmleditor({
                editor: this
            });
            this.htmleditorwindow.show();
        } else {
            this.editor.htmleditorwindow.show();
        }
        htmlcont = Y.one('#editorcontainer');
        htmlcont.on('blur', function() {
            // Save the changes back to the comment.
            textarea = Y.one('#html_editor');
            this.rawtext = textarea.get('value');
            node.set('innerHTML', this.rawtext);
            this.width = parseInt(htmleditor.getStyle('width'), 10);
            Y.use('mathjax', function() {
                MathJax.Hub.Queue(["Typeset", MathJax.Hub, node.getDOMNode()]);
            });
            // Trim.
            if (this.rawtext.replace(/^\s+|\s+$/g, "") === '') {
                // Delete empty comments.
                this.deleteme = true;
                Y.later(400, this, this.delete_htmlcomment_later);
            }
            this.editor.save_current_page();
        }, this);
    };

};

M.assignfeedback_editpdf = M.assignfeedback_editpdf || {};
M.assignfeedback_editpdf.htmlcomment = HTMLCOMMENT;
