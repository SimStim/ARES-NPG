<?php
if (!isset($_POST) || count ($_POST) == 0 || !isset ($_POST["submit"])) {
    $index = file_get_contents('index.html');
	$form = str_ireplace("::self", $_SERVER['PHP_SELF'], file_get_contents('form.html'));
    if (isset($_GET)) {
        $form = (isset($_GET["language"])) ? str_ireplace("::language-" . $_GET["language"], " CHECKED", $form) : str_ireplace("::language-english", " CHECKED", $form);
        $form = (isset($_GET["brevity"])) ? str_ireplace("::brevity-" . $_GET["brevity"], " CHECKED", $form) : str_ireplace("::brevity-poe", " CHECKED", $form);
        $form = (isset($_GET["emphasis"])) ? str_ireplace("::em-" . $_GET["emphasis"], " CHECKED", $form) : str_ireplace("::em-it", " CHECKED", $form);
        $form = (isset($_GET["role"])) ? str_ireplace("::role", $_GET["role"], $form) : str_ireplace("::role", "", $form);
        $form = (isset($_GET["enter-short"])) ? str_ireplace("::enter-short", $_GET["enter-short"], $form) : str_ireplace("::enter-short", "", $form);
        $form = (isset($_GET["enter-long"])) ? str_ireplace("::enter-long", $_GET["enter-long"], $form) : str_ireplace("::enter-long", "", $form);
        $form = (isset($_GET["reference"])) ? str_ireplace("::reference", $_GET["reference"], $form) : str_ireplace("::reference", "", $form);
    }
    $form = str_ireplace("::language-english", "", str_ireplace("::language-german", "", $form));
    $form = str_ireplace("::brevity-gibson", "", str_ireplace("::brevity-poe", "", str_ireplace("::brevity-eliade", "", $form)));
    $form = str_ireplace("::em-general", "", str_ireplace("::em-gaming", "", str_ireplace("::em-it", "", str_ireplace("::em-logophilia", "", $form))));
    $index = str_ireplace("::Stylesheet", "<style>" . file_get_contents("stylesheet.css") . "</style>",
	         str_ireplace("::Title", "ARES - Automatic Resume Generator", str_ireplace("::Body", $form, $index)));
    echo $index;
} else {
    require_once ('tcpdf_include.php');
    $GLOBALS["language"] = ($_POST["language"] == "english") ? "english" : "german";
    $GLOBALS["role"] = ($_POST["role"] == "") ? "Dummy" : $_POST["role"];
    $GLOBALS["enter-short"] = ($_POST["enter-short"] == "") ? "Dummy" : $_POST["enter-short"];
    $GLOBALS["enter-long"] = ($_POST["enter-long"] == "") ? "Dummy" : $_POST["enter-long"];
    $GLOBALS["brevity"] = ($_POST["brevity"] == "") ? "poe" : $_POST["brevity"];
    $GLOBALS["emphasis"] = ($_POST["emphasis"] == "") ? "it" : $_POST["emphasis"];
    $GLOBALS["reference"] = ($_POST["reference"] == "") ? "Dummy" : $_POST["reference"];
    $GLOBALS["version"] = "1.6c";
    $linkSelf = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?role=" . urlencode($GLOBALS["role"])
              . "&enter-short=" . urlencode($GLOBALS["enter-short"]) . "&enter-long=" . urlencode($GLOBALS["enter-long"])
              . "&language=" . urlencode($GLOBALS["language"]) . "&brevity=" . urlencode($GLOBALS["brevity"])
              . "&emphasis=" . urlencode($GLOBALS["emphasis"]) . "&reference=" . urlencode($GLOBALS["reference"]);
    class MYPDF extends TCPDF {
        public function Header() {
            $bMargin = $this->getBreakMargin(); // get the current page break margin
            $auto_page_break = $this->AutoPageBreak; // get current auto-page-break mode
            $this->SetAutoPageBreak(false, 0); // disable auto-page-break
            $bg_img = ($GLOBALS["language"] == "english") ? "flag-uk.jpg" : "flag-de.jpg";
            $this->Image($bg_img, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
            $this->SetAutoPageBreak($auto_page_break, $bMargin); // restore auto-page-break status
            $this->setPageMark(); // set the starting point for the page content
        }
    }
    $qr_stl = array('border' => 1, 'vpadding' => 'auto', 'hpadding' => 'auto', 'fgcolor' => array(0, 0, 0),
                    'bgcolor' => array(255,255,239), 'module_width' => 1.2, 'module_height' => 1.2); // set default style for qr code
    $qr_tpl = array('', 'QRCODE,H', '', '', '', '', $qr_stl, 'N'); // set qr parameter template array
    function qrLink ($obj, $qrparams, $text, $link) {
        $qrparams[0] = $link;
		return '<tcpdf method="write2DBarcode" params="' . $obj->serializeTCPDFtagParameters($qrparams) . '" />'
		     . '<p><a href="' . $link . '">' . $text . '</a></p>';
    }
	$pdfcss = "<style>" . file_get_contents("pdfstylesheet.css") . "</style>";
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Ares - Automated RESume Generator');
    $pdf->SetTitle("Eduard-Pech-ARES-v" . $GLOBALS["version"] . "-" . $GLOBALS["enter-short"] . "-" . $GLOBALS["language"]);
    $pdf->SetSubject('Resume for Eduard Pech for the role "' . $GLOBALS["role"] . '" at ' . $GLOBALS["enter-long"]);
    $pdf->SetKeywords('translation, English, German, information technology, IT, Computer Wizard Extraordinaire');
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->setFontSubsetting(true);
    $pdf->AddPage();
    $pdf->setJPEGQuality(75);
    if ($GLOBALS["emphasis"]=="general") {
        $GLOBALS["enter-short"] = ($GLOBALS["language"]=="english") ? "your company" : "Ihrem Unternehmen";
        $pdf->Image("eduard-general-assistant.jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
        $pdf->SetFont('times', '', 14, '', true);
        $pdf->writeHTMLCell(0, 0, '', 230, file_get_contents("content-" . $GLOBALS["language"] . "-summary-assistant.html"), 0, 1, 0, true, 'C', true);
        $pdf->writeHTMLCell(105, 0, '95', '18', file_get_contents("content-" . $GLOBALS["language"] . "-assistant.html"), 0, 1, 0, true, 'L', true);
        $pdf->AddPage();
        $pdf->Image("eduard-general-" . $GLOBALS["language"] . ".jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
    }
    else {
        $pdf->Image("eduard-" . $GLOBALS["emphasis"] . ".jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
    }
    $pdf->SetFont('helvetica', '', 12, '', true);
    $pdf->writeHTMLCell(0, 0, '', 230, file_get_contents("content-" . $GLOBALS["language"] . "-summary-" . $GLOBALS["emphasis"] . ".html"), 0, 1, 0, true, 'C', true);
    $pdf->writeHTMLCell(105, 0, '95', '18', file_get_contents("content-" . $GLOBALS["language"] . "-basicinfo.html"), 0, 1, 0, true, 'L', true);
    $pdf->SetFont('helvetica', '', 11, '', true);
    $pdf->writeHTMLCell(105, 0, '10', '42', file_get_contents("content-" . $GLOBALS["language"] . "-contact-" . $GLOBALS["emphasis"] . ".html"), 0, 1, 0, true, 'R', true);
    $pdf->AddPage();
	$html = $pdfcss . file_get_contents("content-" . $GLOBALS["language"] . "-coverletter.html");
    $introhtml = ($GLOBALS["emphasis"]=="general") ? $introhtml = "" : file_get_contents("content-" . $GLOBALS["language"] . "-coverletter-intro.html");
    $rndstuff = explode(PHP_EOL, trim(file_get_contents("content-" . $GLOBALS["language"] . "-items-coverletter.txt"), " \n\r\t\v\0"));
    shuffle ($rndstuff);
    $size = count($rndstuff);
    switch ($GLOBALS["brevity"]) { case "gibson": $num = 1; break;
                                   case "poe": $num = rand((int) ((int) $size * .25), (int) ((int) $size * .45)); break;
                                   default: $num = rand((int) ((int) $size * .85), $size); }
    $rndhtml = "";
    for ($i = 0; $i < $num; $i++) { $rndhtml .= "<p>" . $rndstuff[$i] . "</p>"; }
    $html = str_ireplace("::enter-long", $GLOBALS["enter-long"], str_ireplace("::enter-short", $GLOBALS["enter-short"],
            str_ireplace("::role", $GLOBALS["role"], str_ireplace("::randomstuff", $rndhtml,
            str_ireplace("::reference", $GLOBALS["reference"], str_ireplace("::intro", $introhtml, $html))))));
	$pdf->WriteHTML($html, true, false, true, false, "");
    $pdf->AddPage();
	$pdf->WriteHTML($pdfcss . file_get_contents("content-" . $GLOBALS["language"] . "-experience.html"), true, false, true, false, "");
    $pdf->AddPage();
	$pdf->WriteHTML($pdfcss . file_get_contents("content-" . $GLOBALS["language"] . "-education.html"), true, false, true, false, "");
    $pdf->AddPage();
	$html = $pdfcss . file_get_contents("content-" . $GLOBALS["language"] . "-skills.html");
    $skills = explode(PHP_EOL, trim(file_get_contents("content-" . $GLOBALS["language"] . "-items-skills.txt"), " \n\r\t\v\0"));
    natcasesort ($skills);
    foreach ($skills as $value) { $skl = explode("::", $value)[0]; $lnk = trim(explode("::", $value)[1], " \n\r\t\v\0"); $html .= qrLink ($pdf, $qr_tpl, $skl . "<br />($lnk)", $lnk); }
	$pdf->WriteHTML($html, true, false, true, false, "");
    $pdf->AddPage();
	$pdf->WriteHTML($pdfcss . file_get_contents("content-" . $GLOBALS["language"] . "-notes.html")
	              . qrLink ($pdf, $qr_tpl, str_ireplace("::reference", $GLOBALS["reference"], file_get_contents("content-" . $GLOBALS["language"] . "-reference.txt")), $GLOBALS["reference"])
				  . qrLink ($pdf, $qr_tpl, str_ireplace("::linkself", $linkSelf, file_get_contents("content-" . $GLOBALS["language"] . "-linkself.txt")), $linkSelf)
				  . "<p>ARES version: v" . $GLOBALS["version"] . "</p>", true, false, true, false, "");
    $pdf->Output("Eduard-Pech-ARES-v" . $GLOBALS["version"] . "-" . $GLOBALS["enter-short"] . "-" . $GLOBALS["language"] . ".pdf", 'I');
}
?>
