<?php
/**
 * Kontrola verzií doplnkov Jupiter: hlavička „Version:“, konštanta *_VERSION a updates.json sa musia zhodovať.
 * Použitie: php .github/scripts/check-versions.php [značka]   (značka napr. j-invoices-v0.9.4 – overí aj ju)
 */
$root    = dirname( __DIR__, 2 );
$updates = json_decode( (string) file_get_contents( $root . '/updates.json' ), true );
$errors  = array();
$tag     = $argv[1] ?? '';

foreach ( glob( $root . '/plugins/j-*', GLOB_ONLYDIR ) as $dir ) {
	$slug = basename( $dir );
	$main = $dir . '/' . $slug . '.php';
	if ( ! is_file( $main ) ) {
		$errors[] = "$slug: chýba hlavný súbor $slug.php";
		continue;
	}
	$src = (string) file_get_contents( $main );
	preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $src, $h );
	preg_match( "/define\(\s*'[A-Z]+_VERSION',\s*'([^']+)'\s*\)/", $src, $c );
	$header = $h[1] ?? '';
	$const  = $c[1] ?? '';
	$json   = $updates['plugins'][ $slug ]['version'] ?? '';
	$pkg    = $updates['plugins'][ $slug ]['package'] ?? '';
	if ( '' === $header ) {
		$errors[] = "$slug: chýba hlavička Version:";
	}
	if ( $const !== $header ) {
		$errors[] = "$slug: konštanta verzie ($const) ≠ hlavička ($header)";
	}
	if ( $json !== $header ) {
		$errors[] = "$slug: updates.json ($json) ≠ hlavička ($header)";
	}
	$expect = "https://github.com/Aveyona/jupiter/releases/download/$slug-v$header/$slug-v$header.zip";
	if ( $pkg !== $expect ) {
		$errors[] = "$slug: package v updates.json má byť $expect";
	}
	if ( $tag && 0 === strpos( $tag, $slug . '-v' ) && $tag !== "$slug-v$header" ) {
		$errors[] = "značka $tag ≠ verzia doplnku $slug ($header)";
	}
}
if ( $tag && ! preg_match( '/^(j-[a-z0-9-]+)-v\d+(\.\d+)*$/', $tag, $m ) ) {
	$errors[] = "značka $tag nemá tvar <doplnok>-v<verzia>";
} elseif ( $tag && ! is_dir( $root . '/plugins/' . $m[1] ) ) {
	$errors[] = "značka $tag: doplnok {$m[1]} neexistuje";
}

if ( $errors ) {
	fwrite( STDERR, "Chyby verzií:\n  - " . implode( "\n  - ", $errors ) . "\n" );
	exit( 1 );
}
echo "Verzie sú v poriadku.\n";
