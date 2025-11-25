<?php
/**
 * Helper class for SEO check help texts.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

/**
 * Provides help texts for SEO checks.
 */
class CheckHelpText {
	/**
	 * Get check importance explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	public function get_importance( string $check_id ): string {
		$importance_map = array(
			'title_length'       => __( 'Il titolo è la prima cosa che gli utenti vedono nelle SERP di Google. Un titolo ben ottimizzato (50-60 caratteri) viene mostrato completamente nei risultati e attira più clic.', 'fp-seo-performance' ),
			'meta_description'   => __( 'La meta description appare sotto il titolo nelle ricerche Google. Una buona description (150-160 caratteri) aumenta il CTR (tasso di clic) del 30-50%.', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'La focus keyword nel titolo aiuta Google a capire l\'argomento principale. I titoli con keyword target rankano in media 15 posizioni più in alto.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Una densità keyword ottimale (1-2%) aiuta il posizionamento senza penalizzazioni per keyword stuffing. Troppo poche keyword = difficile rankare; troppe = penalizzazione Google.', 'fp-seo-performance' ),
			'content_length'     => __( 'Contenuti più lunghi (>1000 parole) tendono a rankare meglio perché forniscono informazioni più complete. Articoli lunghi ottengono il 77% dei backlink.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Una struttura H1-H6 corretta aiuta Google a capire la gerarchia del contenuto. Migliora anche l\'accessibilità per screen reader.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Gli attributi ALT sulle immagini migliorano l\'accessibilità e aiutano il ranking in Google Immagini. Il 27% del traffico organico viene da immagini.', 'fp-seo-performance' ),
			'internal_links'     => __( 'I link interni distribuiscono autorità SEO tra le pagine e aiutano Google a scoprire nuovi contenuti. Siti con buona link structure rankano il 40% meglio.', 'fp-seo-performance' ),
			'external_links'     => __( 'Link a fonti autorevoli aumentano la credibilità del contenuto. Google considera i link esterni un segnale di qualità e profondità dell\'articolo.', 'fp-seo-performance' ),
			'readability'        => __( 'Un contenuto leggibile (punteggio Flesch >60) mantiene gli utenti più tempo sulla pagina, riducendo il bounce rate. Google favorisce contenuti comprensibili.', 'fp-seo-performance' ),
		);

		return $importance_map[ $check_id ] ?? __( 'Questo check SEO è importante per il posizionamento organico del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check how-to-fix explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	public function get_howto( string $check_id ): string {
		$howto_map = array(
			'title_length'       => __( 'Modifica il titolo per mantenerlo tra 50-60 caratteri. Includi la keyword principale all\'inizio. Se troppo lungo, Google lo tronca con "..." perdendo impatto.', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scrivi una description di 150-160 caratteri che riassume il contenuto e include la focus keyword. Usa un tono coinvolgente e aggiungi una call-to-action (CTA).', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Inserisci la focus keyword nel campo apposito sopra, poi assicurati che appaia nel titolo (preferibilmente all\'inizio), nei primi 100 caratteri del contenuto e in almeno un H2.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Aggiungi o rimuovi keyword per raggiungere 1-2% di densità. Usa sinonimi e keyword correlate (LSI keywords) invece di ripetere sempre la stessa keyword.', 'fp-seo-performance' ),
			'content_length'     => __( 'Espandi il contenuto aggiungendo sezioni utili: esempi pratici, FAQ, statistiche, case study. Punta a minimo 1000 parole per argomenti informativi, 500+ per pagine commerciali.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Usa un solo H1 (titolo principale), poi H2 per sezioni principali, H3 per sottosezioni. Non saltare livelli (es: da H2 a H4). Includi keyword nei heading quando possibile.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Aggiungi un attributo ALT descrittivo a ogni immagine. Descrivi cosa mostra l\'immagine includendo keyword dove appropriato. Es: "screenshot plugin SEO WordPress" invece di "immagine1".', 'fp-seo-performance' ),
			'internal_links'     => __( 'Aggiungi 2-5 link interni a pagine/post correlati. Usa anchor text descrittivo (no "clicca qui"). Link a contenuti pillar e articoli correlati per creare topic clusters.', 'fp-seo-performance' ),
			'external_links'     => __( 'Aggiungi 1-3 link a fonti autorevoli (.gov, .edu, siti riconosciuti nel settore). Apri in nuova tab e usa rel="noopener noreferrer" per sicurezza.', 'fp-seo-performance' ),
			'readability'        => __( 'Semplifica le frasi (max 20 parole). Usa paragrafi corti (3-4 righe). Aggiungi elenchi puntati. Evita gergo tecnico o spiegalo. Usa sottotitoli per spezzare il testo.', 'fp-seo-performance' ),
		);

		return $howto_map[ $check_id ] ?? __( 'Segui le best practices SEO per ottimizzare questo aspetto del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check example
	 *
	 * @param string $check_id Check identifier.
	 * @return string|null
	 */
	public function get_example( string $check_id ): ?string {
		$example_map = array(
			'title_length'       => __( 'Guida SEO WordPress: 10 Trucchi per Rankare nel 2025', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scopri 10 tecniche SEO WordPress avanzate per migliorare il ranking nel 2025. Guida pratica con esempi reali e risultati garantiti. Leggi ora!', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Se keyword = "wordpress seo", includi nel titolo: "WordPress SEO: Guida Completa 2025"', 'fp-seo-performance' ),
			'headings_structure' => __( 'H1: Titolo principale | H2: Cos\'è la SEO | H3: Tecniche on-page | H3: Tecniche off-page', 'fp-seo-performance' ),
			'images_alt'         => __( 'ALT="screenshot dashboard plugin SEO WordPress con analytics traffico organico"', 'fp-seo-performance' ),
		);

		return $example_map[ $check_id ] ?? null;
	}
}




