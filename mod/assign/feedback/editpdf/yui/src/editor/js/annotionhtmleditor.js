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
 * Provides an in browser PDF this.editor.
 *
 * @module moodle-assignfeedback_editpdf-this.editor
 */

/**
 * Class representing a stamp.
 *
 * @namespace M.assignfeedback_editpdf
 * @class annotationstamp
 * @extends M.assignfeedback_editpdf.annotation
 */
var ANNOTATIONHTML = function(config) {
    ANNOTATIONHTML.superclass.constructor.apply(this, [config]);
};

ANNOTATIONHTML.NAME = "annotationhtml";
ANNOTATIONHTML.ATTRS = {};

Y.extend(ANNOTATIONHTML, M.assignfeedback_editpdf.annotation, {
    /**
     * Draw a stamp annotation
     * @protected
     * @method draw
     * @return M.assignfeedback_editpdf.drawable
     */
    draw: function() {
        var drawable = new M.assignfeedback_editpdf.drawable(this.editor),
            node,
            drawingcanvas = this.editor.get_dialogue_element(SELECTOR.DRAWINGCANVAS),
            container,
            label,
            marker,
            menu,
            position,
            scrollheight;

        // Lets add a contenteditable div.
        node = Y.Node.create('<div/>');
        container = Y.Node.create('<div class="commentdrawable"/>');
        label = Y.Node.create('<label/>');
        marker = Y.Node.create('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-0.5 -0.5 13 13" ' +
            'preserveAspectRatio="xMinYMin meet">' +
            '<path d="M11 0H1C.4 0 0 .4 0 1v6c0 .6.4 1 1 1h1v4l4-4h5c.6 0 1-.4 1-1V1c0-.6-.4-1-1-1z" ' +
            'fill="currentColor" opacity="0.9" stroke="rgb(153, 153, 153)" stroke-width="0.5"/></svg>');
        menu = Y.Node.create('<a href="#"><img src="' + M.util.image_url('t/contextmenu', 'core') + '"/></a>');

        this.menulink = menu;
        container.append(label);
        label.append(node);
        container.append(marker);
        container.setAttribute('tabindex', '-1');
        label.setAttribute('tabindex', '0');
        node.setAttribute('tabindex', '-1');
        menu.setAttribute('tabindex', '0');

        if (!this.editor.get('readonly')) {
            container.append(menu);
        } else {
            node.setAttribute('readonly', 'readonly');
        }
        if (this.width < 100) {
            this.width = 100;
        }

        position = this.editor.get_window_coordinates(new M.assignfeedback_editpdf.point(this.x, this.y));
        node.setStyles({
            width: this.width + 'px',
            backgroundColor: COMMENTCOLOUR[this.colour],
            color: COMMENTTEXTCOLOUR
        });

        drawingcanvas.append(container);
        container.setStyle('position', 'absolute');
        container.setX(position.x);
        container.setY(position.y);
        drawable.store_position(container, position.x, position.y);
        drawable.nodes.push(container);
        node.set('innerHTML', this.rawtext);
        Y.use('mathjax', function() {
            MathJax.Hub.Queue(["Typeset", MathJax.Hub, node.getDOMNode()]);
        });
        scrollheight = node.get('scrollHeight');
        node.setStyles({
            'height': scrollheight + 'px',
            'overflow': 'hidden'
        });
        marker.setStyle('color', COMMENTCOLOUR[this.colour]);
        this.attach_events(node, menu);

        container.addClass('htmlcollapsed');
        this.drawable = drawable;


        return drawable;
    },
    delete_comment_later : function() {
        if (this.deleteme) {
            this.remove();
        }
    },
    remove : function() {
        var i = 0;
        var comments;

        comments = this.editor.pages[this.editor.currentpage].comments;
        for (i = 0; i < comments.length; i++) {
            if (comments[i] === this) {
                comments.splice(i, 1);
                this.drawable.erase();
                this.editor.save_current_page();
                return;
            }
        }
    },
    attach_events : function(node, menu) {
        var container = node.ancestor('div'),
            label = node.ancestor('label'),
            marker = label.next('svg');

        // Function to collapse a comment to a marker icon.
        node.collapse = function(delay) {
            node.collapse.delay = Y.later(delay, node, function() {
                    container.addClass('htmlcollapsed');
            });
        };

        // Function to expand a comment.
        node.expand = function() {
            if (node.getData('dragging') !== true) {
                if (node.collapse.delay) {
                    node.collapse.delay.cancel();
                }
                container.removeClass('htmlcollapsed');
            }
        };

        // Expand comment on mouse over (under certain conditions) or click/tap.
        container.on('mouseenter', function() {
            if (this.editor.currentedit.tool === 'htmleditor' || this.editor.currentedit.tool === 'select'
                || this.editor.get('readonly')) {
                node.expand();
            }
        }, this);
        container.on('click|tap', function() {
            node.expand();
            node.focus();
        }, this);

        // Functions to capture reverse tabbing events.
        node.on('keyup', function(e) {
            if (e.keyCode === 9 && e.shiftKey && menu.getAttribute('tabindex') === '0') {
                // User landed here via Shift+Tab (but not from this comment's menu).
                menu.focus();
            }
            menu.setAttribute('tabindex', '0');
        }, this);
        menu.on('keydown', function(e) {
            if (e.keyCode === 9 && e.shiftKey) {
                // User is tabbing back to the comment node from its own menu.
                menu.setAttribute('tabindex', '-1');
            }
        }, this);

        // Comment becomes "active" on label or menu focus.
        label.on('focus', function() {
            node.active = true;
            if (node.collapse.delay) {
                node.collapse.delay.cancel();
            }
            // Give comment a tabindex to prevent focus outline being suppressed.
            node.setAttribute('tabindex', '0');
            // Expand comment and pass focus to it.
            node.expand();
            node.focus();
            // Now remove label tabindex so user can reverse tab past it.
            label.setAttribute('tabindex', '-1');
        }, this);
        menu.on('focus', function() {
            node.active = true;
            if (node.collapse.delay) {
                node.collapse.delay.cancel();
            }
            this.deleteme = false;
            // Restore label tabindex so user can tab back to it from menu.
            label.setAttribute('tabindex', '0');
        }, this);

        // Always restore the default tabindex states when moving away.
        node.on('blur', function() {
            node.setAttribute('tabindex', '-1');
        }, this);
        label.on('blur', function() {
            label.setAttribute('tabindex', '0');
        }, this);

        // Collapse comment on mouse out if not currently active.
        container.on('mouseleave', function() {
            if (this.editor.collapsecomments && node.active !== true) {
                node.collapse(400);
            }
        }, this);

        // Collapse comment on blur.
        container.on('blur', function() {
            node.active = false;
            node.collapse(800);
        }, this);

        if (!this.editor.get('readonly')) {
            // Save the text on blur.
            node.on('blur', function() {
                // Save the changes back to the comment.
                this.rawtext = node.get('value');
                this.width = parseInt(node.getStyle('width'), 10);

                // Trim.
                if (this.rawtext.replace(/^\s+|\s+$/g, "") === '') {
                    // Delete empty comments.
                    this.deleteme = true;
                    Y.later(400, this, this.delete_comment_later);
                }
                this.editor.save_current_page();
                this.editor.editingcomment = false;
            }, this);

            // For delegated event handler.
            menu.setData('comment', this);

            node.on('keyup', function() {
                node.setStyle('height', 'auto');
                var scrollheight = node.get('scrollHeight'),
                    height = parseInt(node.getStyle('height'), 10);

                // Webkit scrollheight fix.
                if (scrollheight === height + 8) {
                    scrollheight -= 8;
                }
                node.setStyle('height', scrollheight + 'px');
            });

            node.on('gesturemovestart', function(e) {
                if (this.editor.currentedit.tool === 'select') {
                    e.preventDefault();
                    if (this.editor.collapsecomments) {
                        node.setData('offsetx', 8);
                        node.setData('offsety', 8);
                    } else {
                        node.setData('offsetx', e.clientX - container.getX());
                        node.setData('offsety', e.clientY - container.getY());
                    }
                }
            });
            node.on('gesturemove', function(e) {
                if (this.editor.currentedit.tool === 'select') {
                    var x = e.clientX - node.getData('offsetx'),
                        y = e.clientY - node.getData('offsety'),
                        newlocation,
                        windowlocation,
                        bounds;

                    if (node.getData('dragging') !== true) {
                        // Collapse comment during move.
                        node.collapse(0);
                        node.setData('dragging', true);
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
            node.on('gesturemoveend', function() {
                if (this.editor.currentedit.tool === 'select') {
                    if (node.getData('dragging') === true) {
                        node.setData('dragging', false);
                    }
                    this.editor.save_current_page();
                }
            }, null, this);
            marker.on('gesturemovestart', function(e) {
                if (this.editor.currentedit.tool === 'select') {
                    e.preventDefault();
                    node.setData('offsetx', e.clientX - container.getX());
                    node.setData('offsety', e.clientY - container.getY());
                    node.expand();
                }
            });
            marker.on('gesturemove', function(e) {
                if (this.editor.currentedit.tool === 'select') {
                    var x = e.clientX - node.getData('offsetx'),
                        y = e.clientY - node.getData('offsety'),
                        newlocation,
                        windowlocation,
                        bounds;

                    if (node.getData('dragging') !== true) {
                        // Collapse comment during move.
                        node.collapse(100);
                        node.setData('dragging', true);
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
            marker.on('gesturemoveend', function() {
                if (this.editor.currentedit.tool === 'select') {
                    if (node.getData('dragging') === true) {
                        node.setData('dragging', false);
                    }
                    this.editor.save_current_page();
                }
            }, null, this);

            this.menu = new M.assignfeedback_editpdf.commentmenu({
                buttonNode: this.menulink,
                comment: this
            });
        }
    },
    /**
     * Draw the in progress edit.
     *
     * @public
     * @method draw_current_edit
     * @param M.assignfeedback_editpdf.edit edit
     */
    draw_current_edit: function(edit) {
        var drawable = new M.assignfeedback_editpdf.drawable(this.editor),
            shape,
            bounds;

        bounds = new M.assignfeedback_editpdf.rect();
        bounds.bound([edit.start, edit.end]);

        // We will draw a box with the current background colour.
        shape = this.editor.graphic.addShape({
            type: Y.Rect,
            width: bounds.width,
            height: bounds.height,
            fill: {
                color: COMMENTCOLOUR[edit.commentcolour]
            },
            x: bounds.x,
            y: bounds.y
        });

        drawable.shapes.push(shape);

        return drawable;
    },

    /**
     * Promote the current edit to a real annotation.
     *
     * @public
     * @method init_from_edit
     * @param M.assignfeedback_editpdf.edit edit
     * @return bool if width/height is more than min. required.
     */
    init_from_edit: function(edit) {
        var bounds = new M.assignfeedback_editpdf.rect(),
            textarea;
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
        textarea = Y.one('#html_editor');
        this.rawtext = textarea.get('value');
        this.colour = edit.annotationcolour;
        this.path = edit.stamp;

        // Min width and height is always more than 40px.
        return true;
    },

    /**
     * Move an annotation to a new location.
     * @public
     * @param int newx
     * @param int newy
     * @method move_annotation
     */
    move: function(newx, newy) {
        var diffx = newx - this.x,
            diffy = newy - this.y;

        this.x += diffx;
        this.y += diffy;
        this.endx += diffx;
        this.endy += diffy;

        if (this.drawable) {
            this.drawable.erase();
        }
        this.editor.drawables.push(this.draw());
    }

});

M.assignfeedback_editpdf = M.assignfeedback_editpdf || {};
M.assignfeedback_editpdf.annotationhtml = ANNOTATIONHTML;
