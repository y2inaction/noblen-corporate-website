<?php
/**
 * SVG logo sanitiser: a hostile-input suite. Needs no WordPress (the hooks the theme file registers are stubbed).
 * Usage: php tests-cli/svg-test.php
 */
define( 'ABSPATH', '/' );
function add_filter(): void {}
function add_action(): void {}
require dirname( __DIR__ ) . '/wordpress-theme/noblen-corporate/inc/svg.php';

$pass = 0;
$fail = 0;
function t( bool $ok, string $name, string $extra = '' ): void {
	global $pass, $fail;
	$ok ? ++$pass : ++$fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $extra ? '' : ' :: ' . $extra ) . "\n";
}
function clean( string $svg ): ?string {
	$e = '';
	return noblen_svg_sanitize( $svg, $e );
}
$ns  = 'xmlns="http://www.w3.org/2000/svg"';
$xl  = 'xmlns:xlink="http://www.w3.org/1999/xlink"';
function wrap( string $inner, string $attrs = '' ): string {
	global $ns, $xl;
	return "<svg $ns $xl viewBox=\"0 0 100 40\" $attrs>$inner</svg>";
}

// ---- Legitimate logos survive.
$good = wrap( '<title>Logo</title><defs><linearGradient id="g"><stop offset="0" stop-color="#0a1b3d"/><stop offset="1" stop-color="#1f6bff"/></linearGradient></defs><path d="M0 0h50v40H0z" fill="url(#g)"/><circle cx="70" cy="20" r="10" style="fill:#177443;stroke:none"/><text x="5" y="30" font-family="Arial" font-size="12">NOBLEN</text>' );
$out  = clean( $good );
t( null !== $out && str_contains( $out, 'viewBox="0 0 100 40"' ), 'a normal logo is kept with its viewBox' );
t( null !== $out && str_contains( $out, 'fill="url(#g)"' ) && str_contains( $out, '<linearGradient' ), 'gradients and local url(#id) fills are kept' );
t( null !== $out && str_contains( $out, '<path d="M0 0h50v40H0z"' ) && str_contains( $out, 'NOBLEN' ), 'paths and text are kept' );
t( null !== $out && str_contains( $out, 'fill:#177443' ), 'safe style declarations are kept' );
$css = clean( wrap( '<style>.a{fill:#fff;stroke:none}.b{fill:red}</style><rect class="a" width="10" height="10"/>' ) );
t( null !== $css && str_contains( $css, '.a{fill:#fff;stroke:none}' ), 'a simple class stylesheet is kept' );
t( null !== clean( "\xEF\xBB\xBF" . wrap( '<rect width="1" height="1"/>' ) ), 'a leading byte-order mark is tolerated' );
t( null !== clean( '<?xml version="1.0" encoding="UTF-8"?>' . wrap( '<rect width="1" height="1"/>' ) ), 'an XML declaration is accepted' );
t( null !== clean( wrap( '<defs><path id="p" d="M0 0h5"/></defs><use href="#p"/><use xlink:href="#p"/>' ) ), 'use of a local fragment is accepted' );

// ---- Hostile input is neutralised or refused.
$bad = array(
	'script element'              => wrap( '<script>alert(1)</script><rect width="1" height="1"/>' ),
	'script with CDATA'           => wrap( '<script><![CDATA[alert(1)]]></script><rect width="1" height="1"/>' ),
	'upper-case SCRIPT'           => wrap( '<SCRIPT>alert(1)</SCRIPT><rect width="1" height="1"/>' ),
	'namespaced script'           => wrap( '<s:script xmlns:s="http://www.w3.org/2000/svg">alert(1)</s:script><rect width="1" height="1"/>' ),
	'onload on the root'          => wrap( '<rect width="1" height="1"/>', 'onload="alert(1)"' ),
	'onclick on a path'           => wrap( '<path d="M0 0" onclick="alert(1)"/>' ),
	'onmouseover mixed case'      => wrap( '<path d="M0 0" OnMouseOver="alert(1)"/>' ),
	'javascript href on a'        => wrap( '<a href="javascript:alert(1)"><rect width="1" height="1"/></a>' ),
	'javascript href on use'      => wrap( '<use href="javascript:alert(1)"/>' ),
	'external use'                => wrap( '<use xlink:href="http://evil.example/x.svg#a"/>' ),
	'external image'              => wrap( '<image href="http://evil.example/p.png" width="5" height="5"/>' ),
	'data URI image'              => wrap( '<image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" width="5" height="5"/>' ),
	'foreignObject html'          => wrap( '<foreignObject width="10" height="10"><body xmlns="http://www.w3.org/1999/xhtml" onload="alert(1)"/></foreignObject>' ),
	'animate changing href'       => wrap( '<a><animate attributeName="href" values="javascript:alert(1)"/><rect width="1" height="1"/></a>' ),
	'set href'                    => wrap( '<set attributeName="href" to="javascript:alert(1)"/>' ),
	'iframe'                      => wrap( '<iframe src="http://evil.example"/>' ),
	'embed object'                => wrap( '<object data="http://evil.example"/><embed src="http://evil.example"/>' ),
	'style @import'               => wrap( '<style>@import url(http://evil.example/a.css);.a{fill:red}</style><rect class="a" width="1" height="1"/>' ),
	'style external url'          => wrap( '<style>.a{fill:url(http://evil.example/x)}</style><rect class="a" width="1" height="1"/>' ),
	'style attribute external url' => wrap( '<rect width="1" height="1" style="fill:url(http://evil.example/x)"/>' ),
	'style expression'            => wrap( '<rect width="1" height="1" style="width:expression(alert(1))"/>' ),
	'style behavior'              => wrap( '<rect width="1" height="1" style="behavior:url(x.htc)"/>' ),
	'fill url external'           => wrap( '<rect width="1" height="1" fill="url(http://evil.example/x#a)"/>' ),
	'attribute with markup'       => wrap( '<rect width="1" height="1" fill="&lt;script&gt;"/>' ),
	'xml-stylesheet instruction'  => '<?xml-stylesheet href="http://evil.example/a.css"?>' . wrap( '<rect width="1" height="1"/>' ),
	'php instruction'             => '<?php echo 1; ?>' . wrap( '<rect width="1" height="1"/>' ),
	'comment with script'         => wrap( '<!-- <script>alert(1)</script> --><rect width="1" height="1"/>' ),
);
foreach ( $bad as $name => $svg ) {
	$o = clean( $svg );
	// Either refused, or sanitised so that nothing dangerous survives.
	$danger = null !== $o && preg_match( '/<script|<foreignObject|<iframe|<object|<embed|<image|<animate|<set\b|javascript:|data:|\bon[a-z]+\s*=|evil\.example|expression|behavior|@import|<!--|<\?/i', $o );
	t( ! $danger, "hostile input neutralised: $name", (string) $o );
}

// ---- Link wrappers are unwrapped, not lost.
$unwrapped = (string) clean( wrap( '<a href="javascript:alert(1)" onclick="x()"><rect width="9" height="9" fill="#fff"/></a>' ) );
t( str_contains( $unwrapped, '<rect' ) && ! str_contains( $unwrapped, '<a' ) && ! str_contains( $unwrapped, 'javascript' ), 'an <a> wrapper is removed but its shapes are kept' );

// ---- Documents that must be refused outright.
$entity = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>' . wrap( '<text>&x;</text>' );
t( null === clean( $entity ), 'external entity (XXE) is refused' );
$laughs = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]>' . wrap( '<text>&b;</text>' );
t( null === clean( $laughs ), 'entity expansion (billion laughs) is refused' );
t( null === clean( '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . wrap( '' ) ), 'a DOCTYPE is refused' );
t( null === clean( '' ), 'an empty file is refused' );
t( null === clean( 'not xml at all' ), 'plain text is refused' );
t( null === clean( '<html><body><script>alert(1)</script></body></html>' ), 'an HTML document is refused' );
t( null === clean( '<svg viewBox="0 0 1 1"><rect width="1" height="1"/></svg>' ), 'an SVG without the SVG namespace is refused' );
t( null === clean( '<svg xmlns="http://example.com/other"><rect/></svg>' ), 'a different root namespace is refused' );
t( null === clean( '<svg ' . $ns . '><rect></svg>' ), 'malformed XML is refused' );
t( null === clean( str_repeat( 'a', NOBLEN_SVG_MAX_SIZE + 1 ) ), 'a file over 256 KB is refused' );
t( null === clean( wrap( str_repeat( '<g>', 20 ) . str_repeat( '<rect width="1" height="1"/>', 6000 ) . str_repeat( '</g>', 20 ) ) ), 'more than 5000 nodes is refused' );
t( null === clean( "<svg $ns>\0</svg>" ), 'a NUL byte is refused' );

// ---- Output properties.
$o = (string) clean( wrap( '<rect width="1" height="1" onclick="x()" data-x="1" fill="#fff"/>' ) );
t( ! str_contains( $o, 'onclick' ) && ! str_contains( $o, 'data-x' ) && str_contains( $o, 'fill="#fff"' ), 'unknown and event attributes are dropped, allowed ones kept' );
$o2 = (string) clean( $o );
t( $o === $o2, 'sanitising is idempotent' );
t( 0 === strpos( $o, '<svg' ), 'the output starts at the svg element (no XML declaration or DOCTYPE)' );
$err = '';
noblen_svg_sanitize( 'junk', $err );
t( '' !== $err && ! str_contains( $err, '/' ) && ! str_contains( $err, 'home' ), 'a refusal carries a plain reason without paths' );

// ---- Dimensions.
t( array( 100, 40 ) === noblen_svg_dimensions( wrap( '' ) ), 'dimensions come from the viewBox' );
t( array( 200, 50 ) === noblen_svg_dimensions( '<svg width="200px" height="50" viewBox="0 0 10 10">' ), 'explicit width and height win' );
t( array( 0, 0 ) === noblen_svg_dimensions( '<svg>' ), 'unknown dimensions give zero' );
t( array( 12, 3 ) === noblen_svg_dimensions( '<svg viewBox="0 0 12.4 2.6">' ), 'fractional viewBox values are rounded' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
