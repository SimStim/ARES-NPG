<?php
// Collect every missing required extension before deciding whether to continue.
$requiredExtensions = array('ctype', 'curl', 'filter', 'gd', 'hash', 'json', 'mbstring', 'openssl', 'pcre', 'xml', 'zlib');
$missingExtensions = array();
foreach ($requiredExtensions as $extension)
    if (!extension_loaded($extension))
        $missingExtensions[] = $extension;
if (count($missingExtensions) > 0) {
    echo '<p><strong>ARES cannot start: missing required PHP extensions: '
        . htmlspecialchars(implode(', ', $missingExtensions)) . '</strong></p>';
    exit(1);
}

// All paths are anchored to this file's directory, not the current working directory.
define('ROOT', __DIR__);

// Escape values before placing them into HTML (form attributes or PDF markup).
function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!isset($_POST) || count($_POST) == 0 || !isset ($_POST["submit"])) {
    $index = file_get_contents(ROOT . '/index.html');
    $form = str_ireplace("::self", esc($_SERVER['PHP_SELF']), file_get_contents(ROOT . '/form.html'));
    if (isset($_GET)) {
        $form = (isset($_GET["language"])) ? str_ireplace("::language-" . $_GET["language"], " CHECKED", $form) : str_ireplace("::language-english", " CHECKED", $form);
        $form = (isset($_GET["brevity"])) ? str_ireplace("::brevity-" . $_GET["brevity"], " CHECKED", $form) : str_ireplace("::brevity-poe", " CHECKED", $form);
        $form = (isset($_GET["emphasis"])) ? str_ireplace("::em-" . $_GET["emphasis"], " CHECKED", $form) : str_ireplace("::em-it", " CHECKED", $form);
        $form = (isset($_GET["role"])) ? str_ireplace("::role", esc($_GET["role"]), $form) : str_ireplace("::role", "", $form);
        $form = (isset($_GET["enter-short"])) ? str_ireplace("::enter-short", esc($_GET["enter-short"]), $form) : str_ireplace("::enter-short", "", $form);
        $form = (isset($_GET["enter-long"])) ? str_ireplace("::enter-long", esc($_GET["enter-long"]), $form) : str_ireplace("::enter-long", "", $form);
        $form = (isset($_GET["reference"])) ? str_ireplace("::reference", esc($_GET["reference"]), $form) : str_ireplace("::reference", "", $form);
    }
    $form = str_ireplace("::language-english", "", str_ireplace("::language-german", "", $form));
    $form = str_ireplace("::brevity-gibson", "", str_ireplace("::brevity-poe", "", str_ireplace("::brevity-eliade", "", $form)));
    $form = str_ireplace("::em-general", "", str_ireplace("::em-gaming", "", str_ireplace("::em-it", "", str_ireplace("::em-logophilia", "", $form))));
    $index = str_ireplace("::Stylesheet", "<style>" . file_get_contents(ROOT . "/stylesheet.css") . "</style>",
        str_ireplace("::Title", "ARES - Automatic Resume Generator", str_ireplace("::Body", $form, $index)));
    echo $index;
} else {
    require_once(ROOT . '/ares-config.php');
    require_once(ROOT . '/vendor/autoload.php');
    $config = array(
        "language" => ($_POST["language"] == "english") ? "english" : "german",
        "role" => ($_POST["role"] == "") ? "Dummy" : $_POST["role"],
        "enter-short" => ($_POST["enter-short"] == "") ? "Dummy" : $_POST["enter-short"],
        "enter-long" => ($_POST["enter-long"] == "") ? "Dummy" : $_POST["enter-long"],
        "brevity" => ($_POST["brevity"] == "") ? "poe" : $_POST["brevity"],
        "emphasis" => ($_POST["emphasis"] == "") ? "it" : $_POST["emphasis"],
        "reference" => ($_POST["reference"] == "") ? "Dummy" : $_POST["reference"],
        "version" => "1.8",
    );
    $linkSelf = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?role=" . urlencode($config["role"])
        . "&enter-short=" . urlencode($config["enter-short"]) . "&enter-long=" . urlencode($config["enter-long"])
        . "&language=" . urlencode($config["language"]) . "&brevity=" . urlencode($config["brevity"])
        . "&emphasis=" . urlencode($config["emphasis"]) . "&reference=" . urlencode($config["reference"]);

    class MYPDF extends TCPDF
    {
        public function Header(): void
        {
            global $config;
            $bMargin = $this->getBreakMargin(); // get the current page break margin
            $auto_page_break = $this->AutoPageBreak; // get current auto-page-break mode
            $this->SetAutoPageBreak(false, 0); // disable auto-page-break
            $bg_img = ($config["language"] == "english") ? ROOT . "/blocks/images/flag-uk.jpg" : ROOT . "/blocks/images/flag-de.jpg";
            $this->Image($bg_img, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
            $this->SetAutoPageBreak($auto_page_break, $bMargin); // restore auto-page-break status
            $this->setPageMark(); // set the starting point for the page content
        }
    }

    $qr_stl = array('border' => 1, 'vpadding' => 'auto', 'hpadding' => 'auto', 'fgcolor' => array(0, 0, 0),
        'bgcolor' => array(255, 255, 239), 'module_width' => 1.2, 'module_height' => 1.2); // set default style for qr code
    $qr_tpl = array('', 'QRCODE,H', '', '', '', '', $qr_stl, 'N'); // set qr parameter template array
    function qrLink($obj, $qrparams, $text, $link): string
    {
        $qrparams[0] = $link;
        return '<tcpdf data="' . $obj->serializeTCPDFtag('write2DBarcode', $qrparams) . '" />'
            . '<p><a href="' . esc($link) . '">' . $text . '</a></p>';
    }

    $pdfcss = "<style>" . file_get_contents(ROOT . "/pdfstylesheet.css") . "</style>";
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Ares - Automated RESume Generator');
    $pdf->SetTitle("Eduard-Pech-ARES-v" . $config["version"] . "-" . $config["enter-short"] . "-" . $config["language"]);
    $pdf->SetSubject('Resume for Eduard Pech for the role "' . $config["role"] . '" at ' . $config["enter-long"]);
    $pdf->SetKeywords('translation, English, German, information technology, IT, Computer Wizard Extraordinaire');
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->setFontSubsetting(true);
    $pdf->AddPage();
    $pdf->setJPEGQuality(75);
    if ($config["emphasis"] == "general") {
        $config["enter-short"] = ($config["language"] == "english") ? "your company" : "Ihrem Unternehmen";
        $pdf->Image(ROOT . "/blocks/images/eduard-general-assistant.jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
        $pdf->SetFont('times', '', 14, '', true);
        $pdf->writeHTMLCell(0, 0, '', 230, file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-summary-assistant.html"), 0, 1, 0, true, 'C', true);
        $pdf->writeHTMLCell(105, 0, '95', '18', file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-assistant.html"), 0, 1, 0, true, 'L', true);
        $pdf->AddPage();
        $pdf->Image(ROOT . "/blocks/images/eduard-general-" . $config["language"] . ".jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
    } else {
        $pdf->Image(ROOT . "/blocks/images/eduard-" . $config["emphasis"] . ".jpg", 0, 78.5, 210, 140, "", "https://www.linkedin.com/in/skyabovetheport/", "", true, 150, "TL", false, false, 0, false, false, false);
    }
    $pdf->SetFont('helvetica', '', 12, '', true);
    $pdf->writeHTMLCell(0, 0, '', 230, file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-summary-" . $config["emphasis"] . ".html"), 0, 1, 0, true, 'C', true);
    $pdf->writeHTMLCell(105, 0, '95', '18', file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-basicinfo.html"), 0, 1, 0, true, 'L', true);
    $pdf->SetFont('helvetica', '', 11, '', true);
    $pdf->writeHTMLCell(105, 0, '10', '42', file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-contact-" . $config["emphasis"] . ".html"), 0, 1, 0, true, 'R', true);
    $pdf->AddPage();
    $html = $pdfcss . file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-coverletter.html");
    $introhtml = ($config["emphasis"] == "general") ? $introhtml = "" : file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-coverletter-intro.html");
    $rndstuff = explode(PHP_EOL, trim(file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-items-coverletter.txt"), " \n\r\t\v\0"));
    shuffle($rndstuff);
    $size = count($rndstuff);
    $num = match ($config["brevity"]) {
        "gibson" => 1,
        "poe" => rand((int)($size * .25), (int)($size * .45)),
        default => rand((int)($size * .85), $size),
    };
    $rndhtml = "";
    for ($i = 0; $i < $num; $i++) {
        $rndhtml .= "<p>" . $rndstuff[$i] . "</p>";
    }
    $html = str_ireplace("::enter-long", esc($config["enter-long"]), str_ireplace("::enter-short", esc($config["enter-short"]),
        str_ireplace("::role", esc($config["role"]), str_ireplace("::randomstuff", $rndhtml,
            str_ireplace("::reference", esc($config["reference"]), str_ireplace("::intro", $introhtml, $html))))));
    $pdf->WriteHTML($html, true, false, true, false, "");
    $pdf->AddPage();
    $pdf->WriteHTML($pdfcss . file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-experience.html"), true, false, true, false, "");
    $pdf->AddPage();
    $pdf->WriteHTML($pdfcss . file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-education.html"), true, false, true, false, "");
    $pdf->AddPage();
    $html = $pdfcss . file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-skills.html");
    $skills = explode(PHP_EOL, trim(file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-items-skills.txt"), " \n\r\t\v\0"));
    natcasesort($skills);
    foreach ($skills as $value) {
        $skl = explode("::", $value)[0];
        $lnk = trim(explode("::", $value)[1], " \n\r\t\v\0");
        $html .= qrLink($pdf, $qr_tpl, $skl . "<br />($lnk)", $lnk);
    }
    $pdf->WriteHTML($html, true, false, true, false, "");
    $pdf->AddPage();
    $pdf->WriteHTML($pdfcss . file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-notes.html")
        . qrLink($pdf, $qr_tpl, str_ireplace("::reference", esc($config["reference"]), file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-reference.txt")), $config["reference"])
        . qrLink($pdf, $qr_tpl, str_ireplace("::linkself", esc($linkSelf), file_get_contents(ROOT . "/blocks/text/content-" . $config["language"] . "-linkself.txt")), $linkSelf)
        . "<p>ARES version: v" . $config["version"] . "</p>", true, false, true, false, "");
    $pdf->Output(ROOT . "/output/Eduard-Pech-ARES-v" . $config["version"] . "-" . $config["enter-short"] . "-" . $config["language"] . ".pdf", 'FI');
}
