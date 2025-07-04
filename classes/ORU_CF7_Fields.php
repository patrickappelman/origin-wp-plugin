<?php
/**
 * Custom Contact Form 7 field types for Origin Recruitment
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_CF7_Fields.php
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class ORU_CF7_Fields {
	public function __construct() {
		add_action('wpcf7_init', [$this, 'register_form_tags']);
		// Add tag generator for backend editor
		add_action('admin_init', [$this, 'register_tag_generator'], 20);
		// Add JavaScript for tag generator and client-side validation
		add_action('wp_enqueue_scripts', [$this, 'enqueue_validation_script']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_tag_generator_script']);
		// Add validation filters with higher priority for AJAX compatibility
		add_filter('wpcf7_validate_language_single*', [$this, 'validate_select'], 5, 2);
		add_filter('wpcf7_validate_language_multi*', [$this, 'validate_multi_select'], 5, 2);
		add_filter('wpcf7_validate_country_single*', [$this, 'validate_select'], 5, 2);
		add_filter('wpcf7_validate_country_multi*', [$this, 'validate_multi_select'], 5, 2);
		add_filter('wpcf7_validate_industry_single*', [$this, 'validate_select'], 5, 2);
		add_filter('wpcf7_validate_industry_multi*', [$this, 'validate_multi_select'], 5, 2);
		add_filter('wpcf7_validate_sector_single*', [$this, 'validate_select'], 5, 2);
		add_filter('wpcf7_validate_sector_multi*', [$this, 'validate_multi_select'], 5, 2);
	}

	public function register_form_tags() {
		// Register single and multi-select tags with and without required
		$tags = [
			'language_single' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'language_single*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'language_multi' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'language_multi*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'country_single' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'country_single*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'country_multi' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'country_multi*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'industry_single' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'industry_single*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'industry_multi' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'industry_multi*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'sector_single' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'sector_single*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'sector_multi' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false],
			'sector_multi*' => ['name-attr' => true, 'do-not-store' => false, 'not-for-mail' => false]
		];

		foreach ($tags as $tag => $options) {
			wpcf7_add_form_tag($tag, [$this, 'form_tag_handler'], $options);
		}
	}

	public function form_tag_handler($tag) {
		$tag = new WPCF7_FormTag($tag);
		$name = $tag->name;
		$taxonomy = $this->get_taxonomy_from_tag($tag->type);
		$is_multi = strpos($tag->type, '_multi') !== false;
		$is_required = $tag->is_required();

		// Get attributes
		$id = $tag->get_id_option();
		$classes = $tag->get_class_option();
		$placeholder = '';
		foreach ($tag->raw_values as $raw_value) {
			if (preg_match('/^placeholder:"(.+)"$/', $raw_value, $matches)) {
				$placeholder = $matches[1];
				break;
			}
		}

		// Fetch taxonomy terms
		$terms = get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		]);

		if (is_wp_error($terms)) {
			return '<p>Error loading ' . esc_html($taxonomy) . ' options.</p>';
		}

		// Generate select field with wrapper
		$html = '<span class="wpcf7-form-control-wrap" data-name="' . esc_attr($name) . '">';
		$html .= '<select';
		$html .= ' name="' . esc_attr($name) . ($is_multi ? '[]' : '') . '"';
		if ($id) {
			$html .= ' id="' . esc_attr($id) . '"';
		}
		$html .= ' class="wpcf7-form-control ' . esc_attr($classes) . '"';
		$html .= ' data-name="' . esc_attr($name) . '"';
		if ($is_multi) {
			$html .= ' multiple';
		}
		if ($is_required) {
			$html .= ' required';
		}
		$html .= '>';
		
		// Add placeholder as first option
		$html .= '<option value=""';
		if ($placeholder) {
			$html .= ($is_multi ? '>' : ' disabled selected>') . esc_html($placeholder);
		} else {
			$html .= ($is_multi ? '>' : ' disabled selected>') . esc_html('Select ' . ucfirst($taxonomy));
		}
		$html .= '</option>';

		foreach ($terms as $term) {
			$html .= '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
		}

		$html .= '</select>';
		$html .= '</span>';

		return $html;
	}

	public function validate_select($result, $tag) {
		$tag = new WPCF7_FormTag($tag);
		$name = $tag->name;
		$value = isset($_POST[$name]) ? (string) $_POST[$name] : '';

		// Debug logging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('ORU_CF7_Fields: validate_select called for ' . $name . ', value: ' . print_r($value, true) . ', is AJAX: ' . (wp_doing_ajax() ? 'yes' : 'no'));
			error_log('ORU_CF7_Fields: POST data: ' . print_r($_POST, true));
		}

		if ($tag->is_required() && empty($value)) {
			$result->invalidate($tag, wpcf7_get_message('invalid_required'));
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('ORU_CF7_Fields: Invalidated ' . $name . ' for required field');
			}
		}

		return $result;
	}

	public function validate_multi_select($result, $tag) {
		$tag = new WPCF7_FormTag($tag);
		$name = $tag->name;
		$value = isset($_POST[$name]) ? (array) $_POST[$name] : [];

		// Debug logging
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('ORU_CF7_Fields: validate_multi_select called for ' . $name . ', value: ' . print_r($value, true) . ', is AJAX: ' . (wp_doing_ajax() ? 'yes' : 'no'));
			error_log('ORU_CF7_Fields: POST data: ' . print_r($_POST, true));
		}

		if ($tag->is_required() && empty($value)) {
			$result->invalidate($tag, wpcf7_get_message('invalid_required'));
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('ORU_CF7_Fields: Invalidated ' . $name . ' for required field');
			}
		}

		return $result;
	}

	private function get_taxonomy_from_tag($tag_type) {
		// Map tag type to taxonomy
		$map = [
			'language_single' => 'language',
			'language_single*' => 'language',
			'language_multi' => 'language',
			'language_multi*' => 'language',
			'country_single' => 'country',
			'country_single*' => 'country',
			'country_multi' => 'country',
			'country_multi*' => 'country',
			'industry_single' => 'industry',
			'industry_single*' => 'industry',
			'industry_multi' => 'industry',
			'industry_multi*' => 'industry',
			'sector_single' => 'sector',
			'sector_single*' => 'sector',
			'sector_multi' => 'sector',
			'sector_multi*' => 'sector'
		];

		return $map[$tag_type] ?? '';
	}

	public function register_tag_generator() {
		if (!function_exists('wpcf7_add_tag_generator')) {
			return;
		}

		$tag_generator = WPCF7_TagGenerator::get_instance();
		$taxonomies = [
			'language' => 'Language',
			'country' => 'Country',
			'industry' => 'Industry',
			'sector' => 'Sector'
		];

		foreach ($taxonomies as $taxonomy => $label) {
			// Single select tag
			$tag_generator->add(
				$taxonomy . '_single',
				$label . ' Single Select',
				[$this, 'tag_generator_single'],
				['name-attr' => true]
			);
			// Multi-select tag
			$tag_generator->add(
				$taxonomy . '_multi',
				$label . ' Multi Select',
				[$this, 'tag_generator_multi'],
				['name-attr' => true]
			);
		}
	}

	public function enqueue_tag_generator_script() {
		// Enqueue vanilla JavaScript for quoted placeholders and required field
		wp_add_inline_script(
			'wpcf7-admin',
			'document.addEventListener("click", function(event) {
				if (event.target.classList.contains("insert-tag")) {
					var input = event.target.closest(".insert-box").querySelector(".tag");
					var tag = input.value;
					var placeholderInput = event.target.closest(".control-box").querySelector("input[name=placeholder]");
					var requiredInput = event.target.closest(".control-box").querySelector("input[name=required]");
					if (placeholderInput && placeholderInput.value) {
						tag = tag.replace(/ placeholder:[^ ]*/, "");
						tag = tag.replace(/]$/, " placeholder \"" + placeholderInput.value.replace(/"/g, "\\\"") + "\"]");
					}
					if (requiredInput && requiredInput.checked) {
						tag = tag.replace(/\[([^\s]+)/, "[$1*");
					}
					input.value = tag;
				}
			});'
		);
	}

	public function enqueue_validation_script() {
		// Enqueue vanilla JavaScript for acceptance checkbox validation
		wp_add_inline_script(
			'wpcf7',
			'document.addEventListener("change", function(event) {
				if (event.target.classList.contains("wpcf7-acceptance")) {
					var form = event.target.closest("form.wpcf7-form");
					if (form) {
						// Trigger full form validation
						var inputs = form.querySelectorAll("select[required]");
						inputs.forEach(function(input) {
							if (!input.value || (input.multiple && input.selectedOptions.length === 0)) {
								var wrap = input.closest(".wpcf7-form-control-wrap");
								if (wrap) {
									var error = wrap.querySelector(".wpcf7-not-valid-tip");
									if (!error) {
										error = document.createElement("span");
										error.className = "wpcf7-not-valid-tip";
										error.textContent = "The field is required";
										wrap.appendChild(error);
									}
								}
							} else {
								var wrap = input.closest(".wpcf7-form-control-wrap");
								if (wrap) {
									var error = wrap.querySelector(".wpcf7-not-valid-tip");
									if (error) {
										error.remove();
									}
								}
							}
						});
						var event = new Event("wpcf7:validate", { bubbles: true });
						form.dispatchEvent(event);
						console.log("ORU_CF7_Fields: Dispatched wpcf7:validate for acceptance checkbox");
					}
				}
			});'
		);
	}

	public function tag_generator_single($contact_form, $args = '') {
		$args = wp_parse_args($args, []);
		$type = $args['id'];
		$taxonomy = str_replace('oru-cf7-', '', $type);
		$taxonomy = str_replace('-single', '', $taxonomy);
		?>
		<div class="control-box">
			<fieldset>
				<legend><?php echo esc_html("Generate a form-tag for a single-select $taxonomy field."); ?></legend>
				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-name'); ?>">Name</label></th>
							<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr($args['content'] . '-name'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-id'); ?>">Id</label></th>
							<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr($args['content'] . '-id'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-class'); ?>">Class</label></th>
							<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr($args['content'] . '-class'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-placeholder'); ?>">Placeholder</label></th>
							<td><input type="text" name="placeholder" class="oneline option" id="<?php echo esc_attr($args['content'] . '-placeholder'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-required'); ?>">Required field</label></th>
							<td><input type="checkbox" name="required" class="option" id="<?php echo esc_attr($args['content'] . '-required'); ?>" /></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
			<div class="insert-box">
				<input type="text" name="<?php echo esc_attr($taxonomy . '_single'); ?>" class="tag code" readonly="readonly" onfocus="this.select()" />
				<div class="submitbox">
					<input type="button" class="button button-primary insert-tag" value="Insert Tag" />
				</div>
			</div>
		</div>
		<?php
	}

	public function tag_generator_multi($contact_form, $args = '') {
		$args = wp_parse_args($args, []);
		$type = $args['id'];
		$taxonomy = str_replace('oru-cf7-', '', $type);
		$taxonomy = str_replace('-multi', '', $taxonomy);
		?>
		<div class="control-box">
			<fieldset>
				<legend><?php echo esc_html("Generate a form-tag for a multi-select $taxonomy field."); ?></legend>
				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-name'); ?>">Name</label></th>
							<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr($args['content'] . '-name'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-id'); ?>">Id</label></th>
							<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr($args['content'] . '-id'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-class'); ?>">Class</label></th>
							<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr($args['content'] . '-class'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-placeholder'); ?>">Placeholder</label></th>
							<td><input type="text" name="placeholder" class="oneline option" id="<?php echo esc_attr($args['content'] . '-placeholder'); ?>" /></td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr($args['content'] . '-required'); ?>">Required field</label></th>
							<td><input type="checkbox" name="required" class="option" id="<?php echo esc_attr($args['content'] . '-required'); ?>" /></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
			<div class="insert-box">
				<input type="text" name="<?php echo esc_attr($taxonomy . '_multi'); ?>" class="tag code" readonly="readonly" onfocus="this.select()" />
				<div class="submitbox">
					<input type="button" class="button button-primary insert-tag" value="Insert Tag" />
				</div>
			</div>
		</div>
		<?php
	}
}

// Instantiate the class
new ORU_CF7_Fields();
?>
