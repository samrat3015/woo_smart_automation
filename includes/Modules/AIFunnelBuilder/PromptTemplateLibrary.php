<?php
namespace WooSmartAutomation\Modules\AIFunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompt Template Library
 * 
 * Manages built-in and custom AI prompt templates.
 *
 * @package WooSmartAutomation\Modules\AIFunnelBuilder
 * @since 1.1.0
 */
class PromptTemplateLibrary {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wsa_prompt_templates';
	}

	/**
	 * Get a template by slug
	 *
	 * @param string $slug Template slug
	 * @return array|null Template data or null
	 */
	public function get_template( $slug ) {
		global $wpdb;

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE slug = %s",
				$slug
			),
			ARRAY_A
		);

		return $template ?: null;
	}

	/**
	 * Get a template by ID
	 *
	 * @param int $id Template ID
	 * @return array|null Template data or null
	 */
	public function get_template_by_id( $id ) {
		global $wpdb;

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $template ?: null;
	}

	/**
	 * Get all templates
	 *
	 * @param array $args Query arguments
	 * @return array Templates
	 */
	public function get_templates( $args = [] ) {
		global $wpdb;

		$defaults = [
			'builtin_only' => false,
			'custom_only'  => false,
			'limit'        => 50,
			'offset'       => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = "1=1";

		if ( $args['builtin_only'] ) {
			$where .= " AND is_builtin = 1";
		} elseif ( $args['custom_only'] ) {
			$where .= " AND is_builtin = 0";
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY is_builtin DESC, name ASC LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Create a new template
	 *
	 * @param array $data Template data
	 * @return int|false Template ID or false on failure
	 */
	public function create_template( $data ) {
		global $wpdb;

		$slug = sanitize_title( $data['name'] ?? 'template' );

		// Ensure unique slug
		$existing = $this->get_template( $slug );
		if ( $existing ) {
			$slug .= '-' . time();
		}

		$result = $wpdb->insert(
			$this->table_name,
			[
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'prompt_body' => $data['prompt_body'] ?? '',
				'is_builtin'  => 0,
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a template
	 *
	 * @param int $id Template ID
	 * @param array $data Updated data
	 * @return bool Success status
	 */
	public function update_template( $id, $data ) {
		global $wpdb;

		$template = $this->get_template_by_id( $id );

		if ( ! $template || $template['is_builtin'] ) {
			return false; // Cannot update built-in templates
		}

		$update_data = [
			'updated_at' => current_time( 'mysql' ),
		];

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['prompt_body'] ) ) {
			$update_data['prompt_body'] = $data['prompt_body'];
		}

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			[ 'id' => $id ],
			array_fill( 0, count( $update_data ), '%s' ),
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a template
	 *
	 * @param int $id Template ID
	 * @return bool Success status
	 */
	public function delete_template( $id ) {
		global $wpdb;

		$template = $this->get_template_by_id( $id );

		if ( ! $template || $template['is_builtin'] ) {
			return false; // Cannot delete built-in templates
		}

		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Seed built-in templates
	 * Called during plugin activation
	 */
	public static function seed_builtin_templates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wsa_prompt_templates';

		$builtin_templates = self::get_builtin_template_definitions();

		foreach ( $builtin_templates as $template ) {
			// Check if already exists
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE slug = %s",
					$template['slug']
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					$table_name,
					[
						'name'        => $template['name'],
						'slug'        => $template['slug'],
						'description' => $template['description'],
						'prompt_body' => $template['prompt_body'],
						'is_builtin'  => 1,
						'created_by'  => null,
						'created_at'  => current_time( 'mysql' ),
						'updated_at'  => current_time( 'mysql' ),
					],
					[ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
				);
			}
		}
	}

	/**
	 * Get built-in template definitions
	 *
	 * @return array Template definitions
	 */
	public static function get_builtin_template_definitions() {
		return [
			[
				'name'        => 'Bangladesh Health Product',
				'slug'        => 'bd-health-product',
				'description' => 'Optimized for health supplements and wellness products in the Bangladesh market. Includes Bangla-English mixed content.',
				'prompt_body' => self::get_bd_health_template(),
			],
			[
				'name'        => 'Bangladesh Fashion Product',
				'slug'        => 'bd-fashion-product',
				'description' => 'For clothing, accessories, and fashion items targeting Bangladesh customers.',
				'prompt_body' => self::get_bd_fashion_template(),
			],
			[
				'name'        => 'Bangladesh Electronics',
				'slug'        => 'bd-electronics',
				'description' => 'For electronics, gadgets, and tech products in Bangladesh market.',
				'prompt_body' => self::get_bd_electronics_template(),
			],
			[
				'name'        => 'General E-commerce',
				'slug'        => 'ecom-general',
				'description' => 'A versatile template for any e-commerce product. Clean and professional design.',
				'prompt_body' => self::get_ecom_general_template(),
			],
			[
				'name'        => 'Lead Capture Page',
				'slug'        => 'lead-capture',
				'description' => 'For email list building and lead generation. Focus on capturing visitor information.',
				'prompt_body' => self::get_lead_capture_template(),
			],
			[
				'name'        => 'Product Launch',
				'slug'        => 'product-launch',
				'description' => 'High-impact landing page for product launches with countdown and urgency elements.',
				'prompt_body' => self::get_product_launch_template(),
			],
		];
	}

	/**
	 * Bangladesh Health Product Template
	 */
	private static function get_bd_health_template() {
		return <<<'EOT'
You are an expert landing page designer specializing in Bangladeshi eCommerce.

Create a complete, high-converting landing page for the following health/wellness product:
- Product Name: {{product_name}}
- Product Price: {{product_price}}
- Product Description: {{product_description}}

DESIGN REQUIREMENTS:
- Mobile-first responsive design (most traffic is from mobile)
- Color scheme: Red (#e74c3c) as primary, White (#ffffff) background, Dark gray (#333333) text
- Bangla-English mixed content approach:
  - Headlines and emotional text in Bangla
  - Technical terms and UI labels can be in English
- Clean, modern design with good contrast and readability

REQUIRED SECTIONS (in order):
1. **Hero Section**
   - Large product image or background
   - Compelling Bangla headline (আজই শুরু করুন... or similar)
   - Price with "ছাড়" (discount) indicator if applicable
   - Primary CTA button

2. **Benefits Section**
   - 4-6 key benefits with emoji icons (✅, 💪, 🌿, etc.)
   - Short, punchy Bangla text for each benefit
   - Use icons/emojis instead of heavy images

3. **Product Showcase**
   - Clean product image display
   - Key features list
   - Trust indicators (certified, natural, etc.)

4. **Social Proof / Testimonials**
   - 3 customer testimonials with Bangladeshi names
   - Star ratings (⭐⭐⭐⭐⭐)
   - Location (Dhaka, Chittagong, etc.)

5. **Delivery & Guarantee Section**
   - 🚚 Free delivery or delivery cost info
   - 💯 Money-back guarantee if applicable
   - ☎️ Customer support number

6. **Order Form Zone**
   - Include this exact placeholder for the checkout form:
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>

7. **Sticky Mobile CTA** (position: fixed at bottom on mobile)
   - "অর্ডার করুন" button
   - Price reminder

TECHNICAL REQUIREMENTS:
- Return ONLY valid HTML5 with embedded <style> block
- No external CSS frameworks (no Bootstrap, Tailwind, etc.)
- No JavaScript code
- All CSS inside a single <style> tag in the <head>
- Use semantic HTML: <header>, <main>, <section>, <footer>
- Mobile breakpoint: @media (max-width: 768px)
- Use Bengali Unicode text directly, no transliteration

{{image_placement_block}}
EOT;
	}

	/**
	 * Bangladesh Fashion Product Template
	 */
	private static function get_bd_fashion_template() {
		return <<<'EOT'
You are an expert landing page designer for Bangladeshi fashion eCommerce.

Create a stylish, high-converting landing page for:
- Product Name: {{product_name}}
- Product Price: {{product_price}}
- Product Description: {{product_description}}

DESIGN REQUIREMENTS:
- Mobile-first responsive design
- Modern, clean aesthetic with focus on product imagery
- Color scheme: Elegant and fashion-forward (suggest based on product)
- Typography: Clean, readable fonts

REQUIRED SECTIONS:
1. **Hero Section** - Full-width product image with overlay text
2. **Product Details** - Size, material, care instructions
3. **Why Choose Us** - Quality, authentic, fast delivery
4. **Customer Reviews** - 3 reviews with ratings
5. **Size Guide** (if applicable)
6. **Order Section**
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>
7. **Footer** - Contact, return policy

TECHNICAL: Valid HTML5 + embedded CSS only. Mobile-first. No JS.

{{image_placement_block}}
EOT;
	}

	/**
	 * Bangladesh Electronics Template
	 */
	private static function get_bd_electronics_template() {
		return <<<'EOT'
You are an expert landing page designer for electronics and gadgets.

Create a tech-focused landing page for:
- Product Name: {{product_name}}
- Product Price: {{product_price}}
- Product Description: {{product_description}}

DESIGN REQUIREMENTS:
- Clean, modern tech aesthetic
- Dark theme option or light with tech blue accents (#0066cc)
- Focus on specifications and features
- Mobile-responsive

REQUIRED SECTIONS:
1. **Hero** - Product showcase with key specs
2. **Features Grid** - 6 key features with icons
3. **Specifications Table** - Technical details
4. **What's in the Box** - Package contents
5. **Warranty & Support** - Warranty info, support contact
6. **Customer Reviews** - 3 verified reviews
7. **Order Now**
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>

TECHNICAL: Valid HTML5 + embedded CSS. No external resources.

{{image_placement_block}}
EOT;
	}

	/**
	 * General E-commerce Template
	 */
	private static function get_ecom_general_template() {
		return <<<'EOT'
Create a professional, conversion-optimized landing page for:
- Product Name: {{product_name}}
- Product Price: {{product_price}}
- Product Description: {{product_description}}

DESIGN: Clean, modern, professional. Mobile-first responsive.

SECTIONS:
1. Hero with product highlight
2. Key Benefits (4-6 points)
3. Product Features
4. Social Proof / Testimonials (3)
5. FAQ Section (3-4 common questions)
6. Order Form:
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>
7. Footer with trust badges

OUTPUT: Valid HTML5 with embedded <style>. No JS. No external CSS.

{{image_placement_block}}
EOT;
	}

	/**
	 * Lead Capture Template
	 */
	private static function get_lead_capture_template() {
		return <<<'EOT'
Create a high-converting lead capture landing page.

PURPOSE: Collect email addresses / phone numbers for:
- Product Name: {{product_name}}
- Value Proposition: {{product_description}}

DESIGN: Clean, focused, minimal distractions. Single CTA.

SECTIONS:
1. Headline - Clear value proposition
2. Subheadline - What they'll get
3. Benefits - 3-4 bullet points
4. Lead Form:
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>
5. Trust indicators - Privacy, no spam promise

TECHNICAL: Valid HTML5 + CSS. Mobile responsive. No JS.

{{image_placement_block}}
EOT;
	}

	/**
	 * Product Launch Template
	 */
	private static function get_product_launch_template() {
		return <<<'EOT'
Create an exciting product launch landing page for:
- Product Name: {{product_name}}
- Product Price: {{product_price}}
- Product Description: {{product_description}}

DESIGN: High-energy, urgency-driven, exciting visuals.

REQUIRED ELEMENTS:
1. **Hero** - Big announcement feel, "New Launch" or "সীমিত সময়"
2. **Product Reveal** - Showcase with key features
3. **Limited Time Offer** - Urgency messaging, early bird pricing
4. **Features & Benefits** - What makes it special
5. **Early Reviews** - 2-3 testimonials
6. **Order Now - Limited Stock**
   <div id="wsa-checkout-form" class="wsa-checkout-container">
       <!-- Checkout form will be injected here automatically -->
   </div>
7. **Countdown Feel** - "Only X left" or "Offer ends soon"

TECHNICAL: HTML5 + CSS only. Mobile-first. High contrast for urgency.

{{image_placement_block}}
EOT;
	}

	/**
	 * Resolve a prompt with variable replacements
	 *
	 * @param string $template_slug Template slug or 'custom'
	 * @param string $custom_prompt Custom prompt text
	 * @param array $variables Variable replacements
	 * @return string Resolved prompt
	 */
	public function resolve_prompt( $template_slug, $custom_prompt, $variables ) {
		$prompt = '';

		// Get template prompt if not custom
		if ( $template_slug && $template_slug !== 'custom' ) {
			$template = $this->get_template( $template_slug );
			if ( $template ) {
				$prompt = $template['prompt_body'];
			}
		}

		// Append or use custom prompt
		if ( ! empty( $custom_prompt ) ) {
			if ( ! empty( $prompt ) ) {
				$prompt .= "\n\n--- ADDITIONAL INSTRUCTIONS ---\n" . $custom_prompt;
			} else {
				$prompt = $custom_prompt;
			}
		}

		// Replace variables
		foreach ( $variables as $key => $value ) {
			$prompt = str_replace( '{{' . $key . '}}', $value, $prompt );
		}

		return $prompt;
	}
}
