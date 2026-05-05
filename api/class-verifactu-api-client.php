<?php
/**
 * API Client — InFoAL VeriFactu REST API
 *
 * Wraps all HTTP communication with https://verifactu.infoal.io/api_v2
 * using wp_remote_post() instead of cURL to follow WordPress best practices.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Verifactu_Api_Client {

	// ── Constants ─────────────────────────────────────────────────────────────

	const TIMEOUT = 15; // seconds per request

	// ── Properties ────────────────────────────────────────────────────────────

	/** @var string Bearer token */
	private $token;

	/** @var bool Debug mode */
	private $debug;

	// ── Constructor ───────────────────────────────────────────────────────────

	/**
	 * @param string $token  API bearer token.
	 * @param bool   $debug  Whether to write verbose logs.
	 */
	public function __construct( $token = '', $debug = false ) {
		$this->token = $token ?: Verifactu_Infoal::get_api_token();
		$this->debug = $debug || Verifactu_Infoal::is_debug();
	}

	// ── Public API endpoints ──────────────────────────────────────────────────

	/**
	 * Validate the current API token.
	 * Equivalent to ApiVerifactu::checkApiStatus() — testkey endpoint.
	 *
	 * @return array{response:string, error?:string}
	 */
	public function test_key() {
		return $this->post( 'verifactu/testkey', [] );
	}

	/**
	 * Get AEAT service operational status.
	 *
	 * @return array{response:string, status?:string, error?:string}
	 */
	public function get_aeat_status() {
		return $this->post( 'verifactu/status', [] );
	}

	/**
	 * Send an alta (invoice) or abono (credit note) to AEAT via InFoAL API.
	 * Equivalent to the cURL POST in ApiVerifactu::sendAltaVerifactu().
	 *
	 * @param  array $payload {buyer, invoice, ...}
	 * @return array{response:string, id_reg_fact?:int, urlQR?:string, apiMode?:string, error?:string}
	 */
	public function send_alta( array $payload ) {
		return $this->post( 'verifactu/alta', $payload );
	}

	/**
	 * Send a cancellation (anulacion) to AEAT.
	 *
	 * @param  array $payload {InvoiceNumber: string}
	 * @return array{response:string, id_reg_fact?:int, error?:string}
	 */
	public function send_anulacion( array $payload ) {
		return $this->post( 'verifactu/anulacion', $payload );
	}

	/**
	 * Check the status of pending records.
	 * Equivalent to ApiVerifactu::_checkPendingStatuses().
	 *
	 * @param  int[] $ids Array of id_reg_fact integers.
	 * @return array List of status objects from the API.
	 */
	public function check_pending( array $ids ) {
		$result = $this->post( 'verifactu/check', [ 'ids' => $ids ] );

		// The API returns an array of objects, not a {response: ...} envelope
		if ( is_array( $result ) && isset( $result['_raw'] ) ) {
			return $result['_raw'];
		}
		return $result;
	}

	/**
	 * Validate a customer NIF/DNI via InFoAL CDI service.
	 *
	 * @param  string $nif   NIF or DNI to validate.
	 * @param  string $name  Full name of the person/company.
	 * @return array{valid?:bool, error?:string}
	 */
	public function check_nif( $nif, $name = '' ) {
		return $this->post( 'cdi/check', [
			'dni'    => sanitize_text_field( $nif ),
			'nombre' => sanitize_text_field( $name ),
		] );
	}

	// ── Facturae endpoints ────────────────────────────────────────────────────

	/**
	 * Generate a Facturae 3.2.2 electronic invoice (invoice or refund).
	 *
	 * @param  array $payload Facturae payload.
	 * @return array{response:string, id_facturae?:int, error?:string}
	 */
	public function facturae_alta( array $payload ) {
		return $this->post( 'facturae/alta', $payload );
	}

	/**
	 * Download signed .xsig Facturae file (streams binary).
	 *
	 * @param  array $payload {id_facturae: int}
	 * @return string|WP_Error Raw binary content or WP_Error.
	 */
	public function facturae_download_xsig( array $payload ) {
		return $this->post_raw( 'facturae/download', $payload );
	}

	/**
	 * Download unsigned .xml Facturae file (streams binary).
	 *
	 * @param  array $payload {id_facturae: int}
	 * @return string|WP_Error Raw binary content or WP_Error.
	 */
	public function facturae_download_xml( array $payload ) {
		return $this->post_raw( 'facturae/download_xml', $payload );
	}

	/**
	 * Submit a Facturae to FACe.
	 *
	 * @param  array $payload {id_facturae: int}
	 * @return array{response:string, error?:string}
	 */
	public function facturae_send_face( array $payload ) {
		return $this->post( 'facturae/send_face', $payload );
	}

	/**
	 * Get FACe submission status.
	 *
	 * @param  array $payload {id_facturae: int}
	 * @return array{response:string, estado?:string, error?:string}
	 */
	public function facturae_face_status( array $payload ) {
		return $this->post( 'facturae/face_status', $payload );
	}

	// ── Support ───────────────────────────────────────────────────────────────

	/**
	 * Send a diagnostic report to InFoAL support.
	 *
	 * @param  array $diagnostic_data System info payload.
	 * @return array{response:string}
	 */
	public function send_diagnostic( array $diagnostic_data ) {
		return $this->post( 'support/diagnostic', $diagnostic_data );
	}

	// ── Core HTTP transport ───────────────────────────────────────────────────

	/**
	 * Make an authenticated POST request and decode the JSON response.
	 *
	 * @param  string $endpoint  Relative endpoint path (no leading slash).
	 * @param  array  $data      Payload to JSON-encode.
	 * @return array             Decoded response array; always has at minimum
	 *                           ['response' => 'OK'|'KO', 'error' => '…'] keys.
	 */
	public function post( $endpoint, array $data ) {
		$url     = trailingslashit( VERIFACTU_INFOAL_API_BASE ) . ltrim( $endpoint, '/' );
		$body    = wp_json_encode( $data );
		$headers = $this->build_headers();

		if ( $this->debug ) {
			Verifactu_Infoal::log(
				'API POST → ' . $endpoint . ' | payload: ' . $body,
				0,
				'verifactu-api'
			);
		}

		$response = wp_remote_post( $url, [
			'method'      => 'POST',
			'timeout'     => self::TIMEOUT,
			'headers'     => $headers,
			'body'        => $body,
			'data_format' => 'body',
		] );

		return $this->handle_response( $response, $endpoint );
	}

	/**
	 * Make a POST request and return the raw response body (for binary downloads).
	 *
	 * @param  string $endpoint
	 * @param  array  $data
	 * @return string|WP_Error Raw body string or WP_Error.
	 */
	public function post_raw( $endpoint, array $data ) {
		$url      = trailingslashit( VERIFACTU_INFOAL_API_BASE ) . ltrim( $endpoint, '/' );
		$body     = wp_json_encode( $data );
		$headers  = $this->build_headers();

		$response = wp_remote_post( $url, [
			'method'      => 'POST',
			'timeout'     => 30,
			'headers'     => $headers,
			'body'        => $body,
			'data_format' => 'body',
		] );

		if ( is_wp_error( $response ) ) {
			Verifactu_Infoal::log( 'API raw error on ' . $endpoint . ': ' . $response->get_error_message(), 3 );
			return $response;
		}

		return wp_remote_retrieve_body( $response );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Build common HTTP request headers.
	 *
	 * @return array
	 */
	private function build_headers() {
		return [
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
	}

	/**
	 * Process a wp_remote_post() response.
	 *
	 * @param  array|WP_Error $response
	 * @param  string         $endpoint  For logging context.
	 * @return array
	 */
	private function handle_response( $response, $endpoint ) {
		// Network / DNS / timeout errors
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Verifactu_Infoal::log( 'API connection error on ' . $endpoint . ': ' . $msg, 3 );
			return [
				'response' => 'KO',
				'error'    => 'Error de conexión con la API VeriFactu: ' . $msg,
			];
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );

		if ( $this->debug ) {
			Verifactu_Infoal::log(
				'API ← ' . $endpoint . ' [HTTP ' . $http_code . '] ' . $body,
				0,
				'verifactu-api'
			);
		}

		// Try to decode JSON
		$decoded = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Verifactu_Infoal::log( 'API non-JSON response on ' . $endpoint . ': ' . $body, 2 );
			return [
				'response' => 'KO',
				'error'    => 'Respuesta no válida de la API (HTTP ' . $http_code . ')',
			];
		}

		// The check endpoint returns an array of objects, not a keyed array
		if ( is_array( $decoded ) && ! isset( $decoded['response'] ) ) {
			return [ '_raw' => $decoded, 'response' => 'OK' ];
		}

		if ( ! is_array( $decoded ) ) {
			return [
				'response' => 'KO',
				'error'    => 'Formato de respuesta inesperado de la API.',
			];
		}

		return $decoded;
	}
}
