<?php
/**
 * Admin page template.
 *
 * @package NutriciaAiOpisnik
 *
 * @var array  $s         Settings.
 * @var array  $intervals Cron intervals.
 * @var array  $stats     Processing stats.
 * @var int    $next_run  Next scheduled run timestamp.
 * @var array  $log       Log entries.
 * @var bool   $has_key   Whether an API key is stored.
 * @var array|false $notice Flash notice.
 * @var bool   $wc_active WooCommerce active flag.
 * @var bool   $rankmath_active Rank Math active flag.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap nutricia-ai-wrap">
	<h1><?php esc_html_e( 'Nutricia AI Opisnik', 'nutricia-ai-opisnik' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Automatska SEO i CRO optimizacija WooCommerce proizvoda pomoću AI-a (OpenRouter).', 'nutricia-ai-opisnik' ); ?>
	</p>

	<?php if ( $notice && is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : ( 'info' === $notice['type'] ? 'info' : 'success' ) ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $wc_active ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'WooCommerce nije aktivan. Plugin obrađuje WooCommerce proizvode i neće raditi bez njega.', 'nutricia-ai-opisnik' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( ! $rankmath_active ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Rank Math nije detektovan. Meta naslov/opis će i dalje biti sačuvani u odgovarajuća meta polja, ali će ih koristiti tek kada Rank Math bude aktivan.', 'nutricia-ai-opisnik' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="nutricia-ai-grid">
		<div class="nutricia-ai-main">

			<!-- STATUS DASHBOARD -->
			<div class="nutricia-ai-card">
				<h2><?php esc_html_e( 'Status obrade', 'nutricia-ai-opisnik' ); ?></h2>
				<div class="nutricia-ai-stats">
					<div class="nutricia-ai-stat">
						<span class="num"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
						<span class="lbl"><?php esc_html_e( 'Ukupno proizvoda', 'nutricia-ai-opisnik' ); ?></span>
					</div>
					<div class="nutricia-ai-stat is-done">
						<span class="num"><?php echo esc_html( number_format_i18n( $stats['done'] ) ); ?></span>
						<span class="lbl"><?php esc_html_e( 'Obrađeno', 'nutricia-ai-opisnik' ); ?></span>
					</div>
					<div class="nutricia-ai-stat is-pending">
						<span class="num"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></span>
						<span class="lbl"><?php esc_html_e( 'Na čekanju', 'nutricia-ai-opisnik' ); ?></span>
					</div>
					<div class="nutricia-ai-stat is-error">
						<span class="num"><?php echo esc_html( number_format_i18n( $stats['error'] ) ); ?></span>
						<span class="lbl"><?php esc_html_e( 'Greške', 'nutricia-ai-opisnik' ); ?></span>
					</div>
				</div>

				<p class="nutricia-ai-nextrun">
					<?php if ( $s['auto_enabled'] && $next_run ) : ?>
						<?php
						printf(
							/* translators: %s: relative time */
							esc_html__( 'Sledeća automatska obrada: za %s.', 'nutricia-ai-opisnik' ),
							esc_html( human_time_diff( time(), $next_run ) )
						);
						?>
					<?php elseif ( $s['auto_enabled'] ) : ?>
						<?php esc_html_e( 'Automatska obrada je uključena (zakazivanje u toku).', 'nutricia-ai-opisnik' ); ?>
					<?php else : ?>
						<em><?php esc_html_e( 'Automatska obrada je isključena.', 'nutricia-ai-opisnik' ); ?></em>
					<?php endif; ?>
				</p>

				<form method="post" class="nutricia-ai-actions">
					<?php wp_nonce_field( 'nutricia_ai_actions' ); ?>
					<button type="submit" name="nutricia_ai_process_now" class="button button-primary">
						<?php esc_html_e( 'Obradi sledeći proizvod odmah', 'nutricia-ai-opisnik' ); ?>
					</button>
					<button type="submit" name="nutricia_ai_reset" class="button"
						onclick="return confirm('<?php echo esc_js( __( 'Da li ste sigurni? Ovo će resetovati status obrade za SVE proizvode i staviti ih ponovo u red.', 'nutricia-ai-opisnik' ) ); ?>');">
						<?php esc_html_e( 'Resetuj red za obradu', 'nutricia-ai-opisnik' ); ?>
					</button>
				</form>
			</div>

			<!-- SETTINGS -->
			<div class="nutricia-ai-card">
				<h2><?php esc_html_e( 'Podešavanja', 'nutricia-ai-opisnik' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'nutricia_ai_settings' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="nutricia_api_key"><?php esc_html_e( 'OpenRouter API ključ', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="password" id="nutricia_api_key" name="nutricia_ai[api_key]" class="regular-text" autocomplete="off"
									placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (sačuvan) — ostavite prazno da zadržite', 'nutricia-ai-opisnik' ) : 'sk-or-...'; ?>" />
								<p class="description">
									<?php esc_html_e( 'Ključ se čuva u bazi i nije prikazan iz bezbednosnih razloga.', 'nutricia-ai-opisnik' ); ?>
									<?php if ( $has_key ) : ?>
										<span class="nutricia-ai-badge ok"><?php esc_html_e( 'Ključ je podešen', 'nutricia-ai-opisnik' ); ?></span>
									<?php else : ?>
										<span class="nutricia-ai-badge warn"><?php esc_html_e( 'Ključ nije podešen', 'nutricia-ai-opisnik' ); ?></span>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_model"><?php esc_html_e( 'Model', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="text" id="nutricia_model" name="nutricia_ai[model]" class="regular-text" value="<?php echo esc_attr( $s['model'] ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Identifikator OpenRouter modela, npr. openai/gpt-4o-mini, google/gemini-2.0-flash-001, anthropic/claude-3.5-sonnet. Za slanje slika koristite model sa podrškom za vision.', 'nutricia-ai-opisnik' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_interval"><?php esc_html_e( 'Interval obrade (cron)', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<select id="nutricia_interval" name="nutricia_ai[cron_interval]">
									<?php foreach ( $intervals as $slug => $data ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $s['cron_interval'], $slug ); ?>>
											<?php echo esc_html( $data['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Koliko često se obrađuje po jedan proizvod. WordPress cron zavisi od poseta sajtu; za precizniji rad podesite pravi (system) cron.', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Automatska obrada', 'nutricia-ai-opisnik' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="nutricia_ai[auto_enabled]" value="1" <?php checked( $s['auto_enabled'], 1 ); ?> />
									<?php esc_html_e( 'Uključi automatsku obradu preko cron-a', 'nutricia-ai-opisnik' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_vision"><?php esc_html_e( 'Slanje slike (vision)', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<select id="nutricia_vision" name="nutricia_ai[vision_mode]">
									<option value="never" <?php selected( $s['vision_mode'], 'never' ); ?>><?php esc_html_e( 'Nikad', 'nutricia-ai-opisnik' ); ?></option>
									<option value="always" <?php selected( $s['vision_mode'], 'always' ); ?>><?php esc_html_e( 'Uvek', 'nutricia-ai-opisnik' ); ?></option>
									<option value="auto" <?php selected( $s['vision_mode'], 'auto' ); ?>><?php esc_html_e( 'Automatski (kada ima malo teksta)', 'nutricia-ai-opisnik' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Da li slati sliku proizvoda AI-u. "Automatski" šalje sliku samo kada proizvod ima malo tekstualnih informacija.', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_threshold"><?php esc_html_e( 'Prag za "Automatski" (broj karaktera)', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="number" min="0" step="10" id="nutricia_threshold" name="nutricia_ai[vision_threshold]" value="<?php echo esc_attr( $s['vision_threshold'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Ako je ukupan tekst (opisi + sastav) kraći od ovoga, slika se šalje kada je vision u režimu "Automatski".', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_composition"><?php esc_html_e( 'Meta polje za sastav', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="text" id="nutricia_composition" name="nutricia_ai[composition_meta]" value="<?php echo esc_attr( $s['composition_meta'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Naziv meta polja u kome se čuva sastav proizvoda (podrazumevano: sastav).', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Prepisivanje opisa', 'nutricia-ai-opisnik' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="nutricia_ai[overwrite_desc]" value="1" <?php checked( $s['overwrite_desc'], 1 ); ?> />
									<?php esc_html_e( 'Prepiši postojeće kratke/dugačke opise (ako je isključeno, popunjavaju se samo prazni)', 'nutricia-ai-opisnik' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_maxtags"><?php esc_html_e( 'Maksimalan broj tagova', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="number" min="1" max="10" id="nutricia_maxtags" name="nutricia_ai[max_tags]" value="<?php echo esc_attr( $s['max_tags'] ); ?>" class="small-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Rank Math fokus ključna reč', 'nutricia-ai-opisnik' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="nutricia_ai[set_focus_keyword]" value="1" <?php checked( $s['set_focus_keyword'], 1 ); ?> />
									<?php esc_html_e( 'Postavi i fokus ključnu reč koju AI predloži', 'nutricia-ai-opisnik' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_temp"><?php esc_html_e( 'Temperatura', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="number" min="0" max="2" step="0.1" id="nutricia_temp" name="nutricia_ai[temperature]" value="<?php echo esc_attr( $s['temperature'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Kreativnost modela (0 = konzervativno, 2 = kreativno). Preporuka: 0.5–0.7.', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_attempts"><?php esc_html_e( 'Maksimalan broj pokušaja', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<input type="number" min="1" max="10" id="nutricia_attempts" name="nutricia_ai[max_attempts]" value="<?php echo esc_attr( $s['max_attempts'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Koliko puta pokušati ponovo obradu proizvoda kod greške.', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nutricia_context"><?php esc_html_e( 'Kontekst sajta', 'nutricia-ai-opisnik' ); ?></label></th>
							<td>
								<textarea id="nutricia_context" name="nutricia_ai[site_context]" rows="6" class="large-text"><?php echo esc_textarea( $s['site_context'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Opis prodavnice i tona komunikacije koji se šalje AI-u uz svaki proizvod.', 'nutricia-ai-opisnik' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" name="nutricia_ai_save_settings" class="button button-primary"><?php esc_html_e( 'Sačuvaj podešavanja', 'nutricia-ai-opisnik' ); ?></button>
						<button type="submit" name="nutricia_ai_test" class="button"><?php esc_html_e( 'Testiraj vezu', 'nutricia-ai-opisnik' ); ?></button>
					</p>
				</form>
			</div>
		</div>

		<!-- SIDEBAR: LOG -->
		<div class="nutricia-ai-side">
			<div class="nutricia-ai-card">
				<h2><?php esc_html_e( 'Dnevnik aktivnosti', 'nutricia-ai-opisnik' ); ?></h2>
				<?php if ( empty( $log ) ) : ?>
					<p><em><?php esc_html_e( 'Još uvek nema zapisa.', 'nutricia-ai-opisnik' ); ?></em></p>
				<?php else : ?>
					<ul class="nutricia-ai-log">
						<?php foreach ( array_slice( $log, 0, 40 ) as $entry ) : ?>
							<li class="level-<?php echo esc_attr( $entry['level'] ); ?>">
								<span class="time"><?php echo esc_html( $entry['time'] ); ?></span>
								<?php if ( ! empty( $entry['product_id'] ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $entry['product_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( '#' . $entry['product_id'] ); ?></a>
								<?php endif; ?>
								<span class="msg"><?php echo esc_html( $entry['message'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<form method="post">
						<?php wp_nonce_field( 'nutricia_ai_actions' ); ?>
						<button type="submit" name="nutricia_ai_clear_log" class="button button-small"><?php esc_html_e( 'Obriši dnevnik', 'nutricia-ai-opisnik' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
