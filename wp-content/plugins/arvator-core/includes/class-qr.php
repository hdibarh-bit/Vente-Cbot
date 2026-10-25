<?php
/**
 * Générateur de QR code (mode octets, correction d'erreur M, versions 1 à 13) rendu en SVG.
 *
 * Écrit pour le QR code de virement SEPA (norme EPC) : aucun service extérieur, aucune bibliothèque, aucune
 * image à télécharger. Le contenu est encodé en octets (UTF-8).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_QR {

	/**
	 * Niveau M : version => [mots de code au total, mots de correction par bloc, [[nombre de blocs, mots de données par bloc], …]].
	 *
	 * @var array<int, array{0:int,1:int,2:array<int, array{0:int,1:int}>}>
	 */
	private const SPEC = array(
		1  => array( 26, 10, array( array( 1, 16 ) ) ),
		2  => array( 44, 16, array( array( 1, 28 ) ) ),
		3  => array( 70, 26, array( array( 1, 44 ) ) ),
		4  => array( 100, 18, array( array( 2, 32 ) ) ),
		5  => array( 134, 24, array( array( 2, 43 ) ) ),
		6  => array( 172, 16, array( array( 4, 27 ) ) ),
		7  => array( 196, 18, array( array( 4, 31 ) ) ),
		8  => array( 242, 22, array( array( 2, 38 ), array( 2, 39 ) ) ),
		9  => array( 292, 22, array( array( 3, 36 ), array( 2, 37 ) ) ),
		10 => array( 346, 26, array( array( 4, 43 ), array( 1, 44 ) ) ),
		11 => array( 404, 30, array( array( 1, 50 ), array( 4, 51 ) ) ),
		12 => array( 466, 22, array( array( 6, 36 ), array( 2, 37 ) ) ),
		13 => array( 532, 22, array( array( 8, 37 ), array( 1, 38 ) ) ),
	);

	/** Positions des motifs d'alignement. */
	private const ALIGN = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
		11 => array( 6, 30, 54 ),
		12 => array( 6, 32, 58 ),
		13 => array( 6, 34, 62 ),
	);

	/** @var array<int,int> */
	private static array $exp = array();
	/** @var array<int,int> */
	private static array $log = array();

	/** Code QR en SVG ('' si le texte est trop long). */
	public static function svg( string $text, int $quiet = 4 ): string {
		$matrix = self::matrix( $text );
		if ( null === $matrix ) {
			return '';
		}
		$n    = count( $matrix );
		$size = $n + 2 * $quiet;
		$path = '';
		for ( $y = 0; $y < $n; $y++ ) {
			$x = 0;
			while ( $x < $n ) {
				if ( $matrix[ $y ][ $x ] ) {
					$start = $x;
					while ( $x < $n && $matrix[ $y ][ $x ] ) {
						++$x;
					}
					$path .= 'M' . ( $start + $quiet ) . ' ' . ( $y + $quiet ) . 'h' . ( $x - $start ) . 'v1h-' . ( $x - $start ) . 'z';
				} else {
					++$x;
				}
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="220" height="220" shape-rendering="crispEdges" role="img"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
	}

	/**
	 * Matrice de modules (true = noir), ou null si le texte est trop long.
	 *
	 * @return array<int, array<int, bool>>|null
	 */
	public static function matrix( string $text ): ?array {
		$bytes = array_values( unpack( 'C*', $text ) ?: array() );
		$len   = count( $bytes );
		$version = 0;
		for ( $v = 1; $v <= 13; $v++ ) {
			$count_bits = $v < 10 ? 8 : 16;
			if ( 4 + $count_bits + 8 * $len <= 8 * self::data_codewords( $v ) ) {
				$version = $v;
				break;
			}
		}
		if ( 0 === $version ) {
			return null;
		}
		self::init_gf();
		$codewords = self::codewords( $bytes, $version );
		return self::build( $codewords, $version );
	}

	private static function data_codewords( int $version ): int {
		$total = 0;
		foreach ( self::SPEC[ $version ][2] as [ $blocks, $data ] ) {
			$total += $blocks * $data;
		}
		return $total;
	}

	private static function init_gf(): void {
		if ( self::$exp ) {
			return;
		}
		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$exp[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11D;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			self::$exp[ $i ] = self::$exp[ $i - 255 ];
		}
	}

	/** @param int[] $data @return int[] mots de correction */
	private static function rs( array $data, int $ec ): array {
		// Polynôme générateur.
		$gen = array( 1 );
		for ( $i = 0; $i < $ec; $i++ ) {
			$next = array_fill( 0, count( $gen ) + 1, 0 );
			foreach ( $gen as $j => $coef ) {
				$next[ $j ]     ^= $coef;
				$next[ $j + 1 ] ^= 0 === $coef ? 0 : self::$exp[ self::$log[ $coef ] + $i ];
			}
			$gen = $next;
		}
		$res = array_merge( $data, array_fill( 0, $ec, 0 ) );
		$n   = count( $data );
		for ( $i = 0; $i < $n; $i++ ) {
			$coef = $res[ $i ];
			if ( 0 !== $coef ) {
				foreach ( $gen as $j => $g ) {
					if ( 0 !== $g ) {
						$res[ $i + $j ] ^= self::$exp[ self::$log[ $g ] + self::$log[ $coef ] ];
					}
				}
			}
		}
		return array_slice( $res, $n );
	}

	/** @param int[] $bytes @return int[] */
	private static function codewords( array $bytes, int $version ): array {
		$bits = '0100' . str_pad( decbin( count( $bytes ) ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );
		foreach ( $bytes as $b ) {
			$bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
		}
		$capacity = 8 * self::data_codewords( $version );
		$bits    .= str_repeat( '0', min( 4, $capacity - strlen( $bits ) ) );
		if ( strlen( $bits ) % 8 ) {
			$bits .= str_repeat( '0', 8 - strlen( $bits ) % 8 );
		}
		$data = array_map( 'bindec', str_split( $bits, 8 ) );
		for ( $pad = 0; count( $data ) < $capacity / 8; $pad++ ) {
			$data[] = 0 === $pad % 2 ? 0xEC : 0x11;
		}

		$ec     = self::SPEC[ $version ][1];
		$blocks = array();
		$pos    = 0;
		foreach ( self::SPEC[ $version ][2] as [ $count, $size ] ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$chunk    = array_slice( $data, $pos, $size );
				$pos     += $size;
				$blocks[] = array( $chunk, self::rs( $chunk, $ec ) );
			}
		}
		$out  = array();
		$max  = max( array_map( static fn( array $b ): int => count( $b[0] ), $blocks ) );
		for ( $i = 0; $i < $max; $i++ ) {
			foreach ( $blocks as $b ) {
				if ( isset( $b[0][ $i ] ) ) {
					$out[] = $b[0][ $i ];
				}
			}
		}
		for ( $i = 0; $i < $ec; $i++ ) {
			foreach ( $blocks as $b ) {
				$out[] = $b[1][ $i ];
			}
		}
		return $out;
	}

	/**
	 * @param int[] $codewords
	 * @return array<int, array<int, bool>>
	 */
	private static function build( array $codewords, int $version ): array {
		$n      = 17 + 4 * $version;
		$best   = null;
		$best_s = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$m = self::draw( $codewords, $version, $n, $mask );
			$s = self::penalty( $m, $n );
			if ( $s < $best_s ) {
				$best_s = $s;
				$best   = $m;
			}
		}
		return $best;
	}

	/** @return array<int, array<int, bool>> */
	private static function draw( array $codewords, int $version, int $n, int $mask ): array {
		$mod = array_fill( 0, $n, array_fill( 0, $n, false ) );
		$fun = array_fill( 0, $n, array_fill( 0, $n, false ) ); // modules de fonction (non masqués).

		$set = static function ( int $x, int $y, bool $dark ) use ( &$mod, &$fun, $n ): void {
			if ( $x >= 0 && $y >= 0 && $x < $n && $y < $n ) {
				$mod[ $y ][ $x ] = $dark;
				$fun[ $y ][ $x ] = true;
			}
		};

		// Motifs de repérage et séparateurs.
		foreach ( array( array( 0, 0 ), array( $n - 7, 0 ), array( 0, $n - 7 ) ) as [ $ox, $oy ] ) {
			for ( $dy = -1; $dy <= 7; $dy++ ) {
				for ( $dx = -1; $dx <= 7; $dx++ ) {
					$in   = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
					$dark = $in && ( 0 === $dx || 6 === $dx || 0 === $dy || 6 === $dy || ( $dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4 ) );
					$set( $ox + $dx, $oy + $dy, $dark );
				}
			}
		}
		// Synchronisation.
		for ( $i = 8; $i < $n - 8; $i++ ) {
			$set( $i, 6, 0 === $i % 2 );
			$set( 6, $i, 0 === $i % 2 );
		}
		// Alignement.
		$pos = self::ALIGN[ $version ];
		$cnt = count( $pos );
		for ( $a = 0; $a < $cnt; $a++ ) {
			for ( $b = 0; $b < $cnt; $b++ ) {
				if ( ( 0 === $a && 0 === $b ) || ( 0 === $a && $cnt - 1 === $b ) || ( $cnt - 1 === $a && 0 === $b ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$set( $pos[ $a ] + $dx, $pos[ $b ] + $dy, max( abs( $dx ), abs( $dy ) ) !== 1 );
					}
				}
			}
		}
		// Module sombre fixe.
		$set( 8, $n - 8, true );
		// Zones de format (réservées, remplies ensuite) et de version.
		for ( $i = 0; $i < 9; $i++ ) {
			$set( 8, $i, false );
			$set( $i, 8, false );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$set( $n - 1 - $i, 8, false );
			$set( 8, $n - 1 - $i, false );
		}
		$set( 8, $n - 8, true );
		if ( $version >= 7 ) {
			$rem = $version;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$bits = ( $version << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$dark = ( $bits >> $i ) & 1;
				$a    = (int) ( $n - 11 + $i % 3 );
				$b    = intdiv( $i, 3 );
				$set( $a, $b, (bool) $dark );
				$set( $b, $a, (bool) $dark );
			}
		}

		// Données, en zigzag par colonnes de deux modules.
		$bitstream = '';
		foreach ( $codewords as $cw ) {
			$bitstream .= str_pad( decbin( $cw ), 8, '0', STR_PAD_LEFT );
		}
		$bitstream .= str_repeat( '0', self::remainder_bits( $version ) );
		$k   = 0;
		$len = strlen( $bitstream );
		for ( $right = $n - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $n; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $n - 1 - $vert : $vert;
					if ( ! $fun[ $y ][ $x ] && $k < $len ) {
						$mod[ $y ][ $x ] = '1' === $bitstream[ $k ];
						++$k;
					}
				}
			}
		}

		// Masque.
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( ! $fun[ $y ][ $x ] && self::masked( $mask, $x, $y ) ) {
					$mod[ $y ][ $x ] = ! $mod[ $y ][ $x ];
				}
			}
		}

		// Format (niveau M = 00).
		$data = ( 0 << 3 ) | $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		for ( $i = 0; $i <= 5; $i++ ) {
			$mod[ $i ][ 8 ] = (bool) ( ( $bits >> $i ) & 1 );
		}
		$mod[7][8] = (bool) ( ( $bits >> 6 ) & 1 );
		$mod[8][8] = (bool) ( ( $bits >> 7 ) & 1 );
		$mod[8][7] = (bool) ( ( $bits >> 8 ) & 1 );
		for ( $i = 9; $i < 15; $i++ ) {
			$mod[8][ 14 - $i ] = (bool) ( ( $bits >> $i ) & 1 );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$mod[8][ $n - 1 - $i ] = (bool) ( ( $bits >> $i ) & 1 );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$mod[ $n - 15 + $i ][8] = (bool) ( ( $bits >> $i ) & 1 );
		}
		$mod[ $n - 8 ][8] = true;

		return $mod;
	}

	private static function remainder_bits( int $version ): int {
		if ( 1 === $version ) {
			return 0;
		}
		return $version <= 6 ? 7 : 0;
	}

	private static function masked( int $mask, int $x, int $y ): bool {
		return match ( $mask ) {
			0 => 0 === ( $x + $y ) % 2,
			1 => 0 === $y % 2,
			2 => 0 === $x % 3,
			3 => 0 === ( $x + $y ) % 3,
			4 => 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2,
			5 => 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3,
			6 => 0 === ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2,
			default => 0 === ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2,
		};
	}

	/** @param array<int, array<int, bool>> $m */
	private static function penalty( array $m, int $n ): int {
		$score = 0;
		// Séries de modules identiques (lignes puis colonnes) et motifs 1:1:3:1:1.
		for ( $pass = 0; $pass < 2; $pass++ ) {
			for ( $a = 0; $a < $n; $a++ ) {
				$line = '';
				$run  = 1;
				for ( $b = 0; $b < $n; $b++ ) {
					$cur   = $pass ? $m[ $b ][ $a ] : $m[ $a ][ $b ];
					$line .= $cur ? '1' : '0';
					if ( $b > 0 ) {
						$prev = $pass ? $m[ $b - 1 ][ $a ] : $m[ $a ][ $b - 1 ];
						if ( $prev === $cur ) {
							++$run;
							if ( 5 === $run ) {
								$score += 3;
							} elseif ( $run > 5 ) {
								++$score;
							}
						} else {
							$run = 1;
						}
					}
				}
				$score += 40 * ( substr_count( $line, '10111010000' ) + substr_count( $line, '00001011101' ) );
			}
		}
		// Blocs 2x2.
		for ( $y = 0; $y < $n - 1; $y++ ) {
			for ( $x = 0; $x < $n - 1; $x++ ) {
				$c = $m[ $y ][ $x ];
				if ( $c === $m[ $y ][ $x + 1 ] && $c === $m[ $y + 1 ][ $x ] && $c === $m[ $y + 1 ][ $x + 1 ] ) {
					$score += 3;
				}
			}
		}
		// Proportion de modules sombres.
		$dark = 0;
		foreach ( $m as $row ) {
			foreach ( $row as $c ) {
				$dark += $c ? 1 : 0;
			}
		}
		$k      = (int) ( abs( $dark * 20 - $n * $n * 10 ) / ( $n * $n ) );
		$score += $k * 10;
		return $score;
	}
}
