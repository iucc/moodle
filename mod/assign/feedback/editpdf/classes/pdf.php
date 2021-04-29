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
 * Library code for manipulating PDFs
 *
 * @package assignfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_editpdf;
use setasign\Fpdi\TcpdfFpdi;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/mod/assign/feedback/editpdf/fpdi/autoload.php');
require_once($CFG->dirroot.'/mod/assign/feedback/editpdf/vendor/phplatex/phplatex.php');

/**
 * Library code for manipulating PDFs
 *
 * @package assignfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf extends TcpdfFpdi {

    /** @var int the number of the current page in the PDF being processed */
    protected $currentpage = 0;
    /** @var int the total number of pages in the PDF being processed */
    protected $pagecount = 0;
    /** @var float used to scale the pixel position of annotations (in the database) to the position in the final PDF */
    protected $scale = 0.0;
    /** @var string the path in which to store generated page images */
    protected $imagefolder = null;
    /** @var string the path to the PDF currently being processed */
    protected $filename = null;

    /** No errors */
    const GSPATH_OK = 'ok';
    /** Not set */
    const GSPATH_EMPTY = 'empty';
    /** Does not exist */
    const GSPATH_DOESNOTEXIST = 'doesnotexist';
    /** Is a dir */
    const GSPATH_ISDIR = 'isdir';
    /** Not executable */
    const GSPATH_NOTEXECUTABLE = 'notexecutable';
    /** Test file missing */
    const GSPATH_NOTESTFILE = 'notestfile';
    /** Any other error */
    const GSPATH_ERROR = 'error';
    /** Min. width an annotation should have */
    const MIN_ANNOTATION_WIDTH = 5;
    /** Min. height an annotation should have */
    const MIN_ANNOTATION_HEIGHT = 5;
    /** Blank PDF file used during error. */
    const BLANK_PDF = '/mod/assign/feedback/editpdf/fixtures/blank.pdf';
    /** Page image file name prefix*/
    const IMAGE_PAGE = 'image_page';
    /**
     * Get the name of the font to use in generated PDF files.
     * If $CFG->pdfexportfont is set - use it, otherwise use "freesans" as this
     * open licensed font has wide support for different language charsets.
     *
     * @return string
     */
    private function get_export_font_name() {
        global $CFG;

        $fontname = 'freesans';
        if (!empty($CFG->pdfexportfont)) {
            $fontname = $CFG->pdfexportfont;
        }
        return $fontname;
    }

    /**
     * Combine the given PDF files into a single PDF. Optionally add a coversheet and coversheet fields.
     * @param string[] $pdflist  the filenames of the files to combine
     * @param string $outfilename the filename to write to
     * @return int the number of pages in the combined PDF
     */
    public function combine_pdfs($pdflist, $outfilename) {

        raise_memory_limit(MEMORY_EXTRA);
        $olddebug = error_reporting(0);

        $this->setPageUnit('pt');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->scale = 72.0 / 100.0;
        // Use font supporting the widest range of characters.
        $this->SetFont($this->get_export_font_name(), '', 16.0 * $this->scale, '', true);
        $this->SetTextColor(0, 0, 0);

        $totalpagecount = 0;

        foreach ($pdflist as $file) {
            $pagecount = $this->setSourceFile($file);
            $totalpagecount += $pagecount;
            for ($i = 1; $i<=$pagecount; $i++) {
                $this->create_page_from_source($i);
            }
        }

        $this->save_pdf($outfilename);
        error_reporting($olddebug);

        return $totalpagecount;
    }

    /**
     * The number of the current page in the PDF being processed
     * @return int
     */
    public function current_page() {
        return $this->currentpage;
    }

    /**
     * The total number of pages in the PDF being processed
     * @return int
     */
    public function page_count() {
        return $this->pagecount;
    }

    /**
     * Load the specified PDF and set the initial output configuration
     * Used when processing comments and outputting a new PDF
     * @param string $filename the path to the PDF to load
     * @return int the number of pages in the PDF
     */
    public function load_pdf($filename) {
        raise_memory_limit(MEMORY_EXTRA);
        $olddebug = error_reporting(0);

        $this->setPageUnit('pt');
        $this->scale = 72.0 / 100.0;
        $this->SetFont($this->get_export_font_name(), '', 16.0 * $this->scale, '', true);
        $this->SetFillColor(255, 255, 176);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(1.0 * $this->scale);
        $this->SetTextColor(0, 0, 0);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->pagecount = $this->setSourceFile($filename);
        $this->filename = $filename;

        error_reporting($olddebug);
        return $this->pagecount;
    }

    /**
     * Sets the name of the PDF to process, but only loads the file if the
     * pagecount is zero (in order to count the number of pages)
     * Used when generating page images (but not a new PDF)
     * @param string $filename the path to the PDF to process
     * @param int $pagecount optional the number of pages in the PDF, if known
     * @return int the number of pages in the PDF
     */
    public function set_pdf($filename, $pagecount = 0) {
        if ($pagecount == 0) {
            return $this->load_pdf($filename);
        } else {
            $this->filename = $filename;
            $this->pagecount = $pagecount;
            return $pagecount;
        }
    }

    /**
     * Copy the next page from the source file and set it as the current page
     * @return bool true if successful
     */
    public function copy_page() {
        if (!$this->filename) {
            return false;
        }
        if ($this->currentpage>=$this->pagecount) {
            return false;
        }
        $this->currentpage++;
        $this->create_page_from_source($this->currentpage);
        return true;
    }

    /**
     * Create a page from a source PDF.
     *
     * @param int $pageno
     */
    protected function create_page_from_source($pageno) {
        // Get the size (and deduce the orientation) of the next page.
        $template = $this->importPage($pageno);
        $size = $this->getTemplateSize($template);

        // Create a page of the required size / orientation.
        $this->AddPage($size['orientation'], array($size['width'], $size['height']));
        // Prevent new page creation when comments are at the bottom of a page.
        $this->setPageOrientation($size['orientation'], false, 0);
        // Fill in the page with the original contents from the student.
        $this->useTemplate($template);
    }

    /**
     * Copy all the remaining pages in the file
     */
    public function copy_remaining_pages() {
        $morepages = true;
        while ($morepages) {
            $morepages = $this->copy_page();
        }
    }

    /**
     * Append all comments to the end of the document.
     *
     * @param array $allcomments All comments, indexed by page number (starting at 0).
     * @return array|bool An array of links to comments, or false.
     */
    public function append_comments($allcomments) {
        if (!$this->filename) {
            return false;
        }

        $this->SetFontSize(12 * $this->scale);
        $this->SetMargins(100 * $this->scale, 120 * $this->scale, -1, true);
        $this->SetAutoPageBreak(true, 100 * $this->scale);
        $this->setHeaderFont(array($this->get_export_font_name(), '', 24 * $this->scale, '', true));
        $this->setHeaderMargin(24 * $this->scale);
        $this->setHeaderData('', 0, '', get_string('commentindex', 'assignfeedback_editpdf'));

        // Add a new page to the document with an appropriate header.
        $this->setPrintHeader(true);
        $this->AddPage();

        // Add the comments.
        $commentlinks = array();
        foreach ($allcomments as $pageno => $comments) {
            foreach ($comments as $index => $comment) {
                // Create a link to the current location, which will be added to the marker.
                $commentlink = $this->AddLink();
                $this->SetLink($commentlink, -1);
                $commentlinks[$pageno][$index] = $commentlink;
                // Also create a link back to the marker, which will be added here.
                $markerlink = $this->AddLink();
                $this->SetLink($markerlink, $comment->y * $this->scale, $pageno + 1);
                $label = get_string('commentlabel', 'assignfeedback_editpdf', array('pnum' => $pageno + 1, 'cnum' => $index + 1));
                $this->Cell(50 * $this->scale, 0, $label, 0, 0, '', false, $markerlink);
                $this->MultiCell(0, 0, $comment->rawtext, 0, 'L');
                $this->Ln(12 * $this->scale);
            }
            // Add an extra line break between pages.
            $this->Ln(12 * $this->scale);
        }

        return $commentlinks;
    }

    /**
     * Add a comment marker to the specified page.
     *
     * @param int $pageno The page number to add markers to (starting at 0).
     * @param int $index The comment index.
     * @param int $x The x-coordinate of the marker (in pixels).
     * @param int $y The y-coordinate of the marker (in pixels).
     * @param int $link The link identifier pointing to the full comment text.
     * @param string $colour The fill colour of the marker (red, yellow, green, blue, white, clear).
     * @return bool Success status.
     */
    public function add_comment_marker($pageno, $index, $x, $y, $link, $colour = 'yellow') {
        if (!$this->filename) {
            return false;
        }

        $fill = '';
        $fillopacity = 0.9;
        switch ($colour) {
            case 'red':
                $fill = 'rgb(249, 181, 179)';
                break;
            case 'green':
                $fill = 'rgb(214, 234, 178)';
                break;
            case 'blue':
                $fill = 'rgb(203, 217, 237)';
                break;
            case 'white':
                $fill = 'rgb(255, 255, 255)';
                break;
            case 'clear':
                $fillopacity = 0;
                break;
            default: /* Yellow */
                $fill = 'rgb(255, 236, 174)';
        }
        $marker = '@<svg xmlns="http://www.w3.org/2000/svg" viewBox="-0.5 -0.5 12 12" preserveAspectRatio="xMinYMin meet">' .
                '<path d="M11 0H1C.4 0 0 .4 0 1v6c0 .6.4 1 1 1h1v4l4-4h5c.6 0 1-.4 1-1V1c0-.6-.4-1-1-1z" fill="' . $fill . '" ' .
                'fill-opacity="' . $fillopacity . '" stroke="rgb(153, 153, 153)" stroke-width="0.5"/></svg>';
        $label = get_string('commentlabel', 'assignfeedback_editpdf', array('pnum' => $pageno + 1, 'cnum' => $index + 1));

        $x *= $this->scale;
        $y *= $this->scale;
        $size = 24 * $this->scale;
        $this->SetDrawColor(51, 51, 51);
        $this->SetFontSize(10 * $this->scale);
        $this->setPage($pageno + 1);

        // Add the marker image.
        $this->ImageSVG($marker, $x - 0.5, $y - 0.5, $size, $size, $link);

        // Add the label.
        $this->MultiCell($size * 0.95, 0, $label, 0, 'C', false, 1, $x, $y, true, 0, false, true, $size * 0.60, 'M', true);

        return true;
    }

    /**
     * Add a comment to the current page
     * @param string $text the text of the comment
     * @param int $x the x-coordinate of the comment (in pixels)
     * @param int $y the y-coordinate of the comment (in pixels)
     * @param int $width the width of the comment (in pixels)
     * @param string $colour optional the background colour of the comment (red, yellow, green, blue, white, clear)
     * @return bool true if successful (always)
     */
    public function add_comment($text, $x, $y, $width, $colour = 'yellow') {
        if (!$this->filename) {
            return false;
        }
        $this->SetDrawColor(51, 51, 51);
        switch ($colour) {
            case 'red':
                $this->SetFillColor(249, 181, 179);
                break;
            case 'green':
                $this->SetFillColor(214, 234, 178);
                break;
            case 'blue':
                $this->SetFillColor(203, 217, 237);
                break;
            case 'white':
                $this->SetFillColor(255, 255, 255);
                break;
            default: /* Yellow */
                $this->SetFillColor(255, 236, 174);
                break;
        }

        $x *= $this->scale;
        $y *= $this->scale;
        $width *= $this->scale;
        $text = str_replace('&lt;', '<', $text);
        $text = str_replace('&gt;', '>', $text);
        // Draw the text with a border, but no background colour (using a background colour would cause the fill to
        // appear behind any existing content on the page, hence the extra filled rectangle drawn below).
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 4, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        if ($colour != 'clear') {
            $newy = $this->GetY();
            // Now we know the final size of the comment, draw a rectangle with the background colour.
            $this->Rect($x, $y, $width, $newy - $y, 'DF');
            // Re-draw the text over the top of the background rectangle.
            $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 4, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        }
        return true;
    }

    /**
     * Process opening tags.
     * @param $dom (array) html dom array
     * @param $key (int) current element id
     * @param $cell (boolean) if true add the default left (or right if RTL) padding to each new line (default false).
     * @return $dom array
     * @protected
     */
    protected function openHTMLTagHandler($dom, $key, $cell) {
        global $CFG;
        $tag = $dom[$key];
        $parent = $dom[($dom[$key]['parent'])];
        $firsttag = ($key == 1);
        // check for text direction attribute
        if (isset($tag['dir'])) {
            $this->setTempRTL($tag['dir']);
        } else {
            $this->tmprtl = false;
        }
        if ($tag['block']) {
            $hbz = 0; // distance from y to line bottom
            $hb = 0; // vertical space between block tags
            // calculate vertical space for block tags
            if (isset($this->tagvspaces[$tag['value']][0]['h']) && !empty($this->tagvspaces[$tag['value']][0]['h']) && ($this->tagvspaces[$tag['value']][0]['h'] >= 0)) {
                $cur_h = $this->tagvspaces[$tag['value']][0]['h'];
            } elseif (isset($tag['fontsize'])) {
                $cur_h = $this->getCellHeight($tag['fontsize'] / $this->k);
            } else {
                $cur_h = $this->getCellHeight($this->FontSize);
            }
            if (isset($this->tagvspaces[$tag['value']][0]['n'])) {
                $on = $this->tagvspaces[$tag['value']][0]['n'];
            } elseif (preg_match('/[h][0-9]/', $tag['value']) > 0) {
                $on = 0.6;
            } else {
                $on = 1;
            }
            if ((!isset($this->tagvspaces[$tag['value']])) AND (in_array($tag['value'], array('div', 'dt', 'dd', 'li', 'br', 'hr')))) {
                $hb = 0;
            } else {
                $hb = ($on * $cur_h);
            }
            if (($this->htmlvspace <= 0) AND ($on > 0)) {
                if (isset($parent['fontsize'])) {
                    $hbz = (($parent['fontsize'] / $this->k) * $this->cell_height_ratio);
                } else {
                    $hbz = $this->getCellHeight($this->FontSize);
                }
            }
            if (isset($dom[($key - 1)]) AND ($dom[($key - 1)]['value'] == 'table')) {
                // fix vertical space after table
                $hbz = 0;
            }
            // closing vertical space
            $hbc = 0;
            if (isset($this->tagvspaces[$tag['value']][1]['h']) && !empty($this->tagvspaces[$tag['value']][1]['h']) && ($this->tagvspaces[$tag['value']][1]['h'] >= 0)) {
                $pre_h = $this->tagvspaces[$tag['value']][1]['h'];
            } elseif (isset($parent['fontsize'])) {
                $pre_h = $this->getCellHeight($parent['fontsize'] / $this->k);
            } else {
                $pre_h = $this->getCellHeight($this->FontSize);
            }
            if (isset($this->tagvspaces[$tag['value']][1]['n'])) {
                $cn = $this->tagvspaces[$tag['value']][1]['n'];
            } elseif (preg_match('/[h][0-9]/', $tag['value']) > 0) {
                $cn = 0.6;
            } else {
                $cn = 1;
            }
            if (isset($this->tagvspaces[$tag['value']][1])) {
                $hbc = ($cn * $pre_h);
            }
        }
        // Opening tag
        switch($tag['value']) {
            case 'table': {
                $cp = 0;
                $cs = 0;
                $dom[$key]['rowspans'] = array();
                if (!isset($dom[$key]['attribute']['nested']) OR ($dom[$key]['attribute']['nested'] != 'true')) {
                    $this->htmlvspace = 0;
                    // set table header
                    if (!\TCPDF_STATIC::empty_string($dom[$key]['thead'])) {
                        // set table header
                        $this->thead = $dom[$key]['thead'];
                        if (!isset($this->theadMargins) OR (empty($this->theadMargins))) {
                            $this->theadMargins = array();
                            $this->theadMargins['cell_padding'] = $this->cell_padding;
                            $this->theadMargins['lmargin'] = $this->lMargin;
                            $this->theadMargins['rmargin'] = $this->rMargin;
                            $this->theadMargins['page'] = $this->page;
                            $this->theadMargins['cell'] = $cell;
                            $this->theadMargins['gvars'] = $this->getGraphicVars();
                        }
                    }
                }
                // store current margins and page
                $dom[$key]['old_cell_padding'] = $this->cell_padding;
                if (isset($tag['attribute']['cellpadding'])) {
                    $pad = $this->getHTMLUnitToUnits($tag['attribute']['cellpadding'], 1, 'px');
                    $this->SetCellPadding($pad);
                } elseif (isset($tag['padding'])) {
                    $this->cell_padding = $tag['padding'];
                }
                if (isset($tag['attribute']['cellspacing'])) {
                    $cs = $this->getHTMLUnitToUnits($tag['attribute']['cellspacing'], 1, 'px');
                } elseif (isset($tag['border-spacing'])) {
                    $cs = $tag['border-spacing']['V'];
                }
                $prev_y = $this->y;
                if ($this->checkPageBreak(((2 * $cp) + (2 * $cs) + $this->lasth), '', false) OR ($this->y < $prev_y)) {
                    $this->inthead = true;
                    // add a page (or trig AcceptPageBreak() for multicolumn mode)
                    $this->checkPageBreak($this->PageBreakTrigger + 1);
                }
                break;
            }
            case 'tr': {
                // array of columns positions
                $dom[$key]['cellpos'] = array();
                break;
            }
            case 'hr': {
                if ((isset($tag['height'])) AND ($tag['height'] != '')) {
                    $hrHeight = $this->getHTMLUnitToUnits($tag['height'], 1, 'px');
                } else {
                    $hrHeight = $this->GetLineWidth();
                }
                $this->addHTMLVertSpace($hbz, max($hb, ($hrHeight / 2)), $cell, $firsttag);
                $x = $this->GetX();
                $y = $this->GetY();
                $wtmp = $this->w - $this->lMargin - $this->rMargin;
                if ($cell) {
                    $wtmp -= ($this->cell_padding['L'] + $this->cell_padding['R']);
                }
                if ((isset($tag['width'])) AND ($tag['width'] != '')) {
                    $hrWidth = $this->getHTMLUnitToUnits($tag['width'], $wtmp, 'px');
                } else {
                    $hrWidth = $wtmp;
                }
                $prevlinewidth = $this->GetLineWidth();
                $this->SetLineWidth($hrHeight);
                $this->Line($x, $y, $x + $hrWidth, $y);
                $this->SetLineWidth($prevlinewidth);
                $this->addHTMLVertSpace(max($hbc, ($hrHeight / 2)), 0, $cell, !isset($dom[($key + 1)]));
                break;
            }
            case 'a': {
                if (array_key_exists('href', $tag['attribute'])) {
                    $this->HREF['url'] = $tag['attribute']['href'];
                }
                break;
            }
            case 'img': {
                if (empty($tag['attribute']['src'])) {
                    break;
                }
                $imgsrc = $tag['attribute']['src'];
                if ($imgsrc[0] === '@') {
                    // data stream
                    $imgsrc = '@'.base64_decode(substr($imgsrc, 1));
                    $type = '';
                } else {
                    $findataroot = strpos($imgsrc,$CFG->tempdir);
                    if ( $findataroot === false ) {
                        if (($imgsrc[0] === '/') and !empty($_SERVER['DOCUMENT_ROOT']) and ($_SERVER['DOCUMENT_ROOT'] != '/')) {
                            // fix image path
                            $findroot = strpos($imgsrc, $_SERVER['DOCUMENT_ROOT']);
                            if (($findroot === false) or ($findroot > 1)) {
                                if (substr($_SERVER['DOCUMENT_ROOT'], -1) == '/') {
                                    $imgsrc = substr($_SERVER['DOCUMENT_ROOT'], 0, -1) . $imgsrc;
                                } else {
                                    $imgsrc = $_SERVER['DOCUMENT_ROOT'] . $imgsrc;
                                }
                            }
                            $imgsrc = urldecode($imgsrc);
                            $testscrtype = @parse_url($imgsrc);
                            if (empty($testscrtype['query'])) {
                                // convert URL to server path
                                $imgsrc = str_replace(K_PATH_URL, K_PATH_MAIN, $imgsrc);
                            } else if (preg_match('|^https?://|', $imgsrc) !== 1) {
                                // convert URL to server path
                                $imgsrc = str_replace(K_PATH_MAIN, K_PATH_URL, $imgsrc);
                            }
                        }
                    }
                    // get image type
                    $type = \TCPDF_IMAGES::getImageFileType($imgsrc);
                }
                if (!isset($tag['width'])) {
                    $tag['width'] = 0;
                }
                if (!isset($tag['height'])) {
                    $tag['height'] = 0;
                }
                //if (!isset($tag['attribute']['align'])) {
                // the only alignment supported is "bottom"
                // further development is required for other modes.
                $tag['attribute']['align'] = 'bottom';
                //}
                switch($tag['attribute']['align']) {
                    case 'top': {
                        $align = 'T';
                        break;
                    }
                    case 'middle': {
                        $align = 'M';
                        break;
                    }
                    case 'bottom': {
                        $align = 'B';
                        break;
                    }
                    default: {
                        $align = 'B';
                        break;
                    }
                }
                $prevy = $this->y;
                $xpos = $this->x;
                $imglink = '';
                if (isset($this->HREF['url']) AND !\TCPDF_STATIC::empty_string($this->HREF['url'])) {
                    $imglink = $this->HREF['url'];
                    if ($imglink[0] == '#') {
                        // convert url to internal link
                        $lnkdata = explode(',', $imglink);
                        if (isset($lnkdata[0])) {
                            $page = intval(substr($lnkdata[0], 1));
                            if (empty($page) OR ($page <= 0)) {
                                $page = $this->page;
                            }
                            if (isset($lnkdata[1]) AND (strlen($lnkdata[1]) > 0)) {
                                $lnky = floatval($lnkdata[1]);
                            } else {
                                $lnky = 0;
                            }
                            $imglink = $this->AddLink();
                            $this->SetLink($imglink, $lnky, $page);
                        }
                    }
                }
                $border = 0;
                if (isset($tag['border']) AND !empty($tag['border'])) {
                    // currently only support 1 (frame) or a combination of 'LTRB'
                    $border = $tag['border'];
                }
                $iw = '';
                if (isset($tag['width'])) {
                    $iw = $this->getHTMLUnitToUnits($tag['width'], ($tag['fontsize'] / $this->k), 'px', false);
                }
                $ih = '';
                if (isset($tag['height'])) {
                    $ih = $this->getHTMLUnitToUnits($tag['height'], ($tag['fontsize'] / $this->k), 'px', false);
                }
                if (($type == 'eps') OR ($type == 'ai')) {
                    $this->ImageEps($imgsrc, $xpos, $this->y, $iw, $ih, $imglink, true, $align, '', $border, true);
                } elseif ($type == 'svg') {
                    $this->ImageSVG($imgsrc, $xpos, $this->y, $iw, $ih, $imglink, $align, '', $border, true);
                } else {
                    $this->Image($imgsrc, $xpos, $this->y, $iw, $ih, '', $imglink, $align, false, 300, '', false, false, $border, false, false, true);
                }
                switch($align) {
                    case 'T': {
                        $this->y = $prevy;
                        break;
                    }
                    case 'M': {
                        $this->y = (($this->img_rb_y + $prevy - ($this->getCellHeight($tag['fontsize'] / $this->k))) / 2);
                        break;
                    }
                    case 'B': {
                        $this->y = $this->img_rb_y - ($this->getCellHeight($tag['fontsize'] / $this->k) - ($this->getFontDescent($tag['fontname'], $tag['fontstyle'], $tag['fontsize']) * $this->cell_height_ratio));
                        break;
                    }
                }
                break;
            }
            case 'dl': {
                ++$this->listnum;
                if ($this->listnum == 1) {
                    $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                } else {
                    $this->addHTMLVertSpace(0, 0, $cell, $firsttag);
                }
                break;
            }
            case 'dt': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'dd': {
                if ($this->rtl) {
                    $this->rMargin += $this->listindent;
                } else {
                    $this->lMargin += $this->listindent;
                }
                ++$this->listindentlevel;
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'ul':
            case 'ol': {
                ++$this->listnum;
                if ($tag['value'] == 'ol') {
                    $this->listordered[$this->listnum] = true;
                } else {
                    $this->listordered[$this->listnum] = false;
                }
                if (isset($tag['attribute']['start'])) {
                    $this->listcount[$this->listnum] = intval($tag['attribute']['start']) - 1;
                } else {
                    $this->listcount[$this->listnum] = 0;
                }
                if ($this->rtl) {
                    $this->rMargin += $this->listindent;
                    $this->x -= $this->listindent;
                } else {
                    $this->lMargin += $this->listindent;
                    $this->x += $this->listindent;
                }
                ++$this->listindentlevel;
                if ($this->listnum == 1) {
                    if ($key > 1) {
                        $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                    }
                } else {
                    $this->addHTMLVertSpace(0, 0, $cell, $firsttag);
                }
                break;
            }
            case 'li': {
                if ($key > 2) {
                    $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                }
                if ($this->listordered[$this->listnum]) {
                    // ordered item
                    if (isset($parent['attribute']['type']) AND !\TCPDF_STATIC::empty_string($parent['attribute']['type'])) {
                        $this->lispacer = $parent['attribute']['type'];
                    } elseif (isset($parent['listtype']) AND !\TCPDF_STATIC::empty_string($parent['listtype'])) {
                        $this->lispacer = $parent['listtype'];
                    } elseif (isset($this->lisymbol) AND !\TCPDF_STATIC::empty_string($this->lisymbol)) {
                        $this->lispacer = $this->lisymbol;
                    } else {
                        $this->lispacer = '#';
                    }
                    ++$this->listcount[$this->listnum];
                    if (isset($tag['attribute']['value'])) {
                        $this->listcount[$this->listnum] = intval($tag['attribute']['value']);
                    }
                } else {
                    // unordered item
                    if (isset($parent['attribute']['type']) AND !\TCPDF_STATIC::empty_string($parent['attribute']['type'])) {
                        $this->lispacer = $parent['attribute']['type'];
                    } elseif (isset($parent['listtype']) AND !\TCPDF_STATIC::empty_string($parent['listtype'])) {
                        $this->lispacer = $parent['listtype'];
                    } elseif (isset($this->lisymbol) AND !\TCPDF_STATIC::empty_string($this->lisymbol)) {
                        $this->lispacer = $this->lisymbol;
                    } else {
                        $this->lispacer = '!';
                    }
                }
                break;
            }
            case 'blockquote': {
                if ($this->rtl) {
                    $this->rMargin += $this->listindent;
                } else {
                    $this->lMargin += $this->listindent;
                }
                ++$this->listindentlevel;
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'br': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'div': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'p': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            case 'pre': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                $this->premode = true;
                break;
            }
            case 'sup': {
                $this->SetXY($this->GetX(), $this->GetY() - ((0.7 * $this->FontSizePt) / $this->k));
                break;
            }
            case 'sub': {
                $this->SetXY($this->GetX(), $this->GetY() + ((0.3 * $this->FontSizePt) / $this->k));
                break;
            }
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6': {
                $this->addHTMLVertSpace($hbz, $hb, $cell, $firsttag);
                break;
            }
            // Form fields (since 4.8.000 - 2009-09-07)
            case 'form': {
                if (isset($tag['attribute']['action'])) {
                    $this->form_action = $tag['attribute']['action'];
                } else {
                    $this->Error('Please explicitly set action attribute path!');
                }
                if (isset($tag['attribute']['enctype'])) {
                    $this->form_enctype = $tag['attribute']['enctype'];
                } else {
                    $this->form_enctype = 'application/x-www-form-urlencoded';
                }
                if (isset($tag['attribute']['method'])) {
                    $this->form_mode = $tag['attribute']['method'];
                } else {
                    $this->form_mode = 'post';
                }
                break;
            }
            case 'input': {
                if (isset($tag['attribute']['name']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['name'])) {
                    $name = $tag['attribute']['name'];
                } else {
                    break;
                }
                $prop = array();
                $opt = array();
                if (isset($tag['attribute']['readonly']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['readonly'])) {
                    $prop['readonly'] = true;
                }
                if (isset($tag['attribute']['value']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['value'])) {
                    $value = $tag['attribute']['value'];
                }
                if (isset($tag['attribute']['maxlength']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['maxlength'])) {
                    $opt['maxlen'] = intval($tag['attribute']['maxlength']);
                }
                $h = $this->getCellHeight($this->FontSize);
                if (isset($tag['attribute']['size']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['size'])) {
                    $w = intval($tag['attribute']['size']) * $this->GetStringWidth(chr(32)) * 2;
                } else {
                    $w = $h;
                }
                if (isset($tag['attribute']['checked']) AND (($tag['attribute']['checked'] == 'checked') OR ($tag['attribute']['checked'] == 'true'))) {
                    $checked = true;
                } else {
                    $checked = false;
                }
                if (isset($tag['align'])) {
                    switch ($tag['align']) {
                        case 'C': {
                            $opt['q'] = 1;
                            break;
                        }
                        case 'R': {
                            $opt['q'] = 2;
                            break;
                        }
                        case 'L':
                        default: {
                            break;
                        }
                    }
                }
                switch ($tag['attribute']['type']) {
                    case 'text': {
                        if (isset($value)) {
                            $opt['v'] = $value;
                        }
                        $this->TextField($name, $w, $h, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'password': {
                        if (isset($value)) {
                            $opt['v'] = $value;
                        }
                        $prop['password'] = 'true';
                        $this->TextField($name, $w, $h, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'checkbox': {
                        if (!isset($value)) {
                            break;
                        }
                        $this->CheckBox($name, $w, $checked, $prop, $opt, $value, '', '', false);
                        break;
                    }
                    case 'radio': {
                        if (!isset($value)) {
                            break;
                        }
                        $this->RadioButton($name, $w, $prop, $opt, $value, $checked, '', '', false);
                        break;
                    }
                    case 'submit': {
                        if (!isset($value)) {
                            $value = 'submit';
                        }
                        $w = $this->GetStringWidth($value) * 1.5;
                        $h *= 1.6;
                        $prop = array('lineWidth'=>1, 'borderStyle'=>'beveled', 'fillColor'=>array(196, 196, 196), 'strokeColor'=>array(255, 255, 255));
                        $action = array();
                        $action['S'] = 'SubmitForm';
                        $action['F'] = $this->form_action;
                        if ($this->form_enctype != 'FDF') {
                            $action['Flags'] = array('ExportFormat');
                        }
                        if ($this->form_mode == 'get') {
                            $action['Flags'] = array('GetMethod');
                        }
                        $this->Button($name, $w, $h, $value, $action, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'reset': {
                        if (!isset($value)) {
                            $value = 'reset';
                        }
                        $w = $this->GetStringWidth($value) * 1.5;
                        $h *= 1.6;
                        $prop = array('lineWidth'=>1, 'borderStyle'=>'beveled', 'fillColor'=>array(196, 196, 196), 'strokeColor'=>array(255, 255, 255));
                        $this->Button($name, $w, $h, $value, array('S'=>'ResetForm'), $prop, $opt, '', '', false);
                        break;
                    }
                    case 'file': {
                        $prop['fileSelect'] = 'true';
                        $this->TextField($name, $w, $h, $prop, $opt, '', '', false);
                        if (!isset($value)) {
                            $value = '*';
                        }
                        $w = $this->GetStringWidth($value) * 2;
                        $h *= 1.2;
                        $prop = array('lineWidth'=>1, 'borderStyle'=>'beveled', 'fillColor'=>array(196, 196, 196), 'strokeColor'=>array(255, 255, 255));
                        $jsaction = 'var f=this.getField(\''.$name.'\'); f.browseForFileToSubmit();';
                        $this->Button('FB_'.$name, $w, $h, $value, $jsaction, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'hidden': {
                        if (isset($value)) {
                            $opt['v'] = $value;
                        }
                        $opt['f'] = array('invisible', 'hidden');
                        $this->TextField($name, 0, 0, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'image': {
                        // THIS TYPE MUST BE FIXED
                        if (isset($tag['attribute']['src']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['src'])) {
                            $img = $tag['attribute']['src'];
                        } else {
                            break;
                        }
                        $value = 'img';
                        //$opt['mk'] = array('i'=>$img, 'tp'=>1, 'if'=>array('sw'=>'A', 's'=>'A', 'fb'=>false));
                        if (isset($tag['attribute']['onclick']) AND !empty($tag['attribute']['onclick'])) {
                            $jsaction = $tag['attribute']['onclick'];
                        } else {
                            $jsaction = '';
                        }
                        $this->Button($name, $w, $h, $value, $jsaction, $prop, $opt, '', '', false);
                        break;
                    }
                    case 'button': {
                        if (!isset($value)) {
                            $value = ' ';
                        }
                        $w = $this->GetStringWidth($value) * 1.5;
                        $h *= 1.6;
                        $prop = array('lineWidth'=>1, 'borderStyle'=>'beveled', 'fillColor'=>array(196, 196, 196), 'strokeColor'=>array(255, 255, 255));
                        if (isset($tag['attribute']['onclick']) AND !empty($tag['attribute']['onclick'])) {
                            $jsaction = $tag['attribute']['onclick'];
                        } else {
                            $jsaction = '';
                        }
                        $this->Button($name, $w, $h, $value, $jsaction, $prop, $opt, '', '', false);
                        break;
                    }
                }
                break;
            }
            case 'textarea': {
                $prop = array();
                $opt = array();
                if (isset($tag['attribute']['readonly']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['readonly'])) {
                    $prop['readonly'] = true;
                }
                if (isset($tag['attribute']['name']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['name'])) {
                    $name = $tag['attribute']['name'];
                } else {
                    break;
                }
                if (isset($tag['attribute']['value']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['value'])) {
                    $opt['v'] = $tag['attribute']['value'];
                }
                if (isset($tag['attribute']['cols']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['cols'])) {
                    $w = intval($tag['attribute']['cols']) * $this->GetStringWidth(chr(32)) * 2;
                } else {
                    $w = 40;
                }
                if (isset($tag['attribute']['rows']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['rows'])) {
                    $h = intval($tag['attribute']['rows']) * $this->getCellHeight($this->FontSize);
                } else {
                    $h = 10;
                }
                $prop['multiline'] = 'true';
                $this->TextField($name, $w, $h, $prop, $opt, '', '', false);
                break;
            }
            case 'select': {
                $h = $this->getCellHeight($this->FontSize);
                if (isset($tag['attribute']['size']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['size'])) {
                    $h *= ($tag['attribute']['size'] + 1);
                }
                $prop = array();
                $opt = array();
                if (isset($tag['attribute']['name']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['name'])) {
                    $name = $tag['attribute']['name'];
                } else {
                    break;
                }
                $w = 0;
                if (isset($tag['attribute']['opt']) AND !\TCPDF_STATIC::empty_string($tag['attribute']['opt'])) {
                    $options = explode('#!NwL!#', $tag['attribute']['opt']);
                    $values = array();
                    foreach ($options as $val) {
                        if (strpos($val, '#!TaB!#') !== false) {
                            $opts = explode('#!TaB!#', $val);
                            $values[] = $opts;
                            $w = max($w, $this->GetStringWidth($opts[1]));
                        } else {
                            $values[] = $val;
                            $w = max($w, $this->GetStringWidth($val));
                        }
                    }
                } else {
                    break;
                }
                $w *= 2;
                if (isset($tag['attribute']['multiple']) AND ($tag['attribute']['multiple']='multiple')) {
                    $prop['multipleSelection'] = 'true';
                    $this->ListBox($name, $w, $h, $values, $prop, $opt, '', '', false);
                } else {
                    $this->ComboBox($name, $w, $h, $values, $prop, $opt, '', '', false);
                }
                break;
            }
            case 'tcpdf': {
                if (defined('K_TCPDF_CALLS_IN_HTML') AND (K_TCPDF_CALLS_IN_HTML === true)) {
                    // Special tag used to call TCPDF methods
                    if (isset($tag['attribute']['method'])) {
                        $tcpdf_method = $tag['attribute']['method'];
                        if (method_exists($this, $tcpdf_method)) {
                            if (isset($tag['attribute']['params']) AND (!empty($tag['attribute']['params']))) {
                                $params = $this->unserializeTCPDFtagParameters($tag['attribute']['params']);
                                call_user_func_array(array($this, $tcpdf_method), $params);
                            } else {
                                $this->$tcpdf_method();
                            }
                            $this->newline = true;
                        }
                    }
                }
                break;
            }
            default: {
                break;
            }
        }
        // define tags that support borders and background colors
        $bordertags = array('blockquote','br','dd','dl','div','dt','h1','h2','h3','h4','h5','h6','hr','li','ol','p','pre','ul','tcpdf','table');
        if (in_array($tag['value'], $bordertags)) {
            // set border
            $dom[$key]['borderposition'] = $this->getBorderStartPosition();
        }
        if ($dom[$key]['self'] AND isset($dom[$key]['attribute']['pagebreakafter'])) {
            $pba = $dom[$key]['attribute']['pagebreakafter'];
            // check for pagebreak
            if (($pba == 'true') OR ($pba == 'left') OR ($pba == 'right')) {
                // add a page (or trig AcceptPageBreak() for multicolumn mode)
                $this->checkPageBreak($this->PageBreakTrigger + 1);
            }
            if ((($pba == 'left') AND (((!$this->rtl) AND (($this->page % 2) == 0)) OR (($this->rtl) AND (($this->page % 2) != 0))))
                    OR (($pba == 'right') AND (((!$this->rtl) AND (($this->page % 2) != 0)) OR (($this->rtl) AND (($this->page % 2) == 0))))) {
                // add a page (or trig AcceptPageBreak() for multicolumn mode)
                $this->checkPageBreak($this->PageBreakTrigger + 1);
            }
        }
        return $dom;
    }


    /**
     * Returns the HTML DOM array.
     * @param $html (string) html code
     * @return array
     * @protected
     * @since 3.2.000 (2008-06-20)
     */
    protected function getHtmlDomArray($html) {
        // array of CSS styles ( selector => properties).
        $css = array();
        // get CSS array defined at previous call
        $matches = array();
        if (preg_match_all('/<cssarray>([^\<]*)<\/cssarray>/isU', $html, $matches) > 0) {
            if (isset($matches[1][0])) {
                $css = array_merge($css, json_decode($this->unhtmlentities($matches[1][0]), true));
            }
            $html = preg_replace('/<cssarray>(.*?)<\/cssarray>/isU', '', $html);
        }
        // extract external CSS files
        $matches = array();
        if (preg_match_all('/<link([^\>]*)>/isU', $html, $matches) > 0) {
            foreach ($matches[1] as $key => $link) {
                $type = array();
                if (preg_match('/type[\s]*=[\s]*"text\/css"/', $link, $type)) {
                    $type = array();
                    preg_match('/media[\s]*=[\s]*"([^"]*)"/', $link, $type);
                    // get 'all' and 'print' media, other media types are discarded
                    // (all, braille, embossed, handheld, print, projection, screen, speech, tty, tv)
                    if (empty($type) OR (isset($type[1]) AND (($type[1] == 'all') OR ($type[1] == 'print')))) {
                        $type = array();
                        if (preg_match('/href[\s]*=[\s]*"([^"]*)"/', $link, $type) > 0) {
                            // read CSS data file
                            $cssdata = \TCPDF_STATIC::fileGetContents(trim($type[1]));
                            if (($cssdata !== FALSE) AND (strlen($cssdata) > 0)) {
                                $css = array_merge($css, \TCPDF_STATIC::extractCSSproperties($cssdata));
                            }
                        }
                    }
                }
            }
        }
        // extract style tags
        $matches = array();
        if (preg_match_all('/<style([^\>]*)>([^\<]*)<\/style>/isU', $html, $matches) > 0) {
            foreach ($matches[1] as $key => $media) {
                $type = array();
                preg_match('/media[\s]*=[\s]*"([^"]*)"/', $media, $type);
                // get 'all' and 'print' media, other media types are discarded
                // (all, braille, embossed, handheld, print, projection, screen, speech, tty, tv)
                if (empty($type) OR (isset($type[1]) AND (($type[1] == 'all') OR ($type[1] == 'print')))) {
                    $cssdata = $matches[2][$key];
                    $css = array_merge($css, \TCPDF_STATIC::extractCSSproperties($cssdata));
                }
            }
        }
        // create a special tag to contain the CSS array (used for table content)
        $csstagarray = '<cssarray>'.htmlentities(json_encode($css)).'</cssarray>';
        // remove head and style blocks
        $html = preg_replace('/<head([^\>]*)>(.*?)<\/head>/siU', '', $html);
        $html = preg_replace('/<style([^\>]*)>([^\<]*)<\/style>/isU', '', $html);
        // define block tags
        $blocktags = array('blockquote','br','dd','dl','div','dt','h1','h2','h3','h4','h5','h6','hr','li','ol','p','pre','ul','tcpdf','table','tr','td');
        // define self-closing tags
        $selfclosingtags = array('area','base','basefont','br','hr','input','img','link','meta');
        // remove all unsupported tags (the line below lists all supported tags)
        $html = strip_tags($html, '<marker/><a><b><blockquote><body><br><br/><dd><del><div><dl><dt><em><font><form><h1><h2><h3><h4><h5><h6><hr><hr/><i><img><input><label><li><ol><option><p><pre><s><select><small><span><strike><strong><sub><sup><table><tablehead><tcpdf><td><textarea><th><thead><tr><tt><u><ul>');
        //replace some blank characters
        $html = preg_replace('/<pre/', '<xre', $html); // preserve pre tag
        $html = preg_replace('/<(table|tr|td|th|tcpdf|blockquote|dd|div|dl|dt|form|h1|h2|h3|h4|h5|h6|br|hr|li|ol|ul|p)([^\>]*)>[\n\r\t]+/', '<\\1\\2>', $html);
        $html = preg_replace('@(\r\n|\r)@', "\n", $html);
        $repTable = array("\t" => ' ', "\0" => ' ', "\x0B" => ' ', "\\" => "\\\\");
        $html = strtr($html, $repTable);
        $offset = 0;
        while (($offset < strlen($html)) AND ($pos = strpos($html, '</pre>', $offset)) !== false) {
            $html_a = substr($html, 0, $offset);
            $html_b = substr($html, $offset, ($pos - $offset + 6));
            while (preg_match("'<xre([^\>]*)>(.*?)\n(.*?)</pre>'si", $html_b)) {
                // preserve newlines on <pre> tag
                $html_b = preg_replace("'<xre([^\>]*)>(.*?)\n(.*?)</pre>'si", "<xre\\1>\\2<br />\\3</pre>", $html_b);
            }
            while (preg_match("'<xre([^\>]*)>(.*?)".$this->re_space['p']."(.*?)</pre>'".$this->re_space['m'], $html_b)) {
                // preserve spaces on <pre> tag
                $html_b = preg_replace("'<xre([^\>]*)>(.*?)".$this->re_space['p']."(.*?)</pre>'".$this->re_space['m'], "<xre\\1>\\2&nbsp;\\3</pre>", $html_b);
            }
            $html = $html_a.$html_b.substr($html, $pos + 6);
            $offset = strlen($html_a.$html_b);
        }
        $offset = 0;
        while (($offset < strlen($html)) AND ($pos = strpos($html, '</textarea>', $offset)) !== false) {
            $html_a = substr($html, 0, $offset);
            $html_b = substr($html, $offset, ($pos - $offset + 11));
            while (preg_match("'<textarea([^\>]*)>(.*?)\n(.*?)</textarea>'si", $html_b)) {
                // preserve newlines on <textarea> tag
                $html_b = preg_replace("'<textarea([^\>]*)>(.*?)\n(.*?)</textarea>'si", "<textarea\\1>\\2<TBR>\\3</textarea>", $html_b);
                $html_b = preg_replace("'<textarea([^\>]*)>(.*?)[\"](.*?)</textarea>'si", "<textarea\\1>\\2''\\3</textarea>", $html_b);
            }
            $html = $html_a.$html_b.substr($html, $pos + 11);
            $offset = strlen($html_a.$html_b);
        }
        $html = preg_replace('/([\s]*)<option/si', '<option', $html);
        $html = preg_replace('/<\/option>([\s]*)/si', '</option>', $html);
        $offset = 0;
        while (($offset < strlen($html)) AND ($pos = strpos($html, '</option>', $offset)) !== false) {
            $html_a = substr($html, 0, $offset);
            $html_b = substr($html, $offset, ($pos - $offset + 9));
            while (preg_match("'<option([^\>]*)>(.*?)</option>'si", $html_b)) {
                $html_b = preg_replace("'<option([\s]+)value=\"([^\"]*)\"([^\>]*)>(.*?)</option>'si", "\\2#!TaB!#\\4#!NwL!#", $html_b);
                $html_b = preg_replace("'<option([^\>]*)>(.*?)</option>'si", "\\2#!NwL!#", $html_b);
            }
            $html = $html_a.$html_b.substr($html, $pos + 9);
            $offset = strlen($html_a.$html_b);
        }
        if (preg_match("'</select'si", $html)) {
            $html = preg_replace("'<select([^\>]*)>'si", "<select\\1 opt=\"", $html);
            $html = preg_replace("'#!NwL!#</select>'si", "\" />", $html);
        }
        $html = str_replace("\n", ' ', $html);
        // restore textarea newlines
        $html = str_replace('<TBR>', "\n", $html);
        // remove extra spaces from code
        $html = preg_replace('/[\s]+<\/(table|tr|ul|ol|dl)>/', '</\\1>', $html);
        $html = preg_replace('/'.$this->re_space['p'].'+<\/(td|th|li|dt|dd)>/'.$this->re_space['m'], '</\\1>', $html);
        $html = preg_replace('/[\s]+<(tr|td|th|li|dt|dd)/', '<\\1', $html);
        $html = preg_replace('/'.$this->re_space['p'].'+<(ul|ol|dl|br)/'.$this->re_space['m'], '<\\1', $html);
        $html = preg_replace('/<\/(table|tr|td|th|blockquote|dd|dt|dl|div|dt|h1|h2|h3|h4|h5|h6|hr|li|ol|ul|p)>[\s]+</', '</\\1><', $html);
        $html = preg_replace('/<\/(td|th)>/', '<marker style="font-size:0"/></\\1>', $html);
        $html = preg_replace('/<\/table>([\s]*)<marker style="font-size:0"\/>/', '</table>', $html);
        $html = preg_replace('/'.$this->re_space['p'].'+<img/'.$this->re_space['m'], chr(32).'<img', $html);
        $html = preg_replace('/<img([^\>]*)>[\s]+([^\<])/xi', '<img\\1>&nbsp;\\2', $html);
        $html = preg_replace('/<img([^\>]*)>/xi', '<img\\1><span><marker style="font-size:0"/></span>', $html);
        $html = preg_replace('/<xre/', '<pre', $html); // restore pre tag
        $html = preg_replace('/<textarea([^\>]*)>([^\<]*)<\/textarea>/xi', '<textarea\\1 value="\\2" />', $html);
        $html = preg_replace('/<li([^\>]*)><\/li>/', '<li\\1>&nbsp;</li>', $html);
        $html = preg_replace('/<li([^\>]*)>'.$this->re_space['p'].'*<img/'.$this->re_space['m'], '<li\\1><font size="1">&nbsp;</font><img', $html);
        $html = preg_replace('/<([^\>\/]*)>[\s]/', '<\\1>&nbsp;', $html); // preserve some spaces
        $html = preg_replace('/[\s]<\/([^\>]*)>/', '&nbsp;</\\1>', $html); // preserve some spaces
        $html = preg_replace('/<su([bp])/', '<zws/><su\\1', $html); // fix sub/sup alignment
        $html = preg_replace('/<\/su([bp])>/', '</su\\1><zws/>', $html); // fix sub/sup alignment
        $html = preg_replace('/'.$this->re_space['p'].'+/'.$this->re_space['m'], chr(32), $html); // replace multiple spaces with a single space
        // trim string
        $html = $this->stringTrim($html);
        // fix br tag after li
        $html = preg_replace('/<li><br([^\>]*)>/', '<li> <br\\1>', $html);
        // fix first image tag alignment
        $html = preg_replace('/^<img/', '<span style="font-size:0"><br /></span> <img', $html, 1);
        // pattern for generic tag
        $tagpattern = '/(<[^>]+>)/';
        // explodes the string
        $a = preg_split($tagpattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        // count elements
        $maxel = count($a);
        $elkey = 0;
        $key = 0;

        //Font size
        $fontsize = null;
        if (strpos($a[0], 'h3') !== false) {
            $fontsize = 26.25;
        } else if (strpos($a[0], 'h4') !== false) {
            $fontsize = 22.5;
        } else if (strpos($a[0], 'h5') !== false) {
            $fontsize = 18.75;
        }

        // create an array of elements
        $dom = array();
        $dom[$key] = array();
        // set inheritable properties fot the first void element
        // possible inheritable properties are: azimuth, border-collapse, border-spacing, caption-side, color, cursor, direction, empty-cells, font, font-family, font-stretch, font-size, font-size-adjust, font-style, font-variant, font-weight, letter-spacing, line-height, list-style, list-style-image, list-style-position, list-style-type, orphans, page, page-break-inside, quotes, speak, speak-header, text-align, text-indent, text-transform, volume, white-space, widows, word-spacing
        $dom[$key]['tag'] = false;
        $dom[$key]['block'] = false;
        $dom[$key]['value'] = '';
        $dom[$key]['parent'] = 0;
        $dom[$key]['hide'] = false;
        $dom[$key]['fontname'] = $this->FontFamily;
        $dom[$key]['fontstyle'] =  $this->FontStyle;
        $dom[$key]['fontsize'] = $fontsize != null ? $fontsize : $this->FontSizePt;
        $dom[$key]['font-stretch'] = $this->font_stretching;
        $dom[$key]['letter-spacing'] = $this->font_spacing;
        $dom[$key]['stroke'] = $this->textstrokewidth;
        $dom[$key]['fill'] = (($this->textrendermode % 2) == 0);
        $dom[$key]['clip'] = ($this->textrendermode > 3);
        $dom[$key]['line-height'] = $this->cell_height_ratio;
        $dom[$key]['bgcolor'] = false;
        $dom[$key]['fgcolor'] = $this->fgcolor; // color
        $dom[$key]['strokecolor'] = $this->strokecolor;
        $dom[$key]['align'] = '';
        $dom[$key]['listtype'] = '';
        $dom[$key]['text-indent'] = 0;
        $dom[$key]['text-transform'] = '';
        $dom[$key]['border'] = array();
        $dom[$key]['dir'] = $this->rtl?'rtl':'ltr';
        $thead = false; // true when we are inside the THEAD tag
        ++$key;
        $level = array();
        array_push($level, 0); // root
        while ($elkey < $maxel) {
            $dom[$key] = array();
            $element = $a[$elkey];
            $dom[$key]['elkey'] = $elkey;
            if (preg_match($tagpattern, $element)) {
                // html tag
                $element = substr($element, 1, -1);
                // get tag name
                preg_match('/[\/]?([a-zA-Z0-9]*)/', $element, $tag);
                $tagname = strtolower($tag[1]);
                // check if we are inside a table header
                if ($tagname == 'thead') {
                    if ($element[0] == '/') {
                        $thead = false;
                    } else {
                        $thead = true;
                    }
                    ++$elkey;
                    continue;
                }
                $dom[$key]['tag'] = true;
                $dom[$key]['value'] = $tagname;
                if (in_array($dom[$key]['value'], $blocktags)) {
                    $dom[$key]['block'] = true;
                } else {
                    $dom[$key]['block'] = false;
                }
                if ($element[0] == '/') {
                    // *** closing html tag
                    $dom[$key]['opening'] = false;
                    $dom[$key]['parent'] = end($level);
                    array_pop($level);
                    $dom[$key]['hide'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['hide'];
                    $dom[$key]['fontname'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontname'];
                    $dom[$key]['fontstyle'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontstyle'];
                    $dom[$key]['fontsize'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fontsize'];
                    $dom[$key]['font-stretch'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['font-stretch'];
                    $dom[$key]['letter-spacing'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['letter-spacing'];
                    $dom[$key]['stroke'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['stroke'];
                    $dom[$key]['fill'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fill'];
                    $dom[$key]['clip'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['clip'];
                    $dom[$key]['line-height'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['line-height'];
                    $dom[$key]['bgcolor'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['bgcolor'];
                    $dom[$key]['fgcolor'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['fgcolor'];
                    $dom[$key]['strokecolor'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['strokecolor'];
                    $dom[$key]['align'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['align'];
                    $dom[$key]['text-transform'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['text-transform'];
                    $dom[$key]['dir'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['dir'];
                    if (isset($dom[($dom[($dom[$key]['parent'])]['parent'])]['listtype'])) {
                        $dom[$key]['listtype'] = $dom[($dom[($dom[$key]['parent'])]['parent'])]['listtype'];
                    }
                    // set the number of columns in table tag
                    if (($dom[$key]['value'] == 'tr') AND (!isset($dom[($dom[($dom[$key]['parent'])]['parent'])]['cols']))) {
                        $dom[($dom[($dom[$key]['parent'])]['parent'])]['cols'] = $dom[($dom[$key]['parent'])]['cols'];
                    }
                    if (($dom[$key]['value'] == 'td') OR ($dom[$key]['value'] == 'th')) {
                        $dom[($dom[$key]['parent'])]['content'] = $csstagarray;
                        for ($i = ($dom[$key]['parent'] + 1); $i < $key; ++$i) {
                            $dom[($dom[$key]['parent'])]['content'] .= stripslashes($a[$dom[$i]['elkey']]);
                        }
                        $key = $i;
                        // mark nested tables
                        $dom[($dom[$key]['parent'])]['content'] = str_replace('<table', '<table nested="true"', $dom[($dom[$key]['parent'])]['content']);
                        // remove thead sections from nested tables
                        $dom[($dom[$key]['parent'])]['content'] = str_replace('<thead>', '', $dom[($dom[$key]['parent'])]['content']);
                        $dom[($dom[$key]['parent'])]['content'] = str_replace('</thead>', '', $dom[($dom[$key]['parent'])]['content']);
                    }
                    // store header rows on a new table
                    if (($dom[$key]['value'] == 'tr') AND ($dom[($dom[$key]['parent'])]['thead'] === true)) {
                        if (\TCPDF_STATIC::empty_string($dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'])) {
                            $dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'] = $csstagarray.$a[$dom[($dom[($dom[$key]['parent'])]['parent'])]['elkey']];
                        }
                        for ($i = $dom[$key]['parent']; $i <= $key; ++$i) {
                            $dom[($dom[($dom[$key]['parent'])]['parent'])]['thead'] .= $a[$dom[$i]['elkey']];
                        }
                        if (!isset($dom[($dom[$key]['parent'])]['attribute'])) {
                            $dom[($dom[$key]['parent'])]['attribute'] = array();
                        }
                        // header elements must be always contained in a single page
                        $dom[($dom[$key]['parent'])]['attribute']['nobr'] = 'true';
                    }
                    if (($dom[$key]['value'] == 'table') AND (!\TCPDF_STATIC::empty_string($dom[($dom[$key]['parent'])]['thead']))) {
                        // remove the nobr attributes from the table header
                        $dom[($dom[$key]['parent'])]['thead'] = str_replace(' nobr="true"', '', $dom[($dom[$key]['parent'])]['thead']);
                        $dom[($dom[$key]['parent'])]['thead'] .= '</tablehead>';
                    }
                } else {
                    // *** opening or self-closing html tag
                    $dom[$key]['opening'] = true;
                    $dom[$key]['parent'] = end($level);
                    if ((substr($element, -1, 1) == '/') OR (in_array($dom[$key]['value'], $selfclosingtags))) {
                        // self-closing tag
                        $dom[$key]['self'] = true;
                    } else {
                        // opening tag
                        array_push($level, $key);
                        $dom[$key]['self'] = false;
                    }
                    // copy some values from parent
                    $parentkey = 0;
                    if ($key > 0) {
                        $parentkey = $dom[$key]['parent'];
                        $dom[$key]['hide'] = $dom[$parentkey]['hide'];
                        $dom[$key]['fontname'] = $dom[$parentkey]['fontname'];
                        $dom[$key]['fontstyle'] = $dom[$parentkey]['fontstyle'];
                        $dom[$key]['fontsize'] = $dom[$parentkey]['fontsize'];
                        $dom[$key]['font-stretch'] = $dom[$parentkey]['font-stretch'];
                        $dom[$key]['letter-spacing'] = $dom[$parentkey]['letter-spacing'];
                        $dom[$key]['stroke'] = $dom[$parentkey]['stroke'];
                        $dom[$key]['fill'] = $dom[$parentkey]['fill'];
                        $dom[$key]['clip'] = $dom[$parentkey]['clip'];
                        $dom[$key]['line-height'] = $dom[$parentkey]['line-height'];
                        $dom[$key]['bgcolor'] = $dom[$parentkey]['bgcolor'];
                        $dom[$key]['fgcolor'] = $dom[$parentkey]['fgcolor'];
                        $dom[$key]['strokecolor'] = $dom[$parentkey]['strokecolor'];
                        $dom[$key]['align'] = $dom[$parentkey]['align'];
                        $dom[$key]['listtype'] = $dom[$parentkey]['listtype'];
                        $dom[$key]['text-indent'] = $dom[$parentkey]['text-indent'];
                        $dom[$key]['text-transform'] = $dom[$parentkey]['text-transform'];
                        $dom[$key]['border'] = array();
                        $dom[$key]['dir'] = $dom[$parentkey]['dir'];
                    }
                    // get attributes
                    preg_match_all('/([^=\s]*)[\s]*=[\s]*"([^"]*)"/', $element, $attr_array, PREG_PATTERN_ORDER);
                    $dom[$key]['attribute'] = array(); // reset attribute array
                    foreach($attr_array[1] as $id => $name) {
                        $dom[$key]['attribute'][strtolower($name)] = $attr_array[2][$id];
                    }
                    if (!empty($css)) {
                        // merge CSS style to current style
                        list($dom[$key]['csssel'], $dom[$key]['cssdata']) = \TCPDF_STATIC::getCSSdataArray($dom, $key, $css);
                        $dom[$key]['attribute']['style'] = \TCPDF_STATIC::getTagStyleFromCSSarray($dom[$key]['cssdata']);
                    }
                    // split style attributes
                    if (isset($dom[$key]['attribute']['style']) AND !empty($dom[$key]['attribute']['style'])) {
                        // get style attributes
                        preg_match_all('/([^;:\s]*):([^;]*)/', $dom[$key]['attribute']['style'], $style_array, PREG_PATTERN_ORDER);
                        $dom[$key]['style'] = array(); // reset style attribute array
                        foreach($style_array[1] as $id => $name) {
                            // in case of duplicate attribute the last replace the previous
                            $dom[$key]['style'][strtolower($name)] = trim($style_array[2][$id]);
                        }
                        // --- get some style attributes ---
                        // text direction
                        if (isset($dom[$key]['style']['direction'])) {
                            $dom[$key]['dir'] = $dom[$key]['style']['direction'];
                        }
                        // display
                        if (isset($dom[$key]['style']['display'])) {
                            $dom[$key]['hide'] = (trim(strtolower($dom[$key]['style']['display'])) == 'none');
                        }
                        // font family
                        if (isset($dom[$key]['style']['font-family'])) {
                            $dom[$key]['fontname'] = $this->getFontFamilyName($dom[$key]['style']['font-family']);
                        }
                        // list-style-type
                        if (isset($dom[$key]['style']['list-style-type'])) {
                            $dom[$key]['listtype'] = trim(strtolower($dom[$key]['style']['list-style-type']));
                            if ($dom[$key]['listtype'] == 'inherit') {
                                $dom[$key]['listtype'] = $dom[$parentkey]['listtype'];
                            }
                        }
                        // text-indent
                        if (isset($dom[$key]['style']['text-indent'])) {
                            $dom[$key]['text-indent'] = $this->getHTMLUnitToUnits($dom[$key]['style']['text-indent']);
                            if ($dom[$key]['text-indent'] == 'inherit') {
                                $dom[$key]['text-indent'] = $dom[$parentkey]['text-indent'];
                            }
                        }
                        // text-transform
                        if (isset($dom[$key]['style']['text-transform'])) {
                            $dom[$key]['text-transform'] = $dom[$key]['style']['text-transform'];
                        }
                        // font size
                        if (isset($dom[$key]['style']['font-size'])) {
                            $fsize = trim($dom[$key]['style']['font-size']);
                            $dom[$key]['fontsize'] = $this->getHTMLFontUnits($fsize, $dom[0]['fontsize'], $dom[$parentkey]['fontsize'], 'pt');
                        }
                        // font-stretch
                        if (isset($dom[$key]['style']['font-stretch'])) {
                            $dom[$key]['font-stretch'] = $this->getCSSFontStretching($dom[$key]['style']['font-stretch'], $dom[$parentkey]['font-stretch']);
                        }
                        // letter-spacing
                        if (isset($dom[$key]['style']['letter-spacing'])) {
                            $dom[$key]['letter-spacing'] = $this->getCSSFontSpacing($dom[$key]['style']['letter-spacing'], $dom[$parentkey]['letter-spacing']);
                        }
                        // line-height (internally is the cell height ratio)
                        if (isset($dom[$key]['style']['line-height'])) {
                            $lineheight = trim($dom[$key]['style']['line-height']);
                            switch ($lineheight) {
                                // A normal line height. This is default
                                case 'normal': {
                                    $dom[$key]['line-height'] = $dom[0]['line-height'];
                                    break;
                                }
                                case 'inherit': {
                                    $dom[$key]['line-height'] = $dom[$parentkey]['line-height'];
                                }
                                default: {
                                    if (is_numeric($lineheight)) {
                                        // convert to percentage of font height
                                        $lineheight = ($lineheight * 100).'%';
                                    }
                                    $dom[$key]['line-height'] = $this->getHTMLUnitToUnits($lineheight, 1, '%', true);
                                    if (substr($lineheight, -1) !== '%') {
                                        if ($dom[$key]['fontsize'] <= 0) {
                                            $dom[$key]['line-height'] = 1;
                                        } else {
                                            $dom[$key]['line-height'] = (($dom[$key]['line-height'] - $this->cell_padding['T'] - $this->cell_padding['B']) / $dom[$key]['fontsize']);
                                        }
                                    }
                                }
                            }
                        }
                        // font style
                        if (isset($dom[$key]['style']['font-weight'])) {
                            if (strtolower($dom[$key]['style']['font-weight'][0]) == 'n') {
                                if (strpos($dom[$key]['fontstyle'], 'B') !== false) {
                                    $dom[$key]['fontstyle'] = str_replace('B', '', $dom[$key]['fontstyle']);
                                }
                            } elseif (strtolower($dom[$key]['style']['font-weight'][0]) == 'b') {
                                $dom[$key]['fontstyle'] .= 'B';
                            }
                        }
                        if (isset($dom[$key]['style']['font-style']) AND (strtolower($dom[$key]['style']['font-style'][0]) == 'i')) {
                            $dom[$key]['fontstyle'] .= 'I';
                        }
                        // font color
                        if (isset($dom[$key]['style']['color']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['style']['color']))) {
                            $dom[$key]['fgcolor'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['style']['color'], $this->spot_colors);
                        } elseif ($dom[$key]['value'] == 'a') {
                            $dom[$key]['fgcolor'] = $this->htmlLinkColorArray;
                        }
                        // background color
                        if (isset($dom[$key]['style']['background-color']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['style']['background-color']))) {
                            $dom[$key]['bgcolor'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['style']['background-color'], $this->spot_colors);
                        }
                        // text-decoration
                        if (isset($dom[$key]['style']['text-decoration'])) {
                            $decors = explode(' ', strtolower($dom[$key]['style']['text-decoration']));
                            foreach ($decors as $dec) {
                                $dec = trim($dec);
                                if (!\TCPDF_STATIC::empty_string($dec)) {
                                    if ($dec[0] == 'u') {
                                        // underline
                                        $dom[$key]['fontstyle'] .= 'U';
                                    } elseif ($dec[0] == 'l') {
                                        // line-through
                                        $dom[$key]['fontstyle'] .= 'D';
                                    } elseif ($dec[0] == 'o') {
                                        // overline
                                        $dom[$key]['fontstyle'] .= 'O';
                                    }
                                }
                            }
                        } elseif ($dom[$key]['value'] == 'a') {
                            $dom[$key]['fontstyle'] = $this->htmlLinkFontStyle;
                        }
                        // check for width attribute
                        if (isset($dom[$key]['style']['width'])) {
                            $dom[$key]['width'] = $dom[$key]['style']['width'];
                        }
                        // check for height attribute
                        if (isset($dom[$key]['style']['height'])) {
                            $dom[$key]['height'] = $dom[$key]['style']['height'];
                        }
                        // check for text alignment
                        if (isset($dom[$key]['style']['text-align'])) {
                            $dom[$key]['align'] = strtoupper($dom[$key]['style']['text-align'][0]);
                        }
                        // check for CSS border properties
                        if (isset($dom[$key]['style']['border'])) {
                            $borderstyle = $this->getCSSBorderStyle($dom[$key]['style']['border']);
                            if (!empty($borderstyle)) {
                                $dom[$key]['border']['LTRB'] = $borderstyle;
                            }
                        }
                        if (isset($dom[$key]['style']['border-color'])) {
                            $brd_colors = preg_split('/[\s]+/', trim($dom[$key]['style']['border-color']));
                            if (isset($brd_colors[3])) {
                                $dom[$key]['border']['L']['color'] = \TCPDF_COLORS::convertHTMLColorToDec($brd_colors[3], $this->spot_colors);
                            }
                            if (isset($brd_colors[1])) {
                                $dom[$key]['border']['R']['color'] = \TCPDF_COLORS::convertHTMLColorToDec($brd_colors[1], $this->spot_colors);
                            }
                            if (isset($brd_colors[0])) {
                                $dom[$key]['border']['T']['color'] = \TCPDF_COLORS::convertHTMLColorToDec($brd_colors[0], $this->spot_colors);
                            }
                            if (isset($brd_colors[2])) {
                                $dom[$key]['border']['B']['color'] = \TCPDF_COLORS::convertHTMLColorToDec($brd_colors[2], $this->spot_colors);
                            }
                        }
                        if (isset($dom[$key]['style']['border-width'])) {
                            $brd_widths = preg_split('/[\s]+/', trim($dom[$key]['style']['border-width']));
                            if (isset($brd_widths[3])) {
                                $dom[$key]['border']['L']['width'] = $this->getCSSBorderWidth($brd_widths[3]);
                            }
                            if (isset($brd_widths[1])) {
                                $dom[$key]['border']['R']['width'] = $this->getCSSBorderWidth($brd_widths[1]);
                            }
                            if (isset($brd_widths[0])) {
                                $dom[$key]['border']['T']['width'] = $this->getCSSBorderWidth($brd_widths[0]);
                            }
                            if (isset($brd_widths[2])) {
                                $dom[$key]['border']['B']['width'] = $this->getCSSBorderWidth($brd_widths[2]);
                            }
                        }
                        if (isset($dom[$key]['style']['border-style'])) {
                            $brd_styles = preg_split('/[\s]+/', trim($dom[$key]['style']['border-style']));
                            if (isset($brd_styles[3]) AND ($brd_styles[3]!='none')) {
                                $dom[$key]['border']['L']['cap'] = 'square';
                                $dom[$key]['border']['L']['join'] = 'miter';
                                $dom[$key]['border']['L']['dash'] = $this->getCSSBorderDashStyle($brd_styles[3]);
                                if ($dom[$key]['border']['L']['dash'] < 0) {
                                    $dom[$key]['border']['L'] = array();
                                }
                            }
                            if (isset($brd_styles[1])) {
                                $dom[$key]['border']['R']['cap'] = 'square';
                                $dom[$key]['border']['R']['join'] = 'miter';
                                $dom[$key]['border']['R']['dash'] = $this->getCSSBorderDashStyle($brd_styles[1]);
                                if ($dom[$key]['border']['R']['dash'] < 0) {
                                    $dom[$key]['border']['R'] = array();
                                }
                            }
                            if (isset($brd_styles[0])) {
                                $dom[$key]['border']['T']['cap'] = 'square';
                                $dom[$key]['border']['T']['join'] = 'miter';
                                $dom[$key]['border']['T']['dash'] = $this->getCSSBorderDashStyle($brd_styles[0]);
                                if ($dom[$key]['border']['T']['dash'] < 0) {
                                    $dom[$key]['border']['T'] = array();
                                }
                            }
                            if (isset($brd_styles[2])) {
                                $dom[$key]['border']['B']['cap'] = 'square';
                                $dom[$key]['border']['B']['join'] = 'miter';
                                $dom[$key]['border']['B']['dash'] = $this->getCSSBorderDashStyle($brd_styles[2]);
                                if ($dom[$key]['border']['B']['dash'] < 0) {
                                    $dom[$key]['border']['B'] = array();
                                }
                            }
                        }
                        $cellside = array('L' => 'left', 'R' => 'right', 'T' => 'top', 'B' => 'bottom');
                        foreach ($cellside as $bsk => $bsv) {
                            if (isset($dom[$key]['style']['border-'.$bsv])) {
                                $borderstyle = $this->getCSSBorderStyle($dom[$key]['style']['border-'.$bsv]);
                                if (!empty($borderstyle)) {
                                    $dom[$key]['border'][$bsk] = $borderstyle;
                                }
                            }
                            if (isset($dom[$key]['style']['border-'.$bsv.'-color'])) {
                                $dom[$key]['border'][$bsk]['color'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['style']['border-'.$bsv.'-color'], $this->spot_colors);
                            }
                            if (isset($dom[$key]['style']['border-'.$bsv.'-width'])) {
                                $dom[$key]['border'][$bsk]['width'] = $this->getCSSBorderWidth($dom[$key]['style']['border-'.$bsv.'-width']);
                            }
                            if (isset($dom[$key]['style']['border-'.$bsv.'-style'])) {
                                $dom[$key]['border'][$bsk]['dash'] = $this->getCSSBorderDashStyle($dom[$key]['style']['border-'.$bsv.'-style']);
                                if ($dom[$key]['border'][$bsk]['dash'] < 0) {
                                    $dom[$key]['border'][$bsk] = array();
                                }
                            }
                        }
                        // check for CSS padding properties
                        if (isset($dom[$key]['style']['padding'])) {
                            $dom[$key]['padding'] = $this->getCSSPadding($dom[$key]['style']['padding']);
                        } else {
                            $dom[$key]['padding'] = $this->cell_padding;
                        }
                        foreach ($cellside as $psk => $psv) {
                            if (isset($dom[$key]['style']['padding-'.$psv])) {
                                $dom[$key]['padding'][$psk] = $this->getHTMLUnitToUnits($dom[$key]['style']['padding-'.$psv], 0, 'px', false);
                            }
                        }
                        // check for CSS margin properties
                        if (isset($dom[$key]['style']['margin'])) {
                            $dom[$key]['margin'] = $this->getCSSMargin($dom[$key]['style']['margin']);
                        } else {
                            $dom[$key]['margin'] = $this->cell_margin;
                        }
                        foreach ($cellside as $psk => $psv) {
                            if (isset($dom[$key]['style']['margin-'.$psv])) {
                                $dom[$key]['margin'][$psk] = $this->getHTMLUnitToUnits(str_replace('auto', '0', $dom[$key]['style']['margin-'.$psv]), 0, 'px', false);
                            }
                        }
                        // check for CSS border-spacing properties
                        if (isset($dom[$key]['style']['border-spacing'])) {
                            $dom[$key]['border-spacing'] = $this->getCSSBorderMargin($dom[$key]['style']['border-spacing']);
                        }
                        // page-break-inside
                        if (isset($dom[$key]['style']['page-break-inside']) AND ($dom[$key]['style']['page-break-inside'] == 'avoid')) {
                            $dom[$key]['attribute']['nobr'] = 'true';
                        }
                        // page-break-before
                        if (isset($dom[$key]['style']['page-break-before'])) {
                            if ($dom[$key]['style']['page-break-before'] == 'always') {
                                $dom[$key]['attribute']['pagebreak'] = 'true';
                            } elseif ($dom[$key]['style']['page-break-before'] == 'left') {
                                $dom[$key]['attribute']['pagebreak'] = 'left';
                            } elseif ($dom[$key]['style']['page-break-before'] == 'right') {
                                $dom[$key]['attribute']['pagebreak'] = 'right';
                            }
                        }
                        // page-break-after
                        if (isset($dom[$key]['style']['page-break-after'])) {
                            if ($dom[$key]['style']['page-break-after'] == 'always') {
                                $dom[$key]['attribute']['pagebreakafter'] = 'true';
                            } elseif ($dom[$key]['style']['page-break-after'] == 'left') {
                                $dom[$key]['attribute']['pagebreakafter'] = 'left';
                            } elseif ($dom[$key]['style']['page-break-after'] == 'right') {
                                $dom[$key]['attribute']['pagebreakafter'] = 'right';
                            }
                        }
                    }
                    if (isset($dom[$key]['attribute']['display'])) {
                        $dom[$key]['hide'] = (trim(strtolower($dom[$key]['attribute']['display'])) == 'none');
                    }
                    if (isset($dom[$key]['attribute']['border']) AND ($dom[$key]['attribute']['border'] != 0)) {
                        $borderstyle = $this->getCSSBorderStyle($dom[$key]['attribute']['border'].' solid black');
                        if (!empty($borderstyle)) {
                            $dom[$key]['border']['LTRB'] = $borderstyle;
                        }
                    }
                    // check for font tag
                    if ($dom[$key]['value'] == 'font') {
                        // font family
                        if (isset($dom[$key]['attribute']['face'])) {
                            $dom[$key]['fontname'] = $this->getFontFamilyName($dom[$key]['attribute']['face']);
                        }
                        // font size
                        if (isset($dom[$key]['attribute']['size'])) {
                            if ($key > 0) {
                                if ($dom[$key]['attribute']['size'][0] == '+') {
                                    $dom[$key]['fontsize'] = $dom[($dom[$key]['parent'])]['fontsize'] + intval(substr($dom[$key]['attribute']['size'], 1));
                                } elseif ($dom[$key]['attribute']['size'][0] == '-') {
                                    $dom[$key]['fontsize'] = $dom[($dom[$key]['parent'])]['fontsize'] - intval(substr($dom[$key]['attribute']['size'], 1));
                                } else {
                                    $dom[$key]['fontsize'] = intval($dom[$key]['attribute']['size']);
                                }
                            } else {
                                $dom[$key]['fontsize'] = intval($dom[$key]['attribute']['size']);
                            }
                        }
                    }
                    // force natural alignment for lists
                    if ((($dom[$key]['value'] == 'ul') OR ($dom[$key]['value'] == 'ol') OR ($dom[$key]['value'] == 'dl'))
                            AND (!isset($dom[$key]['align']) OR \TCPDF_STATIC::empty_string($dom[$key]['align']) OR ($dom[$key]['align'] != 'J'))) {
                        if ($this->rtl) {
                            $dom[$key]['align'] = 'R';
                        } else {
                            $dom[$key]['align'] = 'L';
                        }
                    }
                    if (($dom[$key]['value'] == 'small') OR ($dom[$key]['value'] == 'sup') OR ($dom[$key]['value'] == 'sub')) {
                        if (!isset($dom[$key]['attribute']['size']) AND !isset($dom[$key]['style']['font-size'])) {
                            $dom[$key]['fontsize'] = $dom[$key]['fontsize'] * K_SMALL_RATIO;
                        }
                    }
                    if (($dom[$key]['value'] == 'strong') OR ($dom[$key]['value'] == 'b')) {
                        $dom[$key]['fontstyle'] .= 'B';
                    }
                    if (($dom[$key]['value'] == 'em') OR ($dom[$key]['value'] == 'i')) {
                        $dom[$key]['fontstyle'] .= 'I';
                    }
                    if ($dom[$key]['value'] == 'u') {
                        $dom[$key]['fontstyle'] .= 'U';
                    }
                    if (($dom[$key]['value'] == 'del') OR ($dom[$key]['value'] == 's') OR ($dom[$key]['value'] == 'strike')) {
                        $dom[$key]['fontstyle'] .= 'D';
                    }
                    if (!isset($dom[$key]['style']['text-decoration']) AND ($dom[$key]['value'] == 'a')) {
                        $dom[$key]['fontstyle'] = $this->htmlLinkFontStyle;
                    }
                    if (($dom[$key]['value'] == 'pre') OR ($dom[$key]['value'] == 'tt')) {
                        $dom[$key]['fontname'] = $this->default_monospaced_font;
                    }
                    if (!empty($dom[$key]['value']) AND ($dom[$key]['value'][0] == 'h') AND (intval($dom[$key]['value'][1]) > 0) AND (intval($dom[$key]['value'][1]) < 7)) {
                        // headings h1, h2, h3, h4, h5, h6
                        if (!isset($dom[$key]['attribute']['size']) AND !isset($dom[$key]['style']['font-size'])) {
                            $headsize = (4 - intval($dom[$key]['value'][1])) * 2;
                            $dom[$key]['fontsize'] = $dom[0]['fontsize'] + $headsize;
                        }
                        if (!isset($dom[$key]['style']['font-weight'])) {
                            $dom[$key]['fontstyle'] .= 'B';
                        }
                    }
                    if (($dom[$key]['value'] == 'table')) {
                        $dom[$key]['rows'] = 0; // number of rows
                        $dom[$key]['trids'] = array(); // IDs of TR elements
                        $dom[$key]['thead'] = ''; // table header rows
                    }
                    if (($dom[$key]['value'] == 'tr')) {
                        $dom[$key]['cols'] = 0;
                        if ($thead) {
                            $dom[$key]['thead'] = true;
                            // rows on thead block are printed as a separate table
                        } else {
                            $dom[$key]['thead'] = false;
                            // store the number of rows on table element
                            ++$dom[($dom[$key]['parent'])]['rows'];
                            // store the TR elements IDs on table element
                            array_push($dom[($dom[$key]['parent'])]['trids'], $key);
                        }
                    }
                    if (($dom[$key]['value'] == 'th') OR ($dom[$key]['value'] == 'td')) {
                        if (isset($dom[$key]['attribute']['colspan'])) {
                            $colspan = intval($dom[$key]['attribute']['colspan']);
                        } else {
                            $colspan = 1;
                        }
                        $dom[$key]['attribute']['colspan'] = $colspan;
                        $dom[($dom[$key]['parent'])]['cols'] += $colspan;
                    }
                    // text direction
                    if (isset($dom[$key]['attribute']['dir'])) {
                        $dom[$key]['dir'] = $dom[$key]['attribute']['dir'];
                    }
                    // set foreground color attribute
                    if (isset($dom[$key]['attribute']['color']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['attribute']['color']))) {
                        $dom[$key]['fgcolor'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['attribute']['color'], $this->spot_colors);
                    } elseif (!isset($dom[$key]['style']['color']) AND ($dom[$key]['value'] == 'a')) {
                        $dom[$key]['fgcolor'] = $this->htmlLinkColorArray;
                    }
                    // set background color attribute
                    if (isset($dom[$key]['attribute']['bgcolor']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['attribute']['bgcolor']))) {
                        $dom[$key]['bgcolor'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['attribute']['bgcolor'], $this->spot_colors);
                    }
                    // set stroke color attribute
                    if (isset($dom[$key]['attribute']['strokecolor']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['attribute']['strokecolor']))) {
                        $dom[$key]['strokecolor'] = \TCPDF_COLORS::convertHTMLColorToDec($dom[$key]['attribute']['strokecolor'], $this->spot_colors);
                    }
                    // check for width attribute
                    if (isset($dom[$key]['attribute']['width'])) {
                        $dom[$key]['width'] = $dom[$key]['attribute']['width'];
                    }
                    // check for height attribute
                    if (isset($dom[$key]['attribute']['height'])) {
                        $dom[$key]['height'] = $dom[$key]['attribute']['height'];
                    }
                    // check for text alignment
                    if (isset($dom[$key]['attribute']['align']) AND (!\TCPDF_STATIC::empty_string($dom[$key]['attribute']['align'])) AND ($dom[$key]['value'] !== 'img')) {
                        $dom[$key]['align'] = strtoupper($dom[$key]['attribute']['align'][0]);
                    }
                    // check for text rendering mode (the following attributes do not exist in HTML)
                    if (isset($dom[$key]['attribute']['stroke'])) {
                        // font stroke width
                        $dom[$key]['stroke'] = $this->getHTMLUnitToUnits($dom[$key]['attribute']['stroke'], $dom[$key]['fontsize'], 'pt', true);
                    }
                    if (isset($dom[$key]['attribute']['fill'])) {
                        // font fill
                        if ($dom[$key]['attribute']['fill'] == 'true') {
                            $dom[$key]['fill'] = true;
                        } else {
                            $dom[$key]['fill'] = false;
                        }
                    }
                    if (isset($dom[$key]['attribute']['clip'])) {
                        // clipping mode
                        if ($dom[$key]['attribute']['clip'] == 'true') {
                            $dom[$key]['clip'] = true;
                        } else {
                            $dom[$key]['clip'] = false;
                        }
                    }
                } // end opening tag
            } else {
                // text
                $dom[$key]['tag'] = false;
                $dom[$key]['block'] = false;
                $dom[$key]['parent'] = end($level);
                $dom[$key]['dir'] = $dom[$dom[$key]['parent']]['dir'];
                if (!empty($dom[$dom[$key]['parent']]['text-transform'])) {
                    // text-transform for unicode requires mb_convert_case (Multibyte String Functions)
                    if (function_exists('mb_convert_case')) {
                        $ttm = array('capitalize' => MB_CASE_TITLE, 'uppercase' => MB_CASE_UPPER, 'lowercase' => MB_CASE_LOWER);
                        if (isset($ttm[$dom[$dom[$key]['parent']]['text-transform']])) {
                            $element = mb_convert_case($element, $ttm[$dom[$dom[$key]['parent']]['text-transform']], $this->encoding);
                        }
                    } elseif (!$this->isunicode) {
                        switch ($dom[$dom[$key]['parent']]['text-transform']) {
                            case 'capitalize': {
                                $element = ucwords(strtolower($element));
                                break;
                            }
                            case 'uppercase': {
                                $element = strtoupper($element);
                                break;
                            }
                            case 'lowercase': {
                                $element = strtolower($element);
                                break;
                            }
                        }
                    }
                }
                $dom[$key]['value'] = stripslashes($this->unhtmlentities($element));
            }
            ++$elkey;
            ++$key;
        }
        return $dom;
    }


    /**
     * Add a htmlcomment marker to the specified page.
     *
     * @param int $pageno The page number to add markers to (starting at 0).
     * @param int $index The comment index.
     * @param int $x The x-coordinate of the marker (in pixels).
     * @param int $y The y-coordinate of the marker (in pixels).
     * @param string $rawtext The fill colour of the marker (red, yellow, green, blue, white, clear).
     * @return bool Success status.
     */
    public function add_htmlcomment($pageno, $width, $x, $y, $rawtext ) {
        if (!$this->filename) {
            return false;
        }
        $checklat = preg_match_all('/\\\\\\([\S\s]*?\\\\\\)/u',$rawtext, $latexs,PREG_SET_ORDER);
        if($checklat){
            foreach ($latexs as $latex){
                $replat = texify($latex[0], 300, 0.0,0.0,0.0, 1.0,1.0,1.0);
                $rawtext = str_replace($latex[0],$replat ,$rawtext);
            }
        }
        $x *= $this->scale;
        $y *= $this->scale;
        $width = 24 * $width;
        $this->setPage($pageno + 1);
        // Add the label.
        $this->writeHTMLCell($width, 0, $x, $y, $rawtext, 0, 0, false, true, 'C');
        return true;
    }

    /**
     * Add an annotation to the current page
     * @param int $sx starting x-coordinate (in pixels)
     * @param int $sy starting y-coordinate (in pixels)
     * @param int $ex ending x-coordinate (in pixels)
     * @param int $ey ending y-coordinate (in pixels)
     * @param string $colour optional the colour of the annotation (red, yellow, green, blue, white, black)
     * @param string $type optional the type of annotation (line, oval, rectangle, highlight, pen, stamp)
     * @param int[]|string $path optional for 'pen' annotations this is an array of x and y coordinates for
     *              the line, for 'stamp' annotations it is the name of the stamp file (without the path)
     * @param string $imagefolder - Folder containing stamp images.
     * @return bool true if successful (always)
     */
    public function add_annotation($sx, $sy, $ex, $ey, $colour = 'yellow', $type = 'line', $path, $imagefolder) {
        global $CFG;
        if (!$this->filename) {
            return false;
        }
        switch ($colour) {
            case 'yellow':
                $colourarray = array(255, 207, 53);
                break;
            case 'green':
                $colourarray = array(153, 202, 62);
                break;
            case 'blue':
                $colourarray = array(125, 159, 211);
                break;
            case 'white':
                $colourarray = array(255, 255, 255);
                break;
            case 'black':
                $colourarray = array(51, 51, 51);
                break;
            default: /* Red */
                $colour = 'red';
                $colourarray = array(239, 69, 64);
                break;
        }
        $this->SetDrawColorArray($colourarray);

        $sx *= $this->scale;
        $sy *= $this->scale;
        $ex *= $this->scale;
        $ey *= $this->scale;

        $this->SetLineWidth(3.0 * $this->scale);
        switch ($type) {
            case 'oval':
                $rx = abs($sx - $ex) / 2;
                $ry = abs($sy - $ey) / 2;
                $sx = min($sx, $ex) + $rx;
                $sy = min($sy, $ey) + $ry;

                // $rx and $ry should be >= min width and height
                if ($rx < self::MIN_ANNOTATION_WIDTH) {
                    $rx = self::MIN_ANNOTATION_WIDTH;
                }
                if ($ry < self::MIN_ANNOTATION_HEIGHT) {
                    $ry = self::MIN_ANNOTATION_HEIGHT;
                }

                $this->Ellipse($sx, $sy, $rx, $ry);
                break;
            case 'rectangle':
                $w = abs($sx - $ex);
                $h = abs($sy - $ey);
                $sx = min($sx, $ex);
                $sy = min($sy, $ey);

                // Width or height should be >= min width and height
                if ($w < self::MIN_ANNOTATION_WIDTH) {
                    $w = self::MIN_ANNOTATION_WIDTH;
                }
                if ($h < self::MIN_ANNOTATION_HEIGHT) {
                    $h = self::MIN_ANNOTATION_HEIGHT;
                }
                $this->Rect($sx, $sy, $w, $h);
                break;
            case 'highlight':
                $w = abs($sx - $ex);
                $h = 8.0 * $this->scale;
                $sx = min($sx, $ex);
                $sy = min($sy, $ey) + ($h * 0.5);
                $this->SetAlpha(0.5, 'Normal', 0.5, 'Normal');
                $this->SetLineWidth(8.0 * $this->scale);

                // width should be >= min width
                if ($w < self::MIN_ANNOTATION_WIDTH) {
                    $w = self::MIN_ANNOTATION_WIDTH;
                }

                $this->Rect($sx, $sy, $w, $h);
                $this->SetAlpha(1.0, 'Normal', 1.0, 'Normal');
                break;
            case 'pen':
                if ($path) {
                    $scalepath = array();
                    $points = preg_split('/[,:]/', $path);
                    foreach ($points as $point) {
                        $scalepath[] = intval($point) * $this->scale;
                    }

                    if (!empty($scalepath)) {
                        $this->PolyLine($scalepath, 'S');
                    }
                }
                break;
            case 'stamp':
                $imgfile = $imagefolder . '/' . clean_filename($path);
                $w = abs($sx - $ex);
                $h = abs($sy - $ey);
                $sx = min($sx, $ex);
                $sy = min($sy, $ey);

                // Stamp is always more than 40px, so no need to check width/height.
                $this->Image($imgfile, $sx, $sy, $w, $h);
                break;
            default: // Line.
                $this->Line($sx, $sy, $ex, $ey);
                break;
        }
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(1.0 * $this->scale);

        return true;
    }

    /**
     * Save the completed PDF to the given file
     * @param string $filename the filename for the PDF (including the full path)
     */
    public function save_pdf($filename) {
        $olddebug = error_reporting(0);
        $this->Output($filename, 'F');
        error_reporting($olddebug);
    }

    /**
     * Set the path to the folder in which to generate page image files
     * @param string $folder
     */
    public function set_image_folder($folder) {
        $this->imagefolder = $folder;
    }

    /**
     * Generate an image of the specified page in the PDF
     * @param int $pageno the page to generate the image of
     * @throws \moodle_exception
     * @throws \coding_exception
     * @return string the filename of the generated image
     */
    public function get_image($pageno) {
        global $CFG;

        if (!$this->filename) {
            throw new \coding_exception('Attempting to generate a page image without first setting the PDF filename');
        }

        if (!$this->imagefolder) {
            throw new \coding_exception('Attempting to generate a page image without first specifying the image output folder');
        }

        if (!is_dir($this->imagefolder)) {
            throw new \coding_exception('The specified image output folder is not a valid folder');
        }

        $imagefile = $this->imagefolder . '/' . self::IMAGE_PAGE . $pageno . '.png';
        $generate = true;
        if (file_exists($imagefile)) {
            if (filemtime($imagefile) > filemtime($this->filename)) {
                // Make sure the image is newer than the PDF file.
                $generate = false;
            }
        }

        if ($generate) {
            // Use ghostscript to generate an image of the specified page.
            $gsexec = \escapeshellarg($CFG->pathtogs);
            $imageres = \escapeshellarg(100);
            $imagefilearg = \escapeshellarg($imagefile);
            $filename = \escapeshellarg($this->filename);
            $pagenoinc = \escapeshellarg($pageno + 1);
            $command = "$gsexec -q -sDEVICE=png16m -dSAFER -dBATCH -dNOPAUSE -r$imageres -dFirstPage=$pagenoinc -dLastPage=$pagenoinc ".
                "-dDOINTERPOLATE -dGraphicsAlphaBits=4 -dTextAlphaBits=4 -sOutputFile=$imagefilearg $filename";

            $output = null;
            $result = exec($command, $output);
            if (!file_exists($imagefile)) {
                $fullerror = '<pre>'.get_string('command', 'assignfeedback_editpdf')."\n";
                $fullerror .= $command . "\n\n";
                $fullerror .= get_string('result', 'assignfeedback_editpdf')."\n";
                $fullerror .= htmlspecialchars($result) . "\n\n";
                $fullerror .= get_string('output', 'assignfeedback_editpdf')."\n";
                $fullerror .= htmlspecialchars(implode("\n", $output)) . '</pre>';
                throw new \moodle_exception('errorgenerateimage', 'assignfeedback_editpdf', '', $fullerror);
            }
        }

        return self::IMAGE_PAGE . $pageno . '.png';
    }

    /**
     * Check to see if PDF is version 1.4 (or below); if not: use ghostscript to convert it
     *
     * @param stored_file $file
     * @return string path to copy or converted pdf (false == fail)
     */
    public static function ensure_pdf_compatible(\stored_file $file) {
        global $CFG;

        // Copy the stored_file to local disk for checking.
        $temparea = make_request_directory();
        $tempsrc = $temparea . "/source.pdf";
        $file->copy_content_to($tempsrc);

        return self::ensure_pdf_file_compatible($tempsrc);
    }

    /**
     * Check to see if PDF is version 1.4 (or below); if not: use ghostscript to convert it
     *
     * @param   string $tempsrc The path to the file on disk.
     * @return  string path to copy or converted pdf (false == fail)
     */
    public static function ensure_pdf_file_compatible($tempsrc) {
        global $CFG;

        $pdf = new pdf();
        $pagecount = 0;
        try {
            $pagecount = $pdf->load_pdf($tempsrc);
        } catch (\Exception $e) {
            // PDF was not valid - try running it through ghostscript to clean it up.
            $pagecount = 0;
        }
        $pdf->Close(); // PDF loaded and never saved/outputted needs to be closed.

        if ($pagecount > 0) {
            // PDF is already valid and can be read by tcpdf.
            return $tempsrc;
        }

        $temparea = make_request_directory();
        $tempdst = $temparea . "/target.pdf";

        $gsexec = \escapeshellarg($CFG->pathtogs);
        $tempdstarg = \escapeshellarg($tempdst);
        $tempsrcarg = \escapeshellarg($tempsrc);
        $command = "$gsexec -q -sDEVICE=pdfwrite -dBATCH -dNOPAUSE -sOutputFile=$tempdstarg $tempsrcarg";
        exec($command);
        if (!file_exists($tempdst)) {
            // Something has gone wrong in the conversion.
            return false;
        }

        $pdf = new pdf();
        $pagecount = 0;
        try {
            $pagecount = $pdf->load_pdf($tempdst);
        } catch (\Exception $e) {
            // PDF was not valid - try running it through ghostscript to clean it up.
            $pagecount = 0;
        }
        $pdf->Close(); // PDF loaded and never saved/outputted needs to be closed.

        if ($pagecount <= 0) {
            // Could not parse the converted pdf.
            return false;
        }

        return $tempdst;
    }

    /**
     * Generate an localised error image for the given pagenumber.
     *
     * @param string $errorimagefolder path of the folder where error image needs to be created.
     * @param int $pageno page number for which error image needs to be created.
     *
     * @return string File name
     * @throws \coding_exception
     */
    public static function get_error_image($errorimagefolder, $pageno) {
        global $CFG;

        $errorfile = $CFG->dirroot . self::BLANK_PDF;
        if (!file_exists($errorfile)) {
            throw new \coding_exception("Blank PDF not found", "File path" . $errorfile);
        }

        $tmperrorimagefolder = make_request_directory();

        $pdf = new pdf();
        $pdf->set_pdf($errorfile);
        $pdf->copy_page();
        $pdf->add_comment(get_string('errorpdfpage', 'assignfeedback_editpdf'), 250, 300, 200, "red");
        $generatedpdf = $tmperrorimagefolder . '/' . 'error.pdf';
        $pdf->save_pdf($generatedpdf);

        $pdf = new pdf();
        $pdf->set_pdf($generatedpdf);
        $pdf->set_image_folder($tmperrorimagefolder);
        $image = $pdf->get_image(0);
        $pdf->Close(); // PDF loaded and never saved/outputted needs to be closed.
        $newimg = self::IMAGE_PAGE . $pageno . '.png';

        copy($tmperrorimagefolder . '/' . $image, $errorimagefolder . '/' . $newimg);
        return $newimg;
    }

    /**
     * Test that the configured path to ghostscript is correct and working.
     * @param bool $generateimage - If true - a test image will be generated to verify the install.
     * @return \stdClass
     */
    public static function test_gs_path($generateimage = true) {
        global $CFG;

        $ret = (object)array(
            'status' => self::GSPATH_OK,
            'message' => null,
        );
        $gspath = $CFG->pathtogs;
        if (empty($gspath)) {
            $ret->status = self::GSPATH_EMPTY;
            return $ret;
        }
        if (!file_exists($gspath)) {
            $ret->status = self::GSPATH_DOESNOTEXIST;
            return $ret;
        }
        if (is_dir($gspath)) {
            $ret->status = self::GSPATH_ISDIR;
            return $ret;
        }
        if (!is_executable($gspath)) {
            $ret->status = self::GSPATH_NOTEXECUTABLE;
            return $ret;
        }

        if (!$generateimage) {
            return $ret;
        }

        $testfile = $CFG->dirroot.'/mod/assign/feedback/editpdf/tests/fixtures/testgs.pdf';
        if (!file_exists($testfile)) {
            $ret->status = self::GSPATH_NOTESTFILE;
            return $ret;
        }

        $testimagefolder = \make_temp_directory('assignfeedback_editpdf_test');
        $filepath = $testimagefolder . '/' . self::IMAGE_PAGE . '0.png';
        // Delete any previous test images, if they exist.
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $pdf = new pdf();
        $pdf->set_pdf($testfile);
        $pdf->set_image_folder($testimagefolder);
        try {
            $pdf->get_image(0);
        } catch (\moodle_exception $e) {
            $ret->status = self::GSPATH_ERROR;
            $ret->message = $e->getMessage();
        }
        $pdf->Close(); // PDF loaded and never saved/outputted needs to be closed.

        return $ret;
    }

    /**
     * If the test image has been generated correctly - send it direct to the browser.
     */
    public static function send_test_image() {
        global $CFG;
        header('Content-type: image/png');
        require_once($CFG->libdir.'/filelib.php');

        $testimagefolder = \make_temp_directory('assignfeedback_editpdf_test');
        $testimage = $testimagefolder . '/' . self::IMAGE_PAGE . '0.png';
        send_file($testimage, basename($testimage), 0);
        die();
    }

    /**
     * This function add an image file to PDF page.
     * @param \stored_file $imagestoredfile Image file to be added
     */
    public function add_image_page($imagestoredfile) {
        $imageinfo = $imagestoredfile->get_imageinfo();
        $imagecontent = $imagestoredfile->get_content();
        $this->currentpage++;
        $template = $this->importPage($this->currentpage);
        $size = $this->getTemplateSize($template);

        if ($imageinfo["width"] > $imageinfo["height"]) {
            if ($size['width'] < $size['height']) {
                $temp = $size['width'];
                $size['width'] = $size['height'];
                $size['height'] = $temp;
            }
        } else if ($imageinfo["width"] < $imageinfo["height"]) {
            if ($size['width'] > $size['height']) {
                $temp = $size['width'];
                $size['width'] = $size['height'];
                $size['height'] = $temp;
            }
        }
        $orientation = $size['orientation'];
        $this->SetHeaderMargin(0);
        $this->SetFooterMargin(0);
        $this->SetMargins(0, 0, 0, true);
        $this->setPrintFooter(false);
        $this->setPrintHeader(false);

        $this->AddPage($orientation, $size);
        $this->SetAutoPageBreak(false, 0);
        $this->Image('@' . $imagecontent, 0, 0, $size['w'], $size['h'],
            '', '', '', false, null, '', false, false, 0);
    }
}

