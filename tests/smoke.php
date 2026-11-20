<?php
/**
 * Headless smoke tests for ARES.
 *
 * Run from anywhere: php /path/to/tests/smoke.php
 * Each rendering case runs in its own child process because index.php
 * declares a class and functions at top level.
 */

$root = dirname(__DIR__);

// Child mode: render a single case and write the result to stdout.
if (($argv[1] ?? '') === '--render') {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';

    if (getenv('ARES_MODE') === 'post') {
        $_POST = array(
            'submit' => 'go',
            'language' => getenv('ARES_LANGUAGE'),
            'role' => 'Developer',
            'enter-short' => 'Acme',
            'enter-long' => 'Acme GmbH',
            'reference' => 'https://example.com/ad?a=1&b=2',
            'brevity' => getenv('ARES_BREVITY'),
            'emphasis' => getenv('ARES_EMPHASIS'),
        );
    } else {
        $_GET = array(
            'language' => getenv('ARES_LANGUAGE'),
            'brevity' => getenv('ARES_BREVITY'),
            'emphasis' => getenv('ARES_EMPHASIS'),
        );
    }

    ini_set('display_errors', 'stderr');
    error_reporting(E_ALL);

    ob_start();
    include $root . '/index.php';
    echo ob_get_clean();
    exit(0);
}

$errors = 0;

function escapeWorkflowValue(string $value): string
{
    return str_replace(array('%', "\r", "\n"), array('%25', '%0D', '%0A'), $value);
}

function reportError(string $message): void
{
    echo '::error::' . escapeWorkflowValue($message) . "\n";
}

function fail(string $message): void
{
    global $errors;
    $errors++;
    reportError($message);
}

function runCase(string $root, array $env, string $stdoutFile, string $stderrFile): array
{
    foreach ($env as $name => $value) {
        putenv($name . '=' . $value);
    }
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --render'
        . ' > ' . escapeshellarg($stdoutFile) . ' 2> ' . escapeshellarg($stderrFile);
    exec($command, $ignored, $exitCode);
    return array(
        'code' => $exitCode,
        'stdout' => (string) @file_get_contents($stdoutFile),
        'stderr' => trim((string) @file_get_contents($stderrFile)),
    );
}

// Lint the application files.
foreach (array('index.php', 'ares-config.php', 'tests/smoke.php') as $file) {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1', $lines, $code);
    if ($code !== 0) {
        fail('Syntax check failed for ' . $file . ': ' . implode(' ', $lines));
    }
    $lines = array();
}

// Dependencies and generated font assets must be present.
if (!is_file($root . '/vendor/autoload.php')) {
    fail('vendor/autoload.php is missing - run "composer install" first.');
}
$font = $root . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json';
if (!is_file($font)) {
    fail('Generated font assets are missing - run "composer install" first.');
}

if ($errors > 0) {
    echo '::notice::Smoke tests aborted: ' . $errors . " error(s)\n";
    exit(min(255, $errors));
}

$tmp = sys_get_temp_dir() . '/ares-smoke-' . getmypid();
@mkdir($tmp, 0700, true);

$cases = array(
    array('mode' => 'get', 'language' => 'english', 'emphasis' => 'it', 'brevity' => 'poe'),
    array('mode' => 'post', 'language' => 'english', 'emphasis' => 'it', 'brevity' => 'poe'),
    array('mode' => 'post', 'language' => 'german', 'emphasis' => 'general', 'brevity' => 'gibson'),
    array('mode' => 'post', 'language' => 'german', 'emphasis' => 'logophilia', 'brevity' => 'eliade'),
    array('mode' => 'post', 'language' => 'english', 'emphasis' => 'gaming', 'brevity' => 'poe'),
);

foreach ($cases as $i => $case) {
    $label = $case['mode'] . '/' . $case['language'] . '/' . $case['emphasis'] . '/' . $case['brevity'];
    $stdoutFile = $tmp . '/out-' . $i;
    $stderrFile = $tmp . '/err-' . $i;
    foreach (glob($root . '/output/*.pdf') ?: array() as $old) {
        @unlink($old);
    }

    $result = runCase($root, array(
        'ARES_MODE' => $case['mode'],
        'ARES_LANGUAGE' => $case['language'],
        'ARES_EMPHASIS' => $case['emphasis'],
        'ARES_BREVITY' => $case['brevity'],
    ), $stdoutFile, $stderrFile);

    if ($result['code'] !== 0) {
        fail($label . ': child process exited with code ' . $result['code']);
    }
    if ($result['stderr'] !== '') {
        fail($label . ': unexpected diagnostics: ' . str_replace("\n", ' | ', $result['stderr']));
    }
    if ($case['mode'] === 'post') {
        if (substr($result['stdout'], 0, 5) !== '%PDF-') {
            fail($label . ': streamed output is not a PDF');
        }
        if (strlen($result['stdout']) < 10000) {
            fail($label . ': PDF is suspiciously small (' . strlen($result['stdout']) . ' bytes)');
        }
        $saved = glob($root . '/output/*.pdf') ?: array();
        if (count($saved) !== 1 || substr((string) @file_get_contents($saved[0]), 0, 5) !== '%PDF-') {
            fail($label . ': PDF was not written correctly into output/');
        }
    } else {
        if (strpos($result['stdout'], '<form') === false || strpos($result['stdout'], 'CHECKED') === false) {
            fail($label . ': form page was not rendered correctly');
        }
    }
}

foreach (glob($root . '/output/*.pdf') ?: array() as $old) {
    @unlink($old);
}
@unlink($stdoutFile ?? '');
@unlink($stderrFile ?? '');
@rmdir($tmp);

if ($errors === 0) {
    echo '::notice::Smoke tests passed (' . count($cases) . " cases)\n";
} else {
    echo '::notice::Smoke tests finished with ' . $errors . " error(s)\n";
}
exit(min(255, $errors));
