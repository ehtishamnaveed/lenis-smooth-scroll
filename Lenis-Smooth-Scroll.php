<?php
/**
 * Plugin Name: Lenis Smooth Scroll
 * Plugin URI: https://github.com/darkroomengineering/lenis
 * Description: Adds smooth scrolling to your WordPress site using Lenis library
 * Version: 1.0.0
 * Author: Your Name
 * License: MIT
 * Text Domain: lenis-smooth-scroll
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LENIS_VERSION', '1.3.19');
define('LENIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LENIS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Main plugin class
class Lenis_Smooth_Scroll {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('lenis-smooth-scroll', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Get settings
        $options = get_option('lenis_settings', $this->get_default_settings());
        
        // Only load if enabled
        if (empty($options['enabled'])) {
            return;
        }
        
        // Check if we should load on specific pages
        if (!$this->should_load_on_current_page($options)) {
            return;
        }
        
        // Enqueue Lenis CSS
        if (!empty($options['load_css'])) {
            wp_enqueue_style(
                'lenis-css',
                'https://unpkg.com/lenis@' . LENIS_VERSION . '/dist/lenis.css',
                array(),
                LENIS_VERSION
            );
        }
        
        // Enqueue Lenis JS
        wp_enqueue_script(
            'lenis-js',
            'https://unpkg.com/lenis@' . LENIS_VERSION . '/dist/lenis.min.js',
            array(),
            LENIS_VERSION,
            true
        );
        
        // Add initialization script
        wp_add_inline_script('lenis-js', $this->get_initialization_script($options));
        
        // Add CSS overrides if needed
        if (!empty($options['disable_overflow'])) {
            wp_add_inline_style('lenis-css', $this->get_overflow_css());
        }
    }
    
    private function get_initialization_script($options) {
        $config = array();
        
        // Build config object based on settings
        $config['autoRaf'] = true;
        $config['smoothWheel'] = !empty($options['smooth_wheel']);
        
        if (!empty($options['duration'])) {
            $config['duration'] = floatval($options['duration']);
        }
        
        if (!empty($options['lerp'])) {
            $config['lerp'] = floatval($options['lerp']);
        }
        
        if (!empty($options['easing']) && $options['easing'] !== 'default') {
            $config['easing'] = $this->get_easing_function($options['easing']);
        }
        
        if (!empty($options['orientation'])) {
            $config['orientation'] = $options['orientation'];
        }
        
        if (!empty($options['wheel_multiplier'])) {
            $config['wheelMultiplier'] = floatval($options['wheel_multiplier']);
        }
        
        if (!empty($options['touch_multiplier'])) {
            $config['touchMultiplier'] = floatval($options['touch_multiplier']);
        }
        
        if (!empty($options['sync_touch'])) {
            $config['syncTouch'] = true;
        }
        
        if (!empty($options['anchors'])) {
            $config['anchors'] = true;
        }
        
        $config_json = json_encode($config, JSON_PRETTY_PRINT);
        
        $script = "
            document.addEventListener('DOMContentLoaded', function() {
                const lenis = new Lenis({$config_json});
                
                // Animation frame loop
                function raf(time) {
                    lenis.raf(time);
                    requestAnimationFrame(raf);
                }
                requestAnimationFrame(raf);
        ";
        
        // Add GSAP ScrollTrigger integration if enabled
        if (!empty($options['gsap_integration']) && wp_script_is('gsap', 'enqueued')) {
            $script .= "
                // GSAP ScrollTrigger integration
                if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
                    lenis.on('scroll', ScrollTrigger.update);
                    gsap.ticker.add((time) => {
                        lenis.raf(time * 1000);
                    });
                    gsap.ticker.lagSmoothing(0);
                }
            ";
        }
        
        $script .= "
            });
        ";
        
        return $script;
    }
    
    private function get_easing_function($easing) {
        $easing_functions = array(
            'linear' => '(t) => t',
            'easeInQuad' => '(t) => t * t',
            'easeOutQuad' => '(t) => t * (2 - t)',
            'easeInOutQuad' => '(t) => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t',
            'easeInCubic' => '(t) => t * t * t',
            'easeOutCubic' => '(t) => (--t) * t * t + 1',
            'easeInOutCubic' => '(t) => t < 0.5 ? 4 * t * t * t : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1',
        );
        
        return isset($easing_functions[$easing]) ? $easing_functions[$easing] : '(t) => Math.min(1, 1.001 - Math.pow(2, -10 * t))';
    }
    
    private function get_overflow_css() {
        return "
            html.lenis {
                height: auto;
            }
            .lenis.lenis-smooth {
                scroll-behavior: auto;
            }
            .lenis.lenis-smooth [data-lenis-prevent] {
                overscroll-behavior: contain;
            }
            .lenis.lenis-stopped {
                overflow: hidden;
            }
        ";
    }
    
    private function should_load_on_current_page($options) {
        // Load on all pages by default
        if (empty($options['load_on'])) {
            return true;
        }
        
        $load_on = $options['load_on'];
        $current_url = $_SERVER['REQUEST_URI'];
        $current_page_id = get_the_ID();
        
        switch ($load_on) {
            case 'home':
                return is_front_page() || is_home();
            case 'specific':
                if (!empty($options['specific_pages']) && is_page($options['specific_pages'])) {
                    return true;
                }
                return false;
            case 'exclude':
                if (!empty($options['exclude_pages']) && is_page($options['exclude_pages'])) {
                    return false;
                }
                return true;
            default:
                return true;
        }
    }
    
    private function get_default_settings() {
        return array(
            'enabled' => true,
            'load_css' => true,
            'smooth_wheel' => true,
            'duration' => 1.2,
            'lerp' => '',
            'easing' => 'default',
            'orientation' => 'vertical',
            'wheel_multiplier' => 1,
            'touch_multiplier' => 1,
            'sync_touch' => false,
            'anchors' => true,
            'gsap_integration' => false,
            'disable_overflow' => true,
            'load_on' => 'all',
            'specific_pages' => array(),
            'exclude_pages' => array(),
        );
    }
    
    public function register_settings() {
        register_setting('lenis_settings_group', 'lenis_settings', array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings($input) {
        $defaults = $this->get_default_settings();
        
        // Boolean fields
        $boolean_fields = array('enabled', 'load_css', 'smooth_wheel', 'sync_touch', 'anchors', 'gsap_integration', 'disable_overflow');
        foreach ($boolean_fields as $field) {
            $input[$field] = !empty($input[$field]);
        }
        
        // Numeric fields
        if (isset($input['duration']) && is_numeric($input['duration'])) {
            $input['duration'] = floatval($input['duration']);
        } else {
            $input['duration'] = $defaults['duration'];
        }
        
        if (isset($input['lerp']) && is_numeric($input['lerp'])) {
            $input['lerp'] = floatval($input['lerp']);
        }
        
        if (isset($input['wheel_multiplier']) && is_numeric($input['wheel_multiplier'])) {
            $input['wheel_multiplier'] = floatval($input['wheel_multiplier']);
        }
        
        if (isset($input['touch_multiplier']) && is_numeric($input['touch_multiplier'])) {
            $input['touch_multiplier'] = floatval($input['touch_multiplier']);
        }
        
        return $input;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Lenis Smooth Scroll Settings', 'lenis-smooth-scroll'),
            __('Lenis Scroll', 'lenis-smooth-scroll'),
            'manage_options',
            'lenis-smooth-scroll',
            array($this, 'render_settings_page')
        );
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=lenis-smooth-scroll') . '">' . __('Settings', 'lenis-smooth-scroll') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function render_settings_page() {
        $options = get_option('lenis_settings', $this->get_default_settings());
        ?>
        <div class="wrap">
            <h1><?php _e('Lenis Smooth Scroll Settings', 'lenis-smooth-scroll'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('lenis_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Smooth Scroll', 'lenis-smooth-scroll'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lenis_settings[enabled]" value="1" <?php checked($options['enabled']); ?>>
                                <?php _e('Enable Lenis smooth scrolling', 'lenis-smooth-scroll'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Basic Settings', 'lenis-smooth-scroll'); ?></th>
                        <td>
                            <label>
                                <?php _e('Duration (seconds):', 'lenis-smooth-scroll'); ?><br>
                                <input type="number" name="lenis_settings[duration]" value="<?php echo esc_attr($options['duration']); ?>" step="0.1" min="0.1" max="5">
                                <p class="description"><?php _e('Scroll animation duration (default: 1.2)', 'lenis-smooth-scroll'); ?></p>
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Lerp intensity:', 'lenis-smooth-scroll'); ?><br>
                                <input type="number" name="lenis_settings[lerp]" value="<?php echo esc_attr($options['lerp']); ?>" step="0.01" min="0" max="1" placeholder="Auto">
                                <p class="description"><?php _e('Leave empty to use duration (0-1, lower = smoother)', 'lenis-smooth-scroll'); ?></p>
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Easing function:', 'lenis-smooth-scroll'); ?><br>
                                <select name="lenis_settings[easing]">
                                    <option value="default" <?php selected($options['easing'], 'default'); ?>>Default (exponential)</option>
                                    <option value="linear" <?php selected($options['easing'], 'linear'); ?>>Linear</option>
                                    <option value="easeInQuad" <?php selected($options['easing'], 'easeInQuad'); ?>>Ease In Quad</option>
                                    <option value="easeOutQuad" <?php selected($options['easing'], 'easeOutQuad'); ?>>Ease Out Quad</option>
                                    <option value="easeInOutQuad" <?php selected($options['easing'], 'easeInOutQuad'); ?>>Ease In Out Quad</option>
                                    <option value="easeInCubic" <?php selected($options['easing'], 'easeInCubic'); ?>>Ease In Cubic</option>
                                    <option value="easeOutCubic" <?php selected($options['easing'], 'easeOutCubic'); ?>>Ease Out Cubic</option>
                                    <option value="easeInOutCubic" <?php selected($options['easing'], 'easeInOutCubic'); ?>>Ease In Out Cubic</option>
                                </select>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Advanced Settings', 'lenis-smooth-scroll'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lenis_settings[smooth_wheel]" value="1" <?php checked($options['smooth_wheel']); ?>>
                                <?php _e('Smooth mouse wheel scrolling', 'lenis-smooth-scroll'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox" name="lenis_settings[anchors]" value="1" <?php checked($options['anchors']); ?>>
                                <?php _e('Enable anchor links (# links)', 'lenis-smooth-scroll'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox" name="lenis_settings[sync_touch]" value="1" <?php checked($options['sync_touch']); ?>>
                                <?php _e('Sync touch devices (experimental)', 'lenis-smooth-scroll'); ?>
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Scroll orientation:', 'lenis-smooth-scroll'); ?><br>
                                <select name="lenis_settings[orientation]">
                                    <option value="vertical" <?php selected($options['orientation'], 'vertical'); ?>>Vertical</option>
                                    <option value="horizontal" <?php selected($options['orientation'], 'horizontal'); ?>>Horizontal</option>
                                </select>
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Wheel multiplier:', 'lenis-smooth-scroll'); ?><br>
                                <input type="number" name="lenis_settings[wheel_multiplier]" value="<?php echo esc_attr($options['wheel_multiplier']); ?>" step="0.1" min="0.1" max="5">
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Touch multiplier:', 'lenis-smooth-scroll'); ?><br>
                                <input type="number" name="lenis_settings[touch_multiplier]" value="<?php echo esc_attr($options['touch_multiplier']); ?>" step="0.1" min="0.1" max="5">
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Integration', 'lenis-smooth-scroll'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lenis_settings[gsap_integration]" value="1" <?php checked($options['gsap_integration']); ?>>
                                <?php _e('Enable GSAP ScrollTrigger integration', 'lenis-smooth-scroll'); ?>
                                <p class="description"><?php _e('Only enable if GSAP is already loaded on your site', 'lenis-smooth-scroll'); ?></p>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Loading Options', 'lenis-smooth-scroll'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lenis_settings[load_css]" value="1" <?php checked($options['load_css']); ?>>
                                <?php _e('Load Lenis CSS', 'lenis-smooth-scroll'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox" name="lenis_settings[disable_overflow]" value="1" <?php checked($options['disable_overflow']); ?>>
                                <?php _e('Add CSS to prevent overflow issues', 'lenis-smooth-scroll'); ?>
                            </label>
                            <br><br>
                            <label>
                                <?php _e('Load on:', 'lenis-smooth-scroll'); ?><br>
                                <select name="lenis_settings[load_on]">
                                    <option value="all" <?php selected($options['load_on'], 'all'); ?>>All pages</option>
                                    <option value="home" <?php selected($options['load_on'], 'home'); ?>>Home page only</option>
                                    <option value="specific" <?php selected($options['load_on'], 'specific'); ?>>Specific pages</option>
                                    <option value="exclude" <?php selected($options['load_on'], 'exclude'); ?>>Exclude specific pages</option>
                                </select>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
Lenis_Smooth_Scroll::get_instance();