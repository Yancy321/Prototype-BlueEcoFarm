<?php
/*******************************************************************************
* FPDF                                                                          *
* Version: 1.86                                                                 *
* Date:    2023-07-10                                                           *
* Author:  Olivier PLATHEY                                                      *
* License: Public Domain                                                        *
*******************************************************************************/

define('FPDF_VERSION','1.86');

class FPDF
{
protected $page;               // current page number
protected $n;                  // current object number
protected $offsets;            // array of object offsets
protected $buffer;             // buffer holding in-memory PDF
protected $pages;              // array containing pages
protected $state;              // current document state
protected $compress;           // compression flag
protected $k;                  // scale factor (user unit -> point)
protected $DefOrientation;     // default orientation
protected $CurOrientation;     // current orientation
protected $StdPageSizes;       // standard page sizes
protected $DefPageSize;        // default page size
protected $CurPageSize;        // current page size
protected $CurRotation;        // current page rotation
protected $PageInfo;           // page-related data
protected $wPt,$hPt;           // dimensions of current page in points
protected $w,$h;               // dimensions of current page in user unit
protected $lMargin;            // left margin
protected $tMargin;            // top margin
protected $rMargin;            // right margin
protected $bMargin;            // page break margin
protected $cMargin;            // cell margin
protected $x,$y;               // current position in user unit
protected $lasth;              // height of last printed cell
protected $LineWidth;          // line width in user unit
protected $fontpath;           // path containing fonts
protected $CoreFonts;          // array of core font names
protected $fonts;              // array of used fonts
protected $FontFiles;          // array of font files
protected $encodings;          // array of encodings
protected $cmaps;              // array of ToUnicode CMaps
protected $FontFamily;         // current font family
protected $FontStyle;          // current font style
protected $underline;          // underlining flag
protected $CurrentFont;        // current font info
protected $FontSizePt;         // current font size in points
protected $FontSize;           // current font size in user unit
protected $DrawColor;          // commands for drawing color
protected $FillColor;          // commands for filling color
protected $TextColor;          // commands for text color
protected $ColorFlag;          // indicates whether fill and text colors are different
protected $WithAlpha;          // indicates whether alpha channel is used
protected $ws;                 // word spacing
protected $images;             // array of used images
protected $PageLinks;          // array of links in pages
protected $links;              // array of internal links
protected $AutoPageBreak;      // automatic page breaking
protected $PageBreakTrigger;   // threshold used to trigger page breaks
protected $InHeader;           // flag set when processing header
protected $InFooter;           // flag set when processing footer
protected $AliasNbPages;       // alias for total number of pages
protected $ZoomMode;           // zoom display mode
protected $LayoutMode;         // layout display mode
protected $metadata;           // document properties
protected $PDFVersion;         // PDF version number

function __construct($orientation='P', $unit='mm', $size='A4')
{
    // Font path
    if(defined('FPDF_FONTPATH'))
        $this->fontpath = FPDF_FONTPATH;
    elseif(is_dir(dirname(__FILE__).'/font'))
        $this->fontpath = dirname(__FILE__).'/font/';
    else
        $this->fontpath = '';

    // Core fonts
    $this->CoreFonts = array('courier','helvetica','helveticab','helveticabi','helveticai',
        'symbol','times','timesb','timesbi','timesi','zapfdingbats');

    // Scale factor
    if($unit=='pt')
        $this->k = 1;
    elseif($unit=='mm')
        $this->k = 72/25.4;
    elseif($unit=='cm')
        $this->k = 72/2.54;
    elseif($unit=='in')
        $this->k = 72;
    else
        $this->Error('Incorrect unit: '.$unit);

    // Page sizes
    $this->StdPageSizes = array('a3'=>array(841.89,1190.55),'a4'=>array(595.28,841.89),
        'a5'=>array(420.94,595.28),'letter'=>array(612,792),'legal'=>array(612,1008));
    $size = $this->_getpagesize($size);
    $this->DefPageSize = $size;
    $this->CurPageSize = $size;

    // Page orientation
    $orientation = strtolower($orientation);
    if($orientation=='p' || $orientation=='portrait') {
        $this->DefOrientation = 'P';
        $this->w = $size[0];
        $this->h = $size[1];
    } elseif($orientation=='l' || $orientation=='landscape') {
        $this->DefOrientation = 'L';
        $this->w = $size[1];
        $this->h = $size[0];
    } else {
        $this->Error('Incorrect orientation: '.$orientation);
    }
    $this->CurOrientation = $this->DefOrientation;
    $this->wPt = $this->w*$this->k;
    $this->hPt = $this->h*$this->k;

    // Page rotation
    $this->CurRotation = 0;

    // Page margins (1 cm)
    $margin = 28.35/$this->k;
    $this->SetMargins($margin,$margin);

    // Interior cell margin (1 mm)
    $this->cMargin = $margin/10;

    // Line width (0.2 mm)
    $this->LineWidth = .567/$this->k;

    // Automatic page break
    $this->SetAutoPageBreak(true,2*$margin);

    // Default display mode
    $this->SetDisplayMode('default');

    // Enable compression
    $this->SetCompression(true);

    // Metadata
    $this->metadata = array('Producer'=>'FPDF '.FPDF_VERSION);

    // Set default PDF version number
    $this->PDFVersion = '1.3';

    $this->page = 0;
    $this->n = 2;
    $this->buffer = '';
    $this->pages = array();
    $this->PageInfo = array();
    $this->fonts = array();
    $this->FontFiles = array();
    $this->encodings = array();
    $this->cmaps = array();
    $this->images = array();
    $this->links = array();
    $this->InHeader = false;
    $this->InFooter = false;
    $this->lasth = 0;
    $this->FontFamily = '';
    $this->FontStyle = '';
    $this->FontSizePt = 12;
    $this->underline = false;
    $this->DrawColor = '0 G';
    $this->FillColor = '0 g';
    $this->TextColor = '0 g';
    $this->ColorFlag = false;
    $this->WithAlpha = false;
    $this->ws = 0;
    $this->AliasNbPages = '';
    $this->ZoomMode = '';
    $this->LayoutMode = '';
    $this->state = 0;
    $this->offsets = array();
    $this->PageLinks = array();
    $this->compress = false;
}

function SetMargins($left, $top, $right=-1)
{
    $this->lMargin = $left;
    $this->tMargin = $top;
    if($right==-1)
        $right = $left;
    $this->rMargin = $right;
}

function SetLeftMargin($margin)
{
    $this->lMargin = $margin;
    if($this->page>0 && $this->x<$margin)
        $this->x = $margin;
}

function SetTopMargin($margin)
{
    $this->tMargin = $margin;
}

function SetRightMargin($margin)
{
    $this->rMargin = $margin;
}

function SetAutoPageBreak($auto, $margin=0)
{
    $this->AutoPageBreak = $auto;
    $this->bMargin = $margin;
    $this->PageBreakTrigger = $this->h-$margin;
}

function SetDisplayMode($zoom, $layout='default')
{
    $this->ZoomMode = $zoom;
    $this->LayoutMode = $layout;
}

function SetCompression($compress)
{
    $this->compress = ($compress && function_exists('gzcompress'));
}

function SetTitle($title, $isUTF8=false)
{
    $this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
}

function SetAuthor($author, $isUTF8=false)
{
    $this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
}

function SetSubject($subject, $isUTF8=false)
{
    $this->metadata['Subject'] = $isUTF8 ? $subject : utf8_encode($subject);
}

function SetKeywords($keywords, $isUTF8=false)
{
    $this->metadata['Keywords'] = $isUTF8 ? $keywords : utf8_encode($keywords);
}

function SetCreator($creator, $isUTF8=false)
{
    $this->metadata['Creator'] = $isUTF8 ? $creator : utf8_encode($creator);
}

function AliasNbPages($alias='{nb}')
{
    $this->AliasNbPages = $alias;
}

function Error($msg)
{
    // Fatal error
    throw new Exception('FPDF error: '.$msg);
}

function Close()
{
    if($this->state==3)
        return;
    if($this->page==0)
        $this->AddPage();
    $this->InFooter = true;
    $this->Footer();
    $this->InFooter = false;
    $this->_endpage();
    $this->_enddoc();
}

function AddPage($orientation='', $size='', $rotation=0)
{
    if($this->state==3)
        $this->Error('The document is closed');
    $family = $this->FontFamily;
    $style = $this->FontStyle.($this->underline ? 'U' : '');
    $fontsize = $this->FontSizePt;
    $lw = $this->LineWidth;
    $dc = $this->DrawColor;
    $fc = $this->FillColor;
    $tc = $this->TextColor;
    $cf = $this->ColorFlag;
    if($this->page>0) {
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
    }
    $this->_beginpage($orientation,$size,$rotation);
    $this->_out('2 J');
    $this->LineWidth = $lw;
    $this->_out(sprintf('%.2F w',$lw*$this->k));
    if($family)
        $this->SetFont($family,$style,$fontsize);
    $this->DrawColor = $dc;
    if($dc!='0 G')
        $this->_out($dc);
    $this->FillColor = $fc;
    if($fc!='0 g')
        $this->_out($fc);
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    $this->InHeader = true;
    $this->Header();
    $this->InHeader = false;
    if($this->LineWidth!=$lw) {
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->k));
    }
    if($family)
        $this->SetFont($family,$style,$fontsize);
    if($this->DrawColor!=$dc) {
        $this->DrawColor = $dc;
        $this->_out($dc);
    }
    if($this->FillColor!=$fc) {
        $this->FillColor = $fc;
        $this->_out($fc);
    }
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
}

function Header()
{
    // To be implemented in your own inherited class
}

function Footer()
{
    // To be implemented in your own inherited class
}

function PageNo()
{
    return $this->page;
}

function SetDrawColor($r, $g=-1, $b=-1)
{
    if(($r==0 && $g==0 && $b==0) || $g==-1)
        $this->DrawColor = sprintf('%.3F G',$r/255);
    else
        $this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
    if($this->page>0)
        $this->_out($this->DrawColor);
}

function SetFillColor($r, $g=-1, $b=-1)
{
    if(($r==0 && $g==0 && $b==0) || $g==-1)
        $this->FillColor = sprintf('%.3F g',$r/255);
    else
        $this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
    if($this->page>0)
        $this->_out($this->FillColor);
}

function SetTextColor($r, $g=-1, $b=-1)
{
    if(($r==0 && $g==0 && $b==0) || $g==-1)
        $this->TextColor = sprintf('%.3F g',$r/255);
    else
        $this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
}

function GetStringWidth($s)
{
    $s = (string)$s;
    $cw = &$this->CurrentFont['cw'];
    $w = 0;
    if($this->unifontSubset) {
        $unicode = $this->UTF8StringToArray($s);
        foreach($unicode as $char) {
            if(isset($cw[$char]))
                $w += $cw[$char];
            elseif(!empty($this->CurrentFont['desc']['MissingWidth']))
                $w += $this->CurrentFont['desc']['MissingWidth'];
            else
                $w += 500;
        }
    } else {
        $l = strlen($s);
        for($i=0;$i<$l;$i++)
            $w += $cw[ord($s[$i])];
    }
    return $w*$this->FontSize/1000;
}

function SetLineWidth($width)
{
    $this->LineWidth = $width;
    if($this->page>0)
        $this->_out(sprintf('%.2F w',$width*$this->k));
}

function Line($x1, $y1, $x2, $y2)
{
    $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',
        $x1*$this->k,($this->h-$y1)*$this->k,
        $x2*$this->k,($this->h-$y2)*$this->k));
}

function Rect($x, $y, $w, $h, $style='')
{
    if($style=='F')
        $op = 'f';
    elseif($style=='FD' || $style=='DF')
        $op = 'B';
    else
        $op = 'S';
    $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',
        $x*$this->k,($this->h-$y)*$this->k,
        $w*$this->k,-$h*$this->k,$op));
}

function AddFont($family, $style='', $file='', $uni=false)
{
    $family = strtolower($family);
    $style = strtoupper($style);
    if($style=='IB')
        $style = 'BI';
    if($file=='')
        $file = str_replace(' ','',$family).strtolower($style).'.php';
    $fontkey = $family.$style;
    if(isset($this->fonts[$fontkey]))
        return;
    if($uni) {
        // Unicode font loading (simplified)
        $this->Error('Unicode fonts not supported in this minimal build');
    } else {
        $info = $this->_loadfont($file);
        $info['i'] = count($this->fonts)+1;
        if(!empty($info['file'])) {
            if($info['type']=='TrueType')
                $this->FontFiles[$info['file']] = array('length1'=>$info['originalsize']);
            else
                $this->FontFiles[$info['file']] = array('length1'=>$info['size1'],'length2'=>$info['size2']);
        }
        $this->fonts[$fontkey] = $info;
    }
}

function SetFont($family, $style='', $size=0)
{
    if($family=='')
        $family = $this->FontFamily;
    else
        $family = strtolower($family);
    $style = strtoupper($style);
    if(strpos($style,'U')!==false) {
        $this->underline = true;
        $style = str_replace('U','',$style);
    } else {
        $this->underline = false;
    }
    if($style=='IB')
        $style = 'BI';
    if($size==0)
        $size = $this->FontSizePt;

    if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
        return;

    $fontkey = $family.$style;
    if(!isset($this->fonts[$fontkey])) {
        // Check if it's a core font
        if(in_array($family,array('courier','helvetica','times','symbol','zapfdingbats'))) {
            if($family=='times' || $family=='helvetica' || $family=='courier') {
                $name = array(''=>$family,'B'=>$family.'bold','I'=>$family.'oblique','BI'=>$family.'boldoblique');
                if($family=='times') {
                    $name = array(''=>'Times-Roman','B'=>'Times-Bold','I'=>'Times-Italic','BI'=>'Times-BoldItalic');
                } elseif($family=='helvetica') {
                    $name = array(''=>'Helvetica','B'=>'Helvetica-Bold','I'=>'Helvetica-Oblique','BI'=>'Helvetica-BoldOblique');
                } elseif($family=='courier') {
                    $name = array(''=>'Courier','B'=>'Courier-Bold','I'=>'Courier-Oblique','BI'=>'Courier-BoldOblique');
                }
            } else {
                $name = array(''=>ucfirst($family));
                $style = '';
                $fontkey = $family;
            }
            $cw = $this->_getcorefontwidths($family.$style);
            $this->fonts[$fontkey] = array(
                'i'      => count($this->fonts)+1,
                'type'   => 'Core',
                'name'   => isset($name[$style]) ? $name[$style] : $name[''],
                'up'     => -100,
                'ut'     => 50,
                'cw'     => $cw,
                'enc'    => '',
                'file'   => '',
            );
        } else {
            $this->Error('Undefined font: '.$family.' '.$style);
        }
    }
    $this->FontFamily = $family;
    $this->FontStyle = $style;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    $this->CurrentFont = &$this->fonts[$fontkey];
    $this->unifontSubset = false;
    if($this->page>0)
        $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function SetFontSize($size)
{
    if($this->FontSizePt==$size)
        return;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    if($this->page>0)
        $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function AddLink()
{
    $n = count($this->links)+1;
    $this->links[$n] = array(0,0);
    return $n;
}

function SetLink($link, $y=0, $page=-1)
{
    if($y==-1)
        $y = $this->y;
    if($page==-1)
        $page = $this->page;
    $this->links[$link] = array($page,$y);
}

function Link($x, $y, $w, $h, $link)
{
    $this->PageLinks[$this->page][] = array($x*$this->k,$this->hPt-$y*$this->k,$w*$this->k,$h*$this->k,$link);
}

function Text($x, $y, $txt)
{
    if(!isset($this->CurrentFont))
        $this->Error('No font has been set');
    $s = sprintf('BT %.2F %.2F Td (%s) Tj ET',
        $x*$this->k,($this->h-$y)*$this->k,
        $this->_escape($txt));
    if($this->underline && $txt!='')
        $s .= ' '.$this->_dounderline($x,$y,$txt);
    if($this->ColorFlag)
        $s = 'q '.$this->TextColor.' '.$s.' Q';
    $this->_out($s);
}

function AcceptPageBreak()
{
    return $this->AutoPageBreak;
}

function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
{
    $k = $this->k;
    if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
        $x = $this->x;
        $ws = $this->ws;
        if($ws>0) {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        $this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
        $this->x = $x;
        if($ws>0) {
            $this->ws = $ws;
            $this->_out(sprintf('%.3F Tw',$ws*$k));
        }
    }
    if($w==0)
        $w = $this->w-$this->rMargin-$this->x;
    $s = '';
    if($fill || $border==1) {
        if($fill)
            $op = ($border==1) ? 'B' : 'f';
        else
            $op = 'S';
        $s = sprintf('%.2F %.2F %.2F %.2F re %s ',
            $this->x*$k,($this->h-$this->y)*$k,
            $w*$k,-$h*$k,$op);
    }
    if(is_string($border)) {
        $x = $this->x;
        $y = $this->y;
        if(strpos($border,'L')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'T')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
        if(strpos($border,'R')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'B')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
    }
    if($txt!=='') {
        if(!isset($this->CurrentFont))
            $this->Error('No font has been set');
        if($align=='R')
            $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
        elseif($align=='C')
            $dx = ($w-$this->GetStringWidth($txt))/2;
        else
            $dx = $this->cMargin;
        if($this->ColorFlag)
            $s .= 'q '.$this->TextColor.' ';
        $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',
            ($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,
            $this->_escape($txt));
        if($this->underline)
            $s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
        if($this->ColorFlag)
            $s .= ' Q';
        if($link)
            $this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
    }
    if($s)
        $this->_out($s);
    $this->lasth = $h;
    if($ln>0) {
        $this->y += $h;
        if($ln==1)
            $this->x = $this->lMargin;
    } else {
        $this->x += $w;
    }
}

function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
{
    if(!isset($this->CurrentFont))
        $this->Error('No font has been set');
    $cw = &$this->CurrentFont['cw'];
    if($w==0)
        $w = $this->w-$this->rMargin-$this->x;
    $wmax = ($w-2*$this->cMargin);
    $s = str_replace("\r",'',(string)$txt);
    $nb = strlen($s);
    if($nb>0 && $s[$nb-1]=="\n")
        $nb--;
    $b = 0;
    if($border) {
        if($border==1) {
            $border = 'LTRB';
            $b = 'LRT';
            $b2 = 'LR';
        } else {
            $b2 = '';
            if(strpos($border,'L')!==false) $b2 .= 'L';
            if(strpos($border,'R')!==false) $b2 .= 'R';
            $b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
        }
    }
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $ns = 0;
    $nl = 1;
    while($i<$nb) {
        $c = $s[$i];
        if($c=="\n") {
            if($this->ws>0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            $i++;
            $sep = -1;
            $j = $i;
            $l = 0;
            $ns = 0;
            $nl++;
            if($border && $nl==2)
                $b = $b2;
            continue;
        }
        if($c==' ') {
            $sep = $i;
            $ls = $l;
            $ns++;
        }
        $l += isset($cw[ord($c)]) ? $cw[ord($c)] : 500;
        if($l>$wmax*1000/$this->FontSize) {
            if($sep==-1) {
                if($i==$j)
                    $i++;
                if($this->ws>0) {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            } else {
                if($align=='J') {
                    $this->ws = ($ns>1) ? ($wmax-$ls/1000*$this->FontSize)/($ns-1) : 0;
                    $this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
                }
                $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                $i = $sep+1;
            }
            $sep = -1;
            $j = $i;
            $l = 0;
            $ns = 0;
            $nl++;
            if($border && $nl==2)
                $b = $b2;
        } else {
            $i++;
        }
    }
    if($this->ws>0) {
        $this->ws = 0;
        $this->_out('0 Tw');
    }
    if($border && strpos($border,'B')!==false)
        $b .= 'B';
    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
    $this->x = $this->lMargin;
}

function Write($h, $txt, $link='')
{
    if(!isset($this->CurrentFont))
        $this->Error('No font has been set');
    $cw = &$this->CurrentFont['cw'];
    $w = $this->w-$this->rMargin-$this->x;
    $wmax = ($w-2*$this->cMargin);
    $s = str_replace("\r",'',(string)$txt);
    $nb = strlen($s);
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $nl = 1;
    while($i<$nb) {
        $c = $s[$i];
        if($c=="\n") {
            $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
            $i++;
            $sep = -1;
            $j = $i;
            $l = 0;
            if($nl==1) {
                $this->x = $this->lMargin;
                $w = $this->w-$this->rMargin-$this->x;
                $wmax = ($w-2*$this->cMargin);
            }
            $nl++;
            continue;
        }
        if($c==' ')
            $sep = $i;
        $l += isset($cw[ord($c)]) ? $cw[ord($c)] : 500;
        if($l>$wmax*1000/$this->FontSize) {
            if($sep==-1) {
                if($this->x>$this->lMargin) {
                    $this->x = $this->lMargin;
                    $this->y += $h;
                    $w = $this->w-$this->rMargin-$this->x;
                    $wmax = ($w-2*$this->cMargin);
                    $i++;
                    $nl++;
                    continue;
                }
                if($i==$j)
                    $i++;
                $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
            } else {
                $this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',false,$link);
                $i = $sep+1;
            }
            $sep = -1;
            $j = $i;
            $l = 0;
            if($nl==1) {
                $this->x = $this->lMargin;
                $w = $this->w-$this->rMargin-$this->x;
                $wmax = ($w-2*$this->cMargin);
            }
            $nl++;
        } else {
            $i++;
        }
    }
    if($i!=$j)
        $this->Cell($l/1000*$this->FontSize,$h,substr($s,$j),0,0,'',false,$link);
}

function Ln($h=null)
{
    $this->x = $this->lMargin;
    if($h===null)
        $this->y += $this->lasth;
    else
        $this->y += $h;
}

function GetX()
{
    return $this->x;
}

function SetX($x)
{
    if($x>=0)
        $this->x = $x;
    else
        $this->x = $this->w+$x;
}

function GetY()
{
    return $this->y;
}

function SetY($y, $resetX=true)
{
    if($y>=0)
        $this->y = $y;
    else
        $this->y = $this->h+$y;
    if($resetX)
        $this->x = $this->lMargin;
}

function SetXY($x, $y)
{
    $this->SetX($x);
    $this->SetY($y,false);
}

function Output($dest='', $name='', $isUTF8=false)
{
    $this->Close();
    if(strlen($name)==1 && strlen($dest)!=1) {
        $tmp = $dest;
        $dest = $name;
        $name = $tmp;
    }
    if($dest=='')
        $dest = 'I';
    if($name=='')
        $name = 'doc.pdf';
    switch(strtoupper($dest)) {
        case 'I':
            $this->_checkoutput();
            if(PHP_SAPI!='cli') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
            }
            echo $this->buffer;
            break;
        case 'D':
            $this->_checkoutput();
            header('Content-Type: application/x-download');
            header('Content-Disposition: attachment; filename="'.$name.'"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
            break;
        case 'F':
            if(!file_put_contents($name,$this->buffer))
                $this->Error('Unable to create output file: '.$name);
            break;
        case 'S':
            return $this->buffer;
        default:
            $this->Error('Incorrect output destination: '.$dest);
    }
    return '';
}

/*******************************************************************************
*                                                                               *
*                              Protected methods                                *
*                                                                               *
*******************************************************************************/

protected function _checkoutput()
{
    if(PHP_SAPI!='cli') {
        if(headers_sent($file,$line))
            $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
    }
    if(ob_get_length()) {
        if(preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents()))
            ob_clean();
        else
            $this->Error("Some data has already been output, can't send PDF file");
    }
}

protected function _getpagesize($size)
{
    if(is_string($size)) {
        $size = strtolower($size);
        if(!isset($this->StdPageSizes[$size]))
            $this->Error('Unknown page size: '.$size);
        $a = $this->StdPageSizes[$size];
        return array($a[0]/$this->k,$a[1]/$this->k);
    } else {
        if($size[0]>$size[1])
            return array($size[1],$size[0]);
        else
            return array($size[0],$size[1]);
    }
}

protected function _beginpage($orientation, $size, $rotation)
{
    $this->page++;
    $this->pages[$this->page] = '';
    $this->PageInfo[$this->page] = array();
    $this->state = 2;
    $this->x = $this->lMargin;
    $this->y = $this->tMargin;
    $this->FontFamily = '';
    if($orientation=='')
        $orientation = $this->DefOrientation;
    else
        $orientation = strtoupper($orientation[0]);
    if($size=='')
        $size = $this->DefPageSize;
    else
        $size = $this->_getpagesize($size);
    if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1]) {
        if($orientation=='P') {
            $this->w = $size[0];
            $this->h = $size[1];
        } else {
            $this->w = $size[1];
            $this->h = $size[0];
        }
        $this->wPt = $this->w*$this->k;
        $this->hPt = $this->h*$this->k;
        $this->PageBreakTrigger = $this->h-$this->bMargin;
        $this->CurOrientation = $orientation;
        $this->CurPageSize = $size;
    }
    if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
        $this->PageInfo[$this->page]['size'] = array($this->wPt,$this->hPt);
    if($rotation!=0) {
        if($rotation%90!=0)
            $this->Error('Incorrect rotation value: '.$rotation);
        $this->CurRotation = $rotation;
        $this->PageInfo[$this->page]['rotation'] = $rotation;
    }
}

protected function _endpage()
{
    $this->state = 1;
}

protected function _loadfont($font)
{
    include($this->fontpath.$font);
    if(!isset($name))
        $this->Error('Could not include font definition file');
    if(isset($enc))
        $enc = strtolower($enc);
    if(!isset($subsetted))
        $subsetted = false;
    return get_defined_vars();
}

protected function _escape($s)
{
    $s = str_replace('\\','\\\\',$s);
    $s = str_replace(')','\\)',$s);
    $s = str_replace('(','\\(',$s);
    $s = str_replace("\r","\\r",$s);
    return $s;
}

protected function _textstring($s)
{
    if(!$this->_isascii($s))
        $s = $this->_UTF8toUTF16($s);
    return '('.$this->_escape($s).')';
}

protected function _isascii($s)
{
    $nb = strlen($s);
    for($i=0;$i<$nb;$i++) {
        if(ord($s[$i])>127)
            return false;
    }
    return true;
}

protected function _UTF8toUTF16($s)
{
    $res = "\xFE\xFF";
    $nb = strlen($s);
    $i = 0;
    while($i<$nb) {
        $c1 = ord($s[$i++]);
        if($c1>=224) {
            $c2 = ord($s[$i++]);
            $c3 = ord($s[$i++]);
            $c = (($c1 & 0x0F)<<12) | (($c2 & 0x3F)<<6) | ($c3 & 0x3F);
        } elseif($c1>=192) {
            $c2 = ord($s[$i++]);
            $c = (($c1 & 0x1F)<<6) | ($c2 & 0x3F);
        } else {
            $c = $c1;
        }
        $res .= chr($c>>8).chr($c & 0xFF);
    }
    return $res;
}

protected function _dounderline($x, $y, $txt)
{
    $up = $this->CurrentFont['up'];
    $ut = $this->CurrentFont['ut'];
    $w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
    return sprintf('%.2F %.2F %.2F %.2F re f',
        $x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,
        $w*$this->k,-$ut/1000*$this->FontSize*$this->k);
}

protected function _out($s)
{
    if($this->state==2)
        $this->pages[$this->page] .= $s."\n";
    elseif($this->state==1)
        $this->_put($s);
    elseif($this->state==0)
        $this->Error('No page has been added yet');
    elseif($this->state==3)
        $this->Error('The document is closed');
}

protected function _put($s)
{
    $this->buffer .= $s."\n";
}

protected function _getoffset()
{
    return strlen($this->buffer);
}

protected function _newobj($n=null)
{
    if($n===null)
        $n = ++$this->n;
    $this->offsets[$n] = $this->_getoffset();
    $this->_put($n.' 0 obj');
    return $n;
}

protected function _putstream($data)
{
    $this->_put('stream');
    $this->_put($data);
    $this->_put('endstream');
}

protected function _putstreamobject($data)
{
    if($this->compress) {
        $entries = '/Filter /FlateDecode ';
        $data = gzcompress($data);
    } else {
        $entries = '';
    }
    $entries .= '/Length '.strlen($data);
    $this->_newobj();
    $this->_put('<<'.$entries.'>>');
    $this->_putstream($data);
    $this->_put('endobj');
}

protected function _putlinks($n)
{
    foreach($this->PageLinks[$n] as $pl) {
        $this->_newobj();
        $rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
        $s = '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
        if(is_string($pl[4]))
            $s .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
        else {
            $l = $this->links[$pl[4]];
            if(isset($this->PageInfo[$l[0]]['size']))
                $h = $this->PageInfo[$l[0]]['size'][1];
            else
                $h = ($this->DefOrientation=='P') ? $this->DefPageSize[1]*$this->k : $this->DefPageSize[0]*$this->k;
            $s .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',
                $this->PageInfo[$l[0]]['n'],$h-$l[1]*$this->k);
        }
        $this->_put($s);
        $this->_put('endobj');
    }
}

protected function _putpage($n)
{
    $this->_newobj();
    $this->_put('<</Type /Page');
    $this->_put('/Parent 1 0 R');
    if(isset($this->PageInfo[$n]['size']))
        $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageInfo[$n]['size'][0],$this->PageInfo[$n]['size'][1]));
    if(isset($this->PageInfo[$n]['rotation']))
        $this->_put('/Rotate '.$this->PageInfo[$n]['rotation']);
    $this->_put('/Resources 2 0 R');
    if(!empty($this->PageLinks[$n])) {
        $s = '/Annots [';
        foreach($this->PageLinks[$n] as $pl)
            $s .= $pl[5].' 0 R ';
        $s .= ']';
        $this->_put($s);
    }
    if($this->WithAlpha)
        $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
    $this->_put('/Contents '.($this->n+1).' 0 R>>');
    $this->_put('endobj');
    if(!empty($this->AliasNbPages))
        $this->pages[$n] = str_replace($this->AliasNbPages,$this->page,$this->pages[$n]);
    $this->_putstreamobject($this->pages[$n]);
}

protected function _putpages()
{
    $nb = $this->page;
    for($n=1;$n<=$nb;$n++) {
        $this->PageInfo[$n]['n'] = $this->n+1+2*($n-1);
        if(!empty($this->PageLinks[$n])) {
            $nbl = count($this->PageLinks[$n]);
            for($i=0;$i<$nbl;$i++)
                $this->PageLinks[$n][$i][5] = ++$this->n;
        }
    }
    for($n=1;$n<=$nb;$n++) {
        $this->_putpage($n);
        if(!empty($this->PageLinks[$n]))
            $this->_putlinks($n);
    }
    $this->_newobj(1);
    $this->_put('<</Type /Pages');
    $kids = '/Kids [';
    for($n=1;$n<=$nb;$n++)
        $kids .= $this->PageInfo[$n]['n'].' 0 R ';
    $this->_put($kids.']');
    $this->_put('/Count '.$nb);
    if($this->DefOrientation=='P') {
        $w = $this->DefPageSize[0];
        $h = $this->DefPageSize[1];
    } else {
        $w = $this->DefPageSize[1];
        $h = $this->DefPageSize[0];
    }
    $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$w*$this->k,$h*$this->k));
    $this->_put('>>');
    $this->_put('endobj');
}

protected function _putfonts()
{
    foreach($this->FontFiles as $file=>$info) {
        $this->_newobj();
        $this->_put('<</Length '.strlen($info['data']));
        if(substr($file,-2)=='.z')
            $this->_put('/Filter /FlateDecode');
        if(isset($info['length2']))
            $this->_put('/Length1 '.$info['length1'].' /Length2 '.$info['length2'].' /Length3 0');
        else
            $this->_put('/Length1 '.$info['length1']);
        $this->_put('>>');
        $this->_putstream($info['data']);
        $this->_put('endobj');
    }
    foreach($this->fonts as $k=>$font) {
        if(isset($font['type']) && $font['type']=='Core') {
            $this->fonts[$k]['n'] = $this->n+1;
            $this->_newobj();
            $this->_put('<</Type /Font');
            $this->_put('/BaseFont /'.$font['name']);
            $this->_put('/Subtype /Type1');
            if($font['name']!='Symbol' && $font['name']!='ZapfDingbats')
                $this->_put('/Encoding /WinAnsiEncoding');
            $this->_put('>>');
            $this->_put('endobj');
        } elseif(isset($font['type']) && ($font['type']=='Type1' || $font['type']=='TrueType')) {
            // Additional Type1 or TrueType font
            $this->fonts[$k]['n'] = $this->n+1;
            $this->_newobj();
            $this->_put('<</Type /Font');
            $this->_put('/BaseFont /'.$font['name']);
            $this->_put('/Subtype /'.$font['type']);
            $this->_put('/FirstChar 32 /LastChar 255');
            $this->_put('/Widths '.($this->n+1).' 0 R');
            $this->_put('/FontDescriptor '.($this->n+2).' 0 R');
            if($font['enc']) {
                if(isset($font['diff']))
                    $this->_put('/Encoding '.($this->n+3).' 0 R');
                else
                    $this->_put('/Encoding /WinAnsiEncoding');
            }
            $this->_put('>>');
            $this->_put('endobj');
            // Widths
            $this->_newobj();
            $cw = &$font['cw'];
            $s = '[';
            for($i=32;$i<=255;$i++)
                $s .= $cw[$i].' ';
            $this->_put($s.']');
            $this->_put('endobj');
            // Descriptor
            $this->_newobj();
            $s = '<</Type /FontDescriptor /FontName /'.$font['name'];
            foreach($font['desc'] as $k2=>$v)
                $s .= ' /'.$k2.' '.$v;
            if(!empty($font['file']))
                $s .= ' /FontFile'.($font['type']=='Type1' ? '' : '2').' '.$this->FontFiles[$font['file']]['n'].' 0 R';
            $this->_put($s.'>>');
            $this->_put('endobj');
            // Encoding
            if($font['enc'] && isset($font['diff'])) {
                $this->_newobj();
                $this->_put('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$font['diff'].']>>');
                $this->_put('endobj');
            }
        }
    }
}

protected function _putimages()
{
    foreach(array_keys($this->images) as $file) {
        $this->_putimage($this->images[$file]);
        unset($this->images[$file]['data']);
        unset($this->images[$file]['smask']);
    }
}

protected function _putimage(&$info)
{
    $this->_newobj();
    $info['n'] = $this->n;
    $this->_put('<</Type /XObject');
    $this->_put('/Subtype /Image');
    $this->_put('/Width '.$info['w']);
    $this->_put('/Height '.$info['h']);
    if($info['cs']=='Indexed')
        $this->_put('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal'])/3-1).' '.($this->n+1).' 0 R]');
    else {
        $this->_put('/ColorSpace /'.$info['cs']);
        if($info['cs']=='DeviceCMYK')
            $this->_put('/Decode [1 0 1 0 1 0 1 0]');
    }
    $this->_put('/BitsPerComponent '.$info['bpc']);
    if(isset($info['f']))
        $this->_put('/Filter /'.$info['f']);
    if(isset($info['dp']))
        $this->_put('/DecodeParms <<'.$info['dp'].'>>');
    if(isset($info['trns']) && is_array($info['trns'])) {
        $trns = '';
        for($i=0;$i<count($info['trns']);$i++)
            $trns .= $info['trns'][$i].' '.$info['trns'][$i].' ';
        $this->_put('/Mask ['.$trns.']');
    }
    if(isset($info['smask']))
        $this->_put('/SMask '.($this->n+1).' 0 R');
    $this->_put('/Length '.strlen($info['data']).'>>');
    $this->_putstream($info['data']);
    $this->_put('endobj');
    if(isset($info['smask'])) {
        $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];
        $smask = array('w'=>$info['w'],'h'=>$info['h'],'cs'=>'DeviceGray','bpc'=>8,'f'=>'FlateDecode','dp'=>$dp,'data'=>$info['smask']);
        $this->_putimage($smask);
    }
    if($info['cs']=='Indexed') {
        $this->_putstreamobject($info['pal']);
    }
}

protected function _putxobjectdict()
{
    foreach($this->images as $image)
        $this->_put('/I'.$image['i'].' '.$image['n'].' 0 R');
}

protected function _putresourcedict()
{
    $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
    $this->_put('/Font <<');
    foreach($this->fonts as $font)
        $this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
    $this->_put('>>');
    $this->_put('/XObject <<');
    $this->_putxobjectdict();
    $this->_put('>>');
}

protected function _putresources()
{
    $this->_putfonts();
    $this->_putimages();
    $this->_newobj(2);
    $this->_put('<<');
    $this->_putresourcedict();
    $this->_put('>>');
    $this->_put('endobj');
}

protected function _putinfo()
{
    $this->metadata['Producer'] = 'FPDF '.FPDF_VERSION;
    $this->metadata['CreationDate'] = 'D:'.@date('YmdHis');
    foreach($this->metadata as $key=>$value)
        $this->_put('/'.$key.' '.$this->_textstring($value));
}

protected function _putcatalog()
{
    $n = $this->PageInfo[1]['n'];
    $this->_put('/Type /Catalog');
    $this->_put('/Pages 1 0 R');
    if($this->ZoomMode=='fullpage')
        $this->_put('/OpenAction ['.$n.' 0 R /Fit]');
    elseif($this->ZoomMode=='fullwidth')
        $this->_put('/OpenAction ['.$n.' 0 R /FitH null]');
    elseif($this->ZoomMode=='real')
        $this->_put('/OpenAction ['.$n.' 0 R /XYZ null null 1]');
    elseif(!is_string($this->ZoomMode))
        $this->_put('/OpenAction ['.$n.' 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
    if($this->LayoutMode=='single')
        $this->_put('/PageLayout /SinglePage');
    elseif($this->LayoutMode=='continuous')
        $this->_put('/PageLayout /OneColumn');
    elseif($this->LayoutMode=='two')
        $this->_put('/PageLayout /TwoColumnLeft');
}

protected function _putheader()
{
    $this->_put('%PDF-'.$this->PDFVersion);
}

protected function _puttrailer()
{
    $this->_put('/Size '.($this->n+1));
    $this->_put('/Root '.$this->n.' 0 R');
    $this->_put('/Info '.($this->n-1).' 0 R');
}

protected function _enddoc()
{
    $this->_putheader();
    $this->_putpages();
    $this->_putresources();
    // Info
    $this->_newobj();
    $this->_put('<<');
    $this->_putinfo();
    $this->_put('>>');
    $this->_put('endobj');
    // Catalog
    $this->_newobj();
    $this->_put('<<');
    $this->_putcatalog();
    $this->_put('>>');
    $this->_put('endobj');
    // Cross-ref
    $offset = $this->_getoffset();
    $this->_put('xref');
    $this->_put('0 '.($this->n+1));
    $this->_put('0000000000 65535 f ');
    for($i=1;$i<=$this->n;$i++)
        $this->_put(sprintf('%010d 00000 n ',$this->offsets[$i]));
    // Trailer
    $this->_put('trailer');
    $this->_put('<<');
    $this->_puttrailer();
    $this->_put('>>');
    $this->_put('startxref');
    $this->_put($offset);
    $this->_put('%%EOF');
    $this->state = 3;
}

protected function _getcorefontwidths($fontkey)
{
    // Standard widths for core fonts (simplified - Helvetica/Times/Courier)
    $helvetica = array(
        0=>278,1=>278,2=>278,3=>278,4=>278,5=>278,6=>278,7=>278,8=>278,9=>278,10=>278,11=>278,12=>278,13=>278,14=>278,15=>278,
        16=>278,17=>278,18=>278,19=>278,20=>278,21=>278,22=>278,23=>278,24=>278,25=>278,26=>278,27=>278,28=>278,29=>278,30=>278,31=>278,
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,
        64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,
        80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,
        96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,
        111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,127=>350,
        128=>556,129=>350,130=>222,131=>556,132=>333,133=>1000,134=>556,135=>556,136=>333,137=>1000,138=>667,139=>333,140=>1000,141=>350,142=>611,143=>350,
        144=>350,145=>222,146=>222,147=>333,148=>333,149=>350,150=>556,151=>1000,152=>333,153=>1000,154=>500,155=>333,156=>944,157=>350,158=>500,159=>667,
        160=>278,161=>333,162=>556,163=>556,164=>556,165=>556,166=>260,167=>556,168=>333,169=>737,170=>370,171=>556,172=>584,173=>333,174=>737,175=>333,
        176=>400,177=>584,178=>333,179=>333,180=>333,181=>556,182=>537,183=>278,184=>333,185=>333,186=>365,187=>556,188=>834,189=>834,190=>834,191=>611,
        192=>667,193=>667,194=>667,195=>667,196=>667,197=>667,198=>1000,199=>722,200=>667,201=>667,202=>667,203=>667,204=>278,205=>278,206=>278,207=>278,
        208=>722,209=>722,210=>778,211=>778,212=>778,213=>778,214=>778,215=>584,216=>778,217=>722,218=>722,219=>722,220=>722,221=>667,222=>667,223=>611,
        224=>556,225=>556,226=>556,227=>556,228=>556,229=>556,230=>889,231=>500,232=>556,233=>556,234=>556,235=>556,236=>278,237=>278,238=>278,239=>278,
        240=>556,241=>556,242=>556,243=>556,244=>556,245=>556,246=>556,247=>584,248=>611,249=>556,250=>556,251=>556,252=>556,253=>500,254=>556,255=>500
    );
    $helveticab = $helvetica; // simplified - same widths
    $helveticai = $helvetica;
    $helveticabi = $helvetica;
    $courier = array_fill(0,256,600);
    $times = array(
        0=>250,1=>250,2=>250,3=>250,4=>250,5=>250,6=>250,7=>250,8=>250,9=>250,10=>250,11=>250,12=>250,13=>250,14=>250,15=>250,
        16=>250,17=>250,18=>250,19=>250,20=>250,21=>250,22=>250,23=>250,24=>250,25=>250,26=>250,27=>250,28=>250,29=>250,30=>250,31=>250,
        32=>250,33=>333,34=>408,35=>500,36=>500,37=>833,38=>778,39=>180,40=>333,41=>333,42=>500,43=>564,44=>250,45=>333,46=>250,47=>278,
        48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>278,59=>278,60=>564,61=>564,62=>564,63=>444,
        64=>921,65=>722,66=>667,67=>667,68=>722,69=>611,70=>556,71=>722,72=>722,73=>333,74=>389,75=>722,76=>611,77=>889,78=>722,79=>722,
        80=>556,81=>722,82=>667,83=>556,84=>611,85=>722,86=>722,87=>944,88=>722,89=>722,90=>611,91=>333,92=>278,93=>333,94=>469,95=>500,
        96=>333,97=>444,98=>500,99=>444,100=>500,101=>444,102=>333,103=>500,104=>500,105=>278,106=>278,107=>500,108=>278,109=>778,110=>500,
        111=>500,112=>500,113=>500,114=>333,115=>389,116=>278,117=>500,118=>500,119=>722,120=>500,121=>500,122=>444,123=>480,124=>200,125=>480,126=>541,127=>350,
        128=>500,129=>350,130=>333,131=>500,132=>444,133=>1000,134=>500,135=>500,136=>333,137=>1000,138=>556,139=>333,140=>889,141=>350,142=>611,143=>350,
        144=>350,145=>333,146=>333,147=>444,148=>444,149=>350,150=>500,151=>1000,152=>333,153=>980,154=>389,155=>333,156=>722,157=>350,158=>444,159=>722,
        160=>250,161=>333,162=>500,163=>500,164=>500,165=>500,166=>200,167=>500,168=>333,169=>760,170=>276,171=>500,172=>564,173=>333,174=>760,175=>333,
        176=>400,177=>564,178=>300,179=>300,180=>333,181=>500,182=>453,183=>250,184=>333,185=>300,186=>310,187=>500,188=>750,189=>750,190=>750,191=>444,
        192=>722,193=>722,194=>722,195=>722,196=>722,197=>722,198=>889,199=>667,200=>611,201=>611,202=>611,203=>611,204=>333,205=>333,206=>333,207=>333,
        208=>722,209=>722,210=>722,211=>722,212=>722,213=>722,214=>722,215=>564,216=>722,217=>722,218=>722,219=>722,220=>722,221=>722,222=>556,223=>500,
        224=>444,225=>444,226=>444,227=>444,228=>444,229=>444,230=>667,231=>444,232=>444,233=>444,234=>444,235=>444,236=>278,237=>278,238=>278,239=>278,
        240=>500,241=>500,242=>500,243=>500,244=>500,245=>500,246=>500,247=>564,248=>500,249=>500,250=>500,251=>500,252=>500,253=>500,254=>500,255=>500
    );
    $timesb = $times;
    $timesi = $times;
    $timesbi = $times;
    $symbol = array_fill(0,256,600);
    $zapfdingbats = array_fill(0,256,600);

    $fontkey = strtolower($fontkey);
    if(isset($$fontkey))
        return $$fontkey;
    return array_fill(0,256,600);
}

} // end class FPDF
