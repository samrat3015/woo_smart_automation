<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output Validator
 * 
 * Validates and sanitizes AI-generated HTML output.
 * Ensures output is safe, well-formed, and uses only allowed image URLs.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class OutputValidator {

	/**
	 * Validate and clean AI output
	 *
	 * @param string $raw_output Raw AI output
	 * @param array $allowed_image_urls List of allowed image URLs
	 * @return string Validated and cleaned HTML
	 */
	public static function validate( $raw_output, $allowed_image_urls = [] ) {
		if ( empty( $raw_output ) ) {
			return '';
		}

		$html = $raw_output;

		// 1. Strip markdown code fences
		$html = self::strip_code_fences( $html );

		// 2. Strip any leading/trailing explanatory text
		$html = self::extract_html_only( $html );

		// 3. Ensure valid HTML structure
		$html = self::ensure_html_structure( $html );

		// 4. Remove potentially dangerous elements
		$html = self::remove_dangerous_elements( $html );

		// 5. Validate image URLs if list provided
		if ( ! empty( $allowed_image_urls ) ) {
			$html = self::validate_image_urls( $html, $allowed_image_urls );
		}

		// 6. Clean up whitespace
		$html = self::clean_whitespace( $html );

		return $html;
	}

	/**
	 * Strip markdown code fences from output
	 *
	 * @param string $content Content to clean
	 * @return string Cleaned content
	 */
	private static function strip_code_fences( $content ) {
		// Remove opening code fence with optional language
		$content = preg_replace( '/^```(?:html|HTML)?\s*\n?/i', '', $content );
		
		// Remove closing code fence
		$content = preg_replace( '/\n?```\s*$/i', '', $content );

		// Also handle triple backticks in the middle (shouldn't happen but just in case)
		$content = preg_replace( '/```(?:html|HTML)?\s*\n/i', '', $content );
		$content = preg_replace( '/\n```/', '', $content );

		return trim( $content );
	}

	/**
	 * Extract only HTML content, removing explanatory text
	 *
	 * @param string $content Content to process
	 * @return string HTML only
	 */
	private static function extract_html_only( $content ) {
		// Find the start of HTML (<!DOCTYPE or <html or first <)
		$doctype_pos = stripos( $content, '<!DOCTYPE' );
		$html_pos = stripos( $content, '<html' );
		$first_tag_pos = strpos( $content, '<' );

		$start_pos = false;

		if ( $doctype_pos !== false ) {
			$start_pos = $doctype_pos;
		} elseif ( $html_pos !== false ) {
			$start_pos = $html_pos;
		} elseif ( $first_tag_pos !== false ) {
			$start_pos = $first_tag_pos;
		}

		if ( $start_pos !== false && $start_pos > 0 ) {
			$content = substr( $content, $start_pos );
		}

		// Find the end of HTML (</html> or last >)
		$html_close_pos = strripos( $content, '</html>' );
		if ( $html_close_pos !== false ) {
			$content = substr( $content, 0, $html_close_pos + 7 );
		}

		return trim( $content );
	}

	/**
	 * Ensure proper HTML structure
	 *
	 * @param string $html HTML to validate
	 * @return string HTML with proper structure
	 */
	private static function ensure_html_structure( $html ) {
		// Check if it starts with doctype or html tag
		$has_doctype = stripos( $html, '<!DOCTYPE' ) === 0;
		$has_html_tag = stripos( ltrim( $html ), '<html' ) === 0;

		// If it doesn't have proper structure, wrap it
		if ( ! $has_doctype && ! $has_html_tag ) {
			// Check if it has head and body
			$has_head = stripos( $html, '<head' ) !== false;
			$has_body = stripos( $html, '<body' ) !== false;

			if ( ! $has_head && ! $has_body ) {
				// It's just body content, wrap completely
				$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>
</head>
<body>
' . $html . '
</body>
</html>';
			} elseif ( ! $has_doctype ) {
				// Has structure but missing doctype
				$html = '<!DOCTYPE html>' . "\n" . $html;
			}
		}

		// Ensure viewport meta tag exists for responsive design
		if ( stripos( $html, 'viewport' ) === false ) {
			$html = preg_replace(
				'/(<head[^>]*>)/i',
				'$1' . "\n" . '    <meta name="viewport" content="width=device-width, initial-scale=1.0">',
				$html,
				1
			);
		}

		// Ensure charset meta tag exists
		if ( stripos( $html, 'charset' ) === false ) {
			$html = preg_replace(
				'/(<head[^>]*>)/i',
				'$1' . "\n" . '    <meta charset="UTF-8">',
				$html,
				1
			);
		}

		return $html;
	}

	/**
	 * Remove potentially dangerous elements
	 *
	 * @param string $html HTML to clean
	 * @return string Cleaned HTML
	 */
	private static function remove_dangerous_elements( $html ) {
		// Remove PHP tags
		$html = preg_replace( '/<\?php.*?\?>/is', '', $html );
		$html = preg_replace( '/<\?.*?\?>/is', '', $html );

		// Remove script tags with external sources (keep inline for potential tracking)
		// Actually, for security, remove ALL script tags
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );

		// Remove on* event handlers (onclick, onload, onerror, etc.)
		$html = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html );
		$html = preg_replace( '/\s+on\w+\s*=\s*[^\s>]+/i', '', $html );

		// Remove javascript: URLs
		$html = preg_replace( '/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html );
		$html = preg_replace( '/src\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html );

		// Remove data: URLs in images (can contain malicious content)
		// Allow only http, https, and relative URLs
		$html = preg_replace( '/src\s*=\s*["\']data:[^"\']*["\']/i', '', $html );

		// Remove iframe tags
		$html = preg_replace( '/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html );

		// Remove object and embed tags
		$html = preg_replace( '/<object\b[^>]*>.*?<\/object>/is', '', $html );
		$html = preg_replace( '/<embed\b[^>]*>/i', '', $html );

		// Remove form action to external URLs (keep forms but neutralize dangerous actions)
		$html = preg_replace( '/(<form[^>]*)\s+action\s*=\s*["\']https?:\/\/[^"\']*["\']/i', '$1', $html );

		// Remove base tags (could redirect all relative URLs)
		$html = preg_replace( '/<base\b[^>]*>/i', '', $html );

		// Remove meta refresh redirects
		$html = preg_replace( '/<meta[^>]*http-equiv\s*=\s*["\']refresh["\'][^>]*>/i', '', $html );

		return $html;
	}

	/**
	 * Validate image URLs against allowed list
	 *
	 * @param string $html HTML content
	 * @param array $allowed_urls List of allowed image URLs
	 * @return string HTML with validated image URLs
	 */
	private static function validate_image_urls( $html, $allowed_urls ) {
		if ( empty( $allowed_urls ) ) {
			return $html;
		}

		// Get site URL for relative path validation
		$site_url = site_url();
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		// Build pattern for allowed URLs
		$escaped_urls = array_map( function( $url ) {
			return preg_quote( $url, '/' );
		}, $allowed_urls );

		// Create callback to validate each image
		$callback = function( $matches ) use ( $allowed_urls, $site_url, $upload_url ) {
			$full_match = $matches[0];
			$url = $matches[2];

			// Check if URL is in allowed list
			if ( in_array( $url, $allowed_urls, true ) ) {
				return $full_match;
			}

			// Check if it's a relative URL from our site
			if ( strpos( $url, $site_url ) === 0 || strpos( $url, $upload_url ) === 0 ) {
				return $full_match;
			}

			// Check if it's a relative path
			if ( strpos( $url, '/' ) === 0 || strpos( $url, './' ) === 0 ) {
				return $full_match;
			}

			// URL is not allowed - replace with placeholder
			$placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="20" x="50%25" y="50%25" text-anchor="middle"%3EImage%3C/text%3E%3C/svg%3E';
			
			return str_replace( $url, $placeholder, $full_match );
		};

		// Process img src attributes
		$html = preg_replace_callback(
			'/(<img[^>]*\s+src\s*=\s*)(["\'])([^"\']+)\2/i',
			function( $matches ) use ( $callback ) {
				return $callback( [ $matches[0], $matches[1], $matches[3] ] );
			},
			$html
		);

		// Process CSS background-image URLs
		$html = preg_replace_callback(
			'/background(-image)?\s*:\s*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
			function( $matches ) use ( $allowed_urls, $site_url, $upload_url ) {
				$url = $matches[2];

				// Check if URL is allowed
				if ( in_array( $url, $allowed_urls, true ) ||
					 strpos( $url, $site_url ) === 0 ||
					 strpos( $url, $upload_url ) === 0 ||
					 strpos( $url, '/' ) === 0 ) {
					return $matches[0];
				}

				// Replace with solid color
				return 'background-color: #f0f0f0';
			},
			$html
		);

		return $html;
	}

	/**
	 * Clean up whitespace in HTML
	 *
	 * @param string $html HTML to clean
	 * @return string Cleaned HTML
	 */
	private static function clean_whitespace( $html ) {
		// Remove excessive blank lines (more than 2 consecutive)
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		// Trim each line
		$lines = explode( "\n", $html );
		$lines = array_map( 'rtrim', $lines );
		$html = implode( "\n", $lines );

		return trim( $html );
	}

	/**
	 * Check if HTML is valid/parseable
	 *
	 * @param string $html HTML to check
	 * @return bool True if valid
	 */
	public static function is_valid_html( $html ) {
		if ( empty( $html ) ) {
			return false;
		}

		// Check for basic HTML structure
		$has_html = stripos( $html, '<html' ) !== false || stripos( $html, '<!DOCTYPE' ) !== false;
		$has_body = stripos( $html, '<body' ) !== false;
		$has_tags = preg_match( '/<[a-z][\s\S]*>/i', $html );

		// At minimum, should have some HTML tags
		return $has_tags && ( $has_html || $has_body || preg_match( '/<(div|section|header|main|article)/i', $html ) );
	}

	/**
	 * Get validation errors for HTML
	 *
	 * @param string $html HTML to validate
	 * @return array List of validation errors
	 */
	public static function get_validation_errors( $html ) {
		$errors = [];

		if ( empty( $html ) ) {
			$errors[] = __( 'HTML content is empty.', 'woo-smart-automation' );
			return $errors;
		}

		// Check for unclosed tags (basic check)
		$open_tags = preg_match_all( '/<([a-z]+)(?:\s[^>]*)?>(?!.*<\/\1>)/is', $html );
		// This is a simplified check - a full validator would be more complex

		// Check for doctype
		if ( stripos( $html, '<!DOCTYPE' ) === false ) {
			$errors[] = __( 'Missing DOCTYPE declaration.', 'woo-smart-automation' );
		}

		// Check for html tag
		if ( stripos( $html, '<html' ) === false ) {
			$errors[] = __( 'Missing <html> tag.', 'woo-smart-automation' );
		}

		// Check for head tag
		if ( stripos( $html, '<head' ) === false ) {
			$errors[] = __( 'Missing <head> tag.', 'woo-smart-automation' );
		}

		// Check for body tag
		if ( stripos( $html, '<body' ) === false ) {
			$errors[] = __( 'Missing <body> tag.', 'woo-smart-automation' );
		}

		// Check for viewport meta (important for responsive design)
		if ( stripos( $html, 'viewport' ) === false ) {
			$errors[] = __( 'Missing viewport meta tag (required for mobile responsiveness).', 'woo-smart-automation' );
		}

		return $errors;
	}

	/**
	 * Extract title from HTML
	 *
	 * @param string $html HTML content
	 * @return string Title or empty string
	 */
	public static function extract_title( $html ) {
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	/**
	 * Extract all image URLs from HTML
	 *
	 * @param string $html HTML content
	 * @return array List of image URLs
	 */
	public static function extract_image_urls( $html ) {
		$urls = [];

		// From img src
		if ( preg_match_all( '/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $matches ) ) {
			$urls = array_merge( $urls, $matches[1] );
		}

		// From CSS background-image
		if ( preg_match_all( '/background(?:-image)?\s*:\s*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $html, $matches ) ) {
			$urls = array_merge( $urls, $matches[1] );
		}

		return array_unique( $urls );
	}
}
