var HTMLEDITOR_NAME = "Htmleditor",
    HTMLEDITOR;

/**
 * Provides an in browser PDF editor.
 *
 * @module moodle-assignfeedback_editpdf-editor
 */

/**
 * HTMLEDITOR
 * This is a modal opens an editor.
 *
 * @namespace M.assignfeedback_editpdf
 * @class htmleditor
 * @constructor
 * @extends M.assignfeedback_editpdf.dropdown
 */
HTMLEDITOR = function(config) {
    config.draggable = false;
    config.centered = true;
    config.width = 'auto';
    config.visible = false;
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
    initializer: function(config) {
        var editor,
            container,
            textarea,
            bb;

        bb = this.get('boundingBox');
        bb.addClass('assignfeedback_editpdf_htmleditor');

        editor = this.get('editor');
        container = Y.Node.create('<div/>');

        textarea = Y.Node.create('<textarea class="editor" rows="4" cols="50" ></textarea>');
        container.append(textarea);

        // Set the body content.
        this.set('bodyContent', container);
        var params = [{'methodname' : 'assignfeedback_editpdf_htmleditor', dataType: 'json',contentType: "application/json",
                  'args' : {'textareaid': 1}}];
        params = JSON.stringify(params);
        HTMLEDITOR.superclass.initializer.call(this, config);
        // Perform the AJAX request.
        var uri = M.cfg.wwwroot + '/lib/ajax/service.php';
        Y.io(uri, {
            method: 'POST',
            data: params,
            on: {
                success: function(tid, response) {
                    // Update section titles, we can't simply swap them as
                    // they might have custom title
                    try {
                        var responsetext = Y.JSON.parse(response.responseText);
                        if (responsetext.error) {
                            new M.core.ajaxException(responsetext);
                        }
                        M.course.format.process_sections(Y, sectionlist, responsetext, loopstart, loopend);
                    } catch (e) {
                        // Ignore.
                    }

                    // Update all of the section IDs - first unset them, then set them
                    // to avoid duplicates in the DOM.
                    var index;

                    // Classic bubble sort algorithm is applied to the section
                    // nodes between original drag node location and the new one.
                    var swapped = false;
                    do {
                        swapped = false;
                        for (index = loopstart; index <= loopend; index++) {
                            if (Y.Moodle.core_course.util.section.getId(sectionlist.item(index - 1)) >
                                Y.Moodle.core_course.util.section.getId(sectionlist.item(index))) {
                                Y.log("Swapping " + Y.Moodle.core_course.util.section.getId(sectionlist.item(index - 1)) +
                                    " with " + Y.Moodle.core_course.util.section.getId(sectionlist.item(index)));
                                // Swap section id.
                                var sectionid = sectionlist.item(index - 1).get('id');
                                sectionlist.item(index - 1).set('id', sectionlist.item(index).get('id'));
                                sectionlist.item(index).set('id', sectionid);

                                // See what format needs to swap.
                                M.course.format.swap_sections(Y, index - 1, index);

                                // Update flag.
                                swapped = true;
                            }
                            sectionlist.item(index).setAttribute('data-sectionid',
                                Y.Moodle.core_course.util.section.getId(sectionlist.item(index)));
                        }
                        loopend = loopend - 1;
                    } while (swapped);

                    window.setTimeout(function() {
                        lightbox.hide();
                    }, 250);
                },

                failure: function(tid, response) {
                    this.ajax_failure(response);
                    lightbox.hide();
                }
            },
            context: this
        });
    }
},{
        NAME: HTMLEDITOR_NAME,
        ATTRS: {
            /**
             * The header for the drop down (only accessible to screen readers).
             *
             * @attribute headerText
             * @type String
             * @default ''
             */
            headerText: {
                value: ''
            },

            /**
             * The button used to show/hide this drop down menu.
             *
             * @attribute buttonNode
             * @type Y.Node
             * @default null
             */
            buttonNode: {
                value: null
            }
        }
    }
);
M.assignfeedback_editpdf = M.assignfeedback_editpdf || {};
M.assignfeedback_editpdf.htmleditor = HTMLEDITOR;
