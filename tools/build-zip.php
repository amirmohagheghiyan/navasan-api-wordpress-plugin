<?php
/**
 * Builds the installable plugin zip (development tool, not part of the zip).
 *
 * Usage: php tools/build-zip.php [output-dir]
 * Result: <output-dir>/navasan-api-wordpress-plugin-<version>.zip containing the
 * folder "navasan-api-wordpress-plugin/" without tests, tooling or dev files.
 */

$root   = dirname( __DIR__ );
$slug   = 'navasan-api-wordpress-plugin';
$outdir = isset( $argv[1] ) ? $argv[1] : $root . '/dist';

$main = file_get_contents( $root . '/' . $slug . '.php' );
if ( ! preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $m ) ) {
	fwrite( STDERR, "Cannot read version\n" );
	exit( 1 );
}
$version = $m[1];

$include = array(
	$slug . '.php',
	'uninstall.php',
	'README.md',
	'CHANGELOG.md',
	'LICENSE',
	'NOTICE',
	'includes',
	'assets',
);

if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0777, true ) ) {
	fwrite( STDERR, "Cannot create $outdir\n" );
	exit( 1 );
}
$zip_path = rtrim( $outdir, '/\\' ) . '/' . $slug . '-' . $version . '.zip';
if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create zip\n" );
	exit( 1 );
}
$count = 0;
foreach ( $include as $item ) {
	$path = $root . '/' . $item;
	if ( is_dir( $path ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
				$zip->addFile( $file->getPathname(), $slug . '/' . $rel );
				$count++;
			}
		}
	} elseif ( is_file( $path ) ) {
		$zip->addFile( $path, $slug . '/' . $item );
		$count++;
	}
}
$zip->close();
echo "Built $zip_path ($count files)\n";
