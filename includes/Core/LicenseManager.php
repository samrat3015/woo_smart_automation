<?php
namespace WooSmartAutomation\Core;

/**
 * License Manager
 * Handles license activation, validation, and caching
 */
class LicenseManager {

    /**
     * License API endpoint
     */
    const API_URL = 'https://license.menorabd.com/api/licence/activate';

    /**
     * Product slug for license validation
     */
    const PRODUCT_SLUG = 'woo-smart-shield';

    /**
     * Option keys for storing license data
     */
    const LICENSE_KEY_OPTION = 'wsa_license_key';
    const LICENSE_DATA_OPTION = 'wsa_license_data';
    const LICENSE_STATUS_OPTION = 'wsa_license_status';

    /**
     * Initialize license manager hooks
     */
    public function init() {
        // AJAX handlers
        add_action( 'wp_ajax_wsa_activate_license', [ $this, 'ajax_activate_license' ] );
        add_action( 'wp_ajax_wsa_deactivate_license', [ $this, 'ajax_deactivate_license' ] );
        add_action( 'wp_ajax_wsa_check_license', [ $this, 'ajax_check_license' ] );
    }

    /**
     * Get current site domain
     * 
     * @return string
     */
    public static function get_current_domain() {
        $site_url = get_site_url();
        $parsed = wp_parse_url( $site_url );
        $domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
        
        // Remove www. prefix if present
        $domain = preg_replace( '/^www\./', '', $domain );
        
        return $domain;
    }

    /**
     * Activate license via API
     * 
     * @param string $license_key
     * @return array
     */
    public static function activate_license( $license_key ) {
        $domain = self::get_current_domain();

        $response = wp_remote_post( self::API_URL, [
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'        => wp_json_encode([
                'licence_key'  => $license_key,
                'domain'       => $domain,
                'product_slug' => self::PRODUCT_SLUG,
            ]),
        ]);

        // Check for WP error
        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return [
                'success' => false,
                'message' => 'Invalid response from license server',
            ];
        }

        // If activation successful, save to database
        if ( isset( $data['success'] ) && $data['success'] === true ) {
            self::save_license_data( $license_key, $data['data'] );
        }

        return $data;
    }

    /**
     * Save license data to WordPress database
     * 
     * @param string $license_key
     * @param array $license_data
     */
    public static function save_license_data( $license_key, $license_data ) {
        update_option( self::LICENSE_KEY_OPTION, $license_key );
        update_option( self::LICENSE_DATA_OPTION, $license_data );
        update_option( self::LICENSE_STATUS_OPTION, 'active' );
        
        // Store activation timestamp
        update_option( 'wsa_license_activated_at', current_time( 'mysql' ) );
    }

    /**
     * Get stored license data
     * 
     * @return array|false
     */
    public static function get_license_data() {
        return get_option( self::LICENSE_DATA_OPTION, false );
    }

    /**
     * Get stored license key
     * 
     * @return string
     */
    public static function get_license_key() {
        return get_option( self::LICENSE_KEY_OPTION, '' );
    }

    /**
     * Check if license is active
     * 
     * @return bool
     */
    public static function is_license_active() {
        $status = get_option( self::LICENSE_STATUS_OPTION, '' );
        $license_data = self::get_license_data();

        if ( $status !== 'active' || empty( $license_data ) ) {
            return false;
        }

        // Check if license is stored and has valid status
        if ( isset( $license_data['status'] ) && $license_data['status'] === 'active' ) {
            // Check expiration if not lifetime
            if ( isset( $license_data['type'] ) && $license_data['type'] !== 'lifetime' ) {
                if ( isset( $license_data['expires_at'] ) && ! empty( $license_data['expires_at'] ) ) {
                    $expires_at = strtotime( $license_data['expires_at'] );
                    if ( $expires_at < time() ) {
                        // License expired
                        update_option( self::LICENSE_STATUS_OPTION, 'expired' );
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Get license plan
     * 
     * @return string
     */
    public static function get_license_plan() {
        $license_data = self::get_license_data();
        return isset( $license_data['plan'] ) ? $license_data['plan'] : '';
    }

    /**
     * Get available features
     * 
     * @return array
     */
    public static function get_features() {
        $license_data = self::get_license_data();
        return isset( $license_data['features'] ) ? $license_data['features'] : [];
    }

    /**
     * Check if a specific feature is available
     * 
     * @param string $feature_name
     * @return bool
     */
    public static function has_feature( $feature_name ) {
        if ( ! self::is_license_active() ) {
            return false;
        }

        $features = self::get_features();
        return in_array( $feature_name, $features, true );
    }

    /**
     * Deactivate license locally
     */
    public static function deactivate_license() {
        delete_option( self::LICENSE_KEY_OPTION );
        delete_option( self::LICENSE_DATA_OPTION );
        delete_option( self::LICENSE_STATUS_OPTION );
        delete_option( 'wsa_license_activated_at' );
    }

    /**
     * AJAX: Activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer( 'wsa_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access' ] );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a license key' ] );
        }

        $result = self::activate_license( $license_key );

        if ( isset( $result['success'] ) && $result['success'] === true ) {
            wp_send_json_success( [
                'message' => $result['message'],
                'data'    => $result['data'],
            ] );
        } else {
            wp_send_json_error( [
                'message' => isset( $result['message'] ) ? $result['message'] : 'License activation failed',
            ] );
        }
    }

    /**
     * AJAX: Deactivate license locally
     */
    public function ajax_deactivate_license() {
        check_ajax_referer( 'wsa_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access' ] );
        }

        self::deactivate_license();

        wp_send_json_success( [ 'message' => 'License deactivated successfully' ] );
    }

    /**
     * AJAX: Check license status
     */
    public function ajax_check_license() {
        check_ajax_referer( 'wsa_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access' ] );
        }

        $is_active = self::is_license_active();
        $license_data = self::get_license_data();

        wp_send_json_success( [
            'is_active'    => $is_active,
            'license_data' => $license_data,
        ] );
    }

    /**
     * Verify license with server (optional - for periodic checks)
     * 
     * @return bool
     */
    public static function verify_license_with_server() {
        $license_key = self::get_license_key();
        
        if ( empty( $license_key ) ) {
            return false;
        }

        $result = self::activate_license( $license_key );
        
        return isset( $result['success'] ) && $result['success'] === true;
    }

    /**
     * Get license status text
     * 
     * @return string
     */
    public static function get_status_text() {
        $status = get_option( self::LICENSE_STATUS_OPTION, '' );
        
        switch ( $status ) {
            case 'active':
                return 'Active';
            case 'expired':
                return 'Expired';
            case 'invalid':
                return 'Invalid';
            default:
                return 'Not Activated';
        }
    }

    /**
     * Get status class for styling
     * 
     * @return string
     */
    public static function get_status_class() {
        $status = get_option( self::LICENSE_STATUS_OPTION, '' );
        
        switch ( $status ) {
            case 'active':
                return 'wsa-status-active';
            case 'expired':
                return 'wsa-status-expired';
            case 'invalid':
                return 'wsa-status-invalid';
            default:
                return 'wsa-status-inactive';
        }
    }
}
