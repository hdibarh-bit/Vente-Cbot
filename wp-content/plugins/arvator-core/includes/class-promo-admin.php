<?php
/**
 * Administration des codes promo (Arvator > Codes promo), réservée aux administrateurs : créer un code, voir ses
 * utilisations, le désactiver ou le réactiver.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arvator_Promo_Admin {

	private const NOTICE_KEY = 'arvator_promo_notice_';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 22 );
		add_action( 'admin_post_arvator_promo_create', array( self::class, 'handle_create' ) );
		add_action( 'admin_post_arvator_promo_toggle', array( self::class, 'handle_toggle' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'arvator', 'Codes promo', 'Codes promo', 'manage_options', 'arvator-promo', array( self::class, 'page' ) );
	}

	private static function back( string $type, string $text ): void {
		set_transient( self::NOTICE_KEY . get_current_user_id(), array( $type, $text ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=arvator-promo' ) );
		exit;
	}

	private static function plan_label( string $plans ): string {
		return array(
			'both'    => 'Mensuel et annuel',
			'monthly' => 'Mensuel seulement',
			'annual'  => 'Annuel seulement',
		)[ $plans ] ?? $plans;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>Codes promo</h1>';
		$notice = get_transient( self::NOTICE_KEY . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( self::NOTICE_KEY . get_current_user_id() );
			printf( '<div class="notice notice-%s"><p>%s</p></div>', 'ok' === $notice[0] ? 'success' : 'error', esc_html( (string) $notice[1] ) );
		}
		echo '<p>Le client tape le code sur la page « Acheter Arvator » ; la réduction apparaît ensuite sur la page de paiement Stripe, avant qu’il paie. Un code peut réduire <strong>le robot, l’abonnement, ou les deux à la fois</strong>, et ne sert qu’une fois par client (le robot ne s’achète qu’une fois). Le parrain garde sa prime d’achat entière ; ses 10&nbsp;% sur l’abonnement sont calculés sur ce que le client paie vraiment.</p>';
		if ( '' === Arvator_Stripe::secret() ) {
			echo '<div class="notice notice-warning inline"><p><strong>Stripe n’est pas configuré</strong> : aucun code ne peut être créé.</p></div>';
		} else {
			printf( '<p>Mode actuel : <strong>%s</strong>. Les codes créés dans un mode ne marchent pas dans l’autre (à recréer au passage en réel).</p>', Arvator_Stripe::is_test_key() ? 'bac à sable (test)' : 'réel' );
		}
		self::form();
		self::table();
		echo '</div>';
	}

	private static function form(): void {
		?>
		<h2>Créer un code</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="arv-promo-form">
			<input type="hidden" name="action" value="arvator_promo_create">
			<?php wp_nonce_field( 'arvator_promo_create', '_arv_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="arv-p-code">Code</label></th>
					<td><input id="arv-p-code" name="code" type="text" required maxlength="30" pattern="[A-Za-z0-9_\-]{3,30}" class="regular-text" style="text-transform:uppercase" placeholder="NOEL30">
					<p class="description">3 à 30 caractères : lettres, chiffres, tiret. Majuscules et minuscules sont équivalentes.</p></td></tr>
				<tr><th scope="row"><label for="arv-p-bot">Réduction sur le robot</label></th>
					<td><input id="arv-p-bot" name="bot_percent" type="number" min="0" max="100" step="1" required value="30" class="small-text"> %
					<p class="description">Paiement unique du robot. 0 = pas de réduction sur le robot, 100 = robot offert.</p></td></tr>
				<tr><th scope="row"><label for="arv-p-sub">Réduction sur l’abonnement</label></th>
					<td><input id="arv-p-sub" name="sub_percent" type="number" min="0" max="100" step="1" required value="0" class="small-text"> %
					<p class="description">0 = pas de réduction sur l’abonnement, 100 = offert. Vous pouvez remplir les deux cases : par exemple 30 sur le robot et 100 sur l’abonnement pendant 6 mois = robot à -30&nbsp;% + 6 mois offerts.</p></td></tr>
				<tr class="arv-p-sub"><th scope="row"><label for="arv-p-plans">Formule concernée</label></th>
					<td><select id="arv-p-plans" name="plans">
						<option value="both">Mensuel et annuel</option>
						<option value="monthly">Mensuel seulement</option>
						<option value="annual">Annuel seulement</option>
					</select>
					<p class="description">Si le client choisit une autre formule, le site lui dit que le code ne s’y applique pas.</p></td></tr>
				<tr class="arv-p-sub"><th scope="row">Pendant combien de temps</th>
					<td><label><input type="radio" name="duration" value="once" checked> La 1re période seulement (1er mois en mensuel, 1re année en annuel)</label><br>
					<label><input type="radio" name="duration" value="repeating"> Pendant <input name="months" type="number" min="1" max="36" step="1" value="6" class="small-text" style="min-height:0;line-height:1.4;padding:0 4px"> mois (formule mensuelle seulement)</label><br>
					<label><input type="radio" name="duration" value="forever"> À chaque paiement, tant que le client reste abonné</label></td></tr>
				<tr><th scope="row"><label for="arv-p-max">Nombre d’utilisations maximum</label></th>
					<td><input id="arv-p-max" name="max_uses" type="number" min="0" step="1" value="0" class="small-text">
					<p class="description">0 = sans limite.</p></td></tr>
				<tr><th scope="row"><label for="arv-p-exp">Valable jusqu’au</label></th>
					<td><input id="arv-p-exp" name="expires" type="date">
					<p class="description">Inclus (jusqu’à 23 h 59, heure du site). Vide = sans date de fin.</p></td></tr>
			</table>
			<p class="description"><strong>À savoir :</strong> un code ne se modifie pas après sa création (Stripe ne le permet pas). Pour le changer, désactivez-le et créez-en un nouveau. Un code « robot offert » (100&nbsp;%) donne quand même sa prime d’achat au parrain du client.</p>
			<?php submit_button( 'Créer le code' ); ?>
		</form>
		<script>
		( function () {
			var f = document.getElementById( 'arv-promo-form' );
			function sync() {
				var sub = parseInt( f.querySelector( 'input[name="sub_percent"]' ).value, 10 ) > 0;
				f.querySelectorAll( '.arv-p-sub' ).forEach( function ( tr ) { tr.style.display = sub ? '' : 'none'; } );
			}
			f.querySelector( 'input[name="sub_percent"]' ).addEventListener( 'input', sync );
			sync();
		} )();
		</script>
		<?php
	}

	private static function table(): void {
		$rows = Arvator_Promo::all();
		echo '<h2>Codes existants</h2>';
		if ( ! $rows ) {
			echo '<p>Aucun code pour le moment.</p>';
			return;
		}
		$mode = Arvator_Stripe::is_test_key() ? 1 : 0;
		echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Avantage</th><th>Formule</th><th>Utilisations</th><th>Valable jusqu’au</th><th>État</th><th>Derniers clients</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$id    = (int) $r['id'];
			$uses  = Arvator_Promo::uses( $id );
			$until = empty( $r['expires_at'] ) ? 'Sans fin' : wp_date( 'd/m/Y', (int) strtotime( $r['expires_at'] . ' UTC' ) );
			if ( (int) $r['is_test'] !== $mode ) {
				$state = (int) $r['is_test'] ? 'Bac à sable (inutilisable en réel)' : 'Réel (inutilisable en test)';
			} elseif ( ! (int) $r['active'] ) {
				$state = 'Désactivé';
			} elseif ( ! empty( $r['expires_at'] ) && (string) $r['expires_at'] <= gmdate( 'Y-m-d H:i:s' ) ) {
				$state = 'Terminé (date passée)';
			} elseif ( (int) $r['max_uses'] > 0 && $uses >= (int) $r['max_uses'] ) {
				$state = 'Épuisé';
			} else {
				$state = 'Actif';
			}
			$who = array();
			foreach ( Arvator_Promo::uses_of( $id, 5 ) as $u ) {
				$user  = get_user_by( 'id', (int) $u['user_id'] );
				$who[] = $user instanceof WP_User ? $user->user_email : '#' . $u['user_id'];
			}
			echo '<tr>';
			foreach ( array( (string) $r['code'], Arvator_Promo::describe( $r ), (int) $r['sub_percent'] > 0 ? self::plan_label( (string) $r['plans'] ) : 'Toutes', $uses . ( (int) $r['max_uses'] > 0 ? ' / ' . (int) $r['max_uses'] : '' ), $until, $state, implode( ', ', $who ) ) as $cell ) {
				echo '<td>' . esc_html( $cell ) . '</td>';
			}
			echo '<td>';
			printf( '<form method="post" action="%s"><input type="hidden" name="action" value="arvator_promo_toggle"><input type="hidden" name="id" value="%d"><input type="hidden" name="on" value="%d">', esc_url( admin_url( 'admin-post.php' ) ), $id, (int) $r['active'] ? 0 : 1 );
			wp_nonce_field( 'arvator_promo_toggle' . $id, '_arv_nonce' );
			submit_button( (int) $r['active'] ? 'Désactiver' : 'Réactiver', 'small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function handle_create(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'arvator_promo_create', '_arv_nonce' );
		$in = array();
		foreach ( array( 'code', 'bot_percent', 'sub_percent', 'duration', 'months', 'plans', 'max_uses', 'expires' ) as $k ) {
			$in[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ) : '';
		}
		$res = Arvator_Promo::create( $in );
		if ( is_wp_error( $res ) ) {
			self::back( 'err', $res->get_error_message() );
		}
		$row = Arvator_Promo::get( (int) $res );
		self::back( 'ok', 'Code ' . ( $row['code'] ?? '' ) . ' créé : ' . ( $row ? Arvator_Promo::describe( $row ) : '' ) . '. Il est utilisable tout de suite sur la page d’achat.' );
	}

	public static function handle_toggle(): void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifiée ci-dessous
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'arvator_promo_toggle' . $id, '_arv_nonce' );
		$on = ! empty( $_POST['on'] );
		Arvator_Promo::set_active( $id, $on );
		Arvator_Repo::log_event( $on ? 'promo_on' : 'promo_off', get_current_user_id(), array( 'id' => $id ) );
		self::back( 'ok', $on ? 'Code réactivé.' : 'Code désactivé : il n’est plus accepté sur la page d’achat.' );
	}
}
