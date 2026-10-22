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

	private const OPT_CREATED = 'arvator_pages_created';
	private const OPT_REV     = 'arvator_pages_rev';
	private const OPT_REVIEW  = 'arvator_pages_review';

	/**
	 * Création des pages manquantes et mise à jour des textes améliorés (appelée à l'installation et à chaque
	 * mise à jour du plugin).
	 *
	 * - Page absente et jamais créée : elle est créée.
	 * - Page supprimée volontairement : elle n'est pas recréée.
	 * - Texte amélioré dans une nouvelle version : remplacé SEULEMENT si la page n'a jamais été modifiée dans
	 *   l'éditeur ; sinon la page est conservée telle quelle et signalée dans l'administration.
	 */
	public static function create_missing(): void {
		$defs    = require ARVATOR_CORE_DIR . 'includes/content/pages.php';
		$done    = (array) get_option( self::OPT_CREATED, array() );
		$revs    = (array) get_option( self::OPT_REV, array() );
		$review  = (array) get_option( self::OPT_REVIEW, array() );
		$authors = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author  = $authors ? (int) $authors[0] : 0;

		foreach ( $defs as $slug => $def ) {
			$rev  = (int) ( $def['rev'] ?? 1 );
			$page = get_page_by_path( $slug, OBJECT, 'page' );

			if ( in_array( $slug, $done, true ) ) {
				// Page déjà créée auparavant : mise à jour éventuelle du texte.
				if ( $page instanceof WP_Post && (int) ( $revs[ $slug ] ?? 1 ) < $rev ) {
					if ( self::is_untouched( $page ) ) {
						self::replace_content( $page, self::to_blocks( $def['text'] ) );
						$revs[ $slug ] = $rev;
						$review        = array_values( array_diff( $review, array( $slug ) ) );
					} elseif ( ! in_array( $slug, $review, true ) ) {
						$review[] = $slug;
					}
				}
				continue;
			}

			if ( null === $page ) {
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
				$revs[ $slug ] = $rev;
			}
			$done[] = $slug;
		}

		update_option( self::OPT_CREATED, array_values( array_unique( $done ) ), false );
		update_option( self::OPT_REV, $revs, false );
		update_option( self::OPT_REVIEW, $review, false );
	}

	/** Une page « jamais touchée » a la même date de création et de dernière modification. */
	private static function is_untouched( WP_Post $page ): bool {
		return $page->post_modified_gmt === $page->post_date_gmt;
	}

	/** Remplace le texte en conservant les dates d'origine (la page reste « jamais touchée »). */
	private static function replace_content( WP_Post $page, string $content ): void {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $page->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $page->ID );
	}

	/** Pages dont le texte a été amélioré mais que Hamid a modifiées (donc non remplacées). */
	public static function pages_to_review(): array {
		$out = array();
		foreach ( (array) get_option( self::OPT_REVIEW, array() ) as $slug ) {
			$page = get_page_by_path( (string) $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post ) {
				$out[ (string) $slug ] = $page;
			}
		}
		return $out;
	}

	/** Efface la liste « à relire ». */
	public static function dismiss_review(): void {
		update_option( self::OPT_REVIEW, array(), false );
	}

	/**
	 * Convertit un texte simple en blocs WordPress (modifiables ensuite dans l'éditeur).
	 *
	 * Syntaxe : « ## Titre », « ### Sous-titre », « - élément », « ?? Question » suivie de sa réponse,
	 * « [provisoire] », « [arvator_contact] », **gras**, [texte](/page/).
	 */
	public static function to_blocks( string $text ): string {
		$text  = strtr(
			$text,
			array(
				'{{email}}'   => Arvator_Site::EMAIL,
				'{{domain}}'  => Arvator_Site::DOMAIN,
				'{{adresse}}' => Arvator_Site::POSTAL,
			)
		);
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
