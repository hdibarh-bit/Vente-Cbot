<?php
/**
 * Pages du site (FAQ, À propos, Contact, Risques, textes légaux, Espace membre).
 *
 * Créées UNE SEULE FOIS, si elles n'existent pas déjà. Une page modifiée dans le tableau de bord
 * n'est jamais écrasée, et une page supprimée volontairement n'est pas recréée.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Pages {

	public const EMAIL = 'contact@cosmartis.com';

	/** Création des pages manquantes (appelée à l'installation et à chaque mise à jour du plugin). */
	public static function create_missing(): void {
		$defs    = require ARVATOR_CORE_DIR . 'includes/content/pages.php';
		$done    = (array) get_option( 'arvator_pages_created', array() );
		$authors = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author  = $authors ? (int) $authors[0] : 0;

		foreach ( $defs as $slug => $def ) {
			if ( in_array( $slug, $done, true ) ) {
				continue;
			}
			if ( null === get_page_by_path( $slug, OBJECT, 'page' ) ) {
				wp_insert_post(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'post_name'      => $slug,
						'post_title'     => $def['title'],
						'post_content'   => self::to_blocks( $def['text'] ),
						'post_author'    => $author,
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					)
				);
			}
			$done[] = $slug;
		}

		update_option( 'arvator_pages_created', array_values( array_unique( $done ) ), false );
	}

	/**
	 * Convertit un texte simple en blocs WordPress (modifiables ensuite dans l'éditeur).
	 *
	 * Syntaxe : « ## Titre », « ### Sous-titre », « - élément », « ?? Question » suivie de sa réponse,
	 * « [provisoire] », « [arvator_contact] », **gras**, [texte](/page/).
	 */
	public static function to_blocks( string $text ): string {
		$text  = strtr( $text, array( '{{email}}' => self::EMAIL ) );
		$lines = preg_split( '/\R/', trim( $text ) );
		$lines = is_array( $lines ) ? $lines : array();
		$out   = '';
		$i     = 0;
		$n     = count( $lines );

		while ( $i < $n ) {
			$line = rtrim( $lines[ $i ] );

			if ( '' === $line ) {
				++$i;
				continue;
			}
			if ( str_starts_with( $line, '### ' ) ) {
				$out .= "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . self::inline( substr( $line, 4 ) ) . "</h3>\n<!-- /wp:heading -->\n\n";
				++$i;
				continue;
			}
			if ( str_starts_with( $line, '## ' ) ) {
				$out .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . self::inline( substr( $line, 3 ) ) . "</h2>\n<!-- /wp:heading -->\n\n";
				++$i;
				continue;
			}
			if ( '[provisoire]' === $line ) {
				$out .= "<!-- wp:paragraph {\"className\":\"notice-provisional\"} -->\n<p class=\"notice-provisional\"><strong>Version provisoire.</strong> Ce texte doit être validé par un avocat avant l’ouverture du site au public.</p>\n<!-- /wp:paragraph -->\n\n";
				++$i;
				continue;
			}
			if ( 1 === preg_match( '/^\[(arvator_[a-z_]+)\]$/', $line, $m ) ) {
				$out .= "<!-- wp:shortcode -->\n[" . $m[1] . "]\n<!-- /wp:shortcode -->\n\n";
				++$i;
				continue;
			}
			if ( str_starts_with( $line, '- ' ) ) {
				$items = array();
				while ( $i < $n && str_starts_with( rtrim( $lines[ $i ] ), '- ' ) ) {
					$items[] = substr( rtrim( $lines[ $i ] ), 2 );
					++$i;
				}
				$out .= self::list_block( $items );
				continue;
			}
			if ( str_starts_with( $line, '?? ' ) ) {
				$question = substr( $line, 3 );
				++$i;
				$answer = array();
				while ( $i < $n && '' !== rtrim( $lines[ $i ] ) ) {
					$answer[] = rtrim( $lines[ $i ] );
					++$i;
				}
				$out .= "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . self::inline( $question ) . "</summary>\n"
					. rtrim( self::to_blocks( implode( "\n", $answer ) ) )
					. "</details>\n<!-- /wp:details -->\n\n";
				continue;
			}

			$para = array();
			while ( $i < $n && '' !== rtrim( $lines[ $i ] ) && ! preg_match( '/^(## |### |- |\?\? |\[provisoire\]|\[arvator_)/', rtrim( $lines[ $i ] ) ) ) {
				$para[] = trim( $lines[ $i ] );
				++$i;
			}
			$out .= "<!-- wp:paragraph -->\n<p>" . self::inline( implode( ' ', $para ) ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}

		return $out;
	}

	/** @param string[] $items */
	private static function list_block( array $items ): string {
		$li = '';
		foreach ( $items as $item ) {
			$li .= "<!-- wp:list-item -->\n<li>" . self::inline( $item ) . "</li>\n<!-- /wp:list-item -->";
		}
		return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $li . "</ul>\n<!-- /wp:list -->\n\n";
	}

	/** Texte → HTML sûr : on échappe tout, puis on ne réactive que le gras et les liens internes. */
	private static function inline( string $s ): string {
		$s = esc_html( $s );
		$s = (string) preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s );
		$s = (string) preg_replace( '/\[([^\]]+)\]\((\/[a-z0-9\-\/#]*)\)/i', '<a href="$2">$1</a>', $s );
		return $s;
	}
}
