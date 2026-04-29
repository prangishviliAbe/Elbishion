<?php
/**
 * Runtime interface language support.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates Elbishion admin interface strings from the plugin language setting.
 */
class Elbishion_I18n {

	const DEFAULT_LANGUAGE = 'ka';

	/**
	 * Initialize translation hooks.
	 */
	public static function init() {
		add_filter( 'gettext', array( __CLASS__, 'translate' ), 5, 3 );
		add_filter( 'gettext', array( __CLASS__, 'translate_wordpress_core' ), 6, 3 );
	}

	/**
	 * Get selected interface language.
	 *
	 * @return string
	 */
	public static function get_language() {
		$settings = get_option( 'elbishion_settings', array() );
		$language = is_array( $settings ) && ! empty( $settings['interface_language'] ) ? sanitize_key( $settings['interface_language'] ) : self::DEFAULT_LANGUAGE;

		return in_array( $language, array( 'ka', 'en' ), true ) ? $language : self::DEFAULT_LANGUAGE;
	}

	/**
	 * Translate plugin strings without requiring external .mo files.
	 *
	 * @param string $translation Current translation.
	 * @param string $text Original string.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function translate( $translation, $text, $domain ) {
		if ( 'elbishion' !== $domain ) {
			return $translation;
		}

		$language = self::get_language();
		$pairs    = self::pairs();
		$original = $text;
		$text     = self::repair_mojibake( $text );

		if ( 'ka' === $language && isset( $pairs[ $text ] ) ) {
			return $pairs[ $text ];
		}

		if ( 'en' === $language ) {
			$reverse = array_flip( $pairs );

			if ( isset( $reverse[ $text ] ) ) {
				return $reverse[ $text ];
			}
		}

		if ( $text !== $original ) {
			return $text;
		}

		return 'ka' === $language ? $text : $translation;
	}

	/**
	 * Repair Georgian text that was accidentally saved as UTF-8 mojibake.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function repair_mojibake( $text ) {
		if ( ! function_exists( 'iconv' ) ) {
			return $text;
		}

		$current = (string) $text;

		for ( $i = 0; $i < 2; $i++ ) {
			if ( false === strpos( $current, "\xC6\x92" ) && false === strpos( $current, "\xC3\x86" ) ) {
				break;
			}

			$repaired = @iconv( 'UTF-8', 'Windows-1252//IGNORE', $current ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false === $repaired || ! preg_match( '//u', $repaired ) ) {
				break;
			}

			if ( preg_match( '/[\x{10A0}-\x{10FF}]/u', $repaired ) ) {
				return $repaired;
			}

			$current = $repaired;
		}

		return $text;
	}

	/**
	 * Translate WordPress table controls on Elbishion admin pages.
	 *
	 * @param string $translation Current translation.
	 * @param string $text Original string.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function translate_wordpress_core( $translation, $text, $domain ) {
		if ( 'default' !== $domain || ! is_admin() || empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'elbishion' ) ) {
			return $translation;
		}

		if ( 'en' === self::get_language() ) {
			return $text;
		}

		$map = array(
			'Bulk actions' => 'მასობრივი ქმედებები',
			'Apply' => 'გამოყენება',
			'Search' => 'ძებნა',
			'Select All' => 'ყველას მონიშვნა',
			'%s item' => '%s ჩანაწერი',
			'%s items' => '%s ჩანაწერი',
			'items' => 'ჩანაწერი',
			'item' => 'ჩანაწერი',
		);

		return $map[ $text ] ?? $translation;
	}

	/**
	 * Source English to Georgian labels used across the admin interface.
	 *
	 * @return array
	 */
	private static function pairs() {
		return array(
			'All Submissions' => 'ყველა განაცხადი',
			'Unread' => 'წაუკითხავი',
			'Starred' => 'ვარსკვლავით მონიშნული',
			'Archive' => 'არქივი',
			'Settings' => 'პარამეტრები',
			'Forms' => 'ფორმები',
			'Integrations' => 'ინტეგრაციები',
			'Tools' => 'ხელსაწყოები',
			'Status' => 'სტატუსი',
			'Name' => 'სახელი',
			'Full Name' => 'სახელი და გვარი',
			'Contact Information' => 'საკონტაქტო ინფორმაცია',
			'Message' => 'შეტყობინება',
			'Page URL' => 'გვერდის ბმული',
			'Date' => 'თარიღი',
			'Actions' => 'ქმედებები',
			'View' => 'ნახვა',
			'Delete' => 'წაშლა',
			'Save Settings' => 'პარამეტრების შენახვა',
			'Save Integrations' => 'ინტეგრაციების შენახვა',
			'Filter' => 'გაფილტვრა',
			'All forms' => 'ყველა ფორმა',
			'All sources' => 'ყველა წყარო',
			'Newest first' => 'ჯერ ახალი',
			'Oldest first' => 'ჯერ ძველი',
			'No submissions found' => 'განაცხადები ვერ მოიძებნა',
			'No message' => 'შეტყობინება არ არის',
			'Course name not found' => 'კურსის დასახელება არ არის',
			'Form Name' => 'ფორმის სახელი',
			'Source Plugin' => 'წყარო',
			'Total' => 'სულ',
			'Last Submission' => 'ბოლო განაცხადი',
			'View submissions' => 'განაცხადების ნახვა',
			'No forms captured yet.' => 'ფორმები ჯერ არ არის დაფიქსირებული.',
			'Active' => 'აქტიური',
			'Not Active' => 'არააქტიური',
			'Enabled' => 'ჩართული',
			'Capture all forms' => 'ყველა ფორმის დაჭერა',
			'Save IP' => 'IP-ის შენახვა',
			'Save user agent' => 'ბრაუზერის ინფორმაციის შენახვა',
			'Selected forms' => 'არჩეული ფორმები',
			'Ignored forms' => 'გამოტოვებული ფორმები',
			'Interface language' => 'ინტერფეისის ენა',
			'Choose the language used inside Elbishion admin pages.' => 'აირჩიეთ ენა, რომელიც Elbishion-ის ადმინისტრაციის გვერდებზე გამოჩნდება.',
			'English' => 'ინგლისური',
			'Georgian' => 'ქართული',
			'Elbishion Settings' => 'Elbishion პარამეტრები',
			'Manage storage, privacy, pagination, notifications, and interface preferences.' => 'მართეთ მონაცემების შენახვა, კონფიდენციალურობა, გვერდებად დაყოფა, შეტყობინებები და ინტერფეისის პარამეტრები.',
			'IP address storage' => 'IP მისამართების შენახვა',
			'User-agent storage' => 'ბრაუზერის ინფორმაციის შენახვა',
			'Delete on uninstall' => 'წაშლა დეინსტალაციისას',
			'Items per page' => 'ჩანაწერები ერთ გვერდზე',
			'Email notifications' => 'ელფოსტის შეტყობინებები',
			'Notification email' => 'შეტყობინების ელფოსტა',
			'Duplicate prevention' => 'დუბლირების პრევენცია',
			'Duplicate time window in seconds' => 'დუბლირების დროის ფანჯარა წამებში',
			'Mask IP address' => 'IP მისამართის დაფარვა',
			'Automatically delete old submissions after X days' => 'ძველი განაცხადების ავტომატური წაშლა X დღის შემდეგ',
			'Store raw data' => 'Raw მონაცემების შენახვა',
			'Exclude hidden fields' => 'დამალული ველების გამოტოვება',
			'Exclude fields by keywords' => 'ველების გამოტოვება keyword-ებით',
			'Submitted Fields' => 'შევსებული ველები',
			'Submission Details' => 'განაცხადის დეტალები',
			'Submission ID' => 'განაცხადის ID',
			'User Agent' => 'ბრაუზერის ინფორმაცია',
			'Updated' => 'განახლდა',
			'Back to list' => 'სიაში დაბრუნება',
			'Submission not found' => 'განაცხადი ვერ მოიძებნა',
			'Read' => 'წაკითხული',
			'Mark read' => 'წაკითხულად',
			'Mark unread' => 'წაუკითხავად',
			'Shortcode' => 'Shortcode',
			'Elementor' => 'Elementor',
			'WordPress Native' => 'WordPress Native',
			'Custom HTML' => 'Custom HTML',
			'API' => 'API',
		);
	}
}
